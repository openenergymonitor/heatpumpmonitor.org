#!/usr/bin/env python3
"""Stage predicted hot-water flags as PHPFina data, ready for scripts/dhw_feed.php to install.

Usage (from analysis/dhw_detection/):
  python3 scripts/export_phpfina.py --ids 748            # from output/system_748.parquet
  python3 scripts/export_phpfina.py --all                # every system_<id>.parquet in --out

Options:
  --out DIR       where predict.py wrote its results (default: output/)
  --stage DIR     where to write the staged files (default: <out>/phpfina/)

This only writes into the staging folder; it never touches emoncms. For each system it writes:
  system_<id>.dat    dhw_predicted as little-endian float32, one value per 30 s step from start_time:
                     1 hot water, 0 not, NaN where there is no prediction (PHPFina's own "missing")
  system_<id>.json   interval, start_time, npoints, the last non-NaN value and its time, and the model,
                     for dhw_feed.php to write the .meta and check what it installs

The values are the parquet's own 30 s grid, which starts on a whole 30 s step, so they copy across
with no resampling.
"""

import argparse
import json
import sys
from pathlib import Path

import numpy as np
import pandas as pd

HERE = Path(__file__).resolve().parents[1]
INTERVAL = 30


def export(system_id, out_dir, stage_dir):
    parquet = out_dir / f"system_{system_id}.parquet"
    settings = json.loads((out_dir / f"system_{system_id}.json").read_text())
    col = pd.read_parquet(parquet, columns=["dhw_predicted"])["dhw_predicted"]
    if col.empty:
        raise ValueError("no rows")

    times = col.index.asi8 // 10**9 if col.index.dtype.unit == "ns" else col.index.as_unit("s").asi8
    start_time = int(times[0])
    if start_time % INTERVAL:
        raise ValueError(f"start {start_time} is not on the {INTERVAL} s grid")
    if not np.array_equal(np.diff(times), np.full(len(times) - 1, INTERVAL)):
        raise ValueError(f"index is not a continuous {INTERVAL} s grid")

    values = col.to_numpy(dtype="<f4")
    valid = np.flatnonzero(~np.isnan(values))
    last = int(valid[-1]) if len(valid) else None

    tmp = stage_dir / f"system_{system_id}.dat.tmp"
    tmp.write_bytes(values.tobytes())
    tmp.replace(stage_dir / f"system_{system_id}.dat")

    info = {
        "system_id": system_id,
        "interval": INTERVAL,
        "start_time": start_time,
        "npoints": len(values),
        "end_time": start_time + (len(values) - 1) * INTERVAL,
        "last_time": start_time + last * INTERVAL if last is not None else None,
        "last_value": float(values[last]) if last is not None else None,
        "hot_water_steps": int(np.nansum(values)),
        "nan_steps": int(len(values) - len(valid)),
        "model": settings.get("model"),
        "predicted_at": settings.get("updated_at"),
    }
    (stage_dir / f"system_{system_id}.json").write_text(json.dumps(info, indent=1) + "\n")
    return info


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    group = ap.add_mutually_exclusive_group(required=True)
    group.add_argument("--ids", type=int, nargs="+")
    group.add_argument("--all", action="store_true")
    ap.add_argument("--out", type=Path, default=HERE / "output")
    ap.add_argument("--stage", type=Path)
    args = ap.parse_args()

    stage_dir = args.stage or args.out / "phpfina"
    stage_dir.mkdir(parents=True, exist_ok=True)
    if args.all:
        ids = sorted(int(p.stem.split("_")[1]) for p in args.out.glob("system_*.parquet"))
    else:
        ids = args.ids

    failed = 0
    for system_id in ids:
        try:
            i = export(system_id, args.out, stage_dir)
            print(f"system {system_id}: {i['npoints']} points from {i['start_time']}, "
                  f"{i['hot_water_steps']} hot water, {i['nan_steps']} NaN, last {i['last_value']} at {i['last_time']}")
        except Exception as e:  # noqa: BLE001 - report and carry on with the other systems
            failed += 1
            print(f"system {system_id}: FAILED {e}", file=sys.stderr)
    print(f"staged {len(ids) - failed} of {len(ids)} systems in {stage_dir}")
    sys.exit(1 if failed else 0)


if __name__ == "__main__":
    main()
