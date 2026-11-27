#!/usr/bin/env python3
"""Test the low-load finding: does running at low load explain what the weighted-dT line misses?

gap.py found that the share of clean heat delivered below 35% of rated output correlates with the
residual of H4 SPF from the dT line (Spearman about -0.36). This checks that more carefully:

1. Heat coupling. Load ratio is metered heat / rated output, and COP has metered heat on top, so a
   window that makes less heat for its electricity is both low-COP and low-load by construction, and a
   heat meter that reads low lowers both. We add a load estimate that doesn't use the window's heat:
   electrical input x a fleet Carnot efficiency x offset Carnot COP / rated output ("est"), with the
   same 0.35 threshold, and report how far a +-5% heat-meter error moves the heat-based share.
2. Confounding. The link is tested with dT, ln(annual heat), ln(rated output) and mean outside
   temperature as controls, with bootstrap intervals and repeated cross-validation.
3. Mechanism. Within each system, windows are grouped into cells of similar outside and flow
   temperature (2 K each). Comparing low-load windows with others in the same cell gives a COP
   penalty at matched temperatures. Also: does the link act through clean SPF or the losses on top?
4. Stability. Metrics from alternating weeks, and from each half of the year, predict the other
   half's residual (window-based space-heating SPF against window-based dT), so the predictor and
   the target share no windows.

Usage (from analysis/performance_prediction/):
    .venv/bin/python cop_ladder/lowload.py --export heatpumpmonitor_all_fields_last365_<date>.csv \
        --windows cop_ladder/windows_30s --exclude-flagged

Writes lowload_systems.csv (or lowload_systems_unflagged.csv) into the windows folder.
"""
import argparse
import glob
import os

import numpy as np
import pandas as pd
from scipy import stats

from gap import flagged_ids

LOW = 0.35            # heat load ratio threshold (heat / rated output)
CELL_K = 2.0          # temperature cell size for matched comparisons
MIN_CLEAN = 500
N_BOOT = 2000
N_CV = 50
RNG = np.random.default_rng(0)
LR_BINS = [0, .15, .25, .35, .5, .7, 1.0, 9]


def cell_dev(c):
    """ln COP minus its mean within the window's (outside, flow) temperature cell."""
    cell = (np.floor(c.outside_t / CELL_K).astype(int).astype(str) + "_"
            + np.floor(c.flow_t / CELL_K).astype(int).astype(str))
    lc = np.log(c.cop)
    n = lc.groupby(cell).transform("size")
    return (lc - lc.groupby(cell).transform("mean")).where(n >= 4), cell


def matched_penalty(c, low, dev, cell):
    """Low-load vs other windows within the same temperature cells: mean ln COP difference,
    weighted by min(n_low, n_other) per cell. -0.05 means about 5% lower COP at low load."""
    d = pd.DataFrame({"cell": cell, "low": low, "dev": dev}).dropna()
    g = d.groupby(["cell", "low"]).dev.agg(["mean", "size"]).unstack("low")
    if True not in g.columns.get_level_values(1) or False not in g.columns.get_level_values(1):
        return np.nan
    g = g.dropna()
    wt = np.minimum(g[("size", True)], g[("size", False)])
    if wt.sum() < 30:
        return np.nan
    return float(((g[("mean", True)] - g[("mean", False)]) * wt).sum() / wt.sum())


def shares(c, cap, heat_scale=1.0):
    lr = c.load_ratio * heat_scale
    out = {}
    for th in (0.25, 0.35, 0.45):
        out[f"low_heat_share_{int(th * 100)}"] = (c.heat_kwh[lr < th].sum() / c.heat_kwh.sum())
    return out


def est_load(c, cap, hours, eta):
    """Load ratio estimated without the window's metered heat: electricity x eta x offset Carnot COP."""
    return c.elec_kwh / hours * eta * c.cop_carnot_offset / cap


