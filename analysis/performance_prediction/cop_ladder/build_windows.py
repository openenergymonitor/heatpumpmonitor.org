#!/usr/bin/env python3
"""Build 15-minute window features for the COP ladder test, reading feeds directly
on the server (PHPFina), with the public API as a fallback for other feed engines.

Usage (from analysis/performance_prediction/):
    php cop_ladder/feed_map.php > cop_ladder/feed_map.json
    .venv/bin/python cop_ladder/build_windows.py --export heatpumpmonitor_all_fields_last365_2026-11-16.csv \
        --map cop_ladder/feed_map.json --end 2026-11-15

Writes one pickle per system to cop_ladder/windows_<step>s/ and a log of skipped systems.

The features approximate the colleague's Stage 0-6 set from the formulas in their findings
document (their code isn't available). Electricity is used only for the running flag and
the COP target, as in theirs. --step 120 averages to 2-minute data, to match EoH resolution.
"""
import argparse
import json
import os
import sys
import time
import urllib.parse
import urllib.request
from concurrent.futures import ProcessPoolExecutor
from pathlib import Path

import numpy as np
import pandas as pd

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE.parent / "scripts"))
import hpmon_analysis as h  # noqa: E402  (standard fleet filter)

BASE = os.environ.get("HPMON_URL", "https://heatpumpmonitor.org")
GRID = 30          # read grid, seconds (as the API's interval=30&average=1)
WIN = 900          # window length, seconds
FEEDS = {"heatpump_elec": "elec", "heatpump_heat": "heat", "heatpump_flowT": "flowT",
         "heatpump_returnT": "returnT", "heatpump_outsideT": "outsideT", "heatpump_dhw": "dhw"}
REQUIRED = ["heatpump_elec", "heatpump_heat", "heatpump_flowT", "heatpump_returnT", "heatpump_outsideT"]
TCRIT = {"R290": 96.74, "R32": 78.11, "R410A": 71.35, "R454C": 85.7, "R452B": 77.1, "R407C": 86.0}


# --- PHPFina reader, copied from analysis/dhw_detection/dhw_detection/phpfina.py (that package imports torch) ---

def phpfina_meta(folder, feedid):
    folder = Path(folder)
    try:
        with open(folder / f"{feedid}.meta", "rb") as f:
            head = f.read(16)
        npoints = os.path.getsize(folder / f"{feedid}.dat") // 4
    except OSError:
        return None
    if len(head) < 16:
        return None
    interval, start_time = (int(v) for v in np.frombuffer(head[8:16], "<u4"))
    if not interval or not start_time or not npoints:
        return None
    return dict(interval=interval, start_time=start_time, npoints=npoints,
                end_time=start_time + interval * (npoints - 1))


def phpfina_values(folder, feedid, m, times):
    """Values at the 30 s steps `times`, as the API's interval=30&average=1 returns them."""
    mi, st, n = m["interval"], m["start_time"], m["npoints"]
    data = np.memmap(Path(folder) / f"{feedid}.dat", dtype="<f4", mode="r", shape=(n,))
    if mi < GRID:
        a = np.clip((times - st) // mi, 0, n)
        b = np.clip((times + GRID - st) // mi, 0, n)
        lo, hi = int(a.min()), int(b.max())
        block = np.asarray(data[lo:hi], np.float64)
        total, count = np.zeros(len(times)), np.zeros(len(times))
        for k in range(int((b - a).max(initial=0))):
            pos = a + k
            inside = pos < b
            v = block[np.where(inside, pos, lo) - lo] if hi > lo else np.full(len(times), np.nan)
            ok = inside & ~np.isnan(v)
            total += np.where(ok, v, 0)
            count += ok
        with np.errstate(invalid="ignore", divide="ignore"):
            return np.where(count > 0, total / count, np.nan)
    out = np.full(len(times), np.nan)
    inside = (times >= st) & (times <= m["end_time"])
    out[inside] = data[(times[inside] - st) // mi]
    return out


def api_values(sysid, feeds, start, end):
    """Fallback: 30 s averages from the public API, in 28-day chunks."""
    cols = {f: [] for f in feeds}
    idx = []
    for a in range(start, end, 28 * 86400):
        b = min(a + 28 * 86400, end)
        q = urllib.parse.urlencode({"id": sysid, "feeds": ",".join(feeds), "start": a, "end": b,
                                    "interval": GRID, "average": 1, "timeformat": "unix"})
        for attempt in range(4):
            try:
                req = urllib.request.Request(f"{BASE}/timeseries/data?{q}", headers={"User-Agent": "hpmon-cop-ladder"})
                with urllib.request.urlopen(req, timeout=300) as r:
                    d = json.loads(r.read())
                break
            except Exception:
                if attempt == 3:
                    raise
                time.sleep(3 * (attempt + 1))
        if not isinstance(d, dict) or d.get("success") is False:
            raise RuntimeError(f"API: {d}")
        t = None
        for f in feeds:
            pts = d.get(f, [])
            if t is None:
                t = [p[0] for p in pts]
            cols[f].extend(np.nan if p[1] is None else p[1] for p in pts)
        idx.extend(t or [])
    df = pd.DataFrame({f: np.asarray(v, float) for f, v in cols.items()}, index=np.asarray(idx, np.int64))
    return df[~df.index.duplicated()]


def load(sysid, feeds, datadir, start, end):
    names = [k for k in FEEDS if k in feeds]
    times = np.arange(start, end, GRID, dtype=np.int64)
    if all(feeds[k]["engine"] == 5 for k in names):
        cols = {}
        for k in names:
            m = phpfina_meta(datadir, feeds[k]["id"])
            cols[FEEDS[k]] = phpfina_values(datadir, feeds[k]["id"], m, times) if m else np.full(len(times), np.nan)
        return pd.DataFrame(cols, index=times), "phpfina"
    d = api_values(sysid, names, start, end).reindex(times)
    return d.rename(columns=FEEDS), "api"


# --- features ---

def resample(d, step):
    if step == GRID:
        return d
    k = step // GRID
    grp = (d.index - d.index[0]) // step
    out = d.groupby(grp).mean()
    out.index = d.index[0] + out.index * step
    # dhw flag: any hot water in the step
    if "dhw" in d:
        out["dhw"] = d.dhw.groupby(grp).max().to_numpy()
    return out


def build(sysid, d, m, step):
    cap = float(m.hp_output) if pd.notna(m.hp_output) and m.hp_output > 0 else np.nan
    t = d.index.to_numpy()
    elec = d.elec.to_numpy(float)
    heat = d.heat.to_numpy(float)
    # running: their definition was elec > 300 W; lower for small units
    thr = 300.0 if not np.isfinite(cap) or cap >= 6 else 200.0
    running = np.nan_to_num(elec) > thr
    defrost = running & (np.nan_to_num(heat) < -50)
    has_dhw = "dhw" in d and d.dhw.notna().sum() > 0.5 * len(d)
    dhw = (np.nan_to_num(d.dhw.to_numpy(float)) > 0) if has_dhw else np.zeros(len(d), bool)
    ts = pd.to_datetime(t, unit="s")

    start = running & ~np.r_[False, running[:-1]]
    run_start_t = pd.Series(np.where(start, t, np.nan)).ffill().to_numpy()
    mins_into_run = np.where(running, (t - run_start_t + step / 2) / 60, np.nan)
    starts_24h = pd.Series(start.astype(float), index=ts).rolling("24h").sum().to_numpy()
    last_def = pd.Series(np.where(defrost, t, np.nan)).ffill().to_numpy()
    mins_since_defrost = np.minimum(np.nan_to_num((t - last_def) / 60, nan=1440), 1440)
    def_start = defrost & ~np.r_[False, defrost[:-1]]
    defrost_6h = pd.Series(def_start.astype(float), index=ts).rolling("6h").sum().to_numpy()

    valid = (np.isfinite(elec) & np.isfinite(heat) & d.flowT.notna().to_numpy()
             & d.returnT.notna().to_numpy() & d.outsideT.notna().to_numpy())
    s = pd.DataFrame({"elec": elec, "heat": heat, "flow_t": d.flowT.to_numpy(), "return_t": d.returnT.to_numpy(),
                      "outside_t": d.outsideT.to_numpy(), "running": running, "defrost": defrost, "dhw": dhw,
                      "mins_into_run": mins_into_run, "starts_24h": starts_24h,
                      "mins_since_defrost": mins_since_defrost, "defrost_6h": defrost_6h, "valid": valid}, index=t)
    s["win"] = (s.index // WIN) * WIN
    per_win = WIN // step
    w = s.groupby("win").agg(
        elec=("elec", "sum"), heat=("heat", "sum"), n=("elec", "size"), n_valid=("valid", "sum"),
        n_run=("running", "sum"), n_def=("defrost", "sum"), n_dhw=("dhw", "sum"),
        flow_t=("flow_t", "mean"), return_t=("return_t", "mean"), outside_t=("outside_t", "mean"),
        flow_first=("flow_t", "first"), flow_last=("flow_t", "last"),
        out_first=("outside_t", "first"), out_last=("outside_t", "last"),
        mins_into_run=("mins_into_run", "mean"), starts_24h=("starts_24h", "last"),
        mins_since_defrost=("mins_since_defrost", "last"), defrost_6h=("defrost_6h", "last"))
    w = w[(w.n >= per_win - 1) & (w.n_valid == w.n) & (w.n_run >= 1)].copy()
    w["clean"] = (w.n_run == w.n) & (w.n_def == 0) & (w.n_dhw == 0)
    w["space"] = w.n_dhw == 0
    w["elec_kwh"] = w.elec * step / 3.6e6
    w["heat_kwh"] = w.heat * step / 3.6e6
    w["cop"] = w.heat_kwh / w.elec_kwh
    hours = w.n * step / 3600
    q_kw = w.heat_kwh / hours

    w["lift"] = w.flow_t - w.outside_t
    tc, te = w.flow_t + 273.15, w.outside_t + 273.15
    w["cop_carnot_formula"] = tc / (tc - te).clip(lower=3)
    tc2, te2 = w.flow_t + 2 + 273.15, w.outside_t - 6 + 273.15
    w["cop_carnot_offset"] = tc2 / (tc2 - te2).clip(lower=3)
    w["dT_water"] = w.flow_t - w.return_t
    w["dflow_dt"] = (w.flow_last - w.flow_first) / (hours * 60)
    w["implied_flow_lpm"] = q_kw * 1000 / (4186 * w.dT_water.where(w.dT_water.abs() > 0.2)) * 60
    w["dT_out_in_win"] = w.out_last - w.out_first
    tm = (w.flow_t + w.return_t) / 2
    w["cop_lorentz_formula"] = (tm + 273.15) / (tm - w.outside_t).clip(lower=3)
    w["size_kw"] = cap
    w["load_ratio"] = q_kw / cap
    w["est_evap_load_ratio"] = q_kw * (1 - 1 / w.cop_carnot_offset.clip(lower=1.5)) / cap
    w["pipe_loss_proxy"] = ((tm - w.outside_t) / q_kw.where(q_kw > 0.2)).clip(upper=100)
    w["flow_per_kw"] = w.implied_flow_lpm / cap
    ref = str(m.refrigerant) if pd.notna(m.refrigerant) else "unknown"
    w["refrigerant"] = ref
    w["T_crit_margin"] = TCRIT.get(ref, np.nan) - w.flow_t
    w["manufacturer_id"] = m.manufacturer_id
    w["model"] = f"{m.hp_model}|{m.hp_output}"
    w["system_id"] = sysid
    w["has_dhw_flag"] = has_dhw
    keep = ["system_id", "cop", "heat_kwh", "elec_kwh", "clean", "space", "n", "n_def", "flow_t", "return_t",
            "outside_t", "lift", "cop_carnot_formula", "cop_carnot_offset", "cop_lorentz_formula", "dT_water",
            "dflow_dt", "implied_flow_lpm", "dT_out_in_win", "size_kw", "load_ratio", "est_evap_load_ratio",
            "pipe_loss_proxy", "flow_per_kw", "refrigerant", "T_crit_margin", "manufacturer_id", "model",
            "mins_into_run", "starts_24h", "mins_since_defrost", "defrost_6h", "has_dhw_flag"]
    w = w[keep]
    w.index.name = "t"
    return w


def job(args):
    sysid, feeds, datadir, start, end, step, meta_row, out = args
    if out.exists():
        return sysid, "cached"
    missing = [k for k in REQUIRED if k not in feeds]
    if missing:
        return sysid, f"skip: no {','.join(missing)}"
    try:
        d, src = load(sysid, feeds, datadir, start, end)
        if d.elec.notna().sum() < 10000:
            return sysid, f"skip: little data ({src})"
        w = build(sysid, resample(d, step), meta_row, step)
        w.to_pickle(out)
        return sysid, f"{src}: {len(w)} windows, {int(w.clean.sum())} clean"
    except Exception as e:
        return sysid, f"error: {e!r}"


def check(fmap, sysid, days=2):
    """Compare the direct PHPFina read with the public API for one system over recent days."""
    feeds = fmap["systems"][str(sysid)]
    end = int(time.time()) // 86400 * 86400 - 86400
    start = end - days * 86400
    d, src = load(sysid, feeds, fmap["datadir"], start, end)
    a = api_values(sysid, [k for k in FEEDS if k in feeds], start, end).rename(columns=FEEDS).reindex(d.index)
    print(f"system {sysid}: read via {src}, {len(d)} steps from {pd.Timestamp(start, unit='s')}")
    for c in d.columns:
        both = d[c].notna() & a[c].notna()
        diff = float(np.abs(d[c][both] - a[c][both]).max()) if both.any() else float("nan")
        print(f"  {c:9s} steps compared {int(both.sum()):6d}  max abs difference {diff:.6g}")


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--export", help="all-fields CSV (last 365 days)")
    ap.add_argument("--map", required=True, help="feed_map.json from feed_map.php")
    ap.add_argument("--end", help="end date of the year, YYYY-MM-DD (UTC midnight)")
    ap.add_argument("--check", type=int, nargs="+", metavar="ID",
                    help="compare the direct read with the public API for these systems, then stop")
    ap.add_argument("--days", type=int, default=365)
    ap.add_argument("--step", type=int, default=30, choices=[30, 60, 120])
    ap.add_argument("--workers", type=int, default=6)
    args = ap.parse_args()
    fmap = json.loads(Path(args.map).read_text())
    if args.check:
        for i in args.check:
            check(fmap, i)
        return
    if not args.export or not args.end:
        ap.error("--export and --end are required")

    end = int(pd.Timestamp(args.end, tz="UTC").timestamp())
    start = end - args.days * 86400
    meta = pd.read_csv(args.export)
    df = h.standard_filter(meta)
    df = df[df.weighted_flowT_minus_outsideT.notna() & (pd.to_numeric(df.combined_cop, errors="coerce") > 0)]
    meta = meta.set_index("id")
    outdir = HERE / f"windows_{args.step}s"
    outdir.mkdir(exist_ok=True)
    jobs = [(int(i), fmap["systems"].get(str(int(i)), {}), fmap["datadir"], start, end, args.step,
             meta.loc[int(i)], outdir / f"{int(i)}.pkl") for i in sorted(df.id)]
    print(f"{len(jobs)} systems, {pd.Timestamp(start, unit='s')} to {pd.Timestamp(end, unit='s')}, step {args.step}s",
          flush=True)
    log = []
    with ProcessPoolExecutor(args.workers) as ex:
        for n, (sysid, msg) in enumerate(ex.map(job, jobs), 1):
            print(n, sysid, msg, flush=True)
            log.append({"system_id": sysid, "result": msg})
    pd.DataFrame(log).to_csv(outdir / "build_log.csv", index=False)


if __name__ == "__main__":
    main()
