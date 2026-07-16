#!/usr/bin/env python3
"""Stage 3: modulation-distribution features from per-system power histograms.

Tests the hypothesis that WHERE in its modulation range a heat pump delivers
its energy (not just at what temperatures) explains part of the residual
scatter around COP ~ weighted_flowT_minus_outsideT.

Elec histogram: kWh of electricity consumed, binned by instantaneous input power.
Heat histogram: kWh of heat delivered, binned by instantaneous output power.
"""

import json
import sys
from pathlib import Path

import numpy as np
import pandas as pd
from scipy import stats

sys.path.insert(0, "/var/www/hpmon_ai2/analysis")
from hpmon_analysis import load, standard_filter, find_csv, linfit
from residual_analysis import build_features, num, cv_eval

CACHE = Path("/var/www/hpmon_ai2/histograms")
STANDBY_W = 120   # below this: standby + circulation pumps, not compressor


def read_hist(sid, kind):
    p = CACHE / f"{sid}_{kind}.json"
    if not p.exists():
        return None
    d = json.loads(p.read_text())
    if "error" in d or not d.get("data"):
        return None
    centers = d["min"] + d["div"] * (np.arange(len(d["data"])) + 0.5)
    return centers, np.asarray(d["data"], float)


def wquantile(x, w, q):
    order = np.argsort(x)
    cw = np.cumsum(w[order])
    return np.interp(np.asarray(q) * cw[-1], cw, x[order])


def hist_features(sid, rated_heat_w):
    out = {}
    h = read_hist(sid, "elec")
    if h is not None:
        p, kwh = h
        run = p >= STANDBY_W                     # compressor-on portion
        pk, kk = p[run], kwh[run]
        if kk.sum() > 100:                       # need meaningful energy
            p50, p90, p99 = wquantile(pk, kk, [0.50, 0.90, 0.99])
            pmax = p99
            tot = kk.sum()
            out["elec_pmax_w"] = pmax
            out["elec_p50_frac"] = p50 / pmax    # where the median kWh sits in range
            out["mod_frac_above_80"] = kk[pk > 0.8 * pmax].sum() / tot
            out["mod_frac_above_60"] = kk[pk > 0.6 * pmax].sum() / tot
            out["mod_frac_below_40"] = kk[pk < 0.4 * pmax].sum() / tot
            out["mod_mean_frac"] = np.average(pk, weights=kk) / pmax
            out["mod_p90_p50"] = p90 / p50
            out["hist_standby_frac"] = kwh[~run].sum() / kwh.sum()
    h = read_hist(sid, "heat")
    if h is not None and rated_heat_w and rated_heat_w > 0:
        p, kwh = h
        run = p >= 500
        pk, kk = p[run], kwh[run]
        if kk.sum() > 500:
            tot = kk.sum()
            lf = pk / rated_heat_w
            out["heat_p99_over_rated"] = wquantile(pk, kk, [0.99])[0] / rated_heat_w
            out["heat_frac_above_rated"] = kk[lf > 1.0].sum() / tot
            out["heat_frac_below_40pct"] = kk[lf < 0.4].sum() / tot
            out["heat_mean_load_factor"] = np.average(lf, weights=kk)
    return out


def main():
    df = load(find_csv())
    f = standard_filter(df, verbose=False)
    f = f[f.weighted_flowT_minus_outsideT.notna() & f.combined_cop.notna()].copy()
    y = num(f.combined_cop)
    X = build_features(f)
    base = linfit(f.weighted_flowT_minus_outsideT, y)
    resid = y - (base["slope"] * X.dT + base["intercept"])

    rows = {}
    for idx, r in f.iterrows():
        rows[idx] = hist_features(int(r.id), num(pd.Series([r.hp_output])).iloc[0] * 1000)
    M = pd.DataFrame.from_dict(rows, orient="index").reindex(f.index)
    print(f"systems with elec-histogram features: {M.elec_pmax_w.notna().sum()} / {len(f)}")
    print(f"systems with heat-histogram features: {M.get('heat_mean_load_factor', pd.Series(dtype=float)).notna().sum()} / {len(f)}")

    print("\n--- modulation features vs baseline residual ---")
    out = []
    for c in M.columns:
        ok = M[c].notna() & resid.notna()
        if ok.sum() < 30:
            continue
        pr, pp = stats.pearsonr(M.loc[ok, c], resid[ok])
        sr, _ = stats.spearmanr(M.loc[ok, c], resid[ok])
        out.append({"feature": c, "n": int(ok.sum()), "pearson_r": pr, "p": pp, "spearman_r": sr})
    t = pd.DataFrame(out).set_index("feature")
    t = t.reindex(t.spearman_r.abs().sort_values(ascending=False).index)
    print(t.to_string(float_format="%.3f"))

    # sanity check: histogram standby share vs CSV-derived standby fraction
    ok = (M.hist_standby_frac.notna() & X.standby_elec_frac.notna()).to_numpy()
    r_chk = stats.pearsonr(M.hist_standby_frac.to_numpy()[ok],
                           X.standby_elec_frac.to_numpy()[ok])[0]
    print(f"\nsanity: hist standby frac vs CSV standby frac r = {r_chk:.2f}")

    # ---------- do modulation features add predictive power? ----------
    from sklearn.linear_model import LinearRegression
    from sklearn.pipeline import make_pipeline
    from sklearn.impute import SimpleImputer

    XA = X.join(M)
    top_mod = [c for c in ["mod_frac_above_60", "mod_frac_above_80", "mod_mean_frac",
                           "elec_p50_frac", "mod_frac_below_40", "heat_mean_load_factor",
                           "heat_frac_below_40pct"] if c in XA]
    sets = {
        "dT only": ["dT"],
        "dT + heat_kwh (prev best pair)": ["dT", "annual_heat_kwh"],
        "dT + modulation": ["dT"] + top_mod,
        "dT + heat_kwh + modulation": ["dT", "annual_heat_kwh"] + top_mod,
        "dT + heat + standby + modulation": ["dT", "annual_heat_kwh", "standby_elec_frac"] + top_mod,
    }
    print("\n--- CV comparison (5-fold) ---")
    for name, cols in sets.items():
        r = cv_eval(XA[cols], y, make_pipeline(SimpleImputer(strategy="median"), LinearRegression()))
        print(f"{name:40s} cv_R2={r['cv_r2']:.3f}  cv_RMSE={r['cv_rmse']:.3f}  90%|err|={r['cv_pi90']:.2f}")

    M.insert(0, "id", f.id.values)
    M["resid"] = resid
    M.to_csv("/var/www/hpmon_ai2/modulation_features.csv", index=False)
    print("\nsaved modulation_features.csv")


if __name__ == "__main__":
    main()
