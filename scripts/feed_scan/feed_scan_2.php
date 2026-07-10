<?php

$dir = dirname(__FILE__);
chdir("/var/www/heatpumpmonitor");

define('EMONCMS_EXEC', 1);
require "Lib/load_database.php";

require("Modules/user/user_model.php");
$user = new User($mysqli,false);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

$system_id = 2;

// 1. Determine the app id.
$result = $mysqli->query("SELECT app_id FROM system_meta WHERE id = $system_id");
$row = $result->fetch_object();
print "App ID: ".$row->app_id."\n";

// 2. Load the app config
$result = $emoncms_mysqli->query("SELECT * FROM app WHERE id = ".$row->app_id);
$row = $result->fetch_object();
$app_config = json_decode($row->config);

// 3. Load phpfina feeds of interest
$feeds_to_load = array(
    "heatpump_elec",
    "heatpump_heat",
    "heatpump_flowT",
    "heatpump_returnT",
    "heatpump_flowrate",
    "heatpump_outsideT"
);

$feedids = array();

// For each of these feeds we need to check if they exist as phpfina feeds in the emoncms feed table.
foreach ($feeds_to_load as $key) {
    if (isset($app_config->$key)) {
        $feedid = (int) $app_config->$key;
        $result = $emoncms_mysqli->query("SELECT id FROM feeds WHERE id = $feedid AND engine = 5");
        // assign the feed id to the array if it exists
        if ($result->num_rows > 0) {
            $feedids[$key] = $feedid;
        }
    }
}

$meta = array();

// 4. For each feed id, get the meta data and print it out.
foreach ($feedids as $key => $feedid) {
    $meta[$key] = getmeta("/var/opt/emoncms/phpfina/", $feedid);
    // response in format {"interval":10,"start_time":1600870440,"npoints":18132636,"end_time":1782196800}
}

// 5. Determine latest start time and earliest end time across all feeds
$latest_start_time = 0;
$earliest_end_time = PHP_INT_MAX;

foreach ($meta as $key => $feed_meta) {
    if ($feed_meta->start_time > $latest_start_time) {
        $latest_start_time = $feed_meta->start_time;
    }
    if ($feed_meta->end_time < $earliest_end_time) {
        $earliest_end_time = $feed_meta->end_time;
    }
}

print "Latest start time: $latest_start_time\n";
print "Earliest end time: $earliest_end_time\n";


$fh = array();

// 6. Seek to the correct position in each feed based on the latest start time
foreach ($meta as $key => $feed_meta) {
    $pos = ($latest_start_time - $meta[$key]->start_time) / $meta[$key]->interval;
    print "Seek position: $pos\n";

    $fh[$key] = fopen("/var/opt/emoncms/phpfina/".$feedids[$key].".dat", 'rb');
    fseek($fh[$key], $pos * 4);
}

// 7. Scan through the feeds at 10s resolution, detecting stable episodes:
//    periods of at least 10 minutes where the compressor is running (elec > MIN_ELEC)
//    and flowT is stable (stdev over a rolling 10 min window below STDEV_MAX).
//    Each episode is collapsed to a single representative point (mean of each feed).
//
//    Efficiency: feeds are read in large blocks (one fread + unpack per feed per
//    ~10 days) and the rolling window uses a ring buffer with running sum and
//    sum-of-squares, so the per-sample cost is O(1). Variance is compared against
//    STDEV_MAX squared to avoid sqrt in the hot loop. Episode means are recovered
//    by differencing running totals at episode start and end.

define('WINDOW', 60);          // rolling window: 60 x 10s = 10 minutes
define('STDEV_MAX', 0.15);     // max flowT standard deviation within the window (°C)
define('MIN_ELEC', 150);       // compressor running threshold (W)
define('BLOCK_SIZE', 86400);   // 10s samples read per block per feed (10 days, ~340kB)

if (!isset($feedids["heatpump_elec"]) || !isset($feedids["heatpump_flowT"])) {
    die("heatpump_elec and heatpump_flowT feeds are required\n");
}

$npoints = intdiv($earliest_end_time - $latest_start_time, 10);
$var_max = STDEV_MAX * STDEV_MAX;

$keys = array_keys($feedids);
$F = count($keys);
$KE = array_search("heatpump_elec", $keys);
$KF = array_search("heatpump_flowT", $keys);

