<?php
$dir = dirname(__FILE__);
chdir("$dir/../www");

require "Lib/load_database.php";

require("Modules/user/user_model.php");
$user = new User($mysqli,false);

require ("Modules/system/system_model.php");
$system = new System($mysqli);
$systems = $system->list_admin();

// System stats model is used for loading system stats data
require ("Modules/system/system_stats_model.php");
$system_stats = new SystemStats($mysqli,$system);
$stats = $system_stats->get_last30(false);

$systems_with_stats = array();
foreach ($systems as $system) {
    if (isset($stats[$system->id])) {
        $systemstats = $stats[$system->id];
        foreach ($systemstats as $key => $stat) {
            $system->$key = $stat;
        }
        $systems_with_stats[] = $system;
    }
}
$systems = $systems_with_stats;

// Order systems by error_air
usort($systems, function($a, $b) {
    return $a->error_air < $b->error_air;
});

foreach ($systems as $system) {
    // Contidions for updating data_flag and data_flag_note
    if ($system->error_air == 0) continue;
    // if ($system->error_air < 3600) continue;
    // if ($system->data_flag_note != "") continue;

    echo $system->id . "\t" . $system->error_air . "\t". $system->data_flag. "\t". $system->data_flag_note. "\n";
    // Update database entry with data_flag = 1 and data_flag_note = "Heat meter air error"
    // $mysqli->query("UPDATE system_meta SET data_flag = 1, data_flag_note = 'Heat meter air error' WHERE id = $system->id");
}