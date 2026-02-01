<?php

define('EMONCMS_EXEC', 1);
$dir = dirname(__FILE__);
chdir("$dir/../www");
require "core.php";

require "Lib/load_database.php";
$emoncms_mysqli = connect_emoncms_database();

require("Modules/user/user_model.php");
$user = new User($mysqli,false);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

// System stats model is used for loading system stats data
require ("Modules/system/system_stats_model.php");
$system_stats = new SystemStats($mysqli,$system);


$last_updated_array = array();

$timer_start = microtime(true);

$data = $system->list_admin();
foreach ($data as $row) {

    $systemid = $row->id;

    // Get app row
    $result = $emoncms_mysqli->query("SELECT * FROM app WHERE id='$row->app_id' LIMIT 1");
    if (!$app_row = $result->fetch_object()) {
        continue;
    }

    // Get app config
    $config = json_decode($app_row->config);

    $now = time();
    $heatpump_elec_feedid = false;
    $heatpump_heat_feedid = false;
    $elec_ago = false;
    $heat_ago = false;

    if (isset($config->heatpump_elec)) {
        $heatpump_elec_feedid = (int) $config->heatpump_elec;
        $elec_last_updated = $redis->hget("feed:$heatpump_elec_feedid",'time');
        $elec_ago = ($now - $elec_last_updated)/3600;
    }

    if (isset($config->heatpump_heat)) {
        $heatpump_heat_feedid = (int) $config->heatpump_heat;
        $heat_last_updated = $redis->hget("feed:$heatpump_heat_feedid",'time');
        $heat_ago = ($now - $heat_last_updated)/3600;
    }

    $last_updated_array[] = array(
        "systemid" => $systemid,
        "elec_feedid" => $heatpump_elec_feedid,
        "heat_feedid" => $heatpump_heat_feedid,
        "elec_ago" => $elec_ago,
        "heat_ago" => $heat_ago
    );
}

// Stop timer
$timer_end = microtime(true);
$timer = $timer_end - $timer_start;
echo "Processed ".count($data)." systems in ".number_format($timer,3)." seconds\n";

// Sort by least recently updated (using max of elec or heat)
usort($last_updated_array, function($a, $b) {
    $a_max = max($a['elec_ago'], $a['heat_ago']);
    $b_max = max($b['elec_ago'], $b['heat_ago']);
    return $b_max <=> $a_max;
});

// Output full list
echo "\nFull list:\n";
$count = 0;
foreach ($last_updated_array as $item) {
    // Show systems that have not updated in the last 1 hour
    $elec_stale = $item['elec_ago'] >= 1;
    $heat_stale = $item['heat_ago'] >= 1;
    
    if (!$elec_stale && !$heat_stale) continue;
    
    
    echo "System ".$item['systemid'].": Elec last updated ".number_format($item['elec_ago'],2)." hrs ago (".$item['elec_feedid']."), Heat last updated ".number_format($item['heat_ago'],2)." hrs ago (".$item['heat_feedid'].")\n";
    $count++;
}

echo "\nTotal systems with stale data: $count\n";