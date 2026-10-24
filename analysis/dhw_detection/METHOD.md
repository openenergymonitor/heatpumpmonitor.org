# How the hot-water model was made

This is an account of how the model in `weights/` (`p19_gru_rescued`) was trained and scored.
The training code is not in this folder.
INTEGRATION.md describes the processing used to run it, which is the same as in training.

## The data

The data is the HeatpumpMonitor.org download of 10 July 2026.
It holds every public system's whole history as 30 s averages from the public API (`interval=30&average=1`).
742 systems had data, from March 2017 to July 2026: 1,249 system-years.

The model reads five feeds: electricity, heat, flow temperature, return temperature and flow rate.
It learns from a sixth: the hot-water flag `heatpump_dhw`, which some systems log.
A 30 s step counts as hot water when the flag is 0.5 or more.
Averaging faster readings onto 30 s can give fractions, which is why the line sits at 0.5.
Where a flag reports only every few steps, each reading stands until the next.

## Which flags it learned from

**An automatic check.**
A flag passed unless it was always off or had values outside 0 to 1.
433 systems passed and 43 failed.
266 never logged a flag.

**A split by system.**
The 433 systems were split at random into 303 train, 65 validation and 65 test systems.
A system's whole history is in one group only.
Every score below uses only systems the model never trained on.

**A check by eye.**
Each train and validation flag was compared with predictions from an earlier model.
That model was trained without the system in question: the systems were split into five groups, and each group was predicted by a model trained on the other four.
Where flag and prediction disagreed, the system was looked at by hand.
The result:

- 49 flags were judged wrong for the whole history or large parts of it (missing, stuck on, or late), with the model right.
- 18 systems had data problems and were kept out of training, though their flags are trusted.
- The other 384, including the 65 test systems, are trusted (`flag = trusted` in `data/flag_check.csv`).

The test flags were not checked, to keep the test set untouched, so a few of them may be wrong.

**Three systems were excluded** for data problems: 412 (heat missing or wrong), 568, and 571 (zero flow rate, flow temperature never rising).

## Rescued flags

Some flags were wrong as a whole but right in parts.
50 such systems were partly used as training labels: 43 of the 49 judged wrong, plus 7 that failed the automatic check only because of a few odd readings (3, 210, 252, 416, 620, 707, 949).
Their flag was compared with predictions from an earlier model (`p16_gru_robust`) that had never trained on them:

- **Months.** A month was left out where the flag was missing or far above the prediction.
  Missing meant on for under 0.1 h a day while the model predicted at least that.
  Far above meant more than twice the prediction plus 0.5 h a day.
  Months with under a week to judge were left out too.
- **Cycles, inside the kept months.**
  - Where a flag cycle and a predicted cycle overlapped, the flag was kept, timing included.
  - A cycle seen only by the flag or only by the model was left unlabelled.
  - So were the minutes before the flag came on, and any flag run more than an hour past the model.
- Flag readings other than 0 and 1 were left unlabelled.

Steps with no label count for nothing in training.

## Training windows

The model reads 24 h windows (2,880 steps), starting every 6 hours on the UTC clock.
Up to 40 windows were drawn at random from each system.
Train and validation windows had to pass these rules; test windows were never filtered:

- the flag is known for at least half of the window;
- every feed is present after joining dropouts of up to 5 minutes (a system with no flow-rate feed is judged on the other four);
- the window is not within 30 days of a feed's first reading, which is often unreliable;
- there are no fault stretches;
- hot water is on for at most 30% of the window; more is taken as a stuck flag (30% is about the 99th percentile).

A fault stretch is 5 minutes or more of either:
- electricity without heat: the compressor running, judged as 50 W of electricity per rated kW, while heat is within ±100 W;
- heat without electricity: over 400 W of heat while electricity is under 100 W.

That gave 11,672 windows from 294 systems.
9,692 came from 244 train systems with trusted flags, and 1,980 from the 50 rescued systems.

## The network

- A 1-D convolutional network with a recurrent layer on top, 92,881 numbers in all.
- The input has 10 channels per 30 s step: five scaled feeds, and five channels marking which feeds are missing (INTEGRATION.md, steps 4-6).
- The feeds are widened to 48 channels, then pass through 7 blocks of convolutions.
  - Each block has kernel 5, with dilations 1, 2, 4 ... 64, group normalisation and GELU, and adds its result to its input.
  - Together they let each step see about 2 hours either side.
