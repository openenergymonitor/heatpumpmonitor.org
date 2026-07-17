# Machine learning approaches to heat pump performance prediction

An outline and plan of action for exploring ML on the HeatpumpMonitor.org
dataset, informed by the analysis work of July 2026 (baseline replication,
residual analysis, power-histogram features, and the dynamic_heatpump simulator
sweep — see `05_harmonic_carnot_metric.md` for the physics-metric route this
complements; its raw-feed fleet test came back negative
(`09_hstar_fleet_results.md`), leaving weighted dT as the physics baseline).

## Context: what we already know

Any ML effort here starts from three established facts:

1. **Baseline ML on the published aggregates is already done, and it plateaus.**
   Cross-validated linear regression and gradient boosting over all
   leakage-safe fields in `heatpumpmonitor_all_fields` reach cv R² ≈ 0.65 and a
   90% prediction interval of ±0.45 SPF (vs ±0.50 for the weighted
   flowT − outsideT baseline alone). Gradient boosting *underperforms* linear
   regression (cv R² 0.55) — with n = 225 systems and ~20 features, extra model
   capacity only overfits. Fancier algorithms on the same table will not
   change this: the features, not the model class, are the constraint.

2. **The target has a noise floor.** For correctly installed systems the
   realistic metering floor is ~±0.10 SPF at 90% (SDM120 + Axioma-class heat
   meters; the EN 1434 certification worst case is ±0.29 but real meters sit
   far inside it — see `06_metering_floor.md`). No model evaluated against
   *measured* SPF can honestly validate below ~±0.10, and a model that is
   itself good to ±0.10 would show ~±0.14 combined.

3. **Most of the spread is physics, not machine quality.** The simulator sweep
   showed ~⅔ of the fleet's ±0.5 spread is reproduced with an *identical*
   machine — it is operating-pattern information (load ratio, DHW share,
   runtime, temperatures over time) that annual aggregates destroy. Any ML
   approach that only sees annual aggregates is denied exactly the information
   that matters. (Doc 09 revised this downward for the real fleet: the
   load-pattern component nets out to ≈ 0 in annual SPF because machine
   quality rises with load factor, so the residual beyond temperatures is
   more machine/install quality than the simulator suggested — information
   ML has to get from metadata or daily dynamics, not from load-weighted
   temperature metrics.)

Leakage rules learned the hard way, which apply to every experiment below:

- Never give a model both heat-side and electricity-side power/energy
  quantities — their ratio *is* the COP (a first attempt scored cv R² 0.85
  this way; it was fake).
- `prc_carnot` and `water_cop`/`running_cop` etc. are restatements of the
  target, not predictors.
- Evaluate with **grouped cross-validation by system** — a model that has seen
  any of a home's data memorises the home.

## Three directions worth pursuing

### Direction 1 — Residual learning on top of physics (cheapest, immediate)

Don't ask ML to rediscover thermodynamics from 225 examples. Give it the
physics prediction — the dT fit (η·H\* was tested on raw feeds and predicts
worse, doc 09; `η·H*_design` remains a candidate where design data exists,
doc 08) — and train a small
regularised model **only on the residual**, i.e. the part physics doesn't
explain. The model then only needs to find corrections (manufacturer effects,
oversizing penalties, standby drag, climate/defrost effects), not the main
relationship. This is the standard physics-informed pattern and the right fit
for small n.

- Model: ridge/lasso or shallow GBM; SHAP values for interpretation.
- Features: the leakage-safe aggregate set + histogram-derived features
  (low-load share etc.) + metadata (make, refrigerant, emitter type…).
