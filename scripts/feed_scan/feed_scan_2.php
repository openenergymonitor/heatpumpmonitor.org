<?php

// Fleet episode scan: detect stable steady-state episodes for every public,
// MID-metered, H4 metering-boundary system and write them to signature_episodes.
//
// The system list uses the same combined_meta_stats_query filters as
// find_homes_like_this / feed_scan_4.php. Public visibility (share + published)
// is enforced inside combined_meta_stats_query and cannot be overridden here.
// feed_scan_4's hp_type='Air Source' filter is intentionally NOT applied: that
// restriction exists for H*'s evaporator reconstruction, which this raw episode
// scan does not do.
//
// Each system's overlapping feed window is scanned at 10s resolution for periods
// of at least 10 minutes where the compressor is running (elec > MIN_ELEC) and
// flowT is stable (rolling 10 min stdev below STDEV_MAX). Each episode is
// collapsed to a single representative point (mean of each feed).
//
// Usage: php feed_scan_2.php [max_systems]   (0 or omitted = all)

$dir = dirname(__FILE__);
chdir($dir."/../../www");

define('EMONCMS_EXEC', 1);
require "Lib/load_database.php";

require("Modules/user/user_model.php");
$user = new User($mysqli,false);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

require ("Modules/system/system_stats_model.php");
$system_stats = new SystemStats($mysqli,$system);

$max_systems = isset($argv[1]) ? (int) $argv[1] : 0;

// Episode detection parameters
define('WINDOW', 60);          // rolling window: 60 x 10s = 10 minutes
define('STDEV_MAX', 0.01);     // max flowT standard deviation within the window (°C)
define('SLOPE_MAX', 0.5);      // max |flowT slope| over the whole episode (°C/hour, 0 = no constraint)
define('MIN_ELEC', 100);       // compressor running threshold (W)
define('BLOCK_SIZE', 86400);   // 10s samples read per block per feed (10 days, ~340kB)
define('MAX_EPISODES', 4000);     // per-system cap for testing (0 = scan everything)
define('PHPFINA_DIR', "/var/opt/emoncms/phpfina/");

// 1. Pull the system list: public, MID metered, H4 metering boundary.
//    (share/published are enforced inside combined_meta_stats_query.)
$query = array(
    'meta_filters' => array(
        'mid_metering' => 1,
        'metering_boundary_code' => 4
    ),
    'meta_fields' => array('id', 'hp_manufacturer', 'hp_model', 'hp_output'),
    'stats_fields' => array('combined_data_length'),
    'sort' => array('field' => 'combined_cop', 'order' => 'desc'),
    'stats_table' => 'system_stats_last365_v2'
);

$systems = $system_stats->combined_meta_stats_query($query);

$N = count($systems);
print "Systems matching filters (public, MID, H4): $N\n";
if ($max_systems > 0 && $N > $max_systems) {
    $systems = array_slice($systems, 0, $max_systems);
    $N = $max_systems;
    print "Limited to first $N (by combined_cop desc)\n";
}
print "\n";

// 2. Scan each system and write its episodes to signature_episodes.
$fleet_start = microtime(true);
$total_written = 0;
$scanned = 0;
$skipped = array();

foreach ($systems as $n => $s) {
    $system_id = (int) $s->id;
    printf("[%3d/%3d] %5d %-24s ", $n + 1, $N, $system_id,
        substr($s->hp_manufacturer." ".$s->hp_model, 0, 24));

    $r = scan_system_episodes($mysqli, $emoncms_mysqli, $system_id);
    if (!is_array($r)) {
        print "SKIP: $r\n";
        $skipped[$system_id] = $r;
        continue;
    }

    $written = signature_write($mysqli, $system_id, $r['episodes']);
    $total_written += $written;
    $scanned++;

    printf(" %5.0fd  episodes %5d  wrote %5d  stable %6.1fh (%4.1f%%)  %5.1fs\n",
        $r['scanned'] * 10 / 86400,
        count($r['episodes']), $written,
        $r['total_stable'] / 3600,
        $r['scanned'] > 0 ? 100 * $r['total_stable'] / ($r['scanned'] * 10) : 0,
        $r['elapsed']);
}

$elapsed = microtime(true) - $fleet_start;

// 3. Summary
print "\n";
print "Scanned $scanned systems, wrote $total_written episodes in ".round($elapsed, 1)."s, skipped ".count($skipped)."\n";
if (count($skipped)) {
    print "\nSkipped systems:\n";
    foreach ($skipped as $id => $reason) print "  $id: $reason\n";
}
print "\n";


