# Hot-water detection

This folder holds a trained model that says, every 30 seconds, whether a heat pump is heating domestic hot water (DHW) or the house.
It reads five standard HeatpumpMonitor.org feeds: electricity, heat, flow temperature, return temperature and flow rate.
It runs on an ordinary CPU.

## Why it is useful

A heat pump heats either the house or the hot-water cylinder.
Without knowing which, heat, electricity and COP can't be split between the two.
Only some systems log a hot-water flag (`heatpump_dhw`), and some of those flags are wrong.
Of the 742 systems in the July 2026 download, 309 had no usable flag: 266 never logged one, and 43 had one that failed a basic check.
Today the scan scripts guess hot water as "running with flow temperature above 45 °C" when there is no flag (`DHW_GUESS_FLOWT` in `scripts/feed_scan/`).
This model is meant to replace that guess, and to check the flags that exist.

## How good it is

Validation means systems the model never trained on.
The scores below compare every 30 s step with the measured flag on 43 validation systems with flags that were checked (1,664 days):

- F1 0.977 (precision 0.972, recall 0.983)
- 98.4% of hot-water cycles found (at least half covered)
- about one false cycle every 22 days
- cycle starts and ends within half a minute of the flag (median)

With feeds removed on the same days:

- no flow rate: F1 0.972 (31 systems have no flow-rate feed)
- no heat: F1 0.965 (4 systems have no heat feed)
- 20-minute outages of every feed, three a day: F1 0.976

Those days were chosen to have every feed working and a believable flag.
A harder test is the whole history of the 65 test systems, which played no part in training or in choosing the model, scored against their flags as logged:

- the median system scores F1 0.978
- 11 of the 65 score below 0.9
- pooled over every step, F1 is 0.896

Some of those low scores are wrong flags rather than wrong predictions: system 1203's flag is on all the time.
The others have not been checked.
`data/predicted_hw_systems.csv` has the score of every system.
[METHOD.md](METHOD.md) explains how the model was trained and scored.

## Quick start

```bash
cd analysis/dhw_detection
python3 -m venv .venv && . .venv/bin/activate
pip install torch --index-url https://download.pytorch.org/whl/cpu   # the CPU build: far smaller than the default on Linux
pip install -r requirements.txt
python3 -m pytest tests                       # about 10 s
python3 scripts/predict.py --ids 204          # one system, fetched from the public API
```

It needs Python 3.10 or later.
It was tested on Python 3.10 (pandas 2.3, PyTorch 2.14) and Python 3.13 (pandas 3.0, PyTorch 2.11).

## Running it on every system

```bash
python3 scripts/predict.py --all              # full run: every public system, whole history
python3 scripts/predict.py --all --update     # afterwards, e.g. daily: only the new data
```

`--update` reuses each system's stored settings and re-predicts only the last day or so, so it takes seconds per system.
`--source parquet DIR` reads files laid out like the HPM download (`DIR/system_<id>.parquet`) instead of the API.
`--workers N` sets how many systems run at once; `--out DIR` sets where results go (default `output/`).

## What it writes

In `output/`:

| File | What it holds |
|---|---|
| `system_<id>.parquet` | One row per 30 s step (UTC): `dhw_probability` (0 to 1), `dhw_predicted` (1 hot water, 0 not, empty where there is no prediction), `inputs_complete` (all five feeds present), `dhw_measured` (the system's own flag, unchanged) |
| `system_<id>_cycles.csv` | Each predicted hot-water period: start and end (unix seconds and UTC text) and length in minutes |
| `hot_water_cycles.csv` | Every system's periods in one file |
| `systems.csv` | One row per system: days predicted, share of time on hot water, cycles a day, and agreement with its own flag where it has one |
| `system_<id>.json` | The system's settings (feed logging intervals and scales), its data range and its summary. `--update` needs these |

There is no prediction where the flow or return temperature is missing, or in the first and last few hours of a history.
The last few hours fill in at the next update.

## Speed

Measured on a laptop (Intel i7-12700H, 14 cores), CPU only:

- one process: about 40 s per system-year of data
- four processes: about 20 s per system-year
- all 742 systems (1,249 system-years): about 7 hours with four processes
- daily update: a few seconds per system
- a GPU (RTX 3080 Ti) does a system-year in about 1 s, but isn't needed

More threads per process don't help beyond about four, so run one process per four cores or so.
Each process needs about 0.3 GB of memory per system-year of the system it is working on (1.3 GB for a 4.5-year history).
Fetching through the public API adds roughly a minute per system-year.

## What is in this folder

| Path | What it is |
|---|---|
| `dhw_detection/` | The code: `prepare.py` (data onto a 30 s grid, gaps joined, scales), `predict.py` (windows, model, averaging, cycles), `model.py` (the network), `hpm_api.py` (fetching from the public API) |
| `weights/` | The trained model: three copies trained with different random seeds (`p19_gru_rescued`), 384 KB each |
| `scripts/predict.py` | The command-line tool above |
| `tests/` | Checks, and a three-day test case (system 350) that any port of the processing should reproduce |
| `data/flag_check.csv` | For each of the 742 systems: its split (train, validation, test or no usable flag) and whether its `heatpump_dhw` flag was judged trustworthy, wrong or unusable |
| `data/predicted_hw_systems.csv` | The per-system results of the 2026-10-14 run over the July 2026 download |
| [INTEGRATION.md](INTEGRATION.md) | The exact processing, step by step, for anyone wiring this into the HPM server or porting it |
| [METHOD.md](METHOD.md) | How the model was trained, which labels it learned from, and how it was scored |
| [CLAUDE.md](CLAUDE.md) | Notes for an AI assistant working in this folder |

## Known limits

- It is a prediction, not a measurement. Keep the source with any number made from it.
- A system with no flow-rate or heat feed gets slightly fewer cycles right (see the scores above).
- System 95 has no temperatures, so it has no prediction.
- System 571 logs zero flow rate and its flow temperature never rises, so it is predicted to have no hot water at all, though its flag shows 8%.
- Systems 581, 1120 and 906 have flags that passed every check but look wrong (see `data/flag_check.csv`). Prefer the prediction there.

## Where it came from

The model was trained on the HeatpumpMonitor.org download of 10 July 2026 by John Ewbank ([Miteas](https://github.com/Miteas)).
The training code (label checks, window sampling, training runs) lives in a separate project and is not included here.
This folder holds what is needed to run the trained model, and METHOD.md says how it was made.
