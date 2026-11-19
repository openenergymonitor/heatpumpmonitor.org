# Machine replay: how close each system runs to its datasheet

Working notes, stage 1 of tuning a twin to each system. Several of the
conclusions below are provisional.

## What it does

The simulated twins mix two things: how well the machine turns electricity
into heat, and how the system runs it. The replay tries to separate them.
It takes a year of each system's real 10 s data and asks what a Vaillant
Arotherm+ datasheet COP fit would have used to make the same heat, at the
same flow temperature, outside temperature and output. The gap between that
and the measured electricity is then split by operating state (steady,
ramp, start-up, DHW, defrost, standby).

It covers the 132 validated Arotherm+ systems; 126 end up with a clean
result.

The main figure is the **level**: field COP / datasheet COP in steady space
heating at 30–70 rps, pooled over the year. 0.95 means about 5% more
electricity than the datasheet for the same heat. It describes the gap
but doesn't say whether the cause is the machine, the meters or something
else.

## Main findings so far

**Operation doesn't seem to separate good systems from poor ones.**
Start-ups, defrosts, ramps, DHW and standby each cost a small and similar
amount of SPF across the fleet (median 0.04–0.13 each). Starts per day has
no link to quality (rho −0.09). This also suggests the simulated twin's
operation is off: for system 75 it starts 33 times a day against a real
4.3.

**The level is most of the difference.** Median 0.96 (p10–p90 0.89–1.02).
It goes with quality at rho 0.77 and explains about 56% of the variance.
The next strongest factor is time at minimum speed (rho −0.43).

**Fast flowT rises need to be kept out.** An early version made some
systems look poor just above minimum speed. Most of that turned out to be
the start of DHW reheats that the DHW flag missed (or that weren't flagged
at all), plus heat stored in the unit and pipes before the meter. A "ramp"
state (flowT changing faster than 8 K/h) now excludes these. Most levels
barely moved, but a few changed a lot, e.g. 143 from 0.88 to 1.00.

**The datasheet doesn't cover how most systems run.** Vaillant's tables
start at 35 °C flow; the fleet's median steady flowT is 32.5 °C. Comparing
like-for-like systems, the fit seems to give low flowT about a quarter too
much credit, which flatters systems that run cool. A **peer level**
(compared against same-size systems in the same conditions) corrects for
this and is probably the fairer figure for ranking.

**Things that don't seem to explain the poorest systems:**

- Heat meter temperature sensor offsets. Measured offsets are around
  ±0.05 K; explaining the gap would need about +0.24 K.
- Pump power. Pump-only draw is around 23 W. That could be part of the
  fleet's typical +30 W gap, but not the +120–200 W on the worst systems.
- Heat lost on the primary pipes. Losses that large should show up in the
  pump-only data, and they don't.
- Metadata. Heat meter make, installer, emitters, controls, zones etc.
  show no clear link to the level.

**What we can't rule out yet is the heat meter's flow reading.** A flow
meter reading a few percent low would look exactly like a machine below
spec. The cylinder reheat check suggests meters are fine on the 10 systems
that have a cylinder sensor, but none of the lowest are among them.

**Results don't depend much on the COP model.** Across the three fits the
ranking (rho 0.88–0.99), the spread (sd ~0.056) and the within-size
patterns hold. The absolute level shifts by a few percent, and the 10 and
12 kW results depend on the fit used.

## The poorest systems

| System | Size | Level | Peer level | SPF now | SPF at level 1.0 | SPF at peer median | Notes |
|---|---|---|---|---|---|---|---|
| 371 | 5 kW | 0.74 | 0.80 | 3.46 | ~4.7 | 4.30 | Flat ~0.8 of peers at every speed |
| 269 | 5 kW | 0.81 | 0.87 | 4.02 | ~4.95 | 4.56 | No DHW flag |
| 741 | 7 kW | 0.86 | 0.88 | 3.80 | ~4.35 | 4.28 | Flag misses reheats |
| 516 | 5 kW | 0.86 | 0.92 | 3.99 | ~4.6 | 4.33 | DHW flag never set |
| 238 | 5 kW | 0.90 | 0.95 | 4.08 | ~4.55 | 4.27 | No DHW flag |

Only 371 clearly stands apart from its peers; the rest sit at the low end
of a continuous tail (51, 372, 384, 611 are similar).

371 and 269 fall short by roughly the same fraction at every speed, which
doesn't look like an extra fixed load. That leaves two likely explanations:
the unit is running below spec, or the heat meter's flow reads 13–20% low.
If it's the meter, the real SPF may already be close to the corrected
figures and only the published number is wrong.

**Next test:** compare the heat meter's flow with the Arotherm's own flow
reading (eBUS or myVaillant) on these systems, and look at how the meter
and unit are installed.

## Better than the datasheet

260, 654, 409, 211 and 335 sit at roughly 1.01–1.05 in all three models.
409 and 211 are good machines in poorer systems: they run at 39–40 °C flow,
inside the datasheet's range, so part of their lead may be that the fit
doesn't flatter them as it does cooler systems. A few others (752, 643,
59, 160, 584) look good only under some fits or after correcting a wrong
declared size.

## Open questions

- Is the poor tail the machine or the heat meter flow measurement?
- Why are systems that spend more time at minimum speed below the
  datasheet at every speed? Part of it may come from inferring speed from
  metered heat, which would push an under-reading system's running into
  the lowest band.
- The 5 kW vs 7 kW level gap (0.93 vs 0.97) isn't a clean comparison yet,
  since the 7 kW fit is scaled from the 5 kW one.

## Data notes

- Five systems fail the SPF cross-check and are excluded (26, 140, 240,
  363, 630). 348 duplicates 465 and is dropped.
- Five declared sizes look wrong and are run one size up: 59, 160, 330,
  584 (5 → 7 kW) and 81 (3.5 → 5 kW).
- 51 systems have no DHW flag, so DHW is guessed from flowT above 45 °C.
  The flag is never set on 433, 516 and 610.
- System 9 logs flow in m³/h and is left out of the pump-only checks.

## Scripts

Scans read phpfina feeds directly, so they run on the HeatpumpMonitor.org
server from the repo root. The Python summaries use the venv from the
README.

| Step | Script | What it does |
|---|---|---|
| 1 | `scripts/feed_scan/replay.php <id> [days] [--qnom=kW] [--model=v2\|generic\|unit]` | Replay one system, classify each sample by state, write `replay_<id>.json` |
| 2 | `scripts/feed_scan/replay_fleet.sh [ids_file] [parallel] [model]` | Run the fleet at declared size and one size up (~6 min, 3 in parallel) |
| 3 | `twins/replay_fleet.py [--model ...]` | Pick the size per system, fleet summary and correlations, write `twins/replay_fleet.csv` |
| 4 | `scripts/feed_scan/pumponly.php` / `pumponly_fleet.sh` | Pump-only running: sensor offset, loop UA and volume |
| 5 | `twins/replay_checks.py` | Check the level against sensor offset, extra watts and pipe losses |
| 6 | `twins/replay_models.py` | Compare the three COP fits |
| 7 | `scripts/feed_scan/dhwcal.php` / `dhwcal_fleet.sh` | Heat meter check from cylinder reheat rate (needs `heatpump_dhwT` and cylinder volume) |
| 8 | `scripts/feed_scan/lowmod.php` / `lowmod_fleet.sh` | Minute-by-minute diagnostics at low speed; `--dump` for plotting |

COP fits (from `www/lib/vaillant_cop_fit.js`, ported to PHP):

- `v2` (default): generic fit of the 5 and 12 kW tables, efficiency by
  inferred compressor speed
- `generic`: generic fit, efficiency by load fraction Q/Qnom
- `unit`: separate 5 kW and 12 kW fits, scaled for other sizes

Compressor speed is always inferred with the v2 capacity model, so the
speed bands are the same whichever COP fit is used.
