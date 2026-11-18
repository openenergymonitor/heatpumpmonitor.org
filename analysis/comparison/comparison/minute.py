"""1-minute timeseries analysis of one system, and matched comparisons between two systems."""
import numpy as np
import pandas as pd

from .otmodel import carnot

RUN_W = 100          # compressor running when electricity above this
DEFROST_W = -1000    # a real defrost dips heat below this
RECOVERY_MIN = 10    # minutes after a defrost counted as recovery
STEADY_MIN = 20      # minutes into a cycle before counting as steady state


def kwh(x):
    return float(np.nansum(x)) / 60 / 1000


def _since(run):
    """Samples since the compressor was last running (large if never)."""
    i = np.arange(len(run))
    last = np.maximum.accumulate(np.where(run, i, -10 ** 9))
    return i - last


def _segments(mask):
    m = np.r_[False, mask, False].astype(int)
    e = np.flatnonzero(np.diff(m))
    return list(zip(e[::2], e[1::2]))


def prep(df):
    d = df.copy()
    d["outsideT"] = d["outsideT"].interpolate(limit=180)
    d["run"] = d.elec > RUN_W
    d["dhwm"] = d.dhw > 0.5
    r = d.run.values.astype(int)
    off5 = pd.Series(1 - r, index=d.index).rolling(5).min().shift(1).fillna(0).values
    d["start"] = (r == 1) & (off5 == 1)
    cyc = np.cumsum(d.start.values)
    first = pd.Series(np.arange(len(d))).groupby(cyc).transform("min").values
    d["tin"] = np.arange(len(d)) - first
    d["cyc"] = cyc
    d["since"] = _since(d.run.values)
    d["defrost"] = d.run & (d.heat < 0)
    d["dT"] = d.flowT - d.returnT
    # real defrost events and their recovery windows
    events, dfz = [], np.zeros(len(d), bool)
    for a, b in _segments(d.defrost.values):
        x = d.iloc[a:b]
        if x.heat.min() < DEFROST_W:
            events.append(dict(i=a, ts=int(d.index[a]), minutes=b - a, out=d.outsideT.iloc[a], dhw=bool(d.dhwm.iloc[a]),
                               kwh=float(x.heat.sum()) / 60000))
            dfz[a:min(len(d), b + RECOVERY_MIN)] = True
    d["dfz"] = dfz
    return d, pd.DataFrame(events)


def eta_agg(x):
    """Heat-weighted ideal efficiency: sum(H / Carnot) / sum(E)."""
    return float((x.heat / carnot(x.flowT, x.outsideT)).sum() / x.elec.sum())


def steady(d):
    return d[d.run & ~d.dhwm & (d.tin >= STEADY_MIN) & ~d.defrost & (d.heat > 200) & d.outsideT.notna()]


def system(d, ev):
    """Single-system metrics from prepared 1-minute data."""
    days = len(d) / 1440
    sp, dh = d[~d.dhwm], d[d.dhwm]
    rs = sp[sp.run]
    cl = rs.groupby("cyc").size()
    ss = rs[(rs.tin >= STEADY_MIN) & ~rs.defrost & (rs.heat > 0)]
    nz = rs[~rs.dfz]
    real_sp = ev[~ev.dhw] if len(ev) else ev
    band = np.floor(rs.outsideT / 2) * 2
    hours = rs.groupby(band).size() / 60
    ev_b = (np.floor(real_sp.out / 2) * 2).value_counts() if len(real_sp) else pd.Series(dtype=float)
    rate = {int(k): float(ev_b.get(k, 0) / hours[k]) for k in hours.index if hours[k] > 50 and k < 10}
    ra = d[d.run]
    hours_all = ra.groupby(np.floor(ra.outsideT / 2) * 2).size() / 60
    ev_all = (np.floor(ev.out / 2) * 2).value_counts() if len(ev) else pd.Series(dtype=float)
    rate_all = {int(k): float(ev_all.get(k, 0) / hours_all[k]) for k in hours_all.index if hours_all[k] > 50 and k < 10}
    off = d[~d.run]
    return dict(
        days=days, heat=kwh(d.heat), elec=kwh(d.elec), spf=float(d.heat.sum() / d.elec.sum()),
        space_heat=kwh(sp.heat), space_elec=kwh(sp.elec), space_cop=float(sp.heat.sum() / sp.elec.sum()),
        water_heat=kwh(dh.heat), water_elec=kwh(dh.elec), water_cop=float(dh.heat.sum() / dh.elec.sum()),
        off_elec_kwh=kwh(off.elec), off_mean_w=float(off.elec.mean()),
        space_starts_per_day=float(sp.start.sum() / days), space_run_hours=len(rs) / 60,
        cycle_median_min=float(cl.median()), share_runtime_cycles_lt30=float(cl[cl < 30].sum() / cl.sum()),
        run_mean=dict(elec=float(rs.elec.mean()), heat=float(rs.heat.mean()), flowT=float(rs.flowT.mean()),
                      returnT=float(rs.returnT.mean()), dT=float(rs.dT.mean()), flowrate=float(rs.flowrate.mean())),
        steady_cop=float(ss.heat.sum() / ss.elec.sum()),
        defrost_events_all=int(len(ev)), defrost_events_space=int(len(real_sp)),
        defrost_rate_per_run_hour=rate, defrost_rate_all_modes=rate_all,
        space_cop_running=float(rs.heat.sum() / rs.elec.sum()),
        space_cop_running_excl_defrost=float(nz.heat.sum() / nz.elec.sum()),
    )


def defrost_effect(ma, mb):
    """COP gain for A if it had B's relative defrost penalty."""
    pa = 1 - ma["space_cop_running"] / ma["space_cop_running_excl_defrost"]
    pb = 1 - mb["space_cop_running"] / mb["space_cop_running_excl_defrost"]
    a_with_b = ma["space_cop_running_excl_defrost"] * (1 - pb)
    return dict(penalty_a=pa, penalty_b=pb, cop_a=ma["space_cop_running"], cop_a_with_b_penalty=a_with_b,
                gain=a_with_b - ma["space_cop_running"])


def sensor_offset(d):
    """Flow - return with the pump running and compressor off for 15+ minutes."""
    p = d[~d.run & (d.flowrate > 3) & (d.since >= 15) & ~d.dhwm]
    month = pd.to_datetime(p.index, unit="s").month
    fb = p.groupby(np.floor(p.flowT / 2) * 2).dT.agg(["size", "mean", "median"])
    fb = fb[fb["size"] > 100]
    return dict(n=len(p), mean=float(p.dT.mean()), median=float(p.dT.median()),
                by_month={int(k): float(v) for k, v in p.groupby(month).dT.mean().items()},
                by_flowT={int(k): dict(n=int(r["size"]), mean=float(r["mean"]), median=float(r["median"]))
                          for k, r in fb.iterrows()})


def offset_correction(d, off):
    cpw = 4180 * 0.995 / 60
    fl = d.flowrate.fillna(0)
    lin = d[~d.run & (d.flowrate > 3) & (d.since >= 15) & (d.since < 1440) & ~d.dhwm & (d.flowT >= 19) & (d.flowT < 30)]
    mt = (lin.flowT + lin.returnT) / 2
    b, a = np.polyfit(mt, lin.dT, 1)

    def apply(corr):
        h = d.heat - corr
        sp = ~d.dhwm
        return dict(spf=float(h.sum() / d.elec.sum()), space_cop=float(h[sp].sum() / d.elec[sp].sum()),
                    water_cop=float(h[~sp].sum() / d.elec[~sp].sum()), heat_change=float(h.sum() / d.heat.sum() - 1))

    run = d.run & (d.flowrate > 3)
    mT = (d.flowT + d.returnT) / 2
    return dict(constant_all_flow=apply(off * cpw * fl), constant_running=apply(off * cpw * fl * d.run),
                trend=dict(a=float(a), b=float(b), at_space_running=float(a + b * mT[run & ~d.dhwm].mean()),
                           at_dhw_running=float(a + b * mT[run & d.dhwm].mean())),
                extrapolated=apply(((a + b * mT).where(run, 0).fillna(0)) * cpw * fl))


