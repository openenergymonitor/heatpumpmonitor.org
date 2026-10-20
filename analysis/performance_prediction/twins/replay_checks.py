#!/usr/bin/env python3
"""Checks on the machine replay level: is the gap to the datasheet a heat
meter, pump or pipework effect?

Reads twins/replay_fleet.csv (from replay_fleet.py), the replay JSONs and the
pump-only scans (scripts/feed_scan/feed_scan_7.php via pumponly_fleet.sh):

  1. heat meter dT offset in pump-only running vs the level
  2. the gap as extra watts of electricity, by speed, and pump-only power
  3. primary loop volume and heat loss from the pump-only fit
  4. example systems at both ends of the level

Usage (from analysis/performance_prediction/):
  python3 twins/replay_checks.py
"""

import json
from pathlib import Path

import numpy as np
import pandas as pd
from scipy import stats

ROOT = Path(__file__).resolve().parent.parent
REPO = ROOT.parent.parent
REPLAY = REPO / "scripts" / "feed_scan" / "replay"
PUMPONLY = REPO / "scripts" / "feed_scan" / "pumponly"

DUPLICATES = [348]      # same feeds as 465
MIN_PUMPONLY_H = 20     # pump-only hours needed for the offset and loop fits
K_RANGE = (55, 80)      # heat / (flow x dT) for meter sensors and flow in L/min (water 69.7)
OUT_CENTRES = [-4, 0, 4.5, 9.5, 14]     # replay outsideT bins -99,-2,2,7,12,99
LEVEL_BANDS = [1, 2, 3, 4]              # 30-70 rps
BAND_LABELS = ["<30", "30-40", "40-50", "50-60", "60-70", "70-90", "90-120"]


def spread(s, f="{:+.0f}"):
    s = s.dropna()
    return (f"median {f.format(s.median())}  IQR {f.format(s.quantile(.25))} to {f.format(s.quantile(.75))}"
            f"  p10-p90 {f.format(s.quantile(.1))} to {f.format(s.quantile(.9))}")


def rho(x, y):
    ok = x.notna() & y.notna()
    r = stats.spearmanr(x[ok].astype(float), y[ok].astype(float))
    return f"rho {r[0]:+.2f} p {r[1]:.3f} n {int(ok.sum())}"


def pool(m, bands):
    return {k: sum(m[a][b][k] for a in range(len(m)) for b in bands) for k in m[0][0]}


def load():
    R = pd.read_csv(ROOT / "twins" / "replay_fleet.csv").set_index("id")
    R = R[R.level.notna() & (R.xcheck.abs() <= 0.02)].drop(index=DUPLICATES, errors="ignore")
    rows = {}
    for i in R.index:
        name = f"replay_{i}" + (f"_q{R.qnom[i]:g}" if R.upsized[i] else "") + ".json"
        d = json.load(open(REPLAY / name))
        m = d["map"]
        r = {"need_offset": (d.get("constancy") or {}).get("dT_offset_mean", np.nan)}
        c = pool(m, LEVEL_BANDS)
        r.update(e=c["e"] / c["n"], em=c["em"] / c["n"], h=c["hpos"] / c["n"],
                 flowT=c["hflowT"] / c["hpos"], dT=c["sdT"] / c["ndT"])
        nb = [sum(m[a][b]["n"] for b in LEVEL_BANDS) for a in range(len(m))]
        r["outT"] = sum(x * n for x, n in zip(OUT_CENTRES, nb)) / sum(nb)
        for b in range(len(BAND_LABELS)):
            c = pool(m, [b])
            ok = c["n"] * 10 / 3600 >= 5 and c["em"] > 0
            r[f"xw_b{b}"] = (c["e"] - c["em"]) / c["n"] if ok else np.nan
            r[f"e_b{b}"] = c["e"] / c["n"] if ok else np.nan

        f = PUMPONLY / f"pumponly_{i}.json"
        if f.exists():
            p = json.load(open(f))
            a, q = p["all"], p["quiet"]
            r.update(k=p["run"]["k"], run_dT=p["run"]["dT"], po_h=a["n"] / 360, quiet_h=q["n"] / 360,
                     po_flow=a["flow"] / a["n"] if a["n"] else np.nan,
                     po_w=a["e"] / a["n"] if a["n"] else np.nan,
                     quiet_dT=q["dT"] / q["n"] if q["n"] else np.nan,
                     fit_offset=p["fit"]["offset"] if p["fit"] else np.nan,
                     fit_loss=p["fit"]["loss"] if p["fit"] else np.nan,
                     fit_drift=p["fit"]["drift"] if p["fit"] else np.nan)
        rows[i] = r
    D = R.join(pd.DataFrame(rows).T)
    D["xw"] = D.e - D.em                  # extra W at 30-70 rps
    D["cop_field"] = D.h / D.e
    D["cop_ds"] = D.h / D.em
    D["missing_heat"] = D.xw * D.cop_ds   # the same gap in heat W: elec x datasheet COP - heat
    D["meter_ok"] = D.k.between(*K_RANGE)
    return D


