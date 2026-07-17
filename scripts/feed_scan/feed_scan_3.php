<?php

// H*: heat-weighted harmonic Carnot COP with load-dependent offsets.
// See analysis/performance_prediction/docs/05_harmonic_carnot_metric.md
//
// Single pass over the raw 10s feeds accumulating:
//   H* (variable offsets)   = sum(heat) / sum(heat / carnot_var)
//   H  (fixed +2/-6 offsets) = sum(heat) / sum(heat / carnot_fixed)
//   heat-weighted mean flowT, outsideT and (flowT - outsideT)
//   measured SPF over the same window (sum heat / sum elec), for SPF / H*
//
// Usage: php feed_scan_3.php [system_id]

$dir = dirname(__FILE__);
chdir("/var/www/heatpumpmonitor");

define('EMONCMS_EXEC', 1);
require "Lib/load_database.php";

require("Modules/user/user_model.php");
$user = new User($mysqli,false);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

$system_id = isset($argv[1]) ? (int) $argv[1] : 748;

// H* offset coefficients (K per unit load ratio) and fixed-offset convention (K)
define('A_COND', 3.0);
define('B_EVAP', 8.0);
define('FIXED_COND', 2.0);
define('FIXED_EVAP', 6.0);

define('BLOCK_SIZE', 86400);   // 10s samples read per block per feed (10 days, ~340kB)

// 1. Determine the app id and rated capacity
$result = $mysqli->query("SELECT app_id, hp_output FROM system_meta WHERE id = $system_id");
$row = $result->fetch_object();
print "App ID: ".$row->app_id."\n";

$capacity = $row->hp_output * 1000.0; // kW -> W
if (!($capacity > 0)) die("system $system_id has no rated capacity (hp_output)\n");
print "Rated capacity: ".$row->hp_output." kW\n";

// 2. Load the app config
$result = $emoncms_mysqli->query("SELECT * FROM app WHERE id = ".$row->app_id);
$row = $result->fetch_object();
$app_config = json_decode($row->config);

// 3. Load phpfina feeds of interest
$feeds_to_load = array(
    "heatpump_heat",
    "heatpump_flowT",
    "heatpump_outsideT",
    "heatpump_elec"       // only used for measured SPF / H*
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

foreach (array("heatpump_heat", "heatpump_flowT", "heatpump_outsideT") as $key) {
    if (!isset($feedids[$key])) die("$key feed is required\n");
}

$meta = array();

// 4. For each feed id, get the meta data.
foreach ($feedids as $key => $feedid) {
    $meta[$key] = getmeta("/var/opt/emoncms/phpfina/", $feedid);
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

print "Latest start time: $latest_start_time (".date("Y-m-d", $latest_start_time).")\n";
print "Earliest end time: $earliest_end_time (".date("Y-m-d", $earliest_end_time).")\n";

$fh = array();
foreach ($feedids as $key => $feedid) {
    $fh[$key] = fopen("/var/opt/emoncms/phpfina/".$feedid.".dat", 'rb');
}

$npoints = intdiv($earliest_end_time - $latest_start_time, 10);

$keys = array_keys($feedids);
$F = count($keys);
$KH = array_search("heatpump_heat", $keys);
$KF = array_search("heatpump_flowT", $keys);
$KO = array_search("heatpump_outsideT", $keys);
$KE = array_search("heatpump_elec", $keys);
$has_elec = ($KE !== false);

// 6. Single pass accumulators (dt is a constant 10s so it cancels in every ratio)
$sum_heat = 0.0;             // sum of heat while running, valid temps
$sum_ideal_elec_var = 0.0;   // sum of heat / carnot (variable offsets)
$sum_ideal_elec_fixed = 0.0; // sum of heat / carnot (fixed +2/-6 offsets)
$sum_heat_flowT = 0.0;       // heat-weighted flowT
$sum_heat_outsideT = 0.0;    // heat-weighted outsideT

$sum_heat_all = 0.0;         // all valid heat samples, for measured SPF
$sum_elec_all = 0.0;         // all valid elec samples, for measured SPF
$n_elec_all = 0;
$n_heat_all = 0;

$n_running = 0;              // samples accumulated into H*
$n_skipped_temp = 0;         // running but flowT/outsideT missing

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

        $h = $data[$KH][$j];
        $flowT = $data[$KF][$j];
        $outsideT = $data[$KO][$j];

        // measured SPF over the whole window, standby included
        // (NAN == NAN is false, so NANs fail these tests)
        if ($h == $h) { $sum_heat_all += $h; $n_heat_all++; }
        if ($has_elec) {
            $elec = $data[$KE][$j];
            if ($elec == $elec) { $sum_elec_all += $elec; $n_elec_all++; }
        }

        // accumulate H* while heat is being delivered only
        if ($h > 50) {
            if ($flowT == $flowT && $outsideT == $outsideT) {

                $r = $h / $capacity;

                // variable (load-dependent) offsets
                $Tcond = $flowT + A_COND * $r;
                $Tevap = $outsideT - B_EVAP * $r;
                $lift = $Tcond - $Tevap;
                if ($lift > 0) {
                    $carnot = ($Tcond + 273.15) / $lift;
                    $sum_heat += $h;
                    $sum_ideal_elec_var += $h / $carnot;

                    // fixed-offset convention over the same samples
                    $lift_fixed = ($flowT + FIXED_COND) - ($outsideT - FIXED_EVAP);
                    $sum_ideal_elec_fixed += $h * $lift_fixed / ($flowT + FIXED_COND + 273.15);

                    $sum_heat_flowT += $h * $flowT;
                    $sum_heat_outsideT += $h * $outsideT;
                    $n_running++;
                }
            } else {
                $n_skipped_temp++;
            }
        }

        // Print . for every 24h that has been processed
        if ($i % 8640 == 0) print ".";
        // newline every 30 days
        if ($i % 259200 == 0) print "\n";
    }
}

