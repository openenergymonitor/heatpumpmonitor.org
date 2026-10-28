#!/usr/bin/env python3
"""Summarise the machine replay (scripts/feed_scan/feed_scan_6.php) across the
Arotherm+ fleet and ask what drives each system's level against its datasheet.

Each replay compares measured electricity with the Arotherm+ datasheet fit at
the measured flowT, outsideT and heat output. Per system this gives:

  level        field / datasheet COP in steady space heating at 30-70 rps
  min_penalty  field / datasheet at minimum speed (inferred < 30 rps), over level
  min_share    share of steady running at minimum speed
  rise         field / datasheet at 70-120 rps, over level (a size check)
  ramp_share   share of steady-or-ramp running where flowT changes fast (ramp state),
               mostly DHW warm-ups the flag or guess misses
  steps        the SPF ladder from the datasheet replay to the measured SPF

replay_fleet.sh runs every system at its declared capacity and one Arotherm+
size up. The upsized run is used when the declared one shows sustained heat
beyond the fit's maximum at a believable COP (not meter spikes) and the upsized
run removes most of it.

Level is computed from electricity, so it's a decomposition of COP, not a
predictor. The second half looks for what predicts it.

Usage (from analysis/performance_prediction/):
  python3 twins/replay_fleet.py [replay_dir] [--model v2|generic|unit] [--csv path]

--model reads the replays run with that datasheet COP model (replay_fleet.sh's
third argument) and writes twins/replay_fleet_<model>.csv; v2, the default,
writes twins/replay_fleet.csv.
"""

import glob
import json
import re
import sys
from pathlib import Path

import numpy as np
import pandas as pd
from scipy import stats

ROOT = Path(__file__).resolve().parent.parent
REPO = ROOT.parent.parent
DEFAULT_DIR = REPO / "scripts" / "feed_scan" / "replay"

MIN_LEVEL_H = 20       # hours needed at 30-70 rps for a level
MIN_CELL_H = 5         # hours needed for the other speed bands
SPIKE_COP = 8          # beyond-range heat above this COP is meter spikes
UPSIZE_BEYOND_H = 10   # sustained beyond-range hours that suggest a bigger unit
UPSIZE_REMOVED = 0.7   # share of them the upsized run has to remove

def pool(cells):
    return {k: sum(c[k] for c in cells) for k in cells[0]}


def hours(c):
    return c["n"] * 10 / 3600


def ratio(c, min_h):
    """Field COP / model COP, i.e. model elec / measured elec."""
    if hours(c) < min_h or c["e"] <= 0 or c["em"] <= 0:
        return np.nan
    return c["em"] / c["e"]


