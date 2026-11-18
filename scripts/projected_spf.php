<?php
// Projected annual SPF for systems with less than a year of data.
//
// Uses the model in scripts/projected_spf_model.json (fitted by
// analysis/performance_prediction/scripts/projected_spf_calibrate.py):
//
//   log(annual SPF) = c0 + c1·log(COP so far) + c2·ΔT + c3·outsideT + c4·hot water share
//
// with one set of coefficients per (start week, days of data) cell, and an
// error band per cell from cross-year tests. Systems without an outside
// temperature sensor use a fallback model on COP and hot water share only.
// Only systems inside the calibration fleet's region (UK, Ireland and
// north-west Europe) or with no location are projected. A projection is only stored once
// the band is within ±10%. Before that, projected_spf is left empty and
// projected_spf_days is set, so the system page can say it's waiting for
// heating season data. Run daily, after the daily stats have updated.
//
// php scripts/projected_spf.php [systemid] [--dry-run]

define('EMONCMS_EXEC', 1);
$dir = dirname(__FILE__);
chdir("$dir/../www");
require "core.php";

require "Lib/load_database.php";

require ("Modules/system/system_model.php");
$system = new System($mysqli);

$only_id = false;
$apply_changes = true;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg == "--dry-run") $apply_changes = false;
    else $only_id = (int) $arg;
}

$model = json_decode(file_get_contents("$dir/projected_spf_model.json"));
if (!$model) die("Could not load projected_spf_model.json\n");

// Only show a projection when the p10–p90 band is within ±10%
const MAX_HALF_WIDTH = 0.10;
const MIN_DAYS = 7;

foreach ($system->list_admin() as $row) {
    $systemid = (int) $row->id;
    if ($only_id && $systemid != $only_id) continue;

    $appid = (int) $row->app_id;
    if ($row->hp_type != "Air Source") $result = array("reason" => "not air source");
    else if (!in_region($row, $model->region)) $result = array("reason" => "outside the calibration region");
    else if ($appid <= 0) $result = array("reason" => "no data");
    else $result = project($emoncms_mysqli, $appid, $model);

    $p = isset($result['spf']) ? $result : false;
    $days = isset($result['days']) ? $result['days'] : null;
    echo "System $systemid ".$row->location.": ".($p
        ? sprintf("%.2f (%.2f–%.2f) from %d days, %d%% within ±10%% in testing%s", $p['spf'], $p['low'], $p['high'], $days, round(100 * $p['within10']), $p['variant'] == 'notemp' ? " (no outside temperature)" : "")
        : $result['reason'])."\n";

    if ($apply_changes) {
        $v = function ($x, $dp) { return $x === null ? "NULL" : "'" . round($x, $dp) . "'"; };
        $mysqli->query("UPDATE system_meta SET"
            . " projected_spf = " . $v($p ? $p['spf'] : null, 3)
            . ", projected_spf_low = " . $v($p ? $p['low'] : null, 3)
            . ", projected_spf_high = " . $v($p ? $p['high'] : null, 3)
            . ", projected_spf_within10 = " . $v($p ? $p['within10'] : null, 3)
            . ", projected_spf_days = " . ($days === null ? "NULL" : (int) $days)
            . " WHERE id = $systemid");
    }
}

function in_region($row, $region) {
    $lat = isset($row->latitude) ? (float) $row->latitude : 0;
    $lon = isset($row->longitude) ? (float) $row->longitude : 0;
    if ($lat == 0 && $lon == 0) return true;   // no location recorded
    return $lat >= $region->lat[0] && $lat <= $region->lat[1] && $lon >= $region->lon[0] && $lon <= $region->lon[1];
}

