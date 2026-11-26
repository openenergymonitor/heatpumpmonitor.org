# COP ladder: testing the HPM + EoH LightGBM findings on our fleet

A colleague's analysis reports that a window-level COP model reaches annual SPF R² 0.89 across 672 HPM + EoH
systems, against 0.70 for Carnot. That fleet is pooled and the target is SPF from clean space-heating windows only.
Our own best predictor of H4 SPF is weighted flow−outside ΔT (cv R² about 0.59, see `../docs/README.md`).
This folder tests their ablation ladder on our HPM fleet, scored against three targets, with RMSE alongside R²,
next to our ΔT baselines.

**Phase 1, status:** scripts written and checked on 54 systems; the full run is still to do.
Their code isn't available, so the features are rebuilt from the formulas in their findings document and only
approximate theirs.

## Running on the server

From `analysis/performance_prediction/`, with the venv set up as in `../docs/README.md`:

```
php cop_ladder/feed_map.php > cop_ladder/feed_map.json

# 1. check the direct feed read matches the public API (a couple of systems)
.venv/bin/python cop_ladder/build_windows.py --map cop_ladder/feed_map.json --check 2 3

# 2. fetch the all-fields last365 export (prints the matching --end)
.venv/bin/python cop_ladder/fetch_export.py

# 3. build 15-minute windows for the filtered fleet (30 s data; resumes if interrupted)
.venv/bin/python cop_ladder/build_windows.py --map cop_ladder/feed_map.json \
    --export heatpumpmonitor_all_fields_last365_<date>.csv --end <date>

# 4. fit and score the ladder
.venv/bin/python cop_ladder/model.py --export heatpumpmonitor_all_fields_last365_<date>.csv \
    --windows cop_ladder/windows_30s
```

- `fetch_export.py` rebuilds the system list page's all-fields CSV from `system/list/public.json` and
  `system/stats/last365`. It holds all public systems, not just those shown on the page, so more pass the filter.
  A CSV saved from the page works too.
- `--end` should match the export's last-365-days period, so the window data and the export's H4 SPF cover the
  same year. The last365 stats run to midnight (London) on the day they were rebuilt, normally today.
- `--step 120` builds 2-minute windows into `windows_120s/`, to match EoH resolution. Their HPM data was 30 s.
- Systems whose feeds aren't all PHPFina are fetched through the public API at 30 s (set `HPMON_URL` to change
  the server). `windows_*/build_log.csv` lists what was read and what was skipped.
- `model.py` needs only numpy, pandas and scikit-learn. The 54-system test took about a minute on 12 cores; the
  full fleet should take around five, and a few GB of memory.

Outputs, in the windows folder:

- `results.csv`: one row per model, with window R², and n, R² and RMSE for each target
- `systems.csv`: each system's measured targets, fold and predicted clean SPF from each model

## What it does

**Filter:** the standard fleet filter from `scripts/hpmon_analysis.py` (H4, air source, no cooling, ≥330 days,
MID), plus a weighted ΔT and a COP present.

**Windows:** 15 minutes, from 30 s data (the API's `interval=30&average=1`, read directly from PHPFina).

- *Running:* electricity above 300 W, or 200 W for units under 6 kW. Theirs was 300 W for all.
- *Defrost:* running with heat below −50 W.
- *Hot water:* the system's `heatpump_dhw` flag, where it logs one. Where it doesn't, every window counts as
  space heating, which leaves some hot water in for those systems.
- *Clean:* running throughout, no defrost, no hot water, and every input present. COP between 0.5 and 12.

**Features**, by stage (theirs, as far as the document describes them):

| Stage | Adds |
|---|---|
| 0a, 0b | Carnot COP, plain and +2/−6 K, times an efficiency fitted on each training fold |
| 1 | lift, flow, outside, both Carnot COPs |
| 2 | return, flow−return ΔT, `dflow_dt`, implied flow rate, outside change in the window, Lorentz COP |
| 3 | rated size, load ratio, estimated evaporator load ratio, pipe-loss proxy, flow per kW |
| 4 | refrigerant, margin to critical temperature |
| 5b | make and model (we have no compressor type, so their 5a is skipped) |
| 6 | minutes into run, starts in 24 h, minutes since defrost, defrosts in 6 h |

Two extra rows: Stage 6 **without heat-derived features** (implied flow, load ratios, pipe-loss proxy, flow per
kW), to see how much depends on metered heat; and **temperatures plus cycling only**.

**Model:** scikit-learn `HistGradientBoostingRegressor` as a stand-in for LightGBM. Trained on up to 2,000
clean windows per system so each system counts equally. 5 folds grouped by system, the same folds for every
model and baseline.

**Targets**, each scored at system level:

- *Clean SPF:* heat over electricity across the system's clean windows (their target). The model's prediction
  is Σheat / Σ(heat ÷ predicted COP) over the same windows.
- *Space SPF:* across all running space-heating windows, so defrost and short runs are included.
- *H4 SPF:* `combined_cop` from the export.

For space and H4 SPF, each model's predicted clean SPF is turned into a prediction by a linear fit on the
training folds. The ΔT baselines are fitted the same way.

## Differences from their pipeline

- HPM only: no EoH, so no pooled-fleet variance and no dataset flag.
- No compressor type and no Stage 8 (within-system) yet.
- Feature definitions are reconstructions; thresholds and clipping are our own choices.
- `HistGradientBoostingRegressor` in place of LightGBM.

## Smoke test (54 systems, 2-minute API data, not a result)

Early systems only, so too few to draw conclusions from. On these, H4 RMSE fell from 0.27 (ΔT) to about 0.22
from Stage 3 on. Removing the heat-derived features gave 0.24.

## Gap accounting (`gap.py`)

Phase 1 suggested that a perfect clean-window model would still leave about 0.27 RMSE on H4 SPF, so much of the
difference between systems may sit outside clean running. `gap.py` splits each system's gap between clean SPF and
H4 SPF into parts, using the existing windows and the export, with no model:

```
.venv/bin/python cop_ladder/gap.py --export heatpumpmonitor_all_fields_last365_<date>.csv \
    --windows cop_ladder/windows_30s
```

Each part is the share of total electricity used beyond running the same heat at the clean COP, so
`H4 SPF = clean SPF × (1 − sum of parts)` exactly. The parts are partial-run windows (a start or stop),
defrost windows, hot water, standby (from the published running vs combined electricity), and "other" (what the
windows don't cover). It prints the size and spread of each part, its share of the variance in ln(H4 SPF)
between systems, its correlation with ΔT and with the residual from the ΔT line, and the systems with the
largest gaps. Per-system values go to `gap_systems.csv`.

Both `model.py` and `gap.py` take `--exclude-flagged`, which leaves out systems with `data_flag` set on the site
(56 public systems in November 2026, including 429 "Electric meter faulty"). Outputs then get an `_unflagged`
suffix. The standard fleet filter doesn't exclude flagged systems, and our earlier ΔT baseline didn't either.

Partial-run windows can come out slightly *better* than clean running (a negative part). They include pump
overrun heat after the compressor stops and only part of the standby, so read them as approximate.
Systems without a hot-water flag have their hot water inside the space-heating parts.
