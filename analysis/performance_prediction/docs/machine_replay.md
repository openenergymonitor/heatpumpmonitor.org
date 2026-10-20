# Machine replay: how close each system runs to its datasheet

The simulated twins (README section 4) mix two things: how the machine
converts electricity to heat, and how the system operates it. The machine
replay takes the operation out. It replays a year of each system's real
10 s data through a Vaillant Arotherm+ datasheet COP fit, at the measured
flow temperature, outside temperature and heat output, and compares the
datasheet's electricity with the measured electricity. Whatever is left is
the gap between the installed system and the datasheet, split by operating
state.

This is stage 1 of tuning a twin to each system. It covers the 132
validated Arotherm+ systems in the fleet.

The first part of this doc walks through the scripts in the order they're
run. The second part is the results.

## Part 1: how it's done

All scans read the phpfina feeds directly, so they run on the
HeatpumpMonitor.org server from the repo root. The Python summaries run
anywhere the scan outputs can be read, using the venv from the README.

### Step 1. Replay one system: `scripts/feed_scan/feed_scan_6.php`

```
php scripts/feed_scan/feed_scan_6.php <id> [days] [--qnom=kW] [--no-dhw-guess] [--out=dir] [--model=v2|generic|unit]
```

The scan reads the system's elec, heat, flowT, returnT, outsideT and (if
present) DHW flag feeds over the last 365 days, using the same sample
handling as `feed_scan_4.php`: nulls forward-filled across gaps under
15 min, and running meaning elec at or above the app's starting power. It
cross-checks its SPF against the published last-365 stats; across the
fleet it matches to within 0.02 for all but 5 systems.

The datasheet COP comes from one of three fits in the tools repo's
`www/lib/vaillant_cop_fit.js`, ported to PHP. All three are semi-physical
models (Carnot with load-dependent approach temperatures and a fitted
efficiency curve) fitted to Vaillant's Arotherm+ datasheet tables, which
exist for the 5 kW and 12 kW units:

| `--model` | Model | What it is |
|---|---|---|
| `v2` (default) | Speed-based generic fit, `copFitGenericV2()` | A generalised fit of both units' tables, with efficiency a function of inferred compressor speed. Pre-defrost by construction |
| `generic` | Capacity-normalised generic fit, `copFitGeneric()` | A generalised fit of both units' tables, with efficiency a function of load fraction Q/Qnom. Run with frost off |
| `unit` | Capacity-specific unit fits, `copFit("5kW")` and `copFit("12kW")` | One fit per unit's table. The 5 kW fit is used up to 7 kW and the 12 kW fit from 10 kW, with load fraction against the system's own capacity. Exact for 5 and 12 kW, a scaled stand-in for 3.5, 7 and 10 kW. Run with frost off |

Frost is off in all three because the replay handles defrost as its own
state. The scan checks each port against reference values from the JS
before it starts.

Compressor speed in rps is inferred from the declared capacity (or
`--qnom`), the heat output and the measured temperatures, using the v2
capacity model whatever the COP model. So the speed bands are the same in
all three and only the datasheet COP changes. Other models add
`_m<model>` to the output name.

Each 10 s sample is put in one state:

| State | Meaning |
|---|---|
| steady | space heating, compressor running, outside the windows below |
| startup | first 10 min after a compressor start |
| dhw | DHW flag set (with no flag feed, flowT above 45 °C) |
| defrost | running with negative heat, plus 10 min of recovery |
| post_dhw | first 10 min after a DHW to space switch |
| beyond | inferred speed above 130 rps: meter spikes or stored heat |
| off | compressor not running |
| unclass | temperatures missing |

The datasheet electricity is heat / datasheet COP, computed only where the
metered heat is the compressor's output (steady, startup, dhw, defrost).

The report then gives:

- an SPF ladder from the pure datasheet replay to the measured SPF, one
  state at a time, so the steps add up exactly
- a speed map: field COP / datasheet COP by outside temperature and
  inferred speed, plus extra watts and the meter dT offset that would
  explain the gap in each cell
- time of day and time since start tables, defrosts and cycling

It writes `replay_<id>[_q<kW>].json` for the fleet scripts.

**Level** is the one number used most below. It's field COP / datasheet
COP in steady space heating at 30–70 rps, pooled over the year (datasheet
electricity / measured electricity). A level of 0.95 means the system uses
about 5% more electricity than the datasheet for the same heat at the same
temperatures. Below 30 rps is kept separate as the **min-speed penalty**
(field / datasheet at minimum speed, divided by the level).

Level is worked out from measured electricity and heat, so it's a
description of the gap, not a predictor. It doesn't say whether the cause
is the machine, the meters or something else on the electricity meter.

### Step 2. Run the fleet: `scripts/feed_scan/replay_fleet.sh`

```
scripts/feed_scan/replay_fleet.sh [ids_file] [parallel] [model]
scripts/feed_scan/replay_fleet.sh "" 3
scripts/feed_scan/replay_fleet.sh "" 3 generic && scripts/feed_scan/replay_fleet.sh "" 3 unit
```

The ids file (`twins/replay_ids.txt`) has lines of `id kW`. Each system
runs twice: at its declared capacity and at the next Arotherm+ size up,
because some declared sizes are wrong. Scans run under `nice` with PHP's
tracing JIT. 257 scans took 6 min at 3 in parallel, about 4.3 s per scan
per process, against 8 s without the JIT. Outputs go to
`scripts/feed_scan/replay/`.

### Step 3. Summarise the fleet: `twins/replay_fleet.py`

```
.venv/bin/python twins/replay_fleet.py [--model generic|unit]
```

For each system it takes the declared run, or the upsized run when the
declared one shows 10+ hours of believable output beyond the fit's
maximum (COP under 8, so not meter spikes) and the upsized run removes at
least 70% of it. Then it prints:

- data checks: SPF cross-check, missing levels, dead DHW flag feeds,
  guessed DHW, upsized systems
- the spread of each part across the fleet
- Spearman correlation of each part with quality (SPF / H_fixed, from
  `hstar_fleet_hist.csv`)
- what goes with the level: numeric metadata (Spearman) and categories
  such as heat meter, installer and metering flags (Kruskal–Wallis)

It writes `twins/replay_fleet.csv`, one row per system, or
`twins/replay_fleet_<model>.csv` for the other models. Everything below
uses v2 unless it says otherwise.

### Step 4. Pump-only scan: `scripts/feed_scan/feed_scan_7.php`

```
php scripts/feed_scan/feed_scan_7.php <id> [days] [--out=dir]
scripts/feed_scan/pumponly_fleet.sh "" 3
```

This scan looks at the times the compressor is off and the pump is
circulating (at least 10 min after a stop, flow at least 2 L/min, not
DHW). The water then passes the return sensor, goes out through the
primary pipes and the outdoor unit, and comes back to the flow sensor with
no heat added. So the flow-return dT should be close to zero, and what's
left tells us three things:

- **sensor offset**, the error that would carry into the heat measurement
  while running
- **loop heat loss**, which makes flow read cooler than return, more so
  the warmer the water is relative to outside
- **loop volume**: while the water is cooling, flow lags return by the
  time the water takes to go round, so flow reads warm by that delay times
  the cooling rate

The scan fits

    dT = offset + a × (water − outside) + b × (rate of water temperature change, K/min)

over all pump-only samples. The intercept is the offset, −a × flow ×
69.3 is the loop UA in W/K, and −b is the delay in minutes. As a
model-free check it also takes the mean dT of quiet samples: 20+ min
after a stop, with the water changing by less than 0.02 K/min.

It also reports k = heat / (flow × dT) during steady running. For water
and flow in L/min, k is 69.7. A k near that shows the temperature feeds
are the heat meter's own sensors, which the offset test needs. Outputs go
to `scripts/feed_scan/pumponly/`.

### Step 5. Checks on the level: `twins/replay_checks.py`

```
.venv/bin/python twins/replay_checks.py
```

This reads `replay_fleet.csv`, the replay JSONs and the pump-only JSONs
and prints four checks:

1. the measured sensor offset against the level, and against the offset
   that would be needed to explain each system's whole gap
2. the gap as extra watts by speed band, and the pump-only electricity
3. loop volume and UA from the pump-only fit, against the UA that would be
   needed to explain the gap
4. the example systems at both ends of the level

### Step 6. Compare the COP models: `twins/replay_models.py`

```
.venv/bin/python twins/replay_models.py
```

This reads the three `replay_fleet*.csv` files and compares the level,
min-speed penalty and quality fit by model, the level by size, whether the
models rank the systems the same way, the within-size links, and the
example systems.

### Step 7. Heat meter check from hot water reheats: `scripts/feed_scan/feed_scan_8.php`

```
php scripts/feed_scan/feed_scan_8.php <id> [days] [--out=dir] [--volume=L]
scripts/feed_scan/dhwcal_fleet.sh "" 3
```

This checks the heat meter against the cylinder, without the electricity
meter or the datasheet. It needs a cylinder temperature feed
(`heatpump_dhwT`, in °C) and a cylinder volume, from the system metadata
or `--volume`.

Reheats are runs of the DHW flag (or flowT above 45 °C without one). Early
in a reheat the cylinder sensor often reads flat while kW go in, because
the heated zone hasn't reached it yet. So comparing start and end
temperatures mostly measures where the sensor sits. Instead each reheat is
split into 10 min windows, counted back from the end. While the whole
cylinder heats up together, heat in ≈ volume × 1.163 Wh/(L·K) × rate of
rise, so each window gives

    ratio = mean heat / (volume × 1.163 × cylinder slope in K/h)

A window counts when the compressor runs throughout with steady heat and
no immersion, the cylinder rises at least 2 K/h with no step (a draw), and
the cylinder and return temperatures rise together (slopes within a third
of each other), as they do once the cylinder is mixed and the coil return
follows it. The scan also reports the last 10 min of each reheat, the
ratio by window position from the end, and the old start-to-peak ratio.

Standing losses and the heat taken by the coil, steel and pipes push the
ratio a little above 1; water below the coil that doesn't heat pushes it
below. So it's a calibration figure to compare between systems, or to
follow over time in one system, rather than one to hold to exactly 1.

## Part 2: results

### Data checks

- 132 systems scanned, 127 with a level and a clean SPF cross-check. Five
  are 0.04–0.10 below their published SPF (26, 140, 240, 363, 630) and
  are left out.
- 465 and 348 are the same system listed twice. 348 is dropped, leaving
  126.
- Five declared sizes look wrong and run one size up: 59, 160, 330 and
  584 (5 to 7 kW) and 81 (3.5 to 5 kW).
- The DHW flag feed is never set on 433, 516 and 610. 51 systems have no
  DHW flag, so DHW is guessed from flowT.
- System 9 logs flow in m³/h, so it's left out of the pump-only checks.

### The parts are similar everywhere except the level

| Part | Median | p10 to p90 |
|---|---|---|
| Level (field/datasheet, 30–70 rps) | 0.95 | 0.88 to 1.02 |
| Min-speed penalty | 0.87 | 0.76 to 0.91 |
| Share of steady running at min speed | 0.38 | 0.07 to 0.69 |
| Standby | 12 W | 8 to 21 W |
| Defrosts a day | 1.4 | 0.6 to 2.8 |
| SPF step: steady running vs datasheet | −0.27 | −0.65 to −0.02 |
| SPF step: defrost | −0.13 | −0.23 to −0.07 |
| SPF step: DHW | −0.07 | −0.19 to −0.02 |
| SPF step: start-ups | −0.06 | −0.14 to −0.02 |
| SPF step: standby | −0.04 | −0.12 to −0.01 |

Start-ups, defrosts, standby and DHW are small and similar from system to
system. Starts per day has nothing to do with quality (rho −0.09). So
operation isn't what separates good systems from poor ones. This also
says the simulated twin's operation is wrong: for system 75 the datasheet
replay gives SPF 4.95 against a real 4.43, but the simulated twin gave
3.78 while starting 33 times a day against the real 4.3.

### Level is the main part of quality

Level goes with quality at rho 0.80 and log level explains 58% of the
variance in log quality. The next strongest part is the share of time at
minimum speed (rho −0.42).

### What goes with the level

- **Size.** With v2, median level is 0.93 for 5 kW, 0.96 for 7 kW and
  1.01 for 10 kW. The 10 and 12 kW part of this is the fit: against the
  12 kW unit fit they come out at 0.95, level with the 7 kW systems (see
  the COP model section below).
- **Flow rate** goes with level (rho 0.34), but only through size. Flow
  per kW has nothing to do with level (rho 0.05), and within the 5 kW and
  7 kW groups flow doesn't either. The lead from the first 8 systems
  (rho 0.81) didn't survive.
- **Within a size, two things hold.** Higher running flow-return dT goes
  with a higher level (rho about +0.3), and more time at minimum speed
  goes with a lower level (rho −0.40 and −0.45). The second isn't the
  min-speed penalty leaking into the 30–40 rps band: systems that spend
  more time at minimum speed are below the datasheet at every speed up to
  90 rps.
- **Nothing in the metadata.** Heat meter make, installer, training,
  heatgeek, volumiser, UFH, radiators, control type, zones and the
  metering_inc_* flags all have medians within 0.02 of each other
  (Kruskal–Wallis p 0.12 or more).

### Is it the heat meter's temperature sensors?

At a running dT of 2.8 K, a 0.1 K mismatch between the meter's sensors is
a 4% heat error, so sensor offsets could in principle make up the level
spread. They don't.

k comes out at 69.3 for almost every system, so the temperature feeds are
the meter's own sensors. In 107 systems with 20+ quiet pump-only hours,
the quiet dT is a median of −0.01 K, with p10 to p90 of −0.06 to +0.04 K.
That's a heat error of −2% to +1%. To explain each system's whole gap the
offset would need to be a median of +0.30 K, about ten times bigger.
Correcting the level for the measured offset leaves its spread unchanged
(sd 0.059). The offset leans the expected way (quiet dT vs level rho
+0.27) but is worth a fraction of a percent. The size, dT and min-speed
links survive the correction.

The flow side of the heat meter isn't tested by this. A flow reading a
few percent off would scale the heat directly. The hot water check below
looks at it where a cylinder sensor allows.

### Is it pump power?

In steady running at 30–70 rps the median system draws 850 W and makes
3.8 kW of heat. At that input, 10 W moves the level by 1.2% and 100 W by
12%.

| | Extra W (measured − datasheet) |
|---|---|
| Fleet median | +41 W |
| IQR | +12 to +69 W |
| p10 to p90 | −18 to +103 W |
| Poorest five | +125 to +240 W |

Across the fleet the extra is fairly flat with speed, +37 to +50 W from
30 to 70 rps while input doubles, which looks like a fixed load. Within a
system it's mixed: the shortfall as a percentage is a bit more constant
across speeds than the watts are (sd/mean 0.30 against 0.40). At minimum
speed the extra is +97 W, so the min-speed penalty isn't a fixed load.

With the compressor off and the pump circulating, systems draw a median
of 23 W (17 to 37 W), pump and controls together. This has nothing to do
with the extra watts (rho −0.11). The dashboard for system 741 shows its
pump at about 11 W at 14 L/min and about 33 W at its running 20 L/min.
The other poor systems run at 12–14 L/min. The datasheet COP already
includes a pump share for the unit's own pressure drop.

So the pump could be part of the fleet's ordinary +40 W, but it can't be
the 150–240 W of the poorest systems.

### Is it heat lost on the primary pipes?

Heat lost between the outdoor unit and the meter would never be metered.
The poorest systems would need 600–1000 W of missing heat to reach the
datasheet: with the water about 26 K above outside, a loop UA of about
35–40 W/K. Those same pipes are in the loop during pump-only running,
where the water is about 13 K above outside. A 35–40 W/K loop would lose
about 500 W there and make flow read 0.5–1 K below return at 7–14 L/min.
The quiet dT on 371 is +0.02 K and on 741 −0.065 K. Even if all of 741's
reading were loss, that's about 5 W/K, or around 150 W while running,
against about 1000 W needed.

For the fleet as a whole the needed UA is a median of 7 W/K (200 W of
heat), which is at the edge of what the pump-only data can resolve. So
pipe losses could be part of the general ~5% gap, but not the poor tail.

The fit's loss term is too noisy to estimate UA per system: median
1.5 W/K, with p10 to p90 of −7 to +14 W/K (negative values are
impossible). In pump-only the water is only 10–16 K above outside, which
isn't enough range to separate loss from sensor offset and pump heat. The
delay term works better: a median of 0.9 min, giving a loop volume of
11 L (3 to 35 L), a plausible figure for primary pipes plus the unit's
own water. It's a usable per-system input for the simulator's pipework
model. Neither volume nor UA goes with the level (rho 0.02).

### Is it the heat meter's flow measurement?

A flow meter reading low would take the same share off every watt of
metered heat, and the replay can't tell that from a machine below spec.
The hot water check (step 7) can, but only 10 of the 132 systems have a
cylinder temperature feed, and none of the poorest five.

| System | Cylinder | Start-to-peak | Rate method: median (IQR), windows | Level |
|---|---|---|---|---|
| 643 | 250 L | 0.66 | 0.83 (0.72–0.97), 501 | 1.07 |
| 342 | 250 L | 1.74 | 1.03 (0.99–1.08), 829 | 0.98 |
| 284 | 300 L | 1.02 | 1.05 (0.99–1.13), 1538 | 0.96 |
| 696 | 237 L | 1.08 | 1.12 (1.06–1.19), 487 | 0.90 |
| 272 | 400 L | 1.84 | 1.14 (1.03–1.23), 420 | 0.89 |
| 690 | 110 L | 2.86 | 1.16 (1.04–2.96), 165 | 0.97 |
| 280 | 300 L | 0.81 | 1.19 (0.97–1.27), 28 | 0.97 |
| 315 | 300 L | 0.91 | 1.26 (1.17–1.39), 32 | 0.95 |
| 75 | 200 L | 2.06 | 1.59 (1.49–1.71), 271 | 0.96 |

363 (rate 0.87) fails the SPF cross-check and has no level.

The start-to-peak ratio runs from 0.66 to 2.86 and mostly says where the
sensor sits. The rate method pulls six of the eight systems with plenty of
windows into 0.83 to 1.16, and for most of them the ratio settles over the
last 10–20 min of a reheat. 75 still reads 1.59, which looks like a wrong
volume or a sensor in a slow zone rather than a meter 60% high, and 690's
110 L is doubtful too. 280 and 315 have too few windows to lean on.

A correct meter seems to read about 1.05–1.15 on this check. The two
lowest-level systems in the set, 272 and 696 (level 0.89–0.90, about
11–12% missing heat), read 1.12–1.14: at or above the others, not 11%
below. Across the eight, lower level goes with a higher ratio, the
opposite of a flow under-read (Spearman −0.55, p 0.16). So for these
systems the gap to the datasheet isn't the heat meter. With eight systems,
nominal volumes and none of the poorest five, that's a hint rather than a
result.

### What's left

On the temperature side the level spread is real, not a measurement
artefact. The pump and the primary pipes might add up to the fleet's
ordinary ~5% gap to the datasheet. Neither explains the poorest systems,
which run 150–240 W over the datasheet with ordinary pump-only power and
small pump-only dT.

For those, three explanations remain:

1. something else on the electricity meter while the compressor runs
2. the unit itself running about 20% below spec
3. the heat meter's flow reading 20–36% low, which the hot water check
   can't reach on these systems and doesn't support on the ones it can

A separate load would show as steps or a fixed block that doesn't follow
compressor speed; a unit fault would scale with output. Comparing measured
and datasheet electricity minute by minute over a few steady runs on 741
or 371 should separate them. The compressor's minimum speed gives a test
of the third that every system can take: at the floor of its electricity
use, a system with a flow under-read would show normal electricity and
heat well below the datasheet's minimum output.

Also open:

- why systems that spend more time at minimum speed are below the
  datasheet at every speed (a flow under-read would cause this too, since
  inferred speed comes from metered heat)

### Sensitivity to the COP model

The fleet was replayed with all three COP models. 126 systems have a
clean level in each.

| | Speed-based generic (v2) | Capacity-normalised generic | Capacity-specific unit fits |
|---|---|---|---|
| Median level (IQR) | 0.951 (0.913 to 0.984) | 0.929 (0.890 to 0.960) | 0.940 (0.900 to 0.972) |
| Level sd | 0.060 | 0.059 | 0.059 |
| Min-speed penalty | 0.87 | 0.87 | 0.84 |
| 70–120 rps vs level | 1.02 | 1.00 | 0.98 |
| Level vs quality, rho | +0.80 | +0.77 | +0.66 |
| Log level explains of log quality | 58% | 55% | 43% |

Median level by size:

| Size | n | v2 | Capacity-normalised | Unit fits |
|---|---|---|---|---|
| 5 kW | 50 | 0.930 | 0.910 | 0.923 |
| 7 kW | 58 | 0.957 | 0.936 | 0.953 |
| 10 kW | 9 | 1.007 | 0.997 | 0.950 |
| 12 kW | 7 | 0.990 | 0.955 | 0.942 |

What doesn't depend on the model:

- **The ranking.** Rank correlation of the levels is 0.99 between the two
  generic fits and 0.91 between v2 and the unit fits. The five lowest
  systems are the same five in every model.
- **The spread.** The level's sd is 0.059–0.060 in all three, so the ±7%
  spread isn't a product of the fit.
- **The within-size links.** More time at minimum speed goes with a lower
  level (rho −0.30 to −0.47) and higher running dT with a higher level
  (rho +0.27 to +0.36) in every model.

What does:

- **The overall level.** The capacity-normalised generic fit expects about
  2% more COP than v2 at every size and speed. The unit fits agree with
  v2 to within 0.01 for 5 and 7 kW systems. They expect better COP at low
  load, so the min-speed penalty looks a little larger (0.84).
- **The 10 and 12 kW systems.** The two generic fits pool both units'
  tables and under-predict the 12 kW unit by about 5%. Against the 12 kW
  unit fit, the 10 and 12 kW systems sit at 0.95, the same as the 7 kW
  systems, and the correlation of capacity with level drops from 0.42 to
  0.29. A smaller 5 vs 7 kW gap remains (0.92 vs 0.95), but the 7 kW
  figure uses the scaled 5 kW fit, so it isn't a clean comparison.
- **How much of quality the level explains.** Quality is SPF over a fixed
  reference, not the unit's own datasheet, and a bigger Arotherm+ has a
  better datasheet. The generic fits fold that into the level; the unit
  fits take it out, so the level explains less of quality (43%).

### Example systems

All figures are steady space heating at 30–70 rps, comparing field and
datasheet at the same flow temperature, outside temperature and output.
Level is shown for each COP model. The datasheet COP and watts are from
v2.

The gap is given two ways. Extra electricity is measured electricity
minus heat / datasheet COP: the electricity used beyond what the
datasheet needs for the metered heat. Missing heat is measured
electricity × datasheet COP minus measured heat: the heat the datasheet
says that electricity should have made, minus what the meter saw. They
describe the same level (missing heat = extra electricity × datasheet
COP), but they point at different sides. A fault on the electricity side
(a pump, controls, another load on the meter) would add a roughly fixed
number of electrical watts. A fault on the heat side (losses before the
meter, a flow meter under-reading) would take away heat watts or a share
of the heat.

**Poorest**

| System | Size | Level v2 / generic / unit | Field COP vs datasheet | Extra electricity | Missing heat | SPF | Notes |
|---|---|---|---|---|---|---|---|
| 371 | 5 kW | 0.73 / 0.71 / 0.72 | 3.56 vs 4.85 | +204 W | 990 W (36%) | 3.46 | Worst. Low flowT (29 °C) should favour it |
| 741 | 7 kW | 0.81 / 0.80 / 0.82 | 3.40 vs 4.22 | +239 W | 1010 W (24%) | 3.80 | Biggest absolute gap. Pump about 33 W |
| 269 | 5 kW | 0.78 / 0.75 / 0.76 | 3.83 vs 4.94 | +164 W | 810 W (29%) | 4.02 | No DHW flag, DHW guessed |
| 516 | 5 kW | 0.80 / 0.78 / 0.79 | 3.78 vs 4.73 | +153 W | 725 W (25%) | 3.99 | DHW flag feed never set |
| 238 | 5 kW | 0.83 / 0.82 / 0.83 | 3.91 vs 4.70 | +125 W | 585 W (20%) | 4.08 | No DHW flag, DHW guessed |

Missing heat is shown in watts and as a share of the metered heat.

All five spend 55–72% of steady running at minimum speed (fleet median
38%) and start 6–8 times a day. Three of them still have an SPF around
4.0.

**Better than the datasheet**

| System | Size | Level v2 / generic / unit | Field COP vs datasheet | Electricity vs datasheet | Heat vs datasheet | SPF | Notes |
|---|---|---|---|---|---|---|---|
| 260 | 7 kW | 1.04 / 1.02 / 1.03 | 4.94 vs 4.74 | −37 W | +176 W (4%) | 4.29 | |
| 654 | 7 kW | 1.03 / 1.01 / 1.03 | 4.75 vs 4.62 | −26 W | +118 W (3%) | 4.33 | Most data in the fleet (3350 h) |
| 409 | 7 kW | 1.04 / 1.03 / 1.05 | 4.14 vs 3.99 | −40 W | +161 W (4%) | 3.27 | Good machine, poor system: flowT 40 °C |
| 211 | 7 kW | 1.03 / 1.01 / 1.05 | 3.61 vs 3.52 | −25 W | +87 W (3%) | 3.22 | The same: flowT 40 °C |
| 335 | 5 kW | 1.01 / 0.99 / 1.02 | 4.22 vs 4.19 | −5 W | +21 W (1%) | 4.27 | The best 5 kW system |

Heat vs datasheet is the metered heat minus what the datasheet says the
measured electricity should make.

These are above 1.0 in every model, or close to it under the
capacity-normalised fit. Apart from 211 (48%) and 335 (46%) they spend
little time at minimum speed (10–16%).

Some systems look good only under the generic fits. The 10 kW systems 752
and 643 score 1.06–1.07 with v2 but 1.00 against the 12 kW unit fit, so
they're average for their size. 59, 160 and 584 score 1.03–1.09, but
only after being re-run as 7 kW: they're examples of a wrong declared
size rather than over-performance.
