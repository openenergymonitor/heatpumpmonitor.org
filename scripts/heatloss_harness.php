<?php
/**
 * Heat loss / flow temperature fit harness.
 *
 * Runs the SAME fitting logic as www/views/heatloss.php against real daily-stats
 * data (fetched through the same get_daily() code path the browser tool uses), so
 * fit changes can be tested and tuned headlessly without screenshots.
 *
 * Usage:
 *   php scripts/heatloss_harness.php <id[,id,...] | all> [--design=23] [--minDT=0] [--dump] [--save]
 *
 * Examples:
 *   php scripts/heatloss_harness.php 123
 *   php scripts/heatloss_harness.php 123,456,789
 *   php scripts/heatloss_harness.php all --minDT=2
 *   php scripts/heatloss_harness.php all --save     # write fit results to system_meta
 *
 * The PHP fit functions below MIRROR the JS in heatloss.php 1:1. When the JS
 * algorithm changes, mirror the change here (and vice versa) so results match.
 */

define('EMONCMS_EXEC', 1);
require_once __DIR__ . '/common.php';

$dir = dirname(__FILE__);
chdir("$dir/../www");

require "Lib/load_database.php";
require "Modules/user/user_model.php";
$user = new User($mysqli, false);
require "Modules/system/system_model.php";
$system = new System($mysqli);
require "Modules/system/system_stats_model.php";
$system_stats = new SystemStats($mysqli, $system);

// ---------------------------------------------------------------------------
// Fit functions (ported from www/views/heatloss.php — keep in sync)
// ---------------------------------------------------------------------------

// Ordinary least squares over points [[x,y],...] with x >= min_x
function calculateLineOfBestFit($points, $min_x) {
    $xSum = 0; $ySum = 0; $xySum = 0; $xxSum = 0; $n = 0;
    foreach ($points as $p) {
        if ($p[0] >= $min_x) {
            $xSum += $p[0]; $ySum += $p[1];
            $xxSum += $p[0] * $p[0]; $xySum += $p[0] * $p[1];
            $n++;
        }
    }
    if ($n < 2) return array('m' => 0, 'b' => 0);
    $denom = ($n * $xxSum - $xSum * $xSum);
    $m = $denom != 0 ? ($n * $xySum - $xSum * $ySum) / $denom : 0;
    $b = ($ySum - $m * $xSum) / $n;
    return array('m' => $m, 'b' => $b);
}

// OLS with iterative outlier trimming (drop residuals > k*s, refit)
function calculateRobustFit($points, $min_x, $k, $maxIter) {
    $pts = array();
    foreach ($points as $p) if ($p[0] >= $min_x) $pts[] = $p;
    if (count($pts) < 3) return calculateLineOfBestFit($points, $min_x);

    $fit = calculateLineOfBestFit($pts, -INF);
    for ($iter = 0; $iter < $maxIter; $iter++) {
        $sse = 0;
        foreach ($pts as $p) { $r = $p[1] - ($fit['m'] * $p[0] + $fit['b']); $sse += $r * $r; }
        $s = sqrt($sse / max(1, count($pts) - 2));
        if ($s <= 0) break;
        $thresh = $k * $s;
        $kept = array();
        foreach ($pts as $p) if (abs($p[1] - ($fit['m'] * $p[0] + $fit['b'])) <= $thresh) $kept[] = $p;
        if (count($kept) === count($pts) || count($kept) < 3) break;
        $pts = $kept;
        $fit = calculateLineOfBestFit($pts, -INF);
    }
    return $fit;
}

// Heat-demand fit with iterated exclusion of the low-DT DHW/standby floor (see JS notes).
// Returns array('m'=>, 'b'=>, 'base_DT'=>).
function calculateHeatDemandFit($dataPoints, $min_x) {
    $fit = calculateRobustFit($dataPoints, $min_x, 2.5, 3);
    $base = $fit['m'] != 0 ? (0 - $fit['b']) / $fit['m'] : $min_x;

    for ($it = 0; $it < 6; $it++) {
        if ($base <= $min_x) break; // nothing below the filter to exclude
        $f = calculateRobustFit($dataPoints, $base, 2.5, 3);
        $nb = $f['m'] != 0 ? (0 - $f['b']) / $f['m'] : $base;
        $fit = $f;
        if (abs($nb - $base) < 0.1) { $base = $nb; break; }
        $base = $nb;
    }

    return array('m' => $fit['m'], 'b' => $fit['b'], 'base_DT' => $base);
}

function calculateSlopeWithZeroIntercept($points) {
    $xySum = 0; $xxSum = 0;
    foreach ($points as $p) { $xxSum += $p[0] * $p[0]; $xySum += $p[0] * $p[1]; }
    return $xxSum != 0 ? $xySum / $xxSum : 0;
}

// Weighted least squares over [['x','y','w'],...]
function weightedOLS($pts) {
    $Sw = 0; $Swx = 0; $Swy = 0; $Swxx = 0; $Swxy = 0;
    foreach ($pts as $p) {
        $w = $p['w']; $x = $p['x']; $y = $p['y'];
        $Sw += $w; $Swx += $w * $x; $Swy += $w * $y; $Swxx += $w * $x * $x; $Swxy += $w * $x * $y;
    }
    $denom = $Sw * $Swxx - $Swx * $Swx;
    if ($denom == 0) return array('m' => 0, 'b' => $Sw > 0 ? $Swy / $Sw : 0);
    $m = ($Sw * $Swxy - $Swx * $Swy) / $denom;
    $b = ($Swy - $m * $Swx) / $Sw;
    return array('m' => $m, 'b' => $b);
}

// Weighted OLS with the slope clamped to >= 0. A negative weather-compensation
// slope (flow temperature falling as it gets colder) is physically implausible
// and usually an artifact of residual DHW contamination, so fall back to the
// horizontal weighted-mean line instead.
function clampedWeightedOLS($pts) {
    $fit = weightedOLS($pts);
    if ($fit['m'] < 0) {
        $Sw = 0; $Swy = 0;
        foreach ($pts as $p) { $Sw += $p['w']; $Swy += $p['w'] * $p['y']; }
        $fit = array('m' => 0, 'b' => $Sw > 0 ? $Swy / $Sw : 0);
    }
    return $fit;
}

function weightedResidStd($pts, $fit) {
    $sw = 0; $swr2 = 0;
    foreach ($pts as $p) {
        $r = $p['y'] - ($fit['m'] * $p['x'] + $fit['b']);
        $sw += $p['w']; $swr2 += $p['w'] * $r * $r;
    }
    return $sw > 0 ? sqrt($swr2 / $sw) : 0;
}

// Flow-temp fit: demand-weighted + asymmetric (lower-envelope) trimming
// (k values default to the values used in heatloss.php; exposed for tuning)
function calculateWeightedFlowFit($points, $min_x, $base_DT, $maxIter, $kHigh = 1.25, $kLow = 3.0, $kBand = 2.0) {
    $all = array();
    foreach ($points as $p) {
        $x = $p[0];
        if ($x < $min_x) continue;
        $w = $x - $base_DT;
        if ($w <= 0) continue;
        $all[] = array('x' => $x, 'y' => $p[1], 'w' => $w, 'i' => $p[2]);
    }
    if (count($all) < 3) return null;

    $pts = $all;
    $fit = clampedWeightedOLS($pts);
    $s = 0;

    for ($iter = 0; $iter < $maxIter; $iter++) {
        $s = weightedResidStd($pts, $fit);
        if ($s <= 0) break;
        $kept = array();
        foreach ($pts as $p) {
            $r = $p['y'] - ($fit['m'] * $p['x'] + $fit['b']);
            if ($r <= $kHigh * $s && $r >= -$kLow * $s) $kept[] = $p;
        }
        if (count($kept) === count($pts) || count($kept) < 3) break;
        $pts = $kept;
        $fit = clampedWeightedOLS($pts);
    }

    if ($s > 0) {
        $band = array();
        foreach ($all as $p) {
            if (abs($p['y'] - ($fit['m'] * $p['x'] + $fit['b'])) <= $kBand * $s) $band[] = $p;
        }
        if (count($band) >= 3) { $pts = $band; $fit = clampedWeightedOLS($pts); }
    }

    return array('m' => $fit['m'], 'b' => $fit['b'], 'inliers' => $pts);
}

