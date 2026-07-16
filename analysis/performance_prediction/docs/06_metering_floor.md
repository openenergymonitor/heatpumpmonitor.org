# How accurate is a measured SPF? The metering floor, revisited

Earlier analysis notes quoted a metering noise floor of "±0.13–0.22 SPF at
SPF 4" from generic MID class limits. This note replaces that estimate with one
built from the actual instruments used across the HeatpumpMonitor.org fleet
(SDM120 class 1 electricity meters; Axioma Qalcosonic E3-family class 2
ultrasonic heat meters), the EN 1434 error model, the BEIS/DECC heat meter
accuracy testing programme, and OpenEnergyMonitor's own bench and in-situ
testing.

**Headline: the realistic floor is ~±0.10 SPF (90%) for a correctly installed
system — about half the earlier estimate — while the certification worst case
is ±0.29. The ±0.1 prediction ambition is therefore not arithmetically blocked
by metering; it sits right at the metering floor.**

## The EN 1434 worst case (what class 2 *permits*)

EN 1434 sets maximum permissible errors (MPE) as three components summed
arithmetically:

```
flow (class 2):  Ei = ±(2 + 0.02·qp/q) %      capped at ±5%
temp pair:       Et = ±(0.5 + 3·ΔΘmin/ΔΘ) %
calculator:      Ec = ±(0.5 + ΔΘmin/ΔΘ) %
```

Evaluated at each fleet system's actual operating point (weighted flow−return
ΔT, implied flow rate from mean heat output; ΔΘmin = 3 K, qp = 2.5 m³/h):

- fleet operating ΔT: median 3.8 K (p10 3.0, p90 5.2) — heat pumps live near
  the bottom of the heat meter's rated ΔT range, which is what inflates Et/Ec
- heat meter MPE: median **6.2%** (5.4–7.1% across the fleet)
- adding ±1% for the class 1 electricity meter: **COP MPE ≈ ±7.2% → ±0.29 at
  SPF 4**

This is the number a sceptic can always quote — but it is a *certification
envelope*, not a description of real meters.

## What real meters actually do

Three independent sources agree that correctly installed meters sit far inside
the envelope:

**BEIS/DECC lab programme** (8 new DN25 + 4 DN40 meters, incl. ultrasonic):
- matched temperature-sensor pairs measured ΔT errors **mostly below 0.05 K**
  — at the fleet's median 3.8 K ΔT that is ~1.3%, versus the ~2.9% the Et
  formula permits. (Absolute temperature errors were up to 0.6 °C — fine for
  energy, a caution for using heat-meter flowT readings as temperature data.)
- unit-to-unit variability between meters of the same model: negligible when new
- correctly installed flow measurement: within class, typically low-single-digit %

**OpenEnergyMonitor bench testing** (Axioma E4 vs Sontex 531 vs electric
input, steady state): Axioma −1.7% average (range −0.5 to −3.2%), Sontex +0.6%
(−1.3 to +2.2%) — i.e. ±2% covers observed behaviour, some of which was traced
to pre-meter pipework heat loss rather than the meter.

**In-situ cross-check**: two heat meters on the same heat pump for a month
agreed on COP to 0.02 (4.02 vs 4.00).

## Realistic floor for a correctly installed fleet system

Combining independent components in quadrature (they are separate instruments
and mechanisms), per system at its own operating ΔT:

| Component | 90% bound |
|---|---|
| flow measurement (bench-observed envelope) | ±2.0% |
| ΔT measurement (0.05 K at operating ΔT) | ±0.9–1.7% (median 1.3%) |
| calculator | ±0.5% |
| electricity (SDM120, in practice) | ±0.5% |
| **combined COP** | **±2.5% ≈ ±0.10 at SPF 4** |

This matches the conclusion of the OEM heat meter testing thread ("±0.1 COP or
better on a SPF of 4.0") and the 0.02-COP two-meter agreement observed in situ.

## Caveats — when the floor is higher

- **Glycol.** BEIS measured ~1–4% additional error at 10% glycol and 7–8% at
  30% glycol on ultrasonic meters (fluid properties differ from the water the
  meter/calculator assumes). Glycol concentration is not currently recorded in
  the fleet metadata; glycol systems may carry a several-percent systematic
  heat over-read. Worth a metadata field.
- **Low-ΔT operation.** 23 of 225 fleet systems run a *weighted average*
  flow−return ΔT below the 3 K rated minimum — their ΔT-term error is larger
  and less well characterised. (Instantaneously, all systems spend some time
  below 3 K; energy-weighting limits the damage since little energy flows at
  tiny ΔT.)
- **Installation faults.** The BEIS population Monte Carlo (RHI audit
  statistics) put overall metering uncertainty at −5.9% to +2.8% (95%) —
  dominated by a minority of poor installs (strap-on temperature sensors
  average 9% error; mismatched sensor installation up to 60%). MID-certified
  monitored installs with pocketed sensors should sit near the clean-lab end,
  but a tail of faulty installs plausibly exists in any fleet.
- **Ageing and dirt.** BEIS long-term tests showed ultrasonic meters either
  failing dramatically with air/dirt or stabilising; recalibration intervals
  are unresolved. The fleet's meters are young; drift is a future, not
  current, concern.

## Implications for the performance-prediction work

1. **The ±0.1 ambition is back on the table, just.** A hypothetical perfect
   predictor would still show ~±0.10 of scatter against *measured* SPF from
   metering alone; a model that is itself good to ±0.10 would show ~±0.14
   combined. Claims below ~±0.10 against measured SPF remain automatically
   suspect.
2. **Metering explains little of the current ±0.45–0.50 spread.** ±0.10 of
   metering noise accounts for only ~4% of the observed residual variance
   (0.10²/0.51²) — the spread is real system behaviour, which is consistent
   with the simulator reproducing ⅔ of it from physics alone.
3. **A tail of outliers may still be metering.** Systems far off any physics
   prediction (|residual| > 0.5) are candidates for glycol, sub-3K operation,
   or installation issues before being read as genuinely anomalous performance.

## Sources

- [OEM heat meter accuracy testing thread](https://community.openenergymonitor.org/t/heat-meter-accuracy-testing/27306)
  — bench setup, Axioma/Sontex results, in-situ two-meter comparison
- [BEIS/DECC Heat Meter Accuracy Testing final report (2016)](https://assets.publishing.service.gov.uk/media/5a8050cd40f0b62302692c81/Heat_Meter_Accuracy_Testing_Final_Report_16_Jun_incAnxG_for_publication.pdf)
  — EN 1434 MPE formulas, calibration/installation/glycol/dirt test results,
  RHI population Monte Carlo
- [Axioma Qalcosonic E3 datasheet](https://stockshed.uk/files/axioma/qalcosonic%20e3%20data%20sheet.pdf)
  — class 2, qp options, Pt500 pairs (ΔΘmin = 3 K assumed, standard for the class)
- Fleet operating conditions: computed from `heatpumpmonitor_all_fields`
  (`weighted_flowT_minus_returnT`, `running_heat_mean`), n = 225 filtered systems
