"""Fetch a system's inputs from the heatpumpmonitor.org public API, as the model was trained on them.

The training data came from /timeseries/data with interval=30 and average=1: 30 s averages on a
grid of whole 30 s steps since 1970 (start and end must be unix seconds, or the grid shifts).
The server allows at most 70,000 points per feed and about 400,000 in all per request, so
histories are fetched in 20-day pieces (57,600 points per feed).
"""

import time

import numpy as np
import pandas as pd
import requests

from .prepare import FLAG, SIGNALS

BASE_URL = "https://heatpumpmonitor.org"
INTERVAL = 30
CHUNK = 20 * 86400
HEADERS = {"User-Agent": "heatpumpmonitor-dhw-detection"}


def get_json(path, params=None, retries=5):
    for attempt in range(retries):
        try:
            r = requests.get(BASE_URL + path, params=params, headers=HEADERS, timeout=60)
            r.raise_for_status()
            return r.json()
        except (requests.RequestException, ValueError):
            if attempt == retries - 1:
                raise
            time.sleep(2 ** attempt)


def public_system_ids():
    """Ids of all published systems."""
    return sorted(int(s["id"]) for s in get_json("/system/list/public.json"))


def available_feeds(system_id):
    """{feed: {start_time, end_time, interval, npoints, ...}} for the feeds with data."""
    feeds = (get_json("/timeseries/available", {"id": system_id}) or {}).get("feeds") or {}
    return {k: v for k, v in feeds.items() if int(v.get("npoints") or 0) > 0 and int(v.get("start_time") or 0) > 0}


def fetch(system_id, feeds, start, end):
    """30 s averages of ``feeds`` from unix ``start`` to ``end`` (both whole multiples of 30 s), NaN where missing."""
    frames, t, span = [], start, CHUNK
    while t < end:
        t2 = min(t + span, end)
        data = get_json("/timeseries/data", {"id": system_id, "feeds": ",".join(feeds), "start": t, "end": t2,
                                             "interval": INTERVAL, "average": 1, "timeformat": "unix"})
        if isinstance(data, list) and not data:  # '[]': the request was too big for the server
            if span <= 86400:
                raise RuntimeError(f"system {system_id}: server returned no data for a 1-day request")
            span //= 2
            span -= span % INTERVAL
            continue
        cols = {}
        for feed, pairs in data.items():
            if isinstance(pairs, list) and pairs:  # a per-feed error comes back as a dict
                a = np.asarray(pairs, dtype=np.float64)  # null -> NaN
                cols[feed] = pd.Series(a[:, 1].astype(np.float32), index=pd.to_datetime(a[:, 0].astype(np.int64), unit="s", utc=True))
        if cols:
            frames.append(pd.DataFrame(cols))
        t = t2
    if not frames:
        return pd.DataFrame(columns=feeds, dtype=np.float32)
    df = pd.concat(frames)
    return df[~df.index.duplicated(keep="last")].sort_index()


def load_system(system_id, start=None):
    """A system's five inputs and its hot-water flag (where it logs one) from ``start`` (a Timestamp; default:
    the start of its history) to now."""
    avail = available_feeds(system_id)
    feeds = [f for f in SIGNALS + [FLAG] if f in avail]
    if not any(f in SIGNALS for f in feeds):
        return pd.DataFrame(columns=SIGNALS + [FLAG], dtype=np.float32)
    first = min(int(avail[f]["start_time"]) for f in feeds) if start is None else int(pd.Timestamp(start).timestamp())
    last = min(max(int(avail[f]["end_time"]) for f in feeds), int(time.time()))
    return fetch(system_id, feeds, first - first % INTERVAL, last - last % INTERVAL)
