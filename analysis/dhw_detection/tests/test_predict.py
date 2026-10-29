"""Checks for the hot-water predictor. Run from analysis/dhw_detection/:  python3 -m pytest tests"""

import json
import sys
from pathlib import Path

import numpy as np
import pandas as pd
import pytest

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE.parent))

from dhw_detection import FLAG, SIGNALS, cycles, load_models, predict_system  # noqa: E402
from dhw_detection.prepare import STEP, to_grid  # noqa: E402

FIXTURE = HERE / "fixture_350.parquet"


@pytest.fixture(scope="module")
def models():
    return load_models("cpu")


@pytest.fixture(scope="module")
def fixture():
    f = pd.read_parquet(FIXTURE)
    settings = json.loads(FIXTURE.with_suffix(".json").read_text())["settings"]
    return f[SIGNALS + [FLAG]], f.expected_probability.to_numpy(), settings


def test_weights_load(models):
    assert len(models) == 3
    assert sum(p.numel() for p in models[0].parameters()) == 92881


def test_fixture_probabilities(models, fixture):
    """The whole chain, from raw 30 s inputs to probabilities, reproduces the stored ones."""
    raw, expected, settings = fixture
    out, _ = predict_system(raw, models, settings)
    got = out.dhw_probability.to_numpy()
    assert len(got) == len(expected)
    assert np.array_equal(np.isnan(got), np.isnan(expected))
    assert np.nanmax(np.abs(got - expected)) < 1e-4
    assert ((got >= 0.5) == (expected >= 0.5)).all()


def test_update_matches_full_run(models, fixture):
    """A run on the first two days and then an update with the third gives what one run on all three gives."""
    raw, _, settings = fixture
    full, _ = predict_system(raw, models, settings)
    first, _ = predict_system(raw[raw.index < raw.index[0] + pd.Timedelta(days=2)], models, settings)
    since = (first.index[-1] + STEP - pd.Timedelta(hours=24)).floor("6h")
    new, _ = predict_system(raw[raw.index >= since - pd.Timedelta(hours=19)], models, settings, since)
    merged = pd.concat([first[first.index < since], new])
    assert merged.index.equals(full.index)
    a, b = merged.dhw_probability.to_numpy(), full.dhw_probability.to_numpy()
    assert np.array_equal(np.isnan(a), np.isnan(b))
    assert np.nanmax(np.abs(a - b)) < 1e-6


def test_no_prediction_without_temperatures(models, fixture):
    """An hour without the flow temperature has no prediction; the steps around it still do."""
    raw, _, settings = fixture
    raw = raw.copy()
    gap = (raw.index >= raw.index[0] + pd.Timedelta(hours=30)) & (raw.index < raw.index[0] + pd.Timedelta(hours=31))
    raw.loc[gap, "heatpump_flowT"] = np.nan
    out, _ = predict_system(raw, models, settings)
    prob = out.dhw_probability.to_numpy()
    assert np.isnan(prob[gap]).all()
    assert not np.isnan(prob[~gap]).any()


def test_short_dropouts_are_joined(models, fixture):
    """A 2-minute dropout of every feed is joined by straight lines, so those steps keep a prediction."""
    raw, _, settings = fixture
    raw = raw.copy()
    raw.iloc[4000:4004, :len(SIGNALS)] = np.nan
    out, _ = predict_system(raw, models, settings)
    assert not np.isnan(out.dhw_probability.to_numpy()[4000:4004]).any()
    assert out.inputs_complete.iloc[4000:4004].all()


def test_cycles():
    idx = pd.date_range("2025-01-01", periods=10, freq="30s", tz="UTC")
    out = pd.DataFrame({"dhw_predicted": [0, 1, 1, 1, 0, np.nan, 1, 1, np.nan, 1]}, index=idx)
    c = cycles(out)
    assert c.minutes.tolist() == [1.5, 1.0, 0.5]
    assert c.start_unix.iloc[0] == idx[1].timestamp() and c.end_unix.iloc[0] == idx[4].timestamp()
    assert c.start_utc.iloc[1] == "2025-01-01T00:03:00Z"


def test_grid_rules():
    idx = pd.date_range("2025-01-01", periods=4, freq="30s")
    with pytest.raises(ValueError):
        to_grid(pd.DataFrame({"heatpump_elec": [1.0, 2, 3, 4]}, index=idx))  # no timezone
    with pytest.raises(ValueError):
        to_grid(pd.DataFrame({"heatpump_elec": [1.0, 2, 3, 4]}, index=idx.tz_localize("UTC") + pd.Timedelta("10s")))  # off the 30 s grid
    df = to_grid(pd.DataFrame({"heatpump_elec": [np.nan, 1.0, np.nan, 2.0]}, index=idx.tz_localize("UTC")))
    assert len(df) == 3 and list(df.columns) == SIGNALS + [FLAG]  # trimmed to the first reading; missing feeds added as NaN
