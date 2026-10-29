"""Predict hot water over a system's history.

The history is cut into 24 h windows starting every 6 hours on the UTC clock (00, 06, 12, 18).
Each window is scaled with the system's own scales and predicted by the three trained copies.
Each step's probability is the mean over the three copies and over the (up to four) windows
that contain it. There is no prediction where the flow or return temperature is missing, or
where no window covers the step (the first and last few hours of a history).
A step counts as hot water when its probability is at least 0.5.
"""

import numpy as np
import pandas as pd
import torch

from .prepare import FLAG, SIGNALS, STEP, prepare

STEPS_PER_HOUR = 120
WINDOW = 24 * STEPS_PER_HOUR  # 2880 steps of 30 s
STRIDE = 6 * STEPS_PER_HOUR   # a window starts every 6 hours
THRESHOLD = 0.5
MIN_CYCLE_STEPS = 10          # cycles of at least 5 minutes count in cycles_per_day


def model_inputs(signals):
    """(n, 10, steps) tensor: the five scaled signals with missing values set to 0 (clipped to -1..2), then five 'missing' channels."""
    missing = np.isnan(signals)
    values = np.clip(np.nan_to_num(signals, nan=0.0), -1, 2)
    return torch.from_numpy(np.concatenate([values, missing], axis=1).astype(np.float32))


def scaled(df, scales):
    """(5, steps) float32: each input as (value - low) / (high - low), NaN kept. A flat or absent feed divides by 1."""
    lo = np.nan_to_num(np.array([scales[c][0] for c in SIGNALS], np.float32))
    hi = np.array([scales[c][1] for c in SIGNALS], np.float32)
    width = np.where(hi > lo, hi - lo, 1).astype(np.float32)
    return ((df[SIGNALS].to_numpy(np.float32) - lo) / width).T


@torch.no_grad()
def predict_probability(df, scales, models, since=None, batch=64):
    """Probability of hot water at each step of ``df`` (from prepare), float32, NaN where there is no prediction.

    ``since``: a 6 h boundary for the daily update. Only steps from then on are predicted, from the windows
    starting up to 18 h before it (all the windows that cover those steps); earlier steps are NaN."""
    x = scaled(df, scales)
    n = x.shape[1]
    anchor = df.index[0].ceil("6h")
    if since is not None:
        anchor = max(anchor, since - pd.Timedelta(hours=18))
    first = int((anchor - df.index[0]) / STEP)
    starts = np.arange(first, n - WINDOW + 1, STRIDE)
    temps_missing = df[["heatpump_flowT", "heatpump_returnT"]].isna().any(axis=1).to_numpy()
    with_temps = np.r_[0, np.cumsum(~temps_missing)]
    starts = starts[with_temps[starts + WINDOW] - with_temps[starts] > 0]  # a window with no temperatures at all can't change any prediction
    device = next(models[0].parameters()).device
    total, count = np.zeros(n, np.float32), np.zeros(n, np.float32)
    for i in range(0, len(starts), batch):
        s = starts[i:i + batch]
        inp = model_inputs(np.stack([x[:, a:a + WINDOW] for a in s])).to(device)
        p = torch.stack([torch.sigmoid(m(inp).float()) for m in models]).mean(0).cpu().numpy()
        for a, row in zip(s, p):
            total[a:a + WINDOW] += row
            count[a:a + WINDOW] += 1
    with np.errstate(invalid="ignore", divide="ignore"):
        prob = np.where((count > 0) & ~temps_missing, total / count, np.nan).astype(np.float32)
    if since is not None:
        prob[df.index < since] = np.nan
    return prob


