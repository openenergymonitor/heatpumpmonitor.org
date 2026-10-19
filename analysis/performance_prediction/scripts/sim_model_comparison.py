#!/usr/bin/env python3
"""Compare simulator sweeps run with different COP models against the fleet.

Runs the same tests on each simulator sweep (from sweep.js in the tools repo)
and on the fleet raw-feed scan (hstar_fleet_hist.csv):

  - how well weighted dT, fixed-offset harmonic Carnot (H_fixed) and
    load-dependent H* predict SPF (10-fold CV, repeated)
  - the P test: slope of SPF/H_fixed against P = H*/H_fixed
  - for the sims, whether the low-load heat share explains the dT residual

The fleet is reported in full and for its Arotherm+ systems alone, the
like-for-like comparison with a sweep run on the Arotherm+ datasheet fit
(--cop-model generic_v2).

See docs/load_and_quality.md.

Usage:
  python3 scripts/sim_model_comparison.py carnot=sw_carnot_variable.csv \
      arotherm=sw_generic_v2.csv [--fig docs/figures/sim_model_ptest.png]
"""

import csv
import sys
from pathlib import Path

import numpy as np

COMFORT_MAX_DEGREE_HOURS = 2500
FLEET_CSV = Path(__file__).resolve().parent.parent / "hstar_fleet_hist.csv"


def load_sim(path):
    rows = list(csv.DictReader(open(path)))
    keep = [r for r in rows if float(r["degree_hours_below_setpoint"]) <= COMFORT_MAX_DEGREE_HOURS]
    col = lambda k: np.array([float(r[k]) for r in keep])
    return {
        "n_all": len(rows),
        "spf": col("spf"),
        "dT": col("weighted_flowT_minus_outsideT"),
        "H_fixed": col("H_fixed"),
        "H_star": col("H_star"),
        "r_bar": col("r_bar"),
        "below40": col("heat_frac_below_40pct"),
        "dhw": col("water_heat_frac"),
    }


def load_fleet(path, model=None):
    out = {k: [] for k in ["spf", "dT", "H_fixed", "H_star"]}
    n_all = 0
    for r in csv.DictReader(open(path)):
        if r["hp_type"] != "Air Source":
            continue
        if model and model not in r["hp_model"]:
            continue
        try:
            v = {k: float(r[k]) for k in ["calc_weighted_dT", "rec_weighted_dT", "spf_window",
                                          "rec_combined_cop", "H_fixed", "H_star"]}
        except ValueError:
            continue
        n_all += 1
        # Same validation as analyse_hstar_fleet.py
        if abs(v["calc_weighted_dT"] - v["rec_weighted_dT"]) > 0.5:
            continue
        if abs(v["spf_window"] - v["rec_combined_cop"]) > 0.25:
            continue
        out["spf"].append(v["spf_window"])
        out["dT"].append(v["calc_weighted_dT"])
        out["H_fixed"].append(v["H_fixed"])
        out["H_star"].append(v["H_star"])
    d = {k: np.array(x) for k, x in out.items()}
    d["n_all"] = n_all
    return d


def cv_fit(x, y, folds=10, repeats=20, seed=1):
    """Out-of-fold R2 and 90th percentile |error| for y = a + b*x."""
    rng = np.random.default_rng(seed)
    r2s, errs = [], []
    for _ in range(repeats):
        idx = rng.permutation(len(x))
        pred = np.empty(len(x))
        for f in range(folds):
            test = idx[f::folds]
            train = np.setdiff1d(idx, test)
            b, a = np.polyfit(x[train], y[train], 1)
            pred[test] = a + b * x[test]
        res = y - pred
        r2s.append(1 - np.sum(res ** 2) / np.sum((y - y.mean()) ** 2))
        errs.append(np.quantile(np.abs(res), 0.9))
    return np.mean(r2s), np.mean(errs)


def spearman(a, b):
    ra = np.argsort(np.argsort(a))
    rb = np.argsort(np.argsort(b))
    return np.corrcoef(ra, rb)[0, 1]


def report(name, d):
    n = len(d["spf"])
    print(f"\n== {name}: n = {n}" + (f" of {d['n_all']} (comfort filter)" if "r_bar" in d else ""))
    print(f"   SPF median {np.median(d['spf']):.2f}, dT median {np.median(d['dT']):.1f} K")
    for label, key in [("weighted dT", "dT"), ("H_fixed", "H_fixed"), ("H*", "H_star")]:
        r2, pi = cv_fit(d[key], d["spf"])
        print(f"   SPF ~ {label:12s} cv R2 {r2:.2f}  90% PI ±{pi:.2f}")
    P = d["H_star"] / d["H_fixed"]
    q = d["spf"] / d["H_fixed"]
    slope, icpt = np.polyfit(P, q, 1)
    se = np.sqrt(np.sum((q - icpt - slope * P) ** 2) / (n - 2) / np.sum((P - P.mean()) ** 2))
    print(f"   P test: slope {slope:+.3f} ± {1.96 * se:.3f} (95%), corr {np.corrcoef(P, q)[0, 1]:+.2f}, sd(P) {P.std():.3f}, "
          f"median SPF/H_fixed {np.median(q):.3f}")
    if "r_bar" in d:
        b, a = np.polyfit(d["dT"], d["spf"], 1)
        resid = d["spf"] - (a + b * d["dT"])
        print(f"   SPF/H_fixed vs r_bar corr {np.corrcoef(d['r_bar'], q)[0, 1]:+.2f}; "
              f"low-load share vs dT residual rho {spearman(d['below40'], resid):+.2f} "
              f"(median share {np.median(d['below40']):.2f})")
        print(f"   r_bar median {np.median(d['r_bar']):.2f}, DHW share median {np.median(d['dhw']):.2f}")
    return P, q


def main():
    sims, fig_path = [], None
    args = sys.argv[1:]
    i = 0
    while i < len(args):
        if args[i] == "--fig":
            fig_path = args[i + 1]
            i += 2
            continue
        name, path = args[i].split("=", 1)
        sims.append((name, load_sim(path)))
        i += 1

    panels = [("fleet, all", report("fleet (raw feed scan)", load_fleet(FLEET_CSV))),
              ("fleet, Arotherm+", report("fleet, Arotherm+ only", load_fleet(FLEET_CSV, "Arotherm")))]
    for name, d in sims:
        panels.append((name, report(f"sim, {name}", d)))

    if fig_path:
        import matplotlib
        matplotlib.use("Agg")
        import matplotlib.pyplot as plt
        fig, axes = plt.subplots(1, len(panels), figsize=(4.2 * len(panels), 3.8), sharey=True)
        for ax, (name, (P, q)) in zip(axes, panels):
            ax.scatter(P, q, s=8, alpha=0.5, color="#3b6ea5")
            b, a = np.polyfit(P, q, 1)
            xs = np.linspace(P.min(), P.max(), 2)
            ax.plot(xs, a + b * xs, color="#c0392b", lw=1.5)
            ax.set_title(f"{name}: slope {b:+.2f}")
            ax.set_xlabel("P = H* / H_fixed")
            ax.grid(alpha=0.3)
        axes[0].set_ylabel("SPF / H_fixed")
        fig.tight_layout()
        fig.savefig(fig_path, dpi=130)
        print(f"\nwrote {fig_path}")


if __name__ == "__main__":
    main()
