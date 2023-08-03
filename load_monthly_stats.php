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
    if ($userid!=2) continue;

    if ($user_data = $user->get($userid)) {
        print json_encode($user_data) . "\n\n";

        // timestamp start of July
        $date = new DateTime();
        // set timezone Europe/London
        $date->setTimezone(new DateTimeZone('Europe/London'));
        $date->setDate(2022, 6, 1);
        $date->setTime(0, 0, 0);
        $start = $date->getTimestamp();

        while (true) {
            // Print $date formatted e.g 1st of January 2023
            print $date->format('jS \of F Y') . "\n";

            // +1 month
            $date->modify('+1 month');
            $end = $date->getTimestamp();
            if ($end>time()) break;

            // print "start: $start end: $end\n";
            $stats = $system_stats->load_from_url($row->url,$start,$end);
            if ($stats===false) {
                print "Failed to load stats\n";
                continue;
            }
            $system_stats->save_monthly($row->id,$start,$stats);

            print json_encode($stats,JSON_PRETTY_PRINT) . "\n";

            $start = $end;
        }
    }
}
