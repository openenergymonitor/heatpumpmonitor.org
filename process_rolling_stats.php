<?php
$dir = dirname(__FILE__);
chdir("$dir/www");

require "Lib/load_database.php";

require("Modules/user/user_model.php");
$user = new User($mysqli, false);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

require ("Modules/system/system_stats_model.php");
$system_stats = new SystemStats($mysqli,$system);

print "Updating rolling stats: ".date("Y-m-d H:i:s")."\n";
print "- directory: $dir\n";


$date = new DateTime();
$date->setTimezone(new DateTimeZone('Europe/London'));
$date->modify("midnight");
$end = $date->getTimestamp();

$date->modify("-30 days");
$start_last30 = $date->getTimestamp();

$date->setTimestamp($end);
$date->modify("-365 days");
$start_last365 = $date->getTimestamp();

$data = $system->list_admin();
foreach ($data as $row) {
    $systemid = $row->id;
    $userid = (int) $row->userid;
    if ($user_data = $user->get($userid)) {
    
        // Last 365 days
        $stats = $system_stats->process_from_daily($systemid,$start_last365,$end);
        if ($stats == false) continue;
        $mysqli->query("DELETE FROM system_stats_last365 WHERE id=$systemid");
        $system_stats->save_stats_table('system_stats_last365',$stats);

        // Last 30 days
        $stats = $system_stats->process_from_daily($systemid,$start_last30,$end);
        if ($stats == false) continue;
        $mysqli->query("DELETE FROM system_stats_last30 WHERE id=$systemid");
        $system_stats->save_stats_table('system_stats_last30',$stats);
        
    }
}
print "- systems: ".count($data)."\n";
