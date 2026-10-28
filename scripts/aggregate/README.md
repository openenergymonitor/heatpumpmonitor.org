# Fast fleet aggregator

Replacement for `scripts/aggregate_v2_last7.php`. It gives the same output and is
roughly 15–20x faster. It can also reprocess a long range in a single pass.

- `aggregate_fast.php`: does the metadata work through the same emoncms models as
  the original (system list, app config, `exist`, `get_meta`, engine and
  processList). It writes `plan.json`, runs the worker, then prints or posts the
  results.
- `fast_aggregate.py`: the numpy worker. It reads PHPFina files directly and
  evaluates simple virtual feeds (source, ± × ÷ source, scale, offset,
  allowpositive/negative, abs). The planner renders any other feed through
  emoncms `get_data` first.

```
php aggregate_fast.php                       # last 7 days, like the original (add --post to post)
php aggregate_fast.php --start 2024-11-18 --end 2026-10-26 --post   # full reprocess
```

A long range is split into consecutive 7-day windows (`--window` sets the length
in days). Each window gets its own cooling detection and elec feed range check,
which is what running the original once per week would do. Results are posted in
7-day chunks. Needs `python3` with numpy. `--workers` defaults to the CPU count.

## Synthetic benchmark (`synth/`)

```
python3 synth/generate.py --out DIR --systems 1000 --start 2026-10-14 --days 14
mysql -uemoncms -p < DIR/setup.sql                 # creates database hpmon_synth
php synth/baseline.php DIR START END base.json      # original loop via emoncms Feed model
php aggregate_fast.php --start START --end END --dataset DIR --workdir W
python3 synth/compare.py base.json W/out
```

The fleet includes these cases: 5/10/30/60/120/300 s intervals, feeds misaligned
to the minute, feeds that start, stop or end before the window, gaps, defrost
blips, cooling systems, ×1000 scaled feeds, −127 °C sensors, zero hp_output,
virtual feeds, missing feeds and configs, and unshared systems.
`baseline.php` and `--dataset` runs use redis db 9 and flush it, so only run one at a time.
