<?php

// confirm that we want to clear all data and rebuild the database
echo "This script will clear all data and rebuild the database\n";
echo "Are you sure you want to continue? (y/n): ";
$handle = fopen ("php://stdin","r");
$line = fgets($handle);
if(trim($line) != 'y'){
    echo "ABORTING!\n";
    exit;
}

// This script pulls in public data from heatpumpmonitor.org and loads it into the database
// private data is not loaded and is replaced with dummy data

$data = file_get_contents("https://heatpumpmonitor.org/system/list/public.json");
$systems = json_decode($data);
if ($systems==null) die("Error: could not load data from heatpumpmonitor.org");

chdir("/var/www/heatpumpmonitororg");
require "www/Lib/load_database.php";
require "www/core.php";

// Clear all existing data
$result = $mysqli->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $mysqli->query("DROP TABLE IF EXISTS `$row[0]`");
}

// Rebuid database
require "www/Lib/dbschemasetup.php";
$schema = array();
require "www/Modules/user/user_schema.php";
require "www/Modules/system/system_schema.php";
db_schema_setup($mysqli, $schema, true);

require ("Modules/system/system_model.php");
$system_class = new System($mysqli);

require ("Modules/system/system_stats_model.php");
$system_stats_class = new SystemStats($mysqli,$system_class);

// Get list of userid's
$users = array();
foreach ($systems as $system) {
    $users[$system->userid] = $system->location;
}

// Create users using userid and dummy data
$index = 0;
foreach ($users as $userid => $location) {

    $username = "user".$userid;
    $email = "example@heatpumpmonitor.org";
    $password = "password";

    if ($index==0) {
        $admin = 1;
        $username = "admin";
        $password = "admin";
    } else {
        $admin = 0;
    }

    $hash = hash('sha256', $password);
    $salt = generate_secure_key(16);
    $hash = hash('sha256', $salt . $hash);

    $created = time();
    $stmt = $mysqli->prepare("INSERT INTO users ( id, username, hash, email, salt, created, email_verified, admin) VALUES (?,?,?,?,?,?,1,?)");
    $stmt->bind_param("issssii", $userid, $username, $hash, $email, $salt, $created, $admin);
    $stmt->execute();
    $stmt->close();
}
print "- Created ".count($users)." users\n";

// Create systems
foreach ($systems as $system) {
    $mysqli->query("INSERT INTO system_meta (id,userid) VALUES ('$system->id', '$system->userid')");

    $result = $system_class->save($system->userid, $system->id, $system, false);
    if ($result['success']==false) {
        echo "Error: could not save system: ".$system->id."\n";
        print_r($result);
        die;
    }
}
$mysqli->query("UPDATE system_meta SET published=1");
print "- Created ".count($systems)." systems\n";

// Load 365 day data
$data = file_get_contents("https://heatpumpmonitor.org/system/stats/last365");
$stats = json_decode($data);
if ($stats==null) die("Error: could not load last 365 day data from heatpumpmonitor.org");
foreach ($stats as $id=>$system_stats) {
    $system_stats->id = $id;
    // convert to array
    $system_stats = (array) $system_stats;
    $system_stats_class->save_stats_table('system_stats_last365',$system_stats);
}
print "- Loaded 365 day data\n";

// Load 30 day data
$data = file_get_contents("https://heatpumpmonitor.org/system/stats/last30");
$stats = json_decode($data);
if ($stats==null) die("Error: could not load last 30 day data from heatpumpmonitor.org");
foreach ($stats as $id=>$system_stats) {
    $system_stats->id = $id;
    $system_stats->when_running_elec_W = 0;
    $system_stats->when_running_heat_W = 0;
    // convert to array
    $system_stats = (array) $system_stats;
    $system_stats_class->save_stats_table('system_stats_last30',$system_stats);
}
print "- Loaded 30 day data\n";

// Load monthly data
foreach ($systems as $system) {
    $data = file_get_contents("https://heatpumpmonitor.org/system/monthly?id=".$system->id);
    $monthly = json_decode($data);
    foreach ($monthly as $month=>$stats) {
        $stats->id = $system->id;
        $stats->when_running_elec_W = 0;
        $stats->when_running_heat_W = 0;
        $stats->since = 0;
        // convert to array
        $stats = (array) $stats;
        $timestamp = $stats['timestamp'];
        $mysqli->query("DELETE FROM system_stats_monthly WHERE id=$system->id AND timestamp=$timestamp");
        $system_stats_class->save_stats_table('system_stats_monthly',$stats);
    }
    print "- Loaded monthly data for system $system->id\n";
}