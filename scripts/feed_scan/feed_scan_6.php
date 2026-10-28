<?php

// Machine replay twin for one system: stage 1 of the per-system twin work
// (see analysis/performance_prediction/docs/load_and_quality.md).
//
// Replays a year of raw 10s feeds through the Vaillant Arotherm+ datasheet COP
// fit (generic_v2, ported from www/lib/vaillant_cop_fit.js in the tools repo)
// at the measured flowT, outsideT and heat output, and compares the model's
// electricity with the measured electricity, split by operating state:
//
//   steady     space heating with the compressor running, outside the windows below
//   ramp       steady space heating while flowT changes faster than RAMP_KH across a
//              centred RAMP_WINDOW_S: the water and the unit between compressor and
//              meter store heat on the way up and give it back on the way down, and
//              most fast rises are the start of a DHW reheat the flag or guess misses
//   startup    first TRANSIENT_S after a compressor start
//   dhw        heatpump_dhw flag set (without the feed: flowT above DHW_GUESS_FLOWT)
//   defrost    running with negative heat, plus RECOVERY_S after each defrost
//   post_dhw   first TRANSIENT_S after a DHW to space switch, when the hot
//              primary water empties into the heating circuit
//   beyond     inferred speed above RPS_BEYOND: more heat than the compressor
//              can make (meter spikes, stored heat), so not the machine's output
//   off        compressor not running (standby, pump overrun)
//   unclass    elec and heat present but flowT or outsideT missing
//
// The model is only compared where metered heat is the compressor's output:
// steady, ramp, startup, dhw and defrost. post_dhw and beyond heat stays in the
// measured totals.
//
// A defrost is a run of negative heat while running (gaps under
// DEFROST_MERGE_S merged) removing at least DEFROST_MIN_WH. Smaller runs are
// counted as meter blips: their samples stay in the defrost state but they
// start no recovery window.
//
// The fit is pre-defrost and has no standby, so model electricity is only
// defined for running samples with positive heat. The report builds measured
// SPF up from the datasheet replay one state at a time, so the steps add up to
// the measured SPF exactly.
//
// The steady space heating gap is shown two ways per cell of outsideT and
// inferred compressor speed, to tell apart two explanations: a constant extra
// load while running (extra W the same in every cell) and a heat meter
// flow-return dT error (needed offset the same in every cell).
//
// Sample handling follows feed_scan_4.php: nulls forward-filled across gaps
// under 15 min, measured SPF over samples with elec and heat present and
// elec >= 0. Running means elec >= the app's starting_power (default 150 W),
// as in the published stats.
//
// Output: report to stdout + replay_<id>.json alongside this script.
//
// Usage: php feed_scan_6.php [system_id] [days] [--qnom=kW] [--no-dhw-guess] [--out=dir] [--model=name]
//   defaults 75, 365; --qnom replaces the declared rated output in the fit;
//   --no-dhw-guess treats everything as space heating when there's no DHW flag;
//   --out writes the JSON there instead of alongside this script;
//   --model picks the datasheet COP model (all pre-defrost, from vaillant_cop_fit.js):
//     v2       copFitGenericV2, speed-based generic fit (default)
//     generic  copFitGeneric, capacity-normalised generic fit (load fraction Q/Qnom)
//     unit     copFit unit fits: the 5 kW fit up to 7 kW, the 12 kW fit from 10 kW,
//              with load fraction taken against the system's capacity (exact for
//              5 and 12 kW, a scaled stand-in for the other sizes)
//   Inferred speed, and so the speed bands, come from the v2 capacity model whatever
//   the COP model. Other models add _m<name> to the JSON name.
//   (replay_fleet.sh runs the Arotherm+ fleet)

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
$cop_model = "v2";
foreach (array_slice($argv, 1) as $a) {
    if (strpos($a, "--qnom=") === 0) $qnom_override = (float) substr($a, 7);
    else if ($a == "--no-dhw-guess") $dhw_guess = false;
    else if (strpos($a, "--out=") === 0) $out_dir = rtrim(substr($a, 6), "/");
    else if (strpos($a, "--model=") === 0) $cop_model = substr($a, 8);
    else $positional[] = $a;
}
if (!in_array($cop_model, array("v2", "generic", "unit"))) die("unknown --model $cop_model (v2, generic or unit)\n");
$system_id = isset($positional[0]) ? (int) $positional[0] : 75;
$scan_days = isset($positional[1]) ? (int) $positional[1] : 365;
// a relative --out is relative to where the command was run, not www/
if ($out_dir[0] != "/") $out_dir = $start_cwd."/".$out_dir;
if (!is_dir($out_dir) || !is_writable($out_dir)) die("output directory $out_dir is missing or not writable\n");

