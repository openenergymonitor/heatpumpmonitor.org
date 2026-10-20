<?php

// Low modulation diagnostics: what steady space heating looks like, minute by
// minute, in each inferred compressor speed band.
//
// The machine replay (feed_scan_6.php) found that some systems (269, 516, 741)
// are close to their peers at 50-60 rps but need 15-23% more electricity for
// the same heat in the band just above minimum speed. This scan asks whether
// that band is steady running or the compressor hunting around its lower limit.
// States, speed inference and the v2 datasheet fit are as feed_scan_6.php, so
// the band totals match the replay's map.
//
// For each steady sample (10 s) in a speed band it looks at a centred window of
// WINDOW_S and reports, by band:
//
//   settledness  elec coefficient of variation over the window: settled (under
//                CV_SETTLED), wobbling, or unsettled (CV_UNSETTLED or more);
//                "edge" where the window reaches outside steady or startup
//   trend        elec in the window's second half against its first half:
//                falling, flat or rising (TREND_FRAC)
//   lag          field/model with heat taken LAG seconds after the elec, to see
//                whether heat trails elec in that band
//   episodes     how long each stay in the band lasts, and band changes per
//                steady hour (hunting shows as short stays and many changes)
//   drift        flowT change across the window (K/h), flow rate, flow-return dT,
//                and field/model by drift class: falling, flat, warming, fast rise
//   near DHW     field/model for samples with a DHW sample within DHW_NEAR_S
//                after them (a reheat the flag or guess only catches later),
//                before them (still settling after one), or neither
//   clean        settled, flat flowT and not near DHW: the steadiest running,
//                to compare systems on
//
// It also lists the days with most steady hours at 30-50 rps and their
// field/model, to pick days for --dump, which writes every 10 s sample of the
// given local days to CSV for plotting.
//
// Output: report to stdout + lowmod_<id>.json (and dump_<id>_<date>.csv).
//
// Usage: php feed_scan_9.php [system_id] [days] [--qnom=kW] [--no-dhw-guess] [--out=dir]
//        [--dump=YYYY-MM-DD[,YYYY-MM-DD...]]
//   (lowmod_fleet.sh runs the Arotherm+ fleet)

$dir = dirname(__FILE__);
$start_cwd = getcwd();
chdir($dir."/../../www");

define('EMONCMS_EXEC', 1);
require "Lib/load_database.php";
require $dir."/../common.php";

$positional = array();
$qnom_override = null;
$dhw_guess = true;
$out_dir = $dir;
$dump_days = array();
foreach (array_slice($argv, 1) as $a) {
    if (strpos($a, "--qnom=") === 0) $qnom_override = (float) substr($a, 7);
    else if ($a == "--no-dhw-guess") $dhw_guess = false;
    else if (strpos($a, "--out=") === 0) $out_dir = rtrim(substr($a, 6), "/");
    else if (strpos($a, "--dump=") === 0) $dump_days = explode(",", substr($a, 7));
    else $positional[] = $a;
}
$system_id = isset($positional[0]) ? (int) $positional[0] : 269;
$scan_days = isset($positional[1]) ? (int) $positional[1] : 365;
if ($out_dir[0] != "/") $out_dir = $start_cwd."/".$out_dir;
if (!is_dir($out_dir) || !is_writable($out_dir)) die("output directory $out_dir is missing or not writable\n");

define('TRANSIENT_S', 600);
define('RPS_BEYOND', 130);
define('DHW_GUESS_FLOWT', 45);
define('RECOVERY_S', 600);
define('DEFROST_MERGE_S', 60);
define('DEFROST_MIN_WH', 50);
define('BLOCK_SIZE', 86400);
define('FILL_MAX_S', 900);
define('PHPFINA_DIR', "/var/opt/emoncms/phpfina/");

define('HALF_W', 15);              // samples either side: 31 samples, about 5 min
define('CV_SETTLED', 0.03);
define('CV_UNSETTLED', 0.08);
define('TREND_FRAC', 0.04);        // second-half elec vs first-half, for falling / rising
define('TOP_DAYS', 8);
define('DHW_NEAR_S', 1800);        // a DHW sample this close counts as near DHW

