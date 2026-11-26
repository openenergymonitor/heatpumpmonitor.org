#!/usr/bin/env python3
"""Where does each system lose SPF between clean running and H4? No model, just accounting.

Phase 1 found that a perfect clean-window model would still leave about 0.27 RMSE on H4 SPF,
because much of the difference between systems is in what clean windows leave out.
This splits that gap, per system, into parts measured from the 15-minute windows.

For each part c (a set of windows, or standby), the electricity lost is what it used beyond
running the same heat at the system's clean COP:

    loss_c = elec_c - heat_c / clean_spf          (standby: all of its electricity)
    f_c    = loss_c / total electricity

which gives the exact identity

    H4 SPF = clean SPF x (1 - sum of f_c)

once "other" takes up what the windows don't cover (data gaps, windows with a missing input,
differences between our window year and the published stats). The parts:

    partial    space heating windows with a start or stop in them (not running throughout)
    defrost    space heating windows with a defrost in them
    hot water  windows flagged as hot water (lower COP is expected: higher flow temperature)
    standby    combined minus running electricity, from the published stats
    other      the remainder

Systems without a hot-water flag have their hot water inside the space parts (has_dhw_flag).

Usage (from analysis/performance_prediction/):
    .venv/bin/python cop_ladder/gap.py --export heatpumpmonitor_all_fields_last365_<date>.csv \
        --windows cop_ladder/windows_30s

Writes gap_systems.csv into the windows folder and prints the fleet summary.
"""
import argparse
import glob
import os

import numpy as np
import pandas as pd
from scipy import stats

PARTS = ["partial", "defrost", "hot_water", "standby", "other"]
LOW_LOAD = 0.35