define('TRANSIENT_S', 600);        // after a start or a DHW to space switch (s)
define('RPS_BEYOND', 130);         // fit tops out at 120 rps, plus margin for the speed inference
define('DHW_GUESS_FLOWT', 45);     // DHW when there's no flag feed (degC)
define('RECOVERY_S', 600);         // after a defrost, counted as defrost (s)
define('RAMP_WINDOW_S', 300);      // centred window for the flowT rate of change (s)
define('RAMP_KH', 8);              // flowT changing faster than this is a ramp (K/h)
define('DEFROST_MERGE_S', 60);     // negative heat runs closer than this are one run
define('DEFROST_MIN_WH', 50);      // smaller negative heat runs are meter blips
define('MIN_CELL_H', 5);           // table cells with fewer hours are left blank
define('BLOCK_SIZE', 86400);       // 10s samples read per block per feed (10 days, ~340kB)
define('FILL_MAX_S', 900);         // forward-fill nulls across gaps shorter than this (s)
define('PHPFINA_DIR', "/var/opt/emoncms/phpfina/");

// Bin edges for the field compressor map and the time since start table
$OUT_EDGES = array(-99, -2, 2, 7, 12, 99);
$RPS_EDGES = array(0, 30, 40, 50, 60, 70, 90, 120, 999);
$START_EDGES = array(0, 2, 5, 10, 15, 20, 30, 60, 99999);   // minutes
$TOD_LABELS = array("22-06", "06-10", "10-16", "16-22");     // local time of day

// 0. The PHP port must match the JS fit. Reference values from node on
//    copFitGenericV2() and estimateSpeed(): qnom, flowT, outsideT, kW, cop, rps
$parity = array(
    array(5, 35, -7, 5.1, 2.995427, 102.7353),
    array(5, 35, 7, 2.5, 4.652104, 34.3901),
    array(5, 45, 2, 4, 3.141552, 67.7585),
    array(5, 30, 10, 1.2, 7.125749, 14.8132),
    array(7, 40, 0, 6.5, 3.304047, 79.3765),
    array(12, 50, -5, 9, 2.415250, 80.8910)
);
foreach ($parity as $p) {
    $cop = cop_fit_v2($p[0], $p[1], $p[2], $p[3]);
    $rps = estimate_speed($p[0], $p[1], $p[2], $p[3]);
    if (abs($cop - $p[4]) > 1e-5 || abs($rps - $p[5]) > 1e-3) {
        die("COP fit parity check failed at ".implode(",", $p).": cop $cop rps $rps\n");
    }
}
// copFitGeneric() and the copFit() unit fits with frost off (unit at other sizes
// as copFit(unit, flow, amb, kW x unit_kW / qnom)): qnom, flowT, outsideT, kW, generic, unit
$parity2 = array(
    array(5, 35, -7, 5.1, 3.204495, 3.276592),
    array(5, 35, 7, 2.5, 4.783457, 4.655135),
    array(5, 45, 2, 4, 3.130361, 3.098902),
    array(5, 30, 10, 1.2, 6.822085, 7.049728),
    array(7, 40, 0, 6.5, 3.350089, 3.345140),
    array(12, 50, -5, 9, 2.383303, 2.317094),
    array(10, 35, 5, 4, 4.477963, 4.744396)
);
foreach ($parity2 as $p) {
    $g = cop_model("generic", $p[0], $p[1], $p[2], $p[3]);
    $u = cop_model("unit", $p[0], $p[1], $p[2], $p[3]);
    if (abs($g - $p[4]) > 1e-5 || abs($u - $p[5]) > 1e-5) {
        die("COP fit parity check failed at ".implode(",", $p).": generic $g unit $u\n");
    }
}

// 1. System meta and feeds
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

// Window: last $scan_days to the next Europe/London midnight, as feed_scan_4,
// clamped to the required feeds (the DHW flag can start later; missing is not DHW)
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
    .($qnom_override > 0 ? ", fit run as $qnom kW" : "").", COP model $cop_model\n";
print "Window: ".date("Y-m-d H:i", $window_start)." to ".date("Y-m-d H:i", $window_end)
    ." (".round($npoints * 10 / 86400, 1)." days)\n";
print "Starting power $starting_power W, DHW flag ".($has_dhw ? "feed ".$feedids["heatpump_dhw"] : "not available")."\n";
if (stripos($s->hp_manufacturer, "vaillant") === false) {
    print "WARNING: not a Vaillant, the Arotherm+ fit is only a generic stand-in here\n";
}
if (!$has_dhw && $dhw_guess) {
    print "WARNING: no DHW flag, so DHW is guessed as flowT above ".DHW_GUESS_FLOWT." C\n";
} else if (!$has_dhw) {
    print "WARNING: no DHW flag and the guess is off, so any DHW counts as space heating\n";
}
print "Parity check against the JS fit: ok\n";

// 2. Scan
$STATES = array("steady", "ramp", "startup", "dhw", "defrost", "post_dhw", "beyond", "off", "unclass");
$MODEL_STATES = array("steady", "ramp", "startup", "dhw", "defrost");
$acc = array();
foreach ($STATES as $st) $acc[$st] = new_acc();

