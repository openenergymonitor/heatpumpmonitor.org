<?php

// Heat loss fit functions used by scripts/measurements/system_measurements.php.
// Ported from the heat demand explorer (www/views/heatloss.php, since removed): robust line fit,
// then exclude days below the balance point and refit until it settles. The dashboard's heat
// demand tool (www/Modules/dashboard/myheatpump_heatdemand.js) uses the same method on the
// days in its window; keep the two in step when either changes.

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

// Heat demand fit with iterated exclusion of the low-ΔT DHW/standby floor.
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
