#!/usr/bin/env python3
"""Does the published %-of-ideal-Carnot metric (fixed +2/-6 offsets) overstate
machine quality relative to the variable-offset (H*) convention?

Testbed: the simulator sweep — every system is by construction an identical
machine at exactly 47% of variable-offset Carnot, so any spread or bias in an
apparent %-carnot figure is an artifact of the metric, not the machine.

  prc_fixed = SPF / H_carnot      (heat-weighted harmonic ideal, +2/-6 fixed —
                                   the heatpumpmonitor convention)
  prc_var   = SPF / H_carnot_var  (same with +3r/-8r load-dependent offsets)
"""

import sys

import numpy as np
import pandas as pd
from scipy import stats

sys.path.insert(0, "/var/www/hpmon_ai2/analysis")
from hpmon_analysis import load, standard_filter, find_csv
from residual_analysis import num

s = pd.read_csv("/var/www/hpmon_ai2/sim_results.csv")
s = s[s.degree_hours_below_setpoint < 2500].reset_index(drop=True)

prc_fixed = s.spf / s.H_carnot
prc_var = s.spf / s.H_carnot_var

print(f"SIMULATED (n={len(s)}, identical machine, true quality 0.470)")
for name, v in [("fixed +2/-6 (published convention)", prc_fixed),
                ("fixed +2/-6, running COP (tool wa_prc_carnot)", s.wa_prc_carnot),
                ("variable +3r/-8r (H* convention)", prc_var)]:
    print(f"  {name:46s} median {v.median():.3f}  IQR {v.quantile(.25):.3f}-{v.quantile(.75):.3f}  sd {v.std():.3f}")

print("\n  apparent %carnot vs mean load factor (identical machines!):")
for name, v in [("fixed +2/-6", prc_fixed), ("variable", prc_var)]:
    r, p = stats.pearsonr(s.mean_load_factor, v)
    print(f"    {name:12s} r = {r:+.3f} (p={p:.1e})")
for lo, hi in [(0.2, 0.35), (0.35, 0.5), (0.5, 0.7)]:
    m = (s.mean_load_factor >= lo) & (s.mean_load_factor < hi)
    print(f"    load factor {lo:.2f}-{hi:.2f}: fixed-offset reads {prc_fixed[m].median():.3f} (n={m.sum()})")

# real fleet: published prc_carnot level and its (absent) load-factor slope
df = load(find_csv())
f = standard_filter(df, verbose=False)
f = f[f.weighted_flowT_minus_outsideT.notna() & f.combined_cop.notna()].reset_index(drop=True)
prc = num(f.combined_prc_carnot).where(lambda v: (v > 25) & (v < 70)) / 100
lf = (num(f.running_heat_mean) / (num(f.hp_output) * 1000)).where(lambda v: (v > 0.05) & (v < 1.2))
ok = prc.notna() & lf.notna()
r, p = stats.pearsonr(lf[ok], prc[ok])
print(f"\nREAL FLEET (n={ok.sum()})")
print(f"  published combined_prc_carnot: median {prc.median():.3f}  IQR {prc.quantile(.25):.3f}-{prc.quantile(.75):.3f}")
print(f"  vs load factor while running:  r = {r:+.3f} (p={p:.2f})")
print("  (identical machines would show r = -0.75: the fixed-offset flattery of")
print("   low-load systems is cancelling a genuine oversizing penalty)")