$NO = count($OUT_EDGES) - 1;
$NR = count($RPS_EDGES) - 1;
$map = array();       // steady space heating by [outsideT bin][rps bin]
$tod = array();       // steady space heating by [outsideT bin][time of day]
for ($a = 0; $a < $NO; $a++) {
    for ($b = 0; $b < $NR; $b++) $map[$a][$b] = new_acc();
    for ($b = 0; $b < count($TOD_LABELS); $b++) $tod[$a][$b] = new_acc();
}
$since_start_tab = array();   // space heating running, not defrost, by minutes since start
for ($a = 0; $a < count($START_EDGES) - 1; $a++) $since_start_tab[$a] = new_acc();
$defrost_tab = array();       // defrosts by outsideT bin at their start
for ($a = 0; $a < $NO; $a++) $defrost_tab[$a] = array('events' => 0, 'heat_removed_kwh' => 0.0);

$keys = array_keys($feedids);
$F = count($keys);
$K = array_flip($keys);
$fh = array();
$fill = array();
foreach ($keys as $f => $key) {
    $fh[$f] = fopen(PHPFINA_DIR.$feedids[$key].".dat", 'rb');
    $fill[$f] = array('value' => NAN, 'gap' => 0);
}

$running = null;           // last known compressor state
$since_start = 1e9;        // s since the compressor started
$since_dhw_end = 1e9;      // s since a DHW run ended with the compressor still on
$since_defrost = 1e9;      // s since the last negative heat sample of a closed defrost
$since_neg = 1e9;          // s since the last negative heat sample while running
$neg = null;               // open negative heat run: Wh removed, outsideT bin at start
$blips = 0;
$blip_wh = 0.0;
$dhw_prev = false;
$starts = 0;
$run_lengths = array();    // s, completed runs
$run_len = 0;
$bad_cop = 0;

$i = 0;
while ($i < $npoints) {
    $block_n = min(BLOCK_SIZE, $npoints - $i);
    $block_t0 = $window_start + $i * 10;

    // local time of day from the block's UTC offset (a DST change lands up
    // to a block late, which is an hour's error for a few days a year)
    $dt = new DateTime("@$block_t0");
    $dt->setTimezone($tz);
    $tz_offset = $dt->getOffset();

    $data = array();
    for ($f = 0; $f < $F; $f++) {
        $data[$f] = load_block($fh[$f], $meta[$keys[$f]], $block_t0, $block_n);
        fill_nulls($data[$f], $block_n, $fill[$f]);
    }

    // the state machine runs sample by sample; the ramp test needs flowT either
    // side of each sample, so samples are accumulated in a second pass per block
    $B = array_fill(0, $block_n, null);

    for ($j = 0; $j < $block_n; $j++, $i++) {
        $elec = $data[$K["heatpump_elec"]][$j];
        $h = $data[$K["heatpump_heat"]][$j];
        $flowT = $data[$K["heatpump_flowT"]][$j];
        $returnT = $data[$K["heatpump_returnT"]][$j];
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

        // close a negative heat run once DEFROST_MERGE_S has passed without one
        if ($neg !== null && $since_neg > DEFROST_MERGE_S) {
            if ($neg['wh'] >= DEFROST_MIN_WH) {
                $defrost_tab[$neg['bin']]['events']++;
                $defrost_tab[$neg['bin']]['heat_removed_kwh'] += $neg['wh'] / 1000;
                $since_defrost = $since_neg;
            } else {
                $blips++;
                $blip_wh += $neg['wh'];
            }
            $neg = null;
        }

        // elec missing: compressor state unknown, sample left out of everything
        if (!($elec == $elec)) continue;

        // compressor state, starts and run lengths (compressor_starts semantics)
        $now_running = $elec >= $starting_power;
        if ($running === false && $now_running) {
            $starts++;
            $since_start = 0;
        }
        if ($running === true && !$now_running) {
            $run_lengths[] = $run_len;
            $run_len = 0;
        }
        if ($now_running) $run_len += 10;
        $running = $now_running;

        if ($dhw_prev && !$dhw && $now_running) $since_dhw_end = 0;
        $dhw_prev = $dhw;

        // measured SPF samples only from here on
        if (!($h == $h) || $elec < 0) continue;

        $temps_ok = ($flowT == $flowT) && ($outsideT == $outsideT);
        $rps = null;
        if ($now_running && $h > 0 && $temps_ok) $rps = estimate_speed($qnom, $flowT, $outsideT, $h / 1000);

        if (!$temps_ok) {
            $st = "unclass";
        } else if (!$now_running) {
            $st = "off";
        } else if ($h < 0) {
            if ($neg === null) $neg = array('wh' => 0.0, 'bin' => bin_index($outsideT, $OUT_EDGES));
            $neg['wh'] -= $h * 10 / 3600;
            $since_neg = 0;
            $st = "defrost";
        } else if ($rps !== null && $rps > RPS_BEYOND) {
            $st = "beyond";
        } else if (($neg !== null && $neg['wh'] >= DEFROST_MIN_WH) || $since_defrost < RECOVERY_S) {
            $st = "defrost";
        } else if ($dhw) {
            $st = "dhw";
        } else if ($since_dhw_end < TRANSIENT_S) {
            $st = "post_dhw";
        } else if ($since_start < TRANSIENT_S) {
            $st = "startup";
        } else {
            $st = "steady";
        }

        // model electricity: positive heat in the states where it's the compressor's output
        $em = 0.0;
        if ($rps !== null && in_array($st, $MODEL_STATES)) {
            $cop = cop_model($cop_model, $qnom, $flowT, $outsideT, $h / 1000);
            if ($cop > 0) {
                $em = $h / $cop;
            } else {
                $bad_cop++;
            }
        }
        $dTfr = ($returnT == $returnT) ? $flowT - $returnT : NAN;

        $B[$j] = array($st, $h, $elec, $em, $flowT, $dTfr,
            $rps !== null ? bin_index($outsideT, $OUT_EDGES) : -1,
            $rps !== null ? bin_index($rps, $RPS_EDGES) : -1,
            $since_start < 1e8 ? bin_index($since_start / 60, $START_EDGES) : -1);
    }

    $flowT_b = $data[$K["heatpump_flowT"]];
    for ($j = 0; $j < $block_n; $j++) {
        if ($B[$j] === null) continue;
        list($st, $h, $elec, $em, $flowT, $dTfr, $ob, $rb, $sb) = $B[$j];
        if ($st == "steady" && is_ramp($flowT_b, $j, $block_n)) $st = "ramp";

        add($acc[$st], $h, $elec, $em, $flowT, $dTfr);

        if ($st == "steady" && $ob >= 0) {
            add($map[$ob][$rb], $h, $elec, $em, $flowT, $dTfr);
            $hour = intdiv(($block_t0 + $j * 10 + $tz_offset) % 86400, 3600);
            add($tod[$ob][tod_index($hour)], $h, $elec, $em, $flowT, $dTfr);
        }
        if (($st == "steady" || $st == "ramp" || $st == "startup") && $sb >= 0) {
            add($since_start_tab[$sb], $h, $elec, $em, $flowT, $dTfr);
        }
    }
    print ".";
}
print "\n\n";
foreach ($fh as $handle) fclose($handle);

