<?php

// Heat meter calibration check from DHW reheats, for one system.
//
// During a cylinder reheat the heat the meter reports should match the heat
// stored in the cylinder: volume x 1.163 Wh/(L K) x the cylinder temperature
// rise. A flow meter reading a share low would show as a ratio
//   meter heat / stored heat
// that same share below the fleet's. The machine replay (feed_scan_6.php)
// can't tell a heat meter under-read from a machine or electricity-side
// fault; this check doesn't involve the electricity meter or the datasheet.
//
// Two estimates. The first takes the whole reheat: start temperature to the
// peak after it. That only works if the cylinder sensor tracks the whole
// cylinder, and often it doesn't: early in a reheat the heated zone hasn't
// reached the sensor, which reads flat while kW go in.
//
// The second is a rate method on 10 min windows, counted back from the end of
// each reheat. While the whole cylinder heats uniformly, meter heat ~ volume x
// 1.163 x rate of rise, so
//   ratio = mean heat / (volume x 1.163 x cylinder slope, K/h)
// Windows count only when the cylinder is visibly heating as a whole: running
// throughout with steady heat, no immersion, the cylinder rising at least
// WIN_MIN_SLOPE K/h with no step (a draw), and the cylinder and return
// temperatures rising together (slope ratio within WIN_PARALLEL), as the coil
// return follows a mixed cylinder. The last window of each reheat is also
// reported on its own, with the same checks except the parallel one.
// Standing losses add a few percent to the ratio during a reheat.
//
// Neither estimate is expected to be exactly 1 for a correct meter:
//   - a coil heats the water above it, so the heated volume is less than the
//     nominal one (ratio below 1)
//   - a single sensor can sit above a cold layer left by a draw (above 1)
//   - hot water drawn during the reheat, standing losses, and hot primary
//     water dumped to the heating at the end (above 1)
// so systems are compared against each other, using reheats that look clean:
//   - at least MIN_RISE K rise, MIN_DUR to MAX_DUR long, mostly running
//   - cylinder temperature never falls more than MAX_DIP K during it (a draw)
//   - no immersion energy during it or its settling window
//   - not cut short by another reheat
// The end temperature is the highest reading within POST_S after the reheat,
// for sensor lag. Night reheats (00-06 local) have fewest draws.
//
// Reheats are runs of the DHW flag (gaps under MERGE_S merged); without a
// flag feed, running with flowT above DHW_GUESS_FLOWT, as feed_scan_6.php.
// Cylinder volume comes from system_meta.cylinder_volume, or --volume.
//
// Usage: php feed_scan_8.php <system_id> [days] [--out=dir] [--volume=L]
// Output: report to stdout + dhwcal_<id>.json in --out (default ./dhwcal)

$dir = dirname(__FILE__);
$start_cwd = getcwd();
chdir($dir."/../../www");

define('EMONCMS_EXEC', 1);
require "Lib/load_database.php";
require $dir."/../common.php";

$positional = array();
$out_dir = $dir."/dhwcal";
$volume_override = 0;
foreach (array_slice($argv, 1) as $a) {
    if (strpos($a, "--out=") === 0) $out_dir = rtrim(substr($a, 6), "/");
    else if (strpos($a, "--volume=") === 0) $volume_override = (float) substr($a, 9);
    else $positional[] = $a;
}
if (!isset($positional[0])) die("usage: feed_scan_8.php <system_id> [days] [--out=dir] [--volume=L]\n");
$system_id = (int) $positional[0];
$scan_days = isset($positional[1]) ? (int) $positional[1] : 365;
if ($out_dir[0] != "/") $out_dir = $start_cwd."/".$out_dir;
if (!is_dir($out_dir) || !is_writable($out_dir)) die("output directory $out_dir is missing or not writable\n");

define('WH_PER_LK', 1.163);       // water, Wh per litre per K
define('MERGE_S', 120);           // DHW flag gaps shorter than this are one reheat (s)
define('POST_S', 1200);           // settling window after a reheat (s)
define('MIN_RISE', 8.0);          // K
define('MIN_DUR', 900);           // s
define('MAX_DUR', 3 * 3600);      // s
define('MIN_RUN_FRAC', 0.8);      // share of the reheat with the compressor running
define('MAX_DIP', 1.0);           // K fall from the running maximum during a reheat
define('MAX_POST_FALL', 2.0);     // K fall from the peak by the end of the settling window
define('IMMERSION_W', 50);        // immersion above this counts as on
define('DHW_GUESS_FLOWT', 45);
define('WIN_S', 600);             // rate method window (s)
define('WIN_MIN_SLOPE', 2.0);     // K/h
define('WIN_PARALLEL', 1.33);     // cylinder / return slope within 1/x to x
define('WIN_HEAT_CV', 0.15);      // heat sd / mean within a window
define('WIN_MAX_RESID', 0.3);     // K rms about the window's straight line
define('WIN_MIN_HEAT', 500);      // W
define('FILL_MAX_S', 900);
define('BLOCK_SIZE', 86400);
define('PHPFINA_DIR', "/var/opt/emoncms/phpfina/");

