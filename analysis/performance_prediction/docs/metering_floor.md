# How accurate is a measured SPF?

Any prediction we make is checked against measured SPF, so it's worth
knowing how accurate those measurements are likely to be. Most systems in
the fleet use SDM120 class 1 electricity meters and Axioma Qalcosonic
class 2 ultrasonic heat meters.

On a correctly installed system, measured SPF looks to be accurate to
roughly **±0.10** (90% confidence). The certification standard allows up
to about ±0.29.

## What the standard allows

EN 1434 sets a worst case by adding up three error limits:

```
flow (class 2):   ±(2 + 0.02·qp/q) %     capped at ±5%
temperature pair: ±(0.5 + 3·ΔΘmin/ΔΘ) %
calculator:       ±(0.5 + ΔΘmin/ΔΘ) %
```

Heat pumps usually run with a small flow−return temperature difference
(fleet median 3.8 K, p10 3.0 K, p90 5.2 K). This is close to the 3 K
minimum the meter is rated for, which pushes the temperature terms up.

Working this out at each system's own operating point gives a median
allowed heat meter error of 6.2%. Adding 1% for the electricity meter
gives about ±7.2%, or ±0.29 at SPF 4.

## Accuracy testing

Three sources point the same way:

- **BEIS/DECC lab tests** (12 meters, including ultrasonic): matched
  temperature pairs measured the difference to within about 0.05 K. At
  3.8 K that's around 1.3%, compared with 2.9% allowed. Correctly
  installed flow measurement was typically within a few percent.
- **OEM bench tests**: Axioma meters read −1.7% on average (range −0.5 to
  −3.2%), and Sontex +0.6% (range −1.3 to +2.2%). Some of the Axioma
  offset may have been heat lost from the pipework before the meter,
  rather than the meter itself.
- **Two heat meters on one heat pump** for a month agreed on COP to
  within 0.02 (4.02 vs 4.00).

Combining the independent error sources gives a rough estimate:

| Part | 90% bound |
|---|---|
| Flow measurement | ±2.0% |
| Temperature difference (0.05 K) | ±0.9–1.7%, median 1.3% |
| Calculator | ±0.5% |
| Electricity meter | ±0.5% |
| **COP** | **about ±2.5%, or ±0.10 at SPF 4** |

## When accuracy is likely to be worse

- **Glycol.** BEIS found 1–4% extra error at 10% glycol and 7–8% at 30%,
  as the meter assumes plain water. Most systems on HeatpumpMonitor do not use glycol and those that do will have calibrated sontex heat meters.
- **Very low temperature difference.** 23 of the 225 systems have a
  weighted flow−return difference below 3 K.
- **Poor installs.** Using RHI audit data, BEIS estimated a range of
  −5.9% to +2.8% (95%), mostly due to a minority of poor installs such as
  strap-on sensors. Pocketed sensors on MID installs should be closer to
  the lab results, but a few faulty installs are likely in any fleet.
- **Age and dirt** can cause ultrasonic meters to drift or fail. The
  fleet is fairly young, so this is more likely to matter later.

## What this means for prediction

- ±0.10 is roughly the floor when testing against measured SPF. Even a
  perfect model would scatter by about ±0.10, and a model that is itself
  good to ±0.10 would show about ±0.14. A claimed accuracy much tighter
  than ±0.10 should be treated with caution.
- Metering likely accounts for only around 4% of the current scatter
  (0.10² / 0.51²). Most of the rest probably comes from real differences
  between systems.

## Sources

- [OEM heat meter accuracy testing thread](https://community.openenergymonitor.org/t/heat-meter-accuracy-testing/27306)
- [BEIS/DECC Heat Meter Accuracy Testing final report (2016)](https://assets.publishing.service.gov.uk/media/5a8050cd40f0b62302692c81/Heat_Meter_Accuracy_Testing_Final_Report_16_Jun_incAnxG_for_publication.pdf)
- [Axioma Qalcosonic E3 datasheet](https://stockshed.uk/files/axioma/qalcosonic%20e3%20data%20sheet.pdf)
  (ΔΘmin = 3 K assumed, standard for the class)
- Fleet operating conditions from the all-fields export
  (`weighted_flowT_minus_returnT`, `running_heat_mean`), n = 225
