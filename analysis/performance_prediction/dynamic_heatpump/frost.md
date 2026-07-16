# Add evaporator frosting + defrost cycle model to dynamic heat pump simulator

## Context

The dynamic heat pump simulator (`/var/www/tools/tools/dynamic_heatpump/`) models heat output, COP, cycling, space heating and (just added) a DHW cylinder — but assumes the outdoor unit never frosts. Real ASHPs build frost on the evaporator when the coil runs below 0 °C in moist air, and periodically run a reverse-cycle defrost that pulls heat back out of the heating circuit, typically costing 1–2% of annual heat delivered. This change adds a physically based frost accumulation model, a threshold-triggered reverse-cycle defrost state machine, user-configurable parameters (RH default 80%), a frost-mass chart trace, and defrost energy results — with defaults calibrated so the Llanberis annual run lands in the 1–2% band.

**Discovery:** `llanberis2024.csv` rows are `timestamp, temp, RH%, solar, agile` — `parse_csv` already reads the humidity column (`dynamic_heatpump.js:250`) then discards it. Annual mode will therefore use **real measured humidity** (with a toggle), and the fixed RH param (default 80%) drives day mode.

## Files

- `dynamic_heatpump.js` — model, state machine, chart, config (line refs current as of planning)
- `dynamic_heatpump.php` — new "Evaporator frosting & defrost" card after the Hot water cylinder card
- Read-only: `llanberis2024.csv` (humidity col 2 verified), scratchpad harnesses (`harness.js`, `annual.js`, `regression.js`)

## Physics design

**Frost accumulation** (only when `defrost_state == 0` and `heatpump_heat > 0`):
- Coil temp `T_coil = outside − coil_dt` (default 6 K, matching the carnot_fixed offset). Frost forms when `T_coil < 0` and ambient humidity ratio exceeds saturation at the coil:
  - Magnus: `e = RH·610.94·exp(17.625·T/(T+243.04))` Pa; `w = 0.622·e/(101325−e)`
  - `frost_mass += air_kg_s · (w_amb − w_coil) · capture_eff · timestep`
- `air_kg_s = airflow/3600 × 1.25` (default airflow 3500 m³/h for an ~8.5 kW monobloc). Single over-water Magnus for both; the water/ice difference is lumped into `capture_eff`. The model naturally reproduces the worst-case 0…+5 °C high-RH band (below ~−7 °C air holds little moisture; above +6 °C coil is above freezing).

**Defrost trigger:** `frost_mass ≥ threshold` (default 1.0 kg) while `heatpump_state == 1`, gated by `frost.enabled`.

**Reverse-cycle defrost (run-until-melted):** `heatpump_heat = 0`; `dhw_mode` forced false (defrost always draws from the space circuit — real systems divert to space, and the 15 L DHW primary would swing wildly); heat drawn from MWT at `defrost_power` (default 4000 W); melt rate `defrost_power·melt_eff/334000` kg/s (`melt_eff` 0.6 absorbs coil/fan/sensible losses); electric draw `defrost_elec` (default 1000 W) + pumps. Exit when frost ≤ 0 (clamp) or 20-min safety cap. Emergent cycle: ~557 kJ at 4 kW ≈ 2.3 min per 1 kg — tunable via `defrost_power`.

**Interactions (by construction, no extra guards):**
- Placed **after ramp limiting**: overrides final `heatpump_heat`, so diverter split, MWT, COP, and all accumulators see 0 heat. `last_heatpump_heat` is captured pre-ramp (js:1145), so post-defrost output ramps back up from min modulation — realistic recovery, no anti-cycle lockout triggered. `dhw_reheat_state` untouched → DHW resumes automatically.
- `system_DT = (heatpump_heat − defrost_draw)/flow_heat_capacity` (js:1195) → flow temp dips ~2.4 K below MWT and below return during defrost, matching real monitoring signatures. Existing `system_DT>1` guard already excludes defrost steps from carnot stats.
- `heatpump_heat` never negative → `heat_kwh`, `dhw_heat_kwh`, flowT-weighted stats all safe. Defrost elec flows into `elec_kwh` (SCOP/cost degrade correctly); the circuit heat loss shows up as extra space-heating runtime.
- Summer cutoff: no interaction (coil > 0 °C above 6 °C outside).

**COP derating with frost build-up: excluded from v1** — the Vaillant/Ecodan EN 14511 tables already embed average frost penalties near +2 °C (explicit derating would double-count), and it would destabilise the 1–2% calibration. Noted in card help text as a future extension.

**Calibration (computed against the real CSV):** ~1499 h/yr of frosting-capable weather at Llanberis (mean RH 87%), duty-weighted ~1133 h; at 3500 m³/h and capture_eff 0.20 → ~0.92 kg/h while running → ~1045 cycles/yr at 1 kg threshold (one per ~65 min in frosty weather; target 40–90 min ✓) → ~162 kWh/yr drawn ≈ **1.7% of 9300 kWh** ✓, defrost elec ~40 kWh/yr. `capture_eff` is the linear tuning knob; final value confirmed via harness (step 4 below).

## Implementation steps