// ---- Per-system scan ---------------------------------------------------
// Scan one system's overlapping feed window at 10s resolution, detecting stable
// episodes and collapsing each to a single representative point (mean of each
// feed). Returns an array of results (including 'episodes'), or an error string
// if the system cannot be scanned.
//
// Efficiency: feeds are read in large blocks (one fread + unpack per feed per
// ~10 days) and the rolling window uses a ring buffer with running sum and
// sum-of-squares, so the per-sample cost is O(1). Variance is compared against
// STDEV_MAX squared to avoid sqrt in the hot loop. Episode means are recovered
// by differencing running totals at episode start and end.
function scan_system_episodes($mysqli, $emoncms_mysqli, $system_id)
{
    // app id -> app config -> feed ids
    $result = $mysqli->query("SELECT app_id FROM system_meta WHERE id = $system_id");
    if (!$result || !($row = $result->fetch_object())) return "no system_meta row";

    $result = $emoncms_mysqli->query("SELECT * FROM app WHERE id = ".(int)$row->app_id);
    if (!$result || !($row = $result->fetch_object())) return "no app row";
    $app_config = json_decode($row->config);

    // phpfina feeds of interest (elec and flowT are required; the rest optional)
    $feeds_to_load = array(
        "heatpump_elec",
        "heatpump_heat",
        "heatpump_flowT",
        "heatpump_returnT",
        "heatpump_flowrate",
        "heatpump_outsideT",
        "heatpump_roomT"
    );
    $feedids = array();
    foreach ($feeds_to_load as $key) {
        if (isset($app_config->$key)) {
            $feedid = (int) $app_config->$key;
            $result = $emoncms_mysqli->query("SELECT id FROM feeds WHERE id = $feedid AND engine = 5");
            if ($result->num_rows > 0) $feedids[$key] = $feedid;
        }
    }
    if (!isset($feedids["heatpump_elec"]) || !isset($feedids["heatpump_flowT"])) {
        return "heatpump_elec and heatpump_flowT feeds required";
    }

    // Feeds with missing meta or no data abort the scan if required, and are
    // silently dropped if optional (so e.g. an empty roomT feed doesn't stop
    // the rest of the system's episodes being processed).
    $meta = array();
    foreach ($feedids as $key => $feedid) {
        $m = getmeta(PHPFINA_DIR, $feedid);
        if ($m === false || $m->npoints <= 0) {
            $reason = ($m === false) ? "missing meta for" : "empty feed";
            if ($key == "heatpump_elec" || $key == "heatpump_flowT") {
                return "$reason $key (feed $feedid)";
            }
            unset($feedids[$key]);
            continue;
        }
        $meta[$key] = $m;
    }

    // Overlapping window across feeds: latest start, earliest end.
    $latest_start_time = 0;
    $earliest_end_time = PHP_INT_MAX;
    foreach ($meta as $m) {
        if ($m->start_time > $latest_start_time) $latest_start_time = $m->start_time;
        if ($m->end_time < $earliest_end_time) $earliest_end_time = $m->end_time;
    }
    $npoints = intdiv($earliest_end_time - $latest_start_time, 10);
    if ($npoints <= 0) return "no overlapping feed data";

    // Open each feed. load_block() re-seeks by timestamp on every read, so this
    // initial seek to the latest common start time is just a starting position;
    // intdiv keeps it an integer sample offset when feeds aren't interval-aligned.
    $fh = array();
    foreach ($meta as $key => $m) {
        $pos = intdiv($latest_start_time - $m->start_time, $m->interval);
        $fh[$key] = fopen(PHPFINA_DIR.$feedids[$key].".dat", 'rb');
        fseek($fh[$key], $pos * 4);
    }

    $var_max = STDEV_MAX * STDEV_MAX;
    $keys = array_keys($feedids);
    $F = count($keys);
    $KE = array_search("heatpump_elec", $keys);
    $KF = array_search("heatpump_flowT", $keys);
    $KH = array_search("heatpump_heat", $keys);   // false if no heat feed

    // Ring buffer holding the last WINDOW good samples for every feed, with
    // running window sums, plus running totals over the current unbroken run of
    // good samples.
    $ring = array();
    for ($f=0; $f<$F; $f++) $ring[$f] = array_fill(0, WINDOW, NAN);
    $win_sum = array_fill(0, $F, 0.0); $win_n = array_fill(0, $F, 0);
    $run_sum = array_fill(0, $F, 0.0); $run_n = array_fill(0, $F, 0);
    $win_sumsq = 0.0; $run_sumsq = 0.0;   // flowT only, for stdev
    $win_sumxy = 0.0; $run_sumxy = 0.0;   // flowT only, sum of (run index * flowT) for slope
    $run_len = 0;

    $episodes = array();
    $total_stable = 0;
    $rejected_slope = 0;
    $ep = false;

    $close_episode = function() use (&$episodes, &$ep, &$total_stable, &$rejected_slope, $keys, $F, $KF, $latest_start_time) {
        $n_samples = $ep['last_i'] - $ep['start_i'] + 1;
        $e = array(
            "start_time" => $latest_start_time + $ep['start_i'] * 10,
            "end_time"   => $latest_start_time + $ep['last_i'] * 10 + 10,
            "duration"   => $n_samples * 10
        );
        // Mean of each feed over the episode: difference of running totals
        for ($f=0; $f<$F; $f++) {
            $n = $ep['end_n'][$f] - $ep['start_n'][$f];
            $e[$keys[$f]] = $n > 0 ? ($ep['end_sum'][$f] - $ep['start_sum'][$f]) / $n : NAN;
        }
        // flowT stdev over the whole episode
        $nf = $ep['end_n'][$KF] - $ep['start_n'][$KF];
        $mean = $e["heatpump_flowT"];
        $var = ($ep['end_sumsq'] - $ep['start_sumsq']) / $nf - $mean * $mean;
        $e["flowT_stdev"] = $var > 0 ? sqrt($var) : 0.0;
        // flowT slope over the whole episode: least squares fit against sample index.
        // sum_xy is shifted so x runs 0..n-1 within the episode, which keeps the
        // sums well conditioned; sum_x and sum_x2 then have closed forms.
        $n = $n_samples;
        $sum_y = $ep['end_sum'][$KF] - $ep['start_sum'][$KF];
        $sum_xy = ($ep['end_sumxy'] - $ep['start_sumxy']) - $ep['start_g'] * $sum_y;
        $sum_x = $n * ($n - 1) / 2;
        $sum_x2 = ($n - 1) * $n * (2 * $n - 1) / 6;
        $den = $n * $sum_x2 - $sum_x * $sum_x;
        $slope = $den > 0 ? ($n * $sum_xy - $sum_x * $sum_y) / $den : 0.0;  // °C per sample
        $e["flowT_slope"] = $slope * 360.0;  // °C per hour (360 x 10s samples per hour)

        $e["dT"] = isset($e["heatpump_returnT"]) ? $e["heatpump_flowT"] - $e["heatpump_returnT"] : NAN;
        $e["cop"] = (isset($e["heatpump_heat"]) && $e["heatpump_elec"] > 0) ? $e["heatpump_heat"] / $e["heatpump_elec"] : NAN;
        $ep = false;

        // optional constraint: reject episodes with too much overall slope
        if (SLOPE_MAX > 0 && abs($e["flowT_slope"]) > SLOPE_MAX) {
            $rejected_slope++;
            return;
        }

        $episodes[] = $e;
        $total_stable += $e["duration"];
    };

    $scan_start = microtime(true);

    $i = 0;
    while ($i < $npoints) {
        $block_n = min(BLOCK_SIZE, $npoints - $i);
        $block_t0 = $latest_start_time + $i * 10;

        $data = array();
        for ($f=0; $f<$F; $f++) {
            $data[$f] = load_block($fh[$keys[$f]], $meta[$keys[$f]], $block_t0, $block_n);
        }

        for ($j=0; $j<$block_n; $j++, $i++) {

            $elec = $data[$KE][$j];
            $flowT = $data[$KF][$j];

            // Zero (or negative) metered heat while the compressor is running is
            // a heat-meter error: treat the sample as bad so these periods are
            // discarded rather than pulling episode heat/COP means down.
            // (A NAN heat sample is not an error; it is simply missing data and
            // is already excluded from the episode means.)
            $heat = $KH !== false ? $data[$KH][$j] : NAN;
            $heat_ok = $KH === false || $heat != $heat || $heat > 0;

            // Good sample: compressor running, flowT valid and heat plausible
            // (NAN == NAN is false, NAN > x is false, so NANs fail these tests)
            if ($flowT == $flowT && $elec > MIN_ELEC && $heat_ok) {

                $pos = $run_len % WINDOW;
                $full = $run_len >= WINDOW;

                // flowT sum of squares and sum of (run index * flowT) for stdev and slope
                // (evict before the ring slot is overwritten below; the evicted sample
                //  was pushed WINDOW samples ago so its run index is run_len - WINDOW)
                if ($full) {
                    $old = $ring[$KF][$pos];
                    $win_sumsq -= $old * $old;
                    $win_sumxy -= ($run_len - WINDOW) * $old;
                }
                $win_sumsq += $flowT * $flowT;
                $run_sumsq += $flowT * $flowT;
                $win_sumxy += $run_len * $flowT;
                $run_sumxy += $run_len * $flowT;

                for ($f=0; $f<$F; $f++) {
                    if ($full) {
                        $old = $ring[$f][$pos];
                        if ($old == $old) { $win_sum[$f] -= $old; $win_n[$f]--; }
                    }
                    $v = $data[$f][$j];
                    $ring[$f][$pos] = $v;
                    if ($v == $v) {
                        $win_sum[$f] += $v; $win_n[$f]++;
                        $run_sum[$f] += $v; $run_n[$f]++;
                    }
                }
                $run_len++;

                if ($run_len >= WINDOW) {
                    $mean = $win_sum[$KF] / WINDOW;
                    $var = $win_sumsq / WINDOW - $mean * $mean;

                    if ($var < $var_max) {
                        // window [i-59, i] is stable
                        if ($ep && ($i - $ep['last_i']) < WINDOW) {
                            // overlaps / nearly adjacent to the open episode: extend it
                            $ep['last_i'] = $i;
                            $ep['end_sum'] = $run_sum; $ep['end_n'] = $run_n;
                            $ep['end_sumsq'] = $run_sumsq;
                            $ep['end_sumxy'] = $run_sumxy;
                        } else {
                            if ($ep) $close_episode();
                            // run totals minus window totals = totals at the window start
                            $start_sum = array(); $start_n = array();
                            for ($f=0; $f<$F; $f++) {
                                $start_sum[$f] = $run_sum[$f] - $win_sum[$f];
                                $start_n[$f] = $run_n[$f] - $win_n[$f];
                            }
                            $ep = array(
                                'start_i' => $i - (WINDOW - 1), 'last_i' => $i,
                                'start_g' => $run_len - WINDOW,   // run index of the first episode sample
                                'start_sum' => $start_sum, 'start_n' => $start_n,
                                'start_sumsq' => $run_sumsq - $win_sumsq,
                                'start_sumxy' => $run_sumxy - $win_sumxy,
                                'end_sum' => $run_sum, 'end_n' => $run_n,
                                'end_sumsq' => $run_sumsq,
                                'end_sumxy' => $run_sumxy
                            );
                        }
                    } else if ($ep && ($i - $ep['last_i']) >= WINDOW) {
                        $close_episode();
                    }
                }

            } else {
                // run broken: close any open episode and reset all rolling state
                if ($ep) $close_episode();
                if ($run_len > 0) {
                    $run_len = 0;
                    $run_sumsq = 0.0; $win_sumsq = 0.0;
                    $run_sumxy = 0.0; $win_sumxy = 0.0;
                    for ($f=0; $f<$F; $f++) {
                        $run_sum[$f] = 0.0; $run_n[$f] = 0;
                        $win_sum[$f] = 0.0; $win_n[$f] = 0;
                    }
                }
            }

            // For testing: stop once we have MAX_EPISODES episodes
            if (MAX_EPISODES > 0 && count($episodes) >= MAX_EPISODES) break 2;

            // progress: one dot per 30 days scanned
            if ($i % 259200 == 0) print ".";
        }
    }
    // close any episode still open at the end of the scan (unless we stopped at the test limit)
    if ($ep && (MAX_EPISODES == 0 || count($episodes) < MAX_EPISODES)) $close_episode();

    foreach ($fh as $h) fclose($h);

    return array(
        'episodes'      => $episodes,
        'scanned'       => $i,
        'npoints'       => $npoints,
        'total_stable'  => $total_stable,
        'rejected_slope' => $rejected_slope,
        'elapsed'       => microtime(true) - $scan_start
    );
}