$RPS_EDGES = array(0, 30, 40, 50, 60, 70, 999);
$BAND_LABELS = array("0-30", "30-40", "40-50", "50-60", "60-70", ">70");
$NB = count($BAND_LABELS);
$SET_LABELS = array("settled", "wobbling", "unsettled", "edge");
$TREND_LABELS = array("falling", "flat", "rising");
$LAGS = array(-120, -60, -30, 0, 30, 60, 120, 180);   // s, heat taken this long after elec
$EP_EDGES = array(0, 1, 5, 15, 60, 99999);            // episode length, minutes
$EP_LABELS = array("<1", "1-5", "5-15", "15-60", ">60");
$DRIFT_EDGES = array(-999, -2, 2, 8, 999);            // flowT change across the window, K/h
$DRIFT_LABELS = array("falling", "flat", "warming", "fast rise");
$NEAR_LABELS = array("DHW after", "DHW before", "neither");

// state codes for the per-block arrays
$STATES = array("steady", "startup", "dhw", "defrost", "post_dhw", "beyond", "off", "unclass");
$S = array_flip($STATES);

// 0. The PHP port must match the JS fit (reference values as feed_scan_6.php)
foreach (array(array(5, 35, 7, 2.5, 4.652104, 34.3901), array(7, 40, 0, 6.5, 3.304047, 79.3765)) as $p) {
    if (abs(cop_fit_v2($p[0], $p[1], $p[2], $p[3]) - $p[4]) > 1e-5 || abs(estimate_speed($p[0], $p[1], $p[2], $p[3]) - $p[5]) > 1e-3) {
        die("COP fit parity check failed at ".implode(",", $p)."\n");
    }
}

// 1. System meta and feeds (as feed_scan_6.php, plus the flow rate if present)
$result = $mysqli->query("SELECT app_id, hp_manufacturer, hp_model, hp_output FROM system_meta WHERE id = $system_id");
if (!$result || !($s = $result->fetch_object())) die("no system_meta row for $system_id\n");
$qnom = $qnom_override > 0 ? $qnom_override : (float) $s->hp_output;
if (!($qnom > 0)) die("system $system_id has no rated output\n");

$result = $emoncms_mysqli->query("SELECT * FROM app WHERE id = ".(int)$s->app_id);
if (!$result || !($row = $result->fetch_object())) die("no app row\n");
$app_config = json_decode($row->config);

$starting_power = 150;
if (isset($app_config->starting_power) && is_numeric($app_config->starting_power)) {
    $starting_power = (float) $app_config->starting_power;
}

$required = array("heatpump_elec", "heatpump_heat", "heatpump_flowT", "heatpump_returnT", "heatpump_outsideT");
$optional = array("heatpump_dhw", "heatpump_flowrate");
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
$has_flow = isset($feedids["heatpump_flowrate"]);

$meta = array();
foreach ($feedids as $key => $feedid) {
    $m = getmeta(PHPFINA_DIR, $feedid);
    if ($m === false || $m->npoints <= 0) die("missing or empty $key (feed $feedid)\n");
    $meta[$key] = $m;
}

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

print "System $system_id: {$s->hp_manufacturer} {$s->hp_model} {$s->hp_output} kW"
    .($qnom_override > 0 ? ", fit run as $qnom kW" : "")."\n";
print "Window: ".date("Y-m-d H:i", $window_start)." to ".date("Y-m-d H:i", $window_end)
    ." (".round($npoints * 10 / 86400, 1)." days)\n";
print "Starting power $starting_power W, DHW flag ".($has_dhw ? "feed ".$feedids["heatpump_dhw"] : "not available")
    .", flow rate ".($has_flow ? "feed ".$feedids["heatpump_flowrate"] : "not available")."\n";
if (!$has_dhw && $dhw_guess) print "WARNING: no DHW flag, so DHW is guessed as flowT above ".DHW_GUESS_FLOWT." C\n";

// 2. Accumulators
function new_acc2()
{
    return array('n' => 0, 'h' => 0.0, 'e' => 0.0, 'em' => 0.0);
}
function add2(&$a, $h, $e, $em)
{
    $a['n']++;
    $a['h'] += $h;
    $a['e'] += $e;
    $a['em'] += $em;
}

