<?php
// System measurements: design parameters worked out from each system's own
// monitoring data, written to the measurement table. Run nightly, after the
// daily stats have updated. See
// analysis/performance_prediction/docs/system_page_insights.md.
//
// Two steps per system:
//
// 1. Scan the 10 s phpfina feeds day by day (Europe/London days, as the daily
//    stats) into measurement_day: space heating flow temperature weighted by
//    heat output, the highest 4 hour heat output and the highest 4 hour space
//    heating flow temperature (with their start times), and hourly outside
//    temperature. Only days from the last stored day onwards are scanned,
//    unless --full.
//
// 2. Work out the measurements from measurement_day and the daily stats:
//
//    heat_loss_htc, heat_loss_base_dT, heat_loss_design(_measured)
//        Daily heat against inside - outside ΔT (heatloss_fit.php), the same
//        method as the dashboard heat demand tool.
//        At the form's design temperature, and at the measured one below.
//    heat_coldest_days, heat_peak_4h
//        Heat output on the coldest days (as flowT_coldest_days), all modes:
//        the highest day's mean (kWh / 24) with the lowest and highest day, and
//        the highest 4 hours on those days. An all-time peak day is often a
//        boost or recovery on a mild day rather than a cold one.
//    design_outsideT_996, design_outsideT_990
//        Hourly outside temperature equalled or exceeded for 99.6% / 99.0% of
//        hours, over complete 365 day blocks counted back from the latest data.
//    coldest_day
//        Coldest day of the last year from the daily stats, as the old
//        scripts/coldest_day.php, which also keeps the system_meta coldest day
//        fields up to date for the pages that read them.
//    flowT_coldest_days, flowT_peak_4h
//        Space heating flow temperature on the 3 coldest days of the last 365
//        days (by outside temperature weighted by space heating output, as the
//        flow temperature): the highest day weighted by heat
//        output, with the lowest and highest day, and the highest 4 hours on those
//        days. The days are listed with timestamps. Read off the coldest days
//        rather than a fit extrapolated to design: with no hot water flag,
//        reheats count as space heating and skew the mild days that set a fit's
//        slope, while on cold days they are a small share of the heat.
//    flowT_map
//        Space heating over the latest year, binned by outside and flow
//        temperature (0.25 K), for the dashboard's flow temperature map: hot
//        water, outside temperatures from FLOWMAP_MAX_OUTSIDE up, and readings
//        outside FLOWMAP_MIN_OUTSIDE and FLOWMAP_MIN/MAX_FLOW (sensor faults)
//        are left out. Each 10 s sample's heat goes in at its flow and outside temperature. The scan keeps one sparse histogram per day
//        (gzipped JSON in measurement_day.flowmap); the year's sum is stored
//        as an 8 bit greyscale PNG, log scaled, with its axes in details, and
//        the space heating fit: the peak flow temperature in each 1 K of
//        outside temperature and a line through them (flowmap_fit).
//    flowT_design_inferred
//        Design flow temperature inferred from the flow map's space heating:
//        the peak (most common) flow temperature in each 1 K of outside
//        temperature, a line through them weighted by heat, then the coldest
//        FLOW_INFER_COLD_SHARE of the heat moved along that line to the design
//        outside temperature. The highest clear peak of that is the value: on
//        a weather compensation curve, the curve at design; with fixed flow
//        temperatures, the highest one run in the cold. Where the setting was
//        changed during the year there can be several peaks; all are in details.
//    dT_coldest_days, flowrate_coldest_days
//        Space heating flow - return ΔT and flow rate on the same coldest days,
//        from the daily stats: mean flow and return temperature while space
//        heating, and flow rate implied as mean heat / (cp × ΔT) rather than
//        read from the flow meter, whose units differ between systems. Mean of
//        the days weighted by space heating heat, with the lowest and highest day.
//
// Measurements that come from one period carry start_time and end_time;
// selected days are listed with their timestamps in details.
//
// php scripts/measurements/system_measurements.php [systemid] [--full] [--dry-run] [--no-scan] [--json=FILE]
//   --full        rescan every day rather than from the last stored day
//   --dry-run     print results, write nothing
//   --no-scan     skip the feed scan, work out measurements from stored days
//   --json=FILE   also write the measurements, with details, to FILE

if (PHP_SAPI !== 'cli') exit(1);

define('EMONCMS_EXEC', 1);
$dir = dirname(__FILE__);
chdir("$dir/../../www");
require "core.php";
require "Lib/load_database.php";
require "Modules/system/system_model.php";
$system = new System($mysqli);
require "Modules/system/system_stats_model.php";
$system_stats = new SystemStats($mysqli, $system);
require "$dir/../feed_scan/phpfina_reader.php";
require "$dir/heatloss_fit.php";

const VERSION = 14;                  // 2: flowT_typical_design details.points, 3: no hot water flag fits the colder days,
                                     // 4: flowT_coldest_days replaces the flow temperature fits, 5: 3 coldest days rather than 5,
                                     // 6: flowT_coldest_days is the highest of the days, not the median,
                                     // 7: no settling time after DHW, days ranked by space heating outside temperature,
                                     // 8: heat_loss_design(_measured) details.max_dT,
                                     // 9: heat_coldest_days replaces heat_peak_day, heat_peak_4h on the coldest days,
                                     // 10: dT_coldest_days, flowrate_coldest_days,
                                     // 11: flowT_map, 12: flow temperature peaks to a fraction of a bin,
                                     // 13: flow map space heating only, below FLOWMAP_MAX_OUTSIDE,
                                     // 14: flow map within FLOWMAP_MIN/MAX_OUTSIDE and FLOWMAP_MIN/MAX_FLOW
const PHPFINA_DIR = "/var/opt/emoncms/phpfina/";
const TIMEZONE = "Europe/London";
const FILL_MAX_S = 900;              // forward fill gaps shorter than this, as the myheatpump app
const CP_WATER = 4.15;               // kJ/kg/K, water at 30-45 °C, as the heat pump database's flow rate check
const STARTING_POWER = 100;          // running threshold (W) when the app config has none, as the daily stats
const BIN_S = 600;                   // rolling windows step in 10 minute bins
const WINDOW_BINS = 24;              // 4 hours
const WINDOW_HEAT_COVERAGE = 0.9;    // share of a 4 hour window with heat data
const WINDOW_SPACE_MIN_S = 7200;     // space heating running time in a window for its flow temperature to count
const FULL_DAY_COVERAGE = 0.95;      // share of a day with heat data for a full day
const DOT_YEAR_COVERAGE = 0.9;       // share of hours with outside temperature for a 365 day block
const FLOW_DAYS = 365;               // flow temperature uses the latest year (weather compensation changes)
const FLOW_MIN_SPACE_KWH = 2;        // space heating per day for a flow temperature day
const MIN_FIT_DAYS = 10;
const ROOM_T = 20;                   // design room temperature, and assumed room temperature with no feed
const PI_Z = 1.2816;                 // 80% two sided
const COLDEST_DAYS = 3;              // days for the flow temperature on the coldest days
const FLOWMAP_OUTSIDE_BINS = 4;      // flow map bins per K of outside temperature
const FLOWMAP_FLOW_BINS = 4;         // and of flow temperature
const FLOWMAP_MIN_OUTSIDE = -25;     // °C: the flow map's outside temperature range, below which readings are taken as sensor faults
const FLOWMAP_MAX_OUTSIDE = 15;      // and above which it leaves out milder outside temperatures (little space heating, mostly noise)
const FLOWMAP_MIN_FLOW = 15;         // °C: the flow map's flow temperature range; readings outside it are taken as sensor faults
const FLOWMAP_MAX_FLOW = 80;
const FLOWMAP_KEEP_DAYS = 400;       // daily flow map histograms older than this are cleared
const FLOW_INFER_COLD_SHARE = 0.2;   // inferred design flow: the coldest share of space heating heat
const FLOW_INFER_PROMINENCE = 0.15;  // a peak stands out by this share of the highest
const FLOW_INFER_MIN_COLUMN = 0.01;  // 1 K outside columns with less of the heat get no peak
const FLOWMAP_PNG_TAIL = 0.001;      // the year's PNG leaves out stray bins with this share of the heat at each end of each axis

$only_id = false;
$full = false;
$apply = true;
$scan = true;
$json_out = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg == "--full") $full = true;
    else if (preg_match('/^--json=(.+)$/', $arg, $mt)) $json_out = $mt[1];
    else if ($arg == "--dry-run") $apply = false;
    else if ($arg == "--no-scan") $scan = false;
    else if (is_numeric($arg)) $only_id = (int) $arg;
    else die("Unknown option $arg\n");
}
$json = array();

$tz = new DateTimeZone(TIMEZONE);

foreach ($system->list_admin() as $row) {
    $systemid = (int) $row->id;
    if ($only_id && $systemid != $only_id) continue;

    print "System $systemid ".$row->location."\n";
    $t0 = microtime(true);

    $days = load_days($mysqli, $systemid);
    // Days stored with the older, larger flow map: trimmed in place
    $trimmed = array();
    foreach ($days as $ts => $d) {
        if (!flowmap_current(isset($d['flowmap']) ? $d['flowmap'] : null)) continue;
        $fm = flowmap_trim($d['flowmap']);
        if ($fm == $d['flowmap']) continue;
        $days[$ts]['flowmap'] = $fm; $trimmed[$ts] = $days[$ts];
    }
    if ($trimmed) {
        print "  flow map trimmed on ".count($trimmed)." days\n";
        if ($apply) save_days($mysqli, $systemid, $trimmed);
    }
    if ($scan) {
        $r = scan_system($mysqli, $emoncms_mysqli, $systemid, $days, $full, $tz);
        if (is_string($r)) {
            print "  scan skipped: $r\n";
        } else {
            printf("  scanned %d days in %.1fs%s\n", count($r), microtime(true) - $t0, $r ? "" : " (none new)");
            foreach ($r as $ts => $d) $days[$ts] = $d;
            if ($apply && $r) save_days($mysqli, $systemid, $r);
            if ($apply && $r) $mysqli->query("UPDATE measurement_day SET flowmap = NULL WHERE system_id = $systemid AND flowmap IS NOT NULL AND timestamp < ".
                (max(array_keys($r)) - FLOWMAP_KEEP_DAYS * 86400));
        }
    }
    ksort($days);

    $meta = $mysqli->query("SELECT design_temp FROM system_meta WHERE id=$systemid")->fetch_object();
    $m = derive($system_stats, $systemid, $days, $meta);

    // Coldest day of the last year (was scripts/coldest_day.php)
    $cd = coldest_day($emoncms_mysqli, (int) $row->app_id);
    if ($cd['day']) {
        $d = $cd['day'];
        $space = isset($days[$d->timestamp]) ? $days[$d->timestamp]['space_flowT'] : null;
        $m['coldest_day'] = measurement((float) $d->weighted_outsideT, "°C", null, null, null, (int) $d->timestamp, (int) $d->timestamp + 86400,
            array('method' => "Coldest day of the last year by outside temperature weighted by heat output, from the daily stats",
                'roomT' => r(is_numeric($d->running_roomT_mean) ? (float) $d->running_roomT_mean : null, 1),
                'flowT' => r(is_numeric($d->weighted_flowT) ? (float) $d->weighted_flowT : null, 1),
                'space_flowT' => r($space, 1),
                'flowT_note' => "flowT is weighted by heat output over all modes, including hot water; space_flowT is space heating only"));
    }
    if ($apply) save_coldest_day_fields($mysqli, $systemid, $cd);

    foreach ($m as $name => $v) {
        printf("  %-26s %8s %-4s %s%s\n", $name,
            $v['value'] === null ? "-" : round($v['value'], 2), $v['unit'],
            $v['lower'] !== null ? "[".round($v['lower'], 2)." to ".round($v['upper'], 2)."] " : "",
            $v['start_time'] ? date("Y-m-d H:i", $v['start_time']) : "");
    }
    if ($apply) save_measurements($mysqli, $systemid, $m);
    if ($json_out) $json[$systemid] = $m;
    printf("  %.1fs\n\n", microtime(true) - $t0);
}
if ($json_out) file_put_contents($json_out, json_encode($json));

// ---------------------------------------------------------------------------
// Feed scan
// ---------------------------------------------------------------------------

// Scan the system's feeds from the last stored day (or the start with $full)
// to the end of the data. Returns the scanned days keyed by local midnight, or
// a reason string when the system can't be scanned.
function scan_system($mysqli, $emoncms_mysqli, $systemid, $days, $full, $tz)
{
    $result = $mysqli->query("SELECT app_id FROM system_meta WHERE id = $systemid");
    if (!$result || !($row = $result->fetch_object()) || !$row->app_id) return "no app";
    $result = $emoncms_mysqli->query("SELECT config FROM app WHERE id = ".(int) $row->app_id);
    if (!$result || !($row = $result->fetch_object())) return "no app row";
    $config = json_decode($row->config);
    if (!$config) return "no app config";

    $starting_power = STARTING_POWER;
    if (isset($config->starting_power) && is_numeric($config->starting_power) && $config->starting_power >= 0) {
        $starting_power = (float) $config->starting_power;
    }

    // phpfina feeds: heat and elec required, the rest optional
    $keys = array("elec", "heat", "flowT", "outsideT", "roomT", "dhw", "cooling");
    $meta = array();
    $fh = array();
    foreach ($keys as $k) {
        $prop = "heatpump_$k";
        if (!isset($config->$prop) || !is_numeric($config->$prop)) continue;
        $feedid = (int) $config->$prop;
        $r = $emoncms_mysqli->query("SELECT id FROM feeds WHERE id = $feedid AND engine = 5");
        if (!$r || !$r->num_rows) continue;
        $m = getmeta(PHPFINA_DIR, $feedid);
        if ($m === false || $m->npoints <= 0) continue;
        $meta[$k] = $m;
        $fh[$k] = fopen(PHPFINA_DIR."$feedid.dat", 'rb');
    }
    if (!isset($meta["heat"]) || !isset($meta["elec"])) return "heat and elec phpfina feeds required";

    // From the last stored day: its 4 hour windows run into the next day. If any
    // day of the latest year has no flow map at the current bins (stored before
    // the flow map, or at other bins), from the first such day
    $date = new DateTime("now", $tz);
    if (!$full && count($days)) {
        $from = max(array_keys($days));
        if (isset($meta["flowT"]) && isset($meta["outsideT"])) {
            foreach ($days as $ts => $d) {
                if ($ts > $from - FLOW_DAYS * 86400 && !flowmap_current(isset($d['flowmap']) ? $d['flowmap'] : null)) { $from = $ts; break; }
            }
        }
        $date->setTimestamp($from);
    } else {
        $date->setTimestamp(max($meta["heat"]->start_time, $meta["elec"]->start_time));
    }
    $date->modify("midnight");
    $end = min($meta["heat"]->end_time, $meta["elec"]->end_time);
    if ($date->getTimestamp() >= $end) return array();

    $has_dhw = isset($meta["dhw"]);
    $has_cool = isset($meta["cooling"]);
    $fill_state = array();
    foreach ($meta as $k => $m) $fill_state[$k] = array('gap' => 0, 'value' => NAN);

    $out = array();
    $bins = array();          // rolling 10 minute bins: [start, heat_sum, heat_n, sp_hf, sp_h, sp_n, day]

    while ($date->getTimestamp() < $end) {
        $day = $date->getTimestamp();
        $date->modify("+1 day");
        $next = $date->getTimestamp();
        $n = intdiv($next - $day, 10);

        $d = array();
        foreach ($meta as $k => $m) {
            $d[$k] = load_block($fh[$k], $m, $day, $n);
            fill_nulls($d[$k], $n, $fill_state[$k]);
        }

        $heat_sum = 0; $heat_n = 0; $out_sum = 0; $out_n = 0; $room_sum = 0; $room_n = 0;
        $sp_hf = 0; $sp_h = 0; $sp_n = 0; $sp_ho = 0; $sp_hoh = 0;
        $hour_sum = array(); $hour_n = array();
        $bin = null;
        $heat_a = $d["heat"]; $elec_a = $d["elec"];
        $dhw_a = $has_dhw ? $d["dhw"] : null;
        $cool_a = $has_cool ? $d["cooling"] : null;
        $out_a = isset($d["outsideT"]) ? $d["outsideT"] : null;
        $room_a = isset($d["roomT"]) ? $d["roomT"] : null;
        $flow_a = isset($d["flowT"]) ? $d["flowT"] : null;
        $bin_n = BIN_S / 10;

        // Flow map: sparse histogram of space heating (Wh) keyed "outside bin,flow bin",
        // within the FLOWMAP_MIN/MAX_OUTSIDE and FLOWMAP_MIN/MAX_FLOW ranges
        $fm = array();
        $round = function ($v) { return round($v, 1); };

        for ($j = 0; $j < $n; $j++) {
            $t = $day + $j * 10;
            if ($j % $bin_n == 0) {
                if ($bin !== null) $bins[] = $bin;
                $bin = array($t, 0, 0, 0, 0, 0, $day);
            }

            $heat = $heat_a[$j];
            $elec = $elec_a[$j];
            $dhw = $dhw_a !== null && $dhw_a[$j] >= 0.5;      // NAN >= 0.5 is false
            $cool = $cool_a !== null && $cool_a[$j] >= 0.5;

            if ($heat == $heat) {
                if ($cool) $heat = -abs($heat);
                $heat_sum += $heat; $heat_n++;
                $bin[1] += $heat; $bin[2]++;
            }

            if ($out_a !== null) {
                $o = $out_a[$j];
                if ($o == $o) {
                    $out_sum += $o; $out_n++;
                    $h = intdiv($j * 10, 3600);
                    if (!isset($hour_sum[$h])) { $hour_sum[$h] = 0; $hour_n[$h] = 0; }
                    $hour_sum[$h] += $o; $hour_n[$h]++;
                }
            }
            if ($room_a !== null) {
                $rt = $room_a[$j];
                if ($rt == $rt) { $room_sum += $rt; $room_n++; }
            }

            // Space heating: running, not DHW, not cooling. The same samples and heat
            // weighting as the dashboard's space heating stats, so the values match them
            if ($flow_a !== null && $elec == $elec && $elec >= $starting_power && !$dhw && !$cool && $heat == $heat) {
                $f = $flow_a[$j];
                if ($f == $f) {
                    $sp_n++; $bin[5]++;
                    if ($heat > 0) {
                        $sp_hf += $heat * $f; $sp_h += $heat;
                        $bin[3] += $heat * $f; $bin[4] += $heat;
                        if ($out_a !== null && $out_a[$j] == $out_a[$j]) { $sp_ho += $heat * $out_a[$j]; $sp_hoh += $heat; }
                    }
                }
            }

            // Flow map: running space heating with heat output
            if ($flow_a !== null && $out_a !== null && $heat > 0 && $elec >= $starting_power && !$cool && !$dhw) {
                $f = $flow_a[$j]; $o = $out_a[$j];
                if ($f == $f && $o == $o) {
                    $x = (int) floor($o * FLOWMAP_OUTSIDE_BINS); $y = (int) floor($f * FLOWMAP_FLOW_BINS);
                    if (flowmap_in_range($x, $y, FLOWMAP_OUTSIDE_BINS, FLOWMAP_FLOW_BINS))
                        $fm["$x,$y"] = (isset($fm["$x,$y"]) ? $fm["$x,$y"] : 0) + $heat * 10 / 3600;   // W x 10 s -> Wh
                }
            }
        }
        $bins[] = $bin;

        $hourly = array();
        $hours = intdiv($n * 10, 3600);
        for ($h = 0; $h < $hours; $h++) {
            $hourly[] = isset($hour_n[$h]) && $hour_n[$h] >= 180 ? round($hour_sum[$h] / $hour_n[$h], 2) : null;
        }

        $out[$day] = array(
            'data_s' => $heat_n * 10,
            'heat_kwh' => $heat_n ? $heat_sum * 10 / 3600000 : null,
            'outsideT' => $out_n ? $out_sum / $out_n : null,
            'roomT' => $room_n ? $room_sum / $room_n : null,
            'outsideT_hourly' => $out_n ? $hourly : null,
            'space_s' => $sp_n * 10,
            'space_kwh' => $sp_h * 10 / 3600000,
            'space_flowT' => $sp_h > 0 ? $sp_hf / $sp_h : null,
            'space_outsideT' => $sp_hoh > 0 ? $sp_ho / $sp_hoh : null,
            'heat_max4h' => null, 'heat_max4h_start' => null,
            'space_flowT_max4h' => null, 'space_flowT_max4h_start' => null,
            'dhw_flag' => $has_dhw ? 1 : 0,
            'flowmap' => ($flow_a !== null && $out_a !== null) ?
                array('ob' => FLOWMAP_OUTSIDE_BINS, 'fb' => FLOWMAP_FLOW_BINS, 's' => array_map($round, $fm)) : null
        );

        // 4 hour windows that have ended by the end of this day, credited to the day they start in
        roll_windows($bins, $out);
        if (count($bins) > WINDOW_BINS) $bins = array_slice($bins, -(WINDOW_BINS - 1));
    }
    foreach ($fh as $h) fclose($h);

    // Days with no heat data at all are not kept
    foreach ($out as $ts => $o) if (!$o['data_s']) unset($out[$ts]);
    return $out;
}

// A day's flow map is usable if it was binned as this run bins
function flowmap_current($fm)
{
    return is_array($fm) && isset($fm['ob']) && $fm['ob'] == FLOWMAP_OUTSIDE_BINS && $fm['fb'] == FLOWMAP_FLOW_BINS;
}

// Whether the bin at ($x, $y), with $ob and $fb bins per K, is within the flow
// map's ranges: outside from FLOWMAP_MIN_OUTSIDE up to (not including)
// FLOWMAP_MAX_OUTSIDE, flow from FLOWMAP_MIN_FLOW up to FLOWMAP_MAX_FLOW
function flowmap_in_range($x, $y, $ob, $fb)
{
    return $x >= FLOWMAP_MIN_OUTSIDE * $ob && $x < FLOWMAP_MAX_OUTSIDE * $ob && $y >= FLOWMAP_MIN_FLOW * $fb && $y < FLOWMAP_MAX_FLOW * $fb;
}

// A day's flow map as now stored: space heating only, within the ranges.
// Days stored before also had hot water ('w') and any temperatures
function flowmap_trim($fm)
{
    $s = array();
    foreach ($fm['s'] as $key => $wh) {
        list($x, $y) = explode(",", $key);
        if (flowmap_in_range((int) $x, (int) $y, $fm['ob'], $fm['fb'])) $s[$key] = $wh;
    }
    return array('ob' => $fm['ob'], 'fb' => $fm['fb'], 's' => $s);
}

// Slide a 4 hour window over the bins, crediting each full window to the day it starts in
function roll_windows($bins, &$out)
{
    $nb = count($bins);
    for ($i = 0; $i + WINDOW_BINS <= $nb; $i++) {
        $start = $bins[$i][0];
        $day = $bins[$i][6];
        if (!isset($out[$day])) continue;

        $heat_sum = 0; $heat_n = 0; $hf = 0; $h = 0; $sn = 0;
        for ($k = $i; $k < $i + WINDOW_BINS; $k++) {
            $heat_sum += $bins[$k][1]; $heat_n += $bins[$k][2];
            $hf += $bins[$k][3]; $h += $bins[$k][4]; $sn += $bins[$k][5];
        }
        if ($heat_n >= WINDOW_HEAT_COVERAGE * WINDOW_BINS * BIN_S / 10) {
            $kw = $heat_sum / $heat_n / 1000;
            if ($out[$day]['heat_max4h'] === null || $kw > $out[$day]['heat_max4h']) {
                $out[$day]['heat_max4h'] = $kw;
                $out[$day]['heat_max4h_start'] = $start;
            }
        }
        if ($sn * 10 >= WINDOW_SPACE_MIN_S && $h > 0) {
            $f = $hf / $h;
            if ($out[$day]['space_flowT_max4h'] === null || $f > $out[$day]['space_flowT_max4h']) {
                $out[$day]['space_flowT_max4h'] = $f;
                $out[$day]['space_flowT_max4h_start'] = $start;
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Measurements
// ---------------------------------------------------------------------------

function derive($system_stats, $systemid, $days, $meta)
{
    $m = array();

    // Design outside temperature from hourly outside temperature
    $dot = design_outside_temperature($days);
    if ($dot) {
        foreach (array("996" => 0.996, "990" => 0.990) as $key => $p) {
            $m["design_outsideT_$key"] = measurement($dot["t$key"], "°C", $dot['hours'], null, null,
                $dot['start'], $dot['end'], array(
                    'method' => "Hourly outside temperature equalled or exceeded for ".($p * 100)."% of hours",
                    'blocks' => $dot['blocks'],
                    'coldest_hour' => $dot['coldest_hour']
                ));
        }
    }

    // Design ΔT: the form's design temperature, else the measured one
    $form_dot = ($meta && is_numeric($meta->design_temp)) ? (float) $meta->design_temp : null;
    $measured_dot = $dot ? $dot['t996'] : null;
    $design_dot = $form_dot !== null ? $form_dot : $measured_dot;
    $design_dot_source = $form_dot !== null ? "form" : "measured";

    // Heat loss: the heat demand explorer's fit on the daily stats
    $hl = heat_loss_fit($system_stats, $systemid);
    if ($hl) {
        $common = array('days' => $hl['n'], 'room' => $hl['room_source'], 'room_temperature' => ROOM_T,
            'interval' => "80% prediction interval, a single day about the fit");
        $m['heat_loss_htc'] = measurement($hl['m'] * 1000, "W/K", $hl['n'], null, null, $hl['start'], $hl['end'], $common);
        $m['heat_loss_base_dT'] = measurement($hl['base_dT'], "K", $hl['n'], null, null, $hl['start'], $hl['end'], $common);
        foreach (array("heat_loss_design" => array($design_dot, $design_dot_source),
                       "heat_loss_design_measured" => array($measured_dot, "measured")) as $name => $c) {
            if ($c[0] === null) continue;
            $dT = ROOM_T - $c[0];
            $e = heat_loss_at($hl, $dT);
            $m[$name] = measurement($e['value'], "kW", $hl['n'], $e['value'] - $e['pi'], $e['value'] + $e['pi'],
                $hl['start'], $hl['end'], $common + array(
                    'design_outsideT' => $c[0], 'design_outsideT_source' => $c[1], 'design_dT' => $dT,
                    'ci80' => $e['ci'], 'extrapolated' => $dT > $hl['max_dT'], 'max_dT' => r($hl['max_dT'], 1)));
        }
    }

    // The coldest days of the latest year, shared by the heat and flow temperature measurements
    $cold = coldest_flow_days($days);

    // Heat output on the coldest days, all modes (the heat pump covers hot water
    // too): the highest day's mean (kWh / 24), and the highest 4 hours on those days
    if ($cold) {
        $kws = array(); $list = array(); $top = null; $peak = null;
        foreach ($cold['days'] as $ts) {
            $d = $days[$ts];
            if ($d['heat_kwh'] === null) continue;
            $kws[] = $d['heat_kwh'] / 24;
            $list[] = array('start' => $ts, 'end' => $ts + 86400, 'heat_kw' => r($d['heat_kwh'] / 24, 2),
                'heat_kwh' => r($d['heat_kwh'], 2), 'space_kw' => r($d['space_kwh'] / 24, 2),
                'outsideT' => r($d['space_outsideT'], 1), 'outsideT_day' => r($d['outsideT'], 1),
                'heat_4h' => r($d['heat_max4h'], 2),
                'start_4h' => $d['heat_max4h_start'] !== null ? (int) $d['heat_max4h_start'] : null);
            if ($top === null || $d['heat_kwh'] > $days[$top]['heat_kwh']) $top = $ts;
            if ($d['heat_max4h'] !== null && ($peak === null || $d['heat_max4h'] > $days[$peak]['heat_max4h'])) $peak = $ts;
        }
        if ($top !== null) {
            $d = $days[$top];
            $m['heat_coldest_days'] = measurement($d['heat_kwh'] / 24, "kW", count($kws), min($kws), max($kws), $top, $top + 86400, array(
                'method' => "Highest day's mean heat output (kWh / 24, all modes) of the coldest days of the latest year; lower and upper are the lowest and highest of those days",
                'heat_kwh' => r($d['heat_kwh'], 2), 'outsideT' => r($d['space_outsideT'], 1), 'outsideT_day' => r($d['outsideT'], 1),
                'roomT' => r($d['roomT'], 1), 'days' => $list));
        }
        if ($peak !== null) {
            $s = $days[$peak]['heat_max4h_start'];
            $m['heat_peak_4h'] = measurement($days[$peak]['heat_max4h'], "kW", count($kws), null, null, $s, $s + 14400,
                array('method' => "Highest 4 hour mean heat output, all modes, on the coldest days of the latest year",
                    'outsideT' => r(window_outsideT($days, $s), 1)));
        }
    }

    // Space heating flow temperature on the coldest days of the latest year:
    // the highest day, and the highest 4 hours on those days. With no hot water
    // flag, reheats count as space heating, but on cold days they are a small
    // share of the heat.
    if ($cold) {
        $flows = array(); $list = array(); $peak = null; $top = null;
        foreach ($cold['days'] as $ts) {
            $d = $days[$ts];
            $flows[] = $d['space_flowT'];
            $list[] = array('start' => $ts, 'end' => $ts + 86400, 'flowT' => r($d['space_flowT'], 1),
                'outsideT' => r($d['space_outsideT'], 1), 'outsideT_day' => r($d['outsideT'], 1),
                'roomT' => r($d['roomT'], 1), 'space_kwh' => r($d['space_kwh'], 1),
                'flowT_4h' => r($d['space_flowT_max4h'], 1),
                'start_4h' => $d['space_flowT_max4h_start'] !== null ? (int) $d['space_flowT_max4h_start'] : null);
            if ($top === null || $d['space_flowT'] > $days[$top]['space_flowT']) $top = $ts;
            if ($d['space_flowT_max4h'] !== null && ($peak === null || $d['space_flowT_max4h'] > $days[$peak]['space_flowT_max4h'])) $peak = $ts;
        }
        $outs = array_column($list, 'outsideT');
        $common = array('dhw_split' => $cold['dhw_split'], 'outsideT_min' => min($outs), 'outsideT_max' => max($outs));

        $m['flowT_coldest_days'] = measurement($days[$top]['space_flowT'], "°C", count($flows), min($flows), max($flows),
            $top, $top + 86400, array(
                'method' => "Highest daily space heating flow temperature, weighted by heat output, of the coldest days of the latest year; lower and upper are the lowest and highest of those days",
                'outsideT' => r($days[$top]['space_outsideT'], 1), 'outsideT_day' => r($days[$top]['outsideT'], 1),
                'outsideT_method' => "Outside temperature weighted by space heating output, as the flow temperature; outsideT_day is the plain mean over the day",
                'roomT' => r($days[$top]['roomT'], 1),
                'from' => $cold['start'], 'to' => $cold['end']) +
                $common + array('days' => $list));

        if ($peak !== null) {
            $s = $days[$peak]['space_flowT_max4h_start'];
            $m['flowT_peak_4h'] = measurement($days[$peak]['space_flowT_max4h'], "°C", count($flows), null, null, $s, $s + 14400,
                array('method' => "Highest 4 hour space heating flow temperature, weighted by heat output, on the coldest days of the latest year",
                    'outsideT' => r(window_outsideT($days, $s), 1)) + $common);
        }
    }

    // Flow map: the latest year's daily histograms summed
    $map = flowmap_year($days);
    if ($map) $m['flowT_map'] = measurement($map['days'], "days", $map['days'], null, null, $map['start'], $map['end'], $map['details']);

    // Design flow temperature inferred from the flow map's space heating
    $inf = $map && $map['fit'] && $design_dot !== null ? flow_design_inferred($map['space'], $map['fit'], $design_dot) : null;
    if ($inf) $m['flowT_design_inferred'] = measurement($inf['value'], "°C", $map['days'], null, null, $map['start'], $map['end'], array(
        'method' => "Peak flow temperature in each 1 K of outside temperature, a line through them weighted by heat, then the coldest ".
            (FLOW_INFER_COLD_SHARE * 100)."% of space heating heat moved along it to the design outside temperature: the highest clear peak",
        'design_outsideT' => $design_dot, 'design_outsideT_source' => $design_dot_source) + $inf['details']);

    // Space heating flow - return ΔT and implied flow rate on the same days
    if ($cold) {
        $hyd = coldest_days_hydraulics($system_stats, $systemid, $cold['days'], $days);
        if ($hyd) {
            $dTs = array_column($hyd['days'], 'dT');
            $frs = array_column($hyd['days'], 'flowrate');
            $common = array('heat_kw' => r($hyd['heat_kw'], 2), 'cp' => CP_WATER, 'days' => $hyd['days']);
            $m['dT_coldest_days'] = measurement(r($hyd['dT'], 2), "K", count($dTs), min($dTs), max($dTs), null, null, array(
                'method' => "Space heating mean flow - return temperature on the coldest days of the latest year (as flowT_coldest_days), from the daily stats; mean of the days weighted by space heating heat, lower and upper the lowest and highest day") + $common);
            $m['flowrate_coldest_days'] = measurement(r($hyd['flowrate'], 2), "l/min", count($frs), min($frs), max($frs), null, null, array(
                'method' => "Space heating flow rate implied as mean heat / (cp × mean ΔT) while space heating, on the same days; mean of the days weighted by space heating heat") + $common);
        }
    }

    return $m;
}

// Flow - return ΔT and implied flow rate for each of the coldest days, from the
// daily stats' space heating means (time means while space heating). With a
// steady flow rate, mean heat = cp × flow × mean ΔT, so the flow rate follows
// from the means. Days with under an hour of space heating or a ΔT under 0.5 K
// are left out
function coldest_days_hydraulics($system_stats, $systemid, $cold_days, $days)
{
    $csv = $system_stats->get_daily($systemid, min($cold_days), max($cold_days) + 86400,
        "timestamp,space_flowT_mean,space_returnT_mean,space_heat_mean,space_data_length");
    if ($csv === false || !is_string($csv)) return null;
    $list = array(); $w = 0; $wdT = 0; $wfr = 0; $wh = 0;
    foreach (array_slice(explode("
", trim($csv)), 1) as $line) {
        $p = explode(",", $line);
        if (count($p) < 5 || !in_array((int) $p[0], $cold_days)) continue;
        if (!is_numeric($p[1]) || !is_numeric($p[2]) || !is_numeric($p[3]) || !is_numeric($p[4])) continue;
        $ts = (int) $p[0];
        $dT = (float) $p[1] - (float) $p[2];
        $heat = (float) $p[3];
        $secs = (float) $p[4];
        if ($secs < 3600 || $dT < 0.5 || $heat <= 0) continue;
        $fr = $heat / (CP_WATER * 1000 * $dT) * 60;     // kg/s to l/min, about 1 kg per litre
        $kwh = $heat * $secs / 3600000;
        $list[] = array('start' => $ts, 'end' => $ts + 86400, 'dT' => r($dT, 2), 'flowrate' => r($fr, 2),
            'heat_kw' => r($heat / 1000, 2), 'space_kwh' => r($kwh, 1), 'running_h' => r($secs / 3600, 1),
            'outsideT' => isset($days[$ts]) ? r($days[$ts]['space_outsideT'], 1) : null);
        $w += $kwh; $wdT += $kwh * $dT; $wfr += $kwh * $fr; $wh += $kwh * $heat;
    }
    if (!$list || $w <= 0) return null;
    return array('dT' => $wdT / $w, 'flowrate' => $wfr / $w, 'heat_kw' => $wh / $w / 1000, 'days' => $list);
}

// Heat demand fit (heatloss_fit.php, from the old heat demand explorer) on the daily stats
function heat_loss_fit($system_stats, $systemid)
{
    $fields = "timestamp,combined_heat_mean,combined_roomT_mean,combined_outsideT_mean,weighted_flowT," .
        "running_returnT_mean,combined_data_length,cooling_heat_kwh";
    $csv = $system_stats->get_daily($systemid, false, false, $fields);
    if ($csv === false || !is_string($csv)) return null;

    $lines = explode("\n", trim($csv));
    $header = explode(",", array_shift($lines));
    $col = array_flip($header);
    $rows = array();
    $valid_room = false;
    foreach ($lines as $line) {
        $p = explode(",", $line);
        if (count($p) != count($header)) continue;
        $rows[] = $p;
        if ((float) $p[$col['combined_roomT_mean']] > 0) $valid_room = true;
    }

    $pts = array(); $start = null; $end = null; $max_dT = 0;
    foreach ($rows as $p) {
        $roomT = $valid_room ? (float) $p[$col['combined_roomT_mean']] : ROOM_T;
        if (!($roomT > 0 && (float) $p[$col['combined_data_length']] > 64800)) continue;
        if ((float) $p[$col['weighted_flowT']] == 0 && (float) $p[$col['running_returnT_mean']] == 0) continue;
        if ($p[$col['combined_outsideT_mean']] === "") continue;
        $x = $roomT - (float) $p[$col['combined_outsideT_mean']];
        $y = (float) $p[$col['combined_heat_mean']] * 0.001;
        $cool = (float) $p[$col['cooling_heat_kwh']];
        if ($cool > 0) $y -= $cool / 24.0;
        $pts[] = array($x, $y);
        $ts = (int) $p[$col['timestamp']];
        if ($start === null || $ts < $start) $start = $ts;
        if ($end === null || $ts > $end) $end = $ts;
        if ($x > $max_dT) $max_dT = $x;
    }
    if (count($pts) < MIN_FIT_DAYS) return null;

    $hd = calculateHeatDemandFit($pts, 0);
    $m = $hd['m']; $b = $hd['b']; $base = $hd['base_DT'];
    if ($base < 0) {
        $m = calculateSlopeWithZeroIntercept($pts);
        $b = 0; $base = 0;
    }
    if (!($m > 0)) return null;

    return array('m' => $m, 'b' => $b, 'base_dT' => $base, 'n' => count($pts), 'pts' => $pts,
        'pi' => calculatePIStats($pts, $m, $b, 0), 'start' => $start, 'end' => $end + 86400,
        'max_dT' => $max_dT, 'room_source' => $valid_room ? "feed" : "assumed");
}

// Heat demand at a design ΔT with 80% prediction and confidence half widths
function heat_loss_at($hl, $dT)
{
    $value = $hl['m'] * $dT + $hl['b'];
    $pi = null; $ci = null;
    if ($hl['pi']) {
        $s = $hl['pi'];
        $pi = piHalfWidth($s, $dT, PI_Z);
        $ci = PI_Z * $s['s'] * sqrt(1 / $s['n'] + ($dT - $s['xbar']) * ($dT - $s['xbar']) / $s['Sxx']);
    }
    return array('value' => $value, 'pi' => $pi, 'ci' => $ci !== null ? r($ci, 3) : null);
}

// The coldest days of the latest year: full days with space heating flow
// temperature, by outside temperature weighted by space heating output (the
// conditions the flow temperature was run at). Coldest first.
function coldest_flow_days($days)
{
    if (!count($days)) return null;
    $last = max(array_keys($days));
    $all = array(); $dhw_split = false;
    foreach ($days as $ts => $d) {
        if ($ts <= $last - FLOW_DAYS * 86400) continue;
        if ($d['data_s'] < FULL_DAY_COVERAGE * 86400 || $d['space_outsideT'] === null) continue;
        if ($d['space_kwh'] < FLOW_MIN_SPACE_KWH || $d['space_flowT'] === null) continue;
        $all[$ts] = $d['space_outsideT'];
        if ($d['dhw_flag']) $dhw_split = true;
    }
    if (!count($all)) return null;
    $start = min(array_keys($all));
    asort($all);
    return array('days' => array_slice(array_keys($all), 0, COLDEST_DAYS), 'dhw_split' => $dhw_split,
        'start' => $start, 'end' => $last + 86400);
}

// Flow map over the latest year: the daily space heating histograms summed,
// then stored as an 8 bit greyscale PNG. Columns are outside temperature bins,
// rising left to right; rows are flow temperature bins, highest at the top. A
// pixel of 0 is no heat, otherwise Wh = exp((v - 1) / 254 × ln(max_wh)): log
// scaled, so the small cells keep about 1% resolution
function flowmap_year($days)
{
    if (!count($days)) return null;
    $last = max(array_keys($days));
    $sum = array();
    $n = 0; $start = null;
    foreach ($days as $ts => $d) {
        if ($ts <= $last - FLOW_DAYS * 86400 || !flowmap_current(isset($d['flowmap']) ? $d['flowmap'] : null)) continue;
        $fm = flowmap_trim($d['flowmap']);
        foreach ($fm['s'] as $key => $wh) $sum[$key] = (isset($sum[$key]) ? $sum[$key] : 0) + $wh;
        if (count($fm['s'])) $n++;
        if ($start === null) $start = $ts;
    }
    if (!$n) return null;

    // Bin indexes, and the range once the stray bins at either end are left out
    $cells = array(); $cx = array(); $cy = array(); $tot = 0;
    foreach ($sum as $key => $wh) {
        list($x, $y) = explode(",", $key);
        $x = (int) $x; $y = (int) $y;
        $cells[] = array($x, $y, $wh);
        $cx[$x] = (isset($cx[$x]) ? $cx[$x] : 0) + $wh;
        $cy[$y] = (isset($cy[$y]) ? $cy[$y] : 0) + $wh;
        $tot += $wh;
    }
    if ($tot <= 0) return null;
    list($xlo, $xhi) = flowmap_span($cx, FLOWMAP_PNG_TAIL * $tot);
    list($ylo, $yhi) = flowmap_span($cy, FLOWMAP_PNG_TAIL * $tot);
    $nx = $xhi - $xlo + 1; $ny = $yhi - $ylo + 1;

    $im = imagecreate($nx, $ny);
    for ($v = 0; $v < 256; $v++) imagecolorallocate($im, $v, $v, $v);   // palette index = grey level
    $mx = 0;
    foreach ($cells as $c) if ($c[0] >= $xlo && $c[0] <= $xhi && $c[1] >= $ylo && $c[1] <= $yhi && $c[2] > $mx) $mx = $c[2];
    $lnmax = log(max($mx, 1.001));
    foreach ($cells as $c) {
        if ($c[0] < $xlo || $c[0] > $xhi || $c[1] < $ylo || $c[1] > $yhi || $c[2] < 0.5) continue;
        $v = 1 + (int) round(254 * log(max($c[2], 1)) / $lnmax);
        imagesetpixel($im, $c[0] - $xlo, $yhi - $c[1], min(255, $v));
    }
    ob_start();
    imagepng($im, null, 9);
    $png = ob_get_clean();

    $space = flowmap_parse($sum);
    $fit = $space ? flowmap_fit($space) : null;

    return array('days' => $n, 'start' => $start, 'end' => $last + 86400, 'space' => $space, 'fit' => $fit, 'details' => array(
        'method' => "Space heating from each 10 s sample binned by its outside and flow temperature, running only, ".FLOWMAP_MIN_OUTSIDE." to ".FLOWMAP_MAX_OUTSIDE." °C outside and ".FLOWMAP_MIN_FLOW." to ".FLOWMAP_MAX_FLOW." °C flow, hot water and cooling left out; the latest year's daily histograms summed",
        'x0' => $xlo / FLOWMAP_OUTSIDE_BINS, 'x_step' => 1 / FLOWMAP_OUTSIDE_BINS, 'nx' => $nx,
        'y0' => $ylo / FLOWMAP_FLOW_BINS, 'y_step' => 1 / FLOWMAP_FLOW_BINS, 'ny' => $ny,
        'encoding' => "png8_log", 'layout' => "space heating rows, highest flow first",
        'max_wh' => array('space' => round($mx, 1)), 'kwh' => array('space' => round($tot / 1000, 1)),
        'png' => base64_encode($png),
        'fit' => $fit ? array('method' => "Peak (most common) space heating flow temperature in each 1 K of outside temperature, and a line through them weighted by heat",
            'slope' => round($fit['b'], 3), 'intercept' => round($fit['a'], 2), 'within_2K' => round($fit['within'], 3),
            'peaks' => $fit['peaks']) : null));
}

// The year's space heating histogram ("x,y" => Wh at FLOWMAP_*_BINS per K) as
// columns of outside temperature bins, coldest first: [x][y] => Wh
function flowmap_parse($hist)
{
    $cols = array(); $tot = 0; $ymin = PHP_INT_MAX; $ymax = PHP_INT_MIN;
    foreach ($hist as $key => $wh) {
        list($x, $y) = explode(",", $key);
        $x = (int) $x; $y = (int) $y;
        $cols[$x][$y] = $wh; $tot += $wh;
        $ymin = min($ymin, $y); $ymax = max($ymax, $y);
    }
    if ($tot <= 0) return null;
    ksort($cols);
    return array('cols' => $cols, 'tot' => $tot, 'ymin' => $ymin, 'ny' => $ymax - $ymin + 1);
}

// Flow temperature at the centre of row $j (or a fraction of a row) of a parsed histogram
function flowmap_flow($g, $j)
{
    return ($j + $g['ymin'] + 0.5) / FLOWMAP_FLOW_BINS;
}

// Peak (most common) flow temperature in each 1 K of outside temperature, and a
// line through them weighted by heat; within is the share of the heat within
// ±2 K of the line. Peaks are [outside, flow, share of the heat]
function flowmap_fit($g)
{
    $groups = array();
    foreach ($g['cols'] as $x => $c) {
        $k = (int) floor($x / FLOWMAP_OUTSIDE_BINS);
        if (!isset($groups[$k])) $groups[$k] = array_fill(0, $g['ny'], 0.0);
        foreach ($c as $y => $wh) $groups[$k][$y - $g['ymin']] += $wh;
    }
    $pts = array();
    foreach ($groups as $k => $p) {
        $w = array_sum($p);
        if ($w < FLOW_INFER_MIN_COLUMN * $g['tot']) continue;
        $ps = flow_smooth($p);
        $pts[] = array($k + 0.5, flowmap_flow($g, flow_peak_at($ps, array_search(max($ps), $ps))), $w);
    }
    if (count($pts) < 3) return null;
    $sw = 0; $sx = 0; $sy = 0; $sxx = 0; $sxy = 0;
    foreach ($pts as $q) { $sw += $q[2]; $sx += $q[2] * $q[0]; $sy += $q[2] * $q[1]; $sxx += $q[2] * $q[0] * $q[0]; $sxy += $q[2] * $q[0] * $q[1]; }
    $den = $sw * $sxx - $sx * $sx;
    if ($den <= 0) return null;
    $b = ($sw * $sxy - $sx * $sy) / $den; $a = ($sy - $b * $sx) / $sw;

    $within = 0;
    foreach ($g['cols'] as $x => $c) {
        $line = $a + $b * ($x + 0.5) / FLOWMAP_OUTSIDE_BINS;
        foreach ($c as $y => $wh) if (abs(($y + 0.5) / FLOWMAP_FLOW_BINS - $line) <= 2) $within += $wh;
    }
    return array('a' => $a, 'b' => $b, 'within' => $within / $g['tot'],
        'peaks' => array_map(function ($q) use ($g) { return array($q[0], round($q[1], 2), round($q[2] / $g['tot'], 3)); }, $pts));
}

// Design flow temperature from the parsed space heating histogram and its fit:
// see flowT_design_inferred at the top
function flow_design_inferred($g, $fit, $design_dot)
{
    $fb = FLOWMAP_FLOW_BINS; $ny = $g['ny']; $tot = $g['tot']; $b = $fit['b'];

    // The coldest share of the heat, each column moved along the line to the design outside temperature
    $prof = array_fill(0, $ny, 0.0); $acc = 0; $cold_max = null;
    foreach ($g['cols'] as $x => $c) {
        if ($cold_max !== null && $acc + array_sum($c) > FLOW_INFER_COLD_SHARE * $tot) break;
        $acc += array_sum($c);
        $xc = ($x + 0.5) / FLOWMAP_OUTSIDE_BINS; $cold_max = ($x + 1) / FLOWMAP_OUTSIDE_BINS;
        $shift = $b * ($design_dot - $xc) * $fb;           // bins
        $p = array_fill(0, $ny, 0.0);
        foreach ($c as $y => $wh) $p[$y - $g['ymin']] += $wh;
        for ($j = 0; $j < $ny; $j++) {
            $src = $j - $shift; $j0 = (int) floor($src); $f = $src - $j0;
            $v = ($j0 >= 0 && $j0 < $ny ? (1 - $f) * $p[$j0] : 0) + ($j0 + 1 >= 0 && $j0 + 1 < $ny ? $f * $p[$j0 + 1] : 0);
            $prof[$j] += $v;
        }
    }
    $total = array_sum($prof);
    if ($total <= 0) return null;

    // Clear peaks of that, each with its share of the heat within ±0.5 K
    $ps = flow_smooth($prof); $top = max($ps); $bands = array();
    for ($j = 1; $j < $ny - 1; $j++) {
        if (!($ps[$j] >= $ps[$j - 1] && $ps[$j] > $ps[$j + 1])) continue;
        $l = $j; while ($l > 0 && $ps[$l - 1] <= $ps[$j]) $l--;
        $r = $j; while ($r < $ny - 1 && $ps[$r + 1] <= $ps[$j]) $r++;
        $prom = $ps[$j] - max(min(array_slice($ps, $l, $j - $l + 1)), min(array_slice($ps, $j, $r - $j + 1)));
        if ($prom < FLOW_INFER_PROMINENCE * $top) continue;
        $half = (int) round(0.5 * $fb); $near = 0;
        for ($q = max(0, $j - $half); $q <= min($ny - 1, $j + $half); $q++) $near += $prof[$q];
        $bands[] = array('flowT' => round(flowmap_flow($g, flow_peak_at($ps, $j)), 2), 'share' => round($near / $total, 3), 'prominence' => round($prom / $top, 2));
    }
    if (!count($bands)) return null;
    $value = max(array_column($bands, 'flowT'));

    return array('value' => $value, 'details' => array(
        'slope' => round($b, 3), 'at_design' => round($fit['a'] + $b * $design_dot, 2),
        'cold_share' => FLOW_INFER_COLD_SHARE, 'cold_outsideT_max' => $cold_max,
        'bands' => $bands));
}

// Row of a peak at $j of a smoothed profile, to a fraction of a row: the top
// of a parabola through it and its neighbours
function flow_peak_at($ps, $j)
{
    if ($j <= 0 || $j >= count($ps) - 1) return $j;
    $den = $ps[$j - 1] - 2 * $ps[$j] + $ps[$j + 1];
    return $den < 0 ? $j + max(-0.5, min(0.5, 0.5 * ($ps[$j - 1] - $ps[$j + 1]) / $den)) : $j;
}

// Gaussian smoothing, sigma 1 bin
function flow_smooth($p)
{
    static $k = null;
    if ($k === null) {
        $k = array(); $s = 0;
        for ($i = -4; $i <= 4; $i++) { $k[$i] = exp(-0.5 * $i * $i); $s += $k[$i]; }
        foreach ($k as $i => $v) $k[$i] = $v / $s;
    }
    $n = count($p); $out = array_fill(0, $n, 0.0);
    for ($j = 0; $j < $n; $j++) {
        $v = 0;
        for ($i = -4; $i <= 4; $i++) if ($j + $i >= 0 && $j + $i < $n) $v += $k[$i] * $p[$j + $i];
        $out[$j] = $v;
    }
    return $out;
}

// First and last bin of $sums (bin => heat) once more than $tail has been passed from each end
function flowmap_span($sums, $tail)
{
    ksort($sums);
    $keys = array_keys($sums);
    $a = 0; $b = count($keys) - 1; $acc = 0;
    while ($a < $b && $acc + $sums[$keys[$a]] <= $tail) $acc += $sums[$keys[$a++]];
    $acc = 0;
    while ($b > $a && $acc + $sums[$keys[$b]] <= $tail) $acc += $sums[$keys[$b--]];
    return array($keys[$a], $keys[$b]);
}

// 99.6% and 99.0% design outside temperature over complete 365 day blocks
// counted back from the latest day with outside temperature
function design_outside_temperature($days)
{
    $hours = array();   // timestamp => hourly mean
    foreach ($days as $ts => $d) {
        if (!$d['outsideT_hourly']) continue;
        foreach ($d['outsideT_hourly'] as $h => $v) if ($v !== null) $hours[$ts + $h * 3600] = $v;
    }
    if (!count($hours)) return null;
    ksort($hours);
    $times = array_keys($hours);
    $last = end($times) + 3600;
    $first = $times[0];

    $blocks = array(); $pooled = array(); $coldest = null;
    for ($end = $last; $end - 365 * 86400 >= $first; $end -= 365 * 86400) {
        $start = $end - 365 * 86400;
        $v = array();
        foreach ($hours as $t => $x) if ($t >= $start && $t < $end) $v[] = $x;
        $coverage = count($v) / (365 * 24);
        $block = array('start' => $start, 'end' => $end, 'hours' => count($v), 'coverage' => r($coverage, 3));
        if ($coverage >= DOT_YEAR_COVERAGE) {
            sort($v);
            $block['t996'] = percentile_exceeded($v, 0.996);
            $block['t990'] = percentile_exceeded($v, 0.990);
            $block['min'] = $v[0];
            $pooled = array_merge($pooled, $v);
        }
        $blocks[] = $block;
    }
    if (!count($pooled)) return null;
    sort($pooled);
    foreach ($hours as $t => $x) if ($coldest === null || $x < $coldest['outsideT']) $coldest = array('start' => $t, 'end' => $t + 3600, 'outsideT' => $x);

    $used = array_filter($blocks, function ($b) { return isset($b['t996']); });
    return array('t996' => percentile_exceeded($pooled, 0.996), 't990' => percentile_exceeded($pooled, 0.990),
        'hours' => count($pooled), 'blocks' => $blocks, 'coldest_hour' => $coldest,
        'start' => min(array_column($used, 'start')), 'end' => max(array_column($used, 'end')));
}

// Temperature equalled or exceeded for share $p of the sorted values
function percentile_exceeded($sorted, $p)
{
    return $sorted[(int) floor((1 - $p) * count($sorted))];
}

// Mean outside temperature over the 4 hours from $start, from the hourly values
function window_outsideT($days, $start)
{
    $sum = 0; $n = 0;
    foreach ($days as $ts => $d) {
        if (!$d['outsideT_hourly'] || $ts > $start || $ts + 90000 < $start) continue;
        foreach ($d['outsideT_hourly'] as $h => $v) {
            $t = $ts + $h * 3600;
            if ($v !== null && $t >= $start - 3599 && $t < $start + 14400) { $sum += $v; $n++; }
        }
    }
    return $n ? $sum / $n : null;
}

function measurement($value, $unit, $samples, $lower, $upper, $start, $end, $details)
{
    return array('value' => $value, 'unit' => $unit, 'samples' => $samples, 'lower' => $lower, 'upper' => $upper,
        'start_time' => $start, 'end_time' => $end, 'details' => $details);
}

function r($v, $dp)
{
    return $v === null ? null : round($v, $dp);
}

// Coldest day of the last year, by outside temperature weighted by heat output,
// from the daily stats. Same rules as the old scripts/coldest_day.php, so the
// system_meta coldest day fields read by the system page, the coldest day view
// and the home page flow temperature view don't change:
//   no day below 4 °C                       -> clear the fields
//   -10 < outside < 5 °C and a positive COP -> set them from that day
//   otherwise                               -> leave them as they are
// Returns array('action' => clear|set|keep, 'day' => daily stats row or null)
function coldest_day($emoncms_mysqli, $appid)
{
    if ($appid <= 0) return array('action' => 'keep', 'day' => null);
    $since = time() - 365 * 86400;
    $result = $emoncms_mysqli->query("SELECT timestamp, weighted_outsideT, running_roomT_mean, weighted_flowT, combined_cop FROM myheatpump_daily_stats " .
        "WHERE id = $appid AND timestamp > $since AND weighted_outsideT IS NOT NULL ORDER BY weighted_outsideT ASC LIMIT 1");
    $row = $result ? $result->fetch_object() : null;
    if (!$row || $row->weighted_outsideT >= 4) return array('action' => 'clear', 'day' => null);
    if ($row->weighted_outsideT > -10 && $row->weighted_outsideT < 5 && $row->combined_cop > 0) return array('action' => 'set', 'day' => $row);
    return array('action' => 'keep', 'day' => null);
}

function save_coldest_day_fields($mysqli, $systemid, $cd)
{
    if ($cd['action'] == 'clear') {
        $mysqli->query("UPDATE system_meta SET measured_outside_temp_coldest_day = NULL, measured_room_temp_coldest_day = NULL, " .
            "measured_mean_flow_temp_coldest_day = NULL, measured_coldest_day_timestamp = NULL WHERE id = $systemid");
    } else if ($cd['action'] == 'set') {
        $d = $cd['day'];
        $num = function ($v) { return is_numeric($v) ? (float) $v : null; };
        $outsideT = $num($d->weighted_outsideT);
        $roomT = $num($d->running_roomT_mean);
        $flowT = $num($d->weighted_flowT);
        $ts = (int) $d->timestamp;
        $stmt = $mysqli->prepare("UPDATE system_meta SET measured_outside_temp_coldest_day = ?, measured_room_temp_coldest_day = ?, " .
            "measured_mean_flow_temp_coldest_day = ?, measured_coldest_day_timestamp = ? WHERE id = ?");
        $stmt->bind_param("dddii", $outsideT, $roomT, $flowT, $ts, $systemid);
        $stmt->execute();
    }
}

// ---------------------------------------------------------------------------
// Storage
// ---------------------------------------------------------------------------

function load_days($mysqli, $systemid)
{
    $days = array();
    $result = $mysqli->query("SELECT * FROM measurement_day WHERE system_id = $systemid ORDER BY timestamp");
    while ($row = $result->fetch_assoc()) {
        $d = array();
        foreach ($row as $k => $v) {
            if ($k == 'system_id' || $k == 'timestamp') continue;
            if ($v === null) $d[$k] = null;
            else if ($k == 'outsideT_hourly') $d[$k] = json_decode($v, true);
            else if ($k == 'flowmap') $d[$k] = json_decode(gzdecode($v), true);
            else $d[$k] = $v * 1;
        }
        $days[(int) $row['timestamp']] = $d;
    }
    return $days;
}

function save_days($mysqli, $systemid, $days)
{
    $cols = array('data_s', 'heat_kwh', 'outsideT', 'roomT', 'outsideT_hourly', 'space_s', 'space_kwh', 'space_flowT', 'space_outsideT',
        'heat_max4h', 'heat_max4h_start', 'space_flowT_max4h', 'space_flowT_max4h_start', 'dhw_flag', 'flowmap');
    $stmt = $mysqli->prepare("REPLACE INTO measurement_day (system_id, timestamp, `".implode("`,`", $cols)."`) VALUES (".
        implode(",", array_fill(0, count($cols) + 2, "?")).")");
    $mysqli->begin_transaction();
    foreach ($days as $ts => $d) {
        $v = array($systemid, $ts);
        foreach ($cols as $c) {
            $x = $d[$c];
            if ($c == 'outsideT_hourly' && $x !== null) $x = json_encode($x);
            else if ($c == 'flowmap' && $x !== null) $x = gzencode(json_encode($x, JSON_FORCE_OBJECT), 6);
            else if (is_float($x) && !is_finite($x)) $x = null;
            $v[] = $x;
        }
        $stmt->bind_param(str_repeat("s", count($v)), ...$v);
        $stmt->execute();
    }
    $mysqli->commit();
}

// Replace the system's measurements with this run's
function save_measurements($mysqli, $systemid, $m)
{
    $now = time();
    $mysqli->begin_transaction();
    $mysqli->query("DELETE FROM measurement WHERE system_id = $systemid");
    $stmt = $mysqli->prepare("INSERT INTO measurement (system_id, name, value, lower, upper, unit, samples, start_time, end_time, details, version, updated) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    foreach ($m as $name => $x) {
        $details = json_encode($x['details']);
        $version = VERSION;
        $stmt->bind_param("isdddsiiisii", $systemid, $name, $x['value'], $x['lower'], $x['upper'], $x['unit'],
            $x['samples'], $x['start_time'], $x['end_time'], $details, $version, $now);
        $stmt->execute();
    }
    $mysqli->commit();
}