// Returns spf/low/high/within10/days/variant, or days + reason when there's no
// projection to show yet (days set) or none applies (days unset). Same day
// rules as the calibration: a day counts if it was monitored, with or without
// heat, so standby and summer shutdowns are included.
function project($db, $appid, $model) {
    $res = $db->query("SELECT timestamp, combined_heat_kwh, combined_elec_kwh, combined_data_length, water_heat_kwh, weighted_flowT_minus_outsideT, weighted_outsideT, cooling_heat_kwh FROM myheatpump_daily_stats WHERE id = $appid ORDER BY timestamp ASC");
    $rows = array();
    while ($r = $res->fetch_object()) $rows[] = $r;

    // data starts on the first day with heat
    $first = null;
    foreach ($rows as $r) {
        if ($r->combined_heat_kwh > 0) { $first = (int) $r->timestamp; break; }
    }
    if ($first === null) return array("reason" => "no data");
    $last = (int) end($rows)->timestamp;
    $days = (int) round(($last - $first) / 86400) + 1;
    if ($days >= 365) return array("reason" => "a full year of data");
    if ($days < MIN_DAYS) return array("reason" => "less than " . MIN_DAYS . " days");

    $H = $E = $W = $HDT = $HTO = $HT = 0; $N = $NH = $NT = 0;
    foreach ($rows as $r) {
        if ((int) $r->timestamp < $first) continue;
        if ($r->cooling_heat_kwh !== null && abs($r->cooling_heat_kwh) >= 1) return array("reason" => "cooling");
        $e = $r->combined_elec_kwh;
        if (!($r->combined_data_length > 0) || $e === null || $e < 0) continue;
        $h = $r->combined_heat_kwh !== null ? max(0, (float) $r->combined_heat_kwh) : 0;
        $H += $h; $E += $e; $N++;
        if ($h <= 0) continue;
        $W += (float) $r->water_heat_kwh; $NH++;
        $dt = $r->weighted_flowT_minus_outsideT; $to = $r->weighted_outsideT;
        if ($dt !== null && $to !== null && $dt > 0) {
            $HDT += $h * $dt; $HTO += $h * $to; $HT += $h; $NT++;
        }
    }
    if ($N < 0.6 * $days || $E <= 0 || $H <= 0) return array("reason" => "not enough monitored days", "days" => $days);

    // ΔT and outside temperature on at least half the heating days, else the fallback
    $variant = $NT >= 0.5 * $NH && $HT > 0 ? "full" : "notemp";
    $m = $model->models->$variant;
    $all = array(1, log($H / $E), $HT > 0 ? $HDT / $HT : 0, $HT > 0 ? $HTO / $HT : 0, $W / $H);
    $x = $variant == "full" ? $all : array($all[0], $all[1], $all[4]);

    // nearest start week by day of year (calendar, wraps)
    $doy = (int) gmdate("z", $first + 43200) + 1;
    $w = 0; $best = 999;
    foreach ($model->start_doy as $i => $d) {
        $dd = abs($doy - $d); $dd = min($dd, 365 - $dd);
        if ($dd < $best) { $best = $dd; $w = $i; }
    }

    // interpolate between the bracketing day counts on log(days)
    $K = $model->days;
    $d = min($days, end($K));
    $j = 0;
    while ($j < count($K) - 2 && $K[$j + 1] <= $d) $j++;
    $f = $K[$j + 1] > $K[$j] ? (log($d) - log($K[$j])) / (log($K[$j + 1]) - log($K[$j])) : 0;
    $f = max(0, min(1, $f));

    $cell = function ($k) use ($m, $w, $x) {
        $y = 0;
        foreach ($m->coef[$w][$k] as $i => $ci) $y += $ci * $x[$i];
        return array($y, $m->band[$w][$k]);
    };
    list($y0, $b0) = $cell($j);
    list($y1, $b1) = $cell($j + 1);
    $lerp = function ($a, $b) use ($f) { return $a + ($b - $a) * $f; };
    $y = $lerp($y0, $y1);
    $p10 = $lerp($b0[0], $b1[0]);
    $p90 = $lerp($b0[1], $b1[1]);
    $within10 = $lerp($b0[2], $b1[2]);

    if (($p90 - $p10) / 2 > MAX_HALF_WIDTH) return array("reason" => "waiting for heating season data", "days" => $days);

    // band is log(predicted / actual): actual = predicted / exp(e)
    $spf = exp($y);
    return array("spf" => $spf, "low" => $spf / exp($p90), "high" => $spf / exp($p10), "within10" => $within10, "days" => $days, "variant" => $variant);
}
