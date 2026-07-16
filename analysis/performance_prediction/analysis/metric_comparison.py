#!/usr/bin/env python3
"""Which annualised, electricity-free metrics predict SPF best?

Testbed: the simulator sweep (ground truth physics, identical 47%-carnot machine).
Validation: the same metrics (where available) on the real fleet.

Candidate metrics, all computable from monitored flowT/outsideT/heat + metadata:
  dT_w                  heat-weighted mean (flowT - outsideT)      [current best]
  H_carnot              heat-weighted harmonic mean ideal Carnot COP
  H_carnot_var          same with load-dependent offsets (needs rated capacity)
  dT_sd_w               heat-weighted std of dT (distribution width)
  flowT_w               heat-weighted mean flow temp (level)
  runtime_frac          fraction of time running
  starts_per_day        cycling
  heat_frac_below_40pct low-load-factor share
  mean_load_factor      mean output while running / rated
  dhw_frac              DHW share of heat
"""

import sys
import numpy as np
import pandas as pd

sys.path.insert(0, "/var/www/hpmon_ai2/analysis")
from hpmon_analysis import load, standard_filter, find_csv, linfit
from residual_analysis import num, cv_eval
from sklearn.linear_model import LinearRegression
from sklearn.pipeline import make_pipeline
from sklearn.impute import SimpleImputer


def cv_report(X, y, name, folds=5):
    r = cv_eval(X, y, make_pipeline(SimpleImputer(strategy="median"), LinearRegression()), folds)
    print(f"  {name:44s} cv_R2={r['cv_r2']:.3f}  cv_RMSE={r['cv_rmse']:.3f}  90%|err|={r['cv_pi90']:.2f}")
    return r


# ================= SIMULATED FLEET =================
s = pd.read_csv("/var/www/hpmon_ai2/sim_results.csv")
s = s[s.degree_hours_below_setpoint < 2500].reset_index(drop=True)
s["dhw_frac"] = s.dhw_heat_kwh / s.heat_kwh
y = s.spf
print(f"SIMULATED (n={len(s)}, identical machine, physics only)")
cv_report(s[["weighted_flowT_minus_outsideT"]], y, "dT_w (current metric)")
cv_report(s[["H_carnot"]], y, "H_carnot")
cv_report(s[["H_carnot_var"]], y, "H_carnot_var")
cv_report(s[["H_carnot_var", "runtime_frac"]], y, "H_carnot_var + runtime_frac")
cv_report(s[["H_carnot_var", "dhw_frac"]], y, "H_carnot_var + dhw_frac")
cv_report(s[["H_carnot_var", "runtime_frac", "starts_per_day"]],
          y, "H_carnot_var + runtime + cycling")
cv_report(s[["H_carnot_var", "runtime_frac", "starts_per_day", "dhw_frac",
             "heat_frac_below_40pct", "mean_load_factor", "dT_sd_w", "flowT_w"]],
          y, "all metrics")
cv_report(s[["weighted_flowT_minus_outsideT", "dT_sd_w"]], y, "dT_w + dT_sd_w")

# where does the remaining error live? (all-metrics model residual correlates)
from scipy import stats as st
Xall = s[["H_carnot_var", "runtime_frac", "starts_per_day", "dhw_frac",
          "heat_frac_below_40pct", "mean_load_factor", "dT_sd_w", "flowT_w"]]
r = cv_eval(Xall, y, make_pipeline(SimpleImputer(strategy="median"), LinearRegression()))
res = y - r["oof"]
print("\n  residual correlates of the all-metrics model:")
for c in ["standby", "heat_kwh", "capacity"]:
    if c in s:
        print(f"    {c:22s} r={st.pearsonr(s[c], res)[0]:+.3f}")

# ================= REAL FLEET =================
df = load(find_csv())
f = standard_filter(df, verbose=False)
f = f[f.weighted_flowT_minus_outsideT.notna() & f.combined_cop.notna()].reset_index(drop=True)
yr = num(f.combined_cop)
R = pd.DataFrame(index=f.index)
R["dT_w"] = num(f.weighted_flowT_minus_outsideT)
R["H_carnot"] = yr / (num(f.combined_prc_carnot) / 100)        # elec cancels: temps+heat only
R["runtime_frac"] = num(f.running_data_length) / num(f.combined_data_length)
R["starts_per_day"] = num(f.combined_starts) / (num(f.combined_data_length) / 86400)
R["dhw_frac"] = num(f.water_heat_kwh) / (num(f.space_heat_kwh) + num(f.water_heat_kwh))
M = pd.read_csv("/var/www/hpmon_ai2/modulation_features.csv")
R["heat_frac_below_40pct"] = M.heat_frac_below_40pct.to_numpy()
R["mean_load_factor"] = num(f.running_heat_mean) / (num(f.hp_output) * 1000)
R["annual_heat_kwh"] = num(f.combined_heat_kwh)

print(f"\nREAL FLEET (n={len(f)})")
cv_report(R[["dT_w"]], yr, "dT_w (current metric)")
cv_report(R[["H_carnot"]], yr, "H_carnot")
cv_report(R[["H_carnot", "runtime_frac"]], yr, "H_carnot + runtime_frac")
cv_report(R[["H_carnot", "runtime_frac", "starts_per_day", "dhw_frac",
             "heat_frac_below_40pct", "mean_load_factor"]], yr, "sim-guided metric set")
cv_report(R[["H_carnot", "runtime_frac", "starts_per_day", "dhw_frac",
             "heat_frac_below_40pct", "mean_load_factor", "annual_heat_kwh"]],
          yr, "sim-guided + annual_heat_kwh")