// 1. System meta and feeds
$result = $mysqli->query("SELECT app_id, hp_manufacturer, hp_model, hp_output, cylinder_volume FROM system_meta WHERE id = $system_id");
if (!$result || !($s = $result->fetch_object())) die("no system_meta row for $system_id\n");

$result = $emoncms_mysqli->query("SELECT * FROM app WHERE id = ".(int)$s->app_id);
if (!$result || !($row = $result->fetch_object())) die("no app row\n");
$app_config = json_decode($row->config);

if (isset($app_config->heatpump_dhwT_unit) && $app_config->heatpump_dhwT_unit == "%") {
    die("heatpump_dhwT is a charge in %, not a temperature\n");
}
$starting_power = 150;
if (isset($app_config->starting_power) && is_numeric($app_config->starting_power)) {
    $starting_power = (float) $app_config->starting_power;
}

$required = array("heatpump_elec", "heatpump_heat", "heatpump_dhwT");
$optional = array("heatpump_dhw", "heatpump_flowT", "heatpump_returnT", "immersion_elec");
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
$has_flag = isset($feedids["heatpump_dhw"]);
$has_imm = isset($feedids["immersion_elec"]);
$has_ret = isset($feedids["heatpump_returnT"]);
if (!$has_flag && !isset($feedids["heatpump_flowT"])) die("no DHW flag and no flowT to guess DHW from\n");
$volume = $volume_override > 0 ? $volume_override : (float) $s->cylinder_volume;
if (!($volume > 0)) die("no cylinder volume (has a heatpump_dhwT feed; --volume=L to set one)\n");

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

print "System $system_id: {$s->hp_manufacturer} {$s->hp_model} {$s->hp_output} kW, cylinder {$volume} L\n";
print "Window: ".date("Y-m-d H:i", $window_start)." to ".date("Y-m-d H:i", $window_end)
    ." (".round($npoints * 10 / 86400, 1)." days)\n";
print "DHW from ".($has_flag ? "the flag feed" : "flowT above ".DHW_GUESS_FLOWT." C (no flag feed)")
    .", immersion feed ".($has_imm ? "yes" : "no").", returnT ".($has_ret ? "yes" : "no")."\n";

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

$events = array();
$windows = array();  // rate method: [reheat start, position from end, ratio, cyl slope, ret slope, heat W, flags]
$ev = null;         // open reheat
$post = null;        // reheat in its settling window
$off_run = 0;        // samples since the DHW flag went off, while a reheat is open
$gap = null;         // what happened during that gap, added if the reheat resumes
$T_last = NAN;       // last valid cylinder temperature