def predict_system(raw, models, settings=None, since=None):
    """One system, start to finish. Returns (output frame, settings).

    ``raw``: the system's 30 s data (see prepare.to_grid). ``settings``: from an earlier run, or None to work them
    out from ``raw`` (then ``raw`` should be the whole history). ``since``: for the daily update (see
    predict_probability); ``raw`` must then start at least 19 h before it, and only rows from ``since`` on are returned.

    Output columns, one row per 30 s step (UTC 'timestamp' index):
      dhw_measured     the system's own heatpump_dhw flag, unchanged (NaN where it logged none)
      dhw_probability  the model's probability of hot water, 0-1 (NaN where no prediction)
      dhw_predicted    1 if dhw_probability >= 0.5, else 0 (NaN where no prediction)
      inputs_complete  True where all five inputs are present (after joining gaps as the model saw them)"""
    start = None if since is None else since - pd.Timedelta(hours=19)
    df, settings = prepare(raw, settings, start)
    if len(df) == 0:
        return None, settings
    prob = predict_probability(df, settings["scales"], models, since)
    out = pd.DataFrame({"dhw_measured": df[FLAG].to_numpy(np.float32),
                        "dhw_probability": prob,
                        "dhw_predicted": np.where(np.isnan(prob), np.nan, prob >= THRESHOLD).astype(np.float32),
                        "inputs_complete": df[SIGNALS].notna().all(axis=1).to_numpy()},
                       index=pd.DatetimeIndex(df.index.as_unit("ms"), name="timestamp"))
    return (out if since is None else out[out.index >= since]), settings


def runs(mask, min_len=1):
    """(start, end) positions of each stretch of True in a 1-D mask, at least ``min_len`` long."""
    edges = np.flatnonzero(np.diff(np.r_[0, mask.astype(np.int8), 0]))
    return [(a, b) for a, b in zip(edges[::2], edges[1::2]) if b - a >= min_len]


def cycles(out):
    """Every predicted hot-water period: one row per stretch of consecutive steps with dhw_predicted = 1.
    A step with no prediction ends a period. end = the last hot-water step + 30 s."""
    on = (out.dhw_predicted.to_numpy() == 1).astype(np.int8)
    edges = np.flatnonzero(np.diff(np.r_[0, on, 0]))
    a, b = edges[::2], edges[1::2]
    start, end = out.index[a], out.index[b - 1] + STEP
    return pd.DataFrame({"start_unix": start.as_unit("s").asi8, "end_unix": end.as_unit("s").asi8,
                         "start_utc": start.strftime("%Y-%m-%dT%H:%M:%SZ"), "end_utc": end.strftime("%Y-%m-%dT%H:%M:%SZ"),
                         "minutes": (b - a) * 0.5})


def filled_flag(flag):
    """The measured flag with each reading carried on until the next one, for flags that report only every few steps."""
    gap = 1
    known = np.flatnonzero(flag.notna().to_numpy())
    if len(known) > 1:
        gap = int(np.median(np.diff(known)))
    return flag.ffill(limit=gap - 1) if gap > 1 else flag


def signals_present(raw):
    """The model inputs a system has logged at least once."""
    return [c for c in SIGNALS if c in raw and raw[c].notna().any()]


def summary(out, present):
    """One system's totals: how much was predicted, and agreement with its own flag where it logs one.
    ``present``: the inputs the system has logged (signals_present)."""
    prob = out.dhw_probability.to_numpy()
    has_pred = ~np.isnan(prob)
    pred = has_pred & (prob >= THRESHOLD)
    flag = filled_flag(out.dhw_measured).to_numpy()
    known = has_pred & ~np.isnan(flag)
    truth = np.nan_to_num(flag) >= 0.5
    tp, fp, fn = (pred & truth & known).sum(), (pred & ~truth & known).sum(), (~pred & truth & known).sum()
    days = has_pred.sum() / 2880
    return dict(days_predicted=round(days, 1),
                signals_missing=", ".join(c.replace("heatpump_", "") for c in SIGNALS if c not in present),
                predicted_share=round(float(pred[has_pred].mean()), 4) if days else None,
                predicted_cycles_per_day=round(len(runs(pred, MIN_CYCLE_STEPS)) / days, 2) if days else None,
                flag_days=round(known.sum() / 2880, 1),
                flag_share=round(float(truth[known].mean()), 4) if known.any() else None,
                f1_vs_flag=round(2 * tp / (2 * tp + fp + fn), 3) if (2 * tp + fp + fn) else None,
                predicted_not_flagged_h=round(fp / 120, 1), flagged_not_predicted_h=round(fn / 120, 1))
