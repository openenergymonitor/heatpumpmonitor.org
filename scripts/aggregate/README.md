# Fast fleet aggregator

Replacement for `scripts/aggregate_v2_last7.php`. It gives the same output and is
roughly 15–20x faster. It can also reprocess a long range in a single pass.

- `aggregate_fast.php`: does the metadata work through the same emoncms models as
  the original (system list, app config, `exist`, `get_meta`, engine and
  processList). It writes `plan.json`, runs the worker, then prints or posts the
  results.
- `fast_aggregate.py`: the numpy worker. It reads PHPFina and PHPTimeSeries files directly and
  evaluates simple virtual feeds (source, ± × ÷ source, scale, offset,
  allowpositive/negative, abs). The planner renders any other feed through
  emoncms `get_data` first.

```
php aggregate_fast.php                       # last 7 days, like the original (add --post to post)
php aggregate_fast.php --start 2024-11-18 --end 2026-10-26 --post   # full reprocess
```

Systems are aggregated in groups, all in the same pass: `live` (all systems,
the existing feeds) and `mid` (systems with `mid_metering` set). Each feed is
read once and added to every group its system belongs to, so the MID dataset
costs almost nothing extra. `--groups live,mid` is the default; the groups are
defined in `$group_filters` in `aggregate_fast.php`.

With `--post`, each group is posted to the feeds on the remote emoncms tagged
with the group's name, with the names listed in `output_feeds.php`. Missing feeds
are created as 60 s PHPFina feeds, so the first run creates the `mid` feeds. The
server and apikey come from `$aggregate_host` and `$aggregate_apikey` in
`scripts/config.php`. Every group's feeds are looked up before anything is
posted, and if any can't be found or created, nothing is posted.

A long range is split into consecutive 7-day windows (`--window` sets the length
in days). Each window gets its own cooling detection and elec feed range check,
which is what running the original once per week would do. Results are posted in
7-day chunks. Needs `python3` with numpy. `--workers` defaults to the CPU count.

On a multi-year range each worker uses roughly 1 GB (2022 to 2026, both groups),
so set `--workers` to fit the memory. Each post is retried up to 3 times; any
range that still fails is listed at the end and the exit code is 1.

## Synthetic benchmark (`synth/`)

```
python3 synth/generate.py --out DIR --systems 1000 --start 2026-10-14 --days 14
mysql -uemoncms -p < DIR/setup.sql                 # creates database hpmon_synth
php synth/baseline.php DIR START END base.json [mid]   # original loop via emoncms Feed model
php aggregate_fast.php --start START --end END --dataset DIR --workdir W
python3 synth/compare.py base.json W/out/live         # or W/out/mid against a mid baseline
```

The fleet includes these cases: 5/10/30/60/120/300 s intervals, feeds misaligned
to the minute, feeds that start, stop or end before the window, gaps, defrost
blips, cooling systems, ×1000 scaled feeds, −127 °C sensors, zero hp_output,
virtual feeds, missing feeds and configs, and unshared systems.
`baseline.php` and `--dataset` runs use redis db 9 and flush it, so only run one at a time.
