#!/usr/bin/env python3
"""Why does a plain regression on coldest-day lift beat the design model
anchored on the same coldest-day measurement? (docs/design_model.md,
"A plain regression does better")

Starts from the design model with its curve anchored on each system's
measured coldest-day flow temperature (no DHW block), then strips one
modelling assumption at a time, scoring each version by 5-fold cv R² of a
linear fit to SPF.

Usage: .venv/bin/python scripts/design_model_ablation.py [heatpumpmonitor_all_fields_*.csv]
"""

import sys

import numpy as np
import pandas as pd
from sklearn.linear_model import LinearRegression
from sklearn.model_selection import KFold, cross_val_predict

import design_model as d
from hpmon_analysis import load, standard_filter, find_csv, measured_heat_loss
from residual_analysis import num

ROOM, DT_SYS = 20.0, 5.0


def main():
    args = [a for a in sys.argv[1:] if not a.startswith("--")]
    f = standard_filter(load(args[0] if args else find_csv()), verbose=False)
    f = f[f.weighted_flowT_minus_outsideT.notna() & f.combined_cop.notna()].reset_index(drop=True)
    y = num(f.combined_cop)

    hl = num(f.heat_loss)
    hl = hl.where(hl < 100, hl / 1000).where(lambda v: (v > 0.5) & (v < 30))
    mhl = measured_heat_loss(f).where(lambda v: (v > 0.5) & (v < 30))
    hl = mhl.fillna(hl * 0.76)
    dtemp = num(f.design_temp).where(lambda v: (v > -15) & (v < 6))
    cap = num(f.hp_output).where(lambda v: (v > 1) & (v < 30)) * 1000
    mft = num(f.measured_mean_flow_temp_coldest_day).where(lambda v: (v > 25) & (v < 65))
    mot = num(f.measured_outside_temp_coldest_day).where(lambda v: (v > -15) & (v < 8))
    mrt = num(f.measured_room_temp_coldest_day).where(lambda v: (v > 15) & (v < 25)).fillna(20.0)
    w_ref = np.percentile(d.WEATHER, 0.4)

    def hstar(i, loads=True, gains=True, room_meas=True, shift=True):
        if np.isnan([hl[i], dtemp[i], mft[i], mot[i], cap[i]]).any():
            return np.nan
        ra = mrt[i] if room_meas else ROOM
        u = hl[i] * 1000 / (ROOM - dtemp[i])
        mwt = mft[i] - DT_SYS / 2
        if mwt <= ra + 2:
            return np.nan
        rad50 = u * (ra - mot[i]) / ((mwt - ra) / 50) ** d.RAD_EXP
        w = d.WEATHER + ((dtemp[i] if shift else -1.5) - w_ref)
        saved = d.A_COND, d.B_EVAP
        if not loads:
            d.A_COND, d.B_EVAP = 0.0, 0.0
        try:
            return d.design_hstar(u, ROOM, rad50, DT_SYS, cap[i] if loads else 1e9, 0.0, 48.0,
                                  min_mod=d.MINMOD if loads else 0.0, weather=w,
                                  gains_w=d.GAINS_W if gains else 0.0,
                                  solar_scale=d.SOLAR_SCALE if gains else 0.0)
        finally:
            d.A_COND, d.B_EVAP = saved

    def cv_r2(x):
        m = x.notna() & y.notna()
        p = cross_val_predict(LinearRegression(), x[m].to_numpy()[:, None], y[m],
                              cv=KFold(5, shuffle=True, random_state=0))
        return 1 - ((y[m] - p) ** 2).sum() / ((y[m] - y[m].mean()) ** 2).sum(), m.sum()

    steps = [
        ("Design model anchored on coldest day, no DHW", {}),
        ("  without load terms, gains or capacity limit", dict(loads=False, gains=False)),
        ("  + room 20 C, not measured coldest-day room", dict(loads=False, gains=False, room_meas=False)),
        ("  + one climate, not shifted per design temp", dict(loads=False, gains=False, room_meas=False, shift=False)),
    ]
    print(f"{'':48s} {'cv R2':>6s}   n")
    rows = [(label, pd.Series([hstar(i, **kw) for i in f.index])) for label, kw in steps]
    rows += [("Carnot COP on the coldest day", (mft + 273.15) / (mft - mot)),
             ("Coldest-day lift", mft - mot)]
    for label, x in rows:
        r2, n = cv_r2(x)
        print(f"{label:48s} {r2:6.2f} {n:4d}")


if __name__ == "__main__":
    main()
