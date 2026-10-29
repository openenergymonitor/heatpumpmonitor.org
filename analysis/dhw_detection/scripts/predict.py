#!/usr/bin/env python3
"""Predict hot water for HeatpumpMonitor.org systems and write the results to a folder.

Usage (from analysis/dhw_detection/):
  python3 scripts/predict.py --ids 204 1 88      # these systems, fetched from the public API
  python3 scripts/predict.py --all               # every public system (a full run)
  python3 scripts/predict.py --all --update      # daily: only the data since the last run
  python3 scripts/predict.py --all --source parquet DIR   # read DIR/system_<id>.parquet instead of the API
  python3 scripts/predict.py --all --source phpfina MAP   # on the server: read the PHPFina files directly

Options:
  --out DIR       where to write (default: output/ next to this folder's README)
  --workers N     systems run at once, one process each (default: half the CPU cores; 1 on a GPU)
  --device D      cpu or cuda (default: cuda if available, else cpu)

--update keeps each system's stored settings (logging intervals and scales) and re-predicts only from
24-30 h before the end of the last run, so the result is the same as a full run with those settings.
A system gets a full run instead when it has no earlier output, its settings came from under 30 days of
data, or the model has changed. A parquet DIR must hold files in the HPM download layout: a UTC
'timestamp' index on the 30 s grid and one column per feed. A phpfina MAP is the JSON that
scripts/feed_map.php writes; a system with a feed that is not PHPFina is fetched through the API.

Writes to --out:
  system_<id>.parquet       one row per 30 s step: dhw_measured, dhw_probability, dhw_predicted, inputs_complete
  system_<id>.json          the system's settings, data range and summary (needed by --update)
  system_<id>_cycles.csv    its predicted hot-water periods
  hot_water_cycles.csv      every system's periods in one file
  systems.csv               one row per system: days predicted, share of hot water, agreement with its own flag
"""

import argparse
import json
import math
import os
import sys
import time
from concurrent.futures import ProcessPoolExecutor, as_completed
from pathlib import Path

import pandas as pd
import pyarrow.parquet as pq
import torch

HERE = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(HERE))

from dhw_detection import FLAG, MODEL_NAME, SIGNALS, cycles, load_models, predict_system, signals_present, summary  # noqa: E402
from dhw_detection import hpm_api, phpfina  # noqa: E402
from dhw_detection.prepare import STEP  # noqa: E402

FREEZE_AFTER_DAYS = 30  # settings worked out from less data than this are worked out again on the next run

# Set in each worker by init().
models = source = folder = out_dir = update = None


def load_parquet(system_id, start=None):
    path = folder / f"system_{system_id}.parquet"
    present = pq.read_schema(path).names
    raw = pd.read_parquet(path, columns=[c for c in SIGNALS + [FLAG] if c in present])
    return raw if start is None else raw[raw.index >= start]


def load_phpfina(system_id, start=None):
    feeds = folder["systems"].get(str(system_id), {})
    if any(f["engine"] != 5 for f in feeds.values()):
        return hpm_api.load_system(system_id, start)
    return phpfina.load_system(folder["datadir"], {k: f["id"] for k, f in feeds.items()}, start)


def load(system_id, start=None):
    if source == "api":
        return hpm_api.load_system(system_id, start)
    return load_phpfina(system_id, start) if source == "phpfina" else load_parquet(system_id, start)


def init(source_, folder_, out_dir_, update_, device, threads):
    global models, source, folder, out_dir, update
    torch.set_num_threads(threads)
    models = load_models(device)
    source, folder, out_dir, update = source_, folder_, out_dir_, update_
    if source == "phpfina":
        folder = json.loads(Path(folder).read_text())


def no_nan(x):
    """JSON-safe: NaN becomes null."""
    if isinstance(x, dict):
        return {k: no_nan(v) for k, v in x.items()}
    if isinstance(x, (list, tuple)):
        return [no_nan(v) for v in x]
    return None if isinstance(x, float) and math.isnan(x) else x


def stored_settings(meta):
    s = meta["settings"]
    return dict(reporting_steps=s["reporting_steps"],
                scales={c: tuple(float("nan") if v is None else v for v in lo_hi) for c, lo_hi in s["scales"].items()})


def write(system_id, out, settings, settings_days, present):
    """The system's parquet, cycles and json, each written to a temporary file first so a reader never sees half a file."""
    def replace(path, save):
        tmp = path.with_name(path.name + ".tmp")
        save(tmp)
        os.replace(tmp, path)

    replace(out_dir / f"system_{system_id}.parquet", lambda p: out.to_parquet(p, compression="zstd"))
    c = cycles(out)
    c.insert(0, "system_id", system_id)
    replace(out_dir / f"system_{system_id}_cycles.csv", lambda p: c.to_csv(p, index=False))
    meta = dict(system_id=system_id, model=MODEL_NAME, settings=settings, settings_days=settings_days, signals_present=present,
                data_start=str(out.index[0]), data_end=str(out.index[-1]), updated_at=pd.Timestamp.now(tz="UTC").isoformat(timespec="seconds"),
                summary=summary(out, present))
    replace(out_dir / f"system_{system_id}.json", lambda p: p.write_text(json.dumps(no_nan(meta), indent=1)))
    return meta["summary"]