$i = 0;
while ($i < $npoints) {
    $block_n = min(BLOCK_SIZE, $npoints - $i);
    $block_t0 = $window_start + $i * 10;
    $dt = new DateTime("@$block_t0");
    $dt->setTimezone($tz);
    $tz_offset = $dt->getOffset();

    $data = array();
    for ($f = 0; $f < $F; $f++) {
        $data[$f] = load_block($fh[$f], $meta[$keys[$f]], $block_t0, $block_n);
        fill_nulls($data[$f], $block_n, $fill[$f]);
    }

    for ($j = 0; $j < $block_n; $j++, $i++) {
        $t = $block_t0 + $j * 10;
        $elec = $data[$K["heatpump_elec"]][$j];
        $h = $data[$K["heatpump_heat"]][$j];
        $T = $data[$K["heatpump_dhwT"]][$j];
        $imm = $has_imm ? $data[$K["immersion_elec"]][$j] : NAN;
        $ret = $has_ret ? $data[$K["heatpump_returnT"]][$j] : NAN;
        $running = ($elec == $elec) && $elec >= $starting_power;
        if ($has_flag) {
            $v = $data[$K["heatpump_dhw"]][$j];
            $on = ($v == $v && $v != 0);
        } else {
            $flowT = $data[$K["heatpump_flowT"]][$j];
            $on = $running && $flowT == $flowT && $flowT > DHW_GUESS_FLOWT;
        }
        $imm_wh = ($imm == $imm && $imm > IMMERSION_W) ? $imm * 10 / 3600 : 0.0;
        $h_wh = ($h == $h) ? $h * 10 / 3600 : 0.0;

        // settling window of the last reheat
        if ($post !== null) {
            if ($on) {
                $post['truncated'] = true;
                $events[] = finish($post, $volume);
                $post = null;
            } else {
                if ($T == $T) {
                    $post['peak'] = max($post['peak'], $T);
                    $post['T_end'] = $T;
                }
                $post['imm_wh'] += $imm_wh;
                $post['post_n']++;
                if ($post['post_n'] * 10 >= POST_S) {
                    $events[] = finish($post, $volume);
                    $post = null;
                }
            }
        }

        if ($on) {
            if ($ev === null) {
                $hour = intdiv(($t + $tz_offset) % 86400, 3600);
                $ev = array('start' => $t, 'n' => 0, 'n_run' => 0, 'n_T' => 0, 'heat_wh' => 0.0, 'imm_wh' => 0.0,
                    'T0' => ($T_last == $T_last ? $T_last : $T), 'Tmax' => -1e9, 'dip' => 0.0,
                    'night' => $hour < 6, 'truncated' => false, 's' => array());
            } else if ($off_run > 0) {
                // the reheat resumed: the gap was part of it
                $ev['n'] += $gap['n'];
                $ev['heat_wh'] += $gap['heat_wh'];
                $ev['imm_wh'] += $gap['imm_wh'];
                foreach ($gap['s'] as $x) $ev['s'][] = $x;
                $off_run = 0;
            }
            $ev['s'][] = array($t, $h, $T, $ret, $running ? 1 : 0, $imm_wh > 0 ? 1 : 0);
            $ev['n']++;
            if ($running) $ev['n_run']++;
            $ev['heat_wh'] += $h_wh;
            $ev['imm_wh'] += $imm_wh;
            if ($T == $T) {
                $ev['n_T']++;
                $ev['Tmax'] = max($ev['Tmax'], $T);
                $ev['dip'] = max($ev['dip'], $ev['Tmax'] - $T);
            }
        } else if ($ev !== null) {
            if ($off_run == 0) $gap = array('n' => 0, 'heat_wh' => 0.0, 'imm_wh' => 0.0, 'peak' => -1e9, 'T_end' => NAN,
                's' => array());
            $gap['s'][] = array($t, $h, $T, $ret, $running ? 1 : 0, $imm_wh > 0 ? 1 : 0);
            $off_run++;
            $gap['n']++;
            $gap['heat_wh'] += $h_wh;
            $gap['imm_wh'] += $imm_wh;
            if ($T == $T) {
                $gap['peak'] = max($gap['peak'], $T);
                $gap['T_end'] = $T;
            }
            if ($off_run * 10 > MERGE_S) {
                // the reheat is over; its settling window starts at the end of the reheat
                $ev['dur'] = $ev['n'] * 10;
                $ev['peak'] = max($ev['Tmax'], $gap['peak']);
                $ev['T_end'] = $gap['T_end'];
                $ev['imm_wh'] += $gap['imm_wh'];
                $ev['post_n'] = $gap['n'];
                foreach (rate_windows($ev, $volume, $has_ret) as $w) $windows[] = $w;
                unset($ev['s']);
                $post = $ev;
                $ev = null;
                $off_run = 0;
            }
        }
        if ($T == $T) $T_last = $T;
    }
    print ".";
}
print "\n\n";
foreach ($fh as $handle) fclose($handle);

// 3. Report
$clean = array_values(array_filter($events, function($e) { return $e['clean']; }));
$night = array_values(array_filter($clean, function($e) { return $e['night']; }));
$reasons = array();
foreach ($events as $e) foreach ($e['why'] as $w) $reasons[$w] = (isset($reasons[$w]) ? $reasons[$w] : 0) + 1;

$summary = function($list) {
    $r = array_map(function($e) { return $e['ratio']; }, $list);
    sort($r);
    $n = count($r);
    if ($n == 0) return array('n' => 0, 'median' => null, 'p25' => null, 'p75' => null, 'pooled' => null);
    $q = function($p) use ($r, $n) { return $r[(int) min($n - 1, max(0, round($p * ($n - 1))))]; };
    $heat = array_sum(array_map(function($e) { return $e['heat_wh']; }, $list));
    $stored = array_sum(array_map(function($e) { return $e['stored_wh']; }, $list));
    return array('n' => $n, 'median' => $q(0.5), 'p25' => $q(0.25), 'p75' => $q(0.75),
        'pooled' => $stored > 0 ? $heat / $stored : null);
};
$all_s = $summary($clean);
$night_s = $summary($night);

