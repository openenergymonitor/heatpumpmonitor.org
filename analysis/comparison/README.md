# System comparison

Tools to work out why two similar heat pump systems get different SPFs, and to scan a model family for similar pairs.
They reproduce the system 384 vs 335 analysis (Vaillant Arotherm+ 5 kW, SPF 3.72 vs 4.24).

All data comes from the public HeatpumpMonitor.org API: the system list, daily stats (`system/stats/daily`) and timeseries (`timeseries/data`).
Downloads are cached in `data/`, which is not committed.

## Running

```
pip install -r requirements.txt
python -m comparison pair 384 335 --end 2026-11-17
python -m comparison fleet --end 2026-11-17
```

`--end` is the last day of the reference year. Add `--refresh` to download again rather than use the cache.
`pair` also takes `--windows`, the start dates of four 7-day windows of 10 s data (default January and October 2026).
Set `HPMON_URL` to use another server and `HPMON_CACHE` to put the cache elsewhere.

A first `pair` run downloads around 100 MB of JSON per system and takes a few minutes. It is cached compressed at about 8 MB per system, and later runs use the cache.

Results go to `output/`: `pair_A_B.json` and `.md`, `fleet_arotherm.json` and `.md`, and `fleet_arotherm.csv`.

## What it does

`comparison/otmodel.py` is a Python port of the outside-temperature model in `www/views/outsidetemp.php`.
It gives the same fits as the page: the heat demand line, and the ideal (Carnot) efficiency for space heating and hot water.
Carnot COP uses flow + 2 K for condensing and outside − 6 K for evaporating.

`pair` (in `__main__.py`) runs these steps for systems A and B:

- **Daily** (`daily.py`): reference-year summary, then a Shapley decomposition of the model SPF gap over eight factors: climate, space demand, hot water demand, standby, and the efficiency and flow curve for each mode. It also gives A's SPF with B's efficiency.
- **1-minute timeseries** (`minute.py`):
  - **Running behaviour:** cycling, running means and steady-state COP.
  - **Defrost:** events, rates and COP penalty. A defrost is a dip in heat below −1 kW while running.
  - **Matched comparison:** steady-state COP with outside temperature, flow temperature and heat output matched.
  - **Ideal efficiency by outside band.**
  - **Heat meter sensor offset:** measured with the pump running and the compressor off, with the effect of correcting for it.
  - **Implied air shortfall:** how much colder A's evaporator air would need to be to explain the remaining gap.
- **10-second timeseries** (`fast.py`):
  - **Pump power** by flow rate.
  - **Primary loop volume:** from the return-to-flow transport delay.
  - **Primary loop heat loss:** from pump-only periods, evaluated over the measured delays because it is sensitive to them.
- **Between-cycle losses** (`minute.between_cycle_losses`): primary loop loss while the pump runs with the compressor off (loss rate × water − outside), and while the pump is off (each period modelled as the loop water cooling towards outside temperature, an upper bound).
- **COP budget:** A's space heating COP gap split into defrost, pump, pipework while running, pipework between cycles, and a remainder.

`fleet` (`fleet.py`) runs the daily summary for every non-flagged Vaillant Arotherm and lists the most different pairs that are otherwise similar.
It also checks whether ideal efficiency correlates with flow − return ΔT or with cold-day defrost (negative heat on days below 4 °C as a share of heat).

## Limits

- **Outside temperature** is an hourly weather-service feed, not a sensor at the unit.
- **Primary loop heat loss** depends strongly on the assumed transport delay, so it is given as a range.
- **Daily negative heat** is a rough defrost measure: it also includes small negative readings while the pump runs with the compressor off.

## Manual check

[docs/manual_loop_lag.md](docs/manual_loop_lag.md) shows how to measure the primary loop delay, volume and pipe length by hand on the dashboard.