// Prediction-interval stats about y = m*x + b over points with x >= minx
function calculatePIStats($points, $m, $b, $minx) {
    $n = 0; $xSum = 0;
    foreach ($points as $p) { if ($p[0] < $minx) continue; $n++; $xSum += $p[0]; }
    if ($n < 3) return null;
    $xbar = $xSum / $n;
    $Sxx = 0; $sse = 0;
    foreach ($points as $p) {
        if ($p[0] < $minx) continue;
        $r = $p[1] - ($m * $p[0] + $b);
        $Sxx += ($p[0] - $xbar) * ($p[0] - $xbar);
        $sse += $r * $r;
    }
    if ($Sxx <= 0) return null;
    return array('n' => $n, 'xbar' => $xbar, 'Sxx' => $Sxx, 's' => sqrt($sse / ($n - 2)));
}

function piHalfWidth($stats, $x0, $z) {
    return $z * $stats['s'] * sqrt(1 + 1 / $stats['n'] + ($x0 - $stats['xbar']) * ($x0 - $stats['xbar']) / $stats['Sxx']);
}

// ---------------------------------------------------------------------------
// Argument parsing
// ---------------------------------------------------------------------------

$args = array_slice($argv, 1);
$idarg = null;
$design_DT_override = null;
$min_DT = 0;
$dump = false;
$sweep = false;
$save = false;
$kHigh = 1.25; $kLow = 3.0; $kBand = 2.0;
foreach ($args as $a) {
    if (strpos($a, '--design=') === 0) $design_DT_override = (float) substr($a, 9);
    else if (strpos($a, '--minDT=') === 0) $min_DT = (float) substr($a, 8);
    else if (strpos($a, '--kHigh=') === 0) $kHigh = (float) substr($a, 8);
    else if (strpos($a, '--kLow=') === 0) $kLow = (float) substr($a, 7);
    else if (strpos($a, '--kBand=') === 0) $kBand = (float) substr($a, 8);
    else if ($a === '--sweep') $sweep = true;
    else if ($a === '--profile') $profile = true;
    else if ($a === '--dump') $dump = true;
    else if ($a === '--save') $save = true;
    else if ($idarg === null) $idarg = $a;
}
if (!isset($profile)) $profile = false;

// Median of an array of numbers
function median_of($vals) {
    if (count($vals) == 0) return null;
    sort($vals);
    $n = count($vals);
    $mid = (int) floor($n / 2);
    return $n % 2 ? $vals[$mid] : ($vals[$mid - 1] + $vals[$mid]) / 2;
}

// Linear-interpolated quantile (q in [0,1]) of an array of numbers
function quantile_of($vals, $q) {
    if (count($vals) == 0) return null;
    sort($vals);
    $n = count($vals);
    $idx = $q * ($n - 1);
    $lo = (int) floor($idx); $hi = (int) ceil($idx);
    if ($lo == $hi) return $vals[$lo];
    $frac = $idx - $lo;
    return $vals[$lo] * (1 - $frac) + $vals[$hi] * $frac;
}

if ($idarg === null) {
    fwrite(STDERR, "Usage: php scripts/heatloss_harness.php <id[,id,...] | all> [--design=23] [--minDT=0] [--dump]\n");
    exit(1);
}

// Resolve system id list
$ids = array();
if ($idarg === 'all') {
    foreach ($system->list_admin() as $row) $ids[] = (int) $row->id;
} else {
    foreach (explode(',', $idarg) as $part) if (trim($part) !== '') $ids[] = (int) trim($part);
}

$PI_Z = 1.2816;
$fields = "timestamp,combined_heat_mean,combined_roomT_mean,combined_outsideT_mean," .
          "weighted_flowT,running_returnT_mean,combined_elec_kwh,combined_heat_kwh," .
          "combined_data_length,cooling_heat_kwh";

$summary = array();

