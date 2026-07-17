#!/usr/bin/env python3
"""Offset grid search for harmonic Carnot SPF prediction metrics.

Reads the per-system heat-weighted (flowT, outsideT, load-ratio) histogram
exported by scripts/feed_scan/feed_scan_5.php and reconstructs, offline, any
metric of the family

    Tcond = flowT + a0 + a1*r          (K, condenser side)
    Tevap = outsideT - b0 - b1*r       (K, evaporator side)
    H     = sum(heat) / sum(heat * lift / (Tcond + 273.15)),  lift = Tcond - Tevap

then grid-searches (a0, b0, a1, b1) for the best cross-validated R2 of
SPF ~ H across the fleet. Reference points: (0,0,0,0) is 1/weighted-Carnot of
the raw temps, (2,6,0,0) is the published fixed-offset convention, (0,0,3,8)
is H* from doc 05.

The search runs twice with two load-ratio definitions:
  badge:     r = heat / hp_output  (rated capacity, as stored in the histogram)
  empirical: r' = r / r_p98        (r_p98 = heat-weighted 98th percentile of r,
                                    i.e. normalise by the output the machine
                                    actually delivers when pushed)
The empirical normalisation is immune to badge-engineering (identical hardware
sold as 9/11/14/16 kW and limited in software): the sticker cancels out. It
still assumes the machine gets pushed near its real limit sometimes (DHW runs
usually do this) and ignores capacity droop in cold air.

Requires numpy. Usage:
  python3 analyse_hstar_offsets.py [hstar_hist.csv] [hstar_fleet_hist.csv]

Validation-clean systems only (|calc dT - rec dT| <= 0.5 K and
|window SPF - recorded COP| <= 0.25), same as the doc 09 analysis.
"""

import csv
import math
import os
import random
import sys

try:
    import numpy as np
except ImportError:
    sys.exit("numpy required (pip install numpy, or run inside a venv)")

CLEAN_DT_K = 0.5
CLEAN_COP = 0.25
FOLDS = 10
SEED = 42


def load_summary(path):
    systems = {}
    with open(path, newline="") as f:
        for r in csv.DictReader(f):
            def g(k):
                v = r.get(k, "")
                try:
                    return float(v)
                except (ValueError, TypeError):
                    return None
            d = {
                "spf": g("spf_window"),
                "rec_cop": g("rec_combined_cop"),
                "w_dT": g("calc_weighted_dT"),
                "rec_dT": g("rec_weighted_dT"),
            }
            systems[r["id"]] = d
    return systems


def load_hist(path, ids):
    """Return dict id -> (w, flowT, outsideT, r) numpy arrays."""
    cols = {}
    with open(path, newline="") as f:
        for r in csv.DictReader(f):
            i = r["id"]
            if i not in ids:
                continue
            cols.setdefault(i, []).append(
                (float(r["heat_kwh"]), float(r["flowT"]), float(r["outsideT"]), float(r["r"])))
    return {i: tuple(np.array(c) for c in zip(*rows)) for i, rows in cols.items()}


def stack(hists, order):
    """Concatenate all systems' bins; returns arrays + per-bin system index."""
    w = np.concatenate([hists[i][0] for i in order])
    ft = np.concatenate([hists[i][1] for i in order])
    ot = np.concatenate([hists[i][2] for i in order])
    r = np.concatenate([hists[i][3] for i in order])
    idx = np.concatenate([np.full(len(hists[i][0]), k) for k, i in enumerate(order)])
    return w, ft, ot, r, idx.astype(int)


def metric_H(w, ft, ot, r, idx, nsys, a0, b0, a1, b1):
    """Per-system H for one offset combo, vectorised over all bins."""
    tc = ft + a0 + a1 * r + 273.15
    lift = (ft - ot) + (a0 + b0) + (a1 + b1) * r
    ok = lift > 0
    ideal = np.bincount(idx[ok], weights=(w * lift / tc)[ok], minlength=nsys)
    heat = np.bincount(idx[ok], weights=w[ok], minlength=nsys)
    with np.errstate(divide="ignore", invalid="ignore"):
        return np.where(ideal > 0, heat / ideal, np.nan)


def weighted_quantile_r(w, r, idx, nsys, q):
    """Per-system heat-weighted q-quantile of load ratio r."""
    out = np.full(nsys, np.nan)
    for k in range(nsys):
        m = idx == k
        if not m.any():
            continue
        rs = r[m]
        ws = w[m]
        o = np.argsort(rs)
        cum = np.cumsum(ws[o])
        j = int(np.searchsorted(cum, q * cum[-1]))
        out[k] = rs[o][min(j, len(rs) - 1)]
    return out


def cv_r2(x, y, folds=FOLDS, seed=SEED):
    """10-fold cross-validated R2 of a univariate linear fit y ~ x."""
    n = len(y)
    order = list(range(n))
    random.Random(seed).shuffle(order)
    pred = np.zeros(n)
    for f in range(folds):
        test = order[f::folds]
        train = np.ones(n, bool)
        train[test] = False
        xt, yt = x[train], y[train]
        b = np.cov(xt, yt, bias=True)[0, 1] / np.var(xt)
        a = yt.mean() - b * xt.mean()
        pred[test] = a + b * x[test]
    ss_res = float(((y - pred) ** 2).sum())
    ss_tot = float(((y - y.mean()) ** 2).sum())
    return 1 - ss_res / ss_tot


def main():
    base = os.path.dirname(os.path.abspath(__file__))
    hist_path = sys.argv[1] if len(sys.argv) > 1 else os.path.join(base, "hstar_hist.csv")
    summary_path = sys.argv[2] if len(sys.argv) > 2 else os.path.join(base, "hstar_fleet_hist.csv")

    systems = load_summary(summary_path)
    clean = {}
    for i, d in systems.items():
        if d["spf"] is None or d["rec_cop"] is None:
            continue
        if d["rec_dT"] is None or abs(d["w_dT"] - d["rec_dT"]) > CLEAN_DT_K:
            continue
        if abs(d["spf"] - d["rec_cop"]) > CLEAN_COP:
            continue
        clean[i] = d
    print(f"{summary_path}: {len(systems)} systems, {len(clean)} validation-clean")

    hists = load_hist(hist_path, set(clean.keys()))
    order = [i for i in clean if i in hists]
    nsys = len(order)
    w, ft, ot, r, idx = stack(hists, order)
    spf = np.array([clean[i]["spf"] for i in order])
    print(f"{hist_path}: {len(w)} bins over {nsys} systems "
          f"(mean {len(w)//max(nsys,1)} bins/system)")

    # baseline: heat-weighted dT from the same histogram
    heat_tot = np.bincount(idx, weights=w, minlength=nsys)
    wdT = np.bincount(idx, weights=w * (ft - ot), minlength=nsys) / heat_tot
    print(f"\nBaseline  SPF ~ -weighted dT:  cvR2 = {cv_r2(-wdT, spf):.3f}")

    run_search(w, ft, ot, r, idx, nsys, spf, "badge capacity (r = heat / hp_output)")

    # empirical capacity: normalise r by the heat-weighted 98th percentile of r,
    # i.e. by the output the machine actually delivers when pushed. Immune to
    # badge-engineering (9-16 kW same-hardware ranges).
    r_p98 = weighted_quantile_r(w, r, idx, nsys, 0.98)
    q = np.sort(r_p98)
    print(f"\nEmpirical peak output r_p98 (fraction of badge): "
          f"min {q[0]:.2f}  p25 {q[len(q)//4]:.2f}  median {q[len(q)//2]:.2f}  "
          f"p75 {q[3*len(q)//4]:.2f}  max {q[-1]:.2f}")
    r_emp = r / r_p98[idx]
    run_search(w, ft, ot, r_emp, idx, nsys, spf, "empirical capacity (r' = r / r_p98)")


def run_search(w, ft, ot, r, idx, nsys, spf, label):
    print(f"\n================ load ratio: {label} ================")
    for name, combo in [("raw Carnot (0,0,0,0)", (0, 0, 0, 0)),
                        ("fixed +2/-6 (2,6,0,0)", (2, 6, 0, 0)),
                        ("H* +3r/-8r (0,0,3,8)", (0, 0, 3, 8))]:
        H = metric_H(w, ft, ot, r, idx, nsys, *combo)
        print(f"Reference {name:24s} cvR2 = {cv_r2(H, spf):.3f}")

    # grids
    combos = []
    for a0 in np.arange(0, 4.5, 0.5):                    # fixed-offset grid
        for b0 in np.arange(0, 8.5, 0.5):
            combos.append((float(a0), float(b0), 0.0, 0.0))
    for a1 in np.arange(0, 6.5, 0.5):                    # load-dependent grid
        for b1 in np.arange(0, 12.5, 0.5):
            combos.append((0.0, 0.0, float(a1), float(b1)))
    for a0 in (0, 1, 2, 3):                              # combined grid (coarse)
        for b0 in (0, 2, 4, 6):
            for a1 in (0, 0.5, 1, 1.5, 2, 3):
                for b1 in (0, 1, 2, 3, 4, 6, 8):
                    combos.append((float(a0), float(b0), float(a1), float(b1)))
    combos = sorted(set(combos))
    print(f"Searching {len(combos)} offset combos...")

    results = []
    for c in combos:
        H = metric_H(w, ft, ot, r, idx, nsys, *c)
        if np.isnan(H).any():
            continue
        results.append((cv_r2(H, spf), c))
    results.sort(reverse=True)

    print(f"Top 10 (a0, b0, a1, b1) by cvR2 of SPF ~ H:")
    for r2, c in results[:10]:
        kind = "fixed" if c[2] == 0 and c[3] == 0 else ("load" if c[0] == 0 and c[1] == 0 else "mixed")
        print(f"  a0={c[0]:3.1f} b0={c[1]:3.1f} a1={c[2]:3.1f} b1={c[3]:3.1f}  cvR2 = {r2:.3f}  [{kind}]")

    def fmt(x):
        r2, c = x
        return f"a0={c[0]:3.1f} b0={c[1]:3.1f} a1={c[2]:3.1f} b1={c[3]:3.1f}  cvR2 = {r2:.3f}"

    best_fixed = max((x for x in results if x[1][2] == 0 and x[1][3] == 0), default=None)
    best_load = max((x for x in results if x[1][0] == 0 and x[1][1] == 0), default=None)
    if best_fixed:
        print(f"Best fixed-only:  {fmt(best_fixed)}")
    if best_load:
        print(f"Best load-only:   {fmt(best_load)}")
    print(f"Best overall:     {fmt(results[0])}")


if __name__ == "__main__":
    main()
