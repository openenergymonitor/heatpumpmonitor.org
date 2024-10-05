<?php
$dir = dirname(__FILE__);
chdir("$dir/../www");

require "Lib/load_database.php";

// User model is required for session management
require("Modules/user/user_model.php");
$user = new User($mysqli,false);

// System model is used for loading system meta data
require ("Modules/system/system_model.php");
$system = new System($mysqli);
$systems = $system->list_public();

// System stats model is used for loading system stats data
require ("Modules/system/system_stats_model.php");
$system_stats = new SystemStats($mysqli,$system);
$stats = $system_stats->get_last365(false);

// Add in stats data to systems
$systems_with_stats = array();
foreach ($systems as $system) {
    if (isset($stats[$system->id])) {
        $systemstats = $stats[$system->id];
        foreach ($systemstats as $key => $stat) {
            $system->$key = $stat;
        }
        $systems_with_stats[] = $system;
    }
}
$systems = $systems_with_stats;

// Order systems by combined_cop
usort($systems, function($a, $b) {
    return $a->combined_cop < $b->combined_cop;
});

// Filter out systems with less than 290 days of data
// use combined_data_length field (seconds) to determine if system has enough data
$systems = array_filter($systems, function($system) {
    return $system->combined_data_length >= 290*86400;
});

// For each of these systems load daily data in the last 365 days
// Find coldest day

// Midnight this morning
$date = new DateTime();
$date->setTimezone(new DateTimeZone('Europe/London'));
$date->modify("midnight");
$end = $date->getTimestamp();

// Midnight 365 days ago
$date->modify("-365 days");
$start = $date->getTimestamp();

$mode = "running";

foreach ($systems as $system) {
    $daily = $system_stats->get_daily($system->id,$start,$end,"timestamp,".$mode."_cop,".$mode."_flowT_mean,".$mode."_outsideT_mean,combined_flowT_mean,combined_heat_mean");
    // response is csv data, first line is headers
    $daily = explode("\n",$daily);

    $keys = explode(",",$daily[0]);
    $key_length = count($keys);

    $daily = array_slice($daily,1);
    

    // Find coldest day
    $coldest = false;
    
    foreach ($daily as $line) {

        $values = explode(",",$line);

        if (count($values) != $key_length) {
            continue;
            
        }

        $keyval = array();
        foreach ($keys as $i => $key) {
            $key = trim($key);
            $keyval[$key] = 1*$values[$i];
        }

        if ($keyval["timestamp"]<$start || $keyval["timestamp"]>$end) {
            continue;
        }

        if ($keyval[$mode."_cop"] == 0) {
            continue;
        }

        if (!$coldest) {
            $coldest = $keyval;
        }

        if ($keyval[$mode."_outsideT_mean"] < $coldest[$mode."_outsideT_mean"]) {
            $coldest = $keyval;
        }
    }

    $system->coldest = $coldest;

}

// Print headers
print "id,location,hp_output,hp_model,flow_temp,design_temp,".$mode."_cop,combined_heat,".$mode."_outsideT,".$mode."_flowT,space_cop,combined_flowT,weighted_average,date\n";

// Print all systems
foreach ($systems as $system) {

    if (!isset($system->coldest) || !$system->coldest) {
        continue;
    }

    $diff = $system->coldest[$mode."_flowT_mean"] - $system->coldest["combined_flowT_mean"];
    // abs
    $diff = abs($diff);

    if ($diff>0.3) {
        // continue;
    }

    // skip if coldest_flow = 0
    if ($system->coldest[$mode."_flowT_mean"] == 0 || $system->coldest[$mode."_outsideT_mean"] == 0) {
        continue;
    }

    // skip if not ASHP
    if ($system->hp_type != "Air Source") {
        continue;
    }

    $line = array();

    // system id
    $line[] = $system->id;

    // remote comma from location
    $system->location = str_replace(",","",$system->location);

    $line[] = trim($system->location);
    $line[] = trim($system->hp_output);
    $line[] = trim($system->hp_model);

    $line[] = number_format($system->flow_temp,1, ".", "");
    $line[] = number_format($system->design_temp,1, ".", "");

    $line[] = number_format($system->combined_cop,2, ".", "");
    $line[] = round($system->coldest["combined_heat_mean"],1);

    $line[] = number_format($system->coldest[$mode."_outsideT_mean"],1, ".", "");
    $line[] = number_format($system->coldest[$mode."_flowT_mean"],1, ".", "");
    $line[] = number_format($system->coldest[$mode."_cop"],2, ".", "");
    $line[] = number_format($system->coldest["combined_flowT_mean"],1, ".", "");

    // Get weighted average flow temp from histogram tool
    $url = "https://heatpumpmonitor.org/histogram/kwh_at_flow_minus_outside?id=$system->id&start=$start&end=$end&x_min=0&x_max=60";

    $data = file_get_contents($url);
    $data = json_decode($data,true);

    $avg_x = 0;

    if (isset($data["data"])) {
        $sum = 0;
        $sum_y = 0;

        foreach ($data["data"] as $i => $y) {
            $x = $data["min"] + $i*$data["div"];
            $sum += $x * $y;
            $sum_y += $y;
        }

        $avg_x = $sum / $sum_y;
    }

    $line[] = number_format($avg_x,1, ".", "");

    // Update system_meta field weighted_average_flow_minus_outside with this value
    $mysqli->query("UPDATE system_meta SET `weighted_average_flow_minus_outside` = '$avg_x' WHERE `id` = '$system->id'");

    // print date use datetime
    $date = new DateTime();
    $date->setTimezone(new DateTimeZone('Europe/London'));
    $date->setTimestamp($system->coldest["timestamp"]);

    $line[] = $date->format("Y-m-d");
    print implode(",",$line)."\n";
}
