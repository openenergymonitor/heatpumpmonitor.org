"""Does the physical outside-temperature model (www/views/outsidetemp.php)
project annual SPF better than the empirical projected SPF model?

Same samples as projected_spf_calibrate.py: each system's year of daily data,
treated as circular, with a window of K days starting every week.

  physical   fit on the window as on the page (space heat line with a balance
             temperature, hot water line or constant, Carnot COP with fitted η,
             standby per day), run over the system's own year of outside
             temperatures. No fleet calibration.
  empirical  the per-cell regression from projected_spf_calibrate.py, fitted
             on the other year (cross-year).
  hybrid     empirical features + log(physical projection), cross-year.

Usage:
    python3 physical_projection_test.py --fetch DATA_DIR   # download daily CSVs
    python3 physical_projection_test.py DATA_DIR

DATA_DIR has the same layout as for projected_spf_calibrate.py, with extra
daily fields. Physical projections are cached in DATA_DIR/phys_cache.npz.
"""
import csv, json, sys, os, time, importlib.util, urllib.request
import datetime as dtm
from concurrent.futures import ThreadPoolExecutor
import numpy as np

HERE = os.path.dirname(os.path.abspath(__file__))
spec = importlib.util.spec_from_file_location("cal", f"{HERE}/projected_spf_calibrate.py")
cal = importlib.util.module_from_spec(spec); spec.loader.exec_module(cal)
KS = [7, 14, 30, 60, 90, 120, 180]
cal.KS = KS

FIELDS = ("timestamp,combined_heat_kwh,combined_elec_kwh,water_heat_kwh,weighted_flowT_minus_outsideT,weighted_outsideT,"
          "cooling_heat_kwh,combined_data_length,combined_outsideT_mean,space_heat_kwh,space_elec_kwh,water_elec_kwh,"
          "space_flowT_mean,water_flowT_mean,running_flowT_mean")


def fetch(d):
    """Last two complete 365-day years, as in projected_spf_calibrate.py."""
    os.makedirs(d, exist_ok=True)
    systems = json.loads(cal.get(f"{cal.HOST}/home/predictors_systems"))
    json.dump(systems, open(f"{d}/systems.json", "w"))
    end = int(time.time() // 86400 * 86400)
    meta, jobs = {}, []
    for y in (0, 1):
        start = end - 365 * 86400 * (2 - y)
        meta[f"year{y}"] = start
        os.makedirs(f"{d}/year{y}", exist_ok=True)
        for s in systems:
            jobs.append((f"{cal.HOST}/system/stats/daily?id={s['id']}&start={start}&end={start + 365 * 86400}&fields={FIELDS}",
                         f"{d}/year{y}/{s['id']}.csv"))
    json.dump(meta, open(f"{d}/years.json", "w"))
    def run(j):
        if not os.path.exists(j[1]):
            open(j[1], "wb").write(cal.get(j[0]))
    with ThreadPoolExecutor(4) as ex:
        list(ex.map(run, jobs))


if len(sys.argv) > 2 and sys.argv[1] == "--fetch":
    fetch(sys.argv[2]); sys.exit()
if len(sys.argv) < 2:
    print(__doc__); sys.exit()
DATA = sys.argv[1]

COND, EVAP, TB_PRIOR = 2.0, -6.0, 15.5
TB_GRID = np.arange(12, 20.001, 0.1)


def num(x):
    try: return float(x)
    except (TypeError, ValueError): return np.nan


def carnot(flow, t):
    tc = flow + COND; te = t + EVAP
    return (tc + 273.15) / np.maximum(tc - te, 5)


def wls(x, y, w=None):
    w = np.ones_like(x) if w is None else w
    sw = w.sum()
    if sw <= 0: return None
    sx, sy, sxx, sxy = (w * x).sum(), (w * y).sum(), (w * x * x).sum(), (w * x * y).sum()
    d = sw * sxx - sx * sx
    if abs(d) < 1e-9 * sw * sw: return sy / sw, 0.0
    b = (sw * sxy - sx * sy) / d
    return (sy - b * sx) / sw, b


def load_year(path, t0):
    """365-day grid of the page's day quantities, same day index as the calibrate script."""
    R = list(csv.DictReader(open(path)))
    g = {k: np.full(365, np.nan) for k in ["t", "dl", "H", "E", "sh", "se", "wh", "we", "fs", "fw", "fr", "cool"]}
    src = {"t": "combined_outsideT_mean", "dl": "combined_data_length", "H": "combined_heat_kwh", "E": "combined_elec_kwh",
           "sh": "space_heat_kwh", "se": "space_elec_kwh", "wh": "water_heat_kwh", "we": "water_elec_kwh",
           "fs": "space_flowT_mean", "fw": "water_flowT_mean", "fr": "running_flowT_mean", "cool": "cooling_heat_kwh"}
    for r in R:
        d = int((num(r["timestamp"]) - t0) // 86400)
        if 0 <= d < 365:
            for k, f in src.items(): g[k][d] = num(r.get(f))
    split = np.nansum(g["wh"]) > 0
    H = np.nan_to_num(g["H"]); E = np.nan_to_num(g["E"])
    if split:
        Hw = np.nan_to_num(g["wh"]); Ew = np.nan_to_num(g["we"])
        Hs = np.where(np.isfinite(g["sh"]), g["sh"], np.maximum(0, H - Hw)); Es = np.nan_to_num(g["se"])
        fs = g["fs"]
    else:
        Hw = np.zeros(365); Ew = np.zeros(365); Hs = H.copy(); Es = E.copy()
        fs = np.where(np.isfinite(g["fs"]) & (g["fs"] > 0), g["fs"], g["fr"])
    Es = np.where(Hs < 0.3, 0, Es)
    idle = np.maximum(0, E - Es - Ew)
    ok = (g["dl"] >= 0.9 * 86400) & np.isfinite(g["t"])
    return dict(t=g["t"], ok=ok, Hs=Hs, Es=Es, Hw=Hw, Ew=Ew, idle=idle, fs=fs, fw=g["fw"], split=split)


def fit_cop(t, Hh, Ee, F):
    use = (Hh > 0.5) & (Ee > 0)
    if not use.any(): return None
    wf = use & (F > 0)
    if wf.sum() >= 3 and wf.sum() >= 0.5 * use.sum():
        a, b = wls(t[wf], F[wf], Hh[wf])
        eta = (Hh[wf] / carnot(F[wf], t[wf])).sum() / Ee[wf].sum()
        return lambda x: eta * carnot(a + b * x, x)
    a, b = wls(t[use], Hh[use] / Ee[use], Hh[use])
    return lambda x: np.maximum(1, a + b * x)


def phys_projection(Y, idx, tyear):
    """Fit on window day indices idx, return projected SPF over tyear (array of outside T) or nan."""
    m = Y["ok"][idx]
    w = idx[m]
    if len(w) < 3: return np.nan
    t = Y["t"][w]; Hs = Y["Hs"][w]
    if ((Hs > 0.5)).sum() < 5: return np.nan
    grid = TB_GRID if (t > 12).sum() >= 7 else np.array([TB_PRIOR])
    X = np.maximum(0, grid[None, :] - t[:, None])
    sxy = X.T @ Hs; sxx = (X * X).sum(0)
    good = sxx > 0
    if not good.any(): return np.nan
    sse = np.where(good, (Hs * Hs).sum() - sxy ** 2 / np.where(good, sxx, 1), np.inf)
    j = int(np.argmin(sse)); tb = grid[j]; k = sxy[j] / sxx[j]
    Hw = Y["Hw"][w]
    if t.max() - t.min() >= 10: wa, wb = wls(t, Hw)
    else: wa, wb = Hw.mean(), 0.0
    idle = Y["idle"][w].mean()
    copS = fit_cop(t, Hs, Y["Es"][w], Y["fs"][w])
    copW = fit_cop(t, Hw, Y["Ew"][w], Y["fw"][w])
    hs = k * np.maximum(0, tb - tyear)
    hw = np.maximum(0, wa + wb * tyear)
    es = np.where(hs > 0, hs / copS(tyear), 0) if copS else 0 * hs
    ew = np.where(hw > 0, hw / copW(tyear), 0) if copW else 0 * hw
    Ht = hs.sum() + hw.sum(); Et = es.sum() + ew.sum() + idle * len(tyear)
    return Ht / Et if Et > 0 else np.nan


systems = json.load(open(f"{DATA}/systems.json"))
years = json.load(open(f"{DATA}/years.json"))
ids = [s["id"] for s in systems]

Rs, Ps = [], []
CACHE = f"{DATA}/phys_cache.npz"
if os.path.exists(CACHE):
    z = np.load(CACHE); Rs = [z["R0"], z["R1"]]; Ps = [z["P0"], z["P1"]]
for y in ((0, 1) if not Rs else ()):
    t0 = years[f"year{y}"]
    R = cal.samples(f"{DATA}/year{y}", t0, ids)
    R = R[R[:, 8] == 1]                       # windows with temperatures (the "full" model)
    cache = {}
    P = np.full(len(R), np.nan)
    for n, row in enumerate(R):
        i, st, K = int(row[0]), int(row[1]), int(row[2])
        if i not in cache:
            Y = load_year(f"{DATA}/year{y}/{i}.csv", t0)
            cache[i] = (Y, Y["t"][np.isfinite(Y["t"])])
        Y, ty = cache[i]
        idx = (st + np.arange(K)) % 365
        P[n] = phys_projection(Y, idx, ty)
    P[~(P > 0)] = np.nan
    Rs.append(R); Ps.append(P)
    print(f"year{y}: {len(np.unique(R[:, 0]))} systems, {len(R)} samples, physical projection for {100 * np.isfinite(P).mean():.0f}%", flush=True)

if not os.path.exists(CACHE): np.savez(CACHE, R0=Rs[0], R1=Rs[1], P0=Ps[0], P1=Ps[1])
for y in (0, 1): Ps[y][~(Ps[y] > 0)] = np.nan

# Empirical, cross-year
E_emp = [cal.log_errors(cal.fit(Rs[1], "full"), Rs[0], "full"), cal.log_errors(cal.fit(Rs[0], "full"), Rs[1], "full")]

# Hybrid: add log(physical) as a feature; where no physical projection, use log(COP so far)
def Xh(R, P):
    lp = np.where(np.isfinite(P), np.log(np.where(np.isfinite(P), P, 1)), np.log(R[:, 4]))
    return np.column_stack([cal.X(R, "full"), lp, np.isfinite(P) * 1.0])

def fit_h(R, P):
    co = {}
    for w, K, m in cal.cells(R):
        co[(w, K)] = np.linalg.lstsq(Xh(R[m], P[m]), np.log(R[m, 3]), rcond=None)[0]
    return co

def err_h(co, R, P):
    w = (R[:, 1] // 7).astype(int) % 52
    B = np.array([co[(wi, int(k))] for wi, k in zip(w, R[:, 2])])
    return np.sum(Xh(R, P) * B, axis=1) - np.log(R[:, 3])

E_hyb = [err_h(fit_h(Rs[1], Ps[1]), Rs[0], Ps[0]), err_h(fit_h(Rs[0], Ps[0]), Rs[1], Ps[1])]
E_phy = [np.log(Ps[y]) - np.log(Rs[y][:, 3]) for y in (0, 1)]

R = np.vstack(Rs); P = np.concatenate(Ps)
e = {"empirical": np.concatenate(E_emp), "physical": np.concatenate(E_phy), "hybrid": np.concatenate(E_hyb)}
has_p = np.isfinite(P)

t0 = dtm.datetime.fromtimestamp(years["year0"], dtm.timezone.utc)
mon = np.array([(t0 + dtm.timedelta(days=int(x))).month for x in R[:, 1]])

def w10(x): return 100 * np.mean(np.abs(np.exp(x) - 1) <= 0.10)

print(f"\nAll samples: {len(R)}; physical has a projection for {100 * has_p.mean():.0f}%")
print("Share within ±10% on samples where the physical model projects:")
for k in e: print(f"  {k:10s} {w10(e[k][has_p]):5.1f}%   median error {100 * np.median(np.exp(e[k][has_p]) - 1):+5.1f}%")
print("Hybrid vs empirical on all samples:", f"{w10(e['hybrid']):.1f}% vs {w10(e['empirical']):.1f}%")

for name in ["empirical", "physical", "hybrid"]:
    print(f"\n[{name}] within ±10% by start month × days (physical-projectable samples only)")
    print("mon " + " ".join(f"{k:>5}" for k in KS))
    for m in [12, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]:
        cells = []
        for k in KS:
            sel = (mon == m) & (R[:, 2] == k) & has_p
            cells.append(f"{w10(e[name][sel]):5.0f}" if sel.sum() >= 20 else "    -")
        print(f"{m:>3} " + " ".join(cells))

print("\n[physical] median error % by start month × days (bias)")
for m in [12, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]:
    cells = []
    for k in KS:
        sel = (mon == m) & (R[:, 2] == k) & has_p
        cells.append(f"{100 * np.median(np.exp(e['physical'][sel]) - 1):+5.1f}" if sel.sum() >= 20 else "    -")
    print(f"{m:>3} " + " ".join(cells))
