"""Get one system's 30 s data ready for the model.

1. Put the five inputs on a regular 30 s grid (to_grid).
2. Join feeds that log every 1-2 minutes across their own logging interval (fill_reporting_gaps).
3. Join dropouts of up to 5 minutes with straight lines (join_short_gaps).
4. Work out the system's scales from its whole history (system_scales).

The logging intervals (step 2) and scales (step 4) are the system's "settings". A first run
works them out; a daily update reuses the stored ones, so it gives the same answer as a
full run would.
"""

import numpy as np
import pandas as pd

SIGNALS = ["heatpump_elec", "heatpump_heat", "heatpump_flowT", "heatpump_returnT", "heatpump_flowrate"]
FLAG = "heatpump_dhw"  # the measured hot-water flag, where a system logs one; not a model input
TEMPERATURES = ("heatpump_flowT", "heatpump_returnT")
TEMP_RANGE = (0.0, 70.0)  # °C, the same on every system, so a hot-water flow temperature looks the same everywhere
STEP = pd.Timedelta("30s")
JOIN_STEPS = 10  # dropouts of up to 5 minutes, with a reading on both sides, are joined by straight lines


def to_grid(raw, start=None):
    """The five inputs and the flag on a regular 30 s UTC grid, float32, NaN where missing.

    ``raw``: DataFrame with a DatetimeIndex (UTC, on whole 30 s steps since 1970) and any of the
    columns SIGNALS and FLAG; a missing column becomes all NaN. The grid runs from the first to the
    last row where any input has a reading. ``start`` makes the grid begin at that time instead
    (the daily update uses this so its windows line up with a full run)."""
    df = raw.reindex(columns=SIGNALS + [FLAG]).astype(np.float32)
    if df.index.tz is None:
        raise ValueError("the index must be timezone-aware (UTC)")
    df.index = df.index.tz_convert("UTC")
    has_input = df[SIGNALS].notna().any(axis=1).to_numpy()
    if not has_input.any():
        return df.iloc[:0]
    first, last = df.index[has_input][[0, -1]]
    if start is not None:
        first = pd.Timestamp(start).tz_convert("UTC")
    if first.value % STEP.value:
        raise ValueError(f"timestamps must fall on whole 30 s steps since 1970 (got {first})")
    return df.reindex(pd.date_range(first, last, freq=STEP))


def reporting_steps(series):
    """A feed's usual logging interval in 30 s steps: the median spacing of its readings (1 for a 30 s feed)."""
    idx = np.flatnonzero(series.notna().to_numpy())
    return int(np.median(np.diff(idx))) if len(idx) > 1 else 1


def fill_reporting_gaps(df, steps):
    """Readings of a feed that logs less often than every 30 s (some systems log every 1-2 minutes) joined by
    straight lines across that feed's own interval, so the model sees them as it saw 30 s data; longer gaps stay
    missing. ``steps``: {feed: logging interval in steps}. A feed logging every 30 s is unchanged."""
    for c in SIGNALS:
        gap = steps[c]
        if gap > 1:
            df[c] = df[c].interpolate(limit=gap - 1, limit_area="inside")
    return df


def join_short_gaps(df, columns, max_steps=JOIN_STEPS):
    """Missing stretches of up to ``max_steps`` steps with a reading on both sides joined by a straight line;
    longer gaps stay missing. A scattered 30 s dropout is noise, not an outage."""
    for c in columns:
        miss = df[c].isna().to_numpy()
        if not miss.any() or miss.all():
            continue
        edges = np.flatnonzero(np.diff(np.r_[0, miss.astype(np.int8), 0]))
        a, b = edges[::2], edges[1::2]
        ok = (b - a <= max_steps) & (a > 0) & (b < len(miss))
        if not ok.any():
            continue
        mark = np.zeros(len(miss) + 1, np.int32)
        np.add.at(mark, a[ok], 1)
        np.add.at(mark, b[ok], -1)
        short = np.cumsum(mark[:-1]) > 0
        df[c] = df[c].where(~short, df[c].interpolate(limit_area="inside"))
    return df


def system_scales(df):
    """Per system, (low, high) for each input: fixed 0-70 °C for temperatures; for electricity, heat and flow rate,
    low = the 1st percentile or 0, whichever is lower (so defrost, where heat goes negative, stays visible), and
    high = the 99th percentile, over the whole history. NaN for a feed the system lacks."""
    scales = {}
    for c in SIGNALS:
        if c in TEMPERATURES:
            scales[c] = TEMP_RANGE
        else:
            lo, hi = df[c].quantile([0.01, 0.99])
            scales[c] = (float(min(0, lo)), float(hi))
    return scales


def prepare(raw, settings=None, start=None):
    """Steps 1-4 for one system. Returns the prepared frame and the settings used.

    ``settings``: {"reporting_steps": {...}, "scales": {...}} from an earlier run, or None to work them
    out from this data (which should then be the system's whole history). ``start``: see to_grid."""
    df = to_grid(raw, start)
    steps = settings["reporting_steps"] if settings else {c: reporting_steps(df[c]) for c in SIGNALS}
    fill_reporting_gaps(df, steps)
    join_short_gaps(df, SIGNALS)
    if not settings:
        settings = dict(reporting_steps=steps, scales=system_scales(df))
    return df, settings
