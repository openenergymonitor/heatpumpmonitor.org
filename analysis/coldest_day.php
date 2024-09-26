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

$data = $system->list_admin();
foreach ($data as $row) {
    $systemid = $row->id;
    // print "System: " . $row->id." ".$row->name . "\n";

    $n = 0;

    $result = $mysqli->query("SELECT * FROM system_stats_daily WHERE `id` = '$systemid' ORDER BY `combined_outsideT_mean` ASC LIMIT 50");

    while ($row = $result->fetch_object()) {
        $roomT = $row->combined_roomT_mean;
        $outsideT = $row->combined_outsideT_mean;
        $combined_cop = $row->combined_cop;

        $room_minus_outside = $roomT - $outsideT;

        if ($roomT>0 && $outsideT<0 && $combined_cop>0) {

            $date = new DateTime();
            // London
            $date->setTimezone(new DateTimeZone('Europe/London'));
            $date->setTimestamp($row->timestamp);


            echo $systemid."\t".$outsideT . "\t" . $roomT . "\t" . $room_minus_outside. "\t" .$combined_cop . "\t" . $date->format('Y-m-d') . "\n";
            $n++;
            if ($n>=3) {
                break;
            }
        }
    }

    // die;
}