#!/usr/bin/env python3
"""Manufacturer SCOP and MCS 031 against measured SPF H4 across every model
with an MCS SCOP table on the site, using the site's own calculation.

Data comes from the site: system/list/public.json (meta, including the stored
scop_spf and mcs031_spf), system/stats/last365 (measured) and heatpump/list
(each model's chosen MCS SCOP table). The joined rows are saved as
heatpumpmonitor_scop_fleet_<date>.csv and reused on later runs; pass --fetch
to refresh.

The SCOP method is recomputed here as on the site (DesignEstimates::scop):
SCOP at the design flow temperature for space heating and at the hot water
target + 5 K (50 °C target where blank), interpolated, no extrapolation above
the highest declared temperature. The result is checked against the stored
scop_spf.

Usage: python scop_fleet_comparison.py [--fetch] [--url https://heatpumpmonitor.org]
"""

import datetime
import glob
import json
import sys
import urllib.request

import numpy as np
import pandas as pd

from hpmon_analysis import ROOT, DAY
from scop_mcs031_comparison import loo_predict, score

UA = {"User-Agent": "Mozilla/5.0"}


def get_json(url):
    return json.loads(urllib.request.urlopen(urllib.request.Request(url, headers=UA), timeout=120).read())


def fetch(base):
    meta = pd.DataFrame(get_json(f"{base}/system/list/public.json"))
    stats = pd.DataFrame(get_json(f"{base}/system/stats/last365").values())
    models = pd.DataFrame(get_json(f"{base}/heatpump/list"))
    models = models[models.mcs_scop.notna()][["id", "manufacturer_name", "name", "capacity",
                                               "mcs_model_number", "mcs_scop"]]
    models = models.rename(columns={"id": "heatpump_model_id", "name": "model_name"})

    meta_cols = ["id", "hp_manufacturer", "hp_model", "hp_output", "hp_type", "hp_hybrid", "refrigerant",
                 "flow_temp", "dhw_target_temperature", "mid_metering", "metering_boundary_code",
                 "heatpump_model_id", "mcs031_space_spf", "mcs031_spf", "scop_space", "scop_spf"]
    stats_cols = ["id", "combined_cop", "combined_data_length", "combined_heat_kwh", "space_heat_kwh",
                  "water_heat_kwh", "cooling_heat_kwh", "space_cop", "water_cop", "space_flowT_mean",
                  "water_flowT_mean", "weighted_flowT_minus_outsideT"]
    df = meta[meta_cols].merge(stats[stats_cols], on="id")
    df["heatpump_model_id"] = pd.to_numeric(df.heatpump_model_id, errors="coerce")
    models["heatpump_model_id"] = models.heatpump_model_id.astype(float)
    return df.merge(models, on="heatpump_model_id", how="left")


def load(refresh, base):
    existing = sorted(glob.glob(str(ROOT / "heatpumpmonitor_scop_fleet_*.csv")))
    if existing and not refresh:
        return pd.read_csv(existing[-1]), existing[-1]
    df = fetch(base)
    path = ROOT / f"heatpumpmonitor_scop_fleet_{datetime.date.today()}.csv"
    df.to_csv(path, index=False)
    return df, str(path)


def scop_at(table, flow):
    """As the site: 35 °C value below 35, interpolated, None above the table."""
    temps = sorted(table)
    if flow > temps[-1]:
        return None
    return float(np.interp(flow, temps, [table[t] for t in temps]))