- A bidirectional GRU, 24 units each way, then reads the whole 24 h window, and its output is added in.
- A final layer gives one hot-water logit per step.

## Training

- Binary cross-entropy, counted only on labelled steps.
- fastai's one-cycle schedule: 10 epochs, peak learning rate 0.002, batches of 64 windows, mixed precision on a GPU.
- The epoch with the best F1 on the validation windows was kept.
- Three copies were trained with random seeds 0, 1 and 2.
  Predictions use the mean of all three.

Three kinds of rough data were added at random during training, so the model copes with real systems:

- **Level changes, in every window.** Electricity, heat and flow rate were multiplied by a factor between 1/1.2 and 1.2, and temperatures shifted by up to 3 °C, one draw per window and feed.
  So the model has to judge levels against the rest of the day, not learn each system's absolute values.
- **Outages.** In 30% of windows, 1-3 stretches of 5-30 minutes were blanked, in every feed or in one feed, equally often.
  Steps where a temperature was blanked were not scored, since there is no prediction there.
- **A missing feed.** In half of the windows, the whole flow-rate feed (70% of the time) or heat feed (30%) was hidden.
  31 systems have no flow-rate feed and 4 no heat feed.

The last two applied only to windows from systems the model already predicted well (F1 0.95 or more from the five-group check above, not counting rescued systems), whose labels are most trustworthy.

## How it predicts

The method is in INTEGRATION.md, steps 7-10.
Each step's probability is the mean over the three copies and the up-to-four windows that contain it.
Hot water is 0.5 or more.
There is no prediction where a temperature is missing.

## Scores

**Validation windows.**
These are the 1,664 validation windows from 43 systems that passed the window rules, with flags judged unusable left out.
They were predicted by the full method (the whole history, overlapping windows, three copies) and scored per 30 s step against the flag:

| Measure | Value |
|---|---|
| F1 | 0.977 |
| Precision | 0.972 |
| Recall | 0.983 |
| Measured cycles found (at least half covered) | 98.4% |
| Measured cycles missed (under 10% covered) | 1.1% |
| False cycles (5 min or more, under 10% overlap with the flag) | 0.045 a day: about one every 22 days |
| Cycle start and end against the flag (median error) | 0.5 min |
| Error in the share of time on hot water, per window | 0.17 percentage points on average |

With feeds removed on the same windows:

| Test | F1 |
|---|---|
| Flow rate hidden | 0.972 |
| Heat hidden | 0.965 |
| 20-minute outages of every feed, three a day | 0.976 |
| 20-minute outages of one feed, three a day | 0.977 |

Picking the best epoch on these windows flatters them a little.
The flags still contain some errors, which pulls the other way.

**Test systems, whole histories.**
The 65 test systems played no part in training or in choosing the model.
Over their whole histories, against their flags as logged and without any window rules:

- the median system scores F1 0.978
- 11 of the 65 score below 0.9
- pooled over every step, F1 is 0.896

Some of the low scores are wrong flags (system 1203's flag is on all the time).
The others have not been checked.

`data/predicted_hw_systems.csv` gives every system's whole-history agreement with its flag.
Agreement is a fair test only for validation and test systems with a trusted flag.
It flatters train systems and the 50 rescued systems, because the model learned from parts of their histories.

## Earlier versions

`p4_aug`, `p12_gru`, `p14_gru_mask` and `p16_gru_robust` came first and are superseded.
`p19_gru_rescued` is the `p16_gru_robust` recipe on more data.
The extra data is the rescued flags, plus windows with short dropouts joined and systems with no flow-rate feed.

## Known problems (October 2026)

- Three trusted flags look wrong, found after the model was finished: 581 (stuck on, 54% of the time), 1120 (6 h a day against 1.8 h predicted) and 906 (long runs over 2 h with no prediction). All three are validation systems.
- System 3 (a flag with a few odd readings) is predicted less well, F1 0.76 against its flag. Predictions run on through the gaps between short bursts and start a few minutes before the flag.
- System 571 is predicted 0 everywhere because its flow data is broken.
- System 95 has no temperatures, so it has no prediction. System 743 has no electricity feed.

## The training code

The training code is in a separate analysis project by the same author, and is not published.
It covers the flag checks, window sampling, label rescue and training runs.
Retraining needs a GPU (each copy took about 100 s on an RTX 3080 Ti, once the windows were built) and the hand-checked flag verdicts.