def run_metrics(c, cap, hours, eta):
    """Heat-based and heat-free low-load metrics for a set of clean windows."""
    out = {}
    if len(c) < 50 or not np.isfinite(cap):
        return out
    est = est_load(c, cap, hours, eta)
    lo = c.load_ratio < LOW
    lo_e = est < LOW
    out.update(shares(c, cap))
    out["low_time_share_35"] = lo.mean()
    for th in (0.25, 0.35, 0.45):
        out[f"low_est_share_{int(th * 100)}"] = (est < th).mean()
    dev, cell = cell_dev(c)
    out["penalty_heat"] = matched_penalty(c, lo, dev, cell)
    out["penalty_est"] = matched_penalty(c, lo_e, dev, cell)
    return out


def space_resid_inputs(w):
    """Window-based space-heating SPF and heat-weighted flow-outside dT."""
    s = w[w.space]
    if s.heat_kwh.sum() <= 0:
        return np.nan, np.nan
    spf = s.heat_kwh.sum() / s.elec_kwh.sum()
    hw = s.heat_kwh.clip(lower=0)
    dT = ((s.flow_t - s.outside_t) * hw).sum() / hw.sum()
    return spf, dT


def per_system(w, t_mid, eta):
    step = 900 / w.n.max()
    hours = w.n * step / 3600
    cap = float(w.size_kw.iloc[0])
    cm = w.clean & w.cop.between(0.5, 12)
    c = w[cm]
    out = {"clean_windows": len(c), "size_kw": cap}
    if len(c) == 0:
        return out, {}
    out["clean_spf"] = c.heat_kwh.sum() / c.elec_kwh.sum()
    hw = c.heat_kwh.clip(lower=0)
    out["clean_outside_mean"] = (c.outside_t * hw).sum() / hw.sum()
    out["heat_weighted_load"] = (c.load_ratio * hw).sum() / hw.sum()
    out.update(run_metrics(c, cap, hours[cm], eta))
    if np.isfinite(cap):
        for k, v in shares(c, cap, 0.95).items():
            out[k + "_meter-5"] = v
        for k, v in shares(c, cap, 1.05).items():
            out[k + "_meter+5"] = v

    # profile: mean within-cell ln COP deviation by load bin (heat-based and heat-free)
    prof = {}
    if np.isfinite(cap):
        dev, _ = cell_dev(c)
        for name, x in (("heat", c.load_ratio), ("est", est_load(c, cap, hours[cm], eta))):
            b = pd.cut(x, LR_BINS, right=False)
            g = dev.groupby(b, observed=False).agg(["mean", "count"])
            for iv, r in g.iterrows():
                if r["count"] >= 20:
                    prof[(name, str(iv))] = r["mean"]

    # split halves: alternating weeks and the two halves of the year
    t = w.index.to_numpy()
    for name, a_mask in (("week", (t // (7 * 86400)) % 2 == 0), ("season", t < t_mid)):
        for half, m in (("A", a_mask), ("B", ~a_mask)):
            wh = w[m]
            ch = wh[wh.clean & wh.cop.between(0.5, 12)]
            mt = run_metrics(ch, cap, (wh.n * step / 3600)[wh.clean & wh.cop.between(0.5, 12)], eta)
            for k in ("low_heat_share_35", "low_time_share_35", "low_est_share_35", "penalty_heat", "penalty_est"):
                out[f"{name}{half}_{k}"] = mt.get(k, np.nan)
            spf, dT = space_resid_inputs(wh)
            out[f"{name}{half}_space_spf"] = spf
            out[f"{name}{half}_dT"] = dT
    return out, prof


def ols(y, X):
    X = np.column_stack([np.ones(len(y)), X])
    b, *_ = np.linalg.lstsq(X, y, rcond=None)
    return b, X @ b


def zs(x):
    """Rank-based normal scores, so a few extreme systems don't dominate the fits."""
    r = stats.rankdata(x)
    return stats.norm.ppf((r - 0.5) / len(r))


def coef_ci(y, X, j):
    """Coefficient j of X (0-based, excluding intercept) with a bootstrap 95% interval over systems."""
    b, _ = ols(y, X)
    n = len(y)
    bs = np.empty(N_BOOT)
    for i in range(N_BOOT):
        k = RNG.integers(0, n, n)
        bs[i] = ols(y[k], X[k])[0][j + 1]
    return b[j + 1], np.percentile(bs, 2.5), np.percentile(bs, 97.5)


def cv_gain(y, Xb, x):
    """Repeated 5-fold CV RMSE of y ~ Xb versus y ~ Xb + x. Returns mean RMSEs and share of repeats improved."""
    n = len(y)
    rb, rx = [], []
    for _ in range(N_CV):
        f = RNG.permutation(n) % 5
        pb, px = np.empty(n), np.empty(n)
        for k in range(5):
            tr, te = f != k, f == k
            b, _ = ols(y[tr], Xb[tr])
            pb[te] = np.column_stack([np.ones(te.sum()), Xb[te]]) @ b
            Xx = np.column_stack([Xb, x])
            b, _ = ols(y[tr], Xx[tr])
            px[te] = np.column_stack([np.ones(te.sum()), Xx[te]]) @ b
        rb.append(np.sqrt(np.mean((y - pb) ** 2)))
        rx.append(np.sqrt(np.mean((y - px) ** 2)))
    rb, rx = np.array(rb), np.array(rx)
    return rb.mean(), rx.mean(), (rx < rb).mean()


def resid(y, X):
    return y - ols(y, X)[1]


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--export", required=True)
    ap.add_argument("--windows", required=True)
    ap.add_argument("--exclude-flagged", action="store_true")
    args = ap.parse_args()

    meta = pd.read_csv(args.export).set_index("id")
    files = sorted(glob.glob(os.path.join(args.windows, "*.pkl")))
    # first pass: fleet midpoint in time (for the season split) and the fleet Carnot efficiency
    t0, t1, etas = [], [], []
    for p in files:
        w = pd.read_pickle(p)
        if len(w):
            t0.append(w.index.min())
            t1.append(w.index.max())
            c = w[w.clean & w.cop.between(0.5, 12)]
            if len(c) >= MIN_CLEAN:
                etas.append(c.heat_kwh.sum() / (c.elec_kwh * c.cop_carnot_offset).sum())
    t_mid = (np.median(t0) + np.median(t1)) / 2
    eta = float(np.median(etas))

    rows, profs = [], []
    for p in files:
        w = pd.read_pickle(p)
        if w.empty:
            continue
        sid = int(w.system_id.iloc[0])
        r, prof = per_system(w, t_mid, eta)
        r["system_id"] = sid
        rows.append(r)
        profs.append(pd.Series(prof, name=sid, dtype=float))
    s = pd.DataFrame(rows).set_index("system_id")
    s = s[(s.clean_windows >= MIN_CLEAN) & np.isfinite(s.size_kw) & (s.size_kw > 0)]
    if args.exclude_flagged:
        fl = flagged_ids(meta) & set(s.index)
        print(f"leaving out {len(fl)} flagged systems: {sorted(fl)}")
        s = s.drop(index=sorted(fl))
    m = meta.loc[s.index]
    num = lambda col: pd.to_numeric(m[col], errors="coerce")
    s["h4_spf"] = num("combined_cop")
    s["weighted_dT"] = num("weighted_flowT_minus_outsideT")
    s["ln_heat"] = np.log(num("combined_heat_kwh") / 1000)
    s["ln_size"] = np.log(s.size_kw)
    s["ln_clean_spf"] = np.log(s.clean_spf)
    s["loss_pct"] = 100 * (1 - s.h4_spf / s.clean_spf)
    s = s.dropna(subset=["h4_spf", "weighted_dT", "ln_heat", "clean_spf", "low_heat_share_35"])
    pd.set_option("display.width", 200)
    print(f"systems: {len(s)}  (time split at {pd.to_datetime(t_mid, unit='s'):%Y-%m-%d}, "
          f"fleet efficiency vs offset Carnot {eta:.3f})\n")

    # ---- 0. descriptives
    d = s[["low_heat_share_35", "low_time_share_35", "low_est_share_35", "heat_weighted_load",
           "penalty_heat", "penalty_est"]].describe(percentiles=[.1, .5, .9]).T
    print("Metrics across systems:")
    print(d[["count", "mean", "10%", "50%", "90%"]].round(3).to_string(), "\n")

    # ---- 1. heat-meter coupling
    base = s.low_heat_share_35
    lo5, hi5 = s["low_heat_share_35_meter-5"], s["low_heat_share_35_meter+5"]
    print("Heat-meter coupling: change in the low-load heat share if the meter read 5% low / 5% high")
    print(f"  median change {np.median(lo5 - base):+.3f} / {np.median(hi5 - base):+.3f}, "
          f"against a fleet SD of {base.std():.3f} and median {base.median():.3f}")
    print(f"  Spearman, low-load time share heat-based vs heat-free: "
          f"{stats.spearmanr(s.low_time_share_35, s.low_est_share_35)[0]:.2f}\n")

    # ---- 2. system-level tests
    y = s.h4_spf.to_numpy()
    C1 = s[["weighted_dT", "ln_heat"]].to_numpy()
    C2 = s[["weighted_dT", "ln_heat", "ln_size", "clean_outside_mean"]].to_numpy()
    r_dT = resid(y, s[["weighted_dT"]].to_numpy())
    r_C1 = resid(y, C1)
    metrics = ["low_heat_share_25", "low_heat_share_35", "low_heat_share_45", "low_time_share_35",
               "low_est_share_25", "low_est_share_35", "low_est_share_45", "heat_weighted_load",
               "penalty_heat", "penalty_est"]
    print("H4 SPF: each metric added to the baseline. Metrics are rank-transformed to normal scores;")
    print("coefficients are SPF per SD of that score, with bootstrap 95% intervals.")
    print(f"  {'metric':20s} {'n':>4s} {'rho dT res':>10s} {'rho dT+heat res':>15s} "
          f"{'coef | dT, ln heat':>24s} {'coef | + ln size, outside':>26s} {'CV RMSE base->with':>19s} {'% better':>8s}")
    for x in metrics:
        ok = s[x].notna().to_numpy()
        if ok.sum() < 30:
            continue
        xz = zs(s[x].to_numpy()[ok])
        rho1 = stats.spearmanr(s[x][ok], r_dT[ok])[0]
        rho2 = stats.spearmanr(s[x][ok], r_C1[ok])[0]
        b1 = coef_ci(y[ok], np.column_stack([C1[ok], xz]), 2)
        b2 = coef_ci(y[ok], np.column_stack([C2[ok], xz]), 4)
        rb, rx, frac = cv_gain(y[ok], C1[ok], xz)
        print(f"  {x:20s} {ok.sum():4d} {rho1:10.2f} {rho2:15.2f} "
              f"{b1[0]:+7.3f} [{b1[1]:+.3f}, {b1[2]:+.3f}]  {b2[0]:+7.3f} [{b2[1]:+.3f}, {b2[2]:+.3f}]"
              f"  {rb:.3f} -> {rx:.3f} {100 * frac:7.0f}%")
    print()

    # ---- 3. where it acts: clean SPF or the losses on top
    print("Where it acts (coefficient per SD, controlling for dT and ln heat):")
    print(f"  {'metric':20s} {'ln clean SPF (x100 = %)':>26s} {'loss on top (% points)':>26s}")
    for x in ["low_heat_share_35", "low_time_share_35", "low_est_share_35", "penalty_heat", "penalty_est"]:
        ok = s[x].notna().to_numpy()
        if ok.sum() < 30:
            continue
        xz = zs(s[x].to_numpy()[ok])
        X = np.column_stack([C1[ok], xz])
        a = coef_ci(s.ln_clean_spf.to_numpy()[ok], X, 2)
        b = coef_ci(s.loss_pct.to_numpy()[ok], X, 2)
        print(f"  {x:20s} {100 * a[0]:+7.2f} [{100 * a[1]:+.2f}, {100 * a[2]:+.2f}]"
              f"    {b[0]:+7.2f} [{b[1]:+.2f}, {b[2]:+.2f}]")
    print()

    # ---- 4. matched-temperature profile across the fleet
    P = pd.DataFrame(profs).reindex(s.index)
    print("COP against load at matched outside and flow temperature (within-cell ln COP deviation, %).")
    print("Median across systems with 20+ windows in the bin. Binning by metered heat puts any window that")
    print("makes less heat for its electricity into a lower bin, which tilts the curve up; binning by the")
    print("heat-free estimate does the same with electricity, which tilts it down. The truth lies between.")
    for name in ("heat", "est"):
        label = "metered heat / rated" if name == "heat" else "heat-free estimate / rated"
        print(f"  {label}:")
        for iv in pd.IntervalIndex.from_breaks(LR_BINS, closed="left"):
            col = (name, str(iv))
            if col in P.columns:
                v = P[col].dropna()
                if len(v) >= 10:
                    print(f"    {str(iv):14s} n={len(v):3d}  median {100 * v.median():+6.1f}%  "
                          f"IQR [{100 * v.quantile(.25):+.1f}, {100 * v.quantile(.75):+.1f}]")
    print()

    # ---- 5. split halves
    print("Split halves. Reliability: Spearman of each metric between halves.")
    print("Out-of-sample: metric from one half against the other half's residual of window space SPF on dT;")
    print("'same half' uses the metric and residual from the same windows, for comparison.")
    for split in ("week", "season"):
        res = {}
        for h in "AB":
            sp, dT = s[f"{split}{h}_space_spf"], s[f"{split}{h}_dT"]
            ok = sp.notna() & dT.notna()
            r = pd.Series(np.nan, index=s.index)
            r[ok] = resid(sp[ok].to_numpy(), dT[ok].to_numpy()[:, None])
            res[h] = r
        print(f"  {split}:")
        for k in ("low_heat_share_35", "low_time_share_35", "low_est_share_35", "penalty_heat", "penalty_est"):
            a, b = s[f"{split}A_{k}"], s[f"{split}B_{k}"]
            ok = a.notna() & b.notna()
            rel = stats.spearmanr(a[ok], b[ok])[0]
            out = []
            for src, tgt in (("A", "B"), ("B", "A"), ("A", "A"), ("B", "B")):
                x, r = s[f"{split}{src}_{k}"], res[tgt]
                o = x.notna() & r.notna()
                out.append(stats.spearmanr(x[o], r[o])[0])
            print(f"    {k:20s} n={ok.sum():3d}  reliability {rel:5.2f}   A->B {out[0]:+.2f}  B->A {out[1]:+.2f}"
                  f"   same half {out[2]:+.2f} / {out[3]:+.2f}")
    print()

    # ---- 6. systems with the most low-load running
    t = s.assign(heat_MWh=np.exp(s.ln_heat), resid_dT_heat=r_C1)
    t = t.sort_values("low_heat_share_35", ascending=False)
    print("Systems with the largest low-load heat share:")
    print(t[["size_kw", "heat_MWh", "heat_weighted_load", "low_heat_share_35", "low_est_share_35",
             "penalty_heat", "clean_spf", "h4_spf", "weighted_dT", "resid_dT_heat"]].head(15).round(3).to_string())

    out = os.path.join(args.windows, "lowload_systems_unflagged.csv" if args.exclude_flagged
                       else "lowload_systems.csv")
    s.assign(resid_dT=r_dT, resid_dT_heat=r_C1).to_csv(out)
    print(f"\nwrote {out}")


if __name__ == "__main__":
    main()