$band_acc = array();
$set_acc = array();
$trend_acc = array();
$lag_acc = array();     // [band][lag]: h shifted, e, em
$ep_hours = array();    // [band][episode bin]: hours
$drift_acc = array();   // [band][drift class], with flowT and flow sums
$near_acc = array();    // [band][near DHW class]
$clean_acc = array();   // [band]: settled, flat flowT, not near DHW
$drift = array();       // [band]: sums for flowT drift, flow, dT
for ($b = 0; $b < $NB; $b++) {
    $band_acc[$b] = new_acc2();
    foreach ($SET_LABELS as $k => $unused) $set_acc[$b][$k] = new_acc2();
    foreach ($TREND_LABELS as $k => $unused) $trend_acc[$b][$k] = new_acc2();
    foreach ($LAGS as $k => $unused) $lag_acc[$b][$k] = new_acc2();
    foreach ($EP_LABELS as $k => $unused) $ep_hours[$b][$k] = 0.0;
    foreach ($DRIFT_LABELS as $k => $unused) $drift_acc[$b][$k] = new_acc2() + array('flowT' => 0.0, 'flow' => 0.0, 'nflow' => 0);
    foreach ($NEAR_LABELS as $k => $unused) $near_acc[$b][$k] = new_acc2();
    $clean_acc[$b] = new_acc2();
    $drift[$b] = array('n' => 0, 'drift' => 0.0, 'nflow' => 0, 'flow' => 0.0, 'ndT' => 0, 'dT' => 0.0, 'cv' => array());
}
$band_changes = 0;
$days = array();        // local day number => low band and min band accs

$keys = array_keys($feedids);
$F = count($keys);
$K = array_flip($keys);
$fh = array();
$fill = array();
foreach ($keys as $f => $key) {
    $fh[$f] = fopen(PHPFINA_DIR.$feedids[$key].".dat", 'rb');
    $fill[$f] = array('value' => NAN, 'gap' => 0);
}

$dump_set = array();
foreach ($dump_days as $d) {
    $dt = new DateTime($d, $tz);
    $dump_set[intdiv($dt->getTimestamp() + $dt->getOffset(), 86400)] = $d;
}
$dump_fh = array();

$running = null;
$since_start = 1e9;
$since_dhw_end = 1e9;
$since_defrost = 1e9;
$since_neg = 1e9;
$neg = null;
$dhw_prev = false;
$ep_band = -1;          // band of the current episode, -1 none
$ep_len = 0;            // samples

