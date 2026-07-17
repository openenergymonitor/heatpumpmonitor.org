#!/usr/bin/env python3
"""Analyse hstar_fleet.csv produced by feed_scan_4.php.

Filters to air source systems, validates the feed-scan weighted temperatures
against the recorded stats, and compares how well weighted dT, fixed-offset
harmonic Carnot (H) and load-dependent H* predict SPF across the fleet.
See analysis/performance_prediction/docs/05_harmonic_carnot_metric.md

Usage: python3 analyse_hstar_fleet.py [hstar_fleet.csv]

Pure stdlib, no dependencies.
"""

import csv
import math
import os
import sys

# Systems where the scan disagrees with the recorded weighted dT. These are
# almost all cooling systems: the published pipeline inverts cooling's
# negative heat to positive and includes it in combined_cop and the weighted
# temperature sums, while the feed scan excludes it (h > 0). A few others
# have very low quality_outsideT. See docs/09_hstar_fleet_results.md.
WINDOW_MISMATCH_K = 0.5


def fnum(row, key):
    v = row.get(key, "")
    if v is None or v == "":
        return None
    try:
        return float(v)
    except ValueError:
        return None


def mean(xs):
    return sum(xs) / len(xs) if xs else None


def sd(xs):
    if len(xs) < 2:
        return None
    m = mean(xs)
    return math.sqrt(sum((x - m) ** 2 for x in xs) / (len(xs) - 1))


def linfit(pairs):
    """OLS y = a + b*x. Returns (a, b, r2, resid_sd, pi90)."""
    n = len(pairs)
    if n < 3:
        return None
    sx = sum(p[0] for p in pairs)
    sy = sum(p[1] for p in pairs)
    sxx = sum(p[0] * p[0] for p in pairs)
    sxy = sum(p[0] * p[1] for p in pairs)
    den = n * sxx - sx * sx
    if den == 0:
        return None
    b = (n * sxy - sx * sy) / den
    a = (sy - b * sx) / n
    resid = [y - (a + b * x) for x, y in pairs]
    ss_res = sum(r * r for r in resid)
    my = sy / n
    ss_tot = sum((y - my) ** 2 for _, y in pairs)
    r2 = 1 - ss_res / ss_tot if ss_tot > 0 else 0.0
    rsd = math.sqrt(ss_res / (n - 2))
    return a, b, r2, rsd, 1.645 * rsd


def analyse(rows, label):
    print(f"\n=== {label} (n={len(rows)}) ===")
    if len(rows) < 3:
        print("  too few systems")
        return

    pred = [
        ("weighted dT (current)", lambda r: -r["w_dT"]),
        ("H  (fixed +2/-6)", lambda r: r["H_fixed"]),
        ("H* (variable +3r/-8r)", lambda r: r["H_star"]),
    ]
    print("SPF prediction:")
    for name, fx in pred:
        fit = linfit([(fx(r), r["spf"]) for r in rows])
        if fit:
            _, _, r2, rsd, pi90 = fit
            print(f"  SPF ~ {name:24s} R2 = {r2:5.3f}   resid sd = {rsd:5.3f}   90% PI = +/-{pi90:4.2f}")

    print("Quality score spread:")
    for name, xs in [
        ("SPF / H  (prc carnot)", [r["spf"] / r["H_fixed"] for r in rows]),
        ("SPF / H* (corrected)", [r["spf"] / r["H_star"] for r in rows]),
        ("recorded combined_prc_carnot", [r["rec_prc"] / 100.0 for r in rows if r["rec_prc"] is not None]),
    ]:
        if xs:
            q = sorted(xs)
            print(f"  {name:29s} mean {mean(xs):5.3f}  sd {sd(xs):5.3f}  "
                  f"median {q[len(q)//2]:5.3f}  range {q[0]:5.3f}..{q[-1]:5.3f}")


def main():
    path = sys.argv[1] if len(sys.argv) > 1 else os.path.join(os.path.dirname(__file__), "hstar_fleet.csv")
    with open(path, newline="") as f:
        raw = list(csv.DictReader(f))
    print(f"{path}: {len(raw)} systems")

    rows = []
    excluded_type = {}
    for r in raw:
        hp_type = (r.get("hp_type") or "").strip()
        if hp_type != "Air Source":
            excluded_type[hp_type] = excluded_type.get(hp_type, 0) + 1
            continue
        d = {
            "id": r["id"],
            "H_star": fnum(r, "H_star"),
            "H_fixed": fnum(r, "H_fixed"),
            "spf": fnum(r, "spf_window"),
            "w_flowT": fnum(r, "calc_weighted_flowT"),
            "rec_flowT": fnum(r, "rec_weighted_flowT"),
            "w_outT": fnum(r, "calc_weighted_outsideT"),
            "rec_outT": fnum(r, "rec_weighted_outsideT"),
            "w_dT": fnum(r, "calc_weighted_dT"),
            "rec_dT": fnum(r, "rec_weighted_dT"),
            "w_dTfr": fnum(r, "calc_weighted_flowT_minus_returnT"),
            "rec_dTfr": fnum(r, "rec_weighted_flowT_minus_returnT"),
            "rec_cop": fnum(r, "rec_combined_cop"),
            "rec_prc": fnum(r, "rec_combined_prc_carnot"),
            "days": fnum(r, "days_scanned"),
        }
        if d["H_star"] is None or d["spf"] is None:
            continue
        rows.append(d)

    if excluded_type:
        print("Excluded by hp_type: " + ", ".join(f"{k or '(blank)'}: {v}" for k, v in sorted(excluded_type.items())))
    print(f"Air source systems with H* and SPF: {len(rows)}")

    # --- Validation: calculated vs recorded weighted temperatures ---
    print("\n=== Validation: feed scan vs recorded weighted stats ===")
    for name, ck, rk in [
        ("weighted_flowT", "w_flowT", "rec_flowT"),
        ("weighted_outsideT", "w_outT", "rec_outT"),
        ("weighted_dT", "w_dT", "rec_dT"),
        ("weighted_flowT-returnT", "w_dTfr", "rec_dTfr"),
    ]:
        deltas = [r[ck] - r[rk] for r in rows if r[rk] is not None and r[ck] is not None]
        if not deltas:
            continue
        ab = [abs(d) for d in deltas]
        within = sum(1 for d in ab if d <= WINDOW_MISMATCH_K)
        print(f"  {name:20s} n={len(deltas):3d}  mean err {mean(deltas):+6.3f}K  "
              f"mean |err| {mean(ab):5.3f}K  max |err| {max(ab):5.2f}K  "
              f"within {WINDOW_MISMATCH_K}K: {100 * within / len(deltas):.0f}%")

    # --- Split off window-mismatch systems ---
    def mismatched(r):
        return r["rec_dT"] is not None and abs(r["w_dT"] - r["rec_dT"]) > WINDOW_MISMATCH_K

    bad = [r for r in rows if mismatched(r)]
    good = [r for r in rows if not mismatched(r)]
    if bad:
        print(f"\nMismatch systems (|calc dT - rec dT| > {WINDOW_MISMATCH_K}K), "
              f"mostly cooling systems (see docs/09):")
        for r in sorted(bad, key=lambda r: -abs(r["w_dT"] - r["rec_dT"])):
            print(f"  {r['id']:>5s}  dT calc {r['w_dT']:5.2f} rec {r['rec_dT']:5.2f}  "
                  f"SPF {r['spf']:4.2f} rec COP {r['rec_cop'] if r['rec_cop'] is not None else float('nan'):4.2f}  "
                  f"days {r['days']:.0f}")

    # --- Headline comparison ---
    analyse(rows, "All air source")
    if bad:
        analyse(good, "Air source, clean windows only")


if __name__ == "__main__":
    main()
