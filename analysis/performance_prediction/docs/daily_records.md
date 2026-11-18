# Daily records: which day predicts SPF, and how early

First steps on the "daily records" idea from the README. Data is the
public daily stats (system/stats/daily) for the 272 systems on the
predictors page (H4, air source, no cooling, ≥330 days), for the year to
2026-11-16 and, where available, the year before.

## Which single day's ΔT predicts annual SPF best?

The predictors page uses flow − outside ΔT on the coldest day. Taking the
day where most of the year's heat is delivered instead does better. Same
264 systems throughout, target combined SPF:

| ΔT measure | R² | leave-one-out RMSE |
|---|---|---|
| Annual heat-weighted | 0.61 | 0.31 |
| At the heat-median outside temperature (per-system line through daily ΔT) | 0.60 | 0.31 |
| At the peak of heat delivered by outside temperature | 0.58 | 0.32 |
| Modal 2 K outside temperature bin | 0.53 | 0.34 |
| Coldest day | 0.44 | 0.37 |

Most heat is delivered at about 7 °C outside (median 7.2, IQR 6.4–7.9).
Reading each system's daily ΔT line at fixed temperatures, R² rises from
0.35 at −3 °C to 0.55 at 7 °C and falls again at 10 °C. The heat-median
figure nearly matches the annual one because, for a roughly linear weather
compensation curve, the heat-weighted ΔT is the ΔT at the heat-weighted
outside temperature. The coldest day adds nothing once annual ΔT is known.

For space heating COP alone the cold end carries a little extra:
peak-heat ΔT plus coldest-day ΔT reaches R² 0.60 against 0.56 for annual.

Averaging real days rather than reading a fitted line: the N days closest
in outside temperature to the heat-median temperature give R² 0.43 for one
day, 0.56 for five and a plateau of about 0.58 from 10–20 days. These all
sit within about half a kelvin of each other. Consecutive calendar days do
badly (0.42), since they span a wide range of weather. For space heating
COP, 14–20 near-median days (0.585) beat the annual ΔT (0.56), because
they leave out the summer hot water days.

## How early can annual SPF be projected?

Simulating a system joining on each day of the year and using only its
first K days, the best approach scales the COP so far:

    log(annual SPF) = c0 + c1·log(COP so far) + c2·ΔT + c3·outsideT + c4·hot water share

with ΔT and outsideT heat-weighted over the days so far. One pooled fit
across the year doesn't work (63–65% within ±10% from 1–2 weeks in
December), because how much heating is still to come depends on the
calendar. Separate coefficients per start week and K, each fitted on start
dates within ±14 days, do work.

Calibrated on one year and tested on the other (both ways), the share of
systems within ±10% of their annual SPF:

| Start | 7 days | 14 | 30 | 60 | 90 | 120 | 180 | 240 |
|---|---|---|---|---|---|---|---|---|
| Dec | 85 | 86 | 89 | 97 | 99 | 99 | 100 | 100 |
| Jan | 87 | 91 | 95 | 96 | 98 | 99 | 99 | 99 |
| Feb | 92 | 93 | 95 | 97 | 97 | 96 | 95 | 97 |
| Mar | 89 | 92 | 92 | 90 | 90 | 89 | 87 | 90 |
| Apr | 75 | 78 | 78 | 77 | 77 | 75 | 78 | 92 |
| May | 68 | 69 | 68 | 69 | 69 | 69 | 79 | 98 |
| Jun | 63 | 63 | 64 | 65 | 67 | 72 | 89 | 100 |
| Jul | 62 | 63 | 64 | 67 | 73 | 79 | 97 | 100 |
| Aug | 63 | 64 | 66 | 74 | 80 | 91 | 100 | 100 |
| Sep | 64 | 69 | 76 | 82 | 92 | 97 | 100 | 100 |
| Oct | 77 | 82 | 85 | 93 | 96 | 99 | 100 | 100 |
| Nov | 85 | 89 | 92 | 93 | 98 | 99 | 100 | 100 |

In the heating season one to two weeks is enough. Summer data is mostly
hot water and says little about space heating, so a summer joiner waits
until the heating season arrives, whatever the number of days. ΔT alone
reaches about 80% within ±10% even with a full year: it explains
performance, but scaling the measured COP is what forecasts it.

Caveats: two weather years only, each treated as circular; systems that
change settings mid-season move away from any early projection.

## A physical model by outside temperature

The [/outsidetemp](../../../www/views/outsidetemp.php) page bins a system's
heat and electricity by daily outside temperature, split into space heating
and hot water, and fits a small physical model to a window of days:

- space heat per day = k × (balance − outside), zero above the balance
  temperature. The balance is fixed at 15.5 °C unless the window has at
  least 7 days above 12 °C
- hot water per day: a line in outside temperature if the window spans
  10 K or more, otherwise a constant
- COP from Carnot (condensing at flow + 2, evaporating at outside − 6) with
  a heat-weighted line through daily flow temperature and one practical
  efficiency η that matches the window's electricity; space heating and hot
  water each get their own
- standby electricity as a constant per day

The model is then run over all 365 days of a reference year. Days the system
didn't measure take the mean outside temperature of the 5 nearest systems
with a full year, so a system with a few days of data still gets a full-year
histogram and projection. On the handful of systems looked at, η was
45–55% of Carnot for space heating and 40–47% for hot water.

Tested on the same samples as the projection above
([physical_projection_test.py](../scripts/physical_projection_test.py),
271 systems, start every week, 7–180 days). The physical model is fitted on
the window and run over the system's own year, with no fleet calibration.
The hybrid adds log(physical projection) as a feature to the empirical model.
Share within ±10%, on the 93% of samples where the physical model projects:

| | within ±10% |
|---|---|
| Empirical (cross-year) | 84.2% |
| Physical | 78.2% |
| Hybrid (cross-year) | 85.8% |

By start month and days:

| Start, days | Empirical | Physical | Hybrid |
|---|---|---|---|
| Dec, 7 | 87 | 74 | 87 |
| Jan, 14 | 91 | 82 | 91 |
| Mar, 14 | 85 | 91 | 86 |
| Mar, 30 | 85 | 93 | 89 |
| Apr, 180 | 76 | 73 | 81 |
| Jun, 180 | 87 | 95 | 93 |
| Jul, 7 | 66 | 10 | 67 |
| Sep, 60 | 82 | 90 | 88 |

In midwinter a week or two of cold days doesn't pin down the heat line, and
the physical projection reads 3–4% high. In spring and autumn the window
covers a wide range of outside temperatures, the heat line and COP curve are
well determined, and the physics does better than the calendar scaling. In
summer there is too little space heating to fit and it reads 15–30% low.

So the physical view mainly explains the result (where the heat goes, the
heat line, η, the ΔT at the heat-median temperature) and adds a small amount
of accuracy as a hybrid feature, mostly in March, April and September. On
the page, a new system is projected over last year's weather near it rather
than its own year, which adds a little error not in this test. Only 136
systems have the earlier year, so the cross-year fits learn from that one.

## On the site

- [scripts/projected_spf.php](../../../scripts/projected_spf.php) runs
  daily and writes `projected_spf`, `_low`, `_high` (p10–p90 of the
  cross-year errors), `_within10` and `_days` to `system_meta` for air
  source systems with 7–364 days of data. A projection is stored only once
  the band is within ±10%.
- A day counts if it was monitored, with or without heat. Systems that
  shut down for summer (hot water from elsewhere) are common, and their
  standby electricity belongs in the SPF. The table above was produced
  before this change; on the wider fleet it includes, the cross-year
  figure is 85% within ±10% rather than 87%.
- Systems without an outside temperature sensor use a fallback fitted on
  COP so far and hot water share only (82% within ±10% cross-year). In
  winter it needs about 30 days rather than 7–14 before its band is
  inside ±10%.
- Only systems within latitude 47–61°, longitude −11 to 15° (or with no
  location) are projected. The calibration fleet is almost all UK and
  Ireland, with a few in north-west Europe, so the seasonal shape it
  learns doesn't carry to Spain or Idaho.
- The model is [scripts/projected_spf_model.json](../../../scripts/projected_spf_model.json),
  fitted by [projected_spf_calibrate.py](../scripts/projected_spf_calibrate.py).
  Refit once a year: `--fetch DIR` then `DIR`.
- The system page shows a "Projected annual SPF" card, and the fields are
  optional columns in the system list. They aren't used in rankings.