// Write episodes to the signature_episodes table. Existing rows for this system
// are removed first so the scan can be re-run idempotently. Inserts run inside a
// single transaction with a prepared statement for speed. Optional feed means and
// derived metrics that are missing or non-finite (NAN) are stored as NULL.
function signature_write($mysqli, $system_id, $episodes)
{
    $system_id = (int) $system_id;

    // finite value or null (NAN / INF -> NULL for nullable columns)
    $nn = function ($v) { return (isset($v) && is_finite($v)) ? $v : null; };

    $mysqli->query("DELETE FROM signature_episodes WHERE system_id = $system_id");

    $sql = "INSERT INTO signature_episodes
        (system_id, start_time, end_time, duration,
         elec, flowT, heat, returnT, flowrate, outsideT, roomT,
         dT, cop, flowT_stdev, flowT_slope)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        print "signature_write prepare failed: " . $mysqli->error . "\n";
        return 0;
    }

    // Bind variables (assigned per row below; bind_param binds by reference)
    $start_time = $end_time = $duration = 0;
    $elec = $flowT = $heat = $returnT = $flowrate = $outsideT = $roomT = null;
    $dT = $cop = $flowT_stdev = $flowT_slope = null;

    $stmt->bind_param(
        "iiiiddddddddddd",
        $system_id, $start_time, $end_time, $duration,
        $elec, $flowT, $heat, $returnT, $flowrate, $outsideT, $roomT,
        $dT, $cop, $flowT_stdev, $flowT_slope
    );

    $mysqli->begin_transaction();
    $written = 0;
    foreach ($episodes as $e) {
        $start_time  = (int) $e['start_time'];
        $end_time    = (int) $e['end_time'];
        $duration    = (int) $e['duration'];
        $elec        = $nn($e['heatpump_elec'] ?? null);
        $flowT       = $nn($e['heatpump_flowT'] ?? null);
        $heat        = $nn($e['heatpump_heat'] ?? null);
        $returnT     = $nn($e['heatpump_returnT'] ?? null);
        $flowrate    = $nn($e['heatpump_flowrate'] ?? null);
        $outsideT    = $nn($e['heatpump_outsideT'] ?? null);
        $roomT       = $nn($e['heatpump_roomT'] ?? null);
        $dT          = $nn($e['dT'] ?? null);
        $cop         = $nn($e['cop'] ?? null);
        $flowT_stdev = $nn($e['flowT_stdev'] ?? null);
        $flowT_slope = $nn($e['flowT_slope'] ?? null);
        $stmt->execute();
        $written++;
    }
    $mysqli->commit();
    $stmt->close();

    return $written;
}

