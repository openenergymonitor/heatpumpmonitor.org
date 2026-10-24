# Integrating the hot-water model

This is the exact processing, for anyone wiring the model into the HPM server or porting it.
The model was trained on data prepared in exactly this way.
Change a step and the predictions get worse, often without any error to show it.
The code in `dhw_detection/` is the reference: each step names the function that does it.

## What it does

For one system, it reads five feeds at 30 s, the whole history, and gives a hot-water probability for every 30 s step.
A step is hot water when the probability is at least 0.5.
Runs of hot-water steps are the hot-water cycles.

## The inputs

| Feed | Units | Needed? |
|---|---|---|
| `heatpump_elec` | W | no, but better with it |
| `heatpump_heat` | W | no, but better with it (4 systems lack it) |
| `heatpump_flowT` | °C | yes: no prediction where it is missing |
| `heatpump_returnT` | °C | yes: no prediction where it is missing |
| `heatpump_flowrate` | as logged | no, but better with it (31 systems lack it) |
| `heatpump_dhw` | 0/1 | not an input; read only to compare with the prediction |

Electricity, heat and flow rate are scaled per system (step 5), so their units only need to be the same throughout one system's history.
Temperatures must be in °C, because they are scaled the same way on every system.

**The model needs 30 s averages, not 10 s samples.**
It was trained on what the public API returns for `/timeseries/data?id=N&feeds=...&start=U&end=U&interval=30&average=1&timeformat=unix`.
That is, 30 s averages of the stored feed on a grid of whole 30 s steps since 1970.
`start` and `end` must be unix seconds; date strings shift the grid.
On the server, the simplest safe route is the same emoncms code that serves that endpoint.
If you read the phpfina files directly, average them onto the same 30 s grid the same way, and check against the test case (see "Checking a port").
`dhw_detection/hpm_api.py` fetches exactly this from the public API.
It was checked against a month of the July 2026 download: every value was identical.

## The processing, step by step

**1. Grid** (`prepare.to_grid`).
Put the five feeds on a regular 30 s grid in UTC.
The grid runs from the first to the last step where any of the five has a reading.
Every other step is NaN.
A feed the system lacks is a column of NaN.

**2. Feeds that log slowly** (`prepare.reporting_steps`, `prepare.fill_reporting_gaps`).
For each feed, find its usual logging interval: the median spacing between its readings, in 30 s steps.
If that is more than 1 (some systems log every 1-2 minutes), join each reading to the next with a straight line.
Fill at most interval - 1 steps after a reading, and only where there is a reading on both sides (pandas `interpolate(limit=interval - 1, limit_area="inside")`).

**3. Short dropouts** (`prepare.join_short_gaps`).
For each feed, join any run of up to 10 missing steps (5 minutes) that has a reading on both sides with a straight line.
Longer gaps stay missing.

**4. Scales** (`prepare.system_scales`).
For each feed, work out a low and a high value over the system's **whole history**, after steps 2 and 3:

- flow and return temperature: always 0 and 70 °C
- electricity, heat and flow rate: low is the 1st percentile or 0, whichever is lower; high is the 99th percentile
- a feed the system lacks: low and high are NaN

**5. Scaling** (`predict.scaled`).
Scaled value = (value - low) / (high - low).
Where high is not above low, or is NaN, divide by 1 instead, and use 0 for a NaN low.
Missing values stay NaN.

**6. Model input** (`predict.model_inputs`).
There are 10 channels per step.
Channels 1-5 are the scaled feeds in the order elec, heat, flowT, returnT, flowrate, clipped to -1..2, with NaN set to 0.
Channels 6-10 are 1 where that feed is missing and 0 where it is present.

**7. Windows** (`predict.predict_probability`).
Cut the history into 24 h windows (2,880 steps).
A window starts at every 00:00, 06:00, 12:00 and 18:00 UTC.
The first starts at the first such time at or after the grid's start.
The last is the last that ends by the grid's end.
Windows overlap, so most steps fall in four windows.
Keep a window even if feeds are missing in it.
(Windows with no temperatures at all can be skipped; they change nothing.)

**8. Model** (`model.Net`, `model.load_models`).
Run each window through all three weight files and apply a sigmoid to each output.
Each step's probability is the mean over the three copies, then the mean over the windows that contain it.

**9. No prediction.**
The probability is NaN where the flow or return temperature is missing after step 3, and where no window covers the step.
The steps no window covers are up to the first and last 6 hours of a history.

**10. Hot water.**
1 where the probability is at least 0.5, 0 below it, NaN where there is no prediction.

**11. Cycles** (`predict.cycles`).
A cycle is a run of consecutive 1s.
A NaN ends a run.
`end` is the last hot-water step + 30 s.

## Outputs

`scripts/predict.py` writes one parquet file per system: `dhw_probability`, `dhw_predicted`, `inputs_complete` and `dhw_measured`.
It also writes a cycles CSV per system and one for all systems, plus a summary CSV and a settings JSON per system.
README.md describes each file.
The cycles CSVs are plain text so PHP can read them.

Ways to use them on HPM:

- **In the scan scripts.** Where a system has no trusted flag, take hot-water periods from `system_<id>_cycles.csv` instead of the flowT > 45 °C guess (`DHW_GUESS_FLOWT`, `feed_scan_6.php` and `feed_scan_8.php`).
- **Splitting heat and electricity.** Sum heat and electricity over the steps with `dhw_predicted = 1` to get hot-water kWh and COP; the rest is space heating.
- **A best-available flag.** Use the measured flag where it is trusted, and the prediction elsewhere. `data/flag_check.csv` lists the 384 systems whose flags were judged trustworthy in October 2026 (`flag = trusted`). For those, fill the flag across its own logging interval and count it as on at 0.5 or more. Which flags to trust from now on is HPM's call, so this step is not in the code.
- **Checking flags.** A low `f1_vs_flag` in `systems.csv` points to a flag, or a system, worth a look.

## Daily updates

A full run works out each system's settings: the logging interval of each feed (step 2) and its scales (step 4).
It stores them in `system_<id>.json`.
`--update` reuses them, so old predictions don't shift as new data arrives.
It works like this:

1. Take T0 = (the last step of the previous run + 30 s - 24 h), rounded down to a 6-hour boundary.
   Every step before T0 already had all its windows, so it is final.
2. Load the data from T0 - 19 h onwards.
   That is 18 h for the earliest window covering T0, plus 1 h so dropouts near the start join as they would in a full run.
3. Start the grid at T0 - 19 h. Predict with the windows starting at T0 - 18 h or later, using the stored settings.
4. Keep the old rows before T0 and replace everything from T0 onwards.

With the same settings, this gives the same answer as a full run.
`tests/test_predict.py` checks it, and through the command-line tool the difference was under 0.0000002.

When to do a full run instead (`--update` does this on its own in the first two cases):

- a new system, or one whose settings came from under 30 days of data
- a new model (the model name in the JSON changes)
- data that arrives late for a period more than a day before the last run, such as a buffered upload filling an old gap; a full run for that system, or a periodic full run of everything, picks it up
- if you want scales that reflect a much longer history; for systems with a year or more of data they barely change

## Pitfalls

- **Scales must come from the whole history.** Don't scale each window, or each day, on its own. The model judges levels against the system's usual range.
- **Keep the 6-hour UTC anchoring.** Windows starting at other times give slightly different averages and break `--update`.
- **Don't feed 10 s or 1-minute data.** Average onto the 30 s grid first (see "The inputs").
- **Don't drop windows because a feed is missing.** The model was trained to cope with a missing flow rate or heat feed, and with short outages.
- **Don't fill long gaps.** Only dropouts of up to 5 minutes, and slow-logging feeds within their own interval, are joined. Filling more makes up data the model never saw.
- **The threshold is 0.5.** It was not tuned per system.
- **Use full precision on a CPU.** The probabilities match the GPU's half-precision run to within about 0.006; about 1 step in 100,000 lands on the other side of 0.5.

## Checking a port

`tests/fixture_350.parquet` holds three days (19-21 January 2025) of system 350's raw 30 s feeds, as the API returns them.
It also holds the probability this code gives for each step (`expected_probability`).
`tests/fixture_350.json` holds the settings worked out from that system's whole history.
System 350 is a test system: the model never trained on it.

A port should reproduce `expected_probability` to within 0.0001 on every step, with the same steps NaN.
For a port of the data loading only (for example reading phpfina files), check two things:

1. Your 30 s values for those three days match the fixture's feeds.
2. Your settings for system 350's whole history match the JSON.

Run `python3 -m pytest tests` after any change to the code.

On the whole July 2026 download, this code was checked against the original training project's predictions for seven systems.
They covered ordinary, slow-logging, no-flow-rate, no-heat, no-electricity, no-temperature and zero-flow systems.
Away from the last day of each history, the steps with no prediction were identical.
Probabilities differed by up to 0.006 (GPU half precision against CPU full precision).
At most 0.0013% of steps changed side.
Run in full precision, the original code and this code agree to within 0.0000002.
Near the end of a history the original sometimes differs.
It ran its grid to the last reading of any feed, including outside-temperature forecasts that run days ahead.
So its last windows covered a few more real hours, from windows mostly made of missing data.

## Speed and hardware

No GPU is needed.
On a 14-core laptop CPU, one process does about 40 s per system-year and four processes about 20 s per system-year.
Beyond about four threads per process there is little gain, so `--workers` defaults to one process per four cores.
A full run of all 742 systems (1,249 system-years) takes about 7 hours there; a daily update, a few minutes.
Memory peaks at about 1.3 GB per process for a 4.5-year history (system 1), so allow about 0.3 GB per system-year per process.

## Model version

The weights are `p19_gru_rescued` (made 2026-10-14).
Retraining is not part of this folder (see METHOD.md).
A new model needs new weight files and a new `MODEL_NAME` in `dhw_detection/model.py`.
The next `--update` then does a full run of every system.