$i = 0;
while ($i < $npoints) {
    $n = min(BLOCK_SIZE, $npoints - $i);
    $block_t0 = $window_start + $i * 10;
    $dt = new DateTime("@$block_t0");
    $dt->setTimezone($tz);
    $tz_offset = $dt->getOffset();

    $data = array();
    for ($f = 0; $f < $F; $f++) {
        $data[$f] = load_block($fh[$f], $meta[$keys[$f]], $block_t0, $n);
        fill_nulls($data[$f], $n, $fill[$f]);
    }

    // pass 1: state, band and model elec per sample (sequential state as feed_scan_6)
    $A_st = array_fill(0, $n, -1);
    $A_band = array_fill(0, $n, -1);
    $A_e = array_fill(0, $n, NAN);
    $A_h = array_fill(0, $n, NAN);
    $A_em = array_fill(0, $n, 0.0);
    $A_rps = array_fill(0, $n, NAN);

    for ($j = 0; $j < $n; $j++, $i++) {
        $elec = $data[$K["heatpump_elec"]][$j];
        $h = $data[$K["heatpump_heat"]][$j];
        $flowT = $data[$K["heatpump_flowT"]][$j];
        $outsideT = $data[$K["heatpump_outsideT"]][$j];
        $dhw = false;
        if ($has_dhw) {
            $v = $data[$K["heatpump_dhw"]][$j];
            $dhw = ($v == $v && $v != 0);
        } else if ($dhw_guess) {
            $dhw = ($flowT == $flowT && $flowT > DHW_GUESS_FLOWT);
        }

        $since_start += 10;
        $since_dhw_end += 10;
        $since_defrost += 10;
        $since_neg += 10;

        if ($neg !== null && $since_neg > DEFROST_MERGE_S) {
            if ($neg >= DEFROST_MIN_WH) $since_defrost = $since_neg;
            $neg = null;
        }

        $st = -1;
        $band = -1;
        if ($elec == $elec) {
            $now_running = $elec >= $starting_power;
            if ($running === false && $now_running) $since_start = 0;
            $running = $now_running;
            if ($dhw_prev && !$dhw && $now_running) $since_dhw_end = 0;
            $dhw_prev = $dhw;

            if ($h == $h && $elec >= 0) {
                $temps_ok = ($flowT == $flowT) && ($outsideT == $outsideT);
                $rps = null;
                if ($now_running && $h > 0 && $temps_ok) $rps = estimate_speed($qnom, $flowT, $outsideT, $h / 1000);

                if (!$temps_ok) $st = $S["unclass"];
                else if (!$now_running) $st = $S["off"];
                else if ($h < 0) {
                    if ($neg === null) $neg = 0.0;
                    $neg -= $h * 10 / 3600;
                    $since_neg = 0;
                    $st = $S["defrost"];
                }
                else if ($rps !== null && $rps > RPS_BEYOND) $st = $S["beyond"];
                else if (($neg !== null && $neg >= DEFROST_MIN_WH) || $since_defrost < RECOVERY_S) $st = $S["defrost"];
                else if ($dhw) $st = $S["dhw"];
                else if ($since_dhw_end < TRANSIENT_S) $st = $S["post_dhw"];
                else if ($since_start < TRANSIENT_S) $st = $S["startup"];
                else $st = $S["steady"];

                if ($rps !== null) {
                    $A_rps[$j] = $rps;
                    if ($st == $S["steady"] || $st == $S["startup"] || $st == $S["dhw"] || $st == $S["defrost"]) {
                        $cop = cop_fit_v2($qnom, $flowT, $outsideT, $h / 1000);
                        if ($cop > 0) $A_em[$j] = $h / $cop;
                    }
                    if ($st == $S["steady"]) $band = bin_index($rps, $RPS_EDGES);
                }
            }
        }
        $A_st[$j] = $st;
        $A_band[$j] = $band;
        $A_e[$j] = $elec;
        $A_h[$j] = $h;

        // episodes: a run of steady samples in one band; anything else ends it
        if ($band != $ep_band) {
            if ($ep_band >= 0) $ep_hours[$ep_band][bin_index($ep_len / 6, $EP_EDGES)] += $ep_len / 360;
            if ($band >= 0 && $ep_band >= 0) $band_changes++;
            $ep_band = $band;
            $ep_len = 0;
        }
        if ($band >= 0) $ep_len++;
    }

    // pass 2: centred windows within the block (the HALF_W samples at each
    // block edge are counted as "edge")
    $ok = array();      // 1 where the sample is running steady or startup with positive heat
    $pc = array(0); $pe = array(0.0); $pe2 = array(0.0);
    for ($j = 0; $j < $n; $j++) {
        $good = ($A_st[$j] == $S["steady"] || $A_st[$j] == $S["startup"]) && $A_h[$j] > 0;
        $ok[$j] = $good;
        $e = $good ? $A_e[$j] : 0.0;
        $pc[$j + 1] = $pc[$j] + ($good ? 1 : 0);
        $pe[$j + 1] = $pe[$j] + $e;
        $pe2[$j + 1] = $pe2[$j] + $e * $e;
    }
    // distance in samples to the nearest DHW sample after and before each sample
    $NEAR = intdiv(DHW_NEAR_S, 10);
    $dhw_after = array_fill(0, $n, false);
    $dhw_before = array_fill(0, $n, false);
    $last = -1e9;
    for ($j = 0; $j < $n; $j++) {
        if ($A_st[$j] == $S["dhw"]) $last = $j;
        $dhw_before[$j] = ($j - $last) <= $NEAR;
    }
    $last = 1e9;
    for ($j = $n - 1; $j >= 0; $j--) {
        if ($A_st[$j] == $S["dhw"]) $last = $j;
        $dhw_after[$j] = ($last - $j) <= $NEAR;
    }

    $flowT_arr = $data[$K["heatpump_flowT"]];
    $returnT_arr = $data[$K["heatpump_returnT"]];
    $flow_arr = $has_flow ? $data[$K["heatpump_flowrate"]] : null;

    for ($j = 0; $j < $n; $j++) {
        $b = $A_band[$j];
        $t = $block_t0 + $j * 10;
        $day = intdiv($t + $tz_offset, 86400);

        $cv = NAN; $set = 3; $trend = -1; $dclass = -1;
        if ($b >= 0 && $j >= HALF_W && $j + HALF_W < $n) {
            $a0 = $j - HALF_W; $a1 = $j + HALF_W + 1;
            $W = 2 * HALF_W + 1;
            $fd = $flowT_arr[$a1 - 1] - $flowT_arr[$a0];
            if ($fd == $fd) $dclass = bin_index($fd * 3600 / (($W - 1) * 10), $DRIFT_EDGES);
            if ($pc[$a1] - $pc[$a0] == $W) {
                $m = ($pe[$a1] - $pe[$a0]) / $W;
                $var = max(($pe2[$a1] - $pe2[$a0]) / $W - $m * $m, 0);
                $cv = $m > 0 ? sqrt($var) / $m : NAN;
                $set = $cv < CV_SETTLED ? 0 : ($cv < CV_UNSETTLED ? 1 : 2);
                $m1 = ($pe[$j] - $pe[$a0]) / HALF_W;
                $m2 = ($pe[$a1] - $pe[$j + 1]) / HALF_W;
                $r = $m1 > 0 ? $m2 / $m1 - 1 : 0;
                $trend = $r < -TREND_FRAC ? 0 : ($r > TREND_FRAC ? 2 : 1);
                $fd = $flowT_arr[$a1 - 1] - $flowT_arr[$a0];
                if ($fd == $fd) {
                    $drift[$b]['n']++;
                    $drift[$b]['drift'] += $fd * 3600 / (($W - 1) * 10);
                }
                if (count($drift[$b]['cv']) < 200000 && ($j % 6) == 0) $drift[$b]['cv'][] = $cv;
            }
        }

        if ($b >= 0) {
            $h = $A_h[$j]; $e = $A_e[$j]; $em = $A_em[$j];
            add2($band_acc[$b], $h, $e, $em);
            add2($set_acc[$b][$set], $h, $e, $em);
            if ($trend >= 0) add2($trend_acc[$b][$trend], $h, $e, $em);
            if ($dclass >= 0) {
                $da = &$drift_acc[$b][$dclass];
                add2($da, $h, $e, $em);
                $da['flowT'] += $flowT_arr[$j];
                if ($has_flow && $flow_arr[$j] == $flow_arr[$j]) { $da['flow'] += $flow_arr[$j]; $da['nflow']++; }
                unset($da);
            }
            $near = $dhw_after[$j] ? 0 : ($dhw_before[$j] ? 1 : 2);
            add2($near_acc[$b][$near], $h, $e, $em);
            if ($set == 0 && $dclass == 1 && $near == 2) add2($clean_acc[$b], $h, $e, $em);
            foreach ($LAGS as $k => $lag) {
                $jj = $j + intdiv($lag, 10);
                if ($jj < 0 || $jj >= $n) continue;
                if (!$ok[$jj]) continue;
                add2($lag_acc[$b][$k], $A_h[$jj], $e, $em);
            }
            if ($has_flow) {
                $fl = $flow_arr[$j];
                if ($fl == $fl) { $drift[$b]['nflow']++; $drift[$b]['flow'] += $fl; }
            }
            $dTfr = $flowT_arr[$j] - $returnT_arr[$j];
            if ($dTfr == $dTfr) { $drift[$b]['ndT']++; $drift[$b]['dT'] += $dTfr; }

            if (!isset($days[$day])) $days[$day] = array('min' => new_acc2(), 'low' => new_acc2(), 'mid' => new_acc2());
            $grp = $b == 0 ? 'min' : ($b <= 2 ? 'low' : 'mid');
            add2($days[$day][$grp], $h, $e, $em);
        }

        if (isset($dump_set[$day])) {
            if (!isset($dump_fh[$day])) {
                $dump_fh[$day] = fopen("$out_dir/dump_{$system_id}_{$dump_set[$day]}.csv", 'w');
                fwrite($dump_fh[$day], "time,elec,heat,flowT,returnT,outsideT,flow,state,rps,model_elec,band,elec_cv\n");
            }
            $st = $A_st[$j];
            fwrite($dump_fh[$day], implode(",", array(
                $t, csvn($A_e[$j], 1), csvn($A_h[$j], 1), csvn($flowT_arr[$j], 2), csvn($returnT_arr[$j], 2),
                csvn($data[$K["heatpump_outsideT"]][$j], 1), $has_flow ? csvn($flow_arr[$j], 2) : "",
                $st >= 0 ? $STATES[$st] : "", csvn($A_rps[$j], 1), csvn($A_em[$j], 1),
                $b >= 0 ? $BAND_LABELS[$b] : "", csvn($cv, 4)))."\n");
        }
    }
    print ".";
}
print "\n\n";
foreach ($fh as $handle) fclose($handle);
foreach ($dump_fh as $handle) fclose($handle);
if ($ep_band >= 0) $ep_hours[$ep_band][bin_index($ep_len / 6, $EP_EDGES)] += $ep_len / 360;

