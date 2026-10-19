#!/usr/bin/env python3
"""Figures for the H* fleet test (docs/load_and_quality.md).

  hstar_p_test.png         A: SPF against H*, B: the P test, SPF/H_fixed against P = H*/H_fixed
  utilisation_vs_rbar.png  utilisation against heat-weighted mean load ratio r-bar

Validated air source systems from the raw feed scan, as in analyse_hstar_fleet.py.
r-bar comes from the per-system histogram hstar_hist.csv (not in git; regenerate
with scripts/feed_scan/feed_scan_5.php at the repo root).

Usage: python3 scripts/hstar_plots.py
"""

import numpy as np
import pandas as pd
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.colors import LinearSegmentedColormap

from hpmon_analysis import linfit, ROOT, FIGURES

BLUE, ORANGE, INK, GRAY = "#2a78d6", "#e8692f", "#333333", "#52514e"
SEQ = LinearSegmentedColormap.from_list("blues", ["#dbe9f9", "#2a78d6", "#0c2d5c"])

# ---------- data ----------
s = pd.read_csv(ROOT / "hstar_fleet_hist.csv")
s = s[(s.hp_type == "Air Source")
      & ((s.calc_weighted_dT - s.rec_weighted_dT).abs() <= 0.5)
      & ((s.spf_window - s.rec_combined_cop).abs() <= 0.25)].copy()
s["id"] = s.id.astype(str)

h = pd.read_csv(ROOT / "hstar_hist.csv", dtype={"id": str})
h = h[h.id.isin(s.id)]
rbar = (h.heat_kwh * h.r).groupby(h.id).sum() / h.heat_kwh.groupby(h.id).sum()
s["r_bar"] = s.id.map(rbar)
s["utilisation"] = s.heat_kwh_all * 365 / s.days_scanned / (s.hp_output_kw * 8760)
s["P"] = s.H_star / s.H_fixed
s["quality"] = s.spf_window / s.H_fixed
n = len(s)
print(f"validated air source systems: {n}")
print(f"r_bar median {s.r_bar.median():.2f} sd {s.r_bar.std():.2f};  "
      f"utilisation median {s.utilisation.median():.2f};  sd(P) {s.P.std():.3f}")


def style(ax):
    ax.grid(alpha=0.22)
    ax.set_axisbelow(True)
    for side in ("top", "right"):
        ax.spines[side].set_visible(False)


# ---------- hstar_p_test.png ----------
fig, (a, b) = plt.subplots(1, 2, figsize=(14, 6))
for ax in (a, b):
    style(ax)

fa = linfit(s.H_star, s.spf_window)
corr = np.corrcoef(s.H_star, -s.calc_weighted_dT)[0, 1]
sc = a.scatter(s.H_star, s.spf_window, c=s.calc_weighted_dT, cmap=SEQ, s=26,
               edgecolors="none", alpha=0.9)
xs = np.linspace(s.H_star.min(), s.H_star.max(), 50)
a.plot(xs, fa["slope"] * xs + fa["intercept"], color=INK, lw=1.2, ls="--")
fig.colorbar(sc, ax=a, pad=0.02).set_label("weighted flowT − outsideT (K)")
a.set_xlabel("H*: ideal COP, temperatures + load")
a.set_ylabel("Measured SPF")
a.set_title(f"A. SPF against H* mostly restates temperature\n"
            f"R² = {fa['r2']:.2f}, corr(H*, −ΔT) = {corr:.2f}", loc="left", fontsize=11)

fb = linfit(s.P, s.quality)
q = (s.spf_window / s.H_star).median()
b.scatter(s.P, s.quality, s=26, color=BLUE, edgecolors="none", alpha=0.75)
xs = np.linspace(s.P.min(), s.P.max(), 50)
b.plot(xs, q * xs, color=ORANGE, lw=2, label=f"identical machines (slope q ≈ {q:.2f})")
b.plot(xs, fb["slope"] * xs + fb["intercept"], color=INK, lw=2, ls="--",
       label=f"observed fit (slope {fb['slope']:+.2f})")
b.legend(frameon=False, loc="upper left")
b.set_xlabel("P = H*/H_fixed: the load model's adjustment for the year")
b.set_ylabel("SPF / H_fixed: performance against temperature-only benchmark")
b.set_title(f"B. The P test: the predicted slope is absent\n"
            f"{n} validated air source systems, corr = {fb['r']:+.2f}", loc="left", fontsize=11)

fig.tight_layout()
out = FIGURES / "hstar_p_test.png"
fig.savefig(out, dpi=140, bbox_inches="tight")
print("saved", out)

# ---------- utilisation_vs_rbar.png ----------
ok = s.r_bar.notna()
fu = linfit(s.r_bar[ok], s.utilisation[ok])
fig, ax = plt.subplots(figsize=(9, 6.5))
style(ax)
ax.scatter(s.r_bar[ok], s.utilisation[ok], s=26, color=BLUE, edgecolors="none", alpha=0.75)
xs = np.linspace(s.r_bar[ok].min(), s.r_bar[ok].max(), 50)
ax.plot(xs, fu["slope"] * xs + fu["intercept"], color=INK, lw=1.2, ls="--")
ax.text(0.06, 0.82, "runs long hours,\ngently modulating", transform=ax.transAxes,
        color=GRAY, ha="left")
ax.text(0.97, 0.08, "runs rarely, but\nhard when it does", transform=ax.transAxes,
        color=GRAY, ha="right")
ax.set_ylim(bottom=0)
ax.set_xlabel("r̄: heat-weighted mean load ratio while running")
ax.set_ylabel("Utilisation: annual heat ÷ (capacity × hours)")
ax.set_title(f"Utilisation and mean load ratio are related but far from the same thing\n"
             f"{ok.sum()} validated air source systems, r = {fu['r']:+.2f}",
             loc="left", fontsize=11)
fig.tight_layout()
out = FIGURES / "utilisation_vs_rbar.png"
fig.savefig(out, dpi=140, bbox_inches="tight")
print("saved", out)
