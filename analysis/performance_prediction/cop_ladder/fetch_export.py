#!/usr/bin/env python3
"""Build the all-fields last-365-days export without the browser.

The system list page's "download all fields" CSV is system/list/public.json merged with
system/stats/last365. This fetches both and writes the same columns our scripts use, as
heatpumpmonitor_all_fields_last365_<date>.csv in analysis/performance_prediction/.

The last365 stats are rebuilt nightly by load_and_process_cli.php for the 365 days to
midnight (Europe/London), so pass today's date as build_windows.py --end. It prints the
matching command. The page's own export also adds measurement fields and a few derived
per-m² columns, which the COP ladder doesn't use.

Usage (from analysis/performance_prediction/):
    .venv/bin/python cop_ladder/fetch_export.py            # HPMON_URL to use another server
"""
import datetime as dt
import json
import os
import urllib.request
from pathlib import Path
from zoneinfo import ZoneInfo

import pandas as pd

BASE = os.environ.get("HPMON_URL", "https://heatpumpmonitor.org").rstrip("/")
ROOT = Path(__file__).resolve().parent.parent


def get(path):
    req = urllib.request.Request(f"{BASE}/{path}", headers={"User-Agent": "hpmon-cop-ladder"})
    with urllib.request.urlopen(req, timeout=300) as r:
        return json.loads(r.read())


def main():
    systems = pd.DataFrame(get("system/list/public.json"))
    stats = get("system/stats/last365")
    stats = pd.DataFrame([{**v, "id": int(k)} for k, v in stats.items()])
    stats = stats.drop(columns=[c for c in stats.columns if c in systems.columns and c != "id"])
    systems["id"] = systems["id"].astype(int)
    df = systems.merge(stats, on="id", how="left")

    today = dt.datetime.now(ZoneInfo("Europe/London")).date().isoformat()
    out = ROOT / f"heatpumpmonitor_all_fields_last365_{today}.csv"
    df.to_csv(out, index=False)
    print(f"{len(df)} systems, {df.combined_cop.notna().sum()} with last365 stats -> {out.name}")
    print("Build windows for the same year with:")
    print(f"  .venv/bin/python cop_ladder/build_windows.py --map cop_ladder/feed_map.json "
          f"--export {out.name} --end {today}")


if __name__ == "__main__":
    main()
