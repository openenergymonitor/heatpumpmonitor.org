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
    
        // -------------------------------------------------------------
        // Last 365 days
        // -------------------------------------------------------------
        $query = "";
        $query .= "SUM(combined_elec_kwh) AS total_combined_elec_kwh,";
        $query .= "SUM(combined_heat_kwh) AS total_combined_heat_kwh,";
        $query .= "SUM(combined_data_length) AS total_combined_data_length,";
        $query .= "SUM(running_elec_kwh) AS total_running_elec_kwh,";
        $query .= "SUM(running_heat_kwh) AS total_running_heat_kwh,";
        $query .= "SUM(running_data_length) AS total_running_data_length";
        
        $result = $mysqli->query("SELECT $query FROM system_stats_daily WHERE timestamp BETWEEN $start_last365 AND $end AND id = $systemid");
        $row = $result->fetch_object();
        
        $stats = array(
            'id' => $systemid,
            'elec_kwh' => number_format($row->total_combined_elec_kwh,3,'.',''),
            'heat_kwh' => number_format($row->total_combined_heat_kwh,3,'.',''),
            'cop' => number_format($row->total_combined_heat_kwh / $row->total_combined_elec_kwh,2,'.',''),
            'since' => $start_last365,
            'data_length' => $row->total_combined_data_length,
            'data_start' => 0
        );
        
        print json_encode($stats)."\n";

        $mysqli->query("DELETE FROM system_stats_last365 WHERE id=$systemid");
        $system_stats->save_stats_table('system_stats_last365',$stats);


        // -------------------------------------------------------------
        // Last 30 days
        // -------------------------------------------------------------
        $query = "";
        $query .= "SUM(combined_elec_kwh) AS total_combined_elec_kwh,";
        $query .= "SUM(combined_heat_kwh) AS total_combined_heat_kwh,";
        $query .= "SUM(combined_data_length) AS total_combined_data_length,";
        $query .= "SUM(running_elec_kwh) AS total_running_elec_kwh,";
        $query .= "SUM(running_heat_kwh) AS total_running_heat_kwh,";
        $query .= "SUM(running_data_length) AS total_running_data_length";
         
        $result = $mysqli->query("SELECT $query FROM system_stats_daily WHERE timestamp BETWEEN $start_last30 AND $end AND id = $systemid");
        $row = $result->fetch_object();
        
        $stats = array(
            'id' => $systemid,
            'elec_kwh' => number_format($row->total_combined_elec_kwh,3,'.',''),
            'heat_kwh' => number_format($row->total_combined_heat_kwh,3,'.',''),
            'cop' => number_format($row->total_combined_heat_kwh / $row->total_combined_elec_kwh,2,'.',''),
            'since' => $start_last30,
            'data_length' => $row->total_combined_data_length,
            'when_running_elec_kwh' => number_format($row->total_running_elec_kwh,3,'.',''),
            'when_running_heat_kwh' => number_format($row->total_running_heat_kwh,3,'.',''),
            'when_running_cop' => number_format($row->total_running_heat_kwh / $row->total_running_elec_kwh,2,'.',''),
            'when_running_elec_W' => null,
            'when_running_heat_W' => null,
            'when_running_flowT' => 0,
            'when_running_returnT' => 0,
            'when_running_flow_minus_return' => 0,
            'when_running_outsideT' => 0,
            'when_running_flow_minus_outside' => 0,
            'when_running_carnot_prc' => null,
            'standby_threshold' => null,
            'standby_kwh' => null,
            "quality_elec" => 0,
            "quality_heat" => 0,
            "quality_flow" => 0,
            "quality_return" => 0,
            "quality_outside" => 0,
            'data_start' => null
        );
        
        print json_encode($stats)."\n";
        $mysqli->query("DELETE FROM system_stats_last30 WHERE id=$systemid");
        $system_stats->save_stats_table('system_stats_last30',$stats);
        
    }
}
//$end = microtime(true);
//$duration = $end - $start;
print "- systems: ".count($data)."\n";
//print "- duration: ".number_format($duration,1,'.',',')."s\n";
