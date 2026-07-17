<?php

// Fleet scan: H* (heat-weighted harmonic Carnot COP with load-dependent offsets)
// for all systems meeting the MID metering + H4 boundary filters used by
// find_homes_like_this (www/Modules/home/home_controller.php).
// See analysis/performance_prediction/docs/05_harmonic_carnot_metric.md
//
// For each system the last 365 days of raw 10s feeds are scanned (matching the
// system_stats_last365_v2 window as closely as the feeds allow), accumulating
// H*, the fixed-offset variant, heat-weighted flowT/outsideT and a measured SPF
// over the same window. The heat-weighted temperatures are compared against the
// recorded weighted_* stats as a validation of both pipelines.
//
// Sample handling matches the myheatpump processing scripts:
// - nulls are forward-filled with the last value across gaps < 15 min
//   (remove_null_values semantics)
// - weighted / H* accumulation requires elec, heat, flowT, returnT and
//   outsideT all present at the sample (process_weighted_average semantics)
// - the window SPF requires elec and heat present and elec >= 0
//   (calculate_window_cops semantics)
// Cooling is NOT inverted: negative heat is excluded from the weighted sums
// and subtracts from the window heat total, so cooling systems still diverge
// from recorded stats (see docs/09_hstar_fleet_results.md).
//
// Output: one line per system to stdout + hstar_fleet.csv alongside this script.
//
// Usage: php feed_scan_4.php [max_systems]   (0 or omitted = all)

$dir = dirname(__FILE__);
chdir($dir."/../../www");

define('EMONCMS_EXEC', 1);
require "Lib/load_database.php";
require $dir."/../common.php";

require("Modules/user/user_model.php");
$user = new User($mysqli,false);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

require ("Modules/system/system_stats_model.php");
$system_stats = new SystemStats($mysqli,$system);

$max_systems = isset($argv[1]) ? (int) $argv[1] : 0;

// H* offset coefficients (K per unit load ratio) and fixed-offset convention (K)
define('A_COND', 3.0);
define('B_EVAP', 8.0);
define('FIXED_COND', 2.0);
define('FIXED_EVAP', 6.0);

define('BLOCK_SIZE', 86400);       // 10s samples read per block per feed (10 days, ~340kB)
define('SCAN_DAYS', 365);          // stats window length in days (DateTime "-365 days")
define('FILL_MAX_S', 900);         // forward-fill nulls across gaps shorter than this (s)
define('PHPFINA_DIR', "/var/opt/emoncms/phpfina/");

// 1. Pull the system list: same query as find_homes_like_this
$query = array(
    'meta_filters' => array(
        'mid_metering' => 1,
        'metering_boundary_code' => 4,
        // H* reconstructs the evaporator temp from outside air, so ground and
        // water source systems (evaporator on a ground/water loop) are excluded
        'hp_type' => 'Air Source'
    ),
    'meta_fields' => array(
        'id',
        'hp_type',
        'hp_manufacturer',
        'hp_model',
        'hp_output',
        'data_flag'
    ),
    'stats_fields' => array(
        'combined_elec_kwh',
        'combined_heat_kwh',
        'combined_cop',
        'combined_data_length',
        'combined_prc_carnot',
        'error_air_kwh',
        'weighted_flowT',
        'weighted_outsideT',
        'weighted_flowT_minus_outsideT',
        'weighted_flowT_minus_returnT',
        'weighted_prc_carnot'
    ),
    "sort" => array(
        "field" => "combined_cop",
        "order" => "desc"
    ),
    "stats_table" => "system_stats_last365_v2"
);

$systems = $system_stats->combined_meta_stats_query($query);

$selected = array();
foreach ($systems as $s) {

    // Only include systems with a valid COP figure
    if ($s->combined_cop === null) continue;

    // combined_data_length in seconds converted to days
    $days = $s->combined_data_length / 86400;
    if ($days <= 330) continue;

    auto_flag_air_error($s);
    if ($s->data_flag == 1) continue;

    // H* needs a rated capacity
    if (!($s->hp_output > 0)) continue;

    $selected[] = $s;
}