if ($neg !== null) {
    if ($neg['wh'] >= DEFROST_MIN_WH) {
        $defrost_tab[$neg['bin']]['events']++;
        $defrost_tab[$neg['bin']]['heat_removed_kwh'] += $neg['wh'] / 1000;
    } else {
        $blips++;
        $blip_wh += $neg['wh'];
    }
}

// 3. Report
$days = $npoints * 10 / 86400;
$tot = new_acc();
foreach ($STATES as $st) {
    foreach ($tot as $k => $v) $tot[$k] += $acc[$st][$k];
}
$spf = $tot['e'] > 0 ? ($tot['hpos'] + $tot['hneg']) / $tot['e'] : null;

// 3a. Cross-check against the published last 365 day stats
$rec = null;
$result = $mysqli->query("SELECT combined_cop, combined_heat_kwh, combined_elec_kwh, combined_starts, running_cop, water_cop, water_heat_kwh FROM system_stats_last365_v2 WHERE id = $system_id");
if ($result) $rec = $result->fetch_object();

print "Cross-check (scan / published last365)\n";
$tab = array();
$tab[] = array("SPF", fmt($spf, 3), $rec ? fmt($rec->combined_cop, 3) : "--");
$tab[] = array("heat kWh", fmt(kwh($tot['hpos'] + $tot['hneg']), 0), $rec ? fmt($rec->combined_heat_kwh, 0) : "--");
$tab[] = array("elec kWh", fmt(kwh($tot['e']), 0), $rec ? fmt($rec->combined_elec_kwh, 0) : "--");
$tab[] = array("starts", $starts, $rec ? fmt($rec->combined_starts, 0) : "--");
if ($has_dhw) {
    $d = $acc['dhw'];
    $tab[] = array("DHW COP (defrost excluded)", fmt(ratio($d['hpos'] + $d['hneg'], $d['e']), 3), $rec ? fmt($rec->water_cop, 3) : "--");
}
print_table($tab, array("", "scan", "published"));
print "\n";

// 3b. By state
print "By operating state (model = Arotherm+ datasheet fit at the measured conditions)\n";
$tab = array();
foreach ($STATES as $st) {
    $a = $acc[$st];
    $tab[] = array(
        $st, fmt($a['n'] * 10 / 3600, 0),
        fmt(kwh($a['hpos']), 0), fmt(kwh($a['hneg']), 1), fmt(kwh($a['e']), 0), fmt(kwh($a['em']), 0),
        fmt(ratio($a['hpos'] + $a['hneg'], $a['e']), 2),
        $a['em'] > 0 ? fmt($a['hpos'] / $a['em'], 2) : "--",
        $a['em'] > 0 ? fmt($a['em'] / $a['e'], 3) : "--",
        $a['em'] > 0 ? sprintf("%+.0f", extra_w($a)) : "--",
        $a['hpos'] > 0 ? fmt($a['hflowT'] / $a['hpos'], 1) : "--",
        $a['ndT'] > 0 ? fmt($a['sdT'] / $a['ndT'], 2) : "--"
    );
}
print_table($tab, array("state", "hours", "heat+ kWh", "heat- kWh", "elec kWh", "model elec",
    "field COP", "model COP", "model/field elec", "extra W", "flowT", "flow-ret dT"));
