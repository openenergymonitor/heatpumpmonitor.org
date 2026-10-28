#!/usr/bin/env python3
"""Compare industry SPF predictions with measured SPF H4 on the fleet's Arotherm+ systems.

Methods (all given the measured annual space and water heat, so only the
efficiency side is tested):

  scop_design   manufacturer SCOP (MCS product directory, average climate) at the
                declared design flow temperature, hot water at SCOP(DHW target + 5 K).
                Missing DHW target: 50 °C.
  mcs031        MCS 031 a) issue 5.0 Table 2 generic ASHP SPF at the design flow
                temperature, hot water / 1.9, plus the pasteurisation immersion term
                where the form says legionella is done by immersion.
  scop_measured manufacturer SCOP at the measured mean space and water flow
                temperatures (clamped to the table's 35-55 °C range, extrapolated
                above 55 from the 50-55 slope). Shows the method with correct inputs.
  dT, dT_heat   our fleet fits (README section 1-2), leave-one-out over the full
                filtered fleet, so they need a year of monitoring.

Usage: python scop_mcs031_comparison.py
"""

import numpy as np
import pandas as pd

from hpmon_analysis import ROOT, DAY, find_csv, load, standard_filter

# MCS 031 a) issue 5.0, Appendix A Table 2, column 5 (ASHP). Same in every heat loss band.
MCS031_ASHP = [(35, 4.0), (40, 3.8), (45, 3.4), (50, 3.1), (55, 2.8), (60, 2.5), (65, 2.5)]
MCS031_DHW = 1.9
LEGIONELLA_PER_YEAR = {"Weekly": 52, "Fornightly": 26, "Monthly": 12, "Daily": 365}


def mcs031_spf(flow):
    for upper, spf in MCS031_ASHP:
        if np.ceil(flow) <= upper:
            return spf
    return MCS031_ASHP[-1][1]


def load_scop_tables():
    t = pd.read_csv(ROOT / "mcs_scop_arotherm_plus.csv")
    cols = [c for c in t.columns if c.startswith("scop_")]
    out = {}
    for _, r in t.iterrows():
        pts = [(int(c[5:]), r[c]) for c in cols if pd.notna(r[c])]
        out[float(r.hp_output)] = (np.array([p[0] for p in pts], float), np.array([p[1] for p in pts], float))
    return out


def scop_at(table, flow):
    temps, scop = table
    if flow > temps[-1]:
        slope = (scop[-1] - scop[-6]) / (temps[-1] - temps[-6])
        return scop[-1] + slope * (flow - temps[-1])
    return float(np.interp(flow, temps, scop))    # clamps below 35 °C


def blend(space, water, spf_space, spf_water, extra_elec=0.0):
    return (space + water) / (space / spf_space + water / spf_water + extra_elec)


def loo_predict(X, y):
    X = np.column_stack([np.ones(len(y)), X])
    pred = np.empty(len(y))
    for i in range(len(y)):
        m = np.arange(len(y)) != i
        beta, *_ = np.linalg.lstsq(X[m], y[m], rcond=None)
        pred[i] = X[i] @ beta
    return pred


def score(pred, actual):
    e = pred - actual
    r = np.corrcoef(pred, actual)[0, 1]
    r2 = 1 - np.sum(e**2) / np.sum((actual - actual.mean())**2)
    return {"n": len(e), "bias": e.mean(), "mae": np.abs(e).mean(), "rmse": np.sqrt(np.mean(e**2)),
            "r": r, "R2": r2, "pi90": 1.645 * e.std(ddof=1)}


def main():
    fleet = standard_filter(load(find_csv()), verbose=False)
    fleet = fleet[fleet.combined_cop.notna() & fleet.weighted_flowT_minus_outsideT.notna()].copy()
    years = fleet.combined_data_length / (365 * DAY)
    fleet["ln_heat"] = np.log(fleet.combined_heat_kwh / years / 1000)
    y = fleet.combined_cop.to_numpy()
    fleet["dT"] = loo_predict(fleet[["weighted_flowT_minus_outsideT"]].to_numpy(), y)
    fleet["dT_heat"] = loo_predict(fleet[["weighted_flowT_minus_outsideT", "ln_heat"]].to_numpy(), y)

    tables = load_scop_tables()
    a = fleet[(fleet.hp_model == "Arotherm+") & (fleet.flow_temp > 0)].copy()

    rows = []
    for _, s in a.iterrows():
        table = tables[float(s.hp_output)]
        dhw_target = s.dhw_target_temperature if s.dhw_target_temperature > 0 else 50.0
        space, water = s.space_heat_kwh, s.water_heat_kwh
        if space + water <= 0:    # no space/water split: treat it all as space heat
            space, water = s.combined_heat_kwh, 0.0
        immersion = 0.0
        if s.legionella_immersion == 1 and s.cylinder_volume > 0:
            f = LEGIONELLA_PER_YEAR.get(s.legionella_frequency, 52)
            immersion = f * s.cylinder_volume * 10 * 4200 / 3.6e6
        space_flow = s.space_flowT_mean if pd.notna(s.space_flowT_mean) else s.weighted_flowT
        water_flow = s.water_flowT_mean if pd.notna(s.water_flowT_mean) else dhw_target + 5
        rows.append({
            "id": s.id, "kw": s.hp_output, "design_flow": s.flow_temp, "dhw_target": s.dhw_target_temperature,
            "space_flow": space_flow, "water_flow": water_flow,
            "water_share": water / (space + water), "actual": s.combined_cop,
            "scop_sh": scop_at(table, s.flow_temp), "scop_dhw": scop_at(table, dhw_target + 5),
            "scop_design": blend(space, water, scop_at(table, s.flow_temp), scop_at(table, dhw_target + 5)),
            "mcs031": blend(space, water, mcs031_spf(s.flow_temp), MCS031_DHW, immersion),
            "scop_measured": blend(space, water, scop_at(table, space_flow), scop_at(table, water_flow)),
            "dT": s.dT, "dT_heat": s.dT_heat,
        })
    r = pd.DataFrame(rows)
    r.to_csv(ROOT / "scop_mcs031_comparison.csv", index=False, float_format="%.3f")

    methods = ["scop_design", "mcs031", "scop_measured", "dT", "dT_heat"]
    for label, sub in [("Arotherm+ with metered hot water", r[r.water_share > 0]),
                       ("all Arotherm+ with a design flow temperature", r)]:
        print(f"\n{label} (n = {len(sub)}), measured SPF H4 mean {sub.actual.mean():.2f}")
        print(f"  {'method':14s} {'mean':>5s} {'bias':>6s} {'MAE':>5s} {'RMSE':>5s} {'r':>5s} {'R2':>6s} {'±90%':>5s}")
        for m in methods:
            sc = score(sub[m].to_numpy(), sub.actual.to_numpy())
            print(f"  {m:14s} {sub[m].mean():5.2f} {sc['bias']:+6.2f} {sc['mae']:5.2f} {sc['rmse']:5.2f} "
                  f"{sc['r']:5.2f} {sc['R2']:6.2f} {sc['pi90']:5.2f}")

    m = r[r.water_share > 0]
    print("\nBy design flow temperature (metered hot water):")
    print(m.groupby(pd.cut(m.design_flow, [0, 35, 40, 45, 55]), observed=True)
          .agg(n=("id", "size"), actual=("actual", "mean"), scop_design=("scop_design", "mean"),
               mcs031=("mcs031", "mean"), dT=("dT", "mean"), space_flow=("space_flow", "mean"))
          .round(2).to_string())


if __name__ == "__main__":
    main()