// Scan window aligned with process_rolling_stats (load_and_process_cli.php):
// end = next midnight Europe/London, start = end - 365 days (DST aware).
// Each system is additionally clamped to its own feed availability.
$date = new DateTime();
$date->setTimezone(new DateTimeZone('Europe/London'));
$date->modify("midnight");
$stats_window_end = $date->getTimestamp() + 3600*24;
$date->setTimestamp($stats_window_end);
$date->modify("-".SCAN_DAYS." days");
$stats_window_start = $date->getTimestamp();
print "Stats window: ".date("Y-m-d H:i", $stats_window_start)." to ".date("Y-m-d H:i", $stats_window_end)." (Europe/London midnights)\n";

$N = count($selected);
print "Systems matching filters: $N\n";
if ($max_systems > 0 && $N > $max_systems) {
    $selected = array_slice($selected, 0, $max_systems);
    $N = $max_systems;
    print "Limited to first $N (by combined_cop desc)\n";
}
print "\n";

// 2. CSV output
$csvfile = $dir."/hstar_fleet.csv";
$csv = fopen($csvfile, 'w');
fputcsv($csv, array(
    "id", "hp_type", "hp_manufacturer", "hp_model", "hp_output_kw",
    "days_scanned", "running_hours", "heat_kwh_running", "heat_kwh_all", "elec_kwh_all",
    "H_star", "H_fixed", "spf_window", "spf_over_H_star", "spf_over_H_fixed",
    "calc_weighted_flowT", "rec_weighted_flowT",
    "calc_weighted_outsideT", "rec_weighted_outsideT",
    "calc_weighted_dT", "rec_weighted_dT",
    "calc_weighted_flowT_minus_returnT", "rec_weighted_flowT_minus_returnT",
    "rec_combined_cop", "rec_combined_prc_carnot", "rec_weighted_prc_carnot"
));

$results = array();
$skipped = array();
$fleet_start = microtime(true);

foreach ($selected as $n => $s) {
    $system_id = (int) $s->id;
    printf("[%3d/%3d] %4d %5.1fkW ", $n + 1, $N, $system_id, $s->hp_output);

    $r = scan_system($mysqli, $emoncms_mysqli, $system_id, $s->hp_output * 1000.0, $stats_window_start, $stats_window_end);
    if (!is_array($r)) {
        print "SKIP: $r\n";
        $skipped[$system_id] = $r;
        continue;
    }

    // validation deltas: calculated vs recorded weighted temperature stats
    $rec_dT = $s->weighted_flowT_minus_outsideT;
    printf(" %5.1fd  H*=%6.3f Hf=%6.3f SPF=%5.2f SPF/H*=%5.3f | dT %5.2f/%s flowT %5.2f/%s outT %5.2f/%s dTfr %4.2f/%s\n",
        $r['days'], $r['H_star'], $r['H_fixed'],
        $r['spf'] !== null ? $r['spf'] : NAN,
        $r['spf'] !== null ? $r['spf'] / $r['H_star'] : NAN,
        $r['w_dT'], $rec_dT !== null ? number_format($rec_dT, 2) : "--",
        $r['w_flowT'], $s->weighted_flowT !== null ? number_format($s->weighted_flowT, 2) : "--",
        $r['w_outsideT'], $s->weighted_outsideT !== null ? number_format($s->weighted_outsideT, 2) : "--",
        $r['w_dTfr'], $s->weighted_flowT_minus_returnT !== null ? number_format($s->weighted_flowT_minus_returnT, 2) : "--"
    );

    $row = array(
        'id' => $system_id,
        'H_star' => $r['H_star'], 'H_fixed' => $r['H_fixed'], 'spf' => $r['spf'],
        'w_flowT' => $r['w_flowT'], 'w_outsideT' => $r['w_outsideT'], 'w_dT' => $r['w_dT'],
        'rec_flowT' => $s->weighted_flowT, 'rec_outsideT' => $s->weighted_outsideT, 'rec_dT' => $rec_dT,
        'rec_cop' => $s->combined_cop, 'rec_prc_carnot' => $s->combined_prc_carnot
    );
    $results[] = $row;

    fputcsv($csv, array(
        $system_id, $s->hp_type, $s->hp_manufacturer, $s->hp_model, $s->hp_output,
        round($r['days'], 1), round($r['running_hours'], 1),
        round($r['heat_kwh_running'], 1), round($r['heat_kwh_all'], 1), round($r['elec_kwh_all'], 1),
        round($r['H_star'], 4), round($r['H_fixed'], 4),
        $r['spf'] !== null ? round($r['spf'], 4) : "",
        $r['spf'] !== null ? round($r['spf'] / $r['H_star'], 4) : "",
        $r['spf'] !== null ? round($r['spf'] / $r['H_fixed'], 4) : "",
        round($r['w_flowT'], 2), $s->weighted_flowT !== null ? round($s->weighted_flowT, 2) : "",
        round($r['w_outsideT'], 2), $s->weighted_outsideT !== null ? round($s->weighted_outsideT, 2) : "",
        round($r['w_dT'], 2), $rec_dT !== null ? round($rec_dT, 2) : "",
        round($r['w_dTfr'], 2), $s->weighted_flowT_minus_returnT !== null ? round($s->weighted_flowT_minus_returnT, 2) : "",
        round($s->combined_cop, 3),
        $s->combined_prc_carnot !== null ? round($s->combined_prc_carnot, 1) : "",
        $s->weighted_prc_carnot !== null ? round($s->weighted_prc_carnot, 1) : ""
    ));
}
fclose($csv);

$elapsed = microtime(true) - $fleet_start;

// 3. Summary
print "\n";
print "Scanned ".count($results)." systems in ".round($elapsed, 1)."s, skipped ".count($skipped)."\n";
print "CSV written to $csvfile\n\n";

// 3a. Validation: calculated vs recorded weighted temperature stats
foreach (array(
    'weighted_flowT' => array('w_flowT', 'rec_flowT'),
    'weighted_outsideT' => array('w_outsideT', 'rec_outsideT'),
    'weighted_dT' => array('w_dT', 'rec_dT')
) as $name => $f) {
    $deltas = array();
    foreach ($results as $r) {
        if ($r[$f[1]] !== null) $deltas[] = $r[$f[0]] - $r[$f[1]];
    }
    if (!count($deltas)) continue;
    $abs = array_map('abs', $deltas);
    $within = count(array_filter($abs, function($d) { return $d <= 0.5; }));
    printf("%-22s n=%3d  mean err %+6.3fK  mean |err| %5.3fK  max |err| %5.2fK  within 0.5K: %d%%\n",
        $name, count($deltas), calc_mean($deltas), calc_mean($abs), max($abs),
        round(100 * $within / count($deltas)));
}

// 3b. How well does each metric predict SPF? (correlation over the fleet)
$pairs_dT = array(); $pairs_Hf = array(); $pairs_Hs = array();
$q_fixed = array(); $q_star = array(); $q_rec = array();
foreach ($results as $r) {
    if ($r['spf'] === null) continue;
    $pairs_dT[] = array(-$r['w_dT'], $r['spf']);   // negated so all correlations are positive
    $pairs_Hf[] = array($r['H_fixed'], $r['spf']);
    $pairs_Hs[] = array($r['H_star'], $r['spf']);
    $q_fixed[] = $r['spf'] / $r['H_fixed'];
    $q_star[] = $r['spf'] / $r['H_star'];
    if ($r['rec_prc_carnot'] !== null) $q_rec[] = $r['rec_prc_carnot'] / 100.0;
}

print "\nSPF prediction (between-system, n=".count($pairs_Hs).")\n";
printf("  SPF ~ weighted dT (current):  R2 = %5.3f\n", pow(pearson($pairs_dT), 2));
printf("  SPF ~ H  (fixed +2/-6):       R2 = %5.3f\n", pow(pearson($pairs_Hf), 2));
printf("  SPF ~ H* (variable +3r/-8r):  R2 = %5.3f\n", pow(pearson($pairs_Hs), 2));