foreach ($fh as $h) fclose($h);

$elapsed = microtime(true) - $scan_start;

print "\n\n";
print "System $system_id\n";
print "Scanned $i samples (".round($i * 10 / 86400, 1)." days) in ".round($elapsed, 1)."s\n";
print "Running samples in H*: $n_running (".round($n_running * 10 / 3600)." hours)\n";
if ($n_skipped_temp > 0) print "Running samples skipped (missing temps): $n_skipped_temp\n";
print "\n";

if ($sum_ideal_elec_var <= 0) die("no running data accumulated\n");

$H_star = $sum_heat / $sum_ideal_elec_var;
$H_fixed = $sum_heat / $sum_ideal_elec_fixed;
$w_flowT = $sum_heat_flowT / $sum_heat;
$w_outsideT = $sum_heat_outsideT / $sum_heat;

print "Total heat (while running): ".round($sum_heat * 10 / 3600000, 1)." kWh\n";
print "Heat-weighted flowT:        ".number_format($w_flowT, 2)." C\n";
print "Heat-weighted outsideT:     ".number_format($w_outsideT, 2)." C\n";
print "Heat-weighted dT:           ".number_format($w_flowT - $w_outsideT, 2)." K\n";
print "\n";
print "H  (fixed +2/-6 offsets):   ".number_format($H_fixed, 3)."\n";
print "H* (variable +".A_COND."r/-".B_EVAP."r):     ".number_format($H_star, 3)."\n";

if ($has_elec && $sum_elec_all > 0) {
    $spf = $sum_heat_all / $sum_elec_all;
    print "\n";
    print "Total heat (all):           ".round($sum_heat_all * 10 / 3600000, 1)." kWh\n";
    print "Total elec (all):           ".round($sum_elec_all * 10 / 3600000, 1)." kWh\n";
    print "Measured SPF (window):      ".number_format($spf, 3)."\n";
    print "SPF / H  (prc carnot):      ".number_format($spf / $H_fixed, 3)."\n";
    print "SPF / H* (corrected):       ".number_format($spf / $H_star, 3)."\n";
}
print "\n";

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
