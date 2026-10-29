# Notes for Claude

This folder predicts when a heat pump is heating domestic hot water (DHW), every 30 s, from five HPM feeds.
Read INTEGRATION.md before you change, port or wire in anything.
README.md covers use, and METHOD.md covers how the model was made.

The model was trained on data prepared in exactly one way. These must not change:

- Inputs are 30 s averages on a grid of whole 30 s steps since 1970, as `/timeseries/data?interval=30&average=1` returns. They are not raw 10 s samples.
- Gaps: join only dropouts of up to 5 minutes, and feeds that log slowly within their own logging interval.
- Scales come from each system's whole history: temperatures 0-70 °C; electricity, heat and flow rate from their 1st and 99th percentiles.
- Windows: 24 h, starting at 00/06/12/18 UTC.
- Averaging: the mean of the three weight files, then the mean of the overlapping windows.
- No prediction where the flow or return temperature is missing.
- Hot water at a probability of 0.5 or more.
- The weights are fixed. The training code is not here, so don't retrain or fine-tune.

After any change to the code, `python3 -m pytest tests` must pass.
The fixture test checks the whole chain against stored probabilities.
To check a port, see "Checking a port" in INTEGRATION.md.

When judging accuracy against `heatpump_dhw` flags, use only validation and test systems whose flag is `trusted` in `data/flag_check.csv`.
Train systems and rescued systems flatter the model.

Outputs are predictions, not measurements. Say so wherever numbers made from them are shown.
