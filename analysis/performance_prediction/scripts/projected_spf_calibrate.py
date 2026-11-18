"""Calibrate the projected annual SPF model used by scripts/projected_spf.php.

A system with less than a year of data is projected from its first K days:

    log(annual SPF) = c0 + c1·log(COP so far) + c2·ΔT + c3·outsideT + c4·hot water share

ΔT (flow − outside) and outsideT are heat-weighted over the days so far. There is
one set of coefficients per (start week, K days) cell, fitted on all start dates
within ±14 days of that week, because how much of the year's heating is still to
come depends on the calendar. Each year of daily data is treated as circular.

Calibration uses two full years (two sets of weather). The error band in each
cell comes from cross-year residuals: fit on one year, predict the other, both
ways, then take the 10th/90th percentile of log(predicted / actual).

Usage:
    python3 projected_spf_calibrate.py --fetch DATA_DIR      # download daily CSVs
    python3 projected_spf_calibrate.py DATA_DIR [OUT_JSON]   # fit, write model

DATA_DIR holds systems.json (home/predictors_systems) and year0/, year1/ with
one <id>.csv per system from system/stats/daily. Default OUT_JSON is
scripts/projected_spf_model.json.
"""
import csv, json, os, sys, time, datetime as dtm, urllib.request
import numpy as np

HOST = "https://heatpumpmonitor.org"
FIELDS = ("timestamp,combined_heat_kwh,combined_elec_kwh,water_heat_kwh,"
          "weighted_flowT_minus_outsideT,weighted_outsideT,cooling_heat_kwh,combined_data_length")
# Two variants: "full" uses ΔT and outside temperature, "notemp" is the fallback
# for systems without an outside temperature sensor.
VARIANTS = {"full": [0, 1, 2, 3, 4], "notemp": [0, 1, 4]}
FEATURES = ["1", "log(cop)", "weighted_flowT_minus_outsideT", "weighted_outsideT", "water_share"]
KS = [7, 10, 14, 21, 30, 45, 60, 90, 120, 150, 180, 240, 300, 330]
NEIGHBOURHOOD = 14   # days either side of the start week pooled into each cell
REPO = os.path.abspath(os.path.join(os.path.dirname(__file__), "../../.."))


def get(url):
    req = urllib.request.Request(url, headers={"User-Agent": "hpmon-analysis"})
    return urllib.request.urlopen(req, timeout=60).read()


def fetch(data_dir):
    """Last two complete 365-day years, each starting at 00:00 UTC."""
    os.makedirs(data_dir, exist_ok=True)
    systems = json.loads(get(f"{HOST}/home/predictors_systems"))
    json.dump(systems, open(f"{data_dir}/systems.json", "w"))
    end = int(time.time() // 86400 * 86400)
    meta = {}
    for y in (0, 1):
        start = end - 365 * 86400 * (2 - y)
        stop = start + 365 * 86400
        meta[f"year{y}"] = start
        os.makedirs(f"{data_dir}/year{y}", exist_ok=True)
        for s in systems:
            url = f"{HOST}/system/stats/daily?id={s['id']}&start={start}&end={stop}&fields={FIELDS}"
            open(f"{data_dir}/year{y}/{s['id']}.csv", "wb").write(get(url))
            time.sleep(0.2)
    json.dump(meta, open(f"{data_dir}/years.json", "w"))


def num(x):
    try:
        return float(x)
    except (TypeError, ValueError):
        return np.nan


def samples(path, t0, ids):
    """One row per (system, start, K): id, start, K, annual SPF, COP so far, ΔT, outsideT,
    water share, has temperatures. A day counts if it was monitored, with or without
    heat, so standby electricity and summer shutdowns are included."""
    rows = []
    for i in ids:
        p = f"{path}/{i}.csv"
        if not os.path.exists(p):
            continue
        R = list(csv.DictReader(open(p)))
        if len(R) < 330:
            continue
        a = np.array([[num(r.get(k)) for k in FIELDS.split(",")] for r in R])
        if np.any(np.abs(np.nan_to_num(a[:, 6])) >= 1):
            continue   # cooling: weighted stats count cooling heat as heat
        g = np.full((365, 6), np.nan)
        d = ((a[:, 0] - t0) // 86400).astype(int)
        k = (d >= 0) & (d < 365)
        g[d[k]] = a[k][:, [1, 2, 3, 4, 5, 7]]
        h, e, wat, dt, to, dl = g.T
        ok = (dl > 0) & np.isfinite(e) & (e >= 0)
        h = np.where(ok & np.isfinite(h), np.maximum(h, 0), 0)
        hd = ok & (h > 0)
        if ok.sum() < 330 or e[ok].sum() <= 0:
            continue
        spf = h[ok].sum() / e[ok].sum()
        okt = hd & np.isfinite(dt) & np.isfinite(to) & (dt > 0)
        cols = [h, np.where(ok, e, 0), np.where(hd, np.nan_to_num(wat), 0),
                np.where(okt, h * dt, 0), np.where(okt, h * to, 0), np.where(okt, h, 0),
                ok * 1.0, hd * 1.0, okt * 1.0]
        C = [np.concatenate([[0], np.cumsum(np.tile(c, 2))]) for c in cols]
        for st in range(0, 365, 7):
            for K in KS:
                H, E, W, HDT, HTO, HT, N, NH, NT = [c[st + K] - c[st] for c in C]
                if N < 0.6 * K or E <= 0 or H <= 0:
                    continue
                has_t = NT >= 0.5 * NH and HT > 0
                rows.append((i, st, K, spf, H / E, HDT / HT if has_t else np.nan,
                             HTO / HT if has_t else np.nan, W / H, has_t))
    return np.array(rows)


def X(R, variant="full"):
    full = np.column_stack([np.ones(len(R)), np.log(R[:, 4]), R[:, 5], R[:, 6], R[:, 7]])
    return full[:, VARIANTS[variant]]


def cells(R):
    """Row masks per (start week, K): start within ±NEIGHBOURHOOD days of the week."""
    for w in range(52):
        s0 = w * 7
        dd = np.abs(((R[:, 1] - s0 + 182) % 365) - 182)
        for K in KS:
            yield w, K, (dd <= NEIGHBOURHOOD) & (R[:, 2] == K)


def fit(R, variant):
    co = {}
    for w, K, m in cells(R):
        co[(w, K)] = np.linalg.lstsq(X(R[m], variant), np.log(R[m, 3]), rcond=None)[0]
    return co


def log_errors(co, R, variant):
    w = (R[:, 1] // 7).astype(int) % 52
    B = np.array([co[(wi, int(k))] for wi, k in zip(w, R[:, 2])])
    return np.sum(X(R, variant) * B, axis=1) - np.log(R[:, 3])


def calibrate(data_dir, out):
    systems = json.load(open(f"{data_dir}/systems.json"))
    years = json.load(open(f"{data_dir}/years.json"))
    ids = [s["id"] for s in systems]
    Y = [samples(f"{data_dir}/year{y}", years[f"year{y}"], ids) for y in (0, 1)]
    for y in (0, 1):
        print(f"year{y}: {len(np.unique(Y[y][:, 0]))} systems, {len(Y[y])} samples")

    t0 = dtm.datetime.fromtimestamp(years["year0"], dtm.timezone.utc)
    models = {}
    for variant in VARIANTS:
        # the full model is fitted and tested on windows with temperatures; the
        # fallback on all windows, as if the temperatures weren't there
        V = [y[y[:, 8] == 1] if variant == "full" else y for y in Y]
        # cross-year residuals for the error bands (each year's start aligned on the same calendar day)
        E = [log_errors(fit(V[1], variant), V[0], variant), log_errors(fit(V[0], variant), V[1], variant)]
        R_all = np.vstack(V)
        e_all = np.concatenate(E)
        within = np.mean(np.abs(np.exp(e_all) - 1) <= 0.10)
        print(f"{variant}: cross-year {100 * within:.1f}% of samples within ±10%")

        co = fit(R_all, variant)
        coef, band = [], []
        for w in range(52):
            coef.append([])
            band.append([])
            for K in KS:
                m = next(mm for ww, kk, mm in cells(R_all) if ww == w and kk == K)
                e = e_all[m]
                coef[w].append([round(float(c), 6) for c in co[(w, K)]])
                # interval on the actual SPF: actual = predicted / exp(e)
                band[w].append([round(float(np.percentile(e, 10)), 4),
                                round(float(np.percentile(e, 90)), 4),
                                round(float(np.mean(np.abs(np.exp(e) - 1) <= 0.10)), 3)])
        models[variant] = {"features": [FEATURES[j] for j in VARIANTS[variant]], "coef": coef, "band": band}
        summary(variant, R_all, e_all, t0)

    model = {
        "description": "Projected annual SPF from the first K days of data. See analysis/performance_prediction/scripts/projected_spf_calibrate.py",
        "calibrated": dtm.date.today().isoformat(),
        "years": [dtm.datetime.fromtimestamp(years[f"year{y}"], dtm.timezone.utc).date().isoformat() for y in (0, 1)],
        "systems": int(len(np.unique(np.vstack(Y)[:, 0]))),
        # calibration fleet is UK, Ireland and north-west Europe: only project inside this box
        "region": {"lat": [47, 61], "lon": [-11, 15]},
        "start_doy": [(t0 + dtm.timedelta(days=7 * w)).timetuple().tm_yday for w in range(52)],
        "days": KS,
        "models": models,
        "band_note": "[p10, p90] of log(predicted / actual) from cross-year tests, and share within ±10%",
    }
    json.dump(model, open(out, "w"), separators=(",", ":"))
    print("wrote", out)


def summary(variant, R_all, e_all, t0):
    """Share within ±10% and p10–p90 half-width by start month and K."""
    mon = np.array([(t0 + dtm.timedelta(days=int(x))).month for x in R_all[:, 1]])
    show = [7, 14, 30, 60, 90, 120, 180, 240]
    print(f"\n[{variant}] share within ±10% (cross-year) by start month × days")
    print("mon " + " ".join(f"{k:>4}" for k in show))
    for mth in [12, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]:
        print(f"{mth:>3} " + " ".join(f"{100 * np.mean(np.abs(np.exp(e_all[(mon == mth) & (R_all[:, 2] == k)]) - 1) <= .1):4.0f}" for k in show))
    print("\nhalf-width of the p10–p90 band (%) by start month × days")
    for mth in [12, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]:
        out_row = []
        for k in show:
            e = e_all[(mon == mth) & (R_all[:, 2] == k)]
            out_row.append(50 * (np.percentile(e, 90) - np.percentile(e, 10)))
        print(f"{mth:>3} " + " ".join(f"{v:4.1f}" for v in out_row))


if __name__ == "__main__":
    if len(sys.argv) > 2 and sys.argv[1] == "--fetch":
        fetch(sys.argv[2])
    elif len(sys.argv) > 1:
        calibrate(sys.argv[1], sys.argv[2] if len(sys.argv) > 2 else f"{REPO}/scripts/projected_spf_model.json")
    else:
        print(__doc__)
