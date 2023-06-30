<?php

chdir("/var/www/heatpumpmonitororg");
require "www/Lib/load_database.php";

require("Modules/user/user_model.php");
$user = new User($mysqli);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

$data = $system->list();
foreach ($data as $row) {
    $userid = (int) $row->userid;
    if ($user_data = $user->get($userid)) {
        // $apikey_read = $user_data->apikey_read;
        // print "$apikey_read\n";
        $stats = $system->load_stats_from_url($row->url);
        if ($stats !== false) {
            $system->save_stats($userid, $stats);
            print json_encode($stats) . "\n";        
        }
    }
}
