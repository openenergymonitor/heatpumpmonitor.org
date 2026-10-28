#!/usr/bin/env python3
"""
Fast fleet aggregator: numpy reimplementation of the per-system loop in
scripts/aggregate_v2_last7.php.

The PHP planner (aggregate_fast.php) resolves the system list, app configs and
feed metadata and writes a plan.json. This script reads the feed data, builds
the aggregate series and writes them to --out as float64 arrays.

Data is read the way emoncms Feed::get_data($id,$start,$end,60,1,"UTC","notime",
false,0,0,false,0) returns it:
  - PHPFina, feed interval < 60:  mean of the non-NaN points in each minute
  - PHPFina, feed interval >= 60: the point at the start of each minute
  - PHPTimeSeries: PHPTimeSeries::get_data_combined averaging, quirks included
  - virtual feeds made of the simple processes in VIRTUAL_PROCESSES are
    evaluated here; anything else is pre-rendered by the planner through
    emoncms itself (engine "file")
  - values are then rounded to 0 dp, half away from zero as PHP round()

Every feed is read once over the whole requested range, and the range is cut
into windows (default 7 days) for cooling detection and the elec feed range
check, so a multi-year reprocess gives the same result as running the original
script on each consecutive 7-day window.

Systems can belong to several groups (e.g. all systems and MID metered only);
each feed is still read once and the sums are kept per group.

  python3 fast_aggregate.py plan.json --out DIR [--workers N]
"""
import argparse, json, os, sys, time
from concurrent.futures import ProcessPoolExecutor, as_completed
import numpy as np

INTERVAL = 60

# id_num => name for processes evaluated here (Modules/process/process_processlist.php)
VIRTUAL_PROCESSES = {
    "53": "source_feed_data_time", "55": "add_source_feed", "56": "sub_source_feed",
    "57": "multiply_by_source_feed", "58": "divide_by_source_feed",
    "2": "scale", "3": "offset", "24": "allowpositive", "25": "allownegative", "63": "abs_value",
}

SERIES = ["elec", "heat", "elec_kw_nrml", "heat_kw_nrml", "capacity", "outsideT", "numsys"]


def php_round(x):
    """PHP round($x, 0): half away from zero."""
    t = np.trunc(x)
    return t + np.copysign((np.abs(x - t) >= 0.5).astype(np.float64), x)


def phpfina_meta(prefix):
    with open(prefix + ".meta", "rb") as f:
        f.seek(8)
        b = f.read(8)
    feed_interval, start_time = np.frombuffer(b.ljust(8, b"\0"), dtype="<u4")
    try:
        npoints = os.path.getsize(prefix + ".dat") // 4
    except FileNotFoundError:
        npoints = 0
    return int(feed_interval), int(start_time), int(npoints)


def read_phpfina(prefix, start, n):
    """PHPFina::get_data_combined(average=1, limitinterval=0) at start + k*60, k < n."""
    out = np.full(n, np.nan)
    try:
        feed_interval, st, npoints = phpfina_meta(prefix)
    except FileNotFoundError:
        return out
    if npoints == 0 or feed_interval == 0:
        return out

    t = start + INTERVAL * np.arange(n, dtype=np.int64)
    pos_start = np.floor_divide(t - st, feed_interval)

    if INTERVAL <= feed_interval:
        # point read at the start of each minute, only within [start_time, end_time]
        ok = (t >= st) & (t <= st + feed_interval * (npoints - 1))
        if not ok.any():
            return out
        lo, hi = int(pos_start[ok].min()), int(pos_start[ok].max()) + 1
        raw = np.fromfile(prefix + ".dat", dtype="<f4", count=hi - lo, offset=4 * lo)
        idx = pos_start[ok] - lo
        good = idx < len(raw)
        vals = np.full(ok.sum(), np.nan)
        vals[good] = raw[idx[good]]
        out[ok] = vals
        return out

    # average of the points in [pos_start, pos_end), clamped to the data
    pos_end = np.floor_divide(t + INTERVAL - st, feed_interval)
    ps = np.clip(pos_start, 0, npoints)
    pe = np.clip(pos_end, 0, npoints)
    lo, hi = int(ps[0]), int(pe[-1])
    if hi <= lo:
        return out
    raw = np.fromfile(prefix + ".dat", dtype="<f4", count=hi - lo, offset=4 * lo).astype(np.float64)
    hi = lo + len(raw)
    ps = np.clip(ps, lo, hi) - lo
    pe = np.clip(pe, lo, hi) - lo
    valid = ~np.isnan(raw)
    cnt_cum = np.concatenate(([0], np.cumsum(valid)))
    cnt = cnt_cum[pe] - cnt_cum[ps]
    has = cnt > 0
    if not has.any():
        return out
    # segments are contiguous (pe[k] == ps[k+1]), so reduceat sums each one in order;
    # the trailing 0 keeps ps == len(raw) a valid index, empty segments are masked by cnt
    sums = np.add.reduceat(np.append(np.where(valid, raw, 0.0), 0.0), ps)
    out[has] = sums[has] / cnt[has]
    return out


def phptimeseries_search(times, q):
    """PHPTimeSeries::binarysearch() for every time in q at once, step for step
    (30 iterations, early exit on an exact match). Index or -1."""
    npoints = len(times)
    result = np.full(len(q), -1, dtype=np.int64)
    if npoints == 0:
        return result
    lo = np.full(len(q), -1, dtype=np.int64)
    hi = np.full(len(q), npoints - 1, dtype=np.int64)
    active = np.ones(len(q), dtype=bool)
    for _ in range(30):
        i = np.flatnonzero(active)
        if not len(i):
            break
        mid = lo[i] + (hi[i] - lo[i] + 1) // 2
        t = times[mid]
        found = t == q[i]
        last = ~found & (hi[i] - lo[i] == 1)
        result[i[found]] = mid[found]
        above = last & (t > q[i])
        result[i[above]] = mid[above]
        active[i[found | last]] = False
        step = ~found & ~last
        right = step & (q[i] > t)
        lo[i[right]] = mid[right]
        hi[i[step & ~right]] = mid[step & ~right]
    return result


def read_phptimeseries(path, start, n, restarts=()):
    """PHPTimeSeries::get_data_combined(average=1) at start + k*60, k < n.
    The first minute of each request can include the datapoint before it, so a
    request per window differs from one long one: restarts are the indices
    where a new request would begin."""
    out = np.full(n, np.nan)
    try:
        rec = np.fromfile(path, dtype=np.dtype([("flag", "u1"), ("time", "<u4"), ("value", "<f4")]))
    except FileNotFoundError:
        return out
    npoints = len(rec)
    if npoints == 0:
        return out
    times = rec["time"].astype(np.int64)

    div_end = start + INTERVAL * (np.arange(n, dtype=np.int64) + 1)
    first = phptimeseries_search(times, np.array([start], dtype=np.int64))[0]
    nxt = phptimeseries_search(times, div_end)
    # past the last datapoint: read to the end; landed after div_end: step back one
    past = nxt == -1
    over = ~past & (times[np.maximum(nxt, 0)] > div_end)
    nxt = np.where(past, npoints, np.where(over, np.maximum(nxt - 1, 0), nxt))

    seg_start = np.concatenate(([npoints if first == -1 else first], nxt[:-1]))
    restarts = np.asarray([k for k in restarts if 0 < k < n], dtype=np.int64)
    if len(restarts):
        found = phptimeseries_search(times, start + INTERVAL * restarts)
        seg_start[restarts] = np.where(found == -1, npoints, found)
    seg_end = nxt
    values = rec["value"].astype(np.float64)
    valid = ~np.isnan(values)
    # [start_0, end_0, start_1, end_1, ...]: even reduceat positions are the segment sums
    idx = np.empty(2 * n, dtype=np.int64)
    idx[0::2] = seg_start
    idx[1::2] = seg_end
    sums = np.add.reduceat(np.append(np.where(valid, values, 0.0), 0.0), idx)[0::2]
    cnt = np.add.reduceat(np.append(valid.astype(np.int64), 0), idx)[0::2]
    has = (seg_end > seg_start) & (cnt > 0)
    out[has] = sums[has] / cnt[has]
    return out


class FeedReader:
    def __init__(self, plan):
        self.feeds = plan["feeds"]
        self.datadir = plan.get("phpfina_dir", "")
        self.timeseries_dir = plan.get("phptimeseries_dir", "")
        self.start = plan["start"]
        self.n = (plan["end"] - plan["start"]) // INTERVAL + 1   # get_data includes the end point
        window = plan.get("window", plan["end"] - plan["start"])
        self.restarts = range(window // INTERVAL, self.n, window // INTERVAL)

    def raw(self, feedid):
        """Feed values as get_data returns them before dp rounding, NaN = null."""
        f = self.feeds.get(str(feedid))
        if f is None:
            return np.full(self.n, np.nan)
        if f["engine"] == 5:
            return read_phpfina(os.path.join(self.datadir, str(feedid)), self.start, self.n)
        if f["engine"] == 2:
            return read_phptimeseries(os.path.join(self.timeseries_dir, f"feed_{feedid}.MYD"), self.start, self.n, self.restarts)
        if f["engine"] == 7:
            return self.virtual(f["processList"])
        if f["engine"] == "file":
            return np.fromfile(f["path"], dtype="<f8", count=self.n)
        return np.full(self.n, np.nan)

    def virtual(self, process_list):
        value = np.full(self.n, np.nan)
        cache = {}
        for pair in process_list.split(","):
            key, _, arg = pair.partition(":")
            key = VIRTUAL_PROCESSES.get(key, key.replace("process__", ""))
            if key.endswith("source_feed") or key == "source_feed_data_time":
                fid = int(arg)
                if fid not in cache:
                    # sources are read with dp=-1 (no rounding); the planner only
                    # accepts PHPFina and PHPTimeSeries sources
                    cache[fid] = self.raw(fid)
                src = cache[fid]
                if key == "source_feed_data_time":
                    value = src.copy()
                elif key == "add_source_feed":
                    value = value + src
                elif key == "sub_source_feed":
                    value = value - src
                elif key == "multiply_by_source_feed":
                    value = value * src
                elif key == "divide_by_source_feed":
                    with np.errstate(divide="ignore", invalid="ignore"):
                        value = np.where(src != 0, value / src, np.nan)
            elif key == "scale":
                value = value * float(arg)
            elif key == "offset":
                value = value + float(arg)
            elif key == "allowpositive":
                value = np.where(value < 0, 0.0, value)
            elif key == "allownegative":
                value = np.where(value > 0, 0.0, value)
            elif key == "abs_value":
                value = np.abs(value)
            else:
                raise ValueError("unsupported virtual process " + pair)
        return value

    def get_data(self, feedid, dp):
        v = self.raw(feedid)
        if self.feeds.get(str(feedid), {}).get("engine") == "file":
            return v   # rendered by emoncms, already rounded
        return php_round(v) if dp == 0 else (np.round(v, dp) if dp > 0 else v)


def cooling_flags(heat):
    """auto_detect_cooling() from aggregate_v2_last7.php. heat has NaN for null.
    Returns a boolean array, or None when the window is predominantly heating."""
    notnull = ~np.isnan(heat)
    heat_kwh = np.sum(np.where(notnull & (heat >= 0), heat, 0.0)) * INTERVAL / 3600
    cool_kwh = np.sum(np.where(notnull & (heat < 0), -heat, 0.0)) * INTERVAL / 3600
    if heat_kwh > cool_kwh * 4:
        return None
    neg = notnull & (heat < 0)
    flags = neg.copy()
    # runs of negative values no longer than 300 s are cleared, unless the run
    # reaches the end of the window (the original only clears on the next point)
    d = np.diff(np.concatenate(([0], neg.astype(np.int8), [0])))
    run_start = np.flatnonzero(d == 1)
    run_end = np.flatnonzero(d == -1)
    for s, e in zip(run_start, run_end):
        if (e - s) * INTERVAL <= 300 and e < len(heat):
            flags[s:e] = False
    return flags


ACC_KEYS = ("elec", "heat", "numsys", "elec_kw", "heat_kw", "capacity", "nkw", "out_sum", "out_n")


def process_systems(args):
    plan, systems = args
    reader = FeedReader(plan)
    start, end = plan["start"], plan["end"]
    window = plan.get("window", end - start)
    dp = plan.get("dp", 0)
    lim = plan["limits"]
    N = (end - start) // INTERVAL

    # one set of sums per group (dataset); each system is read once and added
    # to every group it belongs to
    accs = {g: {k: np.zeros(N) for k in ACC_KEYS} for g in plan["groups"]}
    flagged = {}
    windows = [(ws, min(ws + window, end)) for ws in range(start, end, window)]

    for sysrow in systems:
        hp_output = float(sysrow.get("hp_output") or 0)
        skip_window = [False] * len(windows)
        sys_accs = [accs[g] for g in sysrow["groups"]]

        if sysrow.get("elec") is not None and sysrow.get("heat") is not None:
            em = sysrow.get("elec_meta") or {}
            m_start, m_end = em.get("start_time"), em.get("end_time")
            # $meta->start_time > $end || $meta->end_time < $start => continue
            # (missing end_time behaves as null < $start, i.e. true)
            for w, (ws, we) in enumerate(windows):
                skip_window[w] = (m_start is not None and m_start > we) or m_end is None or m_end < ws

            if not all(skip_window):
                elec = reader.get_data(sysrow["elec"], dp)
                heat = reader.get_data(sysrow["heat"], dp)
                for w, (ws, we) in enumerate(windows):
                    if skip_window[w]:
                        continue
                    a = (ws - start) // INTERVAL
                    b = (we - start) // INTERVAL
                    e, h = elec[a:b], heat[a:b].copy()
                    valid = ~np.isnan(e) & ~np.isnan(h)
                    bad = valid & ((np.abs(h) > lim["heat_max"]) | (np.abs(e) > lim["elec_max"]))
                    good = valid & ~bad
                    if bad.any():
                        bi = np.flatnonzero(bad)
                        f = flagged.setdefault(sysrow["id"], {"location": sysrow.get("location"), "userid": sysrow.get("userid"),
                                                              "app_id": sysrow.get("app_id"), "count": 0, "first": None,
                                                              "last": 0, "max_heat": 0.0, "max_elec": 0.0})
                        f["count"] += len(bi)
                        if f["first"] is None: f["first"] = int(ws + bi[0] * INTERVAL)
                        f["last"] = int(ws + bi[-1] * INTERVAL)
                        f["max_heat"] = max(f["max_heat"], float(np.abs(h[bi]).max()))
                        f["max_elec"] = max(f["max_elec"], float(np.abs(e[bi]).max()))

                    cool = cooling_flags(heat[a:b + 1])   # includes get_data's extra end point
                    if cool is not None:
                        h[cool[:b - a]] *= -1

                    sl = slice(a, b)
                    add = {"elec": np.where(good, e, 0.0), "heat": np.where(good, h, 0.0), "numsys": good}
                    if hp_output > 0:
                        add.update({"elec_kw": np.where(good, e / hp_output, 0.0), "heat_kw": np.where(good, h / hp_output, 0.0),
                                    "capacity": np.where(good, hp_output, 0.0), "nkw": good})
                    for acc in sys_accs:
                        for k, v in add.items():
                            acc[k][sl] += v

        if sysrow.get("outsideT") is not None:
            if all(skip_window):
                continue
            o = reader.get_data(sysrow["outsideT"], dp)[:N]
            ok = ~np.isnan(o) & (o >= lim["outsideT_min"]) & (o <= lim["outsideT_max"])
            for w, (ws, we) in enumerate(windows):
                if skip_window[w]:
                    ok[(ws - start) // INTERVAL:(we - start) // INTERVAL] = False
            o_add = np.where(ok, o, 0.0)
            for acc in sys_accs:
                acc["out_sum"] += o_add
                acc["out_n"] += ok

    return accs, flagged


def run(plan, workers):
    systems = plan["systems"]
    accs, flagged = None, {}

    def add(result):
        nonlocal accs
        a, f = result
        flagged.update(f)
        if accs is None:
            accs = a
        else:
            for g in accs:
                for k in ACC_KEYS: accs[g][k] += a[g][k]

    if workers > 1:
        # one interleaved chunk per worker: each returns full-length sums for every
        # group (hundreds of MB over years), so fewer chunks and adding each in as it
        # arrives keeps memory to a few copies
        nchunks = max(1, min(len(systems), workers))
        with ProcessPoolExecutor(workers) as ex:
            futures = [ex.submit(process_systems, (plan, systems[i::nchunks])) for i in range(nchunks)]
            for fut in as_completed(futures):
                add(fut.result())
                futures.remove(fut)
                del fut
    else:
        add(process_systems((plan, systems)))

    series = {}
    for g, acc in accs.items():
        nkw = np.maximum(1, acc["nkw"])
        with np.errstate(invalid="ignore", divide="ignore"):
            outsideT = np.where(acc["out_n"] > 0, acc["out_sum"] / acc["out_n"], np.nan)
        series[g] = {
            "elec": acc["elec"], "heat": acc["heat"],
            "elec_kw_nrml": acc["elec_kw"] * acc["capacity"] / nkw,
            "heat_kw_nrml": acc["heat_kw"] * acc["capacity"] / nkw,
            "capacity": acc["capacity"], "outsideT": outsideT, "numsys": acc["numsys"],
        }
    return series, flagged


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("plan")
    ap.add_argument("--out", required=True)
    ap.add_argument("--workers", type=int, default=os.cpu_count())
    args = ap.parse_args()

    t0 = time.time()
    with open(args.plan) as f:
        plan = json.load(f)
    series, flagged = run(plan, args.workers)

    # out/<group>/<series>.f64
    for g in series:
        os.makedirs(os.path.join(args.out, g), exist_ok=True)
        for k in SERIES:
            series[g][k].astype("<f8").tofile(os.path.join(args.out, g, k + ".f64"))
    counts = {g: sum(g in s["groups"] for s in plan["systems"]) for g in plan["groups"]}
    summary = {"start": plan["start"], "end": plan["end"], "interval": INTERVAL,
               "count_all": plan.get("count_all", counts),
               "flagged": {str(k): v for k, v in sorted(flagged.items())},
               "seconds": round(time.time() - t0, 3)}
    with open(os.path.join(args.out, "summary.json"), "w") as f:
        json.dump(summary, f)
    print(f"systems {len(plan['systems'])} ({', '.join(f'{g} {n}' for g, n in counts.items())})"
          f"  windows {-(-(plan['end'] - plan['start']) // plan.get('window', plan['end'] - plan['start']))}"
          f"  flagged {len(flagged)}  {summary['seconds']:.2f}s", file=sys.stderr)


if __name__ == "__main__":
    main()
