#!/usr/bin/env python3
"""Re-run the separate regression analysis (regression/heatpump_cop_regression_analysis.md)
on the shared export and standard filter, and test how its findings fit with the
rest of the work.

  1. Reproduce its headline tables: coldest-day lift models and the weighted dT benchmark.
  2. Utilisation: ln(UR) = ln(annual heat) - ln(badge). Is the gain from UR about
     sizing (capacity term matters) or just bigger homes (only heat matters)? And is it
     the same signal as the low-load share from the power histograms?
  3. Categoricals (emitter, controls, make, refrigerant) and DHW target, re-tested on
     the weighted dT base rather than coldest-day lift.
  4. Smaller claims: cooling filter, declared flow temp, declared vs measured heat loss,
     coldest-day reliability, cycling and flow-return dT.
"""

import numpy as np
import pandas as pd
import statsmodels.formula.api as smf
from sklearn.model_selection import KFold

from hpmon_analysis import load, standard_filter, find_csv, ROOT

DAY = 86400
RNG = 42


def num(s):
    return pd.to_numeric(s, errors="coerce")


def collapse(s, min_n=8):
    counts = s.value_counts()
    return s.where(s.isin(counts[counts >= min_n].index), "Other")


def build(f):
    """Derived variables, following the regression doc's data preparation."""
    D = pd.DataFrame(index=f.index)
    D["cop"] = num(f.combined_cop)
    D["W"] = num(f.weighted_flowT_minus_outsideT)
    days = num(f.combined_data_length) / DAY
    heat_annual = num(f.combined_heat_kwh) * 365 / days
    cap = num(f.hp_output).where(lambda s: s > 0)
    D["heat_mwh"] = heat_annual / 1000
    D["ln_heat"] = np.log(heat_annual)
    D["ln_cap"] = np.log(cap)
    D["UR"] = heat_annual / (cap * 8760)
    D["ln_UR"] = np.log(D.UR)

    F = num(f.measured_mean_flow_temp_coldest_day).where(lambda s: s > 0)
    D["F"] = F
    D["lift"] = F - num(f.measured_outside_temp_coldest_day)
    D["dhw"] = num(f.water_heat_kwh) / num(f.combined_heat_kwh)
    D["dhw_target"] = num(f.dhw_target_temperature).where(lambda s: s > 0)
    D["design_flow"] = num(f.flow_temp).where(lambda s: s > 0)

    hl = num(f.heat_loss)
    hl = hl.where(hl <= 100, hl / 1000).where(lambda s: s > 0.5)
    D["heat_loss"] = hl
    D["meas_hl"] = num(f.measured_heat_loss).where(lambda s: s > 0)
    D["hl_ratio"] = D.meas_hl / D.heat_loss
    D["ln_true_oversize"] = np.log(cap / D.meas_hl)
    D["ln_calc_oversize"] = np.log(cap / D.heat_loss)

    standby = (num(f.combined_elec_kwh) - num(f.running_elec_kwh)).clip(lower=0)
    D["standby_frac"] = standby / num(f.combined_elec_kwh)
    D["starts_per_run_h"] = num(f.combined_starts) / (num(f.running_data_length) / 3600)
    D["flow_return_dT"] = num(f.weighted_flowT_minus_returnT)

    D["emitter"] = (np.where(num(f.UFH) == 1, "UFH", "Radiators")
                    + np.where(num(f.new_radiators) == 1, ", new", ", old"))
    D["sh_control"] = collapse(f.space_heat_control_type.fillna("Unknown"))
    D["dhw_control"] = collapse(f.dhw_control_type.fillna("Unknown"))
    D["make"] = collapse(f.hp_manufacturer.fillna("Unknown"))
    D["refrigerant"] = collapse(f.refrigerant.fillna("Unknown"))

    mod = pd.read_csv(ROOT / "modulation_features.csv")[["id", "heat_frac_below_40pct"]]
    D["lowload"] = f[["id"]].merge(mod, on="id", how="left").heat_frac_below_40pct.values
    return D


def terms(formula):
    rhs = formula.split("~")[1]
    out = []
    for t in rhs.replace("C(", "").replace(")", "").split("+"):
        t = t.strip()
        if t and t != "1":
            out.append(t)
    return out


def cv_r2(formula, d, folds=5):
    y = d[formula.split("~")[0].strip()]
    oof = pd.Series(np.nan, index=d.index)
    for tr, te in KFold(folds, shuffle=True, random_state=RNG).split(d):
        m = smf.ols(formula, d.iloc[tr]).fit()
        oof.iloc[te] = m.predict(d.iloc[te]).to_numpy()
    resid = y - oof
    return 1 - (resid**2).sum() / ((y - y.mean())**2).sum(), np.quantile(resid.abs(), 0.9)


