<?php

// Heat meter dT offset from pump-only running, for one system.
//
// With the compressor off and the pump circulating, the flow-return dT the
// meter sees should be close to zero, so what it reads is mostly the offset
// between its two temperature sensors. A small offset matters at heat pump
// dTs: 0.1 K at a 2.7 K running dT is a 4% heat error. This scan measures it
// so it can be compared with the replay level (feed_scan_6.php) and with the
// offset that would explain each system's gap to the datasheet.
//
// The true dT isn't exactly zero while the pump runs:
//   - the water is cooling, and flow lags return through the outdoor unit,
//     so flow reads warm by roughly the lag x the cooling rate
//   - the outdoor unit and pipes lose heat, so flow reads cool by an amount
//     that grows with water minus outside temperature
//   - the pump adds a little heat
// so the offset is taken as the intercept of
//   dT = offset + a x (water - outside) + b x (water temperature change, K/min)
// over pump-only samples at least SETTLE_S after the compressor stops. The
// median dT of quiet samples (late, little drift) is a model-free check.
//
// The flowT and returnT feeds are only the meter's own sensors if heat ~ k x
// flow x dT while running, so the scan also reports k from steady running
// (about 69.7 W per L/min per K for water, a bit less with glycol).
//
// Sample handling follows feed_scan_6.php (nulls forward-filled across gaps
// under 15 min, running means elec >= starting_power).
//
// Usage: php feed_scan_7.php <system_id> [days] [--out=dir]
// Output: report to stdout + pumponly_<id>.json in --out (default ./pumponly)

$dir = dirname(__FILE__);
$start_cwd = getcwd();
chdir($dir."/../../www");

define('EMONCMS_EXEC', 1);
require "Lib/load_database.php";
require $dir."/../common.php";

$positional = array();
$out_dir = $dir."/pumponly";
foreach (array_slice($argv, 1) as $a) {
    if (strpos($a, "--out=") === 0) $out_dir = rtrim(substr($a, 6), "/");
    else $positional[] = $a;
}
if (!isset($positional[0])) die("usage: feed_scan_7.php <system_id> [days] [--out=dir]\n");
$system_id = (int) $positional[0];
$scan_days = isset($positional[1]) ? (int) $positional[1] : 365;
if ($out_dir[0] != "/") $out_dir = $start_cwd."/".$out_dir;
if (!is_dir($out_dir) || !is_writable($out_dir)) die("output directory $out_dir is missing or not writable\n");

define('SETTLE_S', 600);         // pump-only samples count from this long after a stop (s)
define('STEADY_S', 600);         // running samples for k count from this long after a start (s)
define('FLOW_MIN', 2.0);         // L/min, below this the pump is taken as off
define('DRIFT_S', 300);          // water temperature change measured over this (s)
define('QUIET_S', 1200);         // quiet samples: at least this long after a stop (s)
define('QUIET_DRIFT', 0.02);     // and water temperature changing less than this (K/min)
define('HIST_MIN', -2.0);        // dT histogram for the quiet median, K
define('HIST_STEP', 0.01);
define('HIST_N', 401);
define('FILL_MAX_S', 900);
define('BLOCK_SIZE', 86400);
define('PHPFINA_DIR', "/var/opt/emoncms/phpfina/");

$STOP_EDGES = array(10, 20, 40, 90, 99999);   // minutes since the compressor stopped

// 1. System meta and feeds
$result = $mysqli->query("SELECT app_id, hp_manufacturer, hp_model, hp_output FROM system_meta WHERE id = $system_id");
if (!$result || !($s = $result->fetch_object())) die("no system_meta row for $system_id\n");

$result = $emoncms_mysqli->query("SELECT * FROM app WHERE id = ".(int)$s->app_id);
if (!$result || !($row = $result->fetch_object())) die("no app row\n");
$app_config = json_decode($row->config);

$starting_power = 150;
if (isset($app_config->starting_power) && is_numeric($app_config->starting_power)) {
    $starting_power = (float) $app_config->starting_power;
}

$required = array("heatpump_elec", "heatpump_heat", "heatpump_flowT", "heatpump_returnT",
    "heatpump_outsideT", "heatpump_flowrate");
$optional = array("heatpump_dhw");
$feedids = array();
foreach (array_merge($required, $optional) as $key) {
    if (isset($app_config->$key)) {
        $feedid = (int) $app_config->$key;
        $result = $emoncms_mysqli->query("SELECT id FROM feeds WHERE id = $feedid AND engine = 5");
        if ($result->num_rows > 0) $feedids[$key] = $feedid;
    }
}
foreach ($required as $key) {
    if (!isset($feedids[$key])) die("missing $key feed\n");
}
$has_dhw = isset($feedids["heatpump_dhw"]);

$meta = array();
foreach ($feedids as $key => $feedid) {
    $m = getmeta(PHPFINA_DIR, $feedid);
    if ($m === false || $m->npoints <= 0) die("missing or empty $key (feed $feedid)\n");
    $meta[$key] = $m;
}

// Window as feed_scan_6.php
$tz = new DateTimeZone('Europe/London');
$date = new DateTime();
$date->setTimezone($tz);
$date->modify("midnight");
$window_end = $date->getTimestamp() + 3600*24;
$date->setTimestamp($window_end);
$date->modify("-$scan_days days");
$window_start = $date->getTimestamp();
foreach ($required as $key) {
    $window_start = max($window_start, $meta[$key]->start_time);
    $window_end = min($window_end, $meta[$key]->end_time);
}
$window_start -= ($window_start % 10);
$npoints = intdiv($window_end - $window_start, 10);
if ($npoints <= 0) die("no feed data within the window\n");

print "System $system_id: {$s->hp_manufacturer} {$s->hp_model} {$s->hp_output} kW\n";
print "Window: ".date("Y-m-d H:i", $window_start)." to ".date("Y-m-d H:i", $window_end)
    ." (".round($npoints * 10 / 86400, 1)." days)\n";

// 2. Scan
$keys = array_keys($feedids);
$F = count($keys);
$K = array_flip($keys);
$fh = array();
$fill = array();
foreach ($keys as $f => $key) {
    $fh[$f] = fopen(PHPFINA_DIR.$feedids[$key].".dat", 'rb');
    $fill[$f] = array('value' => NAN, 'gap' => 0);
}

$NB = count($STOP_EDGES) - 1;
$bins = array();
for ($b = 0; $b < $NB; $b++) $bins[$b] = new_bin();
$all = new_bin();
$XtX = array_fill(0, 3, array_fill(0, 3, 0.0));
$Xty = array_fill(0, 3, 0.0);
$hist = array_fill(0, HIST_N, 0);
$quiet = new_bin();
$run = array('n' => 0, 'h' => 0.0, 'fdT' => 0.0, 'dT' => 0.0, 'flow' => 0.0);

$NDRIFT = intdiv(DRIFT_S, 10);
$ring = array_fill(0, $NDRIFT, NAN);   // water temperature DRIFT_S ago
$running = null;
$since_stop = -1;      // s since the compressor stopped, -1 until the first stop
$since_start = 1e9;

