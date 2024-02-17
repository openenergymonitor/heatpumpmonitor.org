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
    
        
        // Get earliest day of data for this system
        $result = $mysqli->query("SELECT MIN(timestamp) AS start FROM system_stats_daily WHERE id = $systemid");
        $row = $result->fetch_object();
        $data_start = $row->start;
        if ($data_start == null) {
            continue;
        }

        // Get last day of data for this system
        $result = $mysqli->query("SELECT MAX(timestamp) AS end FROM system_stats_daily WHERE id = $systemid");
        $row = $result->fetch_object();
        $data_end = $row->end;
        if ($data_end == null) {
            continue;
        }

        $date->setTimestamp($data_start);
        // modify to start of month
        $date->modify("midnight first day of this month");
        $start = $date->getTimestamp();
        $start_str = $date->format("Y-m-d");
        $date->modify("+1 month");
        $end = $date->getTimestamp();
        $end_str = $date->format("Y-m-d");

        // for each month
        while ($start < $data_end) {

            $stats = $system_stats->process_from_daily($systemid,$start,$end);
            if ($stats == false) {
                print "No data for system $systemid\n";
                break;
            }

            // print json_encode($stats)."\n";
            $mysqli->query("DELETE FROM system_stats_monthly_v2 WHERE id=$systemid AND timestamp=$start");
            $system_stats->save_stats_table('system_stats_monthly_v2',$stats);

            $start = $end;
            $date->modify("+1 month");
            $end = $date->getTimestamp();
            $start_str = $end_str;
            $end_str = $date->format("Y-m-d");

        }
    }
}
print "- systems: ".count($data)."\n";