def between_cycle_losses(d, volumes, uas):
    """Primary loop losses between cycles, in kWh per year of data.

    overrun: pump running, compressor off, space heating. The meter sees this loss (flow below
    return), so it is already in the measured COP, but it isn't part of the running-time pipework
    estimate. Calculated as UA x (water - outside) over those minutes.

    standing: pump off. The loop water cools towards outside temperature and is reheated, unmetered,
    on the next start. Each pump-off period is modelled as C (water - outside at stop)(1 - exp(-t/tau))
    with C = volume x 4186 J/K and tau = C / UA. This is an upper bound: still water loses heat more
    slowly than circulating water, and indoor pipe sections cool towards room temperature."""
    po = d[~d.run & (d.flowrate > 1) & ~d.dhwm]
    x_po = ((po.flowT + po.returnT) / 2 - po.outsideT).clip(lower=0)
    pump = (d.flowrate > 1).values
    m = np.r_[False, ~pump, False].astype(int)
    e = np.flatnonzero(np.diff(m))
    tw = ((d.flowT + d.returnT) / 2).values
    to = d.outsideT.values
    dur, dT0 = [], []
    for a, b in zip(e[::2], e[1::2]):
        if a == 0:
            continue
        x = tw[a - 1] - np.nanmean(to[a:b])
        if np.isfinite(x):
            dur.append((b - a) * 60.0)
            dT0.append(max(x, 0))
    dur, dT0 = np.array(dur), np.array(dT0)
    years = len(d) / 1440 / 365

    def standing(v, ua):
        C = v * 4186
        return float((C * dT0 * (1 - np.exp(-dur * ua / C))).sum() / 3.6e6 / years)

    overrun = {u: float((u * x_po).sum() / 60 / 1000 / years) for u in uas}
    stand = [standing(v, u) for v in volumes for u in uas]
    return dict(overrun_hours=len(po) / 60 / years, overrun_water_minus_outside=float(x_po.mean()),
                overrun_metered_heat_kwh=kwh(po.heat) / years,
                overrun_low=min(overrun.values()), overrun_high=max(overrun.values()),
                pump_off_periods_per_day=len(dur) / (len(d) / 1440), pump_off_median_min=float(np.median(dur) / 60) if len(dur) else None,
                standing_low=min(stand), standing_high=max(stand),
                space_heat_kwh=kwh(d.heat[~d.dhwm]) / years)


def matched(da, db):
    """Steady-state COP in cells of matching outside temp, flow temp and heat output."""
    g = {}
    for k, x in (("a", steady(da)), ("b", steady(db))):
        cells = [np.floor(x.outsideT / 2) * 2, np.floor(x.flowT / 2) * 2, np.floor(x.heat / 500) * 500]
        g[k] = x.groupby(cells).agg(n=("heat", "size"), H=("heat", "sum"), E=("elec", "sum"),
                                     fr=("flowrate", "mean"), dT=("dT", "mean"))
        g[k + "_total"] = len(x)
    m = g["a"].join(g["b"], how="inner", lsuffix="_a", rsuffix="_b")
    m = m[(m.n_a >= 60) & (m.n_b >= 60)]
    w = np.minimum(m.n_a, m.n_b)
    ca, cb = m.H_a / m.E_a, m.H_b / m.E_b
    wm = lambda s: float((s * w).sum() / w.sum())
    return dict(cells=len(m), coverage_a=float(m.n_a.sum() / g["a_total"]), coverage_b=float(m.n_b.sum() / g["b_total"]),
                cop_a=wm(ca), cop_b=wm(cb), ratio=wm(cb / ca), flowrate_a=wm(m.fr_a), flowrate_b=wm(m.fr_b),
                dT_a=wm(m.dT_a), dT_b=wm(m.dT_b))


def band_eta(d):
    """Steady ideal efficiency by outside band at 31-35 C flow and 1.8-2.6 kW output (defrost windows excluded)."""
    sp = d[d.run & ~d.dhwm]
    ss = sp[(sp.tin >= STEADY_MIN) & ~sp.dfz & (sp.heat > 1800) & (sp.heat < 2600) & (sp.flowT >= 31) & (sp.flowT < 35)]
    g = ss.groupby(np.floor(ss.outsideT / 2) * 2).agg(n=("heat", "size"), H=("heat", "sum"), E=("elec", "sum"),
                                                     f=("flowT", "mean"), o=("outsideT", "mean"))
    g = g[g.n > 200]
    return {int(k): dict(n=int(r.n), flow=float(r.f), cop=float(r.H / r.E), eta=float(r.H / r.E / carnot(r.f, r.o)))
            for k, r in g.iterrows()}


def _solve_air(xa, eta_b):
    """Air temperature drop at A's evaporator that brings A's electricity down to B's efficiency."""
    H, E, f, t = xa.heat.values, xa.elec.values.sum(), xa.flowT.values, xa.outsideT.values
    lo, hi = -10.0, 20.0
    for _ in range(60):
        mid = (lo + hi) / 2
        if (H / (eta_b * carnot(f, t - mid))).sum() < E:
            lo = mid
        else:
            hi = mid
    return (lo + hi) / 2


def air_shortfall(da, db, pump_w=0.0, ua=0.0):
    """Implied evaporator air shortfall for A against B, after removing A's extra pump power (W)
    and extra unmetered pipework loss (W/K of water - outside)."""
    a, b = steady(da).copy(), steady(db).copy()
    a["elec"] = a.elec - pump_w
    a["heat"] = a.heat + ua * ((a.flowT + a.returnT) / 2 - a.outsideT)
    for x in (a, b):
        x["hr"] = pd.to_datetime(x.index, unit="s").hour
    out = dict(pump_w=pump_w, ua=ua, steady_cop_a=float(a.heat.sum() / a.elec.sum()),
               steady_cop_b=float(b.heat.sum() / b.elec.sum()), eta_a=eta_agg(a), eta_b=eta_agg(b),
               overall=_solve_air(a, eta_agg(b)), by_band={}, by_output={}, day={}, night={}, output_4_8={})
    for lo in range(-2, 16, 2):
        xa, xb = a[(a.outsideT >= lo) & (a.outsideT < lo + 2)], b[(b.outsideT >= lo) & (b.outsideT < lo + 2)]
        if min(len(xa), len(xb)) >= 300:
            out["by_band"][lo] = dict(eta_a=eta_agg(xa), eta_b=eta_agg(xb), K=_solve_air(xa, eta_agg(xb)))
    for lo in (1500, 2000, 2500, 3000):
        xa, xb = a[(a.heat >= lo) & (a.heat < lo + 500)], b[(b.heat >= lo) & (b.heat < lo + 500)]
        if min(len(xa), len(xb)) >= 300:
            out["by_output"][lo] = _solve_air(xa, eta_agg(xb))
        xa = xa[(xa.outsideT >= 4) & (xa.outsideT < 8)]
        xb = xb[(xb.outsideT >= 4) & (xb.outsideT < 8)]
        if min(len(xa), len(xb)) >= 200:
            out["output_4_8"][lo] = _solve_air(xa, eta_agg(xb))
    for name, hrs in (("day", lambda h: (h >= 10) & (h < 16)), ("night", lambda h: (h >= 22) | (h < 6))):
        for lo in range(0, 12, 2):
            sel = lambda x: x[(x.outsideT >= lo) & (x.outsideT < lo + 2) & hrs(x.hr) & (x.heat >= 1500) & (x.heat < 2500)]
            xa, xb = sel(a), sel(b)
            if min(len(xa), len(xb)) >= 200:
                out[name][lo] = _solve_air(xa, eta_agg(xb))
    return out
