#!/usr/bin/env python3
"""metric_collapse.png: the July 2026 simulator sweep against weighted dT and H*.

Every simulated design has the same 47%-of-Carnot machine, so this shows how
much of the scatter around SPF ~ dT H* removes when machines are identical
(README section 4). Same comfort filter as metric_comparison.py.

Usage: python3 scripts/metric_collapse_plot.py
"""

import numpy as np
import pandas as pd
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt

from hpmon_analysis import linfit, ROOT, FIGURES

BLUE, RED = "#2a78d6", "#e34948"

s = pd.read_csv(ROOT / "sim_results.csv")
s = s[s.degree_hours_below_setpoint < 2500].reset_index(drop=True)
print(f"simulated designs after comfort filter: {len(s)}")

panels = [
    ("weighted_flowT_minus_outsideT", "current metric: weighted flowT − outsideT (K)"),
    ("H_carnot_var", "proposed: heat-weighted harmonic Carnot COP,\nload-dependent offsets (H*)"),
]
fig, axes = plt.subplots(1, 2, figsize=(13, 5.2), sharey=True)
for ax, (col, label) in zip(axes, panels):
    fit = linfit(s[col], s.spf)
    xs = np.linspace(s[col].min(), s[col].max(), 50)
    ys = fit["slope"] * xs + fit["intercept"]
    ax.fill_between(xs, ys - fit["pi90"], ys + fit["pi90"], color=RED, alpha=0.1, lw=0)
    ax.scatter(s[col], s.spf, s=12, color=BLUE, alpha=0.6, edgecolors="none")
    ax.plot(xs, ys, color=RED, lw=2)
    ax.grid(alpha=0.22)
    ax.set_axisbelow(True)
    for side in ("top", "right"):
        ax.spines[side].set_visible(False)
    ax.set_xlabel(label)
    ax.set_title(f"R² = {fit['r2']:.2f},  90% PI ±{fit['pi90']:.2f}", fontsize=11)
    print(f"{col:32s} R2 {fit['r2']:.3f}  PI ±{fit['pi90']:.2f}")
axes[0].set_ylabel("SPF (simulated, identical 47% Carnot machine)")
fig.suptitle("Same simulated fleet, two annualised metrics", fontsize=13)
fig.tight_layout()
out = FIGURES / "metric_collapse.png"
fig.savefig(out, dpi=140, bbox_inches="tight")
print("saved", out)