// Read a block of $n 10s-aligned samples starting at time $t0 from a phpfina feed.
// Feeds stored at other intervals are resampled by repetition. Missing data is NAN.
function load_block($fh, $meta, $t0, $n)
{
    $interval = $meta->interval;
    $p0 = intdiv($t0 - $meta->start_time, $interval);
    $p1 = intdiv(($t0 + ($n - 1) * 10) - $meta->start_time, $interval);
    if ($p0 < 0) $p0 = 0;
    if ($p1 >= $meta->npoints) $p1 = $meta->npoints - 1;

    $native = array();
    if ($p1 >= $p0) {
        fseek($fh, $p0 * 4);
        $raw = fread($fh, ($p1 - $p0 + 1) * 4);
        if ($raw !== false && strlen($raw) >= 4) {
            $native = unpack("f*", substr($raw, 0, strlen($raw) - (strlen($raw) % 4)));
        }
    }

    // Fast path: native 10s feed aligned to the block start
    if ($interval == 10 && ($t0 - $meta->start_time) % 10 == 0) {
        $out = array_values($native);
        for ($j = count($out); $j < $n; $j++) $out[$j] = NAN;
        return $out;
    }

    $out = array();
    for ($j = 0; $j < $n; $j++) {
        $idx = intdiv(($t0 + $j * 10) - $meta->start_time, $interval) - $p0 + 1;
        $out[$j] = isset($native[$idx]) ? $native[$idx] : NAN;
    }
    return $out;
}

// Read phpfina metadata (interval, start_time, npoints, end_time). Returns false
// if the .meta file is missing.
function getmeta($dir, $id)
{
    if (!file_exists($dir . $id . ".meta")) {
        return false;
    }

    $meta = new stdClass();
    $metafile = fopen($dir . $id . ".meta", 'rb');
    fseek($metafile, 8);
    $tmp = unpack("I", fread($metafile, 4));
    $meta->interval = $tmp[1];
    $tmp = unpack("I", fread($metafile, 4));
    $meta->start_time = $tmp[1];
    fclose($metafile);

    clearstatcache($dir . $id . ".dat");
    $npoints = floor(filesize($dir . $id . ".dat") / 4.0);
    $meta->npoints = $npoints;

    $meta->end_time = $meta->start_time + ($meta->npoints * $meta->interval);

    return $meta;
}