foreach ($ids as $systemid) {

    // Meta (location, saved design DT, outside design temperature)
    $meta_res = $mysqli->query("SELECT location, measured_design_DT, design_temp, hp_output, heat_loss FROM system_meta WHERE id=$systemid LIMIT 1");
    $meta = $meta_res ? $meta_res->fetch_object() : null;
    $location = $meta ? $meta->location : '';

    // Design DT resolution (mirrors heatloss.php load): start from the saved
    // measured_design_DT (default 23), then prefer the system's outside design
    // temperature with the default 20C target room temperature when on record.
    $target_roomT = 20;
    $design_DT = ($meta && $meta->measured_design_DT > 0) ? (float) $meta->measured_design_DT : 23;
    if ($meta && $meta->design_temp !== null && $meta->design_temp !== '' && is_numeric($meta->design_temp)) {
        $design_DT = round(($target_roomT - (float) $meta->design_temp) * 10) / 10;
    }
    if ($design_DT_override !== null) $design_DT = $design_DT_override;

    // Fetch daily stats via the same code path as the browser tool
    $csv = $system_stats->get_daily($systemid, false, false, $fields);
    if ($csv === false) { echo "System $systemid: no data\n\n"; continue; }

    $lines = explode("\n", trim($csv));
    $header = explode(",", array_shift($lines));
    $col = array_flip($header);

    // Detect valid room temperature; fall back to fixed 20 like the tool
    $rows = array();
    $valid_room = false;
    foreach ($lines as $line) {
        $parts = explode(",", $line);
        if (count($parts) != count($header)) continue;
        $rows[] = $parts;
        if ((float) $parts[$col['combined_roomT_mean']] > 0) $valid_room = true;
    }

    // Build heat_vs_dt (mirrors the tool's filtering)
    $heat_vs_dt = array();
    $flow_vs_dt = array();
    foreach ($rows as $i => $parts) {
        $roomT = $valid_room ? (float) $parts[$col['combined_roomT_mean']] : 20;
        $data_length = (float) $parts[$col['combined_data_length']];
        if (!($roomT > 0 && $data_length > 64800)) continue;

        $flowT = (float) $parts[$col['weighted_flowT']];
        $returnT = (float) $parts[$col['running_returnT_mean']];
        if ($flowT == 0 && $returnT == 0) continue; // invalid: pump not running

        $x = $roomT - (float) $parts[$col['combined_outsideT_mean']];
        $y = (float) $parts[$col['combined_heat_mean']] * 0.001;
        $cooling_kwh = (float) $parts[$col['cooling_heat_kwh']];
        if ($cooling_kwh > 0) $y -= $cooling_kwh / 24.0;

        $heat_vs_dt[] = array($x, $y, $i);
        if ($flowT > 0) $flow_vs_dt[] = array($x, $flowT, $i);
    }

    if (count($heat_vs_dt) < 3) { echo "System $systemid ($location): only " . count($heat_vs_dt) . " valid points\n\n"; continue; }

    // Heat fit: iterated floor exclusion (mirrors auto_fit in heatloss.php,
    // including its toFixed roundings which feed the downstream fits)
    $hd = calculateHeatDemandFit($heat_vs_dt, $min_DT);
    $hfit = array('m' => $hd['m'], 'b' => $hd['b']);
    $base_DT = round($hd['base_DT'], 1);
    $measured_heatloss = round($hd['m'] * $design_DT + $hd['b'], 2);
    if ($base_DT < 0) {
        $slope0 = calculateSlopeWithZeroIntercept($heat_vs_dt);
        $base_DT = 0;
        $measured_heatloss = round($slope0 * $design_DT, 2);
    }

    // Heat loss PI (about the base_DT -> design line)
    $heat_pi_txt = 'n/a';
    $measured_heatloss_range = null;
    if ($design_DT > $base_DT) {
        $pm = $measured_heatloss / ($design_DT - $base_DT);
        $pb = -$pm * $base_DT;
        $pi = calculatePIStats($heat_vs_dt, $pm, $pb, $min_DT);
        if ($pi) {
            $measured_heatloss_range = round(piHalfWidth($pi, $design_DT, $PI_Z), 2);
            $heat_pi_txt = '+/-' . number_format($measured_heatloss_range, 2);
        }
    }

    // Contamination-free reference: median flow temp of the coldest 10% of days
    // (space-heating dominated, so a robust anchor for where design_flowT should sit)
    $n_above_balance = 0;
    foreach ($flow_vs_dt as $p) if ($p[0] - $base_DT > 0) $n_above_balance++;
    $sorted_by_dt = $flow_vs_dt;
    usort($sorted_by_dt, function($a, $b) { return $b[0] <=> $a[0]; });
    $decile = max(3, (int) ceil(count($sorted_by_dt) * 0.10));
    $cold_flow = array(); $cold_dt = array();
    for ($ci = 0; $ci < $decile && $ci < count($sorted_by_dt); $ci++) {
        $cold_flow[] = $sorted_by_dt[$ci][1];
        $cold_dt[] = $sorted_by_dt[$ci][0];
    }
    $cold_median = median_of($cold_flow);
    $cold_dt_min = count($cold_dt) ? min($cold_dt) : 0;

    // Flow fit (demand-weighted + asymmetric trimming)
    $ffit = calculateWeightedFlowFit($flow_vs_dt, $min_DT, $base_DT, 5, $kHigh, $kLow, $kBand);
    $design_flowT = null; $flow_pi_txt = 'n/a'; $flow_slope = null;
    $design_flowT_range = null;
    $n_inliers = $ffit ? count($ffit['inliers']) : 0;

    if ($ffit && $design_DT > $base_DT) {
        $design_flowT = round($ffit['m'] * $design_DT + $ffit['b'], 1);
        $flow_slope = $ffit['m'];
        $inl = array();
        foreach ($ffit['inliers'] as $p) $inl[] = array($p['x'], $p['y']);
        $fpi = calculatePIStats($inl, $ffit['m'], $ffit['b'], -INF);
        if ($fpi) {
            $design_flowT_range = round(piHalfWidth($fpi, $design_DT, $PI_Z), 1);
            $flow_pi_txt = '+/-' . number_format($design_flowT_range, 1);
        }
    }

    // Report
    printf("System %d  %s\n", $systemid, $location);
    printf("  points: %d valid   design_DT=%.0f  min_DT=%.1f\n", count($heat_vs_dt), $design_DT, $min_DT);
    printf("  heat:   base_DT=%.2f  slope=%.4f kW/K  heat_loss@design=%.2f kW  PI=%s\n",
        $base_DT, $hfit['m'], $measured_heatloss, $heat_pi_txt);

    printf("  ref:    coldest %d days (DT>=%.1f) median flowT=%.1f C  <-- design_flowT should be near/above this\n",
        $decile, $cold_dt_min, $cold_median);
    if ($design_flowT !== null) {
        printf("  flow:   design_flowT=%.1f C  PI=%s  slope=%.3f C/K  inliers=%d/%d above-balance (excluded DHW=%d)  [kHigh=%.2f]\n",
            $design_flowT, $flow_pi_txt, $flow_slope, $n_inliers, $n_above_balance, $n_above_balance - $n_inliers, $kHigh);
    } else {
        printf("  flow:   no fit (%d points above balance point)\n", $n_above_balance);
    }

    // Persist the fit to system_meta (same fields the frontend Save button writes)
    if ($save) {
        $sv_base_DT = (float) $base_DT;
        $sv_design_DT = (float) $design_DT;
        $sv_heatloss = (float) $measured_heatloss;
        $sv_heatloss_range = $measured_heatloss_range !== null ? (float) $measured_heatloss_range : 0.0;
        $sv_flowT = $design_flowT !== null ? (float) $design_flowT : 0.0;
        $sv_flowT_range = $design_flowT_range !== null ? (float) $design_flowT_range : 0.0;

        $stmt = $mysqli->prepare(
            "UPDATE system_meta SET measured_base_DT=?, measured_design_DT=?, measured_heat_loss=?, " .
            "measured_heat_loss_range=?, measured_design_flowT=?, measured_design_flowT_range=? WHERE id=?"
        );
        $stmt->bind_param("ddddddi", $sv_base_DT, $sv_design_DT, $sv_heatloss,
            $sv_heatloss_range, $sv_flowT, $sv_flowT_range, $systemid);
        if ($stmt->execute()) {
            printf("  saved:  base_DT=%.1f design_DT=%.1f heat_loss=%.2f (+/-%.2f) design_flowT=%.1f (+/-%.1f)\n",
                $sv_base_DT, $sv_design_DT, $sv_heatloss, $sv_heatloss_range, $sv_flowT, $sv_flowT_range);
        } else {
            printf("  saved:  FAILED (%s)\n", $stmt->error);
        }
        $stmt->close();
    }

    // DT-binned profile: p25 is DHW-resistant (DHW is always the UPPER contamination),
    // so its trend across DT reveals the true space-heating flow-temp shape (flat vs rising)
    if ($profile) {
        echo "  DT-binned flow profile (p25 = space-heating estimate):\n";
        echo "     DT band     n    p25   median   p75\n";
        $binw = 2.0;
        $maxdt = 0;
        foreach ($flow_vs_dt as $p) if ($p[0] > $maxdt) $maxdt = $p[0];
        for ($d = 0; $d <= $maxdt; $d += $binw) {
            $vals = array();
            foreach ($flow_vs_dt as $p) if ($p[0] >= $d && $p[0] < $d + $binw) $vals[] = $p[1];
            if (count($vals) < 5) continue;
            printf("    %5.1f-%-5.1f %4d  %5.1f  %5.1f  %5.1f\n",
                $d, $d + $binw, count($vals),
                quantile_of($vals, 0.25), quantile_of($vals, 0.50), quantile_of($vals, 0.75));
        }
    }

    // DT-binned heat-output profile vs the robust fit line. If 'fit' sits near p50
    // while cold-weather demand really tracks p75/p90 (days with least solar/internal
    // gains), the mean-fit under-estimates fabric heat loss (the gains bias).
    if ($profile) {
        echo "  DT-binned heat-output profile (p50/p75/p90 vs robust-fit line):\n";
        echo "     DT band     n    p50    p75    p90    fit\n";
        $binw = 2.0;
        $maxdt = 0;
        foreach ($heat_vs_dt as $p) if ($p[0] > $maxdt) $maxdt = $p[0];
        for ($d = 0; $d <= $maxdt; $d += $binw) {
            $vals = array();
            foreach ($heat_vs_dt as $p) if ($p[0] >= $d && $p[0] < $d + $binw) $vals[] = $p[1];
            if (count($vals) < 5) continue;
            $c = $d + $binw / 2;
            printf("    %5.1f-%-5.1f %4d  %5.2f  %5.2f  %5.2f  %5.2f\n",
                $d, $d + $binw, count($vals),
                quantile_of($vals, 0.50), quantile_of($vals, 0.75), quantile_of($vals, 0.90),
                $hfit['m'] * $c + $hfit['b']);
        }
    }

    // Sweep kHigh to show sensitivity of the fit to trimming aggressiveness
    if ($sweep && $design_DT > $base_DT) {
        echo "  sweep kHigh -> design_flowT (slope, inliers):\n";
        foreach (array(2.0, 1.5, 1.25, 1.0, 0.75, 0.5) as $kh) {
            $f = calculateWeightedFlowFit($flow_vs_dt, $min_DT, $base_DT, 8, $kh, $kLow, $kBand);
            if ($f) {
                printf("    kHigh=%.2f  flowT=%.1f C  slope=%+.3f C/K  inliers=%d\n",
                    $kh, $f['m'] * $design_DT + $f['b'], $f['m'], count($f['inliers']));
            }
        }
    }
    echo "\n";

    if ($dump) {
        $scratch = "/tmp/heatloss_system_$systemid.csv";
        $fh = fopen($scratch, 'w');
        fwrite($fh, "type,dt,value,index\n");
        foreach ($heat_vs_dt as $p) fwrite($fh, "heat,{$p[0]},{$p[1]},{$p[2]}\n");
        foreach ($flow_vs_dt as $p) fwrite($fh, "flow,{$p[0]},{$p[1]},{$p[2]}\n");
        fclose($fh);
        echo "  dumped points -> $scratch\n\n";
    }

    $summary[] = array($systemid, $location, count($heat_vs_dt),
        number_format($base_DT, 1), number_format($measured_heatloss, 2),
        $design_flowT !== null ? number_format($design_flowT, 1) : '-',
        $flow_slope !== null ? number_format($flow_slope, 3) : '-',
        "$n_inliers/$n_above_balance");
}

if (count($summary) > 1) {
    echo "Summary\n";
    print_table($summary, array('id', 'location', 'pts', 'baseDT', 'heatloss', 'flowT', 'flowslope', 'inl/bal'));
}
