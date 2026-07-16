#!/usr/bin/env python3
"""Stage 2: explain the residual scatter around the COP ~ dT baseline.

Hypotheses under test (from Trystan):
  1. Modulation pattern (compressor speed / load factor) affects COP at same temps.
  2. Primary pipework volume -> heat-meter losses (not directly captured; proxies only).
  3. Standby electricity drags combined_cop down in low-demand homes.

Approach:
  a. Fit baseline combined_cop ~ weighted_flowT_minus_outsideT, take residuals.
  b. Engineer candidate features, correlate each with the residual.
  c. Decompose standby drag exactly: combined_cop = running_cop / (1 + standby_elec/running_elec).
  d. Multivariate OLS + gradient boosting with cross-validation for an honest
     predictive-power comparison vs the baseline.
"""

import sys
import numpy as np
import pandas as pd
from scipy import stats

sys.path.insert(0, "/var/www/hpmon_ai2/analysis")
from hpmon_analysis import load, standard_filter, find_csv, linfit

DAY = 86400
RNG = 42


def num(s):
    return pd.to_numeric(s, errors="coerce")


def build_features(f):
    """Engineer candidate predictors. prc_carnot fields are deliberately excluded
    as predictors: %carnot = COP / carnot(temps), so it contains the target."""
    X = pd.DataFrame(index=f.index)
    days = num(f.combined_data_length) / DAY

    # --- temperatures (baseline + refinements) ---
    X["dT"] = num(f.weighted_flowT_minus_outsideT)
    X["flowT"] = num(f.weighted_flowT)
    X["outsideT"] = num(f.weighted_outsideT)
    X["carnot_cop"] = (X.flowT + 273.15) / X.dT          # ideal Carnot at weighted temps
    X["flow_return_dT"] = num(f.weighted_flowT_minus_returnT)
    X["roomT"] = num(f.combined_roomT_mean)

    # --- modulation / load factor ---
    hp_kw = num(f.hp_output).replace(0, np.nan)
    X["load_factor"] = num(f.running_heat_mean) / (hp_kw * 1000)   # mean output while running / rated
    X["elec_load_factor"] = num(f.running_elec_mean) / (hp_kw * 1000)
    X["hp_output_kw"] = hp_kw
    X["oversize"] = num(f.oversizing_factor).replace(0, np.nan)
    X["eflh"] = num(f.combined_heat_kwh) / hp_kw                   # equivalent full-load hours

    # --- standby / runtime ---
    run_s = num(f.running_data_length)
    comb_s = num(f.combined_data_length)
    X["runtime_frac"] = run_s / comb_s
    standby_kwh = (num(f.combined_elec_kwh) - num(f.running_elec_kwh)).clip(lower=0)
    off_h = (comb_s - run_s) / 3600
    X["standby_power_w"] = standby_kwh * 1000 / off_h
    X["standby_elec_frac"] = standby_kwh / num(f.combined_elec_kwh)

    # --- cycling ---
    X["starts_per_run_hour"] = num(f.combined_starts) / (run_s / 3600)
    X["starts_per_day"] = num(f.combined_starts) / days
    wcc = num(f.weighted_cycle_count).replace(0, np.nan)
    X["weighted_cycles_per_day"] = wcc / days

    # --- demand / DHW ---
    X["annual_heat_kwh"] = num(f.combined_heat_kwh)
    X["heat_kwh_per_m2"] = num(f.combined_heat_kwh_per_m2).replace(0, np.nan)
    X["dhw_frac"] = num(f.water_heat_kwh) / (num(f.space_heat_kwh) + num(f.water_heat_kwh))
    X["water_cop"] = num(f.water_cop)
    X["floor_area"] = num(f.floor_area).replace(0, np.nan)
    X["immersion_kwh"] = num(f.immersion_kwh)

    # --- data quality (low quality could look like performance) ---
    X["quality_elec"] = num(f.quality_elec)
    X["quality_heat"] = num(f.quality_heat)

    # --- system / install descriptors ---
    X["system_volume"] = num(f.measured_system_volume).replace(0, np.nan)
    X["emitter_spec"] = num(f.measured_emitter_spec).replace(0, np.nan)
    for make in ["Vaillant", "Mitsubishi", "Samsung", "Daikin", "Viessmann"]:
        X[f"make_{make}"] = (f.hp_manufacturer == make).astype(float)
    return X