def table(title, formulas, d, extra=()):
    """Fit each formula on one common sample (rows complete for every formula)."""
    cols = {"cop", *extra}
    for fm in formulas:
        cols.update(terms(fm))
    s = d.dropna(subset=list(cols))
    print(f"\n--- {title} (n = {len(s)}) ---")
    print(f"{'model':48s} {'R2':>6s} {'adjR2':>6s} {'cvR2':>6s} {'90%':>5s}  p(last term)")
    fits = {}
    prev = None
    for fm in formulas:
        m = smf.ols(fm, s).fit()
        cv, pi = cv_r2(fm, s)
        p = ""
        if prev is not None:
            p = f"{m.compare_f_test(prev)[1]:.3g}" if m.df_model > prev.df_model else ""
        print(f"{fm.split('~')[1].strip():48s} {m.rsquared:6.3f} {m.rsquared_adj:6.3f} "
              f"{cv:6.3f} {pi:5.2f}  {p}")
        fits[fm] = m
        prev = m
    return s, fits


def show(m, names):
    for n in names:
        print(f"    {n:18s} {m.params[n]:+.4f} ± {1.96*m.bse[n]:.4f}  (p = {m.pvalues[n]:.3g})")


def group_residuals(s, resid, col):
    g = pd.DataFrame({"grp": s[col], "res": resid, "cop": s.cop})
    t = g.groupby("grp").agg(n=("res", "size"), raw_cop=("cop", "mean"),
                             resid=("res", "mean"), se=("res", "sem"))
    print(t.sort_values("resid", ascending=False).to_string(float_format="%.3f"))