def per_system(w):
    w = w.copy()
    w["part"] = np.select(
        [~w.space, w.n_def > 0, ~w.clean, w.clean],
        ["hot_water", "defrost", "partial", "clean"], "other")
    g = w.groupby("part")[["heat_kwh", "elec_kwh"]].sum()
    out = {f"{p}_{k}": g.loc[p, f"{k}_kwh"] if p in g.index else 0.0
           for p in ["clean", "partial", "defrost", "hot_water"] for k in ["heat", "elec"]}
    c = w[w.clean & w.cop.between(0.5, 12)]
    lo = c.load_ratio < LOW_LOAD
    out["clean_low_load_heat_share"] = c.heat_kwh[lo].sum() / c.heat_kwh.sum() if len(c) else np.nan
    hi_cop = c.heat_kwh[~lo].sum() / c.elec_kwh[~lo].sum() if (~lo).sum() else np.nan
    lo_cop = c.heat_kwh[lo].sum() / c.elec_kwh[lo].sum() if lo.sum() else np.nan
    out["clean_low_load_cop_ratio"] = lo_cop / hi_cop if lo.sum() >= 50 and (~lo).sum() >= 50 else np.nan
    out["clean_windows"] = len(c)
    out["clean_spf"] = c.heat_kwh.sum() / c.elec_kwh.sum() if len(c) else np.nan
    out["starts_per_day"] = w.starts_24h.median()
    out["defrost_window_share"] = (w.n_def[w.space] > 0).mean()
    out["has_dhw_flag"] = bool(w.has_dhw_flag.iloc[0])
    return out


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--export", required=True)
    ap.add_argument("--windows", required=True)
    args = ap.parse_args()

    meta = pd.read_csv(args.export).set_index("id")
    rows = []
    for p in sorted(glob.glob(os.path.join(args.windows, "*.pkl"))):
        w = pd.read_pickle(p)
        if w.empty:
            print(f"skipping {os.path.basename(p)}: no windows")
            continue
        sid = int(w.system_id.iloc[0])
        r = per_system(w)
        r["system_id"] = sid
        rows.append(r)
    s = pd.DataFrame(rows).set_index("system_id")
    m = meta.loc[s.index]
    num = lambda c: pd.to_numeric(m[c], errors="coerce")
    s["h4_spf"] = num("combined_cop")
    s["weighted_dT"] = num("weighted_flowT_minus_outsideT")
    E = num("combined_elec_kwh")
    s["standby_elec"] = (E - num("running_elec_kwh")).clip(lower=0)
    s = s[s.clean_windows >= 500].copy()
    E = E.loc[s.index]

    cs = s.clean_spf
    for p in ["partial", "defrost", "hot_water"]:
        s[f"f_{p}"] = (s[f"{p}_elec"] - s[f"{p}_heat"] / cs) / E
    s["f_standby"] = s.standby_elec / E
    s["f_other"] = 1 - s.h4_spf / cs - s[["f_partial", "f_defrost", "f_hot_water", "f_standby"]].sum(axis=1)
    s["f_total"] = 1 - s.h4_spf / cs
    s["space_spf"] = ((s.clean_heat + s.partial_heat + s.defrost_heat)
                      / (s.clean_elec + s.partial_elec + s.defrost_elec))
    s["window_heat_coverage"] = ((s.clean_heat + s.partial_heat + s.defrost_heat + s.hot_water_heat)
                                 / num("running_heat_kwh").loc[s.index])
    fit = np.polyfit(s.weighted_dT, s.h4_spf, 1)
    s["h4_resid_vs_dT"] = s.h4_spf - np.polyval(fit, s.weighted_dT)
    s = s.dropna(subset=["h4_spf", "clean_spf", "weighted_dT"])

    pd.set_option("display.width", 200)
    print(f"systems: {len(s)} ({int(s.has_dhw_flag.sum())} with a hot-water flag)")
    print(f"window heat coverage of published running heat: median {s.window_heat_coverage.median():.2f}, "
          f"below 0.8 for {(s.window_heat_coverage < 0.8).sum()} systems\n")

    # 1. size and spread of each part
    fcols = [f"f_{p}" for p in PARTS] + ["f_total"]
    summ = s[fcols].describe(percentiles=[.1, .5, .9]).T[["mean", "std", "10%", "50%", "90%"]]
    print("Share of electricity lost vs running at the clean COP (H4 = clean SPF x (1 - total)):")
    print((100 * summ).round(1).to_string(), "\n")

    # 2. which parts drive differences between systems: covariance shares of ln(H4)
    y = np.log(s.h4_spf)
    comps = {"clean SPF": np.log(s.clean_spf)}
    for p in PARTS:
        comps[p] = -s[f"f_{p}"]
    comps["(log approximation)"] = y - sum(comps.values())
    vy = y.var()
    print("Share of between-system variance in ln(H4 SPF) (covariance shares, sum to 1):")
    for k, v in comps.items():
        print(f"  {k:22s} {np.cov(v, y)[0, 1] / vy:6.2f}")
    print()

    # 3. are the parts related to dT, or to the residual from the dT line?
    print("Spearman correlations:")
    print(f"  {'':22s} {'with weighted dT':>17s} {'with H4 resid vs dT':>20s}")
    for c in fcols + ["clean_spf", "starts_per_day", "defrost_window_share",
                      "clean_low_load_heat_share", "clean_low_load_cop_ratio"]:
        ok = s[c].notna()
        r1 = stats.spearmanr(s[c][ok], s.weighted_dT[ok])[0]
        r2 = stats.spearmanr(s[c][ok], s.h4_resid_vs_dT[ok])[0]
        print(f"  {c:22s} {r1:17.2f} {r2:20.2f}")
    print()

    # 4. same for systems with a hot-water flag only
    f = s[s.has_dhw_flag]
    print(f"With a hot-water flag only (n = {len(f)}), median / p90 of each part (%):")
    print((100 * f[fcols].quantile([.5, .9]).T).round(1).to_string(), "\n")

    # 5. largest gaps
    cols = ["h4_spf", "clean_spf", "space_spf", "weighted_dT"] + fcols + ["window_heat_coverage", "has_dhw_flag"]
    show = s.sort_values("f_total", ascending=False)[cols].head(15).copy()
    for c in fcols:
        show[c] = (100 * show[c]).round(1)
    print("Largest gaps between clean SPF and H4 (parts in %):")
    print(show.round(2).to_string())

    out = os.path.join(args.windows, "gap_systems.csv")
    s.to_csv(out)
    print(f"\nwrote {out}")


if __name__ == "__main__":
    main()
