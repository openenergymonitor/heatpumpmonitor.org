# How accurate is a measured SPF?

If metering error were large, no prediction could be checked against it
more tightly than that error. So how good are the meters in this fleet?
Most use SDM120 class 1 electricity meters and Axioma Qalcosonic class 2
ultrasonic heat meters.

**For a correctly installed system, about ±0.10 SPF (90%).** The
certification worst case is ±0.29, but real meters sit well inside it.

## The worst case allowed by the standard

EN 1434 adds three error limits together:

```
flow (class 2):  ±(2 + 0.02·qp/q) %     capped at ±5%
temperature pair: ±(0.5 + 3·ΔΘmin/ΔΘ) %
calculator:      ±(0.5 + ΔΘmin/ΔΘ) %
```

Heat pumps run at small flow−return differences (fleet median 3.8 K, p10
3.0, p90 5.2), close to the 3 K minimum the meter is rated for, which
inflates the temperature terms. At each system's own operating point the
allowed heat meter error is a median 6.2%. With 1% for the electricity
meter on top, that gives about ±7.2%, or **±0.29 at SPF 4**. That's what
certification permits, not what real meters do.

## What real meters do

- **BEIS/DECC lab tests** (12 meters including ultrasonic): matched
  temperature pairs got ΔT right to within about 0.05 K. That's around
  1.3% at 3.8 K, against 2.9% allowed. Correctly installed flow
  measurement was typically within a few percent, and new meters of the
  same model agreed closely.
- **OEM bench tests**: Axioma −1.7% on average (−0.5 to −3.2%), Sontex
  +0.6% (−1.3 to +2.2%). Some of the Axioma offset was traced to pipework
  heat loss before the meter rather than the meter itself.
- **Two heat meters on one heat pump** for a month agreed on COP to 0.02
  (4.02 vs 4.00).

Combining the independent parts:

| Part | 90% bound |
|---|---|
| Flow measurement | ±2.0% |
| ΔT measurement (0.05 K) | ±0.9–1.7%, median 1.3% |
| Calculator | ±0.5% |
| Electricity meter | ±0.5% |
| **COP** | **±2.5%, about ±0.10 at SPF 4** |

That matches the conclusion of the OEM heat meter testing thread and the
two-meter comparison.

## When it's worse

- **Glycol.** BEIS found 1–4% extra error at 10% glycol and 7–8% at 30%,
  because the meter assumes water. We don't record glycol, and it would be
  worth adding as a field.
- **Very low ΔT.** 23 of the 225 systems have a weighted flow−return ΔT
  below 3 K.
- **Bad installs.** Across RHI audit data BEIS estimated −5.9% to +2.8%
  (95%), driven by a minority of poor installs such as strap-on sensors.
  Pocketed sensors on MID installs should be near the lab end, but any
  fleet will have a few faulty ones.
- **Age and dirt** can make ultrasonic meters fail. The fleet is young,
  so this matters later rather than now.

## What it means for prediction

- ±0.10 is the floor when testing against measured SPF. A perfect model
  would still scatter about ±0.10, and a model that's itself good to
  ±0.10 would show about ±0.14. Anything claimed below ±0.10 is suspect.
- Metering explains only about 4% of the current scatter
  (0.10² / 0.51²). The rest is real differences between systems.
- Systems far from any prediction (more than about 0.5 out) are worth
  checking for glycol, low ΔT or install problems before reading them as
  genuinely good or bad.

## Sources

- [OEM heat meter accuracy testing thread](https://community.openenergymonitor.org/t/heat-meter-accuracy-testing/27306)
- [BEIS/DECC Heat Meter Accuracy Testing final report (2016)](https://assets.publishing.service.gov.uk/media/5a8050cd40f0b62302692c81/Heat_Meter_Accuracy_Testing_Final_Report_16_Jun_incAnxG_for_publication.pdf)
- [Axioma Qalcosonic E3 datasheet](https://stockshed.uk/files/axioma/qalcosonic%20e3%20data%20sheet.pdf)
  (ΔΘmin = 3 K assumed, standard for the class)
- Fleet operating conditions from the all-fields export
  (`weighted_flowT_minus_returnT`, `running_heat_mean`), n = 225
