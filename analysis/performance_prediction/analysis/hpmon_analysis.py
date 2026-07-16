#!/usr/bin/env python3
"""Analysis harness for the HeatpumpMonitor.org all-fields CSV export.

Reproduces and extends the low-temperature analysis:
https://docs.openenergymonitor.org/heatpumpmonitor/low_temperature.html

Usage:
    python hpmon_analysis.py [csv_path]
"""

import sys
import glob

import numpy as np
import pandas as pd
from scipy import stats

DAY = 86400

# Reference fit from the published low_temperature.html analysis
REFERENCE = {"slope": -0.1054, "intercept": 6.8762, "r2": 0.547, "pi90": 0.53}


def find_csv():
    matches = sorted(glob.glob("/var/www/hpmon_ai2/heatpumpmonitor_all_fields_*.csv"))
    if not matches:
        sys.exit("No heatpumpmonitor_all_fields_*.csv found")
    return matches[-1]


def load(csv_path):
    return pd.read_csv(csv_path)


def standard_filter(df, boundary=4, hp_type="Air Source", max_cooling_kwh=1.0,
                    min_days=330, mid_only=True, verbose=True):
    """Apply the standard low-temperature-analysis filter set, reporting counts."""
    steps = [
        (f"metering_boundary_code == {boundary}", df.metering_boundary_code == boundary),
        (f"hp_type == {hp_type}", df.hp_type == hp_type),
        (f"cooling_heat_kwh < {max_cooling_kwh}", df.cooling_heat_kwh.abs() < max_cooling_kwh),
        (f"combined_data_length >= {min_days} days", df.combined_data_length >= min_days * DAY),
    ]
    if mid_only:
        steps.append(("mid_metering == 1", df.mid_metering == 1))

    mask = pd.Series(True, index=df.index)
    if verbose:
        print(f"  starting rows: {len(df)}")
    for label, cond in steps:
        mask &= cond.fillna(False)
        if verbose:
            print(f"  after {label}: {mask.sum()}")
    return df[mask].copy()


def linfit(x, y):
    """Linear regression with R², RMSE and 90% prediction interval half-width."""
    ok = x.notna() & y.notna()
    x, y = x[ok].to_numpy(float), y[ok].to_numpy(float)
    res = stats.linregress(x, y)
    pred = res.slope * x + res.intercept
    resid = y - pred
    n = len(x)
    # 90% prediction interval half-width at the mean of x (ignoring the small
    # leverage term, as in the original analysis which quoted a single ±value)
    s = np.sqrt(np.sum(resid**2) / (n - 2))
    pi90 = stats.t.ppf(0.95, n - 2) * s
    return {
        "n": n, "slope": res.slope, "intercept": res.intercept,
        "r": res.rvalue, "r2": res.rvalue**2, "p": res.pvalue,
        "stderr": res.stderr, "rmse": np.sqrt(np.mean(resid**2)), "pi90": pi90,
        "x": x, "y": y,
    }


def report_fit(name, fit, ref=None):
    print(f"\n{name}")
    print(f"  n = {fit['n']}")
    print(f"  fit: COP = {fit['slope']:.4f} * dT + {fit['intercept']:.4f}")
    print(f"  R^2 = {fit['r2']:.3f}   (r = {fit['r']:.3f}, p = {fit['p']:.2e})")
    print(f"  RMSE = {fit['rmse']:.3f}   90% PI = +/-{fit['pi90']:.2f}")
    for dt in (25, 30, 35):
        print(f"  predicted COP at dT={dt}K: {fit['slope']*dt + fit['intercept']:.2f}")
    if ref:
        print(f"  reference: COP = {ref['slope']:.4f} * dT + {ref['intercept']:.4f}, "
              f"R^2 = {ref['r2']:.3f}, 90% PI +/-{ref['pi90']:.2f}")
        print(f"  delta slope: {fit['slope']-ref['slope']:+.4f}   "
              f"delta intercept: {fit['intercept']-ref['intercept']:+.4f}   "
              f"delta R^2: {fit['r2']-ref['r2']:+.3f}")


def plot_fit(fit, out_png, title):
    import matplotlib
    matplotlib.use("Agg")
    import matplotlib.pyplot as plt

    fig, ax = plt.subplots(figsize=(9, 6))
    ax.scatter(fit["x"], fit["y"], s=18, alpha=0.55, color="#4053d3", edgecolors="none",
               label=f"systems (n={fit['n']})")
    xs = np.linspace(fit["x"].min(), fit["x"].max(), 100)
    ys = fit["slope"] * xs + fit["intercept"]
    ax.plot(xs, ys, color="#b51d14", lw=2,
            label=f"fit: COP = {fit['slope']:.4f}·ΔT + {fit['intercept']:.4f}  (R²={fit['r2']:.3f})")
    ax.fill_between(xs, ys - fit["pi90"], ys + fit["pi90"], color="#b51d14", alpha=0.12,
                    label=f"90% prediction interval ±{fit['pi90']:.2f}")
    ax.plot(xs, REFERENCE["slope"] * xs + REFERENCE["intercept"], color="#454545",
            lw=1.5, ls="--", label="published fit (docs)")
    ax.set_xlabel("Weighted average flow temp − outside temp (K)")
    ax.set_ylabel("SPF H4 (combined COP)")
    ax.set_title(title)
    ax.legend(frameon=False)
    ax.grid(alpha=0.25)
    fig.tight_layout()
    fig.savefig(out_png, dpi=140)
    print(f"\n  plot saved: {out_png}")


def main():
    csv_path = sys.argv[1] if len(sys.argv) > 1 else find_csv()
    print(f"dataset: {csv_path}")
    df = load(csv_path)

    print("\nApplying standard filters:")
    f = standard_filter(df)

    fit = linfit(f["weighted_flowT_minus_outsideT"], f["combined_cop"])
    report_fit("combined_cop vs weighted_flowT_minus_outsideT", fit, ref=REFERENCE)
    plot_fit(fit, "/var/www/hpmon_ai2/docs/figures/cop_vs_flow_minus_outside.png",
             "SPF H4 vs weighted flow − outside temperature")

    # Sensitivity: same fit without the cooling filter (boundary 4, ASHP, 330d, MID)
    f2 = standard_filter(df, max_cooling_kwh=np.inf, verbose=False)
    fit2 = linfit(f2["weighted_flowT_minus_outsideT"], f2["combined_cop"])
    report_fit("sensitivity: no cooling filter", fit2)


if __name__ == "__main__":
    main()