// 3. Report
$fm = function ($a) { return ($a['em'] > 0 && $a['e'] > 0) ? $a['em'] / $a['e'] : null; };
$hrs = function ($a) { return $a['n'] * 10 / 3600; };
$xw = function ($a) { return $a['n'] > 0 ? ($a['e'] - $a['em']) / $a['n'] : null; };

$steady_h = 0;
foreach ($band_acc as $a) $steady_h += $hrs($a);

print "Steady space heating by inferred speed band (field/model as feed_scan_6's map)\n";
$tab = array();
foreach ($BAND_LABELS as $b => $label) {
    $a = $band_acc[$b];
    $d = $drift[$b];
    $cvs = $d['cv'];
    sort($cvs);
    $tab[] = array($label, fmt($hrs($a), 0), fmt($a['n'] ? $a['h'] / $a['n'] : null, 0), fmt($a['n'] ? $a['e'] / $a['n'] : null, 0),
        fmt($fm($a), 3), fmt($xw($a), 0),
        fmt(count($cvs) ? $cvs[intdiv(count($cvs), 2)] : null, 3),
        fmt($d['n'] ? $d['drift'] / $d['n'] : null, 2),
        fmt($d['nflow'] ? $d['flow'] / $d['nflow'] : null, 1),
        fmt($d['ndT'] ? $d['dT'] / $d['ndT'] : null, 2));
}
print_table($tab, array("rps", "hours", "heat W", "elec W", "field/model", "extra W", "median elec CV", "flowT drift K/h", "flow L/min", "dT"));
print "Band changes: ".fmt($steady_h > 0 ? $band_changes / $steady_h : null, 2)." per steady hour\n\n";