print count($events)." reheats, ".count($clean)." clean (".count($night)." at night)\n";
if ($reasons) {
    arsort($reasons);
    print "left out: ";
    foreach ($reasons as $w => $n) print "$w $n  ";
    print "\n";
}
print "\nmeter heat / stored heat (volume x 1.163 x rise)\n";
$tab = array();
foreach (array("clean" => $all_s, "night" => $night_s) as $label => $x) {
    $tab[] = array($label, $x['n'], fmt($x['median'], 3), fmt($x['p25'], 3)." to ".fmt($x['p75'], 3), fmt($x['pooled'], 3));
}
print_table($tab, array("reheats", "n", "median", "IQR", "pooled"));

if ($clean) {
    $rise = array_map(function($e) { return $e['rise']; }, $clean);
    $dur = array_map(function($e) { return $e['dur'] / 60; }, $clean);
    sort($rise); sort($dur);
    printf("\nclean reheats: median rise %.1f K, median length %.0f min\n",
        $rise[intdiv(count($rise), 2)], $dur[intdiv(count($dur), 2)]);
}

// Rate method
$wsum = function($list) {
    $r = array_map(function($w) { return $w[2]; }, $list);
    sort($r);
    $n = count($r);
    if ($n == 0) return array('n' => 0, 'median' => null, 'p25' => null, 'p75' => null);
    $q = function($p) use ($r, $n) { return $r[(int) min($n - 1, max(0, round($p * ($n - 1))))]; };
    return array('n' => $n, 'median' => $q(0.5), 'p25' => $q(0.25), 'p75' => $q(0.75));
};
$w_basic = array_values(array_filter($windows, function($w) { return $w[6]['basic']; }));
$w_par = array_values(array_filter($w_basic, function($w) { return $w[6]['parallel']; }));
$w_last = array_values(array_filter($w_basic, function($w) { return $w[1] == 0; }));
$rate = array('parallel' => $wsum($w_par), 'last' => $wsum($w_last), 'basic' => $wsum($w_basic),
    'reheats_with_parallel' => count(array_unique(array_map(function($w) { return $w[0]; }, $w_par))));

print "\nRate method, 10 min windows: meter heat / (volume x 1.163 x cylinder slope)\n";
$tab = array();
foreach (array("cylinder and return rising together" => $rate['parallel'], "last 10 min of each reheat" => $rate['last'],
        "all heating windows" => $rate['basic']) as $label => $x) {
    $tab[] = array($label, $x['n'], fmt($x['median'], 3), fmt($x['p25'], 3)." to ".fmt($x['p75'], 3));
}
print_table($tab, array("windows", "n", "median", "IQR"));
print "{$rate['reheats_with_parallel']} reheats with at least one window where cylinder and return rise together\n";
print "\nBy position from the end of the reheat (heating windows)\n";
$tab = array();
for ($k = 0; $k < 6; $k++) {
    $sel = array_values(array_filter($w_basic, function($w) use ($k) { return $w[1] == $k; }));
    $par = array_values(array_filter($sel, function($w) { return $w[6]['parallel']; }));
    $x = $wsum($sel);
    $tab[] = array(($k * 10)."-".(($k + 1) * 10)." min before end", $x['n'], fmt($x['median'], 3), count($par));
}
print_table($tab, array("window", "n", "median ratio", "n parallel"));

$out = array(
    'system_id' => $system_id, 'hp_model' => $s->hp_model, 'qnom_kw' => (float) $s->hp_output,
    'cylinder_volume' => $volume, 'has_flag' => $has_flag, 'has_immersion' => $has_imm,
    'window_start' => $window_start, 'window_end' => $window_end, 'days' => $npoints * 10 / 86400,
    'n_events' => count($events), 'left_out' => $reasons, 'clean' => $all_s, 'night' => $night_s,
    'has_returnT' => $has_ret, 'rate' => $rate,
    'windows' => array_map(function($w) {
        return array($w[0], $w[1], round($w[2], 4), round($w[3], 3), $w[4] === null ? null : round($w[4], 3),
            round($w[5], 0), $w[6]['basic'] ? 1 : 0, $w[6]['parallel'] ? 1 : 0);
    }, $windows),
    'window_fields' => array('reheat_start', 'pos_from_end', 'ratio', 'cyl_slope_kh', 'ret_slope_kh', 'heat_w',
        'basic', 'parallel'),
    'events' => array_map(function($e) {
        return array($e['start'], $e['dur'], round($e['T0'], 2), round($e['peak'], 2), round($e['heat_wh'], 1),
            $e['ratio'] === null ? null : round($e['ratio'], 4), $e['night'] ? 1 : 0, $e['clean'] ? 1 : 0);
    }, $events),
    'event_fields' => array('start', 'dur_s', 'T0', 'T1', 'heat_wh', 'ratio', 'night', 'clean'),
);
$jsonfile = $out_dir."/dhwcal_".$system_id.".json";
if (file_put_contents($jsonfile, json_encode($out)) === false) die("ERROR: could not write $jsonfile\n");
print "Written $jsonfile\n";

