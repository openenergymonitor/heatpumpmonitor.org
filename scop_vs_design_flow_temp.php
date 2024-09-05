<?php
$dir = dirname(__FILE__);
chdir("$dir/www");

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

foreach ($systems as $system) {
    $daily = $system_stats->get_daily($system->id,$start,$end,"timestamp,combined_cop,running_flowT_mean,running_outsideT_mean,combined_flowT_mean,combined_heat_mean");
    // response is csv data, first line is headers
    $daily = explode("\n",$daily);
    $daily = array_slice($daily,1);

    // Find coldest day
    $coldest = array("timestamp" => null, "outside" => null, "flow" => null, "cop" => null, "combined_flow" => null, "combined_heat" => null);
    foreach ($daily as $line) {

        $line = explode(",",$line);

        if (count($line) < 2) {
            continue;
        }

        $timestamp = $line[0];
        $cop = $line[1];
        $flow = $line[2];
        $outside = $line[3];
        $combined_flow = $line[4];
        $combined_heat = $line[5];

        if ($timestamp<$start || $timestamp>$end) {
            continue;
        }

        if ($cop == 0) {
            continue;
        }

        if ($coldest["outside"] === null) {
            $coldest["timestamp"] = $timestamp;
            $coldest["outside"] = $outside;
            $coldest["flow"] = $flow;
            $coldest["cop"] = $cop;
            $coldest["combined_flow"] = $combined_flow;
            $coldest["combined_heat"] = $combined_heat;
        }

        if ($outside < $coldest["outside"]) {
            $coldest["timestamp"] = $timestamp;
            $coldest["outside"] = $outside;
            $coldest["flow"] = $flow;
            $coldest["cop"] = $cop;
            $coldest["combined_flow"] = $combined_flow;
            $coldest["combined_heat"] = $combined_heat;
        }
    }

    $system->coldest_outside = $coldest["outside"];
    $system->coldest_flow = $coldest["flow"];
    $system->coldest_cop = $coldest["cop"];
    $system->coldest_timestamp = $coldest["timestamp"];
    $system->coldest_combined_flow = $coldest["combined_flow"];
    $system->coldest_combined_heat = $coldest["combined_heat"];

}


print "ID,Location,HP Output,HP Model,Design Flow Temp,Design Outside Temp,Combined COP,Coldest heat,Coldest Outside,Coldest Flow,Coldest COP,Coldest Combined Flow,Coldest Date\n";

// Print all systems
foreach ($systems as $system) {

    $diff = $system->coldest_flow - $system->coldest_combined_flow;
    // abs
    $diff = abs($diff);

    if ($diff>0.3) {
        // continue;
    }

    // skip if coldest_flow = 0
    if ($system->coldest_flow == 0 || $system->coldest_outside == 0) {
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
    $line[] = round($system->coldest_combined_heat);

    $line[] = number_format($system->coldest_outside,1, ".", "");
    $line[] = number_format($system->coldest_flow,1, ".", "");
    $line[] = number_format($system->coldest_cop,2, ".", "");
    $line[] = number_format($system->coldest_combined_flow,1, ".", "");
    // print date use datetime
    $date = new DateTime();
    $date->setTimezone(new DateTimeZone('Europe/London'));
    $date->setTimestamp($system->coldest_timestamp);

    $line[] = $date->format("Y-m-d");
    print implode(",",$line)."\n";
}