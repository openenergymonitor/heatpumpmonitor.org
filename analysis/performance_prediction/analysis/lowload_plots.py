#!/usr/bin/env python3
"""Visualize the low-load-factor effect on SPF."""

import json
import sys
import numpy as np
import pandas as pd
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.colors import LinearSegmentedColormap

sys.path.insert(0, "/var/www/hpmon_ai2/analysis")
from hpmon_analysis import load, standard_filter, find_csv, linfit
from residual_analysis import num

BLUE, RED, GRAY, INK = "#2a78d6", "#e34948", "#9a9892", "#0b0b0b"
SEQ = LinearSegmentedColormap.from_list("blues", ["#dbe9f9", "#2a78d6", "#0c2d5c"])

df = load(find_csv())
f = standard_filter(df, verbose=False)
f = f[f.weighted_flowT_minus_outsideT.notna() & f.combined_cop.notna()].reset_index(drop=True)
M = pd.read_csv("/var/www/hpmon_ai2/modulation_features.csv")
t = f.join(M.drop(columns="id"))
t["dT"] = num(t.weighted_flowT_minus_outsideT)
base = linfit(t.dT, num(t.combined_cop))
t["resid"] = num(t.combined_cop) - (base["slope"] * t.dT + base["intercept"])
hf = t.heat_frac_below_40pct

fig = plt.figure(figsize=(13.5, 10))
gs = fig.add_gridspec(2, 2, height_ratios=[1.15, 1], hspace=0.3, wspace=0.25)

# --- A: main relationship colored by low-load share ---
ax = fig.add_subplot(gs[0, 0])
ax.grid(alpha=0.22); ax.set_axisbelow(True)
sc = ax.scatter(t.dT, t.combined_cop, c=hf.clip(0, 0.6), cmap=SEQ, s=30, alpha=0.9,
                edgecolors="white", linewidths=0.4)
xs = np.linspace(t.dT.min(), t.dT.max(), 50)
ax.plot(xs, base["slope"] * xs + base["intercept"], color=INK, lw=1.5, ls="--")
cb = fig.colorbar(sc, ax=ax, pad=0.02)
cb.set_label("share of heat delivered below 40% of rated output")
ax.set_xlabel("weighted flowT − outsideT (K)")
ax.set_ylabel("SPF H4")
ax.set_title("Dark points (high low-load share) sit below the fit;\npale points sit above",
             fontsize=11)

# --- B: residual vs low-load share, with binned means + examples ---
ax = fig.add_subplot(gs[0, 1])
ax.grid(alpha=0.22); ax.set_axisbelow(True)
ax.scatter(hf, t.resid, s=22, alpha=0.5, color=BLUE, edgecolors="none")
bins = np.quantile(hf.dropna(), np.linspace(0, 1, 9))
mid, mean = [], []
for a, b in zip(bins[:-1], bins[1:]):
    m = (hf >= a) & (hf <= b)
    if m.sum() >= 5:
        mid.append(hf[m].mean()); mean.append(t.resid[m].mean())
ax.plot(mid, mean, color=RED, lw=2.5, marker="o", ms=6, label="mean residual (octile bins)")
ax.axhline(0, color=GRAY, lw=0.8)
for sid in [61, 341, 118, 584, 276]:
    r = t[t.id == sid].iloc[0]
    ax.annotate(f"#{sid}", (r.heat_frac_below_40pct, r.resid),
                xytext=(6, 4), textcoords="offset points", fontsize=9, color=INK)
    ax.scatter([r.heat_frac_below_40pct], [r.resid], s=42, facecolors="none",
               edgecolors=INK, linewidths=1.2)
ax.set_xlabel("share of heat delivered below 40% of rated output")
ax.set_ylabel("SPF residual vs temperature baseline")
ax.set_title("The effect itself: ~0.4 SPF from best to worst octile", fontsize=11)
ax.legend(frameon=False, loc="lower left")

# --- C/D: real load-factor histograms, under- vs over-performers ---
examples = [("underperformers", RED, [(341, "Vitocal 150A 8kW, SPF 3.78 (−0.62)"),
                                      (118, "Midea 16kW, SPF 3.45 (−0.47)")]),
            ("overperformers", BLUE, [(584, "Arotherm+ 5kW, SPF 5.01 (+0.60)"),
                                      (276, "Ecodan 6kW, SPF 4.52 (+0.66)")])]
for col, (label, color, systems) in enumerate(examples):
    ax = fig.add_subplot(gs[1, col])
    ax.grid(alpha=0.22); ax.set_axisbelow(True)
    for (sid, desc), ls in zip(systems, ["-", "--"]):
        d = json.loads(open(f"/var/www/hpmon_ai2/histograms/{sid}_heat.json").read())
        p = d["min"] + d["div"] * (np.arange(len(d["data"])) + 0.5)
        kwh = np.asarray(d["data"])
        rated = float(f.loc[f.id == sid, "hp_output"].iloc[0]) * 1000
        lf, k = p / rated, kwh
        keep = (p >= 500) & (lf <= 1.6)
        share = 100 * k[keep] / k[keep].sum()
        ax.plot(lf[keep], share, color=color, lw=2, ls=ls, label=f"#{sid} {desc}")
    ax.axvspan(0, 0.4, color="#eda100", alpha=0.10)
    ax.axvline(0.4, color="#c98500", lw=1, ls=":")
    ax.text(0.38, ax.get_ylim()[1] * 0.02, "below 40% of rated ", ha="right",
            fontsize=9, color="#8a5c00")
    ax.set_xlabel("heat output as fraction of rated capacity")
    ax.set_ylabel("% of annual heat per bin")
    ax.set_title(f"Where {label} deliver their heat", fontsize=11)
    ax.legend(frameon=False, fontsize=9)
    ax.set_xlim(0, 1.6)

fig.suptitle("Heat delivered at low load factor drags SPF below what temperatures predict"
             f"  (n={len(t)} ASHPs)", fontsize=13)
out = "/var/www/hpmon_ai2/docs/figures/lowload_effect.png"
fig.savefig(out, dpi=140, bbox_inches="tight")
print("saved", out)
