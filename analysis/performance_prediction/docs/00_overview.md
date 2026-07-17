# Heat pump performance prediction — project overview

---

*Note from Trystan: This performance prediction project was largely generated using Claude code (Model: Fable - effort high).
Appart from this brief introduction the document text is all written by claude as will be clear!*

*This analysis attempts to progress my earlier manual analysis of the HeatpumpMonitor.org dataset as documented here: [https://docs.openenergymonitor.org/heatpumpmonitor/index.html](https://docs.openenergymonitor.org/heatpumpmonitor/index.html). I think it has uncovered a number of useful ideas!*

*The analysis also develops a harness for the dynamic heat pump simulator tool that I have been working on here: [https://openenergymonitor.org/tools/dynamic_heatpump](https://openenergymonitor.org/tools/dynamic_heatpump) and uses this to test and refine ideas that could then be applied to the real world data set. More work needs to be done to verify if these ideas work when applied to real data!*

*The following is work in progress and I welcome suggestions for improvement!*

---


**Goal:** understand and improve the prediction of seasonal heat pump
performance (SPF H4) from system characteristics, starting from the known
relationship with weighted flow − outside temperature, and ideally approach
±0.1 SPF predictive accuracy.

**Data:** HeatpumpMonitor.org all-fields export (2026-07-15), filtered to 225
air-source, MID-metered, boundary-4 systems with ≥330 days of data and no
cooling. Plus: the per-system power-histogram API, and the dynamic_heatpump
physics simulator.

## The narrative arc

**1. Baseline replication** (doc 01). The published relationship
SPF = −0.105·ΔT + 6.88 (R² 0.55) replicates on current data almost exactly:
−0.110·ΔT + 7.04, R² 0.60, 90% PI ±0.51. The question became: *what is the
±0.51 made of, and how far can it be reduced?*

**2. Residual analysis on published aggregates** (doc 02). Three hypotheses
tested — standby drag (real: median 0.10 COP penalty, exactly decomposable),
modulation pattern (invisible in annual means), pipework losses (no field;
signature absorbed into the dominant correlate, annual heat demand, ρ = 0.36).
Honest cross-validated ceiling using everything in the table: cv R² 0.64,
PI ±0.45. Key lessons: leakage rules (heat-side × elec-side features fake
R² 0.85), and the arithmetic that PI ∝ √(1−R²) — so ±0.1 needs R² ≈ 0.98,
i.e. fundamentally better *features*, not better models.

**3. Distributional features from the histogram API** (doc 03). The share of
heat delivered below 40% of rated output is the strongest single residual
correlate found (ρ = −0.33, survives controlling for demand): systems living
at low load factor underperform — an *oversizing-in-operation* effect, and
actionable design guidance. Lifts the ceiling slightly (cv R² 0.65, ±0.46).
Null result: elec-power distribution features normalised to max show nothing —
1-D annual histograms marginalise over weather, hiding the
modulation-at-fixed-temperature effect.

**4. Physics simulator Monte Carlo** (doc 04). Approach inversion: a Node
harness (`sim_harness/`) drives the unmodified dynamic_heatpump tool through
400 randomised system designs with an *identical* 47%-of-Carnot machine.
Result: the physics-only fleet shows ±0.32 around its own SPF ~ ΔT line —
**two-thirds of the real spread, with zero machine variation, install
variation, or metering error**. The sim's residual correlates r = 0.965 with
achieved % carnot, whose drivers mirror the real fleet (demand, DHW share).
Conclusion: the ΔT metric itself is the bottleneck.

**5. A better metric: H\*** (doc 05). Searching metric space with the
simulator as ground truth: the heat-weighted *harmonic* mean of an ideal
Carnot COP with **load-dependent** condenser/evaporator offsets
(+3·r / −8·r K, r = heat/capacity). In the sim testbed it takes R² from 0.63
to 0.90 and halves the PI (±0.32 → ±0.16); fixed-offset harmonic averaging
alone gains almost nothing (confirmed on real data: R² 0.598 via
`combined_cop / combined_prc_carnot`). H\* is electricity-free, one-pass
computable from raw feeds (flowT, outsideT, heat power + rated capacity), and
`SPF / H*` was intended as a modulation-corrected % of Carnot — a fairer
quality score. Side result on the sim testbed: the current fixed-offset
`prc_carnot` reads ~3 points high and flatters low-load-factor (oversized)
systems by up to ~5 points for identical machines, masking the genuine
oversizing penalty in the real fleet. **Outcome: tested against raw fleet
feeds and not adopted — see item 9 / doc 09.**

**6. Metering floor, revisited** (doc 06). The EN 1434 class-2 worst case at
the fleet's operating points is ±0.29 SPF at SPF 4 — but BEIS lab results,
OEM bench tests and an in-situ two-meter comparison agree real, correctly
installed meters sit far inside it: realistic floor **≈ ±0.10** (90%).
Consequences: the ±0.1 ambition is not blocked by metering (it sits *at* the
floor), and metering noise explains only ~4% of the observed residual
variance. Caveats: glycol (unrecorded, worth a metadata field), sub-3K ΔT
operation (23 systems), installation-fault tails.

**7. ML roadmap** (doc 07). Where machine learning genuinely helps given
n = 225 and the findings above: residual learning on top of physics; the
daily-records reframing (~80k system-days, pooled daily-COP model with
per-system embeddings, grouped CV); the simulator as teacher (sim-to-real
pre-training, per-home calibration). Phased plan with success criterion:
90% PI on held-out systems' annual SPF, benchmark ±0.50/±0.45, strong result
≈ ±0.25, floor ≈ ±0.10.

**8. Design-parameter model** (doc 08). Answering the practical question: can
SPF be predicted *before installation* from standard design parameters (heat
loss, design flow temp, DHW share)? A closed-form "H\* at design time" — WC
curve from the radiator equation, standard weather year, harmonic Carnot sum —
needs no simulation and, on the sim testbed with truthful inputs, achieves
**±0.21** (beats a year of measured ΔT, close to measured H\*'s ±0.16). On the
real fleet it collapses (±0.74): declared design flow temp correlates only
r = 0.33 with operated flow temp (median 45 → 34.8 °C, sd 5.3 K) and declared
heat loss runs ~24% high. Injecting exactly those input errors into the sim
reproduces the collapse — **input fidelity, not model sophistication, is the
bottleneck**; the leverage is recording as-commissioned WC settings.

**9. H\* on the real fleet** (doc 09). The raw-feed test closed the
metric-search line: on 229 validation-clean air-source systems
(`scripts/feed_scan/`), H\* predicts SPF *worse* than weighted ΔT (cv R²
0.48 vs 0.60, PI ±0.58 vs ±0.51), and a 1092-combination offset grid search
over the entire fixed + load-dependent family finds the optimal load
coefficient is exactly zero — no coefficients rescue it, under either badge
or empirically-normalised capacity. The within-machine load penalty is real
(manufacturer compressor maps confirm it), but in the fleet it is almost
exactly cancelled by an opposing **while-running** effect: achieved quality
rises with load factor (better-sized systems run compressors nearer their
design point), so the net load signal in annual SPF is ≈ 0. The simulator
only saw the gain because its machines were identical by construction.
`SPF/H*` is not a tighter quality score either (sd 0.041 vs 0.037), though
it *exposes* the sizing/load effect the fixed convention hides (corr with
load factor +0.48 vs +0.07) — a useful diagnostic axis, not a fairer
ranking. Weighted ΔT stands as the best feeds-only annual predictor.

## Where we stand

| Predictor | 90% PI (held-out) | Status |
|---|---|---|
| ΔT (current published metric) | ±0.50 | replicated |
| + all safe published aggregates | ±0.45 | ceiling reached |
| + histogram low-load share | ±0.46 (R² 0.65) | done |
| physics-only spread (sim, identical machine) | ±0.32 | measured |
| H\* metric (sim testbed) | ±0.16 | sim-only — did not transfer to fleet |
| H\* metric (real fleet raw feeds) | ±0.58 | **tested, not adopted (doc 09)** |
| H\*_design, truthful design params (sim testbed) | ±0.21 | done (doc 08) |
| H\*_design, real-fleet declared params | ±0.74 | input-limited (doc 08) |
| metering floor (realistic) | ±0.10 | established |

## Open next steps

1. ~~Validate H\* on real raw feeds~~ — **done, negative** (doc 09): H\*
   predicts SPF worse than ΔT and `SPF/H*` is not tighter than
   `combined_prc_carnot`, so nothing is added to the stats pipeline. The
   surviving thread is *understanding*, not fleet prediction: per-machine
   stable-episode analysis (COP vs load ratio at fixed temperatures,
   compared against manufacturer compressor maps) to separate real offset
   physics from the quality-rises-with-load-factor gradient, feeding a
   two-stage model SPF ≈ η(model, sizing) × H.
2. ML Phase 1: pooled daily-COP model (doc 07).
3. Re-run the simulator sweep with % carnot also varied to close the
   spread decomposition; match sampled design ranges to the fleet.
4. Metadata additions suggested by the analysis: glycol concentration,
   primary pipework length/volume, **as-commissioned WC curve endpoints**
   (doc 08's missing design-time predictor).
5. Consider publishing standby-corrected SPF (exact decomposition, doc 02).

## Repository layout

```
docs/                   this documentation (+ figures/)
analysis/               Python analysis scripts (run from project root,
                        auto-pick newest CSV; venv at .venv/); design_model.py
                        is the doc-08 closed-form design-parameter model
sim_harness/            Node harness around the simulator (engine.js,
                        run_scenarios.js)
design_spf_tool/        single-page Vue tool implementing the doc-08
                        design-parameter model (open index.html)
dynamic_heatpump/       the simulator tool source (unmodified)
histograms/             cached per-system power histograms (JSON)
heatpumpmonitor_*.csv   fleet dataset export
sim_results.csv         Monte Carlo sweep output (with H* metrics)
modulation_features.csv histogram-derived features + residuals
hstar_fleet.csv         raw-feed fleet scan results (doc 09; scanners live
                        in repo-root scripts/feed_scan/)
```
