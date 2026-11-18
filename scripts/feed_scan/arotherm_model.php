<?php

// Vaillant Arotherm+ datasheet model shared by replay.php and lowmod.php, so
// their speed bands and model electricity agree.

// Vaillant Arotherm+ generic v2 COP fit, ported from www/lib/vaillant_cop_fit.js
// in the tools repo (copFitGenericV2, estimateSpeed). Pre-defrost, no standby.

// Heat output per rps as a share of nominal capacity, used to infer speed
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

// COP at the measured flowT, outsideT and output
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
