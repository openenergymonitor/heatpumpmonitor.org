<?php

chdir("/var/www/heatpumpmonitororg");
require "www/Lib/load_database.php";

require("Modules/user/user_model.php");
$user = new User($mysqli);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

require ("Modules/system/system_stats_model.php");
$system_stats = new SystemStats($mysqli,$system);

$data = $system->list_admin();
foreach ($data as $row) {
    $userid = (int) $row->userid;
    if ($user_data = $user->get($userid)) {
        // $apikey_read = $user_data->apikey_read;
        // print "$apikey_read\n";
        $stats = $system_stats->load_from_url($row->url);
        if ($stats !== false) {
            $system_stats->save_last30($row->id, $stats);
            $system_stats->save_last365($row->id, $stats);
            print json_encode($stats) . "\n";        
        }
    }
}
