"""Checks for reading PHPFina files directly. Run from analysis/dhw_detection/:  python3 -m pytest tests"""

import math
import sys
from pathlib import Path

import numpy as np
import pandas as pd
import pytest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from dhw_detection import phpfina  # noqa: E402


def write_feed(folder, feedid, start_time, interval, values):
    (folder / f"{feedid}.meta").write_bytes(np.array([0, 0, interval, start_time], "<u4").tobytes())
    np.asarray(values, "<f4").tofile(folder / f"{feedid}.dat")


def php_get_data(values, start_time, interval, start, end):
    """PHPFina get_data_combined with interval=30, average=1, limitinterval=0, line by line."""
    n, end_time, out = len(values), start_time + interval * (len(values) - 1), []
    average = 30 > interval
    for t in range(start, end + 1, 30):
        pos_start = math.floor((t - start_time) / interval)
        value = None
        if average:
            pos_end = math.floor((t + 30 - start_time) / interval)
            pos_start, pos_end = min(max(pos_start, 0), n), min(max(pos_end, 0), n)
            total, count = 0.0, 0
            for x in range(pos_start, pos_end):
                v = float(values[x])
                if not math.isnan(v):
                    total += v
                    count += 1
            if count:
                value = 1.0 * total / count
        elif start_time <= t <= end_time:
            v = float(values[pos_start])
            value = None if math.isnan(v) else v
        out.append(np.nan if value is None else value)
    return np.array(out).astype(np.float32)


@pytest.mark.parametrize("interval, offset", [(5, 0), (10, 10), (10, 5), (20, 10), (30, 0), (60, 30), (120, 40)])
def test_matches_php(tmp_path, interval, offset):
    rng = np.random.default_rng(interval + offset)
    values = rng.normal(1000, 300, 2000).astype(np.float32)
    values[rng.random(2000) < 0.1] = np.nan
    values[500:560] = np.nan
    start_time = 1_700_000_010 + offset
    write_feed(tmp_path, 1, start_time, interval, values)
    m = phpfina.meta(tmp_path, 1)
    start = start_time - start_time % 30 - 300  # from before the feed's first reading to after its last
    end = m["end_time"] + 300
    end -= end % 30
    times = np.arange(start, end + 1, 30, dtype=np.int64)
    got = phpfina.values(tmp_path, 1, m, times)
    expected = php_get_data(values, start_time, interval, start, end)
    assert np.array_equal(np.isnan(got), np.isnan(expected))
    assert np.array_equal(got[~np.isnan(got)], expected[~np.isnan(expected)])


def test_load_system(tmp_path):
    """The grid runs from the earliest feed's first reading to the latest feed's last, on whole 30 s steps."""
    write_feed(tmp_path, 1, 1_700_000_010, 10, np.arange(90, dtype=np.float32))   # 15 minutes
    write_feed(tmp_path, 2, 1_700_000_100, 60, np.arange(20, dtype=np.float32))   # starts later, ends later
    raw = phpfina.load_system(tmp_path, {"heatpump_elec": 1, "heatpump_flowT": 2, "heatpump_heat": 3})
    assert list(raw.columns) == ["heatpump_elec", "heatpump_flowT"]               # feed 3 has no files
    assert raw.index[0] == pd.Timestamp(1_700_000_010 - 1_700_000_010 % 30, unit="s", tz="UTC")
    assert raw.index[-1] == pd.Timestamp(1_700_000_100 + 19 * 60, unit="s", tz="UTC")
    assert raw.heatpump_flowT.iloc[2:7].isna().tolist() == [True] + [False] * 4
    assert raw.heatpump_flowT.iloc[3:7].tolist() == [0.0, 0.0, 1.0, 1.0]           # a 60 s reading shows twice
    later = phpfina.load_system(tmp_path, {"heatpump_elec": 1}, pd.Timestamp(1_700_000_400, unit="s", tz="UTC"))
    assert later.index[0] == pd.Timestamp(1_700_000_400 - 1_700_000_400 % 30, unit="s", tz="UTC")