def main():
    df = load(find_csv())
    f = standard_filter(df, verbose=False)
    f = f[f.weighted_flowT_minus_outsideT.notna() & f.combined_cop.notna()].copy()
    D = build(f)
    print(f"standard filter: n = {len(D)}")

    # ---------- 1. reproduce the headline tables ----------
    table("1a. coldest-day route", [
        "cop ~ F", "cop ~ lift", "cop ~ lift + ln_UR",
        "cop ~ lift + ln_UR + dhw", "cop ~ lift + ln_UR + dhw + C(emitter)",
    ], D)
    _, fits = table("1b. weighted dT benchmark", [
        "cop ~ W", "cop ~ W + ln_UR", "cop ~ W + ln_UR + dhw", "cop ~ W + lift",
    ], D, extra=("lift",))
    m = fits["cop ~ W + ln_UR"]
    print("  W + ln_UR coefficients:")
    show(m, ["W", "ln_UR"])

    # ---------- 2. what utilisation is ----------
    s, fits = table("2a. utilisation split", [
        "cop ~ W", "cop ~ W + ln_heat", "cop ~ W + ln_heat + ln_cap",
    ], D, extra=("ln_UR",))
    m = fits["cop ~ W + ln_heat + ln_cap"]
    print("  if UR is about sizing, ln_cap ≈ −ln_heat; if it's home size, ln_cap ≈ 0:")
    show(m, ["ln_heat", "ln_cap"])
    t = m.t_test("ln_heat + ln_cap = 0")
    print(f"    test ln_heat + ln_cap = 0 (pure UR): p = {float(t.pvalue):.3g}")
    m2 = smf.ols("cop ~ W + heat_mwh + ln_cap", s).fit()
    print("  same with heat in MWh (linear, as in README §2):")
    show(m2, ["heat_mwh", "ln_cap"])

    table("2b. utilisation vs low-load share and standby", [
        "cop ~ W", "cop ~ W + ln_UR", "cop ~ W + ln_UR + lowload",
        "cop ~ W + ln_UR + lowload + standby_frac",
    ], D)
    s, fits = table("2c. same, other order", [
        "cop ~ W", "cop ~ W + lowload", "cop ~ W + lowload + ln_heat",
        "cop ~ W + lowload + ln_heat + ln_cap",
    ], D)
    show(fits["cop ~ W + lowload + ln_heat + ln_cap"], ["lowload", "ln_heat", "ln_cap"])
    c = s[["ln_UR", "ln_heat", "ln_cap", "lowload", "standby_frac"]].corr()
    print("\n  correlations:")
    print(c.to_string(float_format="%.2f"))

    # ---------- 3. categoricals on the weighted dT base ----------
    for base in ["cop ~ W", "cop ~ W + ln_UR"]:
        print(f"\n=== 3. categoricals added to [{base.split('~')[1].strip()}] ===")
        for cat in ["emitter", "sh_control", "dhw_control", "make", "refrigerant"]:
            s = D.dropna(subset=terms(base))
            s = s[~s[cat].isin(["Unknown"])]
            m0 = smf.ols(base, s).fit()
            m1 = smf.ols(f"{base} + C({cat})", s).fit()
            cv0, _ = cv_r2(base, s)
            cv1, _ = cv_r2(f"{base} + C({cat})", s)
            p = m1.compare_f_test(m0)[1]
            print(f"  + {cat:12s} n={len(s):3d}  dR2={m1.rsquared-m0.rsquared:+.4f}  "
                  f"dadjR2={m1.rsquared_adj-m0.rsquared_adj:+.4f}  dcvR2={cv1-cv0:+.4f}  p={p:.3g}")
        s = D.dropna(subset=terms(base) + ["dhw_target"])
        m0, m1 = smf.ols(base, s).fit(), smf.ols(f"{base} + dhw_target", s).fit()
        print(f"  + dhw_target   n={len(s):3d}  dR2={m1.rsquared-m0.rsquared:+.4f}  "
              f"coef={m1.params.dhw_target:+.4f} ± {1.96*m1.bse.dhw_target:.4f}  "
              f"p={m1.pvalues.dhw_target:.3g}")

    for label, base in [("weighted dT", "cop ~ W"), ("coldest-day lift + ln_UR + dhw",
                                                     "cop ~ lift + ln_UR + dhw")]:
        s = D.dropna(subset=terms(base))
        resid = smf.ols(base, s).fit().resid
        for cat in ["make", "emitter", "sh_control", "dhw_control"]:
            print(f"\n  residual by {cat}, around {label}:")
            group_residuals(s, resid, cat)

    # ---------- 4. smaller claims ----------
    print("\n=== 4. smaller claims ===")
    f_all = standard_filter(df, max_cooling_kwh=np.inf, verbose=False)
    f_all = f_all[f_all.weighted_flowT_minus_outsideT.notna() & f_all.combined_cop.notna()]
    A = build(f_all)
    for fm in ["cop ~ W", "cop ~ lift + ln_UR + dhw"]:
        a = A.dropna(subset=terms(fm))
        b = D.dropna(subset=terms(fm))
        print(f"  cooling filter, {fm.split('~')[1].strip():22s} without: R2={smf.ols(fm, a).fit().rsquared:.3f} "
              f"(n={len(a)})   with: R2={smf.ols(fm, b).fit().rsquared:.3f} (n={len(b)})")

    s = D.dropna(subset=["lift", "ln_UR", "dhw", "design_flow", "F"])
    base = smf.ols("cop ~ lift + ln_UR + dhw", s).fit()
    m = smf.ols("cop ~ lift + ln_UR + dhw + design_flow", s).fit()
    print(f"  declared design flow temp: n={len(s)}  median {s.design_flow.median():.1f} °C "
          f"vs coldest-day {s.F.median():.1f} °C   dR2={m.rsquared-base.rsquared:+.4f}  "
          f"p={m.pvalues.design_flow:.3g}")

    s = D.dropna(subset=["hl_ratio"])
    q = s.hl_ratio.quantile([0.25, 0.5, 0.75])
    print(f"  measured/declared heat loss: n={len(s)}  median {q[0.5]:.3f} "
          f"(IQR {q[0.25]:.3f}–{q[0.75]:.3f})  r={s.meas_hl.corr(s.heat_loss):.2f}")
    s = D.dropna(subset=["lift", "ln_UR", "dhw", "ln_true_oversize", "ln_calc_oversize", "W"])
    for base in ["cop ~ lift + ln_UR + dhw", "cop ~ W + ln_UR"]:
        for v in ["ln_true_oversize", "ln_calc_oversize"]:
            m = smf.ols(f"{base} + {v}", s).fit()
            print(f"  {base.split('~')[1].strip():22s} + {v:17s} n={len(s)}  "
                  f"coef={m.params[v]:+.3f}  p={m.pvalues[v]:.3g}")

    s = D.dropna(subset=["lift", "W"])
    bl = smf.ols("cop ~ lift", s).fit().params.lift
    bw = smf.ols("cop ~ W", s).fit().params.W
    print(f"  coldest-day reliability: slope lift {bl:.4f} / slope W {bw:.4f} = {bl/bw:.2f}  "
          f"(r lift–W = {s.lift.corr(s.W):.2f})")

    table("4b. cycling and flow-return dT on the weighted base", [
        "cop ~ W + ln_UR", "cop ~ W + ln_UR + starts_per_run_h",
    ], D)
    table("4c.", ["cop ~ W + ln_UR", "cop ~ W + ln_UR + flow_return_dT"], D)


if __name__ == "__main__":
    main()