def main():
    base = sys.argv[sys.argv.index("--url") + 1] if "--url" in sys.argv else "https://heatpumpmonitor.org"
    df, path = load("--fetch" in sys.argv, base)
    # dhw_target_temperature was out of the schema until Oct 2026, so older snapshots have it as text
    df["dhw_target_temperature"] = pd.to_numeric(df.dhw_target_temperature, errors="coerce").fillna(0)
    print(f"{path}: {len(df)} public systems, {df.mcs_scop.notna().sum()} linked to a model with a SCOP table")

    # Standard filter, as hpmon_analysis.standard_filter
    fleet = df[(df.metering_boundary_code == 4) & (df.hp_type == "Air Source") & (df.mid_metering == 1)
               & (df.cooling_heat_kwh.abs() < 1) & (df.combined_data_length >= 330 * DAY)
               & df.combined_cop.notna() & df.weighted_flowT_minus_outsideT.notna()].copy()
    fleet["dT"] = loo_predict(fleet[["weighted_flowT_minus_outsideT"]].to_numpy(), fleet.combined_cop.to_numpy())

    rows = []
    for _, s in fleet[fleet.mcs_scop.notna() & (fleet.hp_hybrid != 1) & (fleet.flow_temp > 0)].iterrows():
        table = {int(k): float(v) for k, v in json.loads(s.mcs_scop).items() if float(v) > 0}
        dhw_target = s.dhw_target_temperature if s.dhw_target_temperature > 0 else 50.0
        sh = scop_at(table, s.flow_temp)
        dhw = scop_at(table, dhw_target + 5)
        metered = s.space_heat_kwh > 0 and s.water_heat_kwh > 0
        blend = None
        if sh is not None and dhw is not None and metered:
            blend = (s.space_heat_kwh + s.water_heat_kwh) / (s.space_heat_kwh / sh + s.water_heat_kwh / dhw)
        rows.append({
            "id": s.id, "family": f"{s.manufacturer_name} {s.model_name}", "kw": s.capacity,
            "design_flow": s.flow_temp, "dhw_target": s.dhw_target_temperature, "dhw_assumed": not s.dhw_target_temperature > 0,
            "space_flow": s.space_flowT_mean, "water_flow": s.water_flowT_mean,
            "metered": metered, "water_share": s.water_heat_kwh / (s.space_heat_kwh + s.water_heat_kwh) if metered else np.nan,
            "actual": s.combined_cop, "space_cop": s.space_cop, "water_cop": s.water_cop,
            "scop_sh": sh, "scop_dhw": dhw, "scop_design": blend, "site_scop_spf": s.scop_spf,
            "scop_sh_measured": scop_at(table, max(s.space_flowT_mean, min(table))) if pd.notna(s.space_flowT_mean) else None,
            "mcs031": s.mcs031_spf if s.mcs031_spf > 0 else np.nan, "dT": s.dT,
        })
    r = pd.DataFrame(rows)
    r.to_csv(ROOT / "scop_fleet_comparison.csv", index=False, float_format="%.3f")

    print(f"\nfiltered fleet (H4, ASHP, MID, a year of data): {len(fleet)}")
    print(f"  with a SCOP model and design flow: {len(r)}")
    print(f"  no space SCOP (design flow above the table): {r.scop_sh.isna().sum()}")
    print(f"  no separate hot water metering: {(~r.metered).sum()}")
    print(f"  metered, but hot water target + 5 K above the table: {(r.metered & r.scop_sh.notna() & r.scop_dhw.isna()).sum()}")
    m = r[r.scop_design.notna() & r.mcs031.notna()].copy()
    print(f"  scored (combined SCOP and MCS 031 estimate): {len(m)}, of which DHW target assumed: {m.dhw_assumed.sum()}")

    check = m.site_scop_spf.notna() & (m.site_scop_spf > 0)
    print(f"  site scop_spf vs recomputed: max |diff| {np.abs(m.scop_design[check] - m.site_scop_spf[check]).max():.4f} over {check.sum()}")

    print(f"\nAll models (n = {len(m)}), measured SPF H4 mean {m.actual.mean():.2f}")
    print(f"  {'method':12s} {'mean':>5s} {'bias':>6s} {'MAE':>5s} {'R2':>6s} {'r2':>5s} {'±90%':>5s} {'±10%':>5s} {'beat':>5s}")
    for method in ["scop_design", "mcs031", "dT"]:
        sc = score(m[method].to_numpy(), m.actual.to_numpy())
        within = (np.abs(m[method] / m.actual - 1) <= 0.1).mean()
        beat = (m.actual > m[method]).mean()
        print(f"  {method:12s} {m[method].mean():5.2f} {sc['bias']:+6.2f} {sc['mae']:5.2f} {sc['R2']:6.2f} "
              f"{sc['r']**2:5.2f} {sc['pi90']:5.2f} {within:5.0%} {beat:5.0%}")

    print("\nBy model (n >= 5):")
    g = m.groupby("family").agg(n=("id", "size"), actual=("actual", "mean"), scop=("scop_design", "mean"),
                                mcs031=("mcs031", "mean"), design_flow=("design_flow", "median"),
                                space_flow=("space_flow", "median"))
    g["scop_bias"] = g.scop - g.actual
    g["scop_r"] = m.groupby("family").apply(lambda x: np.corrcoef(x.scop_design, x.actual)[0, 1] if len(x) > 2 else np.nan,
                                            include_groups=False)
    print(g[g.n >= 5].sort_values("n", ascending=False).round(2).to_string())
    small = g[g.n < 5]
    print(f"  other models: {small.n.sum()} systems over {len(small)} models, "
          f"measured {m[m.family.isin(small.index)].actual.mean():.2f}, scop {m[m.family.isin(small.index)].scop_design.mean():.2f}")

    print("\nSpace heating and hot water separately (scored systems):")
    sp = m[m.space_cop.notna()]
    print(f"  space COP measured {sp.space_cop.mean():.2f}, SCOP at design flow {sp.scop_sh.mean():.2f} "
          f"(r {np.corrcoef(sp.scop_sh, sp.space_cop)[0, 1]:.2f}), at measured flow {sp.scop_sh_measured.mean():.2f}")
    print(f"  design flow - measured space flow: median {(sp.design_flow - sp.space_flow).median():.1f} K, "
          f"measured space flow median {sp.space_flow.median():.1f} °C")
    wa = m[m.water_cop.notna()]
    print(f"  water COP measured {wa.water_cop.mean():.2f} (median {wa.water_cop.median():.2f}, p10-p90 "
          f"{wa.water_cop.quantile(0.1):.1f}-{wa.water_cop.quantile(0.9):.1f}), SCOP at target + 5 K {wa.scop_dhw.mean():.2f} "
          f"(r {np.corrcoef(wa.scop_dhw, wa.water_cop)[0, 1]:.2f})")

    print("\nArotherm+ against the other models:")
    for label, sub in [("Arotherm+", m[m.family == "Vaillant Arotherm+"]), ("other models", m[m.family != "Vaillant Arotherm+"])]:
        sc = score(sub.scop_design.to_numpy(), sub.actual.to_numpy())
        mc = score(sub.mcs031.to_numpy(), sub.actual.to_numpy())
        print(f"  {label:13s} n {len(sub):3d}  measured {sub.actual.mean():.2f}  scop {sub.scop_design.mean():.2f} "
              f"(bias {sc['bias']:+.2f}, r2 {sc['r']**2:.2f})  mcs031 {sub.mcs031.mean():.2f} (bias {mc['bias']:+.2f})")


if __name__ == "__main__":
    main()
