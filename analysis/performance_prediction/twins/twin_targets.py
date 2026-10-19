#!/usr/bin/env python3
"""Build calibration targets for simulated twins of the fleet's Arotherm+ systems.

Takes the validated air source systems from the raw feed scan
(hstar_fleet_hist.csv, same filter as analyse_hstar_fleet.py), keeps the
Arotherm+ units, and pulls each one's targets from the all-fields export.
twins.js then calibrates a simulator twin to each.

Usage (from analysis/performance_prediction/):
  python3 twins/twin_targets.py > twins/targets.json
"""

import csv
import glob
import json
import statistics
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
FLEET_CSV = ROOT / "hstar_fleet_hist.csv"
LLANBERIS_MEAN_OUTSIDE_T = 10.18      # annual mean of llanberis2024.csv
AROTHERM_BADGES = {3.5, 5.0, 7.0, 10.0, 12.0}


def num(v):
    try:
        return float(v)
    except (TypeError, ValueError):
        return None


def validated_arotherms():
    out = {}
    for r in csv.DictReader(open(FLEET_CSV)):
        if r["hp_type"] != "Air Source" or "Arotherm" not in r["hp_model"]:
            continue
        v = {k: num(r[k]) for k in ["calc_weighted_dT", "rec_weighted_dT", "spf_window",
                                    "rec_combined_cop", "heat_kwh_running", "running_hours",
                                    "rec_weighted_flowT_minus_returnT"]}
        if None in v.values():
            continue
        if abs(v["calc_weighted_dT"] - v["rec_weighted_dT"]) > 0.5:
            continue
        if abs(v["spf_window"] - v["rec_combined_cop"]) > 0.25:
            continue
        out[r["id"]] = v
    return out


def main():
    scan = validated_arotherms()
    export = sorted(glob.glob(str(ROOT / "heatpumpmonitor_all_fields_*.csv")))[-1]
    rows = {r["id"]: r for r in csv.DictReader(open(export)) if r["id"] in scan}

    # Systems that record a space/water split give the DHW share for those that don't
    def split(r):
        s, w = num(r["space_heat_kwh"]), num(r["water_heat_kwh"])
        return (s, w) if s and w and s > 0 and w > 0 else None
    shares = [w / (s + w) for s, w in filter(None, (split(r) for r in rows.values()))]
    median_water_share = statistics.median(shares)
    room_temps = [num(r["combined_roomT_mean"]) for r in rows.values()
                  if num(r["combined_roomT_mean"]) and num(r["quality_roomT"] or 0) >= 80]
    median_room = statistics.median(room_temps)

    targets = []
    for sid, r in rows.items():
        badge = num(r["hp_output"])
        if badge not in AROTHERM_BADGES:
            continue                                  # e.g. a 24 kW "Arotherm" entry
        s = scan[sid]
        sp = split(r)
        if sp:
            space, water = sp
        else:
            combined = num(r["combined_heat_kwh"])
            water = combined * median_water_share
            space = combined - water
        room = num(r["combined_roomT_mean"])
        if not room or num(r["quality_roomT"] or 0) < 80:
            room = median_room
        dhw_target = num(r["dhw_target_temperature"]) or 0
        cylinder = num(r["cylinder_volume"]) or 0
        # Flow rate from the system's own running output and flow-return dT
        mean_running_w = 1000 * s["heat_kwh_running"] / s["running_hours"]
        flow_rate = mean_running_w * 60 / (4187 * s["rec_weighted_flowT_minus_returnT"])
        targets.append({
            "id": sid,
            "badge_kw": badge,
            "space_heat_kwh": space,
            "water_heat_kwh": water,
            "has_split": bool(sp),
            "weighted_flowT": num(r["weighted_flowT"]),
            "room_temp": min(max(room, 16), 23),
            "room_temp_measured": room == num(r["combined_roomT_mean"]),
            "outsideT_offset": num(r["combined_outsideT_mean"]) - LLANBERIS_MEAN_OUTSIDE_T,
            "dhw_target": dhw_target if 40 <= dhw_target <= 65 else 48,
            "cylinder_volume": cylinder if 100 <= cylinder <= 400 else 200,
            "flow_rate": min(max(flow_rate, 6), 40),
        })
    json.dump({"median_water_share": median_water_share, "median_room_temp": median_room,
               "targets": targets}, __import__("sys").stdout, indent=1)


if __name__ == "__main__":
    main()
