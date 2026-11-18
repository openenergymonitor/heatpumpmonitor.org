"""Fetch and cache HeatpumpMonitor.org data: system list, daily stats and timeseries."""
import gzip
import io
import json
import os
import time
import urllib.request
from concurrent.futures import ThreadPoolExecutor

import numpy as np
import pandas as pd

BASE = os.environ.get("HPMON_URL", "https://dev.heatpumpmonitor.org")
CACHE = os.environ.get("HPMON_CACHE", os.path.join(os.path.dirname(__file__), "..", "data"))

DAILY_FIELDS = [
    "timestamp", "combined_outsideT_mean", "combined_data_length",
    "combined_heat_kwh", "combined_elec_kwh", "space_heat_kwh", "space_elec_kwh",
    "water_heat_kwh", "water_elec_kwh", "space_flowT_mean", "space_returnT_mean",
    "water_flowT_mean", "running_flowT_mean", "combined_cooling_kwh", "cooling_heat_kwh",
]
TS_FEEDS = ["heatpump_elec", "heatpump_heat", "heatpump_flowT", "heatpump_returnT",
            "heatpump_flowrate", "heatpump_outsideT", "heatpump_dhw"]
DAY = 86400


def _get(url, tries=3):
    for i in range(tries):
        try:
            req = urllib.request.Request(url, headers={"User-Agent": "hpmon-comparison"})
            with urllib.request.urlopen(req, timeout=300) as r:
                return r.read()
        except Exception:
            if i == tries - 1:
                raise
            time.sleep(2 * (i + 1))


def _path(*parts):
    p = os.path.join(CACHE, *parts)
    os.makedirs(os.path.dirname(p), exist_ok=True)
    return p


def system_list(refresh=False):
    p = _path("list.json")
    if refresh or not os.path.exists(p):
        with open(p, "wb") as f:
            f.write(_get(f"{BASE}/system/list/public.json"))
    with open(p) as f:
        return {s["id"]: s for s in json.load(f)}


def daily(sysid, refresh=False):
    """All daily stats for a system as a DataFrame (cached as CSV)."""
    p = _path("daily", f"{sysid}.csv")
    if refresh or not os.path.exists(p):
        raw = _get(f"{BASE}/system/stats/daily?id={sysid}&fields={','.join(DAILY_FIELDS)}")
        with open(p, "wb") as f:
            f.write(raw)
    try:
        return pd.read_csv(p)
    except Exception:
        return pd.DataFrame(columns=DAILY_FIELDS)


def daily_many(ids, refresh=False, workers=8):
    with ThreadPoolExecutor(workers) as ex:
        return dict(zip(ids, ex.map(lambda i: daily(i, refresh), ids)))


def _ts_chunk(sysid, feeds, start, end, interval, average):
    url = (f"{BASE}/timeseries/data?id={sysid}&feeds={','.join(feeds)}&start={start}&end={end}"
           f"&interval={interval}&average={average}&timeformat=unix")
    d = json.loads(_get(url))
    if not isinstance(d, dict) or d.get("success") is False:
        raise RuntimeError(f"timeseries request failed for {sysid}: {d}")
    cols = {}
    for k, v in d.items():
        a = np.array([[p[0], np.nan if p[1] is None else p[1]] for p in v], dtype=float)
        if len(a):
            cols[k.replace("heatpump_", "")] = pd.Series(a[:, 1], index=a[:, 0].astype(np.int64))
    return pd.DataFrame(cols)


def timeseries(sysid, start, days, interval, feeds=TS_FEEDS, chunk_days=28, refresh=False):
    """Timeseries for [start, start + days) at `interval` seconds, cached as gzipped pickle.

    interval 60 uses averaged values; shorter intervals use raw values (average=0)."""
    average = 1 if interval >= 60 else 0
    p = _path("ts", f"{sysid}_{start}_{days}d_{interval}s.pkl.gz")
    if not refresh and os.path.exists(p):
        return pd.read_pickle(p)
    end = start + days * DAY
    edges = list(range(start, end, chunk_days * DAY)) + [end]
    parts = [_ts_chunk(sysid, feeds, a, b, interval, average) for a, b in zip(edges[:-1], edges[1:])]
    df = pd.concat(parts)
    df = df[~df.index.duplicated()].sort_index()
    df = df[(df.index >= start) & (df.index < end)]
    df.to_pickle(p)
    return df
