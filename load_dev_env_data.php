<?php

$heatpumpmonitor_host = "https://dev.heatpumpmonitor.org";

$dir = dirname(__FILE__);
chdir("$dir/www");

if (isset($_ENV["LOAD_DATA"]) && ($_ENV["LOAD_DATA"]=="1" || $_ENV["LOAD_DATA"]==1)){
    echo "Forcing load of data\n";
}else{
    if (isset($_ENV["LOAD_DATA"]) && ($_ENV["LOAD_DATA"]=="0" || $_ENV["LOAD_DATA"]==0)){
        echo "Not loading data\n";
        exit(0);
    }else{
        // confirm that we want to clear all data and rebuild the database
        echo "This script will clear all data and rebuild the database\n";
        echo "Are you sure you want to continue? (y/n): ";
        $handle = fopen ("php://stdin","r");
        $line = fgets($handle);
        if(trim($line) != 'y'){
            echo "ABORTING!\n";
            exit (1);
        }   
    }
}

// This script pulls in public data from heatpumpmonitor.org and loads it into the database
// private data is not loaded and is replaced with dummy data

$data = file_get_contents("$heatpumpmonitor_host/system/list/public.json");
$systems = json_decode($data);
if ($systems==null) die("Error: could not load data from heatpumpmonitor.org");

require "Lib/load_database.php";
require "core.php";

// Clear all existing data
$result = $mysqli->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $mysqli->query("DROP TABLE IF EXISTS `$row[0]`");
}

// Rebuid database
require "Lib/dbschemasetup.php";
$schema = array();
require "Modules/user/user_schema.php";
require "Modules/system/system_schema.php";
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


// Initialize cURL session to download the CSV
$apiUrl = 'https://dev.heatpumpmonitor.org/system/stats/export/daily';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$csvData = curl_exec($ch);
curl_close($ch);

// Temporarily save CSV data to a file
$tempFilePath = tempnam(sys_get_temp_dir(), 'csv');
file_put_contents($tempFilePath, $csvData);

// Open the temporary file for reading
if (($handle = fopen($tempFilePath, 'r')) !== FALSE) {

    // Skip the header row
    $header = fgetcsv($handle, 2000, ",");

    $row_count = 0;
    // Read the rest of the file
    while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
        // Build an associative array from the CSV data
        $row = array();
        foreach ($header as $i => $field) {
            $row[$field] = $data[$i];
        }

        // Save the data to the database
        $system_stats_class->save_day($row['id'], $row);
        $row_count++;

        // print a . every 1000 rows
        if ($row_count % 1000 == 0) {
            print ".";
        }
    }
}