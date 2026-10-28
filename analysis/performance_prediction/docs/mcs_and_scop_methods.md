# MCS 031 and manufacturer SCOP estimates

UK installers estimate performance before installing a heat pump in two
ways:

1. **Manufacturer SCOP.** The MCS product directory lists each unit's
   SCOP (EN 14825, average climate) at every flow temperature from 35 to
   65 °C. Space heating is read off at the design flow temperature. A
   common convention reads hot water off the same table at the cylinder
   temperature + 5 K, so an Arotherm+ 12 kW gets 3.63 for a 50 °C
   cylinder (55 °C flow) and 3.92 for 45 °C.
2. **MCS 031 a) issue 5.0** (December 2025). This uses a generic SPF by
   design flow temperature, the same for every product and every heat loss
   band, and divides hot water by 1.9. An immersion term is added if
   pasteurisation is done by immersion.

   | Design flow (°C) | ≤35 | 36–40 | 41–45 | 46–50 | 51–55 | 56–65 |
   |---|---|---|---|---|---|---|
   | ASHP SPF | 4.0 | 3.8 | 3.4 | 3.1 | 2.8 | 2.5 |

Both were run on the fleet's Arotherm+ systems, because the Arotherm+ is
the one make with enough systems (131 in the standard filter) and a clean
SCOP table for every size. Each method was given the system's measured
annual space and water heat, so only the efficiency side is being tested,
not the EPC demand estimate. The inputs are the declared design flow
temperature and DHW target from the form (DHW target defaults to 50 °C
where blank).

The main comparison uses the 78 systems that meter hot water separately.
For the rest, all heat counts as space heat, which flatters both SCOP
methods.

## Results

Measured SPF H4 averages 4.08.

| Method | Needs | Mean | Bias | MAE | R² | r² rescaled | ±90% |
|---|---|---|---|---|---|---|---|
| Manufacturer SCOP at design flow | design sheet | 3.93 | −0.15 | 0.34 | 0.15 | 0.27 | ±0.62 |
| MCS 031 | design sheet | 3.18 | −0.90 | 0.90 | −4.0 | 0.18 | ±0.67 |
| Weighted ΔT | a year of monitoring | 4.06 | −0.03 | 0.24 | 0.54 | 0.53 | ±0.49 |
| ΔT + log annual heat | a year of monitoring | 4.06 | −0.03 | 0.24 | 0.56 | 0.56 | ±0.48 |

The ΔT rows are leave-one-out fits over the full 225-system fleet, scored
on the same 78 systems. R² scores each method's predictions as they come
out, so an average error counts against it; below zero means worse than
predicting the fleet average for every system. r² rescaled is what's left
once a straight line from the method's output to measured SPF is fitted,
which measures how well it ranks systems. ±90% is the spread of the error
once its bias is removed.

**MCS 031 is too pessimistic by about 0.9.** Every one of the 78 systems
beat its MCS 031 estimate, and 90% beat even the optimistic end of its
±10% range. Its R² is negative because of that offset, and even rescaled
it explains only 18% of the variation, since it only sees the design flow
temperature band and the hot water share.

**Manufacturer SCOP gets the average about right, but for the wrong
reasons.** It's 0.15 low on average and 63% of systems land within 10% of
it. But it explains only 15% of the variation as it stands, and 27% even
rescaled, against about half for ΔT. The average comes out right because
two errors cancel, as the space/water split shows.

## Space heating and hot water separately

| | Measured | SCOP method | MCS 031 |
|---|---|---|---|
| Space heating COP | 4.40 | 4.06 at the design flow | 3.58 |
| Hot water COP | 3.00 (p10–p90 2.3–3.6) | 3.32 at target + 5 K | 1.9 |

**Space heating.** The declared design flow temperature is a median 10 K
above the measured mean space heating flow temperature (about 31 °C, see
also [design_model.md](design_model.md)). Read at the design flow, the
SCOP table under-predicts space COP by 0.34. Read at the measured flow it
over-predicts. The table stops at 35 °C, so clamping there gives +0.07,
and extending it down to the real ~31 °C with its own 35–40 °C slope gives
+0.27. So the SCOP curve is optimistic at the temperatures systems
actually run at, and the declared design temperature hides that by being
pessimistic.

**Hot water.** 1.9 is far too low for these systems. Their measured hot
water COP is a median 3.08. The +5 K scop method is closer (0.33 high on
average) but it doesn't rank systems at all (r = 0.13). Reading SCOP at
the measured mean DHW flow temperature is much worse (+0.87): a seasonal
space heating figure isn't a hot water figure, and the +5 K offset is
doing real work.

## Examples

Measured SPF H4 against each prediction, picking the lowest, median and
highest system for each size.

| id | kW | Design flow | DHW target | Space flow | DHW share | Actual | SCOP | MCS 031 | ΔT |
|---|---|---|---|---|---|---|---|---|---|
| 428 | 3.5 | 40 | 45 | 29.1 | 15% | 4.47 | 3.91 | 3.29 | 4.32 |
| 499 | 5 | 45 | 55 | 36.4 | 13% | 3.32 | 3.59 | 3.09 | 3.57 |
| 346 | 5 | 35 | 48 | 29.0 | 8% | 4.20 | 4.34 | 3.68 | 4.28 |
| 373 | 5 | 40 | 45 | 30.2 | 16% | 4.78 | 4.00 | 3.29 | 4.43 |
| 211 | 7 | 50 | 45 | 36.4 | 27% | 3.20 | 3.65 | 2.65 | 3.28 |
| 557 | 7 | 47 | 52 | 33.4 | 8% | 3.98 | 3.75 | 2.94 | 3.98 |
| 424 | 7 | 40 | 49 | 29.9 | 6% | 4.88 | 4.08 | 3.59 | 4.34 |
| 416 | 10 | 40 | – | 32.1 | 19% | 3.97 | 4.34 | 3.18 | 3.97 |
| 284 | 10 | 40 | – | 31.0 | 24% | 4.50 | 4.29 | 3.07 | 4.15 |
| 568 | 10 | 35 | 49 | 27.1 | 6% | 5.20 | 4.92 | 3.76 | 4.73 |
| 689 | 12 | 48 | 51 | 31.9 | 9% | 3.86 | 3.99 | 2.94 | 4.22 |
| 608 | 12 | 45 | 55 | 29.4 | 10% | 4.60 | 4.11 | 3.16 | 4.15 |
| 776 | 12 | 45 | 45 | 28.1 | 14% | 4.69 | 4.17 | 3.06 | 4.46 |

The two 40 °C, 10 kW systems (416 and 284) show the problem with design
inputs: same declared design, near-identical SCOP estimates, half a point
apart in practice.

## What to take from this

- MCS 031 issue 5.0 underestimates every one of these systems, by a
  median 28% (2% to 65%), so it doesn't work as an estimate of what a
  system will achieve.
- Manufacturer SCOP gets the fleet average about right (3.9 against 4.1
  measured), but only because two errors cancel. Declared design flow
  temperatures are about 10 K higher than systems actually run at, and at
  the temperatures they do run at, SCOP is 0.3–0.45 too high. For
  individual systems it's a weak guide: about a third are more than 10%
  out.
- Neither design-stage method ranks systems as well as measured ΔT,
  which is the same conclusion as [design_model.md](design_model.md):
  the declared design flow temperature is the weak input.

## Which MCS 031

Two documents dated 05/12/2025 are current, one per scheme: MCS 031 a)
issue 5.0 (MIS 3005 contractors) and MCS 031 a): 2025 issue 2.0
(MCS:2025 contractors). Their method and Table 2 are identical. The only
change from issue 4.0 (December 2024, mandatory from March 2025) is hot
water at 1.9 instead of 1.7, which moves the fleet estimate from 3.10 to
3.18. Systems quoted before March 2025 got the older SCOP-based method
(issue 3), which is close to the manufacturer SCOP method above. The
MCS standard page still links issue 4.0.

Points of the method worth knowing:

- **The customer sees the table SPF, not the blend.** The template shows
  the space heating SPF from Table 2 (3.52 on average for the systems
  below) and an electricity range. The blended 3.18 is what that
  electricity estimate implies.
- **Existing radiators default to 60 °C** (SPF 2.5) unless a room-by-room
  heat loss justifies lower. Only 3 of the 78 have existing radiators
  alone, all declared at 35–45 °C. At the 60 °C default they'd get 2.39
  against a measured 3.65.
- **Demand comes from the EPC.** Only 10 of the 78 have both EPC fields.
  Their EPC hot water share matches the measured one (18%), so the SPF
  barely moves (3.06 to 3.04). Their measured electricity is a median 0.72
  of the MCS estimate (IQR 0.67–0.76), and none is inside the ±10% range.
- **The heat loss band doesn't change the SPF**, only the star rating
  and emitter notes.
- **Hot water is 1.9 regardless of cylinder temperature or coil.** The
  immersion term (weekly 50 → 60 °C by immersion) is extra.
- **Alternative estimates are allowed.** A SCOP-based figure can be
  given, but not more prominently than the MCS one, and with a
  warning if it is much better.
- **Appendix A** (no domestic EPC, e.g. non-domestic) still uses the
  product SCOP, with hot water at the SCOP for its flow temperature.

## Caveats

- Arotherm+ only. `scripts/fetch_mcs_scop.py "Vitocal 150" "Ecodan"`
  prints tables for other makes, but per-make numbers in the fleet are
  small.
- The measured space flow is the time mean while running, not heat
  weighted, so it reads perhaps 1–2 K low. That moves the "at measured
  flow" figures by about 0.1, not the conclusions.
- SCOP is a unit figure for an average European climate (design −10 °C).
  SPF H4 also includes external pumps and standby, and UK climates are
  milder. These partly offset.
- The tests don't use MCS 031's EPC-based demand, which is a separate
  source of error in the electricity estimate.

## Files

| Path | What it is |
|---|---|
| `scripts/fetch_mcs_scop.py` | pulls SCOP tables from the MCS product directory, writes `mcs_scop_arotherm_plus.csv` |
| `scripts/scop_mcs031_comparison.py` | the comparison, writes `scop_mcs031_comparison.csv` (one row per system) |