print "\nQuality score spread (sd, lower = fairer metric)\n";
printf("  SPF / H  (prc carnot):        mean %5.3f  sd %5.3f\n", calc_mean($q_fixed), calc_sd($q_fixed));
printf("  SPF / H* (corrected):         mean %5.3f  sd %5.3f\n", calc_mean($q_star), calc_sd($q_star));
if (count($q_rec)) {
    printf("  recorded combined_prc_carnot: mean %5.3f  sd %5.3f\n", calc_mean($q_rec), calc_sd($q_rec));
}

if (count($skipped)) {
    print "\nSkipped systems:\n";
    foreach ($skipped as $id => $reason) print "  $id: $reason\n";
}
print "\n";


// Scan a system's feeds over the stats window, accumulating H* and friends.
// Returns an array of results, or an error string if the system cannot be scanned.
function scan_system($mysqli, $emoncms_mysqli, $system_id, $capacity, $stats_window_start, $stats_window_end)
{
    // app id -> app config -> feed ids
    $result = $mysqli->query("SELECT app_id FROM system_meta WHERE id = $system_id");
    if (!$result || !($row = $result->fetch_object())) return "no system_meta row";

    $result = $emoncms_mysqli->query("SELECT * FROM app WHERE id = ".(int)$row->app_id);
    if (!$result || !($row = $result->fetch_object())) return "no app row";
    $app_config = json_decode($row->config);

    $feeds_to_load = array("heatpump_elec", "heatpump_heat", "heatpump_flowT", "heatpump_returnT", "heatpump_outsideT");
    $feedids = array();
    foreach ($feeds_to_load as $key) {
        if (isset($app_config->$key)) {
            $feedid = (int) $app_config->$key;
            $result = $emoncms_mysqli->query("SELECT id FROM feeds WHERE id = $feedid AND engine = 5");
            if ($result->num_rows > 0) $feedids[$key] = $feedid;
        }
    }
    // all five feeds required, matching process_weighted_average
    foreach ($feeds_to_load as $key) {
        if (!isset($feedids[$key])) return "missing $key feed";
    }

    $meta = array();
    foreach ($feedids as $key => $feedid) {
        $m = getmeta(PHPFINA_DIR, $feedid);
        if ($m === false) return "missing meta for $key (feed $feedid)";
        if ($m->npoints <= 0) return "empty feed $key (feed $feedid)";
        $meta[$key] = $m;
    }

    // overlapping window across feeds, clamped to the stats window
    // (midnight-aligned last 365 days, matching process_rolling_stats)
    $latest_start_time = 0;
    $earliest_end_time = PHP_INT_MAX;
    foreach ($meta as $m) {
        if ($m->start_time > $latest_start_time) $latest_start_time = $m->start_time;
        if ($m->end_time < $earliest_end_time) $earliest_end_time = $m->end_time;
    }
    $window_start = max($latest_start_time, $stats_window_start);
    $window_end = min($earliest_end_time, $stats_window_end);
    $npoints = intdiv($window_end - $window_start, 10);
    if ($npoints <= 0) return "no feed data within the stats window";

    $fh = array();
    foreach ($feedids as $key => $feedid) {
        $fh[$key] = fopen(PHPFINA_DIR.$feedid.".dat", 'rb');
    }

    $keys = array_keys($feedids);
    $F = count($keys);
    $KE = array_search("heatpump_elec", $keys);
    $KH = array_search("heatpump_heat", $keys);
    $KF = array_search("heatpump_flowT", $keys);
    $KR = array_search("heatpump_returnT", $keys);
    $KO = array_search("heatpump_outsideT", $keys);

    // forward-fill state per feed, carried across blocks
    $fill = array();
    for ($f=0; $f<$F; $f++) $fill[$f] = array('value' => NAN, 'gap' => 0);

    // single pass accumulators (dt is a constant 10s so it cancels in every ratio)
    $sum_heat = 0.0;
    $sum_ideal_elec_var = 0.0;
    $sum_ideal_elec_fixed = 0.0;
    $sum_heat_flowT = 0.0;
    $sum_heat_outsideT = 0.0;
    $sum_heat_dTfr = 0.0;
    $sum_heat_all = 0.0;
    $sum_elec_all = 0.0;
    $n_running = 0;

    $i = 0;
    while ($i < $npoints) {
        $block_n = min(BLOCK_SIZE, $npoints - $i);
        $block_t0 = $window_start + $i * 10;

        $data = array();
        for ($f=0; $f<$F; $f++) {
            $data[$f] = load_block($fh[$keys[$f]], $meta[$keys[$f]], $block_t0, $block_n);
            fill_nulls($data[$f], $block_n, $fill[$f]);
        }

        for ($j=0; $j<$block_n; $j++, $i++) {

            $elec = $data[$KE][$j];
            $h = $data[$KH][$j];
            $flowT = $data[$KF][$j];
            $returnT = $data[$KR][$j];
            $outsideT = $data[$KO][$j];

            // measured SPF: elec and heat both present, elec >= 0
            // (calculate_window_cops semantics; NAN fails all comparisons)
            if ($elec == $elec && $h == $h && $elec >= 0) {
                $sum_elec_all += $elec;
                $sum_heat_all += $h;
            }

            // weighted / H* accumulation: all five feeds present at this sample
            // (process_weighted_average semantics), while heat is being delivered
            if ($elec == $elec && $h == $h && $flowT == $flowT
                && $returnT == $returnT && $outsideT == $outsideT && $h > 0) {

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
                    $sum_heat_dTfr += $h * ($flowT - $returnT);
                    $n_running++;
                }
            }

            // progress: one dot per 30 days scanned
            if ($i % 259200 == 0) print ".";
        }
    }

    foreach ($fh as $handle) fclose($handle);

    if ($sum_ideal_elec_var <= 0) return "no running data accumulated";

    return array(
        'days' => $npoints * 10 / 86400,
        'running_hours' => $n_running * 10 / 3600,
        'heat_kwh_running' => $sum_heat * 10 / 3600000,
        'heat_kwh_all' => $sum_heat_all * 10 / 3600000,
        'elec_kwh_all' => $sum_elec_all * 10 / 3600000,
        'H_star' => $sum_heat / $sum_ideal_elec_var,
        'H_fixed' => $sum_heat / $sum_ideal_elec_fixed,
        'w_flowT' => $sum_heat_flowT / $sum_heat,
        'w_outsideT' => $sum_heat_outsideT / $sum_heat,
        'w_dT' => ($sum_heat_flowT - $sum_heat_outsideT) / $sum_heat,
        'w_dTfr' => $sum_heat_dTfr / $sum_heat,
        'spf' => $sum_elec_all > 0 ? $sum_heat_all / $sum_elec_all : null
    );
}

