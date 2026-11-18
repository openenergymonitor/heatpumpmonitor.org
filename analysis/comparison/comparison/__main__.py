"""Command line entry point.

    python -m comparison pair 384 335 --end 2026-11-17
    python -m comparison fleet --end 2026-11-17
"""
import argparse
import datetime as dt
import json
import os

import numpy as np

from . import api, daily, fast, fleet, minute

DAY = 86400
OUT = os.path.join(os.path.dirname(__file__), "..", "output")


def _date(s):
    return int(dt.datetime.strptime(s, "%Y-%m-%d").replace(tzinfo=dt.timezone.utc).timestamp())


def _json(o):
    if isinstance(o, dict):
        return {str(k): _json(v) for k, v in o.items()}
    if isinstance(o, (list, tuple)):
        return [_json(v) for v in o]
    if isinstance(o, (np.integer,)):
        return int(o)
    if isinstance(o, (np.floating,)):
        return None if np.isnan(o) else float(o)
    if isinstance(o, float) and np.isnan(o):
        return None
    if isinstance(o, np.bool_):
        return bool(o)
    return o


def _save(name, res, md):
    os.makedirs(OUT, exist_ok=True)
    with open(os.path.join(OUT, name + ".json"), "w") as f:
        json.dump(_json(res), f, indent=1)
    with open(os.path.join(OUT, name + ".md"), "w") as f:
        f.write(md)
    print(md)


def run_pair(a, b, end, windows, refresh=False):
    end_ts = _date(end) + 12 * 3600            # daily rows are stamped at local midnight
    ts_start = _date(end) + DAY - 365 * DAY    # 365 days of timeseries ending with the end date
    systems = api.system_list(refresh)
    res = dict(a=a, b=b, end=end, meta={}, daily={}, minute={}, fast={})
    for i in (a, b):
        s = systems[i]
        res["meta"][i] = {k: s.get(k) for k in ("location", "hp_manufacturer", "hp_model", "hp_output", "metering_boundary_code",
                                               "heat_meter", "electric_meter", "hydraulic_separation", "volumiser",
                                               "space_heat_control_type", "dhw_method", "cylinder_volume", "property",
                                               "floor_area", "installer_name")}
    sm = {i: daily.summary(api.daily(i, refresh), end_ts) for i in (a, b)}
    for i in (a, b):
        res["daily"][i] = daily.public(sm[i])
    res["shapley"] = daily.shapley(sm[a], sm[b])
    res["counterfactual"] = daily.counterfactual(sm[a], sm[b])

    prepared = {}
    for i in (a, b):
        d, ev = minute.prep(api.timeseries(i, ts_start, 365, 60, refresh=refresh))
        prepared[i] = d
        m = minute.system(d, ev)
        off = minute.sensor_offset(d)
        m["sensor_offset"] = off
        m["offset_correction"] = minute.offset_correction(d, off["mean"])
        m["band_eta"] = minute.band_eta(d)
        res["minute"][i] = m
    da, db = prepared[a], prepared[b]
    res["defrost_effect"] = minute.defrost_effect(res["minute"][a], res["minute"][b])
    res["matched"] = minute.matched(da, db)

    win = [_date(w) for w in windows]
    for i in (a, b):
        d10 = __import__("pandas").concat([api.timeseries(i, w, 7, 10, feeds=api.TS_FEEDS[:5] + ["heatpump_dhw"], refresh=refresh)
                                           for w in win]).sort_index()
        lv = fast.loop_volume(d10)
        lags = sorted(v["return_to_flow_s"] // 10 for v in lv.values())
        res["fast"][i] = dict(pump=fast.pump_power(d10), loop_volume=lv,
                              loop_loss=fast.loop_loss(d10, prepared[i].outsideT, range(lags[0], lags[-1] + 1)))
    fa, fb = res["fast"][a], res["fast"][b]
    pump_diff = (fa["pump"]["pump_w_at_running_flow"] or 0) - (fb["pump"]["pump_w_at_running_flow"] or 0)
    pump_diff_raw = (fa["pump"]["total_w_at_running_flow"] or 0) - (fb["pump"]["total_w_at_running_flow"] or 0)
    la, lb = fa["loop_loss"], fb["loop_loss"]
    ua_lo = max(la["ua_low"] - lb["ua_high"], 0)
    ua_hi = max(la["ua_high"] - lb["ua_low"], 0)
    for i in (a, b):
        vols = [v["primary_litres"] for v in res["fast"][i]["loop_volume"].values()]
        ll = res["fast"][i]["loop_loss"]
        res["minute"][i]["between_cycles"] = minute.between_cycle_losses(
            prepared[i], (min(vols), max(vols)), (ll["ua_low"], ll["ua_high"]))
    pump_w = max(pump_diff, 0)
    res["air_raw"] = minute.air_shortfall(da, db)
    res["air_adjusted"] = {k: minute.air_shortfall(da, db, pump_w=pump_w, ua=u) for k, u in (("low", ua_lo), ("high", ua_hi))}

    # COP budget for A's space heating, out of the gap to B
    ma, mb = res["minute"][a], res["minute"][b]
    cop_a = ma["space_cop"]
    run_e, run_h = ma["run_mean"]["elec"], ma["run_mean"]["heat"]
    pump_eff = lambda w: cop_a * (run_e / (run_e - w) - 1)
    xr = la["running_water_minus_outside"]
    budget = dict(
        cop_a=cop_a, cop_b=mb["space_cop"], gap=mb["space_cop"] - cop_a,
        defrost=res["defrost_effect"]["gain"],
        pump=dict(low=pump_eff(max(min(pump_diff, pump_diff_raw), 0)), high=pump_eff(max(pump_diff, pump_diff_raw, 0)),
                  pump_diff_w=pump_diff, total_diff_w=pump_diff_raw),
        pipework=dict(low=cop_a * ua_lo * xr / run_h, high=cop_a * ua_hi * xr / run_h, ua_diff_low=ua_lo, ua_diff_high=ua_hi),
    )
    # between-cycle losses: A's extra over B, as a share of A's space heat
    ca, cb = ma["between_cycles"], mb["between_cycles"]
    tot = lambda c, k: c[f"overrun_{k}"] + c[f"standing_{k}"]
    extra_lo = max(tot(ca, "low") - tot(cb, "high"), 0)
    extra_hi = max(tot(ca, "high") - tot(cb, "low"), 0)
    budget["pipework_between_cycles"] = dict(low=cop_a * extra_lo / ca["space_heat_kwh"], high=cop_a * extra_hi / ca["space_heat_kwh"],
                                             extra_kwh_low=extra_lo, extra_kwh_high=extra_hi)
    known_lo = budget["defrost"] + budget["pump"]["low"] + budget["pipework"]["low"] + budget["pipework_between_cycles"]["low"]
    known_hi = budget["defrost"] + budget["pump"]["high"] + budget["pipework"]["high"] + budget["pipework_between_cycles"]["high"]
    budget["remainder"] = dict(low=budget["gap"] - known_hi, high=budget["gap"] - known_lo)
    res["budget"] = budget
    _save(f"pair_{a}_{b}", res, pair_markdown(res))
    return res


def f2(v, n=2):
    return "–" if v is None else f"{v:.{n}f}"


def pair_markdown(r):
    a, b = r["a"], r["b"]
    A, B = r["daily"][a], r["daily"][b]
    MA, MB = r["minute"][a], r["minute"][b]
    FA, FB = r["fast"][a], r["fast"][b]
    L = [f"# System {a} vs {b} (reference year ending {r['end']})", ""]
    L += ["## Daily summary", "", f"| | {a} | {b} |", "|---|---|---|"]
    rows = [("SPF", "spf", 2), ("Heat kWh", "heat", 0), ("Heat loss W/K", "wk", 0), ("Balance temp", "tb", 1),
            ("Hot water share", "dhw_share", 3), ("Standby share of elec", "standby_share", 3),
            ("Space COP", "space_cop", 2), ("Water COP", "water_cop", 2), ("Ideal eff. space", "etaS", 3),
            ("Ideal eff. water", "etaW", 3), ("Flow−outside all", "dT_flow_outside_all", 1),
            ("Flow−outside space", "dT_flow_outside_space", 1), ("Flow−return space", "dT_flow_return_space", 2),
            ("Heat-median outside T", "heat_median_t", 2), ("Space flow, heat-weighted", "flow_space_hw", 1),
            ("Water flow, heat-weighted", "flow_water_hw", 1), ("Cold-day defrost % of heat", "defrost_cold_pct", 2),
            ("Complete days in history", "days_total", 0), ("Days in reference year", "ok_days", 0)]
    L += [f"| {n} | {f2(A.get(k), p)} | {f2(B.get(k), p)} |" for n, k, p in rows]
    S = r["shapley"]
    L += ["", f"## Factor decomposition (model SPF {S['model_spf_a']:.3f} → {S['model_spf_b']:.3f})", ""]
    L += [f"- {k}: {v:+.3f}" for k, v in S["effects"].items()] + [f"- total: {S['total']:+.3f}"]
    C = r["counterfactual"]
    L += ["", "## Counterfactual for " + str(a), "",
          f"- measured {C['measured']:.3f}; with {b}'s space efficiency {C['space_eta_swapped']:.3f}; "
          f"plus water {C['space_and_water_swapped']:.3f}"]
    L += ["", "## 1-minute data", "", f"| | {a} | {b} |", "|---|---|---|"]
    mrows = [("SPF", "spf", 3), ("Space COP", "space_cop", 3), ("Water COP", "water_cop", 3), ("Heat kWh", "heat", 0),
             ("Space starts/day", "space_starts_per_day", 1), ("Median cycle min", "cycle_median_min", 0),
             ("Runtime in cycles <30 min", "share_runtime_cycles_lt30", 3), ("Steady COP", "steady_cop", 3),
             ("Real defrosts (all)", "defrost_events_all", 0), ("Real defrosts (space)", "defrost_events_space", 0),
             ("Space COP running", "space_cop_running", 3), ("…excluding defrost windows", "space_cop_running_excl_defrost", 3)]
    L += [f"| {n} | {f2(MA[k], p)} | {f2(MB[k], p)} |" for n, k, p in mrows]
    for k in ("flowT", "returnT", "dT", "flowrate", "elec", "heat"):
        L.append(f"| Running mean {k} | {f2(MA['run_mean'][k])} | {f2(MB['run_mean'][k])} |")
    L += ["", "Defrosts per running hour by outside band:", ""]
    for i, M in ((a, MA), (b, MB)):
        for key in ("defrost_rate_per_run_hour", "defrost_rate_all_modes"):
            L.append(f"- {i} {key}: " + ", ".join(f"{k}: {v:.2f}" for k, v in sorted(M[key].items(), key=lambda x: int(x[0]))))
    D = r["defrost_effect"]
    L.append(f"- penalty {a} {D['penalty_a']:.3f}, {b} {D['penalty_b']:.3f}; COP gain for {a}: {D['gain']:.3f}")
    Mt = r["matched"]
    L += ["", f"## Matched steady state: {Mt['cells']} cells, coverage {Mt['coverage_a']:.2f}/{Mt['coverage_b']:.2f}, "
              f"COP {Mt['cop_a']:.3f}/{Mt['cop_b']:.3f}, ratio {Mt['ratio']:.3f}", ""]
    L += ["Band ideal efficiency (31–35 °C flow, 1.8–2.6 kW):", ""]
    for i, M in ((a, MA), (b, MB)):
        L.append(f"- {i}: " + ", ".join(f"{k}: {v['eta']:.3f}" for k, v in sorted(M["band_eta"].items(), key=lambda x: int(x[0]))))
    L += ["", "## Sensor offset", ""]
    for i, M in ((a, MA), (b, MB)):
        o, c = M["sensor_offset"], M["offset_correction"]
        L.append(f"- {i}: mean {o['mean']:+.3f} K, median {o['median']:+.3f} K; corrected SPF all-flow {c['constant_all_flow']['spf']:.3f}, "
                 f"running {c['constant_running']['spf']:.3f}; trend {c['trend']['b']:+.4f} K/K → {c['trend']['at_space_running']:+.3f} K at space running, "
                 f"extrapolated SPF {c['extrapolated']['spf']:.3f} ({c['extrapolated']['heat_change']*100:+.1f}% heat)")
        L.append("  - by flowT median: " + ", ".join(f"{k}: {v['median']:+.3f}" for k, v in M["sensor_offset"]["by_flowT"].items()))
    L += ["", "## 10-second data", ""]
    for i, F in ((a, FA), (b, FB)):
        p, lv, ll = F["pump"], F["loop_volume"], F["loop_loss"]
        L.append(f"- {i}: pump {f2(p['pump_w_at_running_flow'],1)} W at {p['running_flowrate']:.1f} l/min "
                 f"(total {f2(p['total_w_at_running_flow'],1)} W, pump-off standby {p['standby_pump_off_w']:.1f} W)")
        L.append("  - loop: " + "; ".join(f"{k} {v['return_to_flow_s']} s × {v['flowrate']:.1f} l/min = {v['primary_litres']:.1f} L"
                                       for k, v in lv.items()))
        L.append(f"  - loop loss {ll['ua_low']:.2f}–{ll['ua_high']:.2f} W/K over lags "
                 + ", ".join(f"{k}s: {v['ua_w_per_k']:.2f} (quasi-steady n {v['quasi_steady_samples']})" for k, v in ll["by_lag_s"].items())
                 + f"; running loss {ll['loss_share_low']*100:.1f}–{ll['loss_share_high']*100:.1f}% of heat")
    L += ["", "## Between-cycle primary losses (per year)", ""]
    for i, M in ((a, MA), (b, MB)):
        c = M["between_cycles"]
        L.append(f"- {i}: overrun {c['overrun_hours']:.0f} h at {c['overrun_water_minus_outside']:.1f} K → {c['overrun_low']:.0f}–{c['overrun_high']:.0f} kWh "
                 f"(metered heat in overrun {c['overrun_metered_heat_kwh']:+.0f} kWh); pump off {c['pump_off_periods_per_day']:.2f}/day, "
                 f"median {f2(c['pump_off_median_min'], 0)} min → standing {c['standing_low']:.0f}–{c['standing_high']:.0f} kWh; "
                 f"space heat {c['space_heat_kwh']:.0f} kWh")
    L += ["", "## Implied air shortfall", ""]
    for name, X in (("raw", r["air_raw"]), ("adjusted low", r["air_adjusted"]["low"]), ("adjusted high", r["air_adjusted"]["high"])):
        L.append(f"- {name} (pump {X['pump_w']:.1f} W, ua {X['ua']:.2f} W/K): overall {X['overall']:.2f} K; "
                 f"day {', '.join(f'{k}:{v:.1f}' for k, v in X['day'].items())}; "
                 f"night {', '.join(f'{k}:{v:.1f}' for k, v in X['night'].items())}; "
                 f"output 4–8 °C {', '.join(f'{k}:{v:.1f}' for k, v in X['output_4_8'].items())}")
    Bu = r["budget"]
    L += ["", "## Space heating COP budget", "",
          f"- gap {Bu['gap']:.3f} ({Bu['cop_a']:.3f} vs {Bu['cop_b']:.3f})",
          f"- defrost {Bu['defrost']:.3f}",
          f"- pump {Bu['pump']['low']:.3f}–{Bu['pump']['high']:.3f} (pump-only diff {Bu['pump']['pump_diff_w']:.1f} W, total diff {Bu['pump']['total_diff_w']:.1f} W)",
          f"- pipework while running {Bu['pipework']['low']:.3f}–{Bu['pipework']['high']:.3f} (UA diff {Bu['pipework']['ua_diff_low']:.2f}–{Bu['pipework']['ua_diff_high']:.2f} W/K)",
          f"- pipework between cycles {Bu['pipework_between_cycles']['low']:.3f}–{Bu['pipework_between_cycles']['high']:.3f} "
          f"({Bu['pipework_between_cycles']['extra_kwh_low']:.0f}–{Bu['pipework_between_cycles']['extra_kwh_high']:.0f} kWh/yr extra)",
          f"- remainder {Bu['remainder']['low']:.3f}–{Bu['remainder']['high']:.3f}", ""]
    return "\n".join(L)


def run_fleet(end, refresh=False):
    end_ts = _date(end) + 12 * 3600
    systems = api.system_list(refresh)
    ids = fleet.select_ids(systems)
    f = fleet.scan(ids, systems, end_ts, refresh=refresh)
    res = dict(end=end, candidates=len(ids), systems=len(f),
               etaS=dict(min=float(f.etaS.min()), median=float(f.etaS.median()), max=float(f.etaS.max())),
               pairs=fleet.similar_pairs(f), correlations=fleet.correlations(f))
    os.makedirs(OUT, exist_ok=True)
    f.to_csv(os.path.join(OUT, "fleet_arotherm.csv"))
    c = res["correlations"]
    L = [f"# Arotherm fleet scan (reference year ending {end})", "",
         f"- candidates {res['candidates']}, analysed {res['systems']}, without cooling {c['n']}",
         f"- ideal efficiency space: min {res['etaS']['min']:.3f}, median {res['etaS']['median']:.3f}, max {res['etaS']['max']:.3f}", "",
         "## Most different similar pairs", ""]
    for p in res["pairs"]:
        L.append(f"- {p['a']} vs {p['b']} ({p['kw']:g} kW): Δeta {p['d_etaS']:.3f}, eta {p['etaS'][0]:.3f}/{p['etaS'][1]:.3f}, "
                 f"SPF {p['spf'][0]:.2f}/{p['spf'][1]:.2f}, heat {p['heat'][0]:.0f}/{p['heat'][1]:.0f}, "
                 f"dhw {p['dhw_share'][0]:.3f}/{p['dhw_share'][1]:.3f}, standby {p['standby_share'][0]:.3f}/{p['standby_share'][1]:.3f}")
    L += ["", "## Correlation with space ideal efficiency", ""]
    L += [f"- {k}: n {v['n']} pearson {v['pearson']:+.2f} spearman {v['spearman']:+.2f}" for k, v in c["overall"].items()]
    for kw, v in c["by_kw"].items():
        L.append(f"- {kw:g} kW: " + "; ".join(f"{k} r {x['pearson']:+.2f} rho {x['spearman']:+.2f}" for k, x in v.items() if k != "quintiles")
                 + f"; quintiles {v['quintiles']}")
    for col, q in c["quartiles"].items():
        L.append(f"- quartiles of {col}: " + "; ".join(f"{x['range']} n {x['n']:.0f} eta {x['etaS']:.3f} resid sd {x['resid_std']:.3f}" for x in q))
    reg = c["regression"]
    L.append(f"- regression (n {reg['n']}, R² {reg['r2']:.2f}): " + "; ".join(f"{k} {v['b']:+.4f} (t {v['t']:+.1f})" for k, v in reg["coef"].items()))
    _save("fleet_arotherm", res, "\n".join(L) + "\n")
    return res


def main():
    ap = argparse.ArgumentParser(prog="comparison")
    sub = ap.add_subparsers(dest="cmd", required=True)
    p = sub.add_parser("pair", help="compare two systems")
    p.add_argument("a", type=int)
    p.add_argument("b", type=int)
    p.add_argument("--end", required=True, help="last day of the reference year, YYYY-MM-DD")
    p.add_argument("--windows", default="2026-01-01,2026-01-15,2026-10-12,2026-10-26",
                   help="start dates of the 7-day 10-second windows")
    p.add_argument("--refresh", action="store_true", help="re-download instead of using the cache")
    q = sub.add_parser("fleet", help="scan Vaillant Arotherm systems")
    q.add_argument("--end", required=True)
    q.add_argument("--refresh", action="store_true")
    args = ap.parse_args()
    if args.cmd == "pair":
        run_pair(args.a, args.b, args.end, args.windows.split(","), args.refresh)
    else:
        run_fleet(args.end, args.refresh)


if __name__ == "__main__":
    main()