$i = 0;
while ($i < $npoints) {
    $block_n = min(BLOCK_SIZE, $npoints - $i);
    $block_t0 = $window_start + $i * 10;

    $data = array();
    for ($f = 0; $f < $F; $f++) {
        $data[$f] = load_block($fh[$f], $meta[$keys[$f]], $block_t0, $block_n);
        fill_nulls($data[$f], $block_n, $fill[$f]);
    }

    for ($j = 0; $j < $block_n; $j++, $i++) {
        $elec = $data[$K["heatpump_elec"]][$j];
        $h = $data[$K["heatpump_heat"]][$j];
        $flowT = $data[$K["heatpump_flowT"]][$j];
        $returnT = $data[$K["heatpump_returnT"]][$j];
        $outsideT = $data[$K["heatpump_outsideT"]][$j];
        $flow = $data[$K["heatpump_flowrate"]][$j];
        $dhw = false;
        if ($has_dhw) {
            $v = $data[$K["heatpump_dhw"]][$j];
            $dhw = ($v == $v && $v != 0);
        }

        $water = ($flowT + $returnT) / 2;
        $slot = $i % $NDRIFT;
        $water_then = $ring[$slot];
        $ring[$slot] = $water;
        $drift = ($water == $water && $water_then == $water_then) ? ($water - $water_then) / (DRIFT_S / 60) : NAN;

        if ($since_stop >= 0) $since_stop += 10;
        $since_start += 10;

        if (!($elec == $elec)) continue;
        $now_running = $elec >= $starting_power;
        if ($running === true && !$now_running) $since_stop = 0;
        if ($running === false && $now_running) $since_start = 0;
        $running = $now_running;

        if (!($flowT == $flowT && $returnT == $returnT && $flow == $flow && $h == $h)) continue;
        $dT = $flowT - $returnT;

        // steady running: is heat ~ k x flow x dT, i.e. are these the meter's sensors?
        if ($now_running && !$dhw && $since_start >= STEADY_S && $h > 0 && $flow >= FLOW_MIN) {
            $run['n']++;
            $run['h'] += $h;
            $run['fdT'] += $flow * $dT;
            $run['dT'] += $dT;
            $run['flow'] += $flow;
            continue;
        }

        // pump-only: compressor off long enough, pump circulating, not DHW
        if ($now_running || $dhw || $since_stop < SETTLE_S || $flow < FLOW_MIN) continue;
        if (!($outsideT == $outsideT && $drift == $drift)) continue;

        $x1 = $water - $outsideT;
        add_bin($bins[bin_index($since_stop / 60, $STOP_EDGES)], $dT, $flow, $h, $x1, $drift, $elec);
        add_bin($all, $dT, $flow, $h, $x1, $drift, $elec);
        $x = array(1.0, $x1, $drift);
        for ($p = 0; $p < 3; $p++) {
            for ($q = 0; $q < 3; $q++) $XtX[$p][$q] += $x[$p] * $x[$q];
            $Xty[$p] += $x[$p] * $dT;
        }
        if ($since_stop >= QUIET_S && abs($drift) < QUIET_DRIFT) {
            add_bin($quiet, $dT, $flow, $h, $x1, $drift, $elec);
            $k = (int) round(($dT - HIST_MIN) / HIST_STEP);
            $hist[max(0, min(HIST_N - 1, $k))]++;
        }
    }
    print ".";
}
print "\n\n";
foreach ($fh as $handle) fclose($handle);

// 3. Report
$k_run = $run['fdT'] > 0 ? $run['h'] / $run['fdT'] : null;
$run_dT = $run['n'] ? $run['dT'] / $run['n'] : null;
$run_flow = $run['n'] ? $run['flow'] / $run['n'] : null;
print "Steady running: ".fmt($run['n'] * 10 / 3600, 0)." h, mean dT ".fmt($run_dT, 2)." K, flow ".fmt($run_flow, 1)
    .", k = heat / (flow x dT) ".fmt($k_run, 1)." (water in L/min: 69.7)\n\n";

$coef = solve3($XtX, $Xty);
$quiet_median = hist_median($hist, HIST_MIN, HIST_STEP);

print "Pump-only (compressor off ".(SETTLE_S / 60)."+ min, flow >= ".FLOW_MIN.", not DHW)\n";
$tab = array();
foreach ($bins as $b => $c) {
    $tab[] = bin_row($STOP_EDGES[$b]."-".($b == $NB - 1 ? "" : $STOP_EDGES[$b + 1])." min", $c, $k_run);
}
$tab[] = bin_row("all", $all, $k_run);
$tab[] = bin_row("quiet", $quiet, $k_run);
print_table($tab, array("since stop", "hours", "mean dT", "sd dT", "flow", "heat W", "k x flow x dT W",
    "water-out", "drift K/min", "elec W"));
print "quiet: ".(QUIET_S / 60)."+ min after a stop, water changing under ".QUIET_DRIFT." K/min\n\n";

if ($coef !== null) {
    printf("Fit: dT = %+.3f K %+.4f x (water - outside) %+.3f x drift (K/min)\n", $coef[0], $coef[1], $coef[2]);
}
print "Quiet median dT: ".($quiet_median === null ? "--" : sprintf("%+.3f K", $quiet_median))."\n";
if ($coef !== null && $run_dT) {
    printf("Offset as a share of the running dT: %+.1f%% (meter heat error if the offset carries into running)\n",
        100 * $coef[0] / $run_dT);
}

$out = array(
    'system_id' => $system_id, 'hp_model' => $s->hp_model, 'qnom_kw' => (float) $s->hp_output,
    'window_start' => $window_start, 'window_end' => $window_end, 'days' => $npoints * 10 / 86400,
    'starting_power' => $starting_power, 'has_dhw' => $has_dhw,
    'run' => array('hours' => $run['n'] * 10 / 3600, 'dT' => $run_dT, 'flow' => $run_flow, 'k' => $k_run),
    'stop_edges' => $STOP_EDGES, 'bins' => $bins, 'all' => $all, 'quiet' => $quiet,
    'fit' => $coef === null ? null : array('offset' => $coef[0], 'loss' => $coef[1], 'drift' => $coef[2]),
    'quiet_median' => $quiet_median, 'hist_min' => HIST_MIN, 'hist_step' => HIST_STEP, 'hist' => $hist,
);
$jsonfile = $out_dir."/pumponly_".$system_id.".json";
if (file_put_contents($jsonfile, json_encode($out)) === false) die("ERROR: could not write $jsonfile\n");
print "Written $jsonfile\n";

// ---------------------------------------------------------------------------

function new_bin()
{
    return array('n' => 0, 'dT' => 0.0, 'dT2' => 0.0, 'flow' => 0.0, 'h' => 0.0, 'fdT' => 0.0,
        'hzero' => 0, 'x1' => 0.0, 'drift' => 0.0, 'e' => 0.0);
}

function add_bin(&$c, $dT, $flow, $h, $x1, $drift, $e)
{
    $c['n']++;
    $c['dT'] += $dT;
    $c['dT2'] += $dT * $dT;
    $c['flow'] += $flow;
    $c['h'] += $h;
    $c['fdT'] += $flow * $dT;
    if ($h == 0) $c['hzero']++;
    $c['x1'] += $x1;
    $c['drift'] += $drift;
    $c['e'] += $e;
}

function bin_row($label, $c, $k)
{
    $n = $c['n'];
    if ($n == 0) return array($label, "0", "", "", "", "", "", "", "", "");
    $m = $c['dT'] / $n;
    $sd = sqrt(max(0, $c['dT2'] / $n - $m * $m));
    return array($label, fmt($n * 10 / 3600, 0), sprintf("%+.3f", $m), fmt($sd, 3), fmt($c['flow'] / $n, 1),
        fmt($c['h'] / $n, 0), $k === null ? "--" : fmt($k * $c['fdT'] / $n, 0),
        fmt($c['x1'] / $n, 1), sprintf("%+.3f", $c['drift'] / $n), fmt($c['e'] / $n, 0));
}

// Solve a 3x3 system by Gaussian elimination with partial pivoting
function solve3($A, $b)
{
    $n = 3;
    for ($c = 0; $c < $n; $c++) {
        $p = $c;
        for ($r = $c + 1; $r < $n; $r++) if (abs($A[$r][$c]) > abs($A[$p][$c])) $p = $r;
        if (abs($A[$p][$c]) < 1e-9) return null;
        list($A[$c], $A[$p]) = array($A[$p], $A[$c]);
        list($b[$c], $b[$p]) = array($b[$p], $b[$c]);
        for ($r = $c + 1; $r < $n; $r++) {
            $f = $A[$r][$c] / $A[$c][$c];
            for ($q = $c; $q < $n; $q++) $A[$r][$q] -= $f * $A[$c][$q];
            $b[$r] -= $f * $b[$c];
        }
    }
    $x = array_fill(0, $n, 0.0);
    for ($r = $n - 1; $r >= 0; $r--) {
        $s = $b[$r];
        for ($q = $r + 1; $q < $n; $q++) $s -= $A[$r][$q] * $x[$q];
        $x[$r] = $s / $A[$r][$r];
    }
    return $x;
}

function hist_median($hist, $min, $step)
{
    $total = array_sum($hist);
    if ($total == 0) return null;
    $acc = 0;
    foreach ($hist as $k => $n) {
        $acc += $n;
        if ($acc >= $total / 2) return $min + $k * $step;
    }
    return null;
}

function bin_index($x, $edges)
{
    $n = count($edges) - 1;
    for ($b = 0; $b < $n; $b++) {
        if ($x < $edges[$b + 1]) return $b;
    }
    return $n - 1;
}

function fmt($x, $dp) { return $x === null ? "--" : number_format((float) $x, $dp, '.', ''); }

// Forward-fill NANs with the last valid value across gaps shorter than
// FILL_MAX_S (as feed_scan_6.php)
function fill_nulls(&$data, $n, &$state)
{
    for ($j = 0; $j < $n; $j++) {
        if ($data[$j] == $data[$j]) {
            if ($state['gap'] > 0 && ($state['gap'] + 1) * 10 < FILL_MAX_S
                && $state['value'] == $state['value']) {
                $fill_start = $j - $state['gap'];
                if ($fill_start < 0) $fill_start = 0;
                for ($k = $fill_start; $k < $j; $k++) $data[$k] = $state['value'];
            }
            $state['value'] = $data[$j];
            $state['gap'] = 0;
        } else {
            $state['gap']++;
        }
    }
}

// Read a block of $n 10s-aligned samples starting at $t0 from a phpfina feed
// (as feed_scan_6.php). Other intervals are resampled by repetition; missing is NAN.
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

    if ($interval == 10 && ($t0 - $meta->start_time) % 10 == 0 && $t0 >= $meta->start_time) {
        $out = array_values($native);
        for ($j = count($out); $j < $n; $j++) $out[$j] = NAN;
        return $out;
    }

    $out = array();
    for ($j = 0; $j < $n; $j++) {
        $t = $t0 + $j * 10;
        if ($t < $meta->start_time) { $out[$j] = NAN; continue; }
        $idx = intdiv($t - $meta->start_time, $interval) - $p0 + 1;
        $out[$j] = isset($native[$idx]) ? $native[$idx] : NAN;
    }
    return $out;
}

function getmeta($dir, $id)
{
    if (!file_exists($dir . $id . ".meta")) return false;

    $meta = new stdClass();
    $metafile = fopen($dir . $id . ".meta", 'rb');
    fseek($metafile, 8);
    $tmp = unpack("I", fread($metafile, 4));
    $meta->interval = $tmp[1];
    $tmp = unpack("I", fread($metafile, 4));
    $meta->start_time = $tmp[1];
    fclose($metafile);

    clearstatcache(true, $dir . $id . ".dat");
    $meta->npoints = floor(filesize($dir . $id . ".dat") / 4.0);
    $meta->end_time = $meta->start_time + ($meta->npoints * $meta->interval);
    return $meta;
}