def residual_correlations(X, resid):
    rows = []
    for c in X.columns:
        x = X[c]
        ok = x.notna() & resid.notna()
        if ok.sum() < 30 or x[ok].std() == 0:
            continue
        pr, pp = stats.pearsonr(x[ok], resid[ok])
        sr, sp = stats.spearmanr(x[ok], resid[ok])
        rows.append({"feature": c, "n": int(ok.sum()), "pearson_r": pr, "p": pp, "spearman_r": sr})
    t = pd.DataFrame(rows).set_index("feature")
    return t.reindex(t.spearman_r.abs().sort_values(ascending=False).index)


def cv_eval(X, y, model, folds=5):
    """K-fold CV. Returns out-of-fold predictions aligned to X's index."""
    from sklearn.model_selection import KFold
    from sklearn.base import clone
    oof = pd.Series(np.nan, index=X.index)
    for tr, te in KFold(folds, shuffle=True, random_state=RNG).split(X):
        m = clone(model)
        m.fit(X.iloc[tr], y.iloc[tr])
        oof.iloc[te] = m.predict(X.iloc[te])
    resid = y - oof
    return {
        "cv_r2": 1 - (resid**2).sum() / ((y - y.mean())**2).sum(),
        "cv_rmse": float(np.sqrt((resid**2).mean())),
        "cv_pi90": float(np.quantile(resid.abs(), 0.90)),   # empirical: 90% of homes within +/- this
        "oof": oof,
    }


def main():
    df = load(find_csv())
    f = standard_filter(df, verbose=False)
    f = f[f.weighted_flowT_minus_outsideT.notna() & f.combined_cop.notna()].copy()
    y = num(f.combined_cop)
    print(f"n = {len(f)}")

    # ---------- baseline ----------
    base = linfit(f.weighted_flowT_minus_outsideT, y)
    resid = y - (base["slope"] * num(f.weighted_flowT_minus_outsideT) + base["intercept"])
    print(f"\nBASELINE  COP = {base['slope']:.4f}*dT + {base['intercept']:.4f}   "
          f"R2={base['r2']:.3f}  RMSE={base['rmse']:.3f}")
    print(f"empirical 90% |resid| = {np.quantile(resid.abs(), .90):.3f}")

    # ---------- exact standby decomposition ----------
    print("\n--- STANDBY DRAG (exact decomposition) ---")
    standby_kwh = (num(f.combined_elec_kwh) - num(f.running_elec_kwh)).clip(lower=0)
    drag = num(f.running_cop) / (1 + standby_kwh / num(f.running_elec_kwh)) - num(f.running_cop)
    cop_no_standby = num(f.combined_heat_kwh) / num(f.running_elec_kwh)
    print(f"standby elec kWh/yr: median={standby_kwh.median():.0f}, p90={standby_kwh.quantile(.9):.0f}, "
          f"max={standby_kwh.max():.0f}")
    print(f"COP penalty from standby: median={-drag.median():.3f}, p90={-drag.quantile(.1):.3f}, "
          f"max={-drag.min():.3f}")
    fit_ns = linfit(f.weighted_flowT_minus_outsideT, cop_no_standby)
    print(f"refit with standby removed (heat/running_elec): R2={fit_ns['r2']:.3f}  RMSE={fit_ns['rmse']:.3f} "
          f"(baseline {base['r2']:.3f}/{base['rmse']:.3f})")

    # ---------- univariate residual correlations ----------
    X = build_features(f)
    print("\n--- RESIDUAL CORRELATIONS (sorted by |spearman|) ---")
    tbl = residual_correlations(X, resid)
    with pd.option_context("display.float_format", "{:.3f}".format):
        print(tbl.to_string())

    # ---------- multivariate models, honest CV comparison ----------
    from sklearn.linear_model import LinearRegression
    from sklearn.ensemble import HistGradientBoostingRegressor
    from sklearn.pipeline import make_pipeline
    from sklearn.impute import SimpleImputer

    print("\n--- CROSS-VALIDATED MODELS (5-fold, metric on held-out homes) ---")
    b = cv_eval(X[["dT"]], y, LinearRegression())
    print(f"{'dT only (baseline)':52s} cv_R2={b['cv_r2']:.3f}  cv_RMSE={b['cv_rmse']:.3f}  "
          f"90%|err|={b['cv_pi90']:.2f}")

    # LEAKAGE RULE: never give a model both a heat-side and an elec-side power/energy
    # quantity (their ratio IS the COP). load_factor (heat-based) is the modulation
    # proxy; elec_load_factor and water_cop are excluded from models. standby terms
    # are elec/elec ratios (no heat term) so they are safe.
    sink = ["dT", "flowT", "flow_return_dT", "load_factor", "hp_output_kw",
            "eflh", "runtime_frac", "standby_power_w", "standby_elec_frac",
            "starts_per_run_hour", "starts_per_day", "dhw_frac", "annual_heat_kwh",
            "immersion_kwh", "quality_heat"] + [c for c in X if c.startswith("make_")]
    sets = {
        "dT + flow_return_dT": ["dT", "flow_return_dT"],
        "dT + load_factor": ["dT", "load_factor"],
        "dT + annual_heat_kwh": ["dT", "annual_heat_kwh"],
        "dT + standby_elec_frac": ["dT", "standby_elec_frac"],
        "dT + runtime/standby": ["dT", "runtime_frac", "standby_power_w", "standby_elec_frac"],
        "dT + cycling": ["dT", "starts_per_run_hour", "starts_per_day"],
        "dT + dhw_frac": ["dT", "dhw_frac"],
        "dT + makes": ["dT"] + [c for c in X if c.startswith("make_")],
        "dT + heat + standby + loadfactor": ["dT", "annual_heat_kwh", "standby_elec_frac",
                                             "load_factor"],
        "all safe features (linear)": sink,
        "all safe features (GBM)": sink,
    }
    for name, cols in sets.items():
        Xi = X[cols]
        if "GBM" in name:
            model = HistGradientBoostingRegressor(random_state=RNG, max_iter=300,
                                                  max_depth=3, learning_rate=0.06,
                                                  min_samples_leaf=15)
        else:
            model = make_pipeline(SimpleImputer(strategy="median"), LinearRegression())
        r = cv_eval(Xi, y, model)
        print(f"{name:52s} cv_R2={r['cv_r2']:.3f}  cv_RMSE={r['cv_rmse']:.3f}  "
              f"90%|err|={r['cv_pi90']:.2f}")

    # ---------- same, but predicting standby-corrected COP ----------
    print("\n--- TARGET = combined_heat/running_elec (standby removed exactly) ---")
    for name, cols in {"dT only": ["dT"],
                       "dT + heat + loadfactor": ["dT", "annual_heat_kwh", "load_factor"],
                       "all safe features (GBM)": sink}.items():
        model = (HistGradientBoostingRegressor(random_state=RNG, max_iter=300, max_depth=3,
                                               learning_rate=0.06, min_samples_leaf=15)
                 if "GBM" in name else
                 make_pipeline(SimpleImputer(strategy="median"), LinearRegression()))
        r = cv_eval(X[cols], cop_no_standby, model)
        print(f"{name:52s} cv_R2={r['cv_r2']:.3f}  cv_RMSE={r['cv_rmse']:.3f}  "
              f"90%|err|={r['cv_pi90']:.2f}")

    # ---------- interpretable OLS for the best small model ----------
    import statsmodels.api as sm
    cols = ["dT", "annual_heat_kwh", "standby_elec_frac", "load_factor"]
    Xo = X[cols].dropna()
    yo = y.loc[Xo.index]
    Xs = (Xo - Xo.mean()) / Xo.std()
    print("\n--- OLS, standardized coefficients (best small model) ---")
    res = sm.OLS(yo, sm.add_constant(Xs)).fit()
    print(res.summary2().tables[1].to_string(float_format="%.4f"))
    res_raw = sm.OLS(yo, sm.add_constant(Xo)).fit()
    terms = " + ".join(f"{c:.6g}*{n}" for n, c in res_raw.params.items() if n != "const")
    print(f"\nraw equation: COP = {res_raw.params['const']:.4f} + {terms}")

    # ---------- GBM permutation importance on full data ----------
    from sklearn.inspection import permutation_importance
    Xi = X[sink]
    gbm = HistGradientBoostingRegressor(random_state=RNG, max_iter=300, max_depth=3,
                                        learning_rate=0.06, min_samples_leaf=15).fit(Xi, y)
    imp = permutation_importance(gbm, Xi, y, n_repeats=20, random_state=RNG)
    order = np.argsort(-imp.importances_mean)
    print("\n--- GBM permutation importance (full fit, R2 drop when shuffled) ---")
    for i in order[:12]:
        print(f"  {Xi.columns[i]:24s} {imp.importances_mean[i]:.4f} +/- {imp.importances_std[i]:.4f}")


if __name__ == "__main__":
    main()
