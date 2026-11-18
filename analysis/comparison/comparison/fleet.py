"""Fleet scan: per-system daily summaries for a model family, similar-pair search, correlations."""
from itertools import combinations

import numpy as np
import pandas as pd

from . import api
from . import daily as dl

DAY = 86400


def select_ids(systems, manufacturer="aillant", model="rotherm"):
    return [i for i, s in systems.items()
            if manufacturer in (s.get("hp_manufacturer") or "") and model in (s.get("hp_model") or "").lower()
            and not s.get("data_flag")]


def scan(ids, systems, end_ts, min_days=330, max_age_days=30, refresh=False):
    data = api.daily_many(ids, refresh=refresh)
    rows = []
    for i in ids:
        try:
            sm = dl.summary(data[i], end_ts)
        except Exception:
            continue
        if not sm or not sm["split"] or sm["end_ts"] < end_ts - max_age_days * DAY or sm["ok_days"] < min_days:
            continue
        if sm.get("copS_type") != "carnot" or sm.get("copW_type") != "carnot":
            continue
        s = systems[i]
        r = dl.public(sm)
        r.update(id=i, location=s.get("location"), model=s.get("hp_model"), kw=s.get("hp_output"),
                 boundary=s.get("metering_boundary_code"), latitude=s.get("latitude"))
        rows.append(r)
    return pd.DataFrame(rows).set_index("id")


def family(m):
    m = m or ""
    return "V2" if "V2" in m else ("+" if "+" in m or "lus" in m else m)


def similar_pairs(f, top=10):
    out = []
    recs = f.reset_index().to_dict("records")
    for a, b in combinations(recs, 2):
        if a["kw"] != b["kw"] or a["boundary"] != b["boundary"] or family(a["model"]) != family(b["model"]):
            continue
        if abs(a["wk"] - b["wk"]) / max(a["wk"], b["wk"]) > 0.12:
            continue
        if abs(a["dhw_share"] - b["dhw_share"]) > 0.04:
            continue
        if abs(a["days_total"] - b["days_total"]) / max(a["days_total"], b["days_total"]) > 0.2:
            continue
        if abs(a["dT_flow_outside_all"] - b["dT_flow_outside_all"]) > 1.5:
            continue
        if abs(a["heat_median_t"] - b["heat_median_t"]) > 1.5:
            continue
        out.append(dict(a=a["id"], b=b["id"], kw=a["kw"], d_etaS=abs(a["etaS"] - b["etaS"]),
                        etaS=(a["etaS"], b["etaS"]), spf=(a["spf"], b["spf"]), heat=(a["heat"], b["heat"]),
                        dhw_share=(a["dhw_share"], b["dhw_share"]), standby_share=(a["standby_share"], b["standby_share"])))
    return sorted(out, key=lambda p: -p["d_etaS"])[:top]


def _rho(x, y):
    return float(x.rank().corr(y.rank()))


def correlations(f):
    f = f[~f.cooling_mode].copy()
    res = dict(n=len(f), overall={}, by_kw={}, quartiles={}, regression={})
    for b in ("dT_flow_return_space", "defrost_cold_pct", "negheat_mild_pct", "dT_flow_outside_all",
              "heat_median_t", "kw", "latitude", "standby_share"):
        x = f[["etaS", b]].dropna().astype(float)
        res["overall"][b] = dict(n=len(x), pearson=float(x.etaS.corr(x[b])), spearman=_rho(x.etaS, x[b]))
    for kw, g in f.groupby("kw"):
        if len(g) < 10:
            continue
        res["by_kw"][float(kw)] = {b: dict(pearson=float(g[["etaS", b]].dropna().astype(float).corr().iloc[0, 1]),
                                           spearman=_rho(*[g[["etaS", b]].dropna().astype(float)[c] for c in ("etaS", b)]),
                                           n=int(g[b].notna().sum()))
                                   for b in ("dT_flow_return_space", "defrost_cold_pct")}
        g = g.sort_values("etaS")
        q = len(g) // 5
        res["by_kw"][float(kw)]["quintiles"] = dict(
            low_eta=dict(dT=float(g.head(q).dT_flow_return_space.median()), defrost=float(g.head(q).defrost_cold_pct.median())),
            high_eta=dict(dT=float(g.tail(q).dT_flow_return_space.median()), defrost=float(g.tail(q).defrost_cold_pct.median())))
    f["resid"] = f.etaS - f.groupby("kw").etaS.transform("median")
    for col in ("dT_flow_return_space", "defrost_cold_pct"):
        q = pd.qcut(f[col], 4)
        g = f.groupby(q, observed=True).agg(n=("etaS", "size"), etaS=("etaS", "median"), spf=("spf", "median"),
                                           resid_std=("resid", "std"))
        res["quartiles"][col] = [dict(range=str(k), **{c: float(v) for c, v in r.items()}) for k, r in g.iterrows()]
    x = f.dropna(subset=["defrost_cold_pct"])
    kws = sorted(x.kw.unique())
    cols = ["dT_flow_return_space", "defrost_cold_pct", "heat_median_t", "dT_flow_outside_all"]
    X = np.column_stack([np.ones(len(x))] + [x[c].astype(float) for c in cols] + [(x.kw == k).astype(float) for k in kws[1:]])
    y = x.etaS.values.astype(float)
    b, *_ = np.linalg.lstsq(X, y, rcond=None)
    yhat = X @ b
    s2 = ((y - yhat) ** 2).sum() / (len(y) - X.shape[1])
    se = np.sqrt(np.diag(s2 * np.linalg.inv(X.T @ X)))
    names = ["const"] + cols + [f"kw_{k:g}_vs_{kws[0]:g}" for k in kws[1:]]
    res["regression"] = dict(n=len(y), r2=float(1 - ((y - yhat) ** 2).sum() / ((y - y.mean()) ** 2).sum()),
                             coef={n: dict(b=float(bb), se=float(ss), t=float(bb / ss)) for n, bb, ss in zip(names, b, se)})
    return res
