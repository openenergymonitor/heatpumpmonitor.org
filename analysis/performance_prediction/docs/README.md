# Heat pump performance prediction

---

*Note from Trystan: This performance prediction project was largely generated using Claude code (Model: Fable - effort high).
Apart from this brief introduction the document text is all written by claude as will be clear!*

*This analysis attempts to progress my earlier manual analysis of the HeatpumpMonitor.org dataset as documented here: [https://docs.openenergymonitor.org/heatpumpmonitor/index.html](https://docs.openenergymonitor.org/heatpumpmonitor/index.html). I think it has uncovered a number of useful ideas!*

*The analysis also develops a harness for the dynamic heat pump simulator tool that I have been working on here: [https://openenergymonitor.org/tools/dynamic_heatpump](https://openenergymonitor.org/tools/dynamic_heatpump) and uses this to test and refine ideas that could then be applied to the real world data set. More work needs to be done to verify if these ideas work when applied to real data!*

*The following is work in progress and I welcome suggestions for improvement!*

---

How well can we predict a heat pump's seasonal performance (SPF H4) from
what we know about the system, and what is the scatter made of? The
starting point is the known relationship between SPF and weighted flow
temperature minus outside temperature (ΔT). The ambition was to get from
about ±0.5 SPF down towards ±0.1.

The short version: ΔT is still the best predictor we have. Nothing tried
here beat it by much on the real fleet, though several things we learned
along the way are useful in their own right.

**Data.** The HeatpumpMonitor.org all-fields export from 2026-07-15,
filtered to 225 air source, MID-metered, boundary H4 systems with at least
330 days of data and no cooling. Later work scanned the raw 10 s feeds of
254 air source systems, 229 of which passed a cross-check against the
published stats (section 4). The dynamic_heatpump simulator was used as a
testbed.

Other docs in this folder:

- [metering_floor.md](metering_floor.md): how accurate a measured SPF is
- [design_model.md](design_model.md): predicting SPF from the design sheet
- [load_and_quality.md](load_and_quality.md): the one open question, and
  the experiment planned to answer it

## Where things stand

On the real fleet, cross-validated on held-out systems:

| Predictor | cv R² | 90% PI | Notes |
|---|---|---|---|
| Weighted ΔT | 0.59 | ±0.50 | the baseline |
| ΔT + log annual heat | 0.64 | ±0.47 | best found, nothing else adds to it |
| H\* metric | 0.47 | ±0.58 | worse than ΔT (n = 229) |
| Design sheet model | 0.08 | ±0.74 | limited by the inputs |
| Metering error alone | | ±0.10 | the floor |

In the simulator, where every machine follows its COP model exactly and
the design inputs are true, both H\* (±0.16 to ±0.33 depending on the
simulator version) and the design sheet model (±0.21) beat ΔT. Neither
result carried over to the fleet.

## 1. The baseline still holds

The published fit was SPF = −0.105·ΔT + 6.88 (R² 0.55). On the current
data it comes out as **SPF = −0.110·ΔT + 7.04, R² 0.60** (cross-validated
0.59, ±0.50), so the relationship has, if anything, got slightly stronger.
Quadratic, log-log and Carnot-of-the-mean forms were all tried and none
beat the straight line. The problem isn't the shape of the fit, it's how
much a single averaged ΔT can tell you.

Filter counts: boundary H4 gives 268 systems, air source 263, no cooling
228, ≥330 days 226, with both COP and ΔT present 225. The cooling filter
is the big cut. Without it the fit still holds but weakens (n = 258,
R² 0.46), because the published weighted stats count summer cooling heat
as heat (section 4). Coldest-day measures barely notice the filter.

![baseline fit](figures/cop_vs_flow_minus_outside.png)

## 2. What the published stats can add

**Annual heat demand** is the strongest residual correlate (ρ = 0.36:
bigger homes beat the line), and the only addition that helps. Adding log
annual heat to ΔT gives cv R² 0.64, ±0.47:

```
SPF = 6.53 − 0.114·ΔT + 0.25·ln(annual heat, MWh)
```

Doubling annual heat is worth about +0.17 SPF. Why isn't clear. Bigger
homes have a smaller standby share and steadier running, and primary
pipework losses, which have no field in the data, would also matter less.
These can't be separated from the annual data.

**Standby electricity** is real and can be removed exactly, since
`combined_cop = running_cop / (1 + standby_elec / running_elec)`. It
costs a median 0.10 COP (worst case 0.38). Taking it out only lifts R²
from 0.60 to 0.63, though, and it adds nothing once annual heat is in.

**Utilisation** (annual heat ÷ badge capacity × 8760 h) also improves on
ΔT (cv R² 0.62), but only through its annual heat part. Fitting log
annual heat and log capacity separately gives +0.30 ± 0.12 for heat and
−0.11 ± 0.14 for capacity, so how big the machine is relative to the
house adds little.

**Modulation** can't be seen in annual means. The mean load while running
correlates with the residual at only ρ = 0.19.

Make matters a little. Mitsubishi averages about 0.2 above the line and
Samsung, Grant and Daikin about 0.13 below, but the scatter within each
make is large (as a group, p = 0.04, cv R² +0.01).

Of the install and control fields, only space heat control type shows
anything: the 11 systems on weather compensation with significant room
influence sit 0.22 below the line (p = 0.01 as a group, though cv R²
doesn't improve). Emitter type, DHW control type, DHW target temperature
and refrigerant add nothing once ΔT is in. Some of these do help a model
built on coldest-day temperatures, because there they stand in for the
operating temperature (see [design_model.md](design_model.md)).

Throwing every safe field into a cross-validated linear model does worse
than the two-term model (cv R² 0.61, ±0.47), and gradient boosting worse
again (0.55). With 225 systems a bigger model just overfits.

The prediction interval scales with √(1 − R²), so getting to ±0.25 would
need R² ≈ 0.90 and ±0.10 would need about 0.98. Better models won't get
there. We'd need better inputs.

**Leakage, a warning.** A first kitchen-sink model scored cv R² 0.85,
which was fake. If a model gets both heat-side and electricity-side
quantities it can reconstruct COP from their ratio. Keep them apart,
treat `prc_carnot`, `water_cop` and `running_cop` as restatements of the
target, and always score on held-out systems. The unit cost fields are
tariff ÷ COP, so they leak too.

![residual investigation](figures/residual_investigation.png)

## 3. Systems that live at low load do worse

Power histograms from the heatpumpmonitor.org API (heat delivered at each
output power) gave the next strongest residual signal after annual heat:
**the share of annual heat delivered below 40% of rated output**
(ρ = −0.33 with the ΔT residual). Systems that do a lot of their work at
low output underperform what their temperatures predict. This isn't just
small homes in disguise: controlling for annual demand still leaves −0.28.

The typical system delivers only 10% of its heat below 40% of rated
output, but the top 15% deliver 40–72% there and sit about 0.23 COP below
the rest. It reads as oversizing showing up in operation. Below minimum
modulation the machine has to cycle, and fixed losses become a bigger
share.

It's a weak signal in a regression, though. Added to ΔT and log annual
heat its coefficient is −0.18 ± 0.22 (p = 0.11) and cv R² doesn't
improve. It adds nothing on top of utilisation either, which it
correlates with at −0.50.

What this effect actually *is* is still open (see
[load_and_quality.md](load_and_quality.md)). The H\* work below showed
it can't be modelled as extra temperature lift.

Electricity-side histograms showed nothing useful. A one-dimensional annual
histogram mixes all weathers together, and most high-power running happens
in cold weather, which ΔT already accounts for.

![low load effect](figures/lowload_effect.png)

These figures use the histograms fetched in July 2026, kept in
`modulation_features.csv`. A re-fetch in October differed for two systems
(one no longer returns data), which moves the correlation to −0.35.

## 4. The simulator and H\*

A Node harness, now `sweep.js` in the
[tools repo](https://github.com/openenergymonitor/tools) (branch
`dynamic-heatpump-sweep`, not yet on main), runs the
unmodified dynamic_heatpump tool over 400 randomised house and system
designs and drops those that fail on comfort. It has been run on two
versions of the simulator:

- **July 2026** (tools commit `3c8d156`, `sim_results.csv`, 293 designs
  kept). Every design has the same machine at 47% of Carnot, with no
  defrost or pipework losses. The metric search and the design model used
  this sweep.
- **October 2026** (tools commit `e522457`, `sim_carnot_2026-10.csv`,
  `sim_arotherm_2026-10.csv`).
  The simulator now has frost and defrost, primary pipework losses, a new
  cylinder model, and emitters sized from a design flow temperature
  (35–55 °C here). The same 400 houses were run with the carnot model and
  with Vaillant Arotherm+ units sized by badge, using a COP model fitted
  to the datasheet (`generic_v2`, speed based). DHW volume is trimmed so
  the median DHW share matches the fleet's 14%.

**Identical machines still scatter.** The simulated fleets scatter ±0.34
to ±0.40 around their own SPF ~ ΔT line, with no variation in machines,
installs or metering. In the July sweep that residual is almost entirely
how well each design uses the machine (r = 0.97 with achieved % of
Carnot), driven by heat demand, capacity and DHW share, much like the real
fleet. But every simulated machine follows its COP model exactly, which
real ones don't, so this shows how much operating pattern *could* matter,
not how much it does.

![sim vs real](figures/sim_vs_real.png)

**H\*.** Using the July sweep as ground truth, we searched for a better
annual metric than ΔT. The winner was H\*: an ideal Carnot COP averaged
the right way (heat-weighted harmonic mean, because electricity is the sum
of heat/COP), with the condensing and evaporating temperatures pushed
apart as the machine works harder:

```
r      = heat / rated capacity            (instantaneous load ratio)
Tcond  = flowT    + 3·r
Tevap  = outsideT − 8·r
carnot = (Tcond + 273.15) / (Tcond − Tevap)

H* = Σ heat / Σ (heat / carnot)           (running samples only)
```

It needs no electricity data, so it's an honest predictor rather than a
restatement of COP.

![metric collapse](figures/metric_collapse.png)

On the real fleet it did worse than ΔT. `feed_scan_4.php` (in
`scripts/feed_scan/` at the repo root) computed it from the raw 10 s feeds
for the 229 validated systems. Cross-validated:

| SPF against | July sim | Oct sim, carnot | Oct sim, Arotherm+ | Real fleet |
|---|---|---|---|---|
| Weighted ΔT | 0.63, ±0.34 | 0.61, ±0.40 | 0.77, ±0.34 | 0.59, ±0.50 |
| Harmonic Carnot, fixed +2/−6 | 0.64, ±0.32 | 0.63, ±0.40 | 0.78, ±0.32 | 0.57, ±0.52 |
| H\* (+3r/−8r) | 0.90, ±0.16 | 0.72, ±0.33 | 0.87, ±0.25 | 0.47, ±0.58 |

H\* beats ΔT in every version of the simulator, most of all in the July
one with its identical, defrost-free machines. On the fleet it's the worst
of the three.

To rule out the coefficients simply being wrong, `feed_scan_5.php`
exported a per-system histogram of heat by flowT, outsideT and load, so any
offsets could be tried offline. A search over 1,092 combinations (with
capacity taken either from the badge or from each system's own observed
peak) found that **the best load coefficient is zero**, and no member of
the family beats plain ΔT. That closes the metric search.

Why it failed is the subject of [load_and_quality.md](load_and_quality.md).
In short, the Arotherm+ datasheet predicts a strong load effect, and real
Arotherms don't show it.

Side results:

- **The two pipelines agree.** The raw feed scan and the published weighted
  stats match to a few hundredths of a K for about 90% of systems. The 25
  that didn't match were almost all cooling systems: the published
  pipeline flips cooling heat positive and includes it, the scan leaves it
  out. A few others had very poor outside temperature data.
- **Badge capacity is a poor guide to the hardware.** Observed peak output
  is a median 1.33× the badge (IQR 1.09–1.67). A handful of 5 kW systems
  show 26–49 kW peaks, which must be heat-meter spikes and are worth a
  data QA pass.
- **Published % of Carnot reads slightly high.** In the July simulator,
  where every machine is identical, the fixed +2/−6 convention reads about
  3 points high, and up to 5 points for low-load systems. Swapping in H\*
  doesn't give a fairer ranking on the fleet (sd of SPF/H\* 0.041 against
  0.037).
- **Ground source is out.** H\* uses outside air for the evaporator, which
  is wrong for a ground loop.

## 5. Ideas not yet tried

- **Daily records instead of annual ones.** The fleet is about 80,000
  system-days, not 225 data points. A pooled model of daily COP against
  daily conditions, with a per-system offset and held-out systems for
  testing, has far more to learn from. This is the most promising ML
  route. Success would be a held-out annual SPF interval near ±0.25.
- **Learn the residual, not the physics.** A small regularised model on
  top of the ΔT fit, with SHAP values to explain each system's gap.
- **Fit the simulator to individual homes.** The fitted % of Carnot would
  be a cleaned-up machine quality score, and whatever the fit can't
  explain points at unmodelled effects. The Arotherm twins in
  [load_and_quality.md](load_and_quality.md) are a first step.
- **Metadata worth adding:** glycol concentration, primary pipework length,
  and the as-commissioned weather compensation curve (see
  [design_model.md](design_model.md)).
- **Publish a standby-corrected SPF.** It's an exact calculation.
- **Installer as a category.** Probably more informative than make, but
  most installers have only a handful of systems.
- **Defrost exposure from weather data.** Humidity and frost hours joined
  by location, since the defrost penalty varies between sites at the same
  temperature.

## Data notes

Traps in the all-fields export:

- `weighted_average_flow_minus_outside` and `weighted_flow_temp` are
  sparse legacy fields with 0 for missing. Use `weighted_flowT_minus_outsideT`
  and `weighted_flowT`.
- `heat_loss` is mostly kW but some values are in W (up to 6063) and one
  is negative. Divide values over 100 by 1000 and drop anything under
  0.5 kW.
- `measured_heat_loss`, `dhw_target_temperature`, `flow_temp`,
  `hp_max_output` and `measured_mean_flow_temp_coldest_day` use 0 for
  missing.
- About 10% of systems report zero `water_heat_kwh`, probably unmetered
  rather than no hot water.
- Logging periods vary (330–365 days), so annualise kWh before comparing
  systems.

The histogram API endpoint needs a non-default User-Agent (`Python-urllib`
gets a 403):

```
https://heatpumpmonitor.org/histogram/kwh_at_power_heat?id=<id>&start=<t>&end=<t>&x_min=0&x_max=30000
```

## Files

Set up once from `analysis/performance_prediction/`:

```
python3 -m venv .venv
.venv/bin/pip install -r requirements.txt
```

Then run any script with `.venv/bin/python scripts/<name>.py`. Scripts find
the data relative to themselves, so the working directory doesn't matter,
and they pick up the newest `heatpumpmonitor_all_fields_*.csv`. Figures are
written to `docs/figures/`.

| Path | What it is |
|---|---|
| `scripts/hpmon_analysis.py` | loader, standard filter, linear fit with PI (section 1) |
| `scripts/residual_analysis.py`, `residual_plots.py` | residual tests and CV harness (section 2) |
| `scripts/regression_analysis.py` | utilisation, install and control categories, the coldest-day model (section 2 and design_model.md) |
| `scripts/fetch_histograms.py`, `modulation_analysis.py`, `lowload_plots.py` | histogram features (section 3). Run `fetch_histograms.py` first, as the cache in `histograms/` isn't kept in git. `modulation_analysis.py` rewrites `modulation_features.csv` from the cache |
| `scripts/sim_vs_real.py`, `metric_comparison.py`, `prc_carnot_offsets.py`, `metric_collapse_plot.py` | July simulator comparisons (section 4) |
| `scripts/analyse_hstar_fleet.py`, `analyse_hstar_offsets.py` | H\* fleet test and offset search (section 4) |
| `scripts/hstar_plots.py` | P test and utilisation figures (load_and_quality.md); needs `hstar_hist.csv` |
| `scripts/sim_model_comparison.py` | the same tests on the October sweeps and the fleet (section 4, load_and_quality.md) |
| `scripts/design_model.py`, `design_model_plots.py` | design sheet model |
| `twins/` | simulated datasheet twins of the fleet's Arotherm+ systems: `twin_targets.py` builds the targets, `twins.js` calibrates and runs the twins (needs the tools repo at `176ae57` or later), `compare_twins.py` compares them with the real systems |
| `design_spf_tool/index.html` | interactive design sheet model |
| `heatpumpmonitor_all_fields_*.csv` | fleet export |
| `modulation_features.csv` | histogram features, July 2026 fetch |
| `hstar_fleet.csv`, `hstar_fleet_hist.csv` | raw feed scan results, one row per system |
| `hstar_hist.csv` | per-system heat histograms, 113 MB, not in git; regenerate with `feed_scan_5.php` |
| `sim_results.csv` | July 2026 simulator sweep |
| `sim_carnot_2026-10.csv`, `sim_arotherm_2026-10.csv` | October 2026 sweeps, carnot and Arotherm+ datasheet models |
| `llanberis2024.csv` | the simulator's weather year, used by the design model (a copy from the tools repo) |
| tools repo, `www/tools/dynamic_heatpump/` | the simulator; `sweep.js` there runs the Monte Carlo sweep (branch `dynamic-heatpump-sweep`) |
| `scripts/feed_scan/` (repo root) | the PHP feed scanners |
