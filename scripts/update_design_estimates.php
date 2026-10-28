<?php

// Recompute the MCS 031 design estimate fields in system_meta
// (mcs031_space_spf, mcs031_spf, mcs031_spf_ratio). They are normally kept up
// to date on save and by the nightly stats update; use this to backfill.
//
// php scripts/update_design_estimates.php [systemid]

define('EMONCMS_EXEC', 1);
$dir = dirname(__FILE__);
chdir("$dir/../www");
require "core.php";

require "Lib/load_database.php";

require ("Modules/system/system_model.php");
$system = new System($mysqli);

require ("Modules/system/system_stats_model.php");
$system_stats = new SystemStats($mysqli,$system);

require ("Modules/system/design_estimates_model.php");
$design_estimates = new DesignEstimates($system_stats, false, $mysqli);

$systemid = isset($argv[1]) ? (int) $argv[1] : false;
$count = $design_estimates->update_meta($systemid);
print "Updated $count system(s)\n";