// Ring buffer holding the last WINDOW good samples for every feed, with running
// window sums, plus running totals over the current unbroken run of good samples.
$ring = array();
for ($f=0; $f<$F; $f++) $ring[$f] = array_fill(0, WINDOW, NAN);
$win_sum = array_fill(0, $F, 0.0); $win_n = array_fill(0, $F, 0);
$run_sum = array_fill(0, $F, 0.0); $run_n = array_fill(0, $F, 0);
$win_sumsq = 0.0; $run_sumsq = 0.0;   // flowT only, for stdev
$run_len = 0;

$episodes = array();
$total_stable = 0;
$ep = false;

$close_episode = function() use (&$episodes, &$ep, &$total_stable, $keys, $F, $KF, $latest_start_time) {
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
    // flowT drift: change in the 10 min window mean between open and close (°C/hour)
    $e["flowT_drift"] = ($ep['close_mean'] - $ep['open_mean']) / ($e["duration"] / 3600.0);
    $e["dT"] = isset($e["heatpump_returnT"]) ? $e["heatpump_flowT"] - $e["heatpump_returnT"] : NAN;
    $e["cop"] = (isset($e["heatpump_heat"]) && $e["heatpump_elec"] > 0) ? $e["heatpump_heat"] / $e["heatpump_elec"] : NAN;
    $episodes[] = $e;
    $total_stable += $e["duration"];
    $ep = false;
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

        // Good sample: compressor running and flowT valid
        // (NAN == NAN is false, NAN > x is false, so NANs fail this test)
        if ($flowT == $flowT && $elec > MIN_ELEC) {

            $pos = $run_len % WINDOW;
            $full = $run_len >= WINDOW;

            // flowT sum of squares (evict before the ring slot is overwritten below)
            if ($full) {
                $old = $ring[$KF][$pos];
                $win_sumsq -= $old * $old;
            }
            $win_sumsq += $flowT * $flowT;
            $run_sumsq += $flowT * $flowT;

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
                        $ep['close_mean'] = $mean;
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
                            'start_sum' => $start_sum, 'start_n' => $start_n,
                            'start_sumsq' => $run_sumsq - $win_sumsq,
                            'end_sum' => $run_sum, 'end_n' => $run_n,
                            'end_sumsq' => $run_sumsq,
                            'open_mean' => $mean, 'close_mean' => $mean
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
                for ($f=0; $f<$F; $f++) {
                    $run_sum[$f] = 0.0; $run_n[$f] = 0;
                    $win_sum[$f] = 0.0; $win_n[$f] = 0;
                }
            }
        }

        // Print . for every 24h that has been processed
        if ($i % 8640 == 0) {
            print ".";
        }

        // newline every 30 days
        if ($i % 259200 == 0) {
            print "\n";
        }

        // double new line every 365 days
        if ($i % 3153600 == 0) {
            print "\n";
        }
    }
}
if ($ep) $close_episode();

foreach ($fh as $h) fclose($h);

$elapsed = microtime(true) - $scan_start;

// 8. Write one CSV row per episode
/*
$csvfile = $dir."/episodes_".$system_id.".csv";
$csv = fopen($csvfile, 'w');
$header = array("start_time", "date", "duration_s", "flowT_stdev", "flowT_drift_per_h");
foreach ($keys as $key) $header[] = str_replace("heatpump_", "", $key);
$header[] = "dT";
$header[] = "cop";
fputcsv($csv, $header);

foreach ($episodes as $e) {
    $row = array(
        $e["start_time"],
        date("Y-m-d H:i", $e["start_time"]),
        $e["duration"],
        round($e["flowT_stdev"], 4),
        round($e["flowT_drift"], 3)
    );
    foreach ($keys as $key) $row[] = round($e[$key], 3);
    $row[] = round($e["dT"], 3);
    $row[] = round($e["cop"], 3);
    fputcsv($csv, $row);
}
fclose($csv);*/

print "\n\n";
print "Scanned $npoints samples (".round($npoints * 10 / 86400, 1)." days) in ".round($elapsed, 1)."s\n";
print "Stable episodes: ".count($episodes)."\n";
print "Total stable time: ".round($total_stable / 3600, 1)." hours (".round(100 * $total_stable / ($npoints * 10), 1)."% of period)\n";
print "Output: $csvfile\n";

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

function getmeta($dir, $id)
{
    if (!file_exists($dir . $id . ".meta")) {
        print "input file $id.meta does not exist\n";
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