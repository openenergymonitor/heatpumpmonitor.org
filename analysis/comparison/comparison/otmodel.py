"""Python port of OTModel in www/views/outsidetemp.php (daily outside-temperature model).

Kept deliberately close to the JS so results match the outsidetemp page."""
import math

import numpy as np

COND, EVAP = 2.0, -6.0
TB_PRIOR = 15.5
DAY = 86400


def carnot(flow, t):
    """Carnot COP with condensing at flow + 2 K and evaporating at outside - 6 K (numpy friendly)."""
    tc = np.asarray(flow, dtype=float) + COND
    te = np.asarray(t, dtype=float) + EVAP
    return (tc + 273.15) / np.maximum(tc - te, 5)


def _num(v):
    if v is None:
        return None
    try:
        f = float(v)
    except (TypeError, ValueError):
        return None
    return None if math.isnan(f) else f


def parse(df):
    """Daily stats DataFrame -> (days, split). Mirrors OTModel.parse."""
    rows = [{k: _num(v) for k, v in r.items()} for r in df.to_dict("records")]
    split = any((r.get("water_heat_kwh") or 0) > 0 for r in rows)
    days = []
    for r in rows:
        H = r.get("combined_heat_kwh") or 0
        E = r.get("combined_elec_kwh") or 0
        Hw = (r.get("water_heat_kwh") or 0) if split else 0
        Ew = (r.get("water_elec_kwh") or 0) if split else 0
        if split:
            Hs = r["space_heat_kwh"] if r.get("space_heat_kwh") is not None else max(0, H - Hw)
            Es = r.get("space_elec_kwh") or 0
        else:
            Hs, Es = H, E
        if Hs < 0.3:
            Es = 0
        idle = max(0, E - Es - Ew)
        t = r.get("combined_outsideT_mean")
        dl = r.get("combined_data_length")
        flowS = r.get("space_flowT_mean") if split else (r.get("space_flowT_mean") or r.get("running_flowT_mean"))
        days.append(dict(ts=r.get("timestamp"), t=t, ok=(dl is not None and dl >= 0.9 * DAY and t is not None),
                         H=H, E=E, Hs=Hs, Es=Es, Hw=Hw, Ew=Ew, idle=idle, flowS=flowS,
                         flowW=r.get("water_flowT_mean"), retS=r.get("space_returnT_mean"),
                         cool=r.get("combined_cooling_kwh") or 0, cool_heat=r.get("cooling_heat_kwh") or 0))
    return [d for d in days if d["ts"]], split


def wls(xs, ys, ws=None):
    sw = sx = sy = sxx = sxy = 0.0
    for i in range(len(xs)):
        w = ws[i] if ws is not None else 1
        sw += w; sx += w * xs[i]; sy += w * ys[i]; sxx += w * xs[i] ** 2; sxy += w * xs[i] * ys[i]
    if sw <= 0:
        return None
    d = sw * sxx - sx * sx
    if abs(d) < 1e-9 * sw * sw:
        return dict(a=sy / sw, b=0.0)
    b = (sw * sxy - sx * sy) / d
    return dict(a=(sy - b * sx) / sw, b=b)


def fit_hinge(days):
    mild = sum(1 for d in days if d["t"] > 12)
    lo, hi = 12, 20
    if mild < 7:
        lo = hi = TB_PRIOR
    best = None
    tb = lo
    while tb <= hi + 0.001:  # same float stepping as the JS
        sxy = sum(max(0, tb - d["t"]) * d["Hs"] for d in days)
        sxx = sum(max(0, tb - d["t"]) ** 2 for d in days)
        if sxx > 0:
            k = sxy / sxx
            sse = sum((d["Hs"] - k * max(0, tb - d["t"])) ** 2 for d in days)
            if best is None or sse < best["sse"]:
                best = dict(tb=tb, k=k, sse=sse)
        tb += 0.1
    if best:
        best["fixed"] = lo == hi
        return best
    return dict(tb=TB_PRIOR, k=0, fixed=True)


def fit_cop(days, Hk, Ek, Fk):
    use = [d for d in days if d[Hk] > 0.5 and d[Ek] > 0]
    if not use:
        return None
    wf = [d for d in use if (d[Fk] or 0) > 0]
    if len(wf) >= 3 and len(wf) >= 0.5 * len(use):
        line = wls([d["t"] for d in wf], [d[Fk] for d in wf], [d[Hk] for d in wf])
        a = sum(d[Hk] / float(carnot(d[Fk], d["t"])) for d in wf)
        e = sum(d[Ek] for d in wf)
        return dict(type="carnot", f0=line["a"], f1=line["b"], eta=a / e)
    lin = wls([d["t"] for d in use], [d[Hk] / d[Ek] for d in use], [d[Hk] for d in use])
    return dict(type="linear", a=lin["a"], b=lin["b"])


def cop_of(c, t):
    if c is None:
        return None
    if c["type"] == "carnot":
        return c["eta"] * float(carnot(c["f0"] + c["f1"] * t, t))
    return max(1, c["a"] + c["b"] * t)


def fit(days):
    days = [d for d in days if d["ok"]]
    if len(days) < 3:
        return None
    hinge = fit_hinge(days)
    ts = [d["t"] for d in days]
    water = (wls(ts, [d["Hw"] for d in days]) if max(ts) - min(ts) >= 10
             else dict(a=sum(d["Hw"] for d in days) / len(days), b=0.0))
    return dict(n=len(days), hinge=hinge, water=water, idle=sum(d["idle"] for d in days) / len(days),
                copS=fit_cop(days, "Hs", "Es", "flowS"), copW=fit_cop(days, "Hw", "Ew", "flowW"))


def heat_median_t(days):
    ds = sorted([d for d in days if d["ok"] and d["H"] > 0], key=lambda d: d["t"])
    tot = sum(d["H"] for d in ds)
    acc = 0
    for d in ds:
        acc += d["H"]
        if acc >= tot / 2:
            return d["t"]
    return None


def reference_year(days, end_ts=None):
    """Complete days in the 365 days up to the last day (or up to end_ts)."""
    if end_ts is not None:
        days = [d for d in days if d["ts"] <= end_ts]
    if not days:
        return [], None
    end = days[-1]["ts"]
    return [d for d in days if end - 365 * DAY < d["ts"] <= end and d["ok"]], end
