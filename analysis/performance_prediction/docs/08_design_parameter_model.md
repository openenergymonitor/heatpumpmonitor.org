# A design-parameter model: predicting SPF before the system exists

The metrics that predict SPF best on the simulator testbed — weighted ΔT and
H\* (the latter since tested on real fleet feeds and not adopted: doc 09) —
are computed *from a year of monitoring*. What a designer actually needs is the inverse: a
prediction from standard design parameters (heat loss, design flow temperature,
emitter sizing, DHW share and target temperature), before installation. This doc
asks whether a **simple closed-form model** built from those parameters can
approach the prediction interval of the abstract operational metrics — without
the complexity of the full physics simulator.

## The model: H\* computed at design time

No simulation and no monitoring data. The design parameters define the
weather-compensation curve the system *should* run; a standard weather year
turns that into a flow-temperature and load-ratio distribution; H\* (doc 05)
is then a closed-form sum. Per half-hourly weather sample `T`:

```
Q(t)      = U · (room − T) − gains − solar(t)·aperture·0.9    net space demand,
            smoothed over 24 h (thermal mass), clipped to [0, capacity]
MWT(t)    = room + 50 · (Q / rad50)^(1/1.3)          radiator equation
flowT(t)  = MWT + system_DT / 2
r(t)      = max(Q, min_mod·capacity) / capacity      instantaneous load ratio
carnot(t) = (flowT + 3r + 273.15) / ((flowT + 3r) − (T − 8r))

H*_design = Σ heat / Σ (heat / carnot)               + DHW block at its target temp
SPF       ≈ η · H*_design
```

Inputs: heat loss at a design outside temperature, room setpoint, emitter
capacity (equivalently: design flow temp at the design condition), system ΔT,
rated capacity, assessed DHW share, DHW target temperature, internal gains
(body heat + lighting/appliances, W) and a solar aperture (m²) — mirroring the
dynamic model's gains structure (defaults 290 W and 4 m²) — and a standard
weather year (temperature *and* irradiance). One global constant
(`min_mod` = 0.25) was set by a coarse grid on the simulator; η is absorbed by
the linear fit. The 24 h demand smoothing is the closed-form stand-in for
thermal mass: midday solar surplus offsets evening demand within the window,
surplus beyond it is discarded (the dynamic model's overheating-utilisation
limit). Code: `analysis/design_model.py`. An interactive single-page
implementation (Vue, self-contained, JS model verified against the Python
reference to < 0.3%) lives at `design_spf_tool/index.html`. Its defaults are
*calibrated so the model's default state reproduces the fleet's median
observed behaviour* (2026-07-15 export, 225 filtered systems): heat loss
4.2 kW at −1.5 °C with gains 120 + 240 W and 4 m² solar aperture gives
13,035 kWh annual heat vs the fleet median 13,205 (12,194 space + 1,781
DHW); design flow temp 41 °C puts the operating curve through the median
coldest-day observation (37.3 °C flow at −1.8 °C outside, n = 224); DHW
share 14% (measured median); capacity 6 kW (median capacity/heat-loss ratio
1.4); η 47% (bench, defrost-free) with a 13% peak defrost penalty modelled
explicitly — together these reproduce the fleet median achieved
`SPF / H*_design` of ≈ 45–46%. Note the calibrated heat loss sits below
the median *measured* heat loss (5.1 kW; declared calcs 6.5 kW): real
operation implies less than even the measured figure, reflecting zoning and
setback, and the effective design flow temp (41 °C) sits above the naive
no-gains anchor extrapolation (38 °C) because gains carry ~360 W of the
coldest-day load. Two dataset checks fell out of this work: the fleet's
measured annual mean outside temperature (median 7.7 °C) matches the
shift-only standard year (7.4 °C at −3 °C design), so a two-point
mean-matching localisation was tested and rejected (demand ratio worsens to
1.52, no R² gain); and the default-state SPF (4.30) sits above the fleet
median SPF (4.00) — the residual being Jensen's inequality (ratio at median
inputs ≠ median of ratios) plus the tail of underperforming systems.

## Result 1 — with truthful inputs, the simple model is nearly as good as H\*

Simulator testbed (293 designs, identical 47%-of-Carnot machine, exact design
parameters, same cv protocol as doc 05):

| Predictor | information needed | cv R² | 90% PI |
|---|---|---|---|
| weighted ΔT | a year of monitoring | 0.63 | ±0.34 |
| H\* | a year of monitoring | 0.90 | ±0.16 |
| **H\*_design** | **design sheet only** | **0.82** | **±0.21** |

The closed-form model recovers most of what the full dynamic simulation knows:
`SPF / H*_design` has sd 0.016 around the machine's fixed 47 % of Carnot. A
design-parameter model **beats a year of ΔT monitoring** and comes within
±0.05 of the best measured metric — *provided the design sheet tells the
truth*. Notably, almost no dynamics were needed: cycling control, setback and
DHW scheduling — everything the simulator models and the closed form ignores —
contribute only ~±0.1 between them.

**Absolute demand needs the gains modelled explicitly.** The first version of
the closed form folded all gains into a 1 K balance offset — fine for the
*correlation* benchmark (the linear fit absorbs scale) but it overestimated
annual space heat by ~47% against the simulator and ~2.3× against the real
fleet's measured space heat. With the gains treated as the dynamic model does
(internal + solar×aperture×0.9) and the 24 h mass smoothing, the closed form
reproduces the simulator's annual space heat to median ratio 1.12 (constant
setpoint vs sampled setbacks and cylinder-loss regain explain most of the
rest) and the real fleet's measured space heat to median 1.40 with corrected
heat loss — the residual being behaviour the design sheet cannot know (rooms
kept below 20 °C, zoning, setback) plus remaining heat-loss conservatism.

## Result 2 — on the real fleet the same model collapses, and the inputs are why

Real fleet (n = 225), same protocol, design-side predictors marked ●:

| Predictor | cv R² | 90% PI |
|---|---|---|
| weighted ΔT (a year of monitoring) | 0.59 | ±0.50 |
| ● H\*_design from declared design params | 0.08 | ±0.74 |
| ● + corrected heat loss (measured / 0.76×declared) | 0.06 | ±0.72 |
| ● + WC curve anchored on coldest-day flow temp | 0.21 | ±0.65 |
| ● coldest-day ΔT alone (a commissioning spot-check) | 0.42 | ±0.65 |
| ● coldest-day ΔT + DHW share + capacity ratio | 0.42 | ±0.62 |

The failure is not the model — it is that **the declared design parameters do
not describe the system that got built and operated**:

- **Design flow temperature vs reality: r = 0.33.** Declared median 45 °C;
  operated heat-weighted median 34.8 °C; scatter sd 5.3 K. Systems run ~10 K
  below their design flow temp, by very different amounts. The user-facing
  assumption "weather compensation is commissioned to the actual heat demand"
  is the assumption that fails in this fleet.
- **Declared heat loss overestimates measured by ~24%** (median ratio 0.76,
  n = 134 with both) — though it correlates well (r = 0.83), so it is a usable
  input once derated.

![design model](figures/design_model.png)

## Defrost: closing the bench-to-field η gap

The dynamic model (and therefore the simulator validation above) does not
model defrost, and neither did the first closed form — it hid the cost inside
a field-calibrated η. Two independent estimates from the
[OEM forum thread 29547](https://community.openenergymonitor.org/t/what-scop-can-you-expect-from-a-system-that-runs-at-55c-and-50c-flow-temperatures-on-the-coldest-days/29547/18)
turn out to agree once put on the same footing:

- **Fleet-level (posts 18–19):** ~1.6% of net heat lost to defrosting →
  ~0.15 SPF at SPF 4 (0.15–0.25 across assumptions).
- **Single-system field measurement (post 20, Peter Heyes):** 0.5–0.6 COP
  below a defrost-free regression over a cold month (mean 2.1 °C, RH 88%).
  That is an *in-band* penalty of ~12–14%; weighted by the share of annual
  heat delivered below 6 °C (**35.5%** at the calibrated default system), it
  annualises to **0.17–0.21 SPF** — the same ballpark, not a disagreement.

The model now carries defrost explicitly: COP is derated by `D · frost_w(T)`
where `frost_w` is 1 between −2 and +2 °C, fades to 0 by +6 °C and tapers in
drier air below −2 °C, with peak penalty **D = 13%** — the value that
reproduces both estimates (0.16 SPF at the default system). H\* itself stays
defrost-free (doc 05 convention); defrost enters the electricity side only.

Two fleet checks support it:

- **The η decomposition closes.** With defrost modelled, the fleet's implied
  machine quality (median `SPF / H*_design`, variant C) rises from 0.458 to
  **0.480 — at the bench value**. Field η (≈ 45–46%) = bench η (≈ 47–48%)
  minus defrost, quantitatively.
- **The climate signature has the right sign and size:** colder-half sites
  show median implied η 0.451 vs 0.466 for the warmer half (≈ 0.13 SPF
  equivalent), though individually weak (r = 0.10, p = 0.16) — UK sites are
  too climatically similar for defrost to separate systems, which is also
  why the cv benchmark is unchanged by the correction.

Note this does *not* explain the default-state SPF sitting above the fleet
median (that residual is Jensen plus the underperformer tail) — what it
explains is the gap between bench and field machine quality, so η in the
tool can keep its bench meaning and the prediction responds correctly to
climate.

Injecting exactly these error magnitudes (heat-loss log-sd 0.26, flow-temp sd
5.3 K) into the *simulator* testbed's design sheets reproduces the degradation:
±0.21 → ±0.38, R² 0.82 → 0.47. Input fidelity, not model form, is the
bottleneck — a more complex physics model inherits the same wall, since it eats
the same inputs.

Nor is the gap merely careless installers: on the trained-installer subset
(heatgeek/heatingacademy, n = 120) the design model does work better
(R² 0.19, ±0.67 vs 0.06, ±0.86 for the rest) — but the flow-temp gap is
unchanged (r = 0.34 in both). Declared design flow temp seems to encode
deliberate headroom and post-handover tuning as much as commissioning error.

A useful intermediate: reconstructing the operating curve from corrected heat
loss + the coldest-day flow-temperature anchor predicts the *operated* annual
weighted flow temp with r = 0.73 (±2.4 K sd). That residual ±2.4–2.8 K is
behaviour and weather the design sheet cannot know (setpoints, setback, zoning,
actual year); at −0.11 SPF/K it alone costs ~±0.3 SPF, before machine
variation (real `SPF/H*_design` η sd 0.059 vs 0.016 in the sim) adds more.

## Answer to the question

**Can a simple design-parameter model beat the abstract metrics?**

- *In a world where systems run as designed* — yes, nearly: ±0.21 from the
  design sheet alone, vs ±0.34 for a year of measured ΔT and ±0.16 for
  measured H\* (all sim-testbed figures; on the real fleet measured H\*
  itself under-performs ΔT — doc 09). And the *simple* closed-form model is
  enough; full dynamic simulation adds little for annual prediction.
- *On today's fleet metadata* — no: ±0.62–0.74, worse than measured ΔT
  (±0.50). Not because the model is too simple, but because declared design
  parameters describe intent, not operation (r = 0.33 on flow temp).

The two results together locate the real leverage: **commissioning data, not
model sophistication**. If the metadata captured the *as-commissioned* system —
true heat loss and the actual WC curve endpoints (equivalently one measured
coldest-day flow temperature) — a design-time prediction of roughly ±0.2–0.3
looks reachable; the single coldest-day spot-check already gets R² 0.42 with
no monitoring at all.

## Caveats

- Global constants were tuned (coarsely) on the simulator; the sim's COP model
  shares its functional form with H\*, flattering Result 1 (same caveat as
  doc 05 — a caveat doc 09 subsequently showed to be decisive for measured
  H\*, whose sim gain did not transfer to the fleet; the fleet grid search
  also found the optimal *load* coefficient is zero, so the +3r/−8r terms
  in the closed form carry no demonstrated fleet signal beyond the fixed
  offsets). The honest in-principle range is between the noise-free and
  fleet-noise rows.
- One standard weather year (Llanberis 2024), shifted per-system to the design
  outside temperature, stands in for local climate; a proper degree-day
  localisation would tighten the real-fleet numbers somewhat.
- `system_DT` fixed at 5 K, room at 20 °C, radiator exponent 1.3 for all real
  systems; UFH and fan-coil emitters are pushed through the radiator equation.
- The coldest-day anchor variants use one measured operating point, so they are
  not strictly design-time — they model the "commission, then predict" flow.

## Suggested next steps

1. Metadata: record *as-commissioned / as-operated* WC curve endpoints (and
   later changes); they are the missing design-time predictor. The declared
   design flow temp should be treated as an upper bound, not a prediction.
2. Fold `H*_design` into the ML residual-learning plan (doc 07): physics prior
   from the design sheet, residual from data.
3. Localise weather properly (postcode degree-days) and revisit the corrected
   variants; the Llanberis-shift is the crudest part of the real-fleet run.