def run_system(system_id):
    """Predict one system (a full run, or an update where possible). Returns (system_id, what happened)."""
    try:
        return predict_one(system_id)
    except Exception as e:  # report and carry on with the next system
        return system_id, f"failed ({type(e).__name__}: {e})"


def predict_one(system_id):
    t = time.time()
    meta_path = out_dir / f"system_{system_id}.json"
    meta = json.loads(meta_path.read_text()) if update and meta_path.exists() else None
    if meta and meta["model"] == MODEL_NAME and meta["settings_days"] >= FREEZE_AFTER_DAYS:
        old = pd.read_parquet(out_dir / f"system_{system_id}.parquet")
        since = (old.index[-1] + STEP - pd.Timedelta(hours=24)).floor("6h")
        raw = load(system_id, since - pd.Timedelta(hours=19))
        settings = stored_settings(meta)
        new, _ = predict_system(raw, models, settings, since)
        if new is None or new.index[-1] <= old.index[-1]:
            return system_id, "no new data"
        out = pd.concat([old[old.index < since], new])
        present = [c for c in SIGNALS if c in meta["signals_present"] or c in signals_present(raw)]
        days, kind = meta["settings_days"], f"updated from {since:%Y-%m-%d %H:%M}"
    else:
        raw = load(system_id)
        out, settings = predict_system(raw, models)
        if out is None:
            return system_id, "no data"
        present, days, kind = signals_present(raw), round(len(out) / 2880, 1), "full run"
    s = write(system_id, out, settings, days, present)
    return system_id, f"{kind}, {s['days_predicted']:.0f} days predicted, {s['predicted_share']} hot water, F1 vs flag {s['f1_vs_flag']} ({time.time() - t:.0f} s)"


def combine(out_dir):
    """hot_water_cycles.csv and systems.csv from every system in the folder."""
    metas = [json.loads(p.read_text()) for p in out_dir.glob("system_*.json")]
    systems = pd.DataFrame([{"system": m["system_id"], "data_start": m["data_start"], "data_end": m["data_end"], **m["summary"]} for m in metas])
    systems.sort_values("system").to_csv(out_dir / "systems.csv", index=False)
    parts = [pd.read_csv(p) for p in out_dir.glob("system_*_cycles.csv")]
    if parts:
        pd.concat(parts).sort_values(["system_id", "start_unix"]).to_csv(out_dir / "hot_water_cycles.csv", index=False)
    return len(systems)


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    pick = ap.add_mutually_exclusive_group(required=True)
    pick.add_argument("--ids", type=int, nargs="+")
    pick.add_argument("--all", action="store_true")
    ap.add_argument("--source", nargs="+", default=["api"], metavar=("api|parquet|phpfina", "DIR|MAP"))
    ap.add_argument("--out", type=Path, default=HERE / "output")
    ap.add_argument("--update", action="store_true")
    ap.add_argument("--workers", type=int)
    ap.add_argument("--device", default="cuda" if torch.cuda.is_available() else "cpu")
    a = ap.parse_args()

    source_, folder_ = a.source[0], Path(a.source[1]) if len(a.source) > 1 else None
    if source_ not in ("api", "parquet", "phpfina") or (source_ != "api") != (folder_ is not None):
        ap.error("--source is 'api', 'parquet DIR' or 'phpfina MAP'")
    a.out.mkdir(parents=True, exist_ok=True)
    if a.ids:
        ids = a.ids
    elif source_ == "api":
        ids = hpm_api.public_system_ids()
    elif source_ == "phpfina":
        ids = sorted(int(i) for i in json.loads(folder_.read_text())["systems"])
    else:
        ids = sorted(int(p.stem.split("_")[1]) for p in folder_.glob("system_*.parquet"))
    cores = os.cpu_count() or 1
    workers = min(len(ids), a.workers or (1 if a.device == "cuda" else max(1, cores // 2)))
    # On a CPU one thread per process gets the most from each core; a lone process gains up to about four
    threads = max(1, cores // workers) if a.device == "cuda" else (1 if workers > 1 else min(4, cores))
    args = (source_, folder_, a.out, a.update, a.device, threads)
    print(f"{len(ids)} systems, {workers} worker(s) x {threads} thread(s) on {a.device}, writing to {a.out}", flush=True)

    t = time.time()
    if workers == 1:
        init(*args)
        results = map(run_system, ids)
    else:
        pool = ProcessPoolExecutor(workers, initializer=init, initargs=args)
        results = (f.result() for f in as_completed([pool.submit(run_system, i) for i in ids]))
    for n, (system_id, what) in enumerate(results, 1):
        print(f"[{n}/{len(ids)}] system {system_id}: {what}", flush=True)
    print(f"done in {(time.time() - t) / 60:.1f} min; {combine(a.out)} systems in {a.out / 'systems.csv'}")


if __name__ == "__main__":
    main()
