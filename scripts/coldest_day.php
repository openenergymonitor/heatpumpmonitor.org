<?php
$dir = dirname(__FILE__);
chdir("$dir/../www");

require "Lib/load_database.php";

require("Modules/user/user_model.php");
$user = new User($mysqli,false);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

// System stats model is used for loading system stats data
require ("Modules/system/system_stats_model.php");
$system_stats = new SystemStats($mysqli,$system);

$start_1year_ago = time() - 60*60*24*365;

$data = $system->list_admin();
foreach ($data as $row) {
    $systemid = $row->id;
    // print "System: " . $row->id." ".$row->name . "\n";

    $n = 0;

    $result = $mysqli->query("SELECT * FROM system_stats_daily WHERE `id` = '$systemid' AND `timestamp` > '$start_1year_ago' ORDER BY `combined_outsideT_mean` ASC LIMIT 50");

    while ($row = $result->fetch_object()) {
        $roomT = $row->combined_roomT_mean;
        $outsideT = $row->combined_outsideT_mean;
        $combined_cop = $row->combined_cop;
        $flowT = $row->running_flowT_mean;

        $room_minus_outside = $roomT - $outsideT;

        if ($outsideT>-10 && $outsideT<5 && $combined_cop>0) {

            $date = new DateTime();
            // London
            $date->setTimezone(new DateTimeZone('Europe/London'));
            $date->setTimestamp($row->timestamp);

            // update system_meta measured_outside_temp_coldest_day
            $mysqli->query("UPDATE system_meta SET measured_outside_temp_coldest_day = '$outsideT' WHERE `id` = '$systemid'");

            // update system_meta measured_mean_flow_temp_coldest_day
            $mysqli->query("UPDATE system_meta SET measured_mean_flow_temp_coldest_day = '$flowT' WHERE `id` = '$systemid'");

            echo $systemid."\t".$outsideT . "\t" . $roomT . "\t" . $room_minus_outside. "\t" .$combined_cop . "\t" . $date->format('Y-m-d') . "\n";
            $n++;
            if ($n>=1) {
                break;
            }
        }
    }

    // die;
}