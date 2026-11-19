#!/usr/bin/env python3
"""Can a simple closed-form model built from *design parameters* predict SPF
as well as the abstract operational metrics (weighted dT, H*)?

The model is "H* computed at design time": no simulation, no monitoring data.

    inputs (all available at design/commissioning):
      heat loss (kW) at a design outside temperature, room setpoint
      emitter capacity  (design flow temp at design condition, or DT50 rating)
      system flow-return DT
      heat pump rated capacity
      assessed DHW share of annual heat, DHW target temperature
      a standard weather year for the location

    procedure, per weather sample T (half-hourly, one standard year):
      Q(t)      = U * (room - T) - gains - solar(t)*scale*0.9   space heat demand,
                  smoothed over 24 h (thermal mass), clipped at [0, capacity]
      MWT(T)    = room + 50 * (Q / rad50)^(1/1.3)          radiator equation
      flowT(T)  = MWT + system_DT / 2
      r(T)      = max(Q, min_mod * capacity) / capacity    instantaneous load ratio
      carnot(T) = (flowT + 3r + 273.15) / ((flowT + 3r) - (T - 8r))
      accumulate heat-weighted harmonic mean; add DHW at its target temp
      H*_design = sum(heat) / sum(heat / carnot)
      SPF       = eta * H*_design    (eta = % of Carnot; absorbed by the cv fit)

Benchmarked with cross-validation on the real fleet: (A) declared design
params, (B) corrected heat loss, (C) corrected heat loss + WC curve anchored
on the coldest-day flow temp.
"""

import sys

import numpy as np
import pandas as pd

from hpmon_analysis import load, standard_filter, find_csv, linfit, ROOT
from residual_analysis import num, cv_eval
from sklearn.linear_model import LinearRegression
from sklearn.pipeline import make_pipeline
from sklearn.impute import SimpleImputer

# ---------------------------------------------------------------- weather
def load_weather():
    """Half-hourly outside temperature and solar irradiance, Llanberis 2024."""
    ts, sol = [], []
    with open(ROOT / "llanberis2024.csv") as fh:
        for line in fh:
            c = line.strip().split(",")
            if len(c) >= 5:
                ts.append(float(c[1]))
                sol.append(float(c[3]))
    return np.array(ts), np.array(sol)

WEATHER, SOLAR = load_weather()

# ------------------------------------------------------- the design model
A_COND, B_EVAP = 3.0, 8.0        # H* convention (docs/README.md, section 4)
RAD_EXP = 1.3                    # radiator equation exponent (as the simulator)
MINMOD = 0.25                    # minimum modulation floor, global constant
# gains defaults mirror the dynamic model: metabolic + lighting/appliances (W)
# and solar irradiance x an aperture scale x 0.9 (dynamic_heatpump.js:1289)
GAINS_W, SOLAR_SCALE = 290.0, 4.0
SMOOTH_H = 24                    # thermal-mass window: net demand is smoothed
                                 # over this many hours before clipping, so
                                 # midday solar surplus offsets evening demand

def smooth_series(x, hours=SMOOTH_H, samples_per_hour=2):
    win = int(hours * samples_per_hour)
    if win <= 1:
        return x
    return np.convolve(x, np.ones(win) / win, mode="same")

# Defrost: COP is reduced by D * frost_w(T) — a frosting-band weight that is
# 1 between -2 and +2 C, fades to 0 by +6 C, and tapers below -2 C as the air
# dries. D ~ 0.13 reproduces both fleet-level estimates of the annual cost
# (~1.6% of net heat, ~0.15-0.25 SPF) and the in-band penalty measured on a
# real system over a cold month (COP 3.9 vs 4.4-4.5 modelled defrost-free):
# community.openenergymonitor.org thread 29547 posts 18-20.
DEFROST_D = 0.13

def frost_w(T):
    T = np.asarray(T, float)
    w = np.zeros_like(T)
    w = np.where(T < 6, (6 - T) / 4, w)
    w = np.where(T <= 2, 1.0, w)
    w = np.where(T < -2, np.maximum(0.4, 1 - (-2 - T) * 0.075), w)
    return w

def design_hstar(u_wk, room, rad50_w, dt_sys, capacity_w,
                 dhw_share, dhw_target,
                 gains_w=GAINS_W, solar_scale=SOLAR_SCALE,
                 min_mod=MINMOD, dhw_approach=4.0,
                 defrost_D=0.0,
                 weather=WEATHER, solar=SOLAR):
    """Closed-form design-time H* (heat-weighted harmonic ideal Carnot COP).

    u_wk: building conductance W/K; rad50_w: emitter rated output at DT50, W.
    Space demand nets off internal gains (W) and solar gains (irradiance x
    solar_scale x 0.9); the net is smoothed over SMOOTH_H hours (thermal
    mass) and then clipped at zero, so gains surpluses within the window are
    utilised and anything beyond is discarded (the closed-form equivalent of
    the dynamic model's mass + overheating-utilisation behaviour).

    defrost_D=0 gives the pure (defrost-free) H* metric (docs/README.md, section 4). With
    defrost_D>0 the COP is derated by D*frost_w(T) and the returned value is
    the defrost-adjusted harmonic, so SPF = eta_bench * design_hstar(...,
    defrost_D=DEFROST_D) with eta_bench the defrost-free machine quality.
    """
    T = weather
    Q = u_wk * (room - T) - gains_w - solar * solar_scale * 0.9
    Q = np.clip(smooth_series(Q), 0, capacity_w)  # backup heat ignored
    on = Q > 0

    Qo = Q[on]
    mwt = room + 50.0 * (Qo / rad50_w) ** (1.0 / RAD_EXP)
    flow = mwt + dt_sys / 2.0
    r = np.maximum(Qo, min_mod * capacity_w) / capacity_w   # cycling floor
    tc = flow + A_COND * r
    te = T[on] - B_EVAP * r
    carnot = (tc + 273.15) / (tc - te) * (1 - defrost_D * frost_w(T[on]))
    space_h = Qo.sum()
    space_ie = (Qo / carnot).sum()

    # DHW: constant year-round demand at target temp, high load ratio
    dhw_share = min(max(dhw_share, 0.0), 0.6)
    dhw_h = space_h * dhw_share / (1.0 - dhw_share)
    r_dhw = 0.9
    tc = dhw_target + dhw_approach + A_COND * r_dhw
    te = T - B_EVAP * r_dhw
    carnot_dhw = (tc + 273.15) / (tc - te) * (1 - defrost_D * frost_w(T))
    dhw_ie = dhw_h * np.mean(1.0 / carnot_dhw)

    return (space_h + dhw_h) / (space_ie + dhw_ie)


def space_heat_kwh(u_wk, room, capacity_w, gains_w=GAINS_W, solar_scale=SOLAR_SCALE,
                   weather=WEATHER, solar=SOLAR):
    """Annual space heat (kWh) implied by the same demand model as design_hstar."""
    Q = u_wk * (room - weather) - gains_w - solar * solar_scale * 0.9
    return np.clip(smooth_series(Q), 0, capacity_w).sum() * 0.5 / 1000.0


# ------------------------------------------------- median fleet "default" system
# Inputs chosen to reproduce a median HeatpumpMonitor.org system (docs/design_model.md,
# "Defaults"). Heat loss is set so annual heat matches the fleet median, and
# design flow so the heat-weighted flow temperature over the year (hot water
# included) matches. Design temp -1.5 C keeps the climate right (weighted
# outside temp). Uses a 35% minimum modulation rather than the global MINMOD.
DEFAULT_SYSTEM = dict(
    heat_loss_kw=4.23, design_temp=-1.5, room=20.0,
    gains_w=120.0 + 240.0,          # body heat + lighting/appliances
    solar_scale=4.0,                # solar aperture, m2
    design_flow=40.0,               # flow temp needed at design condition, no gains
    dt_sys=5.0, capacity_kw=6.0, min_mod=0.35,
    eta=0.47,                       # bench (defrost-free) % of Carnot
    defrost_D=DEFROST_D,
    dhw_share=0.14, dhw_target=48.0, dhw_approach=4.0,
)
# Fleet medians, 2026-07-15 export (225 air source systems; H* from hstar_fleet_hist.csv)
FLEET_MEDIAN = dict(
    total_heat_kwh=13205, weighted_flow=34.8, weighted_outside=7.3,
    coldest_day=(-1.8, 37.3),       # (outside, flow), daily means
    hstar=9.05, spf=4.00,
)


def default_system(p=DEFAULT_SYSTEM):
    """Run the design model on one system described by a DEFAULT_SYSTEM-style dict."""
    u = p["heat_loss_kw"] * 1000.0 / (p["room"] - p["design_temp"])
    rad50 = p["heat_loss_kw"] * 1000.0 / ((p["design_flow"] - p["dt_sys"] / 2 - p["room"]) / 50.0) ** RAD_EXP
    w = WEATHER + (p["design_temp"] - np.percentile(WEATHER, 0.4))   # localise standard year
    cap = p["capacity_kw"] * 1000.0
    kw = dict(gains_w=p["gains_w"], solar_scale=p["solar_scale"], weather=w)
    args = (u, p["room"], rad50, p["dt_sys"], cap, p["dhw_share"], p["dhw_target"])
    hkw = dict(min_mod=p["min_mod"], dhw_approach=p["dhw_approach"], **kw)
    h_nodef = design_hstar(*args, **hkw)
    h_def = design_hstar(*args, defrost_D=p["defrost_D"], **hkw)
    space = space_heat_kwh(u, p["room"], cap, **kw)
    share = p["dhw_share"]

    def flow_at(q):
        return p["room"] + 50.0 * (q / rad50) ** (1.0 / RAD_EXP) + p["dt_sys"] / 2.0

    # heat-weighted flow and outside temps over the year, hot water included
    # (DHW runs year-round at dhw_target + dhw_approach), as the fleet's
    # weighted_flowT / weighted_outsideT are
    Q = np.clip(smooth_series(u * (p["room"] - w) - p["gains_w"] - SOLAR * p["solar_scale"] * 0.9), 0, cap)
    on = Q > 0
    w_flow = ((flow_at(Q[on]) * Q[on]).sum() / Q[on].sum() * (1 - share)
              + (p["dhw_target"] + p["dhw_approach"]) * share)
    w_out = (w[on] * Q[on]).sum() / Q[on].sum() * (1 - share) + w.mean() * share

    # curve on a day whose mean outside temp is the fleet's coldest day
    # (solar ~0; demand is already a 24 h average)
    t_cold = FLEET_MEDIAN["coldest_day"][0]
    flow_cold = flow_at(u * (p["room"] - t_cold) - p["gains_w"])

    return dict(
        space_heat_kwh=space, total_heat_kwh=space / (1.0 - share),
        rad50_kw=rad50 / 1000.0, hstar=h_nodef,
        spf=p["eta"] * h_def, spf_no_defrost=p["eta"] * h_nodef,
        defrost_spf=p["eta"] * (h_nodef - h_def),
        weighted_flow=w_flow, weighted_outside=w_out, flow_coldest_day=flow_cold,
    )


def print_default_system():
    r, m = default_system(), FLEET_MEDIAN
    t, f = m["coldest_day"]
    print("Default (median fleet) system — docs/design_model.md, Defaults")
    print(f"                           model    fleet median")
    print(f"  annual heat, kWh       {r['total_heat_kwh']:7.0f}  {m['total_heat_kwh']:7.0f}")
    print(f"  weighted flow, C       {r['weighted_flow']:7.1f}  {m['weighted_flow']:7.1f}")
    print(f"  weighted outside, C    {r['weighted_outside']:7.1f}  {m['weighted_outside']:7.1f}")
    print(f"  flow on {t} C day, C {r['flow_coldest_day']:7.1f}  {f:7.1f}")
    print(f"  H* (defrost-free)      {r['hstar']:7.2f}  {m['hstar']:7.2f}")
    print(f"  SPF                    {r['spf']:7.2f}  {m['spf']:7.2f}")
    print(f"  defrost cost {r['defrost_spf']:.2f} SPF; DT50 emitter output {r['rad50_kw']:.1f} kW")


def cv_report(X, y, name, folds=5):
    ok = X.notna().any(axis=1) & y.notna()
    X, y = X[ok].reset_index(drop=True), y[ok].reset_index(drop=True)
    r = cv_eval(X, y, make_pipeline(SimpleImputer(strategy="median"), LinearRegression()), folds)
    print(f"  {name:52s} n={len(y):3d} cv_R2={r['cv_r2']:.3f}  cv_RMSE={r['cv_rmse']:.3f}  90%|err|={r['cv_pi90']:.2f}")
    return r


def main():
    # ============ REAL FLEET (self-reported design params) ============
    df = load(find_csv())
    f = standard_filter(df, verbose=False)
    f = f[f.weighted_flowT_minus_outsideT.notna() & f.combined_cop.notna()].reset_index(drop=True)
    yr = num(f.combined_cop)

    # --- clean design fields ---
    hl = num(f.heat_loss)
    hl = hl.where(hl < 100, hl / 1000.0)             # a few entries in W
    hl = hl.where((hl > 0.5) & (hl < 30))            # kW
    mhl = num(f.measured_heat_loss).where(lambda v: (v > 0.5) & (v < 30))
    hl_true = mhl.fillna(hl * 0.76)                  # median measured/design ratio
    dtemp = num(f.design_temp).where(lambda v: (v > -15) & (v < 6))
    ftemp = num(f.flow_temp).where(lambda v: (v >= 25) & (v <= 70))   # design flow temp
    cap = num(f.hp_output).where(lambda v: (v > 1) & (v < 30)) * 1000.0
    dhwt = num(f.dhw_target_temperature).where(lambda v: (v >= 35) & (v <= 65)).fillna(48.0)
    hd = num(f.heat_demand).where(lambda v: v > 1000)                 # design annual estimates
    wd = num(f.water_heat_demand).where(lambda v: v > 0)
    share = (wd / hd).where(lambda v: (v > 0.01) & (v < 0.6))
    share = share.fillna(share.median())
    mft = num(f.measured_mean_flow_temp_coldest_day).where(lambda v: (v > 25) & (v < 65))
    mot = num(f.measured_outside_temp_coldest_day).where(lambda v: (v > -15) & (v < 8))
    mrt = num(f.measured_room_temp_coldest_day).where(lambda v: (v > 15) & (v < 25)).fillna(20.0)

    ROOM, DT_SYS = 20.0, 5.0
    W_REF = np.percentile(WEATHER, 0.4)              # Llanberis "design temp"

    def real_hstar(i, hl_kw, ft_anchor, t_anchor, room_anchor, dhw=True):
        """H*_design with the WC curve anchored at (ft_anchor when outside = t_anchor)."""
        vals = [hl_kw[i] if hasattr(hl_kw, "__getitem__") else hl_kw,
                dtemp[i], ft_anchor[i], cap[i]]
        t_a = t_anchor[i] if hasattr(t_anchor, "__getitem__") else t_anchor
        r_a = room_anchor[i] if hasattr(room_anchor, "__getitem__") else room_anchor
        if np.isnan(vals + [t_a, r_a]).any():
            return np.nan
        u = vals[0] * 1000.0 / (ROOM - dtemp[i])
        Qa = u * (r_a - t_a)
        mwt_a = ft_anchor[i] - DT_SYS / 2.0
        if mwt_a <= r_a + 2 or Qa <= 0:
            return np.nan
        rad50 = Qa / ((mwt_a - r_a) / 50.0) ** RAD_EXP
        w = WEATHER + (dtemp[i] - W_REF)             # localise standard year (T only)
        return design_hstar(u, ROOM, rad50, DT_SYS, cap[i],
                            share[i] if dhw else 0.0, dhwt[i], weather=w)

    # absolute demand check against measured space heat (corrected heat loss)
    meas_space = num(f.space_heat_kwh)
    ok = hl_true.notna() & dtemp.notna() & meas_space.notna() & (meas_space > 1000)
    ratios = pd.Series([
        space_heat_kwh(hl_true[i] * 1000 / (ROOM - dtemp[i]), ROOM, np.inf,
                       weather=WEATHER + (dtemp[i] - W_REF)) / meas_space[i]
        for i in f.index[ok]])
    print(f"\n  real-fleet space heat: predicted/measured median {ratios.median():.2f}  "
          f"IQR {ratios.quantile(.25):.2f}-{ratios.quantile(.75):.2f}  (n={len(ratios)}, corrected HL)")

    R = pd.DataFrame({
        "dT_w": num(f.weighted_flowT_minus_outsideT),
        # A: everything from the design paperwork
        "H_A": [real_hstar(i, hl, ftemp, dtemp, ROOM) for i in f.index],
        # B: demand from corrected heat loss, emitters as designed
        "H_B": [real_hstar(i, hl_true, ftemp, dtemp, ROOM) for i in f.index],
        # C: corrected heat loss + WC curve anchored on coldest-day measurement
        "H_C": [real_hstar(i, hl_true, mft, mot, mrt) for i in f.index],
        "H_C_nodhw": [real_hstar(i, hl_true, mft, mot, mrt, dhw=False) for i in f.index],
        "cold_dT": mft - mot,
        "share": share,
        "cap_ratio": cap / 1000.0 / hl_true,
    })

    print(f"\nREAL FLEET (n={len(f)}; design-side predictors unless noted)")
    cv_report(R[["dT_w"]], yr, "dT_w (measured, needs a year of monitoring)")
    cv_report(R[["H_A"]], yr, "A: H*_design from declared design params")
    cv_report(R[["H_B"]], yr, "B: + corrected heat loss (0.76x / measured)")
    cv_report(R[["H_C"]], yr, "C: + WC anchored on coldest-day flowT")
    cv_report(R[["H_C_nodhw"]], yr, "C without DHW block")
    cv_report(R[["cold_dT"]], yr, "coldest-day (flowT - outsideT) alone")
    cv_report(R[["cold_dT", "share", "cap_ratio"]], yr, "coldest-day dT + DHW share + capacity ratio")
    cv_report(R[["H_C", "dT_w"]], yr, "H_C + measured dT_w")

    # the commissioning gap, quantified
    from scipy import stats
    wflow = num(f.weighted_flowT)
    ok = ftemp.notna() & wflow.notna()
    print(f"\nCommissioning gap: declared design flowT vs operated weighted flowT")
    print(f"  r = {stats.pearsonr(ftemp[ok], wflow[ok])[0]:.3f}   "
          f"medians {ftemp[ok].median():.1f} -> {wflow[ok].median():.1f} C   "
          f"spread sd {(wflow[ok] - ftemp[ok]).std():.1f} K  (n={ok.sum()})")
    ok = hl.notna() & mhl.notna()
    print(f"  declared vs measured heat loss: r = {stats.pearsonr(hl[ok], mhl[ok])[0]:.3f}, "
          f"median ratio {(mhl[ok] / hl[ok]).median():.2f}  (n={ok.sum()})")
    return f, R, yr


if __name__ == "__main__":
    if "--default" in sys.argv:
        print_default_system()     # needs only the weather file, no fleet data
    else:
        main()