// Forward-fill NANs with the last valid value across gaps shorter than
// FILL_MAX_S, matching remove_null_values in the myheatpump app. Fill state
// is carried across blocks in $state; a fillable gap that spans a block
// boundary only has its current-block portion filled (the samples in the
// previous block have already been processed).
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

// Pearson correlation coefficient over an array of [x, y] pairs
function pearson($pairs)
{
    $n = count($pairs);
    if ($n < 2) return 0.0;
    $sx = 0.0; $sy = 0.0; $sxx = 0.0; $syy = 0.0; $sxy = 0.0;
    foreach ($pairs as $p) {
        $sx += $p[0]; $sy += $p[1];
        $sxx += $p[0] * $p[0]; $syy += $p[1] * $p[1];
        $sxy += $p[0] * $p[1];
    }
    $den = sqrt($n * $sxx - $sx * $sx) * sqrt($n * $syy - $sy * $sy);
    return $den > 0 ? ($n * $sxy - $sx * $sy) / $den : 0.0;
}

function calc_sd($values)
{
    $n = count($values);
    if ($n < 2) return 0.0;
    $mean = calc_mean($values);
    $sum = 0.0;
    foreach ($values as $v) $sum += ($v - $mean) * ($v - $mean);
    return sqrt($sum / ($n - 1));
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
