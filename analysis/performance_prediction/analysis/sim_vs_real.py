#!/usr/bin/env python3
"""Compare the physics-simulator Monte Carlo sweep with the real fleet data:
SPF vs weighted flowT - outsideT, fit quality and 90% prediction interval."""

import sys
import numpy as np
import pandas as pd
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt

sys.path.insert(0, "/var/www/hpmon_ai2/analysis")
from hpmon_analysis import load, standard_filter, find_csv, linfit
from residual_analysis import num
from scipy import stats

BLUE, RED, GRAY, INK = "#2a78d6", "#e34948", "#9a9892", "#0b0b0b"

# --- real fleet ---
df = load(find_csv())
f = standard_filter(df, verbose=False)
f = f[f.weighted_flowT_minus_outsideT.notna() & f.combined_cop.notna()]
real = linfit(f.weighted_flowT_minus_outsideT, num(f.combined_cop))

# --- simulated fleet ---
s = pd.read_csv("/var/www/hpmon_ai2/sim_results.csv")
n0 = len(s)
# comfort filter: exclude designs that badly fail to heat the home
s = s[s.degree_hours_below_setpoint < 2500].copy()
print(f"simulated scenarios: {n0}, after comfort filter: {len(s)}")

sim = linfit(s.weighted_flowT_minus_outsideT, s.spf)
resid_sim = s.spf - (sim["slope"] * s.weighted_flowT_minus_outsideT + sim["intercept"])

print(f"\nREAL  (n={real['n']}):  SPF = {real['slope']:.4f}*dT + {real['intercept']:.4f}"
      f"   R2={real['r2']:.3f}  RMSE={real['rmse']:.3f}  90%PI=+/-{real['pi90']:.2f}")
print(f"SIM   (n={sim['n']}):  SPF = {sim['slope']:.4f}*dT + {sim['intercept']:.4f}"
      f"   R2={sim['r2']:.3f}  RMSE={sim['rmse']:.3f}  90%PI=+/-{sim['pi90']:.2f}")
q90 = np.quantile(np.abs(resid_sim), 0.90)
print(f"SIM empirical 90% |resid| = {q90:.3f}")

# what drives the simulator's own residual scatter?
print("\n--- sim residual correlates (the physics-only spread) ---")
cand = ["wa_prc_carnot", "heat_loss", "capacity", "radiator_ratio", "system_DT",
        "setpointT", "setback", "heat_kwh", "degree_hours_below_setpoint"]
s["dhw_frac"] = s.dhw_heat_kwh / s.heat_kwh
s["load_ratio"] = s.heat_kwh / (s.capacity / 1000)
cand += ["dhw_frac", "load_ratio"]
rows = []
for c in cand:
    pr, pp = stats.pearsonr(s[c], resid_sim)
    sr, _ = stats.spearmanr(s[c], resid_sim)
    rows.append({"feature": c, "pearson_r": pr, "p": pp, "spearman_r": sr})
t = pd.DataFrame(rows).set_index("feature")
print(t.reindex(t.spearman_r.abs().sort_values(ascending=False).index)
        .to_string(float_format="%.3f"))

# --- overlay plot ---
fig, ax = plt.subplots(figsize=(10, 7))
ax.grid(alpha=0.22); ax.set_axisbelow(True)
for sp in ("top", "right"):
    ax.spines[sp].set_visible(False)

ax.scatter(f.weighted_flowT_minus_outsideT, f.combined_cop, s=20, alpha=0.45,
           color=GRAY, edgecolors="none", label=f"measured fleet (n={real['n']})")
ax.scatter(s.weighted_flowT_minus_outsideT, s.spf, s=20, alpha=0.6,
           color=BLUE, edgecolors="none", label=f"simulated designs (n={sim['n']}, 47% carnot fixed)")

xs = np.linspace(18, 43, 60)
ax.plot(xs, real["slope"] * xs + real["intercept"], color=INK, lw=1.8, ls="--",
        label=f"real fit: R²={real['r2']:.2f}, 90% PI ±{real['pi90']:.2f}")
ax.plot(xs, sim["slope"] * xs + sim["intercept"], color=RED, lw=2,
        label=f"sim fit: R²={sim['r2']:.2f}, 90% PI ±{sim['pi90']:.2f}")
ax.fill_between(xs, sim["slope"] * xs + sim["intercept"] - sim["pi90"],
                sim["slope"] * xs + sim["intercept"] + sim["pi90"], color=RED, alpha=0.10)

ax.set_xlabel("weighted average flowT − outsideT (K)")
ax.set_ylabel("SPF")
ax.set_title("Physics simulator (fixed 47% carnot machine) vs measured fleet")
ax.legend(frameon=False, loc="upper right")
fig.tight_layout()
fig.savefig("/var/www/hpmon_ai2/docs/figures/sim_vs_real.png", dpi=140)
print("\nsaved sim_vs_real.png")
