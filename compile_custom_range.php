<?php

$dir = dirname(__FILE__);
chdir("$dir/www");

$fp = fopen("/opt/openenergymonitor/heatpumpmonitor/hpmon.lock", "w");
if (! flock($fp, LOCK_EX | LOCK_NB)) { echo "Already running\n"; die; }

define('EMONCMS_EXEC', 1);
require "Lib/load_database.php";

require("Modules/user/user_model.php");
$user = new User($mysqli, false);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

require ("Modules/system/system_stats_model.php");
$system_stats = new SystemStats($mysqli,$system);

logger("############################################");
logger("HeatpumpMonitor.org CLI: ".date("Y-m-d H:i:s"));
logger("############################################");
logger("Settings:");
logger("- directory: $dir");

$starttimer = time();

$single_system = false;
if (isset($argv[1])) {
    $single_system = (int) $argv[1];
    if ($single_system<1) {
        $single_system = false;
    } else {
        logger("- single system: $single_system");
    }
}
if (!$single_system) {
    logger("- all systems");
}

// Load all systems
$systemlist = $system->list_admin();

$loaded_systems = 0;

foreach ($systemlist as $row) {
    $systemid = $row->id;
    if ($single_system && $systemid!=$single_system) continue;
    
    $userid = (int) $row->userid;

    if ($user_data = $user->get($userid)) {
        // $mysqli->query("DELETE FROM system_stats_all_v2 WHERE `id`='$systemid'");
        process_rolling_stats($systemlist, $systemid);
        $loaded_systems ++;
    }
}
logger("- loaded systems: ".$loaded_systems);

function process_rolling_stats($systemlist, $single_system) {
    global $mysqli, $user, $system, $system_stats;

    $date = new DateTime();
    $date->setTimezone(new DateTimeZone('Europe/London'));
    $date->modify("midnight");
    // set to 05 September 2024
    $date->setTime(0,0,0);
    $date->setDate(2024, 9, 5);
    $start = $date->getTimestamp();
    echo "- start date: ".$date->format("Y-m-d")."\n";

    // end date 1 year later
    $date->modify("+1 year");
    $end = $date->getTimestamp();
    // print end date
    echo "- end date: ".$date->format("Y-m-d")."\n";

    $processed_systems = 0;
    
    foreach ($systemlist as $row) {
        $systemid = $row->id;
        if ($single_system && $systemid!=$single_system) continue;
        
        $userid = (int) $row->userid;
        if ($user_data = $user->get($userid)) {
            // Custom range
            $stats = $system_stats->process_from_daily($systemid,$start,$end);
            if ($stats == false) continue;
            $mysqli->query("DELETE FROM system_stats_custom WHERE id=$systemid");
            $system_stats->save_stats_table('system_stats_custom',$stats);

            $processed_systems ++;            
        }
    }
    logger("- processed rolling systems: ".$processed_systems);
}

function logger($message) {
    print $message."\n";
}
