#!/usr/bin/env python3
"""Compare the fleet's Arotherm+ systems with their simulated datasheet twins.

Each twin (twins.js) runs the Arotherm+ datasheet fit calibrated to its real
system's space heat, water heat and weighted flow temperature. Comparing them
system by system asks: does real machine quality follow what the datasheet
predicts for that system's own operating pattern?

Usage (from analysis/performance_prediction/):
  python3 twins/compare_twins.py [twins/twins.csv] [--fig docs/figures/twins.png]
"""

import csv
import sys

import numpy as np

FLEET_CSV = "hstar_fleet_hist.csv"


def fit(x, y):
    """OLS slope with 95% interval, intercept and correlation."""
    b, a = np.polyfit(x, y, 1)
    se = np.sqrt(np.sum((y - a - b * x) ** 2) / (len(x) - 2) / np.sum((x - x.mean()) ** 2))
    return b, 1.96 * se, a, np.corrcoef(x, y)[0, 1]


def cv_fit(x, y, folds=10, repeats=20, seed=1):
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


def main():
    args = sys.argv[1:]
    fig_path = None
    if "--fig" in args:
        fig_path = args[args.index("--fig") + 1]
        del args[args.index("--fig"):args.index("--fig") + 2]
    twins_csv = args[0] if args else "twins/twins.csv"

    fleet = {r["id"]: r for r in csv.DictReader(open(FLEET_CSV))}
    twins = [t for t in csv.DictReader(open(twins_csv)) if t["converged"] == "1"]
    n_all = sum(1 for _ in csv.DictReader(open(twins_csv)))

    real = {k: [] for k in ["spf", "H_fixed", "H_star", "dT"]}
    twin = {k: [] for k in ["spf", "H_fixed", "H_star", "dT", "r_bar", "below40", "starts", "badge",
                            "offset", "water_share", "heat_loss"]}
    for t in twins:
        f = fleet[t["id"]]
        real["spf"].append(float(f["spf_window"]))
        real["H_fixed"].append(float(f["H_fixed"]))
        real["H_star"].append(float(f["H_star"]))
        real["dT"].append(float(f["calc_weighted_dT"]))
        twin["spf"].append(float(t["spf"]))
        twin["H_fixed"].append(float(t["H_fixed"]))
        twin["H_star"].append(float(t["H_star"]))
        twin["dT"].append(float(t["weighted_flowT_minus_outsideT"]))
        twin["r_bar"].append(float(t["r_bar"]))
        twin["below40"].append(float(t["heat_frac_below_40pct"]))
        twin["starts"].append(float(t["starts_per_day"]))
        twin["badge"].append(float(t["badge_kw"]))
        twin["offset"].append(float(t["outsideT_offset"]))
        sp, wa = float(t["space_heat_kwh"]), float(t["water_heat_kwh"])
        twin["water_share"].append(wa / (sp + wa))
        twin["heat_loss"].append(float(t["heat_loss"]))
    real = {k: np.array(v) for k, v in real.items()}
    twin = {k: np.array(v) for k, v in twin.items()}
    n = len(twins)
    real["P"] = real["H_star"] / real["H_fixed"]
    twin["P"] = twin["H_star"] / twin["H_fixed"]
    real["q"] = real["spf"] / real["H_fixed"]
    twin["q"] = twin["spf"] / twin["H_fixed"]
    gap = real["spf"] / twin["spf"]

    print(f"twins: {n} converged of {n_all}")

    print("\n== Do the twins reproduce each system's operating pattern?")
    print(f"   weighted flowT-outsideT: real median {np.median(real['dT']):.1f}, twin {np.median(twin['dT']):.1f}, "
          f"corr {np.corrcoef(real['dT'], twin['dT'])[0, 1]:.2f}")
    print(f"   H_fixed: corr {np.corrcoef(real['H_fixed'], twin['H_fixed'])[0, 1]:.2f}")
    print(f"   P: real median {np.median(real['P']):.3f} (sd {real['P'].std():.3f}), "
          f"twin median {np.median(twin['P']):.3f} (sd {twin['P'].std():.3f}), "
          f"corr {np.corrcoef(real['P'], twin['P'])[0, 1]:.2f}")

    print("\n== SPF: twins against reality")
    print(f"   median SPF real {np.median(real['spf']):.2f}, twin {np.median(twin['spf']):.2f}; "
          f"real/twin median {np.median(gap):.3f} (IQR {np.percentile(gap, 25):.3f}-{np.percentile(gap, 75):.3f})")
    raw = real["spf"] - twin["spf"] * np.median(gap)
    print(f"   twin SPF scaled by the median ratio, no fitting: 90% |error| {np.quantile(np.abs(raw), 0.9):.2f}")
    for label, x in [("weighted dT", real["dT"]), ("twin SPF", twin["spf"])]:
        r2, pi = cv_fit(x, real["spf"])
        print(f"   real SPF ~ {label:12s} cv R2 {r2:.2f}  90% PI ±{pi:.2f}")

    print("\n== P test, same systems")
    for label, x, y in [("twins: twin q vs twin P", twin["P"], twin["q"]),
                        ("real:  real q vs real P", real["P"], real["q"]),
                        ("real:  real q vs twin P", twin["P"], real["q"])]:
        b, ci, _, r = fit(x, y)
        print(f"   {label}: slope {b:+.3f} ± {ci:.3f}, corr {r:+.2f}")

    print("\n== Does real machine quality follow the twin's? (slope 1 = datasheet holds)")
    b, ci, _, r = fit(twin["q"], real["q"])
    print(f"   real q vs twin q: slope {b:+.2f} ± {ci:.2f}, corr {r:+.2f}; "
          f"twin q sd {twin['q'].std():.3f}, real q sd {real['q'].std():.3f}")

    print("\n== What goes with the gap (real SPF / twin SPF)?")
    for label, x in [("twin r_bar", twin["r_bar"]), ("twin P", twin["P"]),
                     ("twin heat below 40% of badge", twin["below40"]),
                     ("twin starts per day", twin["starts"]), ("badge kW", twin["badge"]),
                     ("heat loss / badge", twin["heat_loss"] / (1000 * twin["badge"])),
                     ("water share", twin["water_share"]), ("outside T offset", twin["offset"]),
                     ("real weighted dT", real["dT"])]:
        print(f"   {label:30s} corr {np.corrcoef(x, gap)[0, 1]:+.2f}")

    if fig_path:
        import matplotlib
        matplotlib.use("Agg")
        import matplotlib.pyplot as plt
        fig, ax = plt.subplots(1, 3, figsize=(13, 4))
        lo, hi = min(real["spf"].min(), twin["spf"].min()), max(real["spf"].max(), twin["spf"].max())
        ax[0].scatter(twin["spf"], real["spf"], s=10, alpha=0.6, color="#3b6ea5")
        ax[0].plot([lo, hi], [lo, hi], color="#999", lw=1, ls="--", label="1:1")
        ax[0].set_xlabel("twin SPF (datasheet)")
        ax[0].set_ylabel("real SPF")
        ax[0].set_title("SPF")
        ax[0].legend(loc="upper left")
        b, _, a, _ = fit(twin["q"], real["q"])
        ax[1].scatter(twin["q"], real["q"], s=10, alpha=0.6, color="#3b6ea5")
        xs = np.linspace(twin["q"].min(), twin["q"].max(), 2)
        ax[1].plot(xs, a + b * xs, color="#c0392b", lw=1.5, label=f"fit, slope {b:+.2f}")
        ax[1].plot(xs, xs * np.median(real["q"] / twin["q"]), color="#999", lw=1, ls="--",
                   label="slope 1 (datasheet holds)")
        ax[1].set_xlabel("twin SPF / H_fixed")
        ax[1].set_ylabel("real SPF / H_fixed")
        ax[1].set_title("Machine quality")
        ax[1].legend(loc="upper left", fontsize=8)
        ax[2].scatter(twin["P"], gap, s=10, alpha=0.6, color="#3b6ea5")
        b, _, a, _ = fit(twin["P"], gap)
        xs = np.linspace(twin["P"].min(), twin["P"].max(), 2)
        ax[2].plot(xs, a + b * xs, color="#c0392b", lw=1.5)
        ax[2].set_xlabel("twin P = H* / H_fixed (higher = run more gently)")
        ax[2].set_ylabel("real SPF / twin SPF")
        ax[2].set_title("Gap against load")
        for a_ in ax:
            a_.grid(alpha=0.3)
        fig.tight_layout()
        fig.savefig(fig_path, dpi=130)
        print(f"\nwrote {fig_path}")


if __name__ == "__main__":
    main()