def offsets(D):
    print("1. Heat meter dT offset in pump-only running")
    print(f"  {D.k.notna().sum()} systems with a pump-only scan; k = heat / (flow x dT): {spread(D.k, '{:.1f}')}")
    print(f"  k outside {K_RANGE} (not the meter's sensors, or flow not in L/min): "
          f"{list(D.index[D.k.notna() & ~D.meter_ok])}")
    G = D[D.meter_ok & (D.quiet_h >= MIN_PUMPONLY_H)].copy()
    print(f"  {len(G)} with {MIN_PUMPONLY_H}+ quiet pump-only hours")
    print(f"  quiet dT, K          {spread(G.quiet_dT, '{:+.3f}')}")
    print(f"  fit offset, K        {spread(G.fit_offset, '{:+.3f}')}")
    print(f"  needed offset, K     {spread(G.need_offset, '{:+.3f}')}  (to explain the whole gap)")
    G["err"] = G.quiet_dT / G.run_dT
    print(f"  heat error, %        {spread(100 * G.err, '{:+.1f}')}  (quiet dT / running dT)")
    print(f"  quiet dT vs level    {rho(G.quiet_dT, G.level)}")
    corr = G.level / (1 + G.err)
    print(f"  level sd {G.level.std():.3f}, corrected for the offset {corr.std():.3f}")
    for c in ["min_share", "steady_dTfr", "qnom"]:
        print(f"  {c:12} vs level {stats.spearmanr(G[c], G.level)[0]:+.2f}, "
              f"vs corrected level {stats.spearmanr(G[c], corr)[0]:+.2f}")


def watts(D):
    print("\n2. The gap as extra watts (steady running at 30-70 rps)")
    print(f"  measured elec W      {spread(D.e)}")
    print(f"  heat W               {spread(D.h)}")
    print(f"  extra W              {spread(D.xw)}")
    e = D.e.median()
    print(f"  at {e:.0f} W input, 10 W is {100 * 10 / e:.1f}% of level and 100 W is {100 * 100 / e:.0f}%")
    print("  by speed band, fleet medians (a fixed load would be flat in W):")
    for b, lab in enumerate(BAND_LABELS):
        x, e = D[f"xw_b{b}"], D[f"e_b{b}"]
        print(f"    {lab:7} extra {x.median():+5.0f} W  measured {e.median():5.0f} W  extra {100 * (x / e).median():+.1f}%")
    W = D[[f"xw_b{b}" for b in LEVEL_BANDS]].values
    E = D[[f"e_b{b}" for b in LEVEL_BANDS]].values
    cw, cp = [], []
    for w, e in zip(W, E):
        ok = ~np.isnan(w) & ~np.isnan(e)
        if ok.sum() >= 3 and np.mean(w[ok]) != 0:
            cw.append(np.std(w[ok]) / abs(np.mean(w[ok])))
            cp.append(np.std(w[ok] / e[ok]) / abs(np.mean(w[ok] / e[ok])))
    print(f"  within a system, sd/mean across 30-70 rps: extra W {np.median(cw):.2f}, extra % {np.median(cp):.2f}")
    P = D[D.po_h >= MIN_PUMPONLY_H]
    print(f"  pump-only elec W     {spread(P.po_w)}  (pump + controls, compressor off)")
    print(f"  pump-only W vs extra W {rho(P.po_w, P.xw)}")


def loop(D):
    print("\n3. Primary loop from the pump-only fit")
    G = D[D.meter_ok & (D.po_h >= MIN_PUMPONLY_H)].copy()
    G["UA"] = -G.fit_loss * G.po_flow * G.k                  # W/K
    G["tau"] = -G.fit_drift                                  # min, return to flow sensor
    G["vol"] = G.tau * G.po_flow                             # L
    G["water_out"] = G.flowT - G.dT / 2 - G.outT
    G["UA_needed"] = G.missing_heat / G.water_out
    print(f"  {len(G)} systems")
    print(f"  delay, min           {spread(G.tau, '{:+.2f}')}")
    print(f"  loop volume, L       {spread(G.vol)}")
    print(f"  UA, W/K              {spread(G.UA, '{:+.1f}')}  (negative values are noise)")
    print(f"  missing heat, W      {spread(G.missing_heat)}")
    print(f"  UA needed, W/K       {spread(G.UA_needed, '{:+.1f}')}  (to explain the whole gap)")
    print(f"  volume vs level {rho(G.vol, G.level)};  UA vs level {rho(G.UA, G.level)}")


def examples(D):
    print("\n4. Examples (30-70 rps steady running)")
    cols = ["qnom", "declared_qnom", "spf", "level", "cop_field", "cop_ds", "xw", "missing_heat", "flowT",
            "min_share", "starts_per_day", "has_dhw", "dhw_flag_dead"]
    with pd.option_context("display.width", 250, "display.float_format", "{:.2f}".format):
        print("  lowest level")
        print(D.sort_values("level")[cols].head(6).to_string())
        print("  highest level, 5 and 7 kW, not upsized")
        print(D[D.qnom.isin([5, 7]) & ~D.upsized].sort_values("level")[cols].tail(5).to_string())
        print("  highest level, 10 and 12 kW")
        print(D[D.qnom >= 10].sort_values("level")[cols].tail(5).to_string())
        print("  median level by size:", D.groupby("qnom")["level"].median().round(3).to_dict())


def main():
    D = load()
    print(f"{len(D)} systems with a level and a clean cross-check\n")
    offsets(D)
    watts(D)
    loop(D)
    examples(D)


if __name__ == "__main__":
    main()