1. **Vue data** (after `dhw` group): `frost: { enabled: true, humidity: 80, use_csv_humidity: true, airflow: 3500, capture_eff: 0.20, coil_dt: 6, threshold: 1.0, defrost_power: 4000, defrost_elec: 1000, melt_eff: 0.6 }` and `show_frost: false` near the other show flags. Add `defrost_heat_kwh, defrost_elec_kwh, defrost_cycles` to `results`.

2. **CSV humidity plumbing**: declare `var annual_dataset_humidity = []` beside `annual_dataset_outsideT` (~js:42); push in `parse_csv` (local `humidity` already parsed at js:250); return `humidity` from `get_from_annual_dataset()`. In the loop, `let humidity = frost_rh` state var; CSV branch sets `humidity = (frost_use_csv_rh && isFinite(dataset.humidity)) ? dataset.humidity : frost_rh`, synthetic branch sets `frost_rh`.

3. **Warm-start globals** (beside `cyl_T = []; dhw_primaryT = 35;` ~js:644): `frost_mass = 0; defrost_state = 0;` — persist across the 5-day pre-sim. `defrost_steps` and accumulators are `sim()` locals; `frost_data = []` with the other arrays.

4. **Hoisted consts** (end of hoisting block ~js:851): `frost_enabled`, clamped `frost_rh`, `frost_use_csv_rh`, `frost_air_kg_s`, `frost_capture_eff`, `frost_coil_dt`, `frost_threshold`, `defrost_power`, `defrost_elec_power`, `defrost_melt_kg_per_s`, `defrost_max_steps = ceil(1200/timestep)`.

5. **Frost/defrost block** — single block inserted immediately after ramp limiting (~js:1155), before the diverter split: accumulation (Magnus equations above) → trigger (`defrost_cycles++`) → defrost override (`heatpump_heat = 0; dhw_mode = false; defrost_draw = defrost_power;` melt, exit on melted/cap). Plus 4 one-line touches:
   - after `MWT += (heat_to_space…)`: `MWT -= (defrost_draw * timestep) / water_heat_capacity;`
   - js:1195: `let system_DT = (heatpump_heat - defrost_draw) / flow_heat_capacity;`
   - after `heatpump_elec += hp_standby;` (js:1287): during defrost add `defrost_elec_power + hp_pumps` to `heatpump_elec` and accumulate `defrost_elec_kwh` / `defrost_heat_kwh += defrost_draw * power_to_kwh`
   - data recording: `frost_data[i] = frost_mass;`

6. **Outputs**: add the three defrost fields to the return object and the `simulate()` copy-back (after `min_cylinder_top_temp`).

7. **Chart**: `window.frost_data = timeseries(frost_data)`; **append** series index 11 `{ label: "Frost", color: "#00aacc", yaxis: 5, lines: {show, fill} }` (new dedicated kg axis — don't reuse axis 4/agile); gate on `app.show_frost` like the cylinder toggles; tooltip line `Frost: …kg` after CylBottomT.

8. **Export/import**: add `frost` to `export_config` and `Object.assign` merge in `import_config` (old configs without it keep defaults).

9. **UI card** (`dynamic_heatpump.php`, after the Hot water cylinder card): help text (frost band 0–5 °C, reverse-cycle defrost, note that datasheet COP tables already embed an average defrost penalty so totals slightly double-count); checkboxes `frost.enabled` + `frost.use_csv_humidity`; input rows humidity %RH / airflow m³/h, capture_eff / coil_dt K, threshold kg / melt_eff, defrost_power W / defrost_elec W; `show_frost` graph checkbox; results lines: defrost cycles, heat drawn kWh, defrost elec kWh, and defrost loss as `% of heat delivered`.

## Verification (headless harness, scratchpad)

1. **Disabled-path regression**: `frost.enabled = false` → day + annual results identical (3 dp) to current working-tree code (and to `git show HEAD:` for the pre-DHW space-heating path with DHW also disabled).
2. **Frosty day** (`external.mid = 2, swing = 2, humidity 85`): defrost every 40–90 min of compressor runtime; sawtooth peaks ~1.0 kg, melts in ≤ 6 min; flowT dips below returnT during defrost; `defrost_heat_kwh ≈ cycles × 0.155 kWh` ±10%; room disturbance < 0.2 K.
3. **Band shape**: day runs at mid −8 °C and +10 °C → near-zero frost; +2 °C → maximal.
4. **Annual calibration**: defrost loss in **1.0–2.0%** of heat_kwh (expect ~1.7%), ~700–1300 cycles/yr; tune `capture_eff` default if outside band and update JS default + card text.
5. **Invariants**: no NaN/Inf (extend annual scan with frost_data), frost_data ≥ 0, `elec(enabled) − elec(disabled) ≈ defrost_elec_kwh` + small recovery term, `dhw_elec_kwh` shift < 2% (defrost must not leak into DHW stats).
6. `php -l` + `node --check`.

## Risks

- Lookup COP models (Vaillant/Ecodan) already embed average defrost penalties → totals ~1–2% pessimistic with those models; accepted for v1, stated in UI text.
- Single-volume MWT damps the defrost dip (~1.1 K vs 3–5 K real); the inverted flow/return DT signature still shows. Future: smaller effective draw volume.
- `enabled: true` default shifts existing day/annual outputs by design (as with the DHW defaults).
- Series indices are positional — series 11 + toggle + tooltip must land together.
