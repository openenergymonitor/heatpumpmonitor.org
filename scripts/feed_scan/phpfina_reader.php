<?php

// PHPFina feed reading shared by the feed_scan scripts.
//
// Callers define FILL_MAX_S (forward-fill limit, s) before calling fill_nulls.

// Read phpfina metadata (interval, start_time, npoints, end_time). Returns false
// if the .meta file is missing.
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

// Read a block of $n 10s-aligned samples starting at time $t0 from a phpfina feed.
// Feeds stored at other intervals are resampled by repetition. Samples before
// the feed's start, and missing data, are NAN.
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
