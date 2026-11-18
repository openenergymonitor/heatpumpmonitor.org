#!/usr/bin/env python3
"""The design model against manufacturer SCOP and MCS 031, scored on the same
systems in the same way (docs/design_model.md, "Against SCOP and MCS 031").

SCOP and MCS 031 predictions and measured SPF H4 come from
scop_fleet_comparison.csv (scripts/scop_fleet_comparison.py). The design
model is run on the same systems from the declared design-sheet fields in an
all-fields export. SCOP and MCS 031 were given each system's measured
space/water split, so the design model is also run with the measured hot
water share, as well as the declared one.

Usage: .venv/bin/python scripts/design_vs_scop.py [heatpumpmonitor_all_fields_*.csv]
"""

import sys

import numpy as np
import pandas as pd

from design_model import design_hstar, WEATHER, RAD_EXP, DEFROST_D
from hpmon_analysis import load, standard_filter, find_csv, ROOT
from residual_analysis import num
from scop_mcs031_comparison import loo_predict, score

ROOM, DT_SYS, ETA = 20.0, 5.0, 0.47
W_REF = np.percentile(WEATHER, 0.4)


def design_inputs(f):
    hl = num(f.heat_loss)
    hl = hl.where(hl < 100, hl / 1000).where(lambda v: (v > 0.5) & (v < 30))
    mhl = num(f.measured_heat_loss).where(lambda v: (v > 0.5) & (v < 30))
    hd = num(f.heat_demand).where(lambda v: v > 1000)
    wd = num(f.water_heat_demand).where(lambda v: v > 0)
    share_decl = (wd / hd).where(lambda v: (v > 0.01) & (v < 0.6))
    sp, wa = num(f.space_heat_kwh), num(f.water_heat_kwh)
    return pd.DataFrame({
        "id": f.id,
        "hl": hl, "hl_corr": mhl.fillna(hl * 0.76),
        "dtemp": num(f.design_temp).where(lambda v: (v > -15) & (v < 6)),
        "ftemp": num(f.flow_temp).where(lambda v: (v >= 25) & (v <= 70)),
        "cap": num(f.hp_output).where(lambda v: (v > 1) & (v < 30)) * 1000,
        "dhwt": num(f.dhw_target_temperature).where(lambda v: (v >= 35) & (v <= 65)).fillna(48.0),
        "share_decl": share_decl.fillna(share_decl.median()),
        "share_meas": (wa / (sp + wa)).where((sp > 0) & (wa > 0)),
        "weighted_dT": num(f.weighted_flowT_minus_outsideT),
        "cold_lift": (num(f.measured_mean_flow_temp_coldest_day).where(lambda v: (v > 25) & (v < 65))
                      - num(f.measured_outside_temp_coldest_day).where(lambda v: (v > -15) & (v < 8))),
    })


def design_spf(r, hl_col, share_col):
    """0.47 x H*_design with defrost, curve from the declared design flow temp."""
    hl, share = r[hl_col], r[share_col]
    if np.isnan([hl, r.dtemp, r.ftemp, r.cap, share]).any():
        return np.nan
    u = hl * 1000 / (ROOM - r.dtemp)
    mwt = r.ftemp - DT_SYS / 2
    if mwt <= ROOM + 2:
        return np.nan
    rad50 = hl * 1000 / ((mwt - ROOM) / 50) ** RAD_EXP
    return ETA * design_hstar(u, ROOM, rad50, DT_SYS, r.cap, share, r.dhwt,
                              defrost_D=DEFROST_D, weather=WEATHER + (r.dtemp - W_REF))


def main():
    args = [a for a in sys.argv[1:] if not a.startswith("--")]
    export = args[0] if args else find_csv()
    f = standard_filter(load(export), verbose=False)
    x = design_inputs(f[f.combined_cop.notna()].reset_index(drop=True))
    x["design_decl"] = x.apply(design_spf, axis=1, args=("hl", "share_decl"))
    x["design_meas"] = x.apply(design_spf, axis=1, args=("hl", "share_meas"))
    x["design_meas_corr"] = x.apply(design_spf, axis=1, args=("hl_corr", "share_meas"))

    s = pd.read_csv(ROOT / "scop_fleet_comparison.csv")
    s = s[s.scop_design.notna() & s.mcs031.notna()]
    cols = ["design_decl", "design_meas", "design_meas_corr", "cold_lift", "weighted_dT"]
    m = s.merge(x[["id"] + cols], on="id").dropna(subset=cols).reset_index(drop=True)
    y = m.actual.to_numpy()
    print(f"{len(m)} of the {len(s)} systems scored in scop_fleet_comparison.csv; "
          f"measured SPF H4 mean {y.mean():.2f}")

    methods = [("scop_design", "Manufacturer SCOP at design flow"),
               ("mcs031", "MCS 031"),
               ("design_decl", "Design model, declared DHW share"),
               ("design_meas", "Design model, measured DHW share"),
               ("design_meas_corr", "  + heat loss corrected"),
               ("cold_lift", "Coldest-day lift (one measured day)"),
               ("weighted_dT", "Weighted dT (a year of monitoring)")]
    print(f"\n{'':36s} {'mean':>5s} {'bias':>6s} {'R2':>6s} {'r2 resc':>8s} {'LOO R2':>7s} {'+-90%':>6s}")
    for col, label in methods:
        v = m[col].to_numpy()
        loo = score(loo_predict(v[:, None], y), y)
        raw = "" if col in ("weighted_dT", "cold_lift") else (lambda r: f"{v.mean():5.2f} {r['bias']:+6.2f} {r['R2']:+6.2f}")(score(v, y))
        print(f"{label:36s} {raw:>19s} {np.corrcoef(v, y)[0, 1]**2:8.2f} {loo['R2']:7.2f} {loo['pi90']:6.2f}")

    # is SCOP's ranking edge real? bootstrap the difference in r2
    rng = np.random.default_rng(1)
    r2 = lambda a, b: np.corrcoef(a, b)[0, 1] ** 2
    print("\nSCOP minus other method, ranking r2 (bootstrap, 4000 resamples):")
    for col, label in methods[1:4]:
        d = []
        for _ in range(4000):
            mm = m.iloc[rng.integers(0, len(m), len(m))]
            d.append(r2(mm.scop_design, mm.actual) - r2(mm[col], mm.actual))
        print(f"  {label:36s} {np.median(d):+.2f}  95% CI {np.percentile(d, 2.5):+.2f} to {np.percentile(d, 97.5):+.2f}")
    print(f"\ncorrelation, SCOP vs design model (measured share): "
          f"{np.corrcoef(m.scop_design, m.design_meas)[0, 1]:.2f}")


if __name__ == "__main__":
    main()
