# Per-system insights on the system page: plan

Working plan, November 2026. The nightly design parameter measurements
(heat loss, peak heat, design outside temperature, flow temperatures) are
built; the rest is not yet.

## Aim

Use each system's monitoring data to work backwards to the design
parameters it actually has: heat loss, design temperature, emitter
capacity, the flow temperature it needs. Then compare those with what the
installer, designer or owner entered on the system form.

Alongside that, show the primary pipework, metering checks and where SPF
is lost, using methods from the machine replay
([machine_replay.md](machine_replay.md)), the comparison tools
(`analysis/comparison`), the heat loss tool and the emitter module.

Every figure should come with its uncertainty. Most of them are built from
many samples (days, steady-state episodes, DHW switch-overs), so each
system builds up a distribution over time. The page can show that
distribution rather than a single number.

## Measured vs form

The core of the page is this comparison. Form fields are in
`system_schema.php`.

| Design parameter | Form field | Measured from |
|---|---|---|
| Heat loss at design temperature | `heat_loss` (kW at DOT) | Daily heat vs inside–outside ΔT fit |
| Heat transfer coefficient | none (implied: `heat_loss` / (room − `design_temp`)) | Slope of the same fit |
| Heat loss per floor area | `heat_loss` / `floor_area` | Measured heat loss / `floor_area` |
| Outside design temperature | `design_temp` | Coldest days experienced, distribution of daily outside temperature |
| Design flow temperature | `flow_temp` | Space heating flow temperature on the coldest days; cross-checked from emitter capacity |
| Emitter capacity | none (implied by `heat_loss` at `flow_temp`) | Radiator equation inverted per steady-state episode |
| Heat pump size vs load | `hp_output` vs `heat_loss` | Measured design heat loss vs rated and tested max output |

The form has no emitter capacity field. We can still compare: the form's
heat loss and design flow temperature imply an emitter capacity, and we
can set that against the measured one.

## Measurements

Readiness:

- **Ready**: method exists per system and has been used on real data
- **Needs work**: method exists but needs porting, automating, an
  uncertainty figure or validation
- **Not yet**: too noisy per system, or the interpretation isn't settled

### Design parameters (phase 1)

| Measurement | Shown as | Sample | Method | Current code | Systems | Readiness |
|---|---|---|---|---|---|---|
| HTC (effective) | W/K | Day | Slope of daily space heat vs (room − outside) ΔT, robust fit | `scripts/measurements`, dashboard heat demand tool | Outside temp; room temp or assumed 20 °C | Ready (needs CI, not just PI) |
| Balance point / base ΔT | K | Day | Intercept of the same fit. Absorbs internal and solar gains | `scripts/measurements` | As above | Ready |
| Heat loss at design temperature | kW, at the form's `design_temp` and at the measured one | Day | HTC × design ΔT + intercept | `scripts/measurements` → `measurement` table | As above | Ready (nightly) |
| Heat loss per m² | W/m² | Day | Above / `floor_area` | none | With floor area | Ready |
| Coldest day experienced | °C, date | Day | Lowest daily weighted outside temp in the last year | `scripts/measurements` (`coldest_day`) | Outside temp | Ready |
| Outside temperature distribution | Curve of daily means with `design_temp` marked | Day | All days of data, coldest 1% / 2.5% | none | Outside temp | Needs work |
| Emitter capacity | kW at ΔT50 (radiators); kW at a stated MWT–room ΔT for all types | Episode | heat = rated × (ΔT / 50)^n per episode, kWh-weighted, KDE with peak detection | `emitter.js`, `signature_episodes` | Room temp feed, heat, return temp | Needs work (UFH and mixed systems, no-room-temp fallback) |
| Implied emitter capacity from form | kW at ΔT50 | none | Form `heat_loss` at form `flow_temp`, assumed design dT and room | none | With form data | Ready (simple calculation) |
| Flow temperature needed at design: physical | °C | Episode | Measured design heat loss + measured emitter capacity + running dT → MWT → flowT | none | Room temp feed | Needs work |
| Flow temperature on the coldest days | °C, highest day, range and max 4 h | Day | Highest daily space heating flow temperature (weighted by heat output) of the 3 coldest days of the last year (by outside temperature weighted the same way; both match the dashboard's space heating heat-weighted means), lowest to highest day, and the highest 4 h on those days. Replaces a flow temp vs ΔT fit extrapolated to design: with no DHW flag, reheats skew the mild days that set the slope, while on cold days they are a small share of the heat | `scripts/measurements` (`flowT_coldest_days`, `flowT_peak_4h`) | Outside temp | Ready |
| Heat pump size vs load | Ratio | none | Measured design heat loss / rated output, and / max output from capacity tests; rated output / heat on the coldest days | Dashboard System card (coldest days, peak 4 h) and heat demand tool (max tests) | All | Needs work |

The physical route, worked out at the coldest days' outside temperature,
and the measured coldest days flow temperature are an internal check. If
they disagree by more than their uncertainty, one of the inputs is off: room
temperature, emitter exponent, or a heating regime change partway through
the data.

### Primary pipework (phase 1)

| Measurement | Shown as | Sample | Method | Current code | Systems | Readiness |
|---|---|---|---|---|---|---|
| Primary loop delay | s | DHW switch-over event; running segment | Return-to-flow transport delay: half-way points at DHW switch-overs, or cross-correlation per segment | `comparison/fast.py loop_volume` (Python), [manual_loop_lag.md](../../comparison/docs/manual_loop_lag.md) | Flow rate feed | Needs work (port to PHP, event detection) |
| Primary loop volume | L | Event | Delay × flow rate; cross-check with spike energy / (cp × ΔT) | As above | Flow rate feed | Needs work |
| Primaries length each way | m | Event | (volume − unit water content) / L per m / 2, with a pipe size selector on the page (22, 28, 35 mm copper, or custom L/m) | Manual only | Flow rate feed | Needs work (needs unit water content per model) |
| Heating circuit volume | L | Running segment | Flow-to-return delay × flow rate; cross-check with the dashboard's MWT-rise method | `fast.py`, `myheatpump_powergraph.js` | Flow rate feed | Not yet (not validated) |

Pipe length is computed in the browser from the stored volume
distribution, so changing the pipe size needs no recompute. Unit water
content needs a new field on the heat pump model table (about 2 L for a
5 kW Arotherm+). Hydraulic separation matters: a buffer or low loss header
between the sensors changes what the volume means, so the card should say
so when `hydraulic_separation` is set.

Known checks: 748 (meter about 0.5 m from the unit, 4.1 L), 2 (meter moved
2.1 m closer between 2025 and 2026, 1.34 L expected, 1.0 L by timing and
1.9 L by spike energy),
384 (21 L by both methods).

### Metering and data checks (phase 1, shown as confidence flags)

These matter because the design figures depend on them. A dead DHW flag
puts hot water heat into the space heating fit, for example.

| Measurement | Shown as | Method | Current code | Readiness |
|---|---|---|---|---|
| DHW flag status | OK / missing / never set / misses reheats | Flag present and ever set; fast flowT rises followed by DHW with no flag | `replay.php`, `lowmod.php` | Ready (the "misses reheats" test needs work) |
| Heat meter sensor offset | K, fleet p10–p90 −0.06 to +0.04 K | Quiet pump-only flow–return dT | `pumponly.php` | Ready |
| Heat meter consistency (k) | Near 69.5 | heat / (flow × dT) in steady running | `pumponly.php` | Ready |
| Room temperature feed | Present / missing | Feed check | none | Ready |
| Other heat sources | Warning | Immersion, boiler, form fields for stoves etc. | `system_stats` | Needs work |
| Standby and pump-only power | W, fleet 8–21 W and 17–37 W | Off-state elec | `replay.php`, `pumponly.php` | Ready |

### Where SPF goes (phase 2)

| Measurement | Shown as | Method | Current code | Readiness |
|---|---|---|---|---|
| SPF cost of defrost, start-ups, ramps, DHW, standby | SPF steps with fleet ranges | Replay ladder by operating state | `replay.php` | Needs work (non-Arotherm baseline: measure against the system's own steady COP) |
| Defrosts and starts per day | Count, with fleet range | Replay states | `replay.php` | Ready |
| Share of steady running at minimum output | % | Arotherm: inferred speed < 30 rps. Others: heat below a fraction of rated output | `replay.php` | Needs work |

### Comparison with peers (phase 3)

| Measurement | Method | Readiness |
|---|---|---|
| Peer level | Field COP vs same-size systems in the same conditions, adjusted for flowT | Not yet (see Wording) |
| Level vs datasheet, min-speed penalty, speed map | Machine replay | Not yet (Arotherm+ only, cause of the gap unknown) |

### Left out for now

| Measurement | Why |
|---|---|
| Primary loop heat loss (UA) | Too noisy per system: p10–p90 −7 to +14 W/K |
| Heat meter check from cylinder reheats | About 10 systems have a cylinder temperature feed, and results depend on sensor position |

## Uncertainty and distributions

Each measurement is built from samples: days for the heat loss and flow
temperature fits, steady-state episodes for emitter capacity, switch-over
events for loop volume. The plan is to keep those samples (or a fixed
histogram of them) per system, and show for each figure:

- the central value (median, or the fit at design)
- a statistical interval (e.g. 80%)
- a distribution curve, weighted where it makes sense (kWh for emitter
  episodes, as `emitter.js` does now)
- the number of samples, and how the interval has narrowed as data built
  up

Two kinds of uncertainty need keeping apart.

**Statistical**: how well the samples pin the value down. Points to watch:

- The heat loss tool currently reports an 80% *prediction* interval, the
  spread of individual days around the line. The uncertainty of the
  heat loss itself is the *confidence* interval of the fit at design ΔT,
  which is much narrower. Both are useful, but they answer different
  questions and should be labelled.
- Days and episodes aren't independent: weather runs in spells and the
  household routine repeats. A plain bootstrap or standard error will look
  too precise. A block bootstrap by week is probably the simplest fix.
- Design conditions are an extrapolation for most systems. A mild winter
  gives few days near design ΔT, and the interval at design should widen
  to show that.
- Emitter distributions can be multimodal (zones, rooms switched off). The
  peak detection in `emitter.js` handles this; the interval should be
  around the main peak, not across all of them.

**Systematic**: errors that more data won't reduce. These can be listed
with a rough size rather than folded into the interval:

- heat meter accuracy (see [metering_floor.md](metering_floor.md))
- outside temperature from a weather service rather than a sensor at the
  house
- room temperature: one sensor, or assumed 20 °C where there's no feed
- radiator exponent n (1.3 assumed; UFH nearer 1.1) and the rated ΔT
- heat from other sources (stoves, solar gain, immersion) reducing the
  measured heat pump load
- for pipework: unit water content and fittings, ±10 s timing on 10 s data
  (about ±11% on an 88 s delay for a single event)

One test of whether the intervals are honest: split each system's data in
two (alternate weeks, or two winters) and check the halves agree about as
often as the intervals say they should.

## Wording

Comparing measured with form values is the sensitive part. A form heat
loss twice the measured one could be a cautious design, an EPC-based
figure, a different room temperature, or a wrong field. The page should
show the comparison and the uncertainty without calling it a design
error.

- Show the design values at the same conditions (the form's design
  temperature and an assumed room temperature) so the comparison is like
  for like, and say what was assumed.
- Say where the measured value is an extrapolation.
- For the peer and datasheet comparisons, wait until the heat meter flow
  test on 371 and 269 is done. Until then, use peer level, phrase it as a
  comparison with similar systems, and consider showing it to owners and
  admins only.

## How it fits in the code

Existing pieces:

- The heat demand explorer (`views/heatloss.php`) has been folded into
  the MyHeatpump dashboard's heat demand tool; `/heatloss` redirects there.
  [system_measurements.php](../../../scripts/measurements/system_measurements.php)
  runs the same fit nightly (shared PHP port in `heatloss_fit.php`), plus
  peak heat, design outside temperature and space heating flow
  temperatures from a 10 s feed scan, into the `measurement` table. The
  dashboard and system list read from there; the hand-saved
  `measured_*` fields are no longer written.
- [emitter.js](../../../www/Modules/emitter/views/emitter.js) builds the
  emitter capacity distribution from `signature_episodes`, written by
  [steady_episodes_fleet.php](../../../scripts/feed_scan/steady_episodes_fleet.php).
- The coldest day fields in `system_meta` are filled nightly by
  `system_measurements.php` (it replaced `coldest_day.php`).
- [projected_spf.php](../../../scripts/projected_spf.php) and
  [update_design_estimates.php](../../../scripts/update_design_estimates.php)
  are the pattern for computed per-system fields shown as cards on
  [system_view.php](../../../www/Modules/system/system_view.php).

Proposed:

1. ~~Run the heat loss fit nightly for every system and store the
   results automatically, adding the confidence interval and the inputs
   used (room temperature assumed or measured, days used).~~ Done:
   `scripts/measurements/system_measurements.php`.
2. Move the KDE, weighted percentile and peak detection out of
   `emitter.js` into a shared JS library, so every distribution on the page
   is drawn the same way.
3. Add an emitter capacity summary per system (median, interval, main
   peak) from `signature_episodes`, computed server-side, so it can be
   compared and listed.
4. Port `loop_volume` and `pump_power` from `comparison/fast.py` to PHP,
   with DHW switch-over event detection, and store per-event samples.
5. Add `scripts/system_insights.php [systemid] [--dry-run]` for the
   phpfina scans (loop volume, sensor offset, DHW flag checks, replay
   ladder), writing one JSON result per system.
6. Storage: summary values in `system_meta` for the list page; samples or
   histograms in a JSON column or separate table for the distribution
   curves.
7. A "Design: measured vs form" card on `system_view.php`, plus primary
   pipework and data check cards.

Scans that read phpfina have to run on the HeatpumpMonitor.org server.
Results computed on a dev machine won't reach live system fields.

## Phases

### Phase 1: design parameters, pipework and data checks

- [x] Nightly heat loss and flow temperature fit with confidence and
      prediction intervals (`scripts/measurements/system_measurements.php`,
      `measurement` table). Flow temperature from space heating only, on the
      3 coldest days (highest day, range, and highest 4 h)
- [ ] Block bootstrap by week for those intervals
- [x] Outside temperature distribution and coldest days vs `design_temp`:
      99.6% / 99.0% design outside temperature from hourly data over complete
      years, coldest hour and coldest day
- [x] Heat output on the 3 coldest days (the flow temperature days): highest
      day (kWh / 24) and highest 4 h, with timestamps. Replaces the all-time
      peak day, often a boost or recovery on a mild day
- [x] Measured values on the MyHeatpump dashboard (System card, heat demand
      tool) and as system list columns
- [ ] Deploy: tables, nightly cron after the daily stats, one `--full` run,
      drop the retired `measured_*` columns from `system_meta`
- [ ] Shared distribution library from `emitter.js`
- [ ] Emitter capacity per system, with UFH and no-room-temp handling
- [ ] Implied emitter capacity from the form, and the physical flow
      temperature route
- [ ] Unit water content field on the heat pump model table
- [ ] Loop volume in PHP, per-event samples; check against 384, 748, 1088
      and 2
- [ ] Data checks: DHW flag, sensor offset, room temp feed, other heat
      sources
- [ ] Split-half test of the intervals across the fleet
- [ ] Cards on `system_view.php` (the dashboard System card shows them so far)

Done when: the measured vs form card shows on all systems with enough
data, the physical and coldest days flow temperatures agree within their intervals on
most systems with a room temperature feed, and loop volumes on the known
systems match the manual checks to within about 10%.

### Phase 2: where SPF goes

- [ ] Replay ladder against each system's own steady COP for non-Arotherm
      systems
- [ ] Minimum-output share for non-Arotherm systems
- [ ] Add to the page

### Phase 3: comparison with peers

Starts once the heat meter flow test on 371 and 269 has a result.

- [ ] Peer level, visibility and wording decided

## Open questions

- Public, or owner and admins only? Possibly different for each card.
- ~~Should the automatic fits replace the hand-saved `measured_*` fields,
  or sit beside them?~~ Replaced: the site reads the `measurement` table.
- What basis does the form's design temperature use (MCS / CIBSE design
  outside temperature for the location), and how do we compare it fairly
  with daily mean temperatures from one or two winters?
- What room temperature to assume where there's no feed: 20 °C, or the
  form's design room temperature if we add one?
- Emitter exponent for mixed radiator and UFH systems.
- Unit water content: from datasheets per model, or asked on the form?
- How much data before each figure is shown? Probably a minimum sample
  count and a maximum interval width, as projected SPF does.
