#!/usr/bin/env python3
"""Fetch per-system power histograms from heatpumpmonitor.org (cached to disk).

kwh_at_power_elec: elec kWh binned by instantaneous elec input power (W)
kwh_at_power_heat: heat kWh binned by instantaneous heat output power (W)
"""

import json
import sys
import time
import urllib.request
from pathlib import Path

sys.path.insert(0, "/var/www/hpmon_ai2/analysis")
from hpmon_analysis import load, standard_filter, find_csv

CACHE = Path("/var/www/hpmon_ai2/histograms")
CACHE.mkdir(exist_ok=True)
START, END = 1752534000, 1784070000   # same last-365d window as the CSV export


def fetch(system_id, kind):
    out = CACHE / f"{system_id}_{kind}.json"
    if out.exists():
        return json.loads(out.read_text())
    url = (f"https://heatpumpmonitor.org/histogram/kwh_at_power_{kind}"
           f"?id={system_id}&start={START}&end={END}&x_min=0&x_max=30000")
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "hpmon-analysis/1.0 (trystan.lea@gmail.com)"})
        with urllib.request.urlopen(req, timeout=30) as r:
            d = json.loads(r.read())
        if not isinstance(d, dict) or "data" not in d:
            d = {"error": str(d)[:200]}
    except Exception as e:
        d = {"error": str(e)[:200]}
    out.write_text(json.dumps(d))
    time.sleep(0.15)
    return d


def main():
    df = load(find_csv())
    f = standard_filter(df, verbose=False)
    ids = f.id.astype(int).tolist()
    print(f"fetching elec+heat histograms for {len(ids)} systems")
    ok = err = 0
    for i, sid in enumerate(ids):
        for kind in ("elec", "heat"):
            d = fetch(sid, kind)
            if "error" in d:
                err += 1
            else:
                ok += 1
        if (i + 1) % 25 == 0:
            print(f"  {i+1}/{len(ids)}  ok={ok} err={err}", flush=True)
    print(f"done: ok={ok} err={err}")


if __name__ == "__main__":
    main()