print "Field/model by how settled the elec is over a centred ".fmt((2 * HALF_W + 1) * 10 / 60, 1)." min window\n";
print "(settled CV < ".CV_SETTLED.", unsettled CV >= ".CV_UNSETTLED."; edge = window leaves steady/startup)\n";
$tab = array();
foreach ($BAND_LABELS as $b => $label) {
    $row = array($label);
    foreach ($SET_LABELS as $k => $unused) {
        $a = $set_acc[$b][$k];
        $row[] = $hrs($a) >= 2 ? fmt($fm($a), 3)." (".fmt($hrs($a) / max($hrs($band_acc[$b]), 1e-9) * 100, 0)."%)" : "";
    }
    $tab[] = $row;
}
print_table($tab, array_merge(array("rps"), $SET_LABELS));
print "\n";

print "Field/model by elec trend across the window (second half vs first, ".(TREND_FRAC * 100)."%)\n";
$tab = array();
foreach ($BAND_LABELS as $b => $label) {
    $row = array($label);
    foreach ($TREND_LABELS as $k => $unused) {
        $a = $trend_acc[$b][$k];
        $row[] = $hrs($a) >= 2 ? fmt($fm($a), 3)." (".fmt($hrs($a) / max($hrs($band_acc[$b]), 1e-9) * 100, 0)."%)" : "";
    }
    $tab[] = $row;
}
print_table($tab, array_merge(array("rps"), $TREND_LABELS));
print "\n";

print "Field/model with heat taken LAG s after the elec (model elec at the unshifted heat)\n";
$tab = array();
foreach ($BAND_LABELS as $b => $label) {
    $row = array($label);
    foreach ($LAGS as $k => $unused) {
        $a = $lag_acc[$b][$k];
        // shifted heat over the same samples' elec, against the unshifted field/model
        $f0 = $lag_acc[$b][array_search(0, $LAGS)];
        $row[] = ($a['e'] > 0 && $f0['h'] > 0 && $hrs($a) >= 2) ? fmt($fm($f0) * ($a['h'] / $a['e']) / ($f0['h'] / $f0['e']), 3) : "";
    }
    $tab[] = $row;
}
print_table($tab, array_merge(array("rps"), array_map(function ($l) { return ($l > 0 ? "+" : "").$l; }, $LAGS)));
print "\n";

print "Field/model by flowT drift across the window, K/h (".implode(", ", array_slice($DRIFT_EDGES, 1, -1))."), with hours share, mean flowT and flow\n";
$tab = array();
foreach ($BAND_LABELS as $b => $label) {
    $row = array($label);
    foreach ($DRIFT_LABELS as $k => $unused) {
        $a = $drift_acc[$b][$k];
        $row[] = $hrs($a) >= 2 ? fmt($fm($a), 3)." (".fmt($hrs($a) / max($hrs($band_acc[$b]), 1e-9) * 100, 0)."%, "
            .fmt($a['flowT'] / $a['n'], 1)."C".($a['nflow'] ? ", ".fmt($a['flow'] / $a['nflow'], 1)." L/m" : "").")" : "";
    }
    $tab[] = $row;
}
print_table($tab, array_merge(array("rps"), $DRIFT_LABELS));
print "\n";

print "Field/model by DHW within ".(DHW_NEAR_S / 60)." min, and clean running (settled, flat flowT, not near DHW)\n";
$tab = array();
foreach ($BAND_LABELS as $b => $label) {
    $row = array($label);
    foreach ($NEAR_LABELS as $k => $unused) {
        $a = $near_acc[$b][$k];
        $row[] = $hrs($a) >= 2 ? fmt($fm($a), 3)." (".fmt($hrs($a) / max($hrs($band_acc[$b]), 1e-9) * 100, 0)."%)" : "";
    }
    $a = $clean_acc[$b];
    $row[] = $hrs($a) >= 2 ? fmt($fm($a), 3)." (".fmt($hrs($a) / max($hrs($band_acc[$b]), 1e-9) * 100, 0)."%)" : "";
    $tab[] = $row;
}
print_table($tab, array_merge(array("rps"), $NEAR_LABELS, array("clean")));
print "\n";

print "Hours in the band by length of stay (minutes)\n";
$tab = array();
foreach ($BAND_LABELS as $b => $label) {
    $row = array($label);
    $tot = array_sum($ep_hours[$b]);
    foreach ($EP_LABELS as $k => $unused) $row[] = $tot > 0 ? fmt($ep_hours[$b][$k] / $tot * 100, 0)."%" : "";
    $tab[] = $row;
}
print_table($tab, array_merge(array("rps"), $EP_LABELS));
print "\n";

$day_rows = array();
foreach ($days as $day => $d) {
    $day_rows[] = array('date' => gmdate("Y-m-d", $day * 86400),
        'min_h' => $hrs($d['min']), 'min_fm' => $fm($d['min']),
        'low_h' => $hrs($d['low']), 'low_fm' => $fm($d['low']),
        'mid_h' => $hrs($d['mid']), 'mid_fm' => $fm($d['mid']));
}
$top = $day_rows;
usort($top, function ($x, $y) { return $y['low_h'] <=> $x['low_h']; });
print "Days with most steady hours at 30-50 rps (for --dump)\n";
$tab = array();
foreach (array_slice($top, 0, TOP_DAYS) as $r) {
    $tab[] = array($r['date'], fmt($r['low_h'], 1), fmt($r['low_fm'], 3), fmt($r['min_h'], 1), fmt($r['min_fm'], 3),
        fmt($r['mid_h'], 1), fmt($r['mid_fm'], 3));
}
print_table($tab, array("day", "30-50 h", "f/m", "<30 h", "f/m", ">50 h", "f/m"));
print "\n";

