"""Read a system's inputs straight from emoncms PHPFina files, as the public API would return them.

For use on the server that holds the feed files: it gives the same values as hpm_api, without the
HTTP requests, which are about half the time of a full run through the API.

The API's /timeseries/data?interval=30&average=1 calls emoncms feed/data.json, which reads each
feed with PHPFina get_data_combined (limitinterval=0). For each 30 s step t (whole steps since 1970):

- a feed logging faster than every 30 s gives the mean of its non-NaN readings at positions
  floor((t - start_time) / interval) up to, not including, floor((t + 30 - start_time) / interval);
- a feed logging every 30 s or slower gives the reading at floor((t - start_time) / interval),
  if t is within the feed's first and last reading, so a 60 s feed shows each reading twice.

A .meta file holds the interval and start time as unsigned 32-bit integers at bytes 8 and 12.
The .dat file is float32, one value per interval, NaN where nothing was logged.
"""

import os
import time
from pathlib import Path

import numpy as np
import pandas as pd

from .prepare import FLAG, SIGNALS

INTERVAL = 30


def meta(folder, feedid):
    """{interval, start_time, npoints, end_time} of a PHPFina feed, or None if it has no data."""
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
    return dict(interval=interval, start_time=start_time, npoints=npoints, end_time=start_time + interval * (npoints - 1))


def values(folder, feedid, m, times):
    """float32 values of a feed at the 30 s steps ``times`` (unix seconds), NaN where the API gives null."""
    mi, st, n = m["interval"], m["start_time"], m["npoints"]
    data = np.memmap(Path(folder) / f"{feedid}.dat", dtype="<f4", mode="r", shape=(n,))
    if mi < INTERVAL:
        a = np.clip((times - st) // mi, 0, n)
        b = np.clip((times + INTERVAL - st) // mi, 0, n)
        lo, hi = int(a.min()), int(b.max())
        block = np.asarray(data[lo:hi], np.float64)
        total, count = np.zeros(len(times)), np.zeros(len(times))
        for k in range(int((b - a).max(initial=0))):  # in order, as PHP adds them
            pos = a + k
            inside = pos < b
            v = block[np.where(inside, pos, lo) - lo]
            ok = inside & ~np.isnan(v)
            total += np.where(ok, v, 0)
            count += ok
        with np.errstate(invalid="ignore", divide="ignore"):
            return np.where(count > 0, total / count, np.nan).astype(np.float32)
    out = np.full(len(times), np.nan, np.float32)
    inside = (times >= st) & (times <= m["end_time"])
    out[inside] = data[(times[inside] - st) // mi]
    return out


def load_system(folder, feedids, start=None):
    """A system's five inputs and its hot-water flag from ``start`` (a Timestamp; default: the start of its
    history) to its last reading, as hpm_api.load_system gives them. ``feedids``: {feed name: PHPFina feed id}."""
    metas = {k: meta(folder, feedids[k]) for k in SIGNALS + [FLAG] if k in feedids}
    feeds = [k for k, m in metas.items() if m]
    if not any(k in SIGNALS for k in feeds):
        return pd.DataFrame(columns=SIGNALS + [FLAG], dtype=np.float32)
    first = min(metas[k]["start_time"] for k in feeds) if start is None else int(pd.Timestamp(start).timestamp())
    last = min(max(metas[k]["end_time"] for k in feeds), int(time.time()))
    first, last = first - first % INTERVAL, last - last % INTERVAL
    if last < first:
        return pd.DataFrame(columns=feeds, dtype=np.float32)
    times = np.arange(first, last + 1, INTERVAL, dtype=np.int64)
    return pd.DataFrame({k: values(folder, feedids[k], metas[k], times) for k in feeds},
                        index=pd.to_datetime(times, unit="s", utc=True))
