# An attempt at a simple steady-state design model

Installers already predict SPF from design parameters before a heat pump
goes in, in two ways: the MCS 031 method and the manufacturer's SCOP read
off at the design flow temperature. On the fleet both are weak
([mcs_and_scop_methods.md](mcs_and_scop_methods.md)): MCS 031 is about
0.9 too pessimistic, and SCOP gets the average right but ranks systems
poorly.

This page is another attempt from the same design sheet (heat loss,
design flow temperature, emitters, DHW share): a simple steady-state model
that works out the weather compensation curve the system should run and
prices it over a weather year. Can it get any closer?

**Not much. Given the same information it is level with SCOP and better
than MCS 031 on the average, but all three are capped by the same input:
the declared design flow temperature, which is about 10 K above what
systems actually run at.** A year of monitoring (weighted ΔT) still does
twice as well.

What does work is much simpler. A single measured coldest-day flow
temperature, in a plain regression, ranks systems far better than any of
the design-stage methods, and better than the same measurement fed
through the physics model ([One measured day beats the
physics](#one-measured-day-beats-the-physics)). So it's worth recording at
commissioning or in the first winter.

The model is in `scripts/design_model.py`
(`.venv/bin/python scripts/design_model.py`, see the README for setup). It
uses the Llanberis 2024 weather year in `llanberis2024.csv`.

## How it compares

Scored on the same 110 systems the same way, with SPF H4 from the
28 October 2026 site data (`scripts/design_vs_scop.py`; metrics as in
[mcs_and_scop_methods.md](mcs_and_scop_methods.md), the design model
unfitted). SCOP and MCS 031 use each system's *measured* hot water share,
so the design model is shown with both the declared and the measured
share:

| Method | Mean | Bias | R² | r² rescaled | ±90% |
|---|---|---|---|---|---|
| Manufacturer SCOP at design flow | 3.91 | −0.14 | 0.20 | 0.28 | ±0.73 |
| MCS 031 | 3.11 | −0.94 | −3.37 | 0.15 | ±0.80 |
| Design model, declared hot water share | 3.73 | −0.32 | −0.28 | 0.15 | ±0.79 |
| Design model, measured hot water share | 3.92 | −0.14 | 0.10 | 0.21 | ±0.76 |
| … with heat loss corrected | 3.99 | −0.06 | 0.15 | 0.20 | ±0.77 |
| **Coldest-day lift (one measured day)** | | | | **0.43** | **±0.65** |
| Weighted ΔT (a year of monitoring) | | | | 0.61 | ±0.54 |

- **Given the same information, the design model is level with SCOP and
  beats MCS 031.** It gets the average as close as SCOP (−0.14) and ranks
  systems almost as well (r² 0.21 against 0.28; bootstrap difference
  0.07, 95% interval −0.06 to +0.20). None of the three is clearly better
  at ranking.
- **The hot water share matters more than the method.** The declared
  share costs the design model 0.18 on the average and over a quarter of
  its ranking power.
- **All three are capped by the same input.** SCOP and the design model
  correlate at 0.71, because both mostly read the declared design flow
  temperature. All of them explain under 30% of the variation, against
  about 60% for weighted ΔT. [Why none of them get closer](#why-none-of-them-get-closer)
  looks at that input.
- **One measured coldest day explains 43%**, half as much again as SCOP,
  with nothing but a single day's flow and outside temperature.

## How it works

The design parameters set the weather compensation curve the system
*should* run. A standard weather year turns that into flow temperatures
and loads through the season, and from those we sum up an H\*-style ideal
COP. For each half-hour at outside temperature T:

```
Q        = U·(room − T) − gains − solar·aperture·0.9    space demand, smoothed over 24 h,
                                                         clipped to [0, capacity]
MWT      = room + 50·(Q / rad50)^(1/1.3)                 radiator equation
flowT    = MWT + system_ΔT / 2
r        = max(Q, min_mod·capacity) / capacity          load ratio
carnot   = (flowT + 3r + 273.15) / ((flowT + 3r) − (T − 8r))

H*_design = Σ heat / Σ (heat / carnot)                   plus a DHW block at its target temperature
SPF       ≈ η · H*_design
```

The 24-hour smoothing stands in for thermal mass: a midday solar surplus
can offset evening demand, and anything beyond that is thrown away. The
only tuned constant is the minimum modulation ratio, 0.25, set by a
coarse search.

### Defaults

A default system in the script stands in for a median fleet system
(`.venv/bin/python scripts/design_model.py --default`):

- 4.23 kW heat loss at −1.5 °C, with 120 + 240 W gains and 4 m² aperture,
  set so annual heat matches the fleet median.
- A 40 °C design flow temperature, set so the heat-weighted flow
  temperature over the year, hot water included, matches the fleet median.
- 14% DHW at 48 °C and 6 kW capacity (the median capacity to heat loss
  ratio is 1.4), with 35% minimum modulation rather than the global 0.25.
- η = 47%, a bench value without defrost, with defrost modelled
  separately (below).

| | Default system | Fleet median |
|---|---|---|
| Annual heat | 13,212 kWh | 13,205 kWh |
| Weighted flow temperature | 34.8 °C | 34.8 °C |
| Weighted outside temperature | 7.4 °C | 7.3 °C |
| Flow on a day averaging −1.8 °C | 39.0 °C | 37.3 °C |
| H\* (without defrost) | 9.60 | 9.05 |
| SPF | 4.35 | 4.00 |

Some of these aren't what you'd guess:

- **4.23 kW is below the median *measured* heat loss of 5.1 kW**
  (declared figures say 6.5 kW), which reflects zoning and setback in
  real homes.
- **The design temperature is −1.5 °C, not the fleet's median declared
  −3 °C**, because −3 °C shifts the weather year about 1.3 K colder than
  the fleet actually sees.
- **The curve is calibrated on the whole year, not the coldest day.**
  Weighted flow temperature is what drives SPF, and the two medians come
  from different systems, so one curve can't match both. Matching the
  coldest day instead (a 38.2 °C design flow) leaves the year 0.9 K too
  cool and gives SPF 4.45.

The default SPF (4.35) is still above the fleet median (4.00). The gap is
in H\*, not efficiency: SPF ÷ H\* is 0.453 for the default system and
0.446 for the fleet, but at the same weighted temperatures the model's
H\* is about 6% higher than measured. The likely reason is that the model
runs exactly on its curve, while real systems spread their temperatures
through cycling, setback recovery and hot-water reheats, which pulls a
harmonic mean down.

## Why none of them get closer

Across the full fleet (225 systems, cross-validated), the design model
does no better than in the comparison above, and feeding it better
inputs helps only a little:

| Predictor | cv R² | 90% PI |
|---|---|---|
| Weighted ΔT (a year of monitoring) | 0.59 | ±0.50 |
| H\*_design from declared parameters | 0.08 | ±0.74 |
| … with heat loss corrected | 0.06 | ±0.72 |
| … with the curve anchored on a measured coldest-day flowT | 0.13 | ±0.69 |
| … the same, leaving out the DHW block | 0.21 | ±0.65 |

Even coldest-day ΔT on its own does better (cv R² 0.42, see the
regression below). The inputs are why, and the first is the one SCOP and
MCS 031 depend on too:

- **Declared design flow temperature barely tracks reality (r = 0.33).**
  Declared median 45 °C, operated heat-weighted median 34.8 °C, with 5.3 K
  of scatter. Systems run about 10 K cooler than declared, and by very
  different amounts.
- **Declared heat loss runs about 24% high** (median ratio 0.76 across 134
  systems), though it does track measured heat loss well (r = 0.83), so it
  works once scaled down.

A more detailed model, or a better SCOP table, would hit the same wall,
because it would be fed the same inputs. Installer training doesn't close
the gap either: on the Heat Geek / Heating Academy subset (n = 120) the
flow temperature gap is just as wide (r = 0.34), which suggests deliberate
headroom and later tuning as much as mistakes.

A useful middle ground: corrected heat loss plus one measured coldest-day
flow temperature predicts the operated annual flow temperature with
r = 0.73 (±2.4 K). The remaining 2.4 K is behaviour and weather the
design sheet can't know, and at −0.11 SPF per K that alone costs about
±0.3 SPF.

## One measured day beats the physics

Skipping the physics and regressing SPF on one measured coldest day does
far better than any design-stage method (`scripts/regression_analysis.py`,
n = 224):

| Model | cv R² | 90% PI |
|---|---|---|
| Coldest-day lift | 0.42 | ±0.65 |
| + log utilisation | 0.47 | ±0.60 |
| + DHW share | 0.48 | ±0.60 |
| + emitter type (UFH/radiators, new/old) | 0.50 | ±0.56 |

```
SPF ≈ 7.27 − 0.070·lift + 0.31·ln(utilisation) − 0.67·DHW share   (before emitter type)
```

Utilisation needs annual heat, which a designer only has as an estimate
(declared heat loss × 0.76 × degree-days). Emitter type works as a
stand-in for operating temperature rather than a separate effect: it adds
nothing on top of weighted ΔT (README section 2). Declared design flow
temperature adds nothing at all (p = 0.92).

The striking part is that the design model was given the same
measurement, anchored on each system's coldest-day flow temperature, and
scored only 0.13 (0.22 without the DHW block). Stripping its assumptions
one at a time shows why (`scripts/design_model_ablation.py`, cv R²):

| Coldest-day anchored model | cv R² |
|---|---|
| As above, without the DHW block | 0.22 |
| … without load terms, gains or capacity limit | 0.28 |
| … and room at 20 °C, not the measured coldest-day room temperature | 0.32 |
| … and one climate, not shifted to each declared design temperature | 0.35 |
| Carnot COP on the coldest day, no extrapolation | 0.41 |
| Coldest-day lift alone | 0.42 |

Every step of the model costs a little and none adds anything. Projecting
one day's reading across a year can't add information the reading doesn't
have, and each assumption (load effects, gains, room temperature,
climate) brings its own noise. Physics only helps when its inputs are
better than the measurement it starts from, and here they aren't.

## Defrost

The model adds defrost separately rather than leaving it hidden inside
η. Two estimates from
[OEM forum thread 29547](https://community.openenergymonitor.org/t/what-scop-can-you-expect-from-a-system-that-runs-at-55c-and-50c-flow-temperatures-on-the-coldest-days/29547/18)
agree:

- Across the fleet, about 1.6% of net heat goes to defrost, about 0.15 SPF
  at SPF 4.
- One system measured COP 0.5–0.6 below its no-defrost trend over a cold,
  damp month. That annualises to 0.17–0.21 SPF.

The model cuts COP by up to 13% in frosting conditions: full penalty
between −2 and +2 °C, fading to zero by +6 °C, and tapering in drier air
below −2 °C. That gives 0.17 SPF for the default system. With defrost
included, the fleet's implied η rises from 0.458 to 0.480, which matches
bench values.

## What to take from this

- A simple steady-state model gets about as close as manufacturer SCOP
  and closer than MCS 031, given the same hot water information, but no
  closer than that.
- All three do much worse than measured ΔT for the same reason: declared
  design parameters describe intent, not operation.
- **One measured coldest day beats every design-stage method**, and a
  plain regression on it beats the physics model given the same reading.
- **The leverage is in commissioning data.** If we recorded the
  as-commissioned weather compensation curve, or even one measured
  coldest-day flow temperature, a design-time prediction of about ±0.3 at
  best looks reachable. Treat declared design flow temperature as an
  upper bound.

Caveats: one weather year (Llanberis 2024, shifted to each design
temperature) stands in for local climate, and postcode degree-days would
be better. System ΔT is fixed at 5 K and room at 20 °C, and underfloor and
fan coil emitters go through the radiator equation. The model hasn't yet
been tested against the simulator sweeps (README section 4), where H\*
itself scores ±0.25 to ±0.33.
