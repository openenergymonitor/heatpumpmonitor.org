#!/usr/bin/env python3
"""How much do the machine replay results depend on the datasheet COP model?

Compares the replay_fleet.py outputs for the three Arotherm+ fits in
vaillant_cop_fit.js (all pre-defrost):

  v2       copFitGenericV2, speed-based generic fit (the default)
  generic  copFitGeneric, capacity-normalised generic fit
  unit     copFit 5 kW fit up to 7 kW, 12 kW fit from 10 kW, load fraction
           against the system's capacity (exact only for 5 and 12 kW)

Speed bands are the same in all three (inferred from the v2 capacity model).

Usage (from analysis/performance_prediction/), after
  scripts/feed_scan/replay_fleet.sh "" 3 <model>
  python3 twins/replay_fleet.py --model <model>
for generic and unit:
  python3 twins/replay_models.py
"""

from pathlib import Path

import numpy as np
import pandas as pd
from scipy import stats

ROOT = Path(__file__).resolve().parent.parent
MODELS = {"v2": "replay_fleet.csv", "generic": "replay_fleet_generic.csv", "unit": "replay_fleet_unit.csv"}
DUPLICATES = [348]      # same feeds as 465


def load():
    out = {}
    for m, f in MODELS.items():
        p = ROOT / "twins" / f
        if not p.exists():
            print(f"missing {p}, skipping {m}")
            continue
        R = pd.read_csv(p).set_index("id")
        out[m] = R[R.level.notna() & (R.xcheck.abs() <= 0.02)].drop(index=DUPLICATES, errors="ignore")
    # same systems in every model
    common = sorted(set.intersection(*(set(R.index) for R in out.values())))
    return {m: R.loc[common] for m, R in out.items()}, common


def q(s, f="{:.3f}"):
    s = s.dropna()
    return f"{f.format(s.median())} ({f.format(s.quantile(.25))} to {f.format(s.quantile(.75))})"


def main():
    M, ids = load()
    print(f"{len(ids)} systems with a level and a clean cross-check in every model\n")

    print("The parts by model: median (IQR)")
    rows = []
    for m, R in M.items():
        ok = R.quality.notna()
        r2 = np.corrcoef(np.log(R.level[ok]), np.log(R.quality[ok]))[0, 1] ** 2
        rows.append({"model": m, "level": q(R.level), "level sd": f"{R.level.std():.3f}",
                     "min-speed penalty": q(R.min_penalty), "70-120 rps vs level": q(R.rise),
                     "replay SPF": q(R.replay_spf, "{:.2f}"),
                     "level~quality rho": f"{stats.spearmanr(R.level, R.quality, nan_policy='omit')[0]:+.2f}",
                     "log R2": f"{100 * r2:.0f}%"})
    print(pd.DataFrame(rows).set_index("model").to_string())

    print("\nMedian level by size")
    print(pd.DataFrame({m: R.groupby("qnom")["level"].median() for m, R in M.items()}).round(3)
          .assign(n=M["v2"].groupby("qnom").size()).to_string())

    print("\nDo the models rank the systems the same way? (Spearman of levels)")
    names = list(M)
    for a in range(len(names)):
        for b in range(a + 1, len(names)):
            x, y = M[names[a]].level, M[names[b]].level
            print(f"  {names[a]:8} vs {names[b]:8} rho {stats.spearmanr(x, y)[0]:.3f}"
                  f"  median |diff| {np.median(np.abs(x - y)):.3f}  median diff {np.median(y - x):+.3f}")
            for s in [5, 7, 10, 12]:
                sel = M["v2"].qnom == s
                if sel.sum() >= 5:
                    print(f"      {s:>4g} kW  n {sel.sum():3d}  rho {stats.spearmanr(x[sel], y[sel])[0]:.3f}"
                          f"  median diff {np.median((y - x)[sel]):+.3f}")

    print("\nWhat goes with the level, by model (Spearman rho)")
    rows = []
    for m, R in M.items():
        r = {"model": m}
        for s in [5, 7]:
            g = R[R.qnom == s]
            r[f"min_share {s} kW"] = stats.spearmanr(g.min_share, g.level)[0]
            r[f"dT {s} kW"] = stats.spearmanr(g.steady_dTfr, g.level)[0]
        r["capacity"] = stats.spearmanr(R.qnom, R.level)[0]
        rows.append(r)
    print(pd.DataFrame(rows).set_index("model").round(2).to_string())

    print("\nLowest and highest 5 by level in each model")
    for m, R in M.items():
        lo = list(R.sort_values("level").index[:5])
        hi = list(R[R.qnom.isin([5, 7]) & ~R.upsized].sort_values("level").index[-5:][::-1])
        print(f"  {m:8} lowest {lo}  highest 5/7 kW {hi}")
    cols = {m: R.level for m, R in M.items()}
    picks = [371, 741, 269, 516, 238, 260, 654, 409, 211, 335, 752, 643]
    print("\nThe doc's example systems")
    print(pd.DataFrame(cols).loc[[i for i in picks if i in ids]]
          .assign(qnom=M["v2"].qnom).round(3).to_string())


if __name__ == "__main__":
    main()
