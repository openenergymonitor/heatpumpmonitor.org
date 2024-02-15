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

//$start = microtime(true);
$data = $system->list_admin();
foreach ($data as $row) {
    $systemid = $row->id;
    if ($row->id!=2) continue;
    $userid = (int) $row->userid;
    if ($user_data = $user->get($userid)) {
    
        
        // Get earliest day of data for this system
        $result = $mysqli->query("SELECT MIN(timestamp) AS start FROM system_stats_daily WHERE id = $systemid");
        $row = $result->fetch_object();
        $data_start = $row->start;

        // Get last day of data for this system
        $result = $mysqli->query("SELECT MAX(timestamp) AS end FROM system_stats_daily WHERE id = $systemid");
        $row = $result->fetch_object();
        $data_end = $row->end;

        $date->setTimestamp($data_start);
        // modify to start of month
        $date->modify("midnight first day of this month");
        $start = $date->getTimestamp();
        $start_str = $date->format("Y-m-d");
        $date->modify("+1 month");
        $end = $date->getTimestamp();
        $end_str = $date->format("Y-m-d");

        $cw = 16;

        // print header use padding to make it line up
        print str_pad("id",$cw).str_pad("start",$cw).str_pad("end",$cw).str_pad("elec_kwh",$cw).str_pad("heat_kwh",$cw).str_pad("cop",$cw).str_pad("flowT_mean",$cw)."\n";

        // for each month
        while ($start < $data_end) {

            $stats = $system_stats->process_from_daily($systemid,$start,$end);

            // print all values
            // print "-----------------------------------------------------------\n";
            print str_pad($systemid,$cw).str_pad($start_str,$cw).str_pad($end_str,$cw).str_pad($stats['elec_kwh'],$cw).str_pad($stats['heat_kwh'],$cw).str_pad($stats['cop'],$cw).str_pad($stats['when_running_flowT'],$cw).str_pad($stats['data_length'],$cw)."\n";
            // print "-----------------------------------------------------------\n";
            
            // print json_encode($stats)."\n";
            $mysqli->query("DELETE FROM system_stats_monthly WHERE id=$systemid AND timestamp=$start");
            $system_stats->save_stats_table('system_stats_monthly',$stats);

            $start = $end;
            $date->modify("+1 month");
            $end = $date->getTimestamp();
            $start_str = $end_str;
            $end_str = $date->format("Y-m-d");

        }
    }
}
//$end = microtime(true);
//$duration = $end - $start;
print "- systems: ".count($data)."\n";
//print "- duration: ".number_format($duration,1,'.',',')."s\n";