- Expected gain: modest (the residual work by hand found heat demand,
  low-load share, standby; this systematises it) — but nearly free, and the
  per-system SHAP breakdown is useful output in itself ("this system is 0.3
  below prediction mainly because of X").

### Direction 2 — Change the unit of data: daily records, not annual systems

**This is where the real headroom is.** The fleet is not 225 data points; it
is ~80,000 system-days. Using daily aggregates per system (daily COP, daily
heat, daily mean flowT / outsideT / return, runtime, starts, DHW share):

1. Pool daily records across all systems and train one model:
   `daily_COP = f(daily conditions, system features)`.
   At n ≈ 80k, gradient boosting and neural networks have room to work, and
   the model learns the *shape* of performance across conditions — cold
   snaps, part-load days, DHW-heavy days — precisely the distributional
   information annual aggregates destroy.
2. Add a **learned per-system embedding** (a small fitted vector per home, or
   a mixed-effects random intercept as the simple version) capturing
   "everything persistent about this system". Annual SPF prediction falls out
   by summing predicted daily electricity over the year.
3. The embeddings are a product in their own right: clustering them should
   rediscover make/model groups and sizing classes without being told, and
   homes whose embeddings sit far from their make/model cluster are exactly
   the "something else is going on" systems (pipework? metering? controls?).

Evaluation: hold out entire systems (grouped K-fold). Report both daily-COP
error and rolled-up annual SPF error on unseen homes.

### Direction 3 — Use the simulator as a teacher

The dynamic_heatpump simulator demonstrably reproduces the fleet's structure
(slope, spread, residual correlates). Two established patterns exploit this:

- **Sim-to-real pre-training**: generate tens of thousands of simulated homes
  (now varying % carnot, standby, weather too), pre-train the daily-COP model
  on simulation, fine-tune on real data. Helps most where real data is thin
  (extreme designs, very cold weather, unusual sizing).
- **Per-home calibration / digital twin**: for each real system, fit the
  simulator parameters (heat loss, emitter ratio, % carnot, standby…) that
  best reproduce its observed daily behaviour (simulation-based inference /
  approximate Bayesian computation). The fitted % carnot is a *cleaned*
  machine-quality score; the residual misfit is where unmodelled effects
  (primary pipework, defrost) become visible. The Monte Carlo harness in
  `sim_harness/` already provides the forward model at ~10 s per simulated
  year, parallelised.

## Plan of action

**Phase 1 — pooled daily-COP model (a weekend-sized project)**
1. Pull daily aggregates for the ~225 filtered systems (emoncms daily feed
   API; feed ids are in the CSV: `heatpump_elec_feedid`,
   `heatpump_heat_feedid`, plus flow/outside temperature feeds).
2. Build the daily table: per system-day → COP, heat kWh, mean/min outsideT,
   mean flowT, runtime fraction, starts, DHW share, plus static system
   features. Apply the leakage rules.
3. Train GBM with grouped CV (hold out systems). SHAP for what drives daily
   COP. Roll up to annual SPF on held-out systems.
4. Benchmark against: dT baseline (±0.50) and the best aggregate model
   (±0.45). (η·H\* has since been computed from raw feeds and landed at
   ±0.58 — doc 09 — so dT remains the physics benchmark to beat.)

**Phase 2 — system embeddings**
5. Add per-system embeddings (start with a random-intercept mixed model, then
   a neural version if warranted). Compare annual roll-up accuracy; cluster
   and inspect the embeddings.

**Phase 3 — simulator integration (if Phase 1–2 show promise)**
6. Extend the sweep to vary % carnot, standby, weather files; pre-train on
   simulated system-days; fine-tune on real.
7. Prototype per-home calibration on a handful of well-monitored systems;
   compare fitted % carnot against `SPF / H*` (now available per system in
   `hstar_fleet.csv`, doc 09).

**Success criterion throughout**: 90% prediction interval on *held-out
systems'* annual SPF. Anything approaching ±0.25 is a genuinely strong result
given the ~±0.10 metering floor (`06_metering_floor.md`); claims below the floor
are automatically suspect.

## What not to expect

- No model on annual aggregates will get near ±0.1 — the information isn't in
  the table, and even with perfect features the ~±0.10 metering floor means
  ±0.14 combined is the practical best case against measured SPF.
- Deep learning on raw 10-second waveforms is premature: daily aggregates
  capture most of the usable signal at 1/10,000th the data volume, and 225
  homes cannot support learning low-level representations from scratch
  (the simulator pre-training route is the honest way to attempt that later).
- ML will not *replace* the physics route: the strongest likely outcome
  is physics prediction (dT, or H\*_design where design data exists — H\*
  itself fell to the fleet test, doc 09) + learned residual + per-system
  embedding, each doing the job it is best at. Doc 09's finding that quality
  rises with load factor is itself a target for the residual model — a
  two-stage SPF ≈ η(model, sizing) × H fit.
