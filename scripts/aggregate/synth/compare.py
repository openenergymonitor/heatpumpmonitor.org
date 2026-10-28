#!/usr/bin/env python3
"""
Compare aggregate output from baseline.php (JSON) and fast_aggregate.py (out dir).

  python3 compare.py BASELINE.json FAST_OUT_DIR [--offset MINUTES]

--offset: index in the fast output where the baseline window starts, for checking
one window of a multi-week fast run against a single-week baseline.
"""
import argparse, json, os, sys
import numpy as np

ap = argparse.ArgumentParser()
ap.add_argument("baseline")
ap.add_argument("fast")
ap.add_argument("--offset", type=int, default=0)
args = ap.parse_args()

base = json.load(open(args.baseline))
ok = True
for k in ["elec", "heat", "elec_kw_nrml", "heat_kw_nrml", "capacity", "outsideT", "numsys"]:
    b = np.array([np.nan if v is None else v for v in base[k]], dtype=np.float64)
    f = np.fromfile(os.path.join(args.fast, k + ".f64"), dtype="<f8")[args.offset:args.offset + len(b)]
    same_null = np.array_equal(np.isnan(b), np.isnan(f))
    m = ~np.isnan(b) & ~np.isnan(f)
    diff = np.abs(b[m] - f[m])
    rel = diff / np.maximum(1, np.abs(b[m]))
    exact = np.mean(diff == 0) * 100
    status = "OK" if same_null and rel.max(initial=0) < 1e-9 else "DIFF"
    if status != "OK": ok = False
    print(f"{k:13s} {status:4s} n={len(b)} exact={exact:6.2f}% max_abs={diff.max(initial=0):.3g} max_rel={rel.max(initial=0):.3g} nulls_match={same_null}")
    if status != "OK":
        bad = np.flatnonzero(rel > 1e-9)[:5] if len(rel) else []
        for i in np.flatnonzero(m)[bad]:
            print(f"    i={i} baseline={b[i]!r} fast={f[i]!r}")

summary = json.load(open(os.path.join(args.fast, "summary.json")))
bf = {str(k): v for k, v in (base["flagged"] or {}).items()}   # PHP encodes an empty array as []
ff = summary["flagged"]
if (summary["end"] - summary["start"]) // 60 == len(base["elec"]):
    cnt_ok = set(bf) == set(ff) and all(bf[k]["count"] == ff[k]["count"] and bf[k]["first"] == ff[k]["first"] and bf[k]["last"] == ff[k]["last"] for k in bf)
else:
    # fast run spans several windows: flags are summed over all of them
    cnt_ok = set(bf) <= set(ff)
print(f"flagged       {'OK' if cnt_ok else 'DIFF'} baseline={sorted(bf)} fast={sorted(ff)}")
ok = ok and cnt_ok
print("PASS" if ok else "FAIL")
sys.exit(0 if ok else 1)