def metrics(d):
    m, st = d["map"], d["states"]
    band = lambda bs: pool([m[a][b] for a in range(len(m)) for b in bs])
    level = ratio(band([1, 2, 3, 4]), MIN_LEVEL_H)
    lo, hi = band([0]), band([5, 6])
    steady, beyond, off = st["steady"], st["beyond"], st["off"]
    ramp = st.get("ramp", {"n": 0})

    beyond_cop = beyond["hpos"] / beyond["e"] if beyond["e"] > 0 else np.inf
    ladder = [x["spf"] for x in d["ladder"]]
    runs = sorted(d["run_lengths_s"])

    r = {
        "id": d["system_id"],
        "qnom": d.get("qnom_override") or d["qnom_kw"],
        "spf": d["spf"],
        "xcheck": d["spf"] - d["published_spf"] if d["published_spf"] else np.nan,
        "replay_spf": ladder[0],
        "level": level,
        "min_ratio": ratio(lo, MIN_CELL_H),
        "min_penalty": ratio(lo, MIN_CELL_H) / level,
        "min_share": lo["n"] / steady["n"] if steady["n"] else np.nan,
        "rise": ratio(hi, MIN_CELL_H) / level,
        "steady_h": hours(steady),
        "ramp_share": ramp["n"] / (steady["n"] + ramp["n"]) if steady["n"] + ramp["n"] else np.nan,
        "steady_flowT": steady["hflowT"] / steady["hpos"] if steady["hpos"] else np.nan,
        "steady_dTfr": steady["sdT"] / steady["ndT"] if steady["ndT"] else np.nan,
        "beyond_h": hours(beyond),
        "beyond_real_h": hours(beyond) if beyond_cop < SPIKE_COP else 0.0,
        "standby_w": off["e"] / off["n"] if off["n"] else np.nan,
        "starts_per_day": d["starts"] / d["days"],
        "median_run_min": runs[len(runs) // 2] / 60 if runs else np.nan,
        "defrosts_per_day": d["defrosts"] / d["days"],
        "has_dhw": d["has_dhw"],
        "dhw_flag_dead": d["has_dhw"] and st["dhw"]["n"] == 0,
        "dhw_guessed": (not d["has_dhw"]) and d.get("dhw_guess", True),
    }
    for prev, cur in zip(d["ladder"], d["ladder"][1:]):
        r["step_" + cur["step"]] = cur["spf"] - prev["spf"]
    r["step_other"] = ladder[-1] - ladder[1]
    return r


def load_replays(replay_dir, model="v2"):
    """One row per system: declared run, or the upsized run when it fits better."""
    runs = {}
    for f in glob.glob(str(Path(replay_dir) / "replay_*.json")):
        suffix = "" if model == "v2" else "_m" + model
        mo = re.match(r"replay_(\d+)(?:_q([\d.]+))?" + suffix + r"\.json$", Path(f).name)
        if not mo:
            continue
        d = json.load(open(f))
        runs.setdefault(int(mo.group(1)), {})["up" if mo.group(2) else "declared"] = metrics(d)

    rows = []
    for sid, v in sorted(runs.items()):
        if "declared" not in v:
            continue
        r, up = dict(v["declared"]), v.get("up")
        r["declared_qnom"] = r["qnom"]
        r["upsized"] = False
        if (up is not None and r["beyond_real_h"] >= UPSIZE_BEYOND_H
                and up["beyond_real_h"] <= (1 - UPSIZE_REMOVED) * r["beyond_real_h"]):
            r = dict(up, declared_qnom=r["qnom"], upsized=True)
        rows.append(r)
    return pd.DataFrame(rows).set_index("id")


def describe(s, name, fmt="{:.2f}"):
    s = s.dropna()
    q = s.quantile([0.1, 0.25, 0.5, 0.75, 0.9])
    print(f"  {name:28} n={len(s):3d}  median " + fmt.format(q[0.5]) +
          "  IQR " + fmt.format(q[0.25]) + " to " + fmt.format(q[0.75]) +
          "  p10-p90 " + fmt.format(q[0.1]) + " to " + fmt.format(q[0.9]))


def spearman(x, y):
    ok = x.notna() & y.notna()
    if ok.sum() < 8:
        return np.nan, np.nan, int(ok.sum())
    rho, p = stats.spearmanr(x[ok], y[ok])
    return rho, p, int(ok.sum())


def main():
    args = sys.argv[1:]
    model = "v2"
    if "--model" in args:
        model = args[args.index("--model") + 1]
        del args[args.index("--model"):args.index("--model") + 2]
    csv_out = ROOT / "twins" / ("replay_fleet.csv" if model == "v2" else f"replay_fleet_{model}.csv")
    if "--csv" in args:
        csv_out = Path(args[args.index("--csv") + 1])
        del args[args.index("--csv"):args.index("--csv") + 2]
    replay_dir = args[0] if args else DEFAULT_DIR

    R = load_replays(replay_dir, model)
    print(f"{len(R)} systems from {replay_dir}, COP model {model}")
    if R.empty:
        return

    # quality and metadata
    fleet = pd.read_csv(ROOT / "hstar_fleet_hist.csv").set_index("id")
    R["quality"] = fleet["spf_over_H_fixed"].reindex(R.index)
    R["weighted_dT"] = fleet["calc_weighted_dT"].reindex(R.index)
    export = pd.read_csv(sorted(glob.glob(str(ROOT / "heatpumpmonitor_all_fields_*.csv")))[-1],
                         low_memory=False).set_index("id")
    targets = {int(t["id"]): t for t in json.load(open(ROOT / "twins" / "targets.json"))["targets"]}
    R["flow_rate"] = [targets.get(i, {}).get("flow_rate", np.nan) for i in R.index]
    meta = export.reindex(R.index)

    # data checks
    bad_x = R.xcheck.abs() > 0.02
    print(f"\nData checks")
    print(f"  scan vs published SPF off by more than 0.02: {int(bad_x.sum())}"
          + (f" ({', '.join(str(i) for i in R.index[bad_x])})" if bad_x.any() else ""))
    print(f"  no level (under {MIN_LEVEL_H} h at 30-70 rps): {int(R.level.isna().sum())}")
    print(f"  DHW flag feed never set: {int(R.dhw_flag_dead.sum())}"
          + (f" ({', '.join(str(i) for i in R.index[R.dhw_flag_dead])})" if R.dhw_flag_dead.any() else ""))
    print(f"  no DHW flag, DHW guessed from flowT: {int(R.dhw_guessed.sum())}")
    up = R[R.upsized]
    print(f"  run one size up (sustained output beyond the declared size): {len(up)}")
    for i, r in up.iterrows():
        print(f"    {i}: declared {r.declared_qnom:g} kW, run as {r.qnom:g} kW")

    G = R[R.level.notna() & ~bad_x]
    print(f"\n{len(G)} systems with a level and a clean cross-check")

    # 1. The parts, across the fleet
    print("\nPer-system parts")
    describe(G.level, "level (field/datasheet)")
    describe(G.min_penalty, "min-speed penalty")
    describe(G.min_share, "share at min speed")
    describe(G.rise, "70-120 rps vs level")
    describe(G.standby_w, "standby W", "{:.1f}")
    describe(G.defrosts_per_day, "defrosts a day", "{:.1f}")
    describe(G.ramp_share, "flowT ramps, share of steady")
    for step in ["steady", "ramp", "startup", "dhw", "defrost", "off", "other"]:
        describe(G["step_" + step], "SPF step: " + step, "{:+.2f}")

    # 2. Which part tracks quality?
    print("\nSpearman rho with quality (SPF / H_fixed)")
    for col in ["level", "min_penalty", "min_share", "rise", "replay_spf", "step_steady", "step_ramp", "step_startup",
                "ramp_share", "step_dhw", "step_defrost", "step_off", "standby_w", "starts_per_day", "median_run_min",
                "defrosts_per_day", "steady_dTfr", "steady_flowT", "flow_rate"]:
        rho, p, n = spearman(G[col].astype(float), G.quality)
        print(f"  {col:20} rho {rho:+.2f}  p {p:.3f}  n {n}")
    ok = G.quality.notna()
    if ok.sum() >= 8:
        r2 = np.corrcoef(np.log(G.level[ok]), np.log(G.quality[ok]))[0, 1] ** 2
        print(f"  log level explains {100 * r2:.0f}% of the variance in log quality")

    # 3. What predicts the level?
    print("\nWhat goes with the level (numeric, Spearman)")
    num = lambda c: pd.to_numeric(meta[c], errors="coerce").reindex(G.index)
    numeric = {
        "steady flow-return dT": G.steady_dTfr,
        "steady flowT": G.steady_flowT,
        "flow rate (L/min)": G.flow_rate,
        "capacity run (kW)": G.qnom,
        "upsized": G.upsized.astype(float),
        "standby W": G.standby_w,
        "starts a day": G.starts_per_day,
        "median run (min)": G.median_run_min,
        "weighted dT": G.weighted_dT,
        "latitude": num("latitude"),
        "oversizing factor": num("oversizing_factor"),
        "cylinder volume": num("cylinder_volume"),
        "annual heat kWh": num("combined_heat_kwh"),
        "quality_heat": num("quality_heat"),
    }
    for name, x in numeric.items():
        rho, p, n = spearman(x.astype(float), G.level)
        print(f"  {name:24} rho {rho:+.2f}  p {p:.3f}  n {n}")

    print("\nWhat goes with the level (categories with at least 5 systems; Kruskal-Wallis)")
    for col in ["heat_meter", "installer_name", "training", "heatgeek", "hydraulic_separation", "volumiser",
                "freeze", "metering_inc_central_heating_pumps", "metering_inc_secondary_heating_pumps",
                "metering_inc_controls", "metering_inc_immersion", "UFH", "new_radiators", "old_radiators",
                "dhw_method", "space_heat_control_type", "zone_number", "uses_backup_heater"]:
        if col not in meta:
            continue
        cat = meta[col].reindex(G.index).fillna("(blank)").astype(str)
        counts = cat.value_counts()
        keep = counts[counts >= 5].index
        if len(keep) < 2:
            continue
        groups = [G.level[cat == k] for k in keep]
        p = stats.kruskal(*groups).pvalue
        parts = ", ".join(f"{k[:28]} {G.level[cat == k].median():.2f} (n={counts[k]})" for k in keep)
        print(f"  {col:38} p {p:.3f}  | {parts}")

    # 4. Table and CSV
    cols = ["qnom", "upsized", "spf", "quality", "replay_spf", "level", "min_penalty", "min_share", "rise",
            "step_steady", "step_ramp", "step_other", "ramp_share", "standby_w", "starts_per_day", "defrosts_per_day", "steady_dTfr",
            "beyond_real_h", "has_dhw", "dhw_flag_dead"]
    print("\nPer system, poorest quality first")
    with pd.option_context("display.max_rows", 500, "display.width", 250, "display.float_format", "{:.2f}".format):
        print(R.sort_values("quality")[cols].to_string())
    R.to_csv(csv_out)
    print(f"\nwritten {csv_out}")


if __name__ == "__main__":
    main()