// ---------------------------------------------------------------------------

// Close a reheat: stored heat, ratio and the reasons it isn't clean
function finish($e, $volume)
{
    $e['rise'] = $e['peak'] - $e['T0'];
    $e['stored_wh'] = $volume * WH_PER_LK * $e['rise'];
    $e['ratio'] = $e['stored_wh'] > 0 ? $e['heat_wh'] / $e['stored_wh'] : null;
    $why = array();
    if (!($e['T0'] == $e['T0']) || $e['n_T'] < 0.9 * $e['n']) $why[] = "no_temp";
    else if ($e['rise'] < MIN_RISE) $why[] = "small_rise";
    if ($e['dur'] < MIN_DUR) $why[] = "short";
    if ($e['dur'] > MAX_DUR) $why[] = "long";
    if ($e['n_run'] < MIN_RUN_FRAC * $e['n']) $why[] = "not_running";
    if ($e['dip'] > MAX_DIP) $why[] = "draw_during";
    if (($e['T_end'] == $e['T_end']) && $e['peak'] - $e['T_end'] > MAX_POST_FALL) $why[] = "draw_after";
    if ($e['imm_wh'] > 10) $why[] = "immersion";
    if ($e['truncated']) $why[] = "truncated";
    $e['why'] = $why;
    $e['clean'] = count($why) == 0 && $e['ratio'] !== null;
    return $e;
}

// Rate method: 10 min windows counted back from the end of a reheat
function rate_windows($e, $volume, $has_ret)
{
    $out = array();
    $S = $e['s'];
    $per = intdiv(WIN_S, 10);
    for ($end = count($S), $k = 0; $end - $per >= 0; $end -= $per, $k++) {
        $seg = array_slice($S, $end - $per, $per);
        $t0 = $seg[0][0];
        $hs = array(); $tx = array(); $ty = array(); $rx = array(); $ry = array();
        $run = 0; $imm = 0;
        foreach ($seg as $x) {
            if ($x[1] == $x[1]) $hs[] = $x[1];
            if ($x[2] == $x[2]) { $tx[] = ($x[0] - $t0) / 3600; $ty[] = $x[2]; }
            if ($x[3] == $x[3]) { $rx[] = ($x[0] - $t0) / 3600; $ry[] = $x[3]; }
            $run += $x[4];
            $imm += $x[5];
        }
        if (count($hs) < 0.9 * $per || count($ty) < 0.9 * $per) continue;
        $hm = array_sum($hs) / count($hs);
        $hsd = 0.0;
        foreach ($hs as $v) $hsd += ($v - $hm) * ($v - $hm);
        $hsd = sqrt($hsd / count($hs));
        list($cs, $cres) = line_fit($tx, $ty);
        if ($cs <= 0) continue;
        $rs = ($has_ret && count($ry) >= 0.9 * $per) ? line_fit($rx, $ry)[0] : null;
        $basic = $run == $per && $imm == 0 && $hm >= WIN_MIN_HEAT && $hsd <= WIN_HEAT_CV * $hm
            && $cs >= WIN_MIN_SLOPE && $cres <= WIN_MAX_RESID;
        $parallel = $basic && $rs !== null && $rs > 0
            && $cs / $rs <= WIN_PARALLEL && $rs / $cs <= WIN_PARALLEL;
        $out[] = array($e['start'], $k, $hm / ($volume * WH_PER_LK * $cs), $cs, $rs, $hm,
            array('basic' => $basic, 'parallel' => $parallel));
    }
    return $out;
}

// Least squares slope and rms residual of y on x
function line_fit($x, $y)
{
    $n = count($x);
    $mx = array_sum($x) / $n;
    $my = array_sum($y) / $n;
    $sxx = 0.0;
    $sxy = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $sxx += ($x[$i] - $mx) * ($x[$i] - $mx);
        $sxy += ($x[$i] - $mx) * ($y[$i] - $my);
    }
    if ($sxx <= 0) return array(0.0, 0.0);
    $b = $sxy / $sxx;
    $ss = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $r = $y[$i] - $my - $b * ($x[$i] - $mx);
        $ss += $r * $r;
    }
    return array($b, sqrt($ss / $n));
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
