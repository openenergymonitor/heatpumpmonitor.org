"""Daily-data analysis: per-system summary, factor (Shapley) decomposition, counterfactual SPF."""
import math
from itertools import combinations

import numpy as np

from . import otmodel as om

FACTORS = ["climate", "space_demand", "dhw_demand", "standby",
           "space_eta", "space_flow", "dhw_eta", "dhw_flow"]


def _hw_mean(pairs):
    w = sum(p[1] for p in pairs)
    return sum(p[0] * p[1] for p in pairs) / w if w else None


def summary(df, end_ts=None):
    """Reference-year summary of one system from its daily stats."""
    days, split = om.parse(df)
    if end_ts is not None:
        days = [d for d in days if d["ts"] <= end_ts]
    ok, end = om.reference_year(days)
    if not ok:
        return None
    m = om.fit(ok)
    s = lambda f: sum(f(d) for d in ok)
    H, E, Hs, Es, Hw, Ew, I = (s(lambda d: d["H"]), s(lambda d: d["E"]), s(lambda d: d["Hs"]), s(lambda d: d["Es"]),
                               s(lambda d: d["Hw"]), s(lambda d: d["Ew"]), s(lambda d: d["idle"]))
    sd = [d for d in ok if d["Hs"] > 2 and (d["flowS"] or 0) > 0]
    wd = [d for d in ok if d["Hw"] > 1 and (d["flowW"] or 0) > 0]
    dts = _hw_mean([(d["flowS"] - d["t"], d["Hs"]) for d in sd])
    dtall = _hw_mean([(d["flowS"] - d["t"], d["Hs"]) for d in sd] + [(d["flowW"] - d["t"], d["Hw"]) for d in wd])
    flow_s = _hw_mean([(d["flowS"], d["Hs"]) for d in sd])
    flow_w = _hw_mean([(d["flowW"], d["Hw"]) for d in wd])
    sr = [d for d in ok if d["Hs"] > 2 and d["flowS"] is not None and d["retS"] is not None]
    dt_fr = _hw_mean([(d["flowS"] - d["retS"], d["Hs"]) for d in sr])
    cold = [d for d in ok if d["t"] < 4]
    mild = [d for d in ok if 6 <= d["t"] < 12]
    summer = [d for d in ok if d["t"] >= 15]
    hc = sum(d["H"] for d in cold)
    out = dict(
        split=split, end_ts=end, ok_days=len(ok), days_total=sum(1 for d in days if d["ok"]),
        heat=H, elec=E, spf=H / E if E else None,
        space_heat=Hs, space_elec=Es, space_cop=Hs / Es if Es else None,
        water_heat=Hw, water_elec=Ew, water_cop=Hw / Ew if Ew else None,
        standby=I, dhw_share=Hw / H if H else None, standby_share=I / E if E else None,
        mean_t=s(lambda d: d["t"]) / len(ok), heat_median_t=om.heat_median_t(ok),
        flow_space_hw=flow_s, flow_water_hw=flow_w, dT_flow_outside_space=dts, dT_flow_outside_all=dtall,
        dT_flow_return_space=dt_fr,
        defrost_cold_pct=(sum(d["cool"] for d in cold) / hc * 100) if len(cold) >= 10 and hc else None,
        negheat_mild_pct=(sum(d["cool"] for d in mild) / sum(d["H"] for d in mild) * 100) if mild else None,
        negheat_summer_per_day=(sum(d["cool"] for d in summer) / len(summer)) if summer else None,
        cooling_mode=any(d["cool_heat"] > 0 for d in ok), n_cold=len(cold),
    )
    if m:
        out.update(wk=m["hinge"]["k"] / 24 * 1000, tb=m["hinge"]["tb"], tb_fixed=m["hinge"]["fixed"],
                   idle_per_day=m["idle"], water_a=m["water"]["a"], water_b=m["water"]["b"])
        for key, c in (("S", m["copS"]), ("W", m["copW"])):
            out[f"cop{key}_type"] = c["type"] if c else None
            if c and c["type"] == "carnot":
                out.update({f"eta{key}": c["eta"], f"flow{key}_f0": c["f0"], f"flow{key}_f1": c["f1"]})
    out["_ok"] = ok
    out["_model"] = m
    return out


def _params(sm):
    m = sm["_model"]
    return dict(climate=[d["t"] for d in sm["_ok"]], space_demand=m["hinge"], dhw_demand=m["water"],
                standby=m["idle"], space_eta=m["copS"]["eta"], space_flow=(m["copS"]["f0"], m["copS"]["f1"]),
                dhw_eta=m["copW"]["eta"], dhw_flow=(m["copW"]["f0"], m["copW"]["f1"]))


def model_spf(P):
    t = np.array(P["climate"])
    hs = P["space_demand"]["k"] * np.maximum(0, P["space_demand"]["tb"] - t)
    hw = np.maximum(0, P["dhw_demand"]["a"] + P["dhw_demand"]["b"] * t)
    cs = P["space_eta"] * om.carnot(P["space_flow"][0] + P["space_flow"][1] * t, t)
    cw = P["dhw_eta"] * om.carnot(P["dhw_flow"][0] + P["dhw_flow"][1] * t, t)
    E = np.where(hs > 0, hs / cs, 0).sum() + np.where(hw > 0, hw / cw, 0).sum() + P["standby"] * len(t)
    return (hs.sum() + hw.sum()) / E


def shapley(sa, sb):
    """Average effect on model SPF of swapping each factor from system A to system B, over all orders."""
    A, B = _params(sa), _params(sb)
    n = len(FACTORS)
    cache = {}

    def val(mask):
        if mask not in cache:
            cache[mask] = model_spf({f: (B[f] if mask >> i & 1 else A[f]) for i, f in enumerate(FACTORS)})
        return cache[mask]

    phi = {}
    for i, f in enumerate(FACTORS):
        tot = 0.0
        for m in range(1 << n):
            if m >> i & 1:
                continue
            k = bin(m).count("1")
            tot += math.factorial(k) * math.factorial(n - k - 1) / math.factorial(n) * (val(m | 1 << i) - val(m))
        phi[f] = tot
    return dict(model_spf_a=val(0), model_spf_b=val((1 << n) - 1), effects=phi, total=sum(phi.values()))


def counterfactual(sa, sb):
    """System A's measured SPF if its space/water electricity scaled to system B's ideal efficiency."""
    rs, rw = sa["etaS"] / sb["etaS"], sa["etaW"] / sb["etaW"]
    H, Es, Ew, I = sa["heat"], sa["space_elec"], sa["water_elec"], sa["standby"]
    return dict(measured=H / (Es + Ew + I), space_eta_swapped=H / (Es * rs + Ew + I),
                space_and_water_swapped=H / (Es * rs + Ew * rw + I), ratio_space=rs, ratio_water=rw)


def public(sm):
    return {k: v for k, v in sm.items() if not k.startswith("_")}