$out = array(
    'system_id' => $system_id,
    'qnom_kw' => $qnom,
    'window_start' => $window_start,
    'window_end' => $window_end,
    'has_flow' => $has_flow,
    'dhw_guess' => !$has_dhw && $dhw_guess,
    'half_w' => HALF_W, 'cv_settled' => CV_SETTLED, 'cv_unsettled' => CV_UNSETTLED, 'trend_frac' => TREND_FRAC,
    'bands' => $BAND_LABELS, 'settled_labels' => $SET_LABELS, 'trend_labels' => $TREND_LABELS,
    'lags' => $LAGS, 'episode_labels' => $EP_LABELS,
    'band' => $band_acc, 'settled' => $set_acc, 'trend' => $trend_acc, 'lag' => $lag_acc,
    'episode_hours' => $ep_hours,
    'drift_labels' => $DRIFT_LABELS, 'drift_edges' => $DRIFT_EDGES, 'drift_class' => $drift_acc,
    'near_labels' => $NEAR_LABELS, 'near_dhw_s' => DHW_NEAR_S, 'near' => $near_acc, 'clean' => $clean_acc, 'band_changes' => $band_changes, 'steady_h' => $steady_h,
    'drift' => array_map(function ($d) { unset($d['cv']); return $d; }, $drift),
    'median_cv' => array_map(function ($d) { $c = $d['cv']; sort($c); return count($c) ? $c[intdiv(count($c), 2)] : null; }, $drift),
    'days' => $day_rows,
    'units' => 'acc sums are W x 10s samples; hours = n * 10 / 3600'
);
$fn = "$out_dir/lowmod_{$system_id}".($qnom_override > 0 ? "_q".$qnom_override : "").".json";
file_put_contents($fn, json_encode($out));
print "Written $fn\n";
foreach ($dump_set as $d) print "Dump: $out_dir/dump_{$system_id}_$d.csv\n";

function csvn($x, $dp) { return $x == $x ? number_format((float) $x, $dp, '.', '') : ""; }

// The functions below are copied from feed_scan_6.php

function bin_index($x, $edges)
{
    $n = count($edges) - 1;
    for ($k = 0; $k < $n - 1; $k++) {
        if ($x < $edges[$k + 1]) return $k;
    }
    return $n - 1;
}

function fmt($x, $dp) { return $x === null ? "--" : number_format((float) $x, $dp, '.', ''); }

function volumetric_capacity($flowT, $outsideT)
{
    $c = array(1.070e-2, 2.889e-4, 6.495e-7, -9.330e-3);
    return max(($c[0] + $c[1] * $outsideT + $c[2] * $outsideT * $outsideT) * (1 + $c[3] * ($flowT - 50.0)), 1e-4);
}

function estimate_speed($qnom_kw, $flowT, $outsideT, $output_kw)
{
    return $output_kw / ($qnom_kw * volumetric_capacity($flowT, $outsideT));
}

function cop_fit_v2($qnom_kw, $flowT, $outsideT, $output_kw, $eta_scale = 1.0)
{
    $p = array(0.24930, 0.74153, -0.48742, 0.66656, -1.05159, 0.32990, -0.03477, -0.00303);

    $qh = $output_kw / $qnom_kw;
    $s = min(max(estimate_speed($qnom_kw, $flowT, $outsideT, $output_kw), 30), 120) / 100.0;

    $Tc = $flowT + 273.15 + 4.0 * $qh;
    $Te = $outsideT + 273.15 - 7.0 * $qh;
    $L = max($Tc - $Te, 5.0);
    $z = ($L - 45.0) / 45.0;
    $w = ($flowT - 50.0) / 15.0;

    $eta = $eta_scale
         * ($p[0] + $p[1] * $s + $p[2] * $s * $s + $p[3] * $s * $z)
         * (1.0 + $p[4] * $z + $p[5] * $z * $z + $p[6] * $z * $z * $z)
         * (1.0 + $p[7] * $w);

    return $eta * $Tc / $L;
}

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
