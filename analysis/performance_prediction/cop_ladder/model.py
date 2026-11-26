#!/usr/bin/env python3
"""Phase 1: score an approximation of the colleague's ablation ladder on our HPM
fleet, against three system-level targets, next to our weighted-dT baselines.

Window model: HistGradientBoostingRegressor (stand-in for LightGBM), trained on
clean space-heating windows with equal windows per system, 5-fold GroupKFold
by system. All system-level scores use the same folds.

Usage (from analysis/performance_prediction/):
    .venv/bin/python cop_ladder/model.py --export heatpumpmonitor_all_fields_last365_2026-11-16.csv \
        --windows cop_ladder/windows_30s

Writes results.csv (one row per model) and systems.csv (per-system predictions) into the windows folder.
"""
import argparse
import glob
import json
import os
import sys

import numpy as np
import pandas as pd
from sklearn.ensemble import HistGradientBoostingRegressor
from sklearn.model_selection import GroupKFold

PER_SYS = 2000
SEED = 0

S1 = ["lift", "flow_t", "outside_t", "cop_carnot_formula", "cop_carnot_offset"]
S2 = S1 + ["return_t", "dT_water", "dflow_dt", "implied_flow_lpm", "dT_out_in_win", "cop_lorentz_formula"]
S3 = S2 + ["size_kw", "load_ratio", "est_evap_load_ratio", "pipe_loss_proxy", "flow_per_kw"]
S4 = S3 + ["refrigerant", "T_crit_margin"]
BRAND = ["manufacturer_id", "model"]
CYC = ["mins_into_run", "starts_24h", "mins_since_defrost", "defrost_6h"]
HEAT_DERIVED = ["implied_flow_lpm", "load_ratio", "est_evap_load_ratio", "pipe_loss_proxy", "flow_per_kw"]
STAGES = {
    "1 temps": S1,
    "2 + hydronics & dT": S2,
    "3 + HX util & pipe proxies": S3,
    "4 + refrigerant & T_crit": S4,
    "5b + brand & model": S4 + BRAND,
    "6 + defrost & cycling (no brand)": S4 + CYC,
    "6b all + brand": S4 + CYC + BRAND,
    "6 without heat-derived features": [f for f in S4 + CYC if f not in HEAT_DERIVED],
    "temps + cycling only": S1 + CYC,
}
CATS = {"refrigerant", "manufacturer_id", "model"}


def load_windows(folder):
    parts = [pd.read_pickle(p) for p in sorted(glob.glob(os.path.join(folder, "*.pkl")))]
    w = pd.concat(parts).reset_index()
    # space-heating SPF over all running space-heating windows (defrost and short runs included)
    sp = w[w.space].groupby("system_id")[["heat_kwh", "elec_kwh"]].sum()
    space_spf = sp.heat_kwh / sp.elec_kwh
    w = w[w.clean & w.cop.between(0.5, 12)].copy()
    for c in CATS:
        w[c] = w[c].astype(str).astype("category").cat.codes
    return w, space_spf


def r2(y, p):
    y, p = np.asarray(y, float), np.asarray(p, float)
    return 1 - ((y - p) ** 2).sum() / ((y - y.mean()) ** 2).sum()


def rmse(y, p):
    return float(np.sqrt(np.mean((np.asarray(y, float) - np.asarray(p, float)) ** 2)))


def system_spf(w, pred):
    """Heat-weighted SPF over each system's windows: sum(heat) / sum(heat / cop)."""
    d = pd.DataFrame({"s": w.system_id.to_numpy(), "h": w.heat_kwh.to_numpy(), "e": w.elec_kwh.to_numpy(),
                      "pe": w.heat_kwh.to_numpy() / pred})
    g = d.groupby("s").sum()
    return g.h / g.e, g.h / g.pe


