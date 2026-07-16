#!/usr/bin/env python3
"""Figures for doc 08: the design-parameter model.

Panel A: the commissioning gap — declared design flow temp vs the flow temp
         systems actually operate at (heat-weighted annual mean).
Panel B: 90% prediction-interval ladder — design-time predictors vs metrics
         that need a year of monitoring, on the sim testbed and the real fleet.
"""

import sys

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
import numpy as np

sys.path.insert(0, "/var/www/hpmon_ai2/analysis")
from hpmon_analysis import load, standard_filter, find_csv
from residual_analysis import num
from scipy import stats

BLUE, RED, INK, MUT = "#4053d3", "#b51d14", "#222222", "#666666"

df = load(find_csv())
f = standard_filter(df, verbose=False)
f = f[f.weighted_flowT_minus_outsideT.notna() & f.combined_cop.notna()].reset_index(drop=True)
ftemp = num(f.flow_temp).where(lambda v: (v >= 25) & (v <= 70))
wflow = num(f.weighted_flowT)
ok = ftemp.notna() & wflow.notna()
r = stats.pearsonr(ftemp[ok], wflow[ok])[0]

fig, (ax, ax2) = plt.subplots(1, 2, figsize=(13.5, 5.6),
                              gridspec_kw={"width_ratios": [1, 1.25]})

# ---------------- Panel A: commissioning gap ----------------
rng = np.random.default_rng(1)
jx = ftemp[ok] + rng.uniform(-0.35, 0.35, ok.sum())      # design temps are quantised
ax.scatter(jx, wflow[ok], s=20, alpha=0.55, color=BLUE, edgecolors="none")
lims = [24, 58]
ax.plot(lims, lims, ls="--", lw=1.2, color=MUT)
ax.annotate("runs as designed", (49.5, 51.5), rotation=38, fontsize=9, color=MUT)
ax.set_xlim(lims); ax.set_ylim(lims)
ax.set_xlabel("Declared design flow temperature (°C)")
ax.set_ylabel("Operated flow temperature, heat-weighted annual mean (°C)")
ax.set_title(f"The commissioning gap  (r = {r:.2f}, n = {ok.sum()})", fontsize=11)
ax.annotate(f"median declared 45.0 °C\nmedian operated 34.8 °C\nscatter sd 5.3 K",
            (0.03, 0.97), xycoords="axes fraction", va="top", fontsize=9.5,
            color=INK, bbox=dict(fc="white", ec="#cccccc", boxstyle="round,pad=0.35"))
ax.grid(alpha=0.25)

# ---------------- Panel B: PI ladder ----------------
# (label, PI, design_time?, group)   PIs = cv 90% |err| from design_model.py
rows = [
    ("Simulator testbed (n=293, identical machine)", None, None),
    ("  H* (measured over a year)",                    0.16, False),
    ("  H*_design — exact design params",              0.21, True),
    ("  weighted ΔT (measured over a year)",           0.34, False),
    ("  H*_design — fleet-grade input errors",         0.38, True),
    ("Real fleet (n=225)", None, None),
    ("  weighted ΔT (measured over a year)",           0.50, False),
    ("  coldest-day ΔT + DHW share + sizing",          0.62, True),
    ("  H*_design — corrected HL + coldest-day anchor",0.65, True),
    ("  H*_design — declared design params",           0.74, True),
]
ys = np.arange(len(rows))[::-1]
for y, (label, pi, design) in zip(ys, rows):
    if pi is None:
        ax2.text(-0.015, y, label, ha="left", va="center", fontsize=10.5,
                 fontweight="bold", color=INK, transform=ax2.get_yaxis_transform())
        continue
    c = BLUE if design else RED
    ax2.hlines(y, 0, pi, color=c, lw=2)
    ax2.plot(pi, y, "o", ms=8, color=c)
    ax2.text(pi + 0.015, y, f"±{pi:.2f}", va="center", fontsize=9.5, color=INK)
    ax2.text(0.0, y, label, ha="right", va="center", fontsize=9.5, color=INK,
             transform=ax2.get_yaxis_transform())

ax2.set_yticks([])
ax2.set_xlim(0, 0.85)
ax2.set_ylim(-0.7, len(rows) - 0.3)
ax2.set_xlabel("90% prediction interval on annual SPF (± SPF, cross-validated)")
ax2.set_title("What information buys what accuracy", fontsize=11)
ax2.grid(alpha=0.25, axis="x")
for s in ("left", "top", "right"):
    ax2.spines[s].set_visible(False)
h = [plt.Line2D([], [], color=BLUE, marker="o", lw=2, label="design-time inputs only"),
     plt.Line2D([], [], color=RED, marker="o", lw=2, label="needs a year of monitoring")]
ax2.legend(handles=h, frameon=False, loc="center right",
           bbox_to_anchor=(0.99, 0.47), fontsize=9.5)

fig.tight_layout()
out = "/var/www/hpmon_ai2/docs/figures/design_model.png"
fig.savefig(out, dpi=140, bbox_inches="tight")
print("saved", out)
