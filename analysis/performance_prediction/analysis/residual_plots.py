#!/usr/bin/env python3
"""Figure for the residual-variance investigation (4 panels)."""

import sys
import numpy as np
import pandas as pd
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt

sys.path.insert(0, "/var/www/hpmon_ai2/analysis")
from hpmon_analysis import load, standard_filter, find_csv, linfit
from residual_analysis import build_features, num, cv_eval

BLUE, RED, YELLOW, GRAY = "#2a78d6", "#e34948", "#eda100", "#52514e"

df = load(find_csv())
f = standard_filter(df, verbose=False)
f = f[f.weighted_flowT_minus_outsideT.notna() & f.combined_cop.notna()].copy()
y = num(f.combined_cop)
X = build_features(f)
base = linfit(f.weighted_flowT_minus_outsideT, y)
resid = y - (base["slope"] * X.dT + base["intercept"])

fig, axes = plt.subplots(2, 2, figsize=(13, 9.5))
for ax in axes.flat:
    ax.grid(alpha=0.22)
    ax.set_axisbelow(True)
    for s in ("top", "right"):
        ax.spines[s].set_visible(False)

# 1. residual vs annual heat demand
ax = axes[0, 0]
ax.scatter(X.annual_heat_kwh / 1000, resid, s=16, alpha=0.55, color=BLUE, edgecolors="none")
r1 = linfit(X.annual_heat_kwh, resid)
xs = np.linspace(X.annual_heat_kwh.min(), X.annual_heat_kwh.max(), 50)
ax.plot(xs / 1000, r1["slope"] * xs + r1["intercept"], color=RED, lw=2)
ax.axhline(0, color=GRAY, lw=0.8)
ax.set_xlabel("Annual heat output (MWh)")
ax.set_ylabel("COP residual vs dT baseline")
ax.set_title(f"Bigger heat demand → outperforms the dT fit  (r={r1['r']:.2f})", fontsize=11)

# 2. standby COP penalty distribution
ax = axes[0, 1]
standby_kwh = (num(f.combined_elec_kwh) - num(f.running_elec_kwh)).clip(lower=0)
penalty = num(f.running_cop) - num(f.running_cop) / (1 + standby_kwh / num(f.running_elec_kwh))
ax.hist(penalty, bins=30, color=BLUE, alpha=0.85)
ax.axvline(penalty.median(), color=RED, lw=2)
ax.annotate(f"median {penalty.median():.2f}", xy=(penalty.median(), ax.get_ylim()[1] * 0.9),
            xytext=(6, 0), textcoords="offset points", color=RED)
ax.set_xlabel("COP lost to standby electricity (exact decomposition)")
ax.set_ylabel("homes")
ax.set_title("Standby drag: real but small (median ≈ 0.10 COP)", fontsize=11)

# 3. residual by manufacturer
ax = axes[1, 0]
g = resid.groupby(f.hp_manufacturer)
t = pd.DataFrame({"n": g.size(), "mean": g.mean(), "std": g.std()}).query("n>=5").sort_values("mean")
ypos = np.arange(len(t))
ax.errorbar(t["mean"], ypos, xerr=t["std"], fmt="o", color=BLUE, ecolor="#b9cfe9",
            elinewidth=3, capsize=0, markersize=7)
ax.axvline(0, color=GRAY, lw=0.8)
ax.set_yticks(ypos, [f"{m}  (n={n})" for m, n in zip(t.index, t.n)])
ax.set_xlabel("COP residual vs dT baseline (mean ± sd)")
ax.set_title("Manufacturer spread: ~0.3 COP between makes", fontsize=11)

# 4. out-of-fold predicted vs actual, best honest model
ax = axes[1, 1]
from sklearn.linear_model import LinearRegression
from sklearn.pipeline import make_pipeline
from sklearn.impute import SimpleImputer
cols = ["dT", "annual_heat_kwh", "standby_elec_frac"] + [c for c in X if c.startswith("make_")]
r = cv_eval(X[cols], y, make_pipeline(SimpleImputer(strategy="median"), LinearRegression()))
ax.scatter(r["oof"], y, s=16, alpha=0.55, color=BLUE, edgecolors="none")
lims = [y.min() - 0.15, y.max() + 0.15]
ax.plot(lims, lims, color=GRAY, lw=1)
ax.plot(lims, [v + r["cv_pi90"] for v in lims], color=RED, lw=1, ls="--")
ax.plot(lims, [v - r["cv_pi90"] for v in lims], color=RED, lw=1, ls="--")
ax.set_xlim(lims); ax.set_ylim(lims)
ax.set_xlabel("Predicted SPF (held-out, dT + heat demand + standby + make)")
ax.set_ylabel("Measured SPF H4")
ax.set_title(f"Best honest model: cv R²={r['cv_r2']:.2f}, 90% of homes within ±{r['cv_pi90']:.2f}",
             fontsize=11)

fig.suptitle("What explains the scatter around SPF ~ (flowT − outsideT)?  n=225 ASHPs",
             fontsize=13, y=0.995)
fig.tight_layout()
out = "/var/www/hpmon_ai2/docs/figures/residual_investigation.png"
fig.savefig(out, dpi=140)
print("saved", out)
