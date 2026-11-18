<?php

$dir = dirname(__FILE__);
chdir("/var/www/heatpumpmonitor");

define('EMONCMS_EXEC', 1);
require "Lib/load_database.php";

require("Modules/user/user_model.php");
$user = new User($mysqli,false);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

$system_id = 2;

// 1. Determine the app id.
$result = $mysqli->query("SELECT app_id FROM system_meta WHERE id = $system_id");
$row = $result->fetch_object();
print "App ID: ".$row->app_id."\n";

// 2. Load the app config
$result = $emoncms_mysqli->query("SELECT * FROM app WHERE id = ".$row->app_id);
$row = $result->fetch_object();
$app_config = json_decode($row->config);

// 3. Load phpfina feeds of interest
$feeds_to_load = array(
    "heatpump_elec",
    "heatpump_heat",
    "heatpump_flowT",
    "heatpump_returnT",
    "heatpump_flowrate",
    "heatpump_outsideT"
);

$feedids = array();

// For each of these feeds we need to check if they exist as phpfina feeds in the emoncms feed table.
foreach ($feeds_to_load as $key) {
    if (isset($app_config->$key)) {
        $feedid = (int) $app_config->$key;
        $result = $emoncms_mysqli->query("SELECT id FROM feeds WHERE id = $feedid AND engine = 5");
        // assign the feed id to the array if it exists
        if ($result->num_rows > 0) {
            $feedids[$key] = $feedid;
        }
    }
}

$meta = array();

// 4. For each feed id, get the meta data and print it out.
foreach ($feedids as $key => $feedid) {
    $meta[$key] = getmeta("/var/opt/emoncms/phpfina/", $feedid);
    // response in format {"interval":10,"start_time":1600870440,"npoints":18132636,"end_time":1782196800}
}

// 5. Determine latest start time and earliest end time across all feeds
$latest_start_time = 0;
$earliest_end_time = PHP_INT_MAX;

foreach ($meta as $key => $feed_meta) {
    if ($feed_meta->start_time > $latest_start_time) {
        $latest_start_time = $feed_meta->start_time;
    }
    if ($feed_meta->end_time < $earliest_end_time) {
        $earliest_end_time = $feed_meta->end_time;
    }
}

print "Latest start time: $latest_start_time\n";
print "Earliest end time: $earliest_end_time\n";


$fh = array();

// 6. Seek to the correct position in each feed based on the latest start time
foreach ($meta as $key => $feed_meta) {
    $pos = ($latest_start_time - $meta[$key]->start_time) / $meta[$key]->interval;
    print "Seek position: $pos\n";

    $fh[$key] = fopen("/var/opt/emoncms/phpfina/".$feedids[$key].".dat", 'rb');
    fseek($fh[$key], $pos * 4);
}

// 7. Read through feeds at 10s interval.
// some of the feeds may have different intervals e.g some at 10s others at 20s.
// we need to compute the position and seek to the correct position if the interval is different from 10s.

// true or false if the feed interval is different from 10s
$flowT_seek = ($meta["heatpump_flowT"]->interval != 10);

$npoints = ($earliest_end_time - $latest_start_time) / 10;


for ($i=0; $i < $npoints; $i++) {
    $current_time = $latest_start_time + ($i * 10);

    // Start by just loading the current value for heatpump_flowT
    $flowT = read($fh["heatpump_flowT"], $meta["heatpump_flowT"], $current_time, $flowT_seek);

    //print "Time: $current_time, heatpump_flowT: $flowT\n";

    // Print . for every 24h that has been processed
    if ($i % 8640 == 0) {
        print ".";
    }

    // newline every 30 days
    if ($i % 259200 == 0) {
        print "\n";
    }

    // double new line every 365 days
    if ($i % 3153600 == 0) {
        print "\n";
    }
}

function read($fh, $meta, $current_time, $seek = false)
{
    if ($seek) {
        $pos = floor(($current_time - $meta->start_time) / $meta->interval);
        fseek($fh, $pos * 4);
    }
    $tmp = unpack("f", fread($fh, 4));
    return $tmp[1];
}

function getmeta($dir, $id)
{
    if (!file_exists($dir . $id . ".meta")) {
        print "input file $id.meta does not exist\n";
        return false;
    }

    $meta = new stdClass();
    $metafile = fopen($dir . $id . ".meta", 'rb');
    fseek($metafile, 8);
    $tmp = unpack("I", fread($metafile, 4));
    $meta->interval = $tmp[1];
    $tmp = unpack("I", fread($metafile, 4));
    $meta->start_time = $tmp[1];
    fclose($metafile);

    clearstatcache($dir . $id . ".dat");
    $npoints = floor(filesize($dir . $id . ".dat") / 4.0);
    $meta->npoints = $npoints;

    $meta->end_time = $meta->start_time + ($meta->npoints * $meta->interval);

    return $meta;
}