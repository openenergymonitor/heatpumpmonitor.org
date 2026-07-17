# 10 — Field-measuring the compressor map from stable episodes

*Started 2026-07-17. Status: planned, not yet run.*

## Context

Definitions:

- **r(t)** — instantaneous load ratio, heat output / rated capacity,
  moment by moment.
- **r̄** — a system's heat-weighted annual mean of r(t). Fleet median
  0.66 (sd 0.15).
- **Utilisation** (capacity factor, annual heat / capacity·hours) not used
  in this analysis (corr with r̄ only +0.56 see doc 11). A system that
  rarely runs but flat-out when it does has low utilisation and *high* r̄.

The doc 09 question, in plain terms. Every system gets two ideal-COP
benchmarks from the same feeds: **H_fixed** prices only the temperatures
it ran at; **H\*** also charges for how hard the compressor was working at
each moment (the load model). Their ratio **P = H\*/H_fixed** is the net
charge the load model applies to that system's year: P > 1 for a system
that delivered its heat gently, P < 1 for one that ran hard. (The ratio
is taken so the temperature signal, common to both benchmarks, cancels —
P isolates the load charge, which is the part on trial; SPF regressed on
H\* directly would be swamped by the shared temperature signal.) Across
the fleet P varies by about ±5% (sd 0.047).

If the load model is right and every machine is equally good — each
achieving the same fraction q ≈ 0.45 of its true ideal, SPF = q·H\* —
then gently-run systems (high P) must visibly *over-perform* the
temperature-only benchmark and hard-run systems *under-perform* it:
SPF/H_fixed = q·P, an upward line of slope ≈ 0.45 when plotted against P,
worth ~±0.2 SPF between typical systems.

Observed: flat (slope +0.05, corr +0.06). The over/under-performance the
load model demands is not there. Two readings, indistinguishable from
annual aggregates:

- **R1 — the penalty is real and cancelled.** Steady-state part-load
  physics holds in the field, and machine/install quality anticorrelates
  with P almost exactly (~0.2 SPF per sd of P) — low-modulation
  inefficiency, install-quality-vs-sizing, or similar. (Standby, DHW share
  and water-dT effects are already ruled out, doc 09.)
- **R2 — there is nothing to cancel.** The offset model overstates how the
  steady-state penalty annualises over real operating distributions, so
  the modelled P does not describe real machines; flat is just flat.

Annual data cannot separate ~0.2 − 0.2 from ~0 − 0. The simulator can't
arbitrate (it *is* the offset model), and a datasheet alone can't either
(steady state, one test condition). What's needed is the machine's real
part-load curve measured in the field, then annualised over each system's
actual operating distribution. That is this experiment.

Ground truth to test against: the Vaillant Arotherm 5 kW datasheet gives
COP and output per compressor speed per outside temperature, at 35 °C
flow / 30 °C return. And the fleet is unusually rich in exactly this
hardware: **150 Arotherm+ systems** in the clean scan (58 × 5 kW,
64 × 7 kW, 12 × 10 kW, 9 × 12 kW, 6 × 3.5 kW).

## Reference data (Arotherm 5 kW, 35/30 flow/return)

COP (output kW) per compressor speed:

| outside °C | 120 rps | 90 rps | 70 rps | 50 rps | 40 rps | 30 rps |
|---|---|---|---|---|---|---|
| −7 | 2.7 (6.2) | 3.0 (4.5) | 2.9 (3.3) | 3.1 (2.2) | 3.4 (1.7) | — |
| +2 | 3.1 (7.3) | 3.4 (5.5) | 3.5 (4.2) | 3.7 (3.0) | 4.0 (2.4) | 3.9 (2.0) |
| +7 | 3.3 (8.0) | 3.7 (6.2) | 3.9 (4.9) | 4.3 (3.7) | 4.6 (2.9) | 4.1 (2.1) |

Three things to hold onto:

1. COP **rises** steadily as speed falls, 120 → 40 rps (+7 °C: 3.3 → 4.6).
   The steady-state part-load advantage is large, ≥ the +3r/−8r model.
2. It **turns over** at the bottom (30 rps: 4.1 < 4.6) — fixed losses bite
   at minimum modulation.
3. The "5 kW" badge spans 2.1–8.0 kW of actual output at +7 °C. Below
   2.1 kW the machine cannot modulate — it must cycle.

## The idea

A **stable episode** (feed_scan_2: ≥10 min, compressor on, flowT steady) is
the field realisation of the datasheet's steady-state test condition. So:

1. Extract stable episodes for all Arotherm systems, recording per-episode
   mean COP, heat output, flowT, returnT, outsideT, flow rate.
2. Plot episode COP against output, grouped by (outsideT, flowT) bins —
   this should **reproduce the datasheet table from field data**, per
   machine and per badge group.
3. Separately compute each system's *episode-implied* annual performance:
   weight its own episode curve by its actual annual (temperature, load)
   histogram (feed_scan_5 already produced these).
4. The **gap** between episode-implied SPF and actual annual running SPF is
   then a direct per-machine measurement of everything the steady-state
   curve misses: cycling, transients, defrost overhead, DHW dynamics.

Step 2 tests H1. Step 4 measures the cancellation directly instead of
inferring it.

## Predictions

- **Field curves match the map** (COP rising to ~40 rps equivalent
  output) → R1: the steady-state penalty is real in the field, so the flat
  annual result *requires* an offsetting effect, and it must live in the
  non-episode states. The step-4 gap should then grow as r̄ falls, by
  roughly the map's part-load advantage annualised (~0.2 SPF per sd of P).
  Between-system spread at fixed operating point, same hardware, is then
  the install-quality term, cleanly isolated.
- **Field curves flatter than the map** → R2: the part-load benefit does
  not survive real installation conditions even in steady state (candidate
  mechanism: part-load water flow rates and dT differ from the fixed
  35/30 test condition), the annualised penalty is small, nothing needed
  cancelling — and the failure of H\* across systems is explained
  directly.
- Watch for: low-load systems may simply have **few or no** steady
  low-output episodes (they cycle instead of modulating). That absence is
  itself informative — and it means their annualised penalty cannot be
  map-like regardless.

## Practical notes

- Machines don't report rps in our feeds — compare on **output kW**, which
  the map provides per speed. No capacity normalisation needed within a
  badge group; that also sidesteps the badge-engineering problem entirely.
- Weather compensation means few episodes will sit exactly at 35 °C flow at
  mild outside temperatures. Options: widen bins and interpolate; or work
  in η = COP / carnot(flowT, outsideT) vs output, which uses every episode
  and turns the map rows into a single reference curve.
- feed_scan_2's thresholds (flowT sd < 0.01 K over 10 min, slope
  < 0.5 K/h, elec > 150 W) were tuned on one system; expect to relax sd for
  fleet-wide yield. Add flow rate and returnT stability checks; require
  outsideT drift small over the episode.
- Exclude DHW episodes by flowT (> ~45 °C) or dhw flag where configured;
  keep them separately — the same comparison at DHW temperatures is a free
  second experiment.
- Defrost recovery is excluded by construction (flowT not stable).
- 58 systems × one winter of the same 5 kW hardware should give thousands
  of episodes per (temperature, output) cell — enough to see the 30 rps
  turnover if it exists in the field.

## Plan

1. Extend feed_scan_2 into a fleet episode extractor (feed_scan_6):
   all-Arotherm subset, per-episode CSV, same fill/coincidence semantics as
   feed_scan_4/5.
2. Per badge group: episode COP vs output at (outsideT, flowT) bins,
   overlaid on the datasheet rows above.
3. Per system: episode-curve × annual-histogram → episode-implied SPF;
   compare with measured running SPF; regress the gap on load factor.
4. Write up as doc 12: does the field reproduce the map, and where does
   the cancelled ~0.2 SPF actually go?

---
*Refs: doc 09 (cancellation result, elimination tests), doc 05 (H\* and
offsets), doc 03 (oversizing penalty), `scripts/feed_scan/feed_scan_2.php`
(episode detector), `hstar_hist.csv` (annual histograms), Arotherm 5 kW
datasheet table (screenshot, 2026-07-17).*