print "model/field elec above 1 means the machine did better than its datasheet;\n";
print "extra W is the mean measured minus model electricity while in that state\n\n";

// 3c. Ladder from the datasheet replay to the measured SPF
print "From the datasheet replay to measured SPF\n";
$H = 0.0; $E = 0.0;
foreach ($MODEL_STATES as $st) {
    $H += $acc[$st]['hpos'];
    $E += $acc[$st]['em'];
}
$ladder = array(array('step' => 'replay', 'spf' => ratio($H, $E)));
$tab = array();
$prev = ratio($H, $E);
$tab[] = array("datasheet replay, compressor heat", fmt($prev, 3), "");
$steps = array(
    "steady" => "+ steady space heating as measured",
    "ramp" => "+ flowT ramps as measured",
    "startup" => "+ start-ups as measured",
    "dhw" => "+ DHW as measured",
    "defrost" => "+ defrost and recovery",
    "post_dhw" => "+ after-DHW heat release",
    "beyond" => "+ heat beyond the compressor's range",
    "off" => "+ off: standby and overrun",
    "unclass" => "+ samples missing temperatures"
);
foreach ($steps as $st => $label) {
    $a = $acc[$st];
    if (!in_array($st, $MODEL_STATES)) {
        $H += $a['hpos'] + $a['hneg'];
        $E += $a['e'];
    } else {
        $H += $a['hneg'];
        $E += $a['e'] - $a['em'];
    }
    $now = ratio($H, $E);
    $tab[] = array($label, fmt($now, 3), sprintf("%+.3f", $now - $prev));
    $ladder[] = array('step' => $st, 'spf' => $now);
    $prev = $now;
}
print_table($tab, array("step", "SPF", "change"));
print "Steps depend on order; the kWh in the state table do not\n\n";

// 3d. Field compressor map, steady space heating
$rps_labels = array();
for ($b = 0; $b < $NR; $b++) {
    $rps_labels[] = $b == $NR - 1 ? ">".$RPS_EDGES[$b] : $RPS_EDGES[$b]."-".$RPS_EDGES[$b+1];
}
$out_labels = array();
for ($a = 0; $a < $NO; $a++) {
    if ($a == 0) $out_labels[] = "< ".$OUT_EDGES[1];
    else if ($a == $NO - 1) $out_labels[] = "> ".$OUT_EDGES[$a];
    else $out_labels[] = $OUT_EDGES[$a]." to ".$OUT_EDGES[$a+1];
}
// pooled over outsideT, as an extra row
$map_rows = $map;
$map_rows[$NO] = array();
for ($b = 0; $b < $NR; $b++) {
    $c = new_acc();
    for ($a = 0; $a < $NO; $a++) {
        foreach ($c as $k => $v) $c[$k] += $map[$a][$b][$k];
    }
    $map_rows[$NO][$b] = $c;
}
$map_row_labels = array_merge($out_labels, array("all"));

print "Steady space heating by outsideT (rows) and inferred rps (columns)\n";
print "Below 30 rps the fit clamps to 30, so that column is running at minimum speed;\n";
print "above 120 is past the fit's maximum (up to ".RPS_BEYOND.", beyond that is its own state)\n";
print "(cells with under ".MIN_CELL_H." hours left blank)\n\n";
print_grid("Field COP / model COP", $map_rows, $map_row_labels, $rps_labels,
    function($c) { return $c['e'] > 0 ? fmt($c['em'] / $c['e'], 3) : ""; });
print_grid("Field COP (model COP)", $map_rows, $map_row_labels, $rps_labels,
    function($c) { return $c['e'] > 0 && $c['em'] > 0 ? fmt($c['hpos'] / $c['e'], 2)." (".fmt($c['hpos'] / $c['em'], 2).")" : ""; });
print_grid("Mean heat W", $map_rows, $map_row_labels, $rps_labels,
    function($c) { return fmt($c['hpos'] / $c['n'], 0); });
print_grid("Extra W: measured minus model electricity", $map_rows, $map_row_labels, $rps_labels,
    function($c) { return sprintf("%+.0f", extra_w($c)); });
print_grid("Mean flow-return dT, K", $map_rows, $map_row_labels, $rps_labels,
    function($c) { return $c['ndT'] > 0 ? fmt($c['sdT'] / $c['ndT'], 2) : ""; });