def cv_linear(X, y, folds_of):
    """Out-of-fold linear regression at system level using the shared system folds."""
    X = np.column_stack([np.ones(len(y)), X])
    out = np.full(len(y), np.nan)
    for k in np.unique(folds_of):
        tr, te = folds_of != k, folds_of == k
        b, *_ = np.linalg.lstsq(X[tr], y[tr], rcond=None)
        out[te] = X[te] @ b
    return out


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--export", required=True)
    ap.add_argument("--windows", required=True)
    args = ap.parse_args()
    w, space_spf = load_windows(args.windows)
    meta = pd.read_csv(args.export).set_index("id")
    systems = np.array(sorted(w.system_id.unique()))
    # need enough clean windows for a stable clean SPF
    counts = w.groupby("system_id").size()
    systems = systems[counts.loc[systems].to_numpy() >= 500]
    w = w[w.system_id.isin(systems)].reset_index(drop=True)
    rng = np.random.default_rng(SEED)
    perm = rng.permutation(systems)
    fold_map = {s: i % 5 for i, s in enumerate(perm)}
    wf = w.system_id.map(fold_map).to_numpy()
    print(f"systems {len(systems)}, clean space windows {len(w):,}", flush=True)

    # equal-per-system training sample
    samp = w.groupby("system_id", group_keys=False).apply(
        lambda g: g.sample(min(len(g), PER_SYS), random_state=SEED)).index.to_numpy()
    is_samp = np.zeros(len(w), bool)
    is_samp[samp] = True

    preds = {}
    # Stage 0: fitted-efficiency Carnot
    for name, col in [("0a Carnot (fitted eta)", "cop_carnot_formula"), ("0b offset Carnot +2/-6 (fitted eta)", "cop_carnot_offset")]:
        p = np.full(len(w), np.nan)
        for k in range(5):
            tr = (wf != k) & is_samp
            x, y = w[col].to_numpy()[tr], w.cop.to_numpy()[tr]
            eta = (x @ y) / (x @ x)
            p[wf == k] = eta * w[col].to_numpy()[wf == k]
        preds[name] = p

    for name, feats in STAGES.items():
        p = np.full(len(w), np.nan)
        cat_mask = [f in CATS for f in feats]
        for k in range(5):
            tr = (wf != k) & is_samp
            m = HistGradientBoostingRegressor(max_iter=400, learning_rate=0.08, max_leaf_nodes=63,
                                              min_samples_leaf=200, l2_regularization=1.0,
                                              categorical_features=cat_mask, random_state=SEED)
            m.fit(w.loc[tr, feats], w.cop.to_numpy()[tr])
            p[wf == k] = m.predict(w.loc[wf == k, feats])
        preds[name] = p
        print("done", name, flush=True)

    # system-level table
    sys_meta = meta.loc[systems]
    h4 = pd.to_numeric(sys_meta.combined_cop, errors="coerce").to_numpy()
    sh = space_spf.reindex(systems).to_numpy()
    dT = sys_meta.weighted_flowT_minus_outsideT.to_numpy(float)
    yrs = sys_meta.combined_data_length.to_numpy(float) / (365 * 86400)
    lnheat = np.log(pd.to_numeric(sys_meta.combined_heat_kwh, errors="coerce").to_numpy() / yrs / 1000)
    sfold = np.array([fold_map[s] for s in systems])
    clean_meas, _ = system_spf(w, np.ones(len(w)))
    clean_meas = clean_meas.loc[systems].to_numpy()

    rows = []
    base_ok = np.isfinite(dT) & np.isfinite(lnheat) & np.isfinite(clean_meas)

    def add(name, win_r2, clean_pred, extra_X=None):
        r = {"model": name, "window R2": win_r2}
        for tname, y in [("clean SPF", clean_meas), ("space SPF", sh), ("H4 SPF", h4)]:
            ok = base_ok & np.isfinite(y)
            r[f"{tname} n"] = int(ok.sum())
            if clean_pred is None:
                X = extra_X
            else:
                X = clean_pred[:, None]
            yp = clean_pred if (tname == "clean SPF" and clean_pred is not None) else cv_linear(X[ok], y[ok], sfold[ok])
            yt = y[ok]
            if tname == "clean SPF" and clean_pred is not None:
                yp = yp[ok]
            r[f"{tname} R2"] = r2(yt, yp)
            r[f"{tname} RMSE"] = rmse(yt, yp)
        rows.append(r)

    add("weighted dT (our baseline)", np.nan, None, dT[:, None])
    add("weighted dT + ln(annual heat)", np.nan, None, np.column_stack([dT, lnheat]))
    sys_preds = {}
    for name, p in preds.items():
        _, ps = system_spf(w, p)
        ps = ps.loc[systems].to_numpy()
        sys_preds[name] = ps
        add(name, r2(w.cop, p), ps)

    res = pd.DataFrame(rows)
    pd.set_option("display.width", 250)
    pd.set_option("display.max_columns", 20)
    print(res.round(3).to_string(index=False))
    res.to_csv(os.path.join(args.windows, "results.csv"), index=False)
    out = pd.DataFrame({"system_id": systems, "fold": sfold, "clean_spf": clean_meas, "space_spf": sh, "h4_spf": h4,
                        "weighted_dT": dT, "ln_annual_heat_MWh": lnheat,
                        **{f"pred_{k}": v for k, v in sys_preds.items()}})
    out.to_csv(os.path.join(args.windows, "systems.csv"), index=False)
    print("SD of targets:", {k: round(float(np.nanstd(v[base_ok])), 3) for k, v in [("clean", clean_meas), ("space", sh), ("h4", h4)]})


if __name__ == "__main__":
    main()
