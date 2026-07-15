<?php
define('EMONCMS_EXEC', 1);
$dir = dirname(__FILE__);
chdir("$dir/../www");

// test
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
    // if ($systemid != 759) continue; // TEMP: only process system 759 for now

    echo "System ".$row->id." ".$row->location."\n";
    
    // Daily stats are stored in the emoncms.org app database, keyed by app_id
    $appid = (int) $row->app_id;
    if ($appid <= 0) continue;


    // Get current values from system_meta to compare
    $result2 = $mysqli->query("SELECT measured_outside_temp_coldest_day, measured_room_temp_coldest_day, measured_mean_flow_temp_coldest_day FROM system_meta WHERE `id` = '$systemid' LIMIT 1");
    $row2 = $result2->fetch_object();
    $last_outsideT = $row2->measured_outside_temp_coldest_day;
    $last_roomT = $row2->measured_room_temp_coldest_day;
    $last_flowT = $row2->measured_mean_flow_temp_coldest_day;

    // Find the coldest day (by combined outside temp) in the last year from the daily stats table,
    // ignoring days where the outside temp is missing (NULLs would otherwise sort first)
    $result = $emoncms_mysqli->query("SELECT * FROM myheatpump_daily_stats WHERE `id` = '$appid' AND `timestamp` > '$start_1year_ago' AND `weighted_outsideT` IS NOT NULL ORDER BY `weighted_outsideT` ASC LIMIT 1");
    $row = $result->fetch_object();

    // only clear existing values if there are no sub 4C days at all
    if (!$row || $row->weighted_outsideT >= 4) {
        echo " Skipping system $systemid - no days below 4C with outside temp data\n";

        // clear any existing values in system_meta for this system
        if ($apply_changes) {
            $mysqli->query("UPDATE system_meta SET measured_outside_temp_coldest_day = NULL, measured_room_temp_coldest_day = NULL, measured_mean_flow_temp_coldest_day = NULL WHERE `id` = '$systemid'");
        }

        continue;
    }

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