#!/usr/bin/env python3
"""
Generate a synthetic heatpumpmonitor fleet for benchmarking the aggregator.

Writes, into --out:
  phpfina/<id>.meta, <id>.dat   PHPFina feeds (float32, NaN = missing)
  manifest.json                 system rows (as System::list_admin) + app configs + feed table
  setup.sql                     creates the emoncms-like `feeds` and `app` tables in --db

The fleet deliberately includes the awkward cases the aggregator has to handle:
mixed feed intervals (5/10/30/60/120 s), feeds starting/ending inside the window
or outside it, gaps, defrost blips, cooling-dominated systems, x1000 scaled
feeds, bad outside temperature sensors, zero hp_output, virtual feeds
(source + add, source x scale), missing feeds and unshared/unpublished systems.

Usage:
  python3 generate.py --out DIR --systems 1000 --start 2026-10-14 --days 14
"""
import argparse, json, os, struct
from datetime import datetime, timezone
import numpy as np

FEED_BASE = 1_000_000          # synthetic feed ids, well clear of real local feeds
APP_BASE = 500_000
USER_BASE = 100_000


def write_phpfina(datadir, feedid, start_time, interval, values):
    with open(os.path.join(datadir, f"{feedid}.meta"), "wb") as f:
        f.write(struct.pack("<IIII", 0, 0, interval, start_time))
    values.astype("<f4").tofile(os.path.join(datadir, f"{feedid}.dat"))


def weather(t, rng_seed):
    """Shared regional outside temperature (deg C) at unix times t."""
    day = 86400.0
    r = np.random.default_rng(rng_seed)
    # slow synoptic variation: sum of a few random sinusoids, periods 2-10 days
    slow = np.zeros_like(t, dtype=np.float64)
    for _ in range(4):
        period = r.uniform(2, 10) * day
        slow += r.uniform(1, 2.5) * np.sin(2 * np.pi * t / period + r.uniform(0, 6.3))
    # seasonal: coldest mid-January
    season = 6.0 * np.cos(2 * np.pi * (t / day - 15) / 365.25)
    diurnal = 3.0 * np.sin(2 * np.pi * ((t % day) / day - 0.375))
    return 10.0 - season + slow + diurnal


def simulate_system(rng, t, hp_kw, cooling):
    """Return (elec_W, heat_W, outsideT) arrays at times t."""
    n = len(t)
    tout = weather(t, 1) + rng.normal(0, 0.8) + rng.normal(0, 0.15, n)

    if cooling:
        demand = np.clip((tout - 18.0) * hp_kw * 1000 / 15.0, 0, None) + 300
    else:
        demand = np.clip((20.0 - tout) * hp_kw * 1000 / 23.0, 0, None)

    min_out = 0.3 * hp_kw * 1000
    period = rng.uniform(1800, 4800)
    phase = rng.uniform(0, period)
    duty = np.clip(demand / min_out, 0, 1)
    on = ((t + phase) % period) / period < duty
    heat = np.where(on, np.maximum(demand, min_out), 0.0)

    # daily DHW run, ~45 min, higher output
    dhw_hour = rng.uniform(1, 5) * 3600
    dhw = ((t % 86400) >= dhw_hour) & ((t % 86400) < dhw_hour + 2700)
    heat = np.where(dhw, hp_kw * 1000 * 0.9, heat)

    cop = np.clip(2.2 + 0.11 * (tout + 5) - 0.8 * dhw, 1.4, 6.0)
    if cooling:
        cop = np.clip(4.0 - 0.08 * (tout - 20), 2.0, 5.0)
    elec = np.where(heat > 0, heat / cop, rng.uniform(8, 25))

    if cooling:
        heat = np.where(dhw, heat, -heat)
    else:
        # defrosts: short negative heat when cold and running
        dperiod = rng.uniform(2400, 4200)
        ddur = rng.uniform(120, 330)
        defrost = on & (tout < 5) & (((t + phase) % dperiod) < ddur)
        heat = np.where(defrost, -rng.uniform(1000, 3000), heat)
        elec = np.where(defrost, hp_kw * 1000 * 0.25, elec)

    noise = rng.normal(1, 0.04, n)
    return elec * noise, heat * noise, tout


def add_gaps(rng, arr, interval):
    n = len(arr)
    for _ in range(rng.poisson(3)):
        length = int(rng.choice([1, 5, 30, 600, 8640]) * 10 / interval) + 1
        s = rng.integers(0, max(1, n))
        arr[s:s + length] = np.nan


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--out", required=True)
    ap.add_argument("--systems", type=int, default=1000)
    ap.add_argument("--start", default="2026-10-14")
    ap.add_argument("--days", type=float, default=14)
    ap.add_argument("--seed", type=int, default=42)
    ap.add_argument("--db", default="hpmon_synth")
    args = ap.parse_args()

    rng = np.random.default_rng(args.seed)
    datadir = os.path.join(args.out, "phpfina")
    os.makedirs(datadir, exist_ok=True)
    os.makedirs(os.path.join(args.out, "phptimeseries"), exist_ok=True)

    t0 = int(datetime.strptime(args.start, "%Y-%m-%d").replace(tzinfo=timezone.utc).timestamp())
    t1 = t0 + int(args.days * 86400)

    systems, apps, feeds = [], {}, []
    stats = {"virtual": 0, "cooling": 0, "scaled": 0, "bad_outsideT": 0, "no_data_in_range": 0,
             "missing_feed": 0, "no_config": 0, "unlisted": 0, "bytes": 0}

    for i in range(1, args.systems + 1):
        base = FEED_BASE + i * 10
        elec_id, heat_id, out_id, src_a, src_b = base + 1, base + 2, base + 3, base + 4, base + 5
        app_id, userid = APP_BASE + i, USER_BASE + i

        hp_kw = float(rng.choice([4, 5, 6, 7, 8, 9, 10, 11, 12, 14, 16]))
        if rng.random() < 0.05: hp_kw = 0.0
        share = int(rng.random() > 0.03)
        published = int(rng.random() > 0.02)
        if not (share and published): stats["unlisted"] += 1
        systems.append({"id": i, "location": f"Synth{i}", "userid": userid, "app_id": app_id,
                        "share": share, "published": published, "hp_output": hp_kw})

        interval = int(rng.choice([5, 10, 30, 60, 120], p=[0.05, 0.70, 0.05, 0.15, 0.05]))
        fstart, fend = t0, t1
        r = rng.random()
        if r < 0.10:   fstart = t0 + int(rng.uniform(0, args.days * 0.8) * 86400)   # new system
        elif r < 0.15: fend = t0 + int(rng.uniform(0.2, 1) * args.days * 86400)     # stopped reporting
        elif r < 0.17:                                                             # gone before window
            fstart, fend = t0 - 30 * 86400, t0 - 10 * 86400
            stats["no_data_in_range"] += 1
        # emoncms aligns start_time to the feed interval; some feeds are
        # misaligned to the minute (e.g. 10s feed starting at :05)
        fstart = (fstart // interval) * interval
        if interval < 60 and rng.random() < 0.3: fstart += interval * int(rng.integers(1, 60 // interval))
        t = np.arange(fstart, fend, interval, dtype=np.float64)

        cooling = rng.random() < 0.03
        stats["cooling"] += cooling
        elec, heat, tout = simulate_system(rng, t, hp_kw if hp_kw > 0 else 6.0, cooling)
        for a in (elec, heat, tout): add_gaps(rng, a, interval)
        if rng.random() < 0.02:
            stats["scaled"] += 1
            s = rng.integers(0, len(t)); heat[s:s + int(86400 / interval)] *= 1000
        if rng.random() < 0.02:
            stats["bad_outsideT"] += 1
            tout[rng.random(len(t)) < 0.01] = -127.0

        config = {}
        kind = rng.random()
        if kind < 0.03:
            # elec as virtual feed: indoor unit + outdoor unit
            stats["virtual"] += 1
            write_phpfina(datadir, src_a, fstart, interval, (elec * 0.12).astype(np.float32))
            write_phpfina(datadir, src_b, fstart, interval, (elec * 0.88).astype(np.float32))
            feeds += [(src_a, userid, 5, ""), (src_b, userid, 5, ""),
                      (elec_id, userid, 7, f"53:{src_a},55:{src_b}")]
            write_phpfina(datadir, heat_id, fstart, interval, heat.astype(np.float32))
            feeds.append((heat_id, userid, 5, ""))
        elif kind < 0.05:
            # heat as virtual feed: source in kW scaled x1000
            stats["virtual"] += 1
            write_phpfina(datadir, src_a, fstart, interval, (heat / 1000).astype(np.float32))
            feeds += [(src_a, userid, 5, ""), (heat_id, userid, 7, f"53:{src_a},2:1000")]
            write_phpfina(datadir, elec_id, fstart, interval, elec.astype(np.float32))
            feeds.append((elec_id, userid, 5, ""))
        else:
            write_phpfina(datadir, elec_id, fstart, interval, elec.astype(np.float32))
            write_phpfina(datadir, heat_id, fstart, interval, heat.astype(np.float32))
            feeds += [(elec_id, userid, 5, ""), (heat_id, userid, 5, "")]

        config["heatpump_elec"] = str(elec_id)
        config["heatpump_heat"] = str(heat_id)
        if rng.random() < 0.85:
            # outside temp often logged at a coarser interval
            oi = int(rng.choice([10, 60, 300], p=[0.5, 0.3, 0.2]))
            ostart = (fstart // oi) * oi
            ot = np.arange(ostart, fend, oi, dtype=np.float64)
            write_phpfina(datadir, out_id, ostart, oi, np.interp(ot, t, tout).astype(np.float32))
            feeds.append((out_id, userid, 5, ""))
            config["heatpump_outsideT"] = str(out_id)

        r = rng.random()
        if r < 0.01:
            stats["missing_feed"] += 1
            config["heatpump_heat"] = str(base + 9)   # no such feed
        elif r < 0.02:
            stats["no_config"] += 1
            del config["heatpump_heat"]
        if rng.random() > 0.005:   # a few systems have no app row at all
            apps[app_id] = {"userid": userid, "config": config}

    for f in os.listdir(datadir):
        stats["bytes"] += os.path.getsize(os.path.join(datadir, f))

    with open(os.path.join(args.out, "manifest.json"), "w") as f:
        json.dump({"start": t0, "end": t1, "db": args.db, "systems": systems,
                   "apps": {str(k): v for k, v in apps.items()},
                   "feeds": [{"id": a, "userid": b, "engine": c, "processList": d} for a, b, c, d in feeds],
                   "stats": stats}, f)

    with open(os.path.join(args.out, "setup.sql"), "w") as f:
        db = args.db
        f.write(f"DROP DATABASE IF EXISTS `{db}`;\nCREATE DATABASE `{db}`;\nUSE `{db}`;\n")
        f.write("CREATE TABLE feeds LIKE emoncms.feeds;\nCREATE TABLE app LIKE emoncms.app;\n")
        for k in range(0, len(feeds), 500):
            rows = ",".join(f"({a},'synth{a}',{b},'synth',0,{c},'{d}')" for a, b, c, d in feeds[k:k + 500])
            f.write(f"INSERT INTO feeds (id,name,userid,tag,datatype,engine,processList) VALUES {rows};\n")
        rows = []
        for app_id, a in apps.items():
            cfg = json.dumps(a["config"]).replace("\\", "\\\\").replace("'", "\\'")
            rows.append(f"({app_id},'myheatpump',{a['userid']},'My Heatpump',1,'{cfg}')")
        for k in range(0, len(rows), 500):
            f.write("INSERT INTO app (id,app,userid,name,public,config) VALUES " + ",".join(rows[k:k + 500]) + ";\n")

    print(json.dumps(stats))


if __name__ == "__main__":
    main()
