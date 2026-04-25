<?php
define('EMONCMS_EXEC', 1);
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

$apply_changes = true;

$data = $system->list_admin();
foreach ($data as $row) {
    $systemid = $row->id;
    echo "System ".$row->id." ".$row->location."\n";

    // Get current values from system_meta to compare
    $result2 = $mysqli->query("SELECT measured_outside_temp_coldest_day, measured_room_temp_coldest_day, measured_mean_flow_temp_coldest_day FROM system_meta WHERE `id` = '$systemid' LIMIT 1");
    $row2 = $result2->fetch_object();
    $last_outsideT = $row2->measured_outside_temp_coldest_day;
    $last_roomT = $row2->measured_room_temp_coldest_day;
    $last_flowT = $row2->measured_mean_flow_temp_coldest_day;


    $result = $mysqli->query("SELECT * FROM system_stats_daily WHERE `id` = '$systemid' AND `timestamp` > '$start_1year_ago' ORDER BY `combined_outsideT_mean` ASC LIMIT 1");

    while ($row = $result->fetch_object()) {
        $roomT = $row->running_roomT_mean;
        $outsideT = $row->weighted_outsideT;
        $combined_cop = $row->combined_cop;
        $flowT = $row->weighted_flowT;

        if ($outsideT>-10 && $outsideT<5 && $combined_cop>0) {

            echo_status($outsideT, $last_outsideT, "outside temp");
            echo_status ($roomT, $last_roomT, "room temp");
            echo_status ($flowT, $last_flowT, "flow temp");

            // update system_meta with all coldest day measurements in a single query
            if ($apply_changes) {

                // Update each field individually if numeric
                if (is_numeric($outsideT)) {
                    $mysqli->query("UPDATE system_meta SET measured_outside_temp_coldest_day = '$outsideT' WHERE `id` = '$systemid'");
                } else {
                    echo " Skipping outside temp update - invalid value: $outsideT\n";
                }

                if (is_numeric($roomT)) {
                    $mysqli->query("UPDATE system_meta SET measured_room_temp_coldest_day = '$roomT' WHERE `id` = '$systemid'");
                } else {
                    echo " Skipping room temp update - invalid value: $roomT\n";
                }

                if (is_numeric($flowT)) {
                    $mysqli->query("UPDATE system_meta SET measured_mean_flow_temp_coldest_day = '$flowT' WHERE `id` = '$systemid'");
                } else {
                    echo " Skipping flow temp update - invalid value: $flowT\n";
                }
            }
        }
    }

    //die;
}

function echo_status($new, $old, $variable_name) {
    if ($new > $old) {
        echo " New $variable_name: $new warmer than $old\n";
    } else if ($new == $old) {
        echo " $variable_name: $new same as $old\n";
    } else {
        echo " $variable_name: $new colder than $old\n";
    }
}