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
//    heat_peak_day, heat_peak_4h
//        Highest day (kWh / 24) and highest 4 hour mean heat output, all modes.
//    design_outsideT_996, design_outsideT_990
//        Hourly outside temperature equalled or exceeded for 99.6% / 99.0% of
//        hours, over complete 365 day blocks counted back from the latest data.
//    coldest_day
//        Coldest day of the last year from the daily stats, as the old
//        scripts/coldest_day.php, which also keeps the system_meta coldest day
//        fields up to date for the pages that read them.
//    flowT_typical_design, flowT_sustained_design, flowT_peak_4h
//        Space heating flow temperature against ΔT over the last 365 days,
//        read off at the design ΔT: the daily value weighted by heat output
//        (typical) and the highest 4 hour value each day (sustained, e.g.
//        coming off setback). The coldest days are listed with timestamps.
//        With no hot water flag, hot water heating counts as space heating and
//        pulls up the flow temperature of mild days, where it is a large share
//        of the heat, so only the colder days are fitted (ΔT at least half the
//        highest).
//
// Measurements that come from one period carry start_time and end_time;
// selected days are listed with their timestamps in details.
//
// php scripts/measurements/system_measurements.php [systemid] [--full] [--dry-run] [--no-scan]
//   --full      rescan every day rather than from the last stored day
//   --dry-run   print results, write nothing
//   --no-scan   skip the feed scan, work out measurements from stored days

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

const VERSION = 3;                   // 2: flowT_typical_design details.points, 3: no hot water flag fits the colder days
const PHPFINA_DIR = "/var/opt/emoncms/phpfina/";
const TIMEZONE = "Europe/London";
const FILL_MAX_S = 900;              // forward fill gaps shorter than this, as the myheatpump app
const STARTING_POWER = 100;          // running threshold (W) when the app config has none, as the daily stats
const DHW_SETTLE_S = 600;            // space heating samples excluded after DHW, while the hot water flushes
const BIN_S = 600;                   // rolling windows step in 10 minute bins
const WINDOW_BINS = 24;              // 4 hours
const WINDOW_HEAT_COVERAGE = 0.9;    // share of a 4 hour window with heat data
const WINDOW_SPACE_MIN_S = 7200;     // space heating running time in a window for its flow temperature to count
const FULL_DAY_COVERAGE = 0.95;      // share of a day with heat data for a full day
const DOT_YEAR_COVERAGE = 0.9;       // share of hours with outside temperature for a 365 day block
const FLOW_DAYS = 365;               // flow temperature fits use the latest year (weather compensation changes)
const FLOW_MIN_SPACE_KWH = 2;        // space heating per day for a flow temperature day
const MIN_FIT_DAYS = 10;
const NO_DHW_MIN_DT_SHARE = 0.5;     // no hot water flag: flow temperature fits use days with ΔT at least this share of the highest
const ROOM_T = 20;                   // design room temperature, and assumed room temperature with no feed
const PI_Z = 1.2816;                 // 80% two sided
const COLDEST_DAYS = 5;

$only_id = false;
$full = false;
$apply = true;
$scan = true;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg == "--full") $full = true;
    else if ($arg == "--dry-run") $apply = false;
    else if ($arg == "--no-scan") $scan = false;
    else if (is_numeric($arg)) $only_id = (int) $arg;
    else die("Unknown option $arg\n");
}

$tz = new DateTimeZone(TIMEZONE);

foreach ($system->list_admin() as $row) {
    $systemid = (int) $row->id;
    if ($only_id && $systemid != $only_id) continue;

    print "System $systemid ".$row->location."\n";
    $t0 = microtime(true);

    $days = load_days($mysqli, $systemid);
    if ($scan) {
        $r = scan_system($mysqli, $emoncms_mysqli, $systemid, $days, $full, $tz);
        if (is_string($r)) {
            print "  scan skipped: $r\n";
        } else {
            printf("  scanned %d days in %.1fs%s\n", count($r), microtime(true) - $t0, $r ? "" : " (none new)");
            foreach ($r as $ts => $d) $days[$ts] = $d;
            if ($apply && $r) save_days($mysqli, $systemid, $r);
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
    print "\n";
}

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

    // From the last stored day: its 4 hour windows run into the next day
    $date = new DateTime("now", $tz);
    if (!$full && count($days)) {
        $date->setTimestamp(max(array_keys($days)));
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
    $last_dhw = -PHP_INT_MAX;

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
        $sp_hf = 0; $sp_h = 0; $sp_n = 0;
        $hour_sum = array(); $hour_n = array();
        $bin = null;
        $heat_a = $d["heat"]; $elec_a = $d["elec"];
        $dhw_a = $has_dhw ? $d["dhw"] : null;
        $cool_a = $has_cool ? $d["cooling"] : null;
        $out_a = isset($d["outsideT"]) ? $d["outsideT"] : null;
        $room_a = isset($d["roomT"]) ? $d["roomT"] : null;
        $flow_a = isset($d["flowT"]) ? $d["flowT"] : null;
        $bin_n = BIN_S / 10;

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
            if ($dhw) $last_dhw = $t;

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

            // Space heating: running, not DHW, not cooling, settled after DHW
            if ($flow_a !== null && $elec == $elec && $elec >= $starting_power && !$dhw && !$cool
                && $t - $last_dhw >= DHW_SETTLE_S && $heat == $heat) {
                $f = $flow_a[$j];
                if ($f == $f) {
                    $sp_n++; $bin[5]++;
                    if ($heat > 0) {
                        $sp_hf += $heat * $f; $sp_h += $heat;
                        $bin[3] += $heat * $f; $bin[4] += $heat;
                    }
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
            'heat_max4h' => null, 'heat_max4h_start' => null,
            'space_flowT_max4h' => null, 'space_flowT_max4h_start' => null,
            'dhw_flag' => $has_dhw ? 1 : 0
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
    $base_dT = null;
    if ($hl) {
        $base_dT = $hl['base_dT'];
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
                    'ci80' => $e['ci'], 'extrapolated' => $dT > $hl['max_dT']));
        }
    }

    // Peak heat output
    $peak_day = null; $peak_4h = null;
    foreach ($days as $ts => $d) {
        if ($d['data_s'] >= FULL_DAY_COVERAGE * 86400 && $d['heat_kwh'] !== null
            && ($peak_day === null || $d['heat_kwh'] > $days[$peak_day]['heat_kwh'])) $peak_day = $ts;
        if ($d['heat_max4h'] !== null && ($peak_4h === null || $d['heat_max4h'] > $days[$peak_4h]['heat_max4h'])) $peak_4h = $ts;
    }
    if ($peak_day !== null) {
        $d = $days[$peak_day];
        $m['heat_peak_day'] = measurement($d['heat_kwh'] / 24, "kW", count($days), null, null, $peak_day, $peak_day + 86400,
            array('method' => "Highest day, heat kWh / 24, all modes", 'heat_kwh' => r($d['heat_kwh'], 2),
                'outsideT' => r($d['outsideT'], 1), 'roomT' => r($d['roomT'], 1)));
    }
    if ($peak_4h !== null) {
        $d = $days[$peak_4h];
        $s = $d['heat_max4h_start'];
        $m['heat_peak_4h'] = measurement($d['heat_max4h'], "kW", count($days), null, null, $s, $s + 14400,
            array('method' => "Highest 4 hour mean heat output, all modes", 'outsideT' => r(window_outsideT($days, $s), 1)));
    }

    // Flow temperature against ΔT over the latest year, space heating only
    $flow = flow_points($days, $base_dT);
    if ($flow && $design_dot !== null) {
        $dT = ROOM_T - $design_dot;
        $info = array('design_outsideT' => $design_dot, 'design_outsideT_source' => $design_dot_source,
            'design_dT' => $dT, 'room' => $flow['room_source'], 'dhw_split' => $flow['dhw_split'],
            'balance_point_dT' => $base_dT !== null ? r($base_dT, 2) : null,
            'fit_min_dT' => $flow['fit_min_dT'] !== null ? r($flow['fit_min_dT'], 2) : null,
            'fit_min_reason' => $flow['fit_min_reason'],
            'interval' => "80% prediction interval, a single day about the fit");

        foreach (array("flowT_typical_design" => array('space_flowT', null, "Daily space heating flow temperature weighted by heat output"),
                       "flowT_sustained_design" => array('space_flowT_max4h', 'space_flowT_max4h_start', "Highest 4 hour space heating flow temperature each day, weighted by heat output"))
                 as $name => $c) {
            $pts = array();
            foreach ($flow['days'] as $ts => $p) if ($days[$ts][$c[0]] !== null) $pts[] = array($p['dT'], $days[$ts][$c[0]], $ts);
            if (count($pts) < MIN_FIT_DAYS) continue;

            $fit = calculateRobustFit($pts, -INF, 2.5, 3);
            $pi = calculatePIStats($pts, $fit['m'], $fit['b'], -INF);
            $value = $fit['m'] * $dT + $fit['b'];
            $hw = $pi ? piHalfWidth($pi, $dT, PI_Z) : null;

            // The coldest days by ΔT, with their timestamps
            usort($pts, function ($a, $b) { return $b[0] <=> $a[0]; });
            $coldest = array();
            foreach (array_slice($pts, 0, COLDEST_DAYS) as $p) {
                $d = $days[$p[2]];
                $start = $c[1] ? $d[$c[1]] : $p[2];
                $coldest[] = array('start' => $start, 'end' => $c[1] ? $start + 14400 : $p[2] + 86400,
                    'flowT' => r($p[1], 1), 'dT' => r($p[0], 1), 'outsideT' => r($d['outsideT'], 1),
                    'space_kwh' => r($d['space_kwh'], 1));
            }

            $m[$name] = measurement($value, "°C", count($pts), $hw !== null ? $value - $hw : null, $hw !== null ? $value + $hw : null,
                $flow['start'], $flow['end'], array('method' => $c[2]) + $info + array(
                    'slope' => r($fit['m'], 4), 'intercept' => r($fit['b'], 3),
                    'pi_stats' => $pi ? array('n' => $pi['n'], 'xbar' => r($pi['xbar'], 4), 'Sxx' => r($pi['Sxx'], 3), 's' => r($pi['s'], 4)) : null,
                    'extrapolated' => $dT > $pts[0][0], 'max_dT' => r($pts[0][0], 1),
                    'coldest_days' => $coldest));
        }

        // The days behind both fits, for the dashboard chart:
        // [day, ΔT, flow temp, highest 4 h flow temp, its start, 1 fitted / 0 below fit_min_dT]
        if (isset($m['flowT_typical_design'])) {
            $points = array();
            foreach (array(1 => $flow['days'], 0 => $flow['below']) as $used => $list) {
                foreach ($list as $ts => $p) {
                    $d = $days[$ts];
                    $points[] = array($ts, r($p['dT'], 2), r($d['space_flowT'], 2), r($d['space_flowT_max4h'], 2),
                        $d['space_flowT_max4h_start'] !== null ? (int) $d['space_flowT_max4h_start'] : null, $used);
                }
            }
            usort($points, function ($a, $b) { return $a[0] <=> $b[0]; });
            $m['flowT_typical_design']['details']['points'] = $points;
        }

        // Highest 4 hour space heating flow temperature in the latest year, on the
        // fitted days (with no hot water flag, mild days' highest 4 hours are
        // often a cylinder reheat)
        $peak = null;
        foreach ($flow['days'] as $ts => $p) {
            if ($days[$ts]['space_flowT_max4h'] !== null && ($peak === null || $days[$ts]['space_flowT_max4h'] > $days[$peak]['space_flowT_max4h'])) $peak = $ts;
        }
        if ($peak !== null) {
            $s = $days[$peak]['space_flowT_max4h_start'];
            $m['flowT_peak_4h'] = measurement($days[$peak]['space_flowT_max4h'], "°C", count($flow['days']), null, null, $s, $s + 14400,
                array('method' => "Highest 4 hour space heating flow temperature in the latest year, weighted by heat output",
                    'outsideT' => r(window_outsideT($days, $s), 1), 'dhw_split' => $flow['dhw_split'],
                    'fit_min_dT' => $flow['fit_min_dT'] !== null ? r($flow['fit_min_dT'], 2) : null));
        }
    }

    return $m;
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

// Days for the flow temperature fits: the latest year, full days with space
// heating, above the balance point. With no hot water flag, only the colder
// days: hot water heating counts as space heating then, and on mild days it is
// a large share of the heat, pulling their flow temperature up and the fitted
// slope down (on a system with a flag, ignoring it took the design value from
// 35.6 to 31.9 °C; fitting the colder half of days gave 35.0 °C).
function flow_points($days, $base_dT)
{
    if (!count($days)) return null;
    $last = max(array_keys($days));
    $has_room = false;
    foreach ($days as $d) if ($d['roomT'] !== null && $d['roomT'] > 0) { $has_room = true; break; }

    $all = array(); $dhw_split = false; $max_dT = null;
    foreach ($days as $ts => $d) {
        if ($ts <= $last - FLOW_DAYS * 86400) continue;
        if ($d['data_s'] < FULL_DAY_COVERAGE * 86400 || $d['outsideT'] === null) continue;
        if ($d['space_kwh'] < FLOW_MIN_SPACE_KWH || $d['space_flowT'] === null) continue;
        $roomT = $has_room ? $d['roomT'] : ROOM_T;
        if ($roomT === null) continue;
        $all[$ts] = $roomT - $d['outsideT'];
        if ($d['dhw_flag']) $dhw_split = true;
        if ($max_dT === null || $all[$ts] > $max_dT) $max_dT = $all[$ts];
    }
    if (!count($all)) return null;

    $min_dT = $base_dT; $reason = $base_dT !== null ? "balance point" : null;
    if (!$dhw_split && ($min_dT === null || NO_DHW_MIN_DT_SHARE * $max_dT > $min_dT)) {
        $min_dT = NO_DHW_MIN_DT_SHARE * $max_dT;
        $reason = "no hot water flag";
    }

    // Days below the minimum ΔT are kept for the chart, but not fitted
    $out = array(); $below = array(); $start = null;
    foreach ($all as $ts => $dT) {
        if ($min_dT !== null && $dT < $min_dT) { $below[$ts] = array('dT' => $dT); continue; }
        $out[$ts] = array('dT' => $dT);
        if ($start === null) $start = $ts;
    }
    if (!count($out)) return null;
    return array('days' => $out, 'below' => $below, 'start' => $start, 'end' => $last + 86400,
        'room_source' => $has_room ? "feed" : "assumed", 'dhw_split' => $dhw_split,
        'fit_min_dT' => $min_dT, 'fit_min_reason' => $reason);
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
            $d[$k] = $v === null ? null : ($k == 'outsideT_hourly' ? json_decode($v, true) : $v * 1);
        }
        $days[(int) $row['timestamp']] = $d;
    }
    return $days;
}

function save_days($mysqli, $systemid, $days)
{
    $cols = array('data_s', 'heat_kwh', 'outsideT', 'roomT', 'outsideT_hourly', 'space_s', 'space_kwh', 'space_flowT',
        'heat_max4h', 'heat_max4h_start', 'space_flowT_max4h', 'space_flowT_max4h_start', 'dhw_flag');
    $stmt = $mysqli->prepare("REPLACE INTO measurement_day (system_id, timestamp, `".implode("`,`", $cols)."`) VALUES (".
        implode(",", array_fill(0, count($cols) + 2, "?")).")");
    $mysqli->begin_transaction();
    foreach ($days as $ts => $d) {
        $v = array($systemid, $ts);
        foreach ($cols as $c) {
            $x = $d[$c];
            if ($c == 'outsideT_hourly' && $x !== null) $x = json_encode($x);
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