print_grid("Heat meter dT offset that would explain the gap, K", $map_rows, $map_row_labels, $rps_labels,
    function($c) { $o = dT_offset($c); return $o === null ? "" : sprintf("%+.2f", $o); });
print_grid("Hours", $map_rows, $map_row_labels, $rps_labels,
    function($c) { return fmt($c['n'] * 10 / 3600, 0); }, 0.5);

// Which explanation is more constant across the cells (hours weighted)?
// Cells past the fit's 120 rps maximum are left out.
$xw = array(); $xo = array(); $wt = array();
$fit_e = array(); $fit_em = array();
for ($a = 0; $a < $NO; $a++) {
    for ($b = 0; $b < $NR - 1; $b++) {
        $c = $map[$a][$b];
        $hours = $c['n'] * 10 / 3600;
        $o = dT_offset($c);
        if ($hours < MIN_CELL_H || $o === null) continue;
        $xw[] = extra_w($c);
        $xo[] = $o;
        $wt[] = $hours;
        $fit_e[] = $c['e'] / $c['n'];
        $fit_em[] = $c['em'] / $c['n'];
    }
}
$constancy = null;
if (count($wt) >= 3) {
    list($mw, $sw) = wmean_sd($xw, $wt);
    list($mo, $so) = wmean_sd($xo, $wt);
    list($fa, $fb, $frms) = wfit($fit_em, $fit_e, $wt);
    $constancy = array('extra_w_mean' => $mw, 'extra_w_sd' => $sw, 'dT_offset_mean' => $mo, 'dT_offset_sd' => $so,
        'fit_slope' => $fa, 'fit_intercept_w' => $fb, 'fit_rms_w' => $frms, 'cells' => count($wt));
    print "Across the ".count($wt)." cells, hours weighted (lower relative spread = more constant):\n";
    printf("  constant extra load:     %+.0f W, sd %.0f W  (sd/mean %.2f)\n", $mw, $sw, $mw != 0 ? abs($sw / $mw) : 0);
    printf("  constant meter dT error: %+.2f K, sd %.2f K  (sd/mean %.2f)\n", $mo, $so, $mo != 0 ? abs($so / $mo) : 0);
    printf("  fit: measured W = %.3f x model W %+.0f W, rms %.0f W\n\n", $fa, $fb, $frms);
}

// 3e. Time of day, for a sun-warmed outside sensor (gap concentrated at midday)
print "Steady space heating by outsideT and local time of day\n\n";
print_grid("Extra W", $tod, $out_labels, $TOD_LABELS,
    function($c) { return sprintf("%+.0f", extra_w($c)); });
print_grid("Field COP / model COP", $tod, $out_labels, $TOD_LABELS,
    function($c) { return $c['e'] > 0 ? fmt($c['em'] / $c['e'], 3) : ""; });
print_grid("Hours", $tod, $out_labels, $TOD_LABELS,
    function($c) { return fmt($c['n'] * 10 / 3600, 0); }, 0.5);

// 3f. Time since start
print "Space heating running (not defrost or DHW) by minutes since compressor start\n";
$tab = array();
for ($a = 0; $a < count($START_EDGES) - 1; $a++) {
    $c = $since_start_tab[$a];
    $label = $a == count($START_EDGES) - 2 ? ">".$START_EDGES[$a] : $START_EDGES[$a]."-".$START_EDGES[$a+1];
    $tab[] = array($label, fmt($c['n'] * 10 / 3600, 0), fmt(kwh($c['hpos']), 0),
        $c['n'] > 0 ? fmt($c['hpos'] / $c['n'], 0) : "--",
        $c['n'] > 0 ? fmt($c['e'] / $c['n'], 0) : "--",
        $c['hpos'] > 0 ? fmt($c['hflowT'] / $c['hpos'], 1) : "--",
        fmt(ratio($c['hpos'], $c['e']), 2),
        $c['em'] > 0 ? fmt($c['hpos'] / $c['em'], 2) : "--",
        $c['em'] > 0 && $c['e'] > 0 ? fmt($c['em'] / $c['e'], 3) : "--");
}
print_table($tab, array("minutes", "hours", "heat kWh", "heat W", "elec W", "flowT", "field COP", "model COP", "field/model"));
print "\n";

// 3g. Defrost and cycling
print "Defrosts (at least ".DEFROST_MIN_WH." Wh removed) by outsideT\n";
$tab = array();
$events = 0;
for ($a = 0; $a < $NO; $a++) {
    $d = $defrost_tab[$a];
    $events += $d['events'];
    $tab[] = array($out_labels[$a], $d['events'], fmt($d['heat_removed_kwh'], 1),
        $d['events'] > 0 ? fmt(1000 * $d['heat_removed_kwh'] / $d['events'], 0) : "--");
}
print_table($tab, array("outsideT", "events", "heat removed kWh", "Wh per event"));
$defrost_elec_share = $tot['e'] > 0 ? $acc['defrost']['e'] / $tot['e'] : 0;
printf("%d defrosts, %.1f a day; defrost and recovery use %.1f%% of electricity\n", $events, $events / $days, 100 * $defrost_elec_share);
printf("%d smaller negative heat blips, %.1f kWh in all\n\n", $blips, $blip_wh / 1000);

sort($run_lengths);
$nr = count($run_lengths);
$short = 0;
foreach ($run_lengths as $r) if ($r < 1200) $short += $r;
$run_total = array_sum($run_lengths);
print "Cycling\n";
printf("%d starts, %.1f a day; median run %.0f min; %.0f%% of running time in runs under 20 min\n\n",
    $starts, $starts / $days, $nr ? calc_median($run_lengths) / 60 : 0,
    $run_total > 0 ? 100 * $short / $run_total : 0);

if ($bad_cop) print "WARNING: $bad_cop running samples where the fit gave COP <= 0 (left out of model elec)\n\n";

// 4. JSON for cross-system comparison and plotting
$out = array(
    'system_id' => $system_id, 'hp_manufacturer' => $s->hp_manufacturer, 'hp_model' => $s->hp_model,
    'qnom_kw' => $qnom, 'window_start' => $window_start, 'window_end' => $window_end, 'days' => $days,
    'starting_power' => $starting_power, 'has_dhw' => $has_dhw,
    'transient_s' => TRANSIENT_S, 'recovery_s' => RECOVERY_S, 'ramp_window_s' => RAMP_WINDOW_S, 'ramp_kh' => RAMP_KH, 'defrost_min_wh' => DEFROST_MIN_WH,
    'spf' => $spf, 'published_spf' => $rec ? (float) $rec->combined_cop : null,
    'ladder' => $ladder, 'constancy' => $constancy,
    'starts' => $starts, 'run_lengths_s' => $run_lengths,
    'defrosts' => $events, 'blips' => $blips, 'blip_wh' => $blip_wh,
    'states' => $acc, 'out_edges' => $OUT_EDGES, 'rps_edges' => $RPS_EDGES, 'start_edges' => $START_EDGES,
    'tod_labels' => $TOD_LABELS,
    'map' => $map, 'tod' => $tod, 'since_start' => $since_start_tab, 'defrost' => $defrost_tab,
    'units' => 'acc sums are W x 10s samples (sdT: K x samples); kWh = sum * 10 / 3.6e6'
);
$out['qnom_override'] = $qnom_override;
$out['dhw_guess'] = $dhw_guess;
$out['cop_model'] = $cop_model;
$suffix = ($qnom_override > 0 ? "_q".$qnom_override : "").(!$has_dhw && !$dhw_guess ? "_nodhw" : "")
    .($cop_model != "v2" ? "_m".$cop_model : "");
$jsonfile = $out_dir."/replay_$system_id$suffix.json";
if (file_put_contents($jsonfile, json_encode($out)) === false) die("ERROR: could not write $jsonfile\n");
print "Written $jsonfile\n";


// Accumulator: samples, positive and negative heat, elec, model elec,
// heat x flowT, and flow-return dT summed over the samples that have it
function new_acc()
{
    return array('n' => 0, 'hpos' => 0.0, 'hneg' => 0.0, 'e' => 0.0, 'em' => 0.0, 'hflowT' => 0.0,
        'sdT' => 0.0, 'ndT' => 0);
}

function add(&$a, $h, $e, $em, $flowT, $dTfr)
{
    $a['n']++;
    if ($h > 0) {
        $a['hpos'] += $h;
        $a['hflowT'] += $h * $flowT;
    } else {
        $a['hneg'] += $h;
    }
    $a['e'] += $e;
    $a['em'] += $em;
    if ($dTfr == $dTfr) {
        $a['sdT'] += $dTfr;
        $a['ndT']++;
    }
}

// flowT changing faster than RAMP_KH across a window of RAMP_WINDOW_S centred on
// sample $j (clipped at the block edges, needing at least half the window)
function is_ramp($flowT, $j, $n)
{
    $half = intdiv(RAMP_WINDOW_S, 20);
    $a0 = max($j - $half, 0);
    $a1 = min($j + $half, $n - 1);
    if ($a1 - $a0 < $half) return false;
    $d = $flowT[$a1] - $flowT[$a0];
    if (!($d == $d)) return false;
    return abs($d) * 360 / ($a1 - $a0) >= RAMP_KH;
}

// Mean measured minus model electricity, W
function extra_w($a)
{
    return $a['n'] > 0 ? ($a['e'] - $a['em']) / $a['n'] : 0;
}

// Flow-return dT offset (K, true minus measured) that would make field COP
// equal model COP if the gap were all heat meter error: dT x (e / em - 1)
function dT_offset($a)
{
    if ($a['em'] <= 0 || $a['ndT'] == 0) return null;
    return ($a['sdT'] / $a['ndT']) * ($a['e'] / $a['em'] - 1);
}

// Print a grid of cells, blank where a cell has under $min_h hours
function print_grid($title, $cells, $row_labels, $col_labels, $fn, $min_h = MIN_CELL_H)
{
    print "$title\n";
    $tab = array();
    foreach ($row_labels as $a => $label) {
        $row = array($label);
        foreach ($col_labels as $b => $unused) {
            $c = $cells[$a][$b];
            $row[] = ($c['n'] * 10 / 3600 >= $min_h) ? $fn($c) : "";
        }
        $tab[] = $row;
    }
    print_table($tab, array_merge(array(""), $col_labels));
    print "\n";
}

function tod_index($hour)
{
    if ($hour >= 22 || $hour < 6) return 0;
    if ($hour < 10) return 1;
    if ($hour < 16) return 2;
    return 3;
}

function wmean_sd($x, $w)
{
    $sw = array_sum($w);
    $m = 0.0;
    foreach ($x as $k => $v) $m += $w[$k] * $v;
    $m /= $sw;
    $v2 = 0.0;
    foreach ($x as $k => $v) $v2 += $w[$k] * ($v - $m) * ($v - $m);
    return array($m, sqrt($v2 / $sw));
}

// Weighted least squares y = a x + b, returns a, b and the weighted rms residual
function wfit($x, $y, $w)
{
    list($mx) = wmean_sd($x, $w);
    list($my) = wmean_sd($y, $w);
    $sxy = 0.0; $sxx = 0.0;
    foreach ($x as $k => $v) {
        $sxy += $w[$k] * ($v - $mx) * ($y[$k] - $my);
        $sxx += $w[$k] * ($v - $mx) * ($v - $mx);
    }
    $a = $sxx > 0 ? $sxy / $sxx : 0;
    $b = $my - $a * $mx;
    $r2 = 0.0;
    foreach ($x as $k => $v) $r2 += $w[$k] * pow($y[$k] - $a * $v - $b, 2);
    return array($a, $b, sqrt($r2 / array_sum($w)));
}

function bin_index($x, $edges)
{
    $n = count($edges) - 1;
    for ($k = 0; $k < $n - 1; $k++) {
        if ($x < $edges[$k + 1]) return $k;
    }
    return $n - 1;
}

function kwh($sum) { return $sum * 10 / 3600000; }
function ratio($a, $b) { return $b > 0 ? $a / $b : null; }
function fmt($x, $dp) { return $x === null ? "--" : number_format((float) $x, $dp, '.', ''); }

// Vaillant Arotherm+ generic v2 COP fit, ported from www/lib/vaillant_cop_fit.js
// in the tools repo (copFitGenericV2, estimateSpeed). Pre-defrost, no standby.
function volumetric_capacity($flowT, $outsideT)
{
    $c = array(1.070e-2, 2.889e-4, 6.495e-7, -9.330e-3);
    return max(($c[0] + $c[1] * $outsideT + $c[2] * $outsideT * $outsideT) * (1 + $c[3] * ($flowT - 50.0)), 1e-4);
}

// Inferred compressor speed, rps, unclamped (below 30 means the machine must cycle)
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

// The datasheet COP for the chosen --model (see the usage notes at the top)
function cop_model($model, $qnom_kw, $flowT, $outsideT, $output_kw)
{
    if ($model == "v2") return cop_fit_v2($qnom_kw, $flowT, $outsideT, $output_kw);
    if ($model == "generic") {
        $p = array(0.32497, 0.35205, -0.16327, 0.24436, -0.48360, 0.04104, -0.02819, -0.06901);
    } else if ($qnom_kw <= 7) {
        // copFit "5kW"
        $p = array(0.29796, 0.34580, -0.14278, 0.21528, -0.36836, 0.39579, -0.53194, -0.09262);
    } else {
        // copFit "12kW"
        $p = array(0.35750, 0.40685, -0.22836, 0.22177, -0.53664, -0.46257, 0.60862, -0.04182);
    }
    return cop_fit_load($p, $qnom_kw, $flowT, $outsideT, $output_kw);
}

// copFitGeneric() / copFit() with frost off: the efficiency curve is in load
// fraction Q/Qnom rather than inferred speed
function cop_fit_load($p, $qnom_kw, $flowT, $outsideT, $output_kw)
{
    $qh = $output_kw / $qnom_kw;
    $Tc = $flowT + 273.15 + 4.0 * $qh;
    $Te = $outsideT + 273.15 - 7.0 * $qh;
    $L = max($Tc - $Te, 5.0);
    $z = ($L - 45.0) / 45.0;
    $w = ($flowT - 50.0) / 15.0;

    $eta = ($p[0] + $p[1] * $qh + $p[2] * $qh * $qh + $p[3] * $qh * $z)
         * (1.0 + $p[4] * $z + $p[5] * $z * $z + $p[6] * $z * $z * $z)
         * (1.0 + $p[7] * $w);

    return $eta * $Tc / $L;
}

// Forward-fill NANs with the last valid value across gaps shorter than
// FILL_MAX_S (as feed_scan_4.php, matching remove_null_values)
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
// (as feed_scan_4.php). Other intervals are resampled by repetition; missing is NAN.
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
