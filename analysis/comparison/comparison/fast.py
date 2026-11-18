"""10-second timeseries analysis: pump power, primary loop volume, primary loop heat loss."""
import numpy as np
import pandas as pd

RUN_W = 100
CPW = 4180 * 0.995 / 60   # W per (l/min x K)


def _segments(mask, minlen):
    m = np.r_[False, mask, False].astype(int)
    e = np.flatnonzero(np.diff(m))
    return [(a, b) for a, b in zip(e[::2], e[1::2]) if b - a >= minlen]


def _xcorr(x, y, mask, maxlag=60, minlen=120):
    """Sum over segments of the correlation between dx(t) and dy(t + lag)."""
    num, n = np.zeros(maxlag + 1), 0
    for a, b in _segments(mask, minlen):
        dx, dy = np.diff(x[a:b]), np.diff(y[a:b])
        if np.isnan(dx).any() or np.isnan(dy).any():
            continue
        dx, dy = dx - dx.mean(), dy - dy.mean()
        for L in range(maxlag + 1):
            num[L] += np.dot(dx[:len(dx) - L], dy[L:])
        n += 1
    return num, n


def pump_power(d):
    """Electricity with the pump running and compressor off, by 1 l/min flow band, minus pump-off standby."""
    run = d.elec.values > RUN_W
    fr, e = d.flowrate.values, d.elec.values
    base = float(np.nanmedian(e[~run & (fr < 0.5)]))
    s = pd.DataFrame({"fr": fr[~run & (fr > 0.5)], "e": e[~run & (fr > 0.5)]}).dropna()
    g = s.groupby(np.floor(s.fr)).e.agg(["size", "median"])
    g = g[g["size"] >= 30]
    running_fr = float(np.nanmedian(fr[run & (d.dhw.values < 0.5)]))
    band = np.floor(running_fr)
    at_run = float(g.loc[band, "median"]) if band in g.index else None
    return dict(standby_pump_off_w=base, running_flowrate=running_fr,
                by_band={int(k): dict(n=int(r["size"]), total_w=float(r["median"]), pump_w=float(r["median"] - base))
                         for k, r in g.iterrows()},
                total_w_at_running_flow=at_run, pump_w_at_running_flow=(at_run - base) if at_run is not None else None)


def loop_volume(d):
    """Return-to-flow transport delay (primary loop through the heat pump) and flow-to-return delay."""
    run = d.elec.values > RUN_W
    dhw = d.dhw.values > 0.5
    fr, F, R = d.flowrate.values, d.flowT.values, d.returnT.values
    pump = fr > 3
    out = {}
    for name, mask in (("space", run & ~dhw & pump), ("dhw", run & dhw & pump), ("pump_only", ~run & pump & ~dhw)):
        q = float(np.nanmean(fr[mask]))
        c1, n = _xcorr(R, F, mask)
        c2, _ = _xcorr(F, R, mask)
        l1, l2 = int(np.argmax(c1[1:])) + 1, int(np.argmax(c2[1:])) + 1
        out[name] = dict(segments=n, flowrate=q, return_to_flow_s=l1 * 10, primary_litres=l1 * 10 / 60 * q,
                         flow_to_return_s=l2 * 10, system_litres=l2 * 10 / 60 * q)
    return out


def loop_loss(d, outside, lags):
    """Heat loss of the primary loop and unit casing from pump-only periods.

    The temperature drop from the lag-shifted return to the flow is regressed on water - outside
    temperature, with the rate of change of water temperature as a second term so that cooling after
    a cycle isn't read as loss. The result is sensitive to the lag, so it is evaluated over the range
    of measured loop lags (in 10 s samples) and reported as a range."""
    d = d.copy()
    o = outside.dropna()
    d["out"] = np.interp(d.index, o.index, o.values)
    run = pd.Series((d.elec > RUN_W).values, index=d.index)
    i = np.arange(len(d))
    d["since"] = i - np.maximum.accumulate(np.where(run.values, i, -10 ** 9))
    mw = ((d.flowT + d.returnT) / 2).rolling(7, center=True).mean()
    d["rate"] = mw.diff().rolling(7, center=True).mean()
    rs = d[run & (d.dhw < 0.5) & (d.flowrate > 3)]
    xr = float(((rs.flowT + rs.returnT) / 2 - rs.out).mean())
    heat = float(rs.heat.mean())
    fits = {}
    for t in sorted(set(lags)):
        d["Rlag"] = d.returnT.shift(t)
        pump = (d.flowrate > 3) & (d.flowrate.shift(t) > 3) & (d.dhw < 0.5)
        p = d[pump & ~run & (d.since >= 60 + t)].dropna(subset=["Rlag", "flowT", "out", "rate"])
        X = np.column_stack([np.ones(len(p)), (p.Rlag + p.flowT) / 2 - p.out, p.rate])
        b, *_ = np.linalg.lstsq(X, (p.Rlag - p.flowT).values, rcond=None)
        ua = float(b[1] * p.flowrate.mean() * CPW)
        fits[t * 10] = dict(samples=len(p), ua_w_per_k=ua, offset_K=float(b[0]),
                            quasi_steady_samples=int((p.rate.abs() < 0.0005).sum()))
    uas = [f["ua_w_per_k"] for f in fits.values()]
    return dict(by_lag_s=fits, ua_low=min(uas), ua_high=max(uas), running_water_minus_outside=xr,
                running_heat_w=heat, loss_share_low=min(uas) * xr / heat, loss_share_high=max(uas) * xr / heat)
