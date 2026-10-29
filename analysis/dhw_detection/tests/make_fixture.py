#!/usr/bin/env python3
"""Make the test fixture: three days of one system's raw 30 s inputs, the settings worked out from its whole
history, and the probabilities this package gives for them. A port of the processing (for example one that reads
the 10 s feeds on the server) should reproduce these probabilities; see INTEGRATION.md.

The fixture in this folder is system 350 (a test-split system: the model never trained on it), from
2025-01-19 00:00 UTC, made from the HPM download of 2026-07-10.

Usage (from analysis/dhw_detection/):
  python3 tests/make_fixture.py PARQUET_DIR [system_id] [start]
"""

import json
import sys
from pathlib import Path

import pandas as pd

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE.parent))

from dhw_detection import FLAG, SIGNALS, load_models, predict_system, prepare  # noqa: E402


def main():
    folder = Path(sys.argv[1])
    system_id = int(sys.argv[2]) if len(sys.argv) > 2 else 350
    start = pd.Timestamp(sys.argv[3] if len(sys.argv) > 3 else "2025-01-19", tz="UTC")
    history = pd.read_parquet(folder / f"system_{system_id}.parquet", columns=SIGNALS + [FLAG])
    _, settings = prepare(history)  # settings from the whole history, as in a full run
    raw = history.loc[start:start + pd.Timedelta(days=3) - pd.Timedelta(seconds=30)]
    out, _ = predict_system(raw, load_models(), settings)
    fixture = raw.join(out.dhw_probability.rename("expected_probability"))
    fixture.to_parquet(HERE / f"fixture_{system_id}.parquet", compression="zstd")
    (HERE / f"fixture_{system_id}.json").write_text(json.dumps(dict(system_id=system_id, start=str(start), settings=settings), indent=1))
    print(f"system {system_id}: {len(fixture)} rows from {start}, {fixture.expected_probability.notna().sum()} with a prediction, "
          f"{(fixture.expected_probability >= 0.5).sum()} hot water")


if __name__ == "__main__":
    main()
