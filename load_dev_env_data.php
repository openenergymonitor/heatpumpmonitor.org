<?php

// -------------------------------------------------------------------------------------
// This script pulls in public data from heatpumpmonitor.org and loads it into the database
// private data is not loaded and is replaced with dummy data
// -------------------------------------------------------------------------------------

// Set the host to pull data from
$heatpumpmonitor_host = "https://heatpumpmonitor.org";

// Change to www directory
$dir = dirname(__FILE__);

if(is_dir("/var/www/heatpumpmonitororg")) {
    chdir("/var/www/heatpumpmonitororg");
} elseif(is_dir("$dir/www")) {
    chdir("$dir/www");
} else {
    die("Error: could not find heatpumpmonitor.org directory");
}

// Load the database
define('EMONCMS_EXEC', 1);
require "Lib/load_database.php";
require "core.php";
require "Lib/dbschemasetup.php";

// Load the schema
$schema = array();
require "Modules/user/user_schema.php";
require "Modules/system/system_schema.php";
require "Modules/heatpump/heatpump_schema.php";
require "Modules/installer/installer_schema.php";
require "Modules/manufacturer/manufacturer_schema.php";

require ("Modules/system/system_model.php");
$system_class = new System($mysqli);

require ("Modules/system/system_stats_model.php");
$system_stats_class = new SystemStats($mysqli,$system_class);

// Before starting load system list, if this fails we can exit before clearing the database
$data = file_get_contents("$heatpumpmonitor_host/system/list/public.json");
$systems = json_decode($data);
if ($systems==null) die("Error: could not load data from heatpumpmonitor.org");

// -------------------------------------------------------------------------------------
// 1. Confirm what data to load
// -------------------------------------------------------------------------------------

$reload_all = 0;
$clear_db = 0;
$load_users = 0;
$load_system_meta = 0;
$load_running_stats = 0;
$load_monthly_stats = 0;
$load_daily_stats = 0;

// Check if we should reload all data
//if (isset($_ENV["RELOAD_ALL"])) {
//   $reload_all = (int) $_ENV["RELOAD_ALL"];
//} else {
//    if (confirm("Would you like to reload all data (this will pull in monthly and daily data which is slow, recommend to skip)?")) $reload_all = 1;
//}

// If not reload all then check if we should clear the database
if ($reload_all==0) {
    if (isset($_ENV["CLEAR_DB"])) {
        $clear_db = (int) $_ENV["CLEAR_DB"];
    } else {
        if (confirm("Would you like to clear the database?")) $clear_db = 1;
    }
}
if ($reload_all) $clear_db = 1;

// Check if we should load users
if (isset($_ENV["LOAD_USERS"])) {
    $load_users = (int) $_ENV["LOAD_USERS"];
} else {
    if (confirm("Would you like to load users?")) $load_users = 1;
}
if ($reload_all) $load_users = 1;

// Check if we should load system meta
if (isset($_ENV["LOAD_SYSTEM_META"])) {
    $load_system_meta = (int) $_ENV["LOAD_SYSTEM_META"];
} else {
    if (confirm("Would you like to load system meta?")) $load_system_meta = 1;
}
if ($reload_all) $load_system_meta = 1;

// Check if we should load running stats
if (isset($_ENV["LOAD_RUNNING_STATS"])) {
    $load_running_stats = (int) $_ENV["LOAD_RUNNING_STATS"];
} else {
    if (confirm("Would you like to load running stats?")) $load_running_stats = 1;
}
if ($reload_all) $load_running_stats = 1;

// Check if we should load monthly stats
if (isset($_ENV["LOAD_MONTHLY_STATS"])) {
    $load_monthly_stats = (int) $_ENV["LOAD_MONTHLY_STATS"];
} else {
    if (confirm("Would you like to load monthly stats? This takes a little while, please only do this if required so as not to overload the server.")) $load_monthly_stats = 1;
}
if ($reload_all) $load_monthly_stats = 1;

// Check if we should load daily stats
if (isset($_ENV["LOAD_DAILY_STATS"])) {
    $load_daily_stats = (int) $_ENV["LOAD_DAILY_STATS"];
} else {
    if (confirm("Would you like to load daily stats? This takes ages, please only do this if required so as not to overload the server.")) $load_daily_stats = 1;
}
if ($reload_all) $load_daily_stats = 1;


// -------------------------------------------------------------------------------------
// 1. Clear the database
// -------------------------------------------------------------------------------------

if ($clear_db) {
    $result = $mysqli->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $mysqli->query("DROP TABLE IF EXISTS `$row[0]`");
    }
}

db_schema_setup($mysqli, $schema, true);


// -------------------------------------------------------------------------------------
// 2. Create users
// -------------------------------------------------------------------------------------

if ($load_users) {
    // Get list of userid's
    $users = array();
    $userid = 1;
    foreach ($systems as $system) {
        $users[$userid] = $system->location;
        $userid++;
    }

    // Create users using userid and dummy data
    $index = 0;
    $created_users = 0;
    foreach ($users as $userid => $location) {

        $username = "user".$userid;
        $email = "example@heatpumpmonitor.org";

        // Make first user admin
        if ($index==0) {
            $admin = 1;
            $username = "admin";
        } else {
            $admin = 0;
        }

        // Password not needed as using dev environment login admin:admin
        // enable in settings.php: dev_env_login_enabled = true

        // Check if username already exists without prepared statement
        $result = $mysqli->query("SELECT * FROM users WHERE username='$username'");
        if ($result->num_rows==0) {
            $created = time();
            $stmt = $mysqli->prepare("INSERT INTO users ( id, username, email, created, admin) VALUES (?,?,?,?,?)");
            $stmt->bind_param("isssi", $userid, $username, $email, $created, $admin);
            $stmt->execute();
            $stmt->close();
            $created_users++;
        }
        $index++;
    }
    print "- Created ".$created_users." users\n";
}

// -------------------------------------------------------------------------------------
// 3. Create systems
// -------------------------------------------------------------------------------------

if ($load_system_meta) {
    $created_systems = 0;
    $system_userid = 1;
    foreach ($systems as $system) {

        // Check if system already exists
        $result = $mysqli->query("SELECT * FROM system_meta WHERE id='$system->id'");
        if ($result->num_rows>0) {
            print "- System already exists: $system->id\n";
            continue;
        }

        // Create system
        $mysqli->query("INSERT INTO system_meta (id,userid) VALUES ('$system->id', '$system_userid')");

        $result = $system_class->save($system_userid, $system->id, $system, false);
        if ($result['success']==false) {
            echo "Error: could not save system: ".$system->id."\n";
            print_r($result);
            die;
        } else {
            $created_systems++;
        }
        $system_userid++;
    }
    $mysqli->query("UPDATE system_meta SET published=1");
    print "- Created ".$created_systems." systems\n";
}

// -------------------------------------------------------------------------------------
// 4. Load stats summaries
// -------------------------------------------------------------------------------------
if ($load_running_stats) {
    $tables = ["last7","last30","last90","last365","all"];

    foreach ($tables as $table) {
        print "- Loading $table stats summary ";

        $data = file_get_contents("$heatpumpmonitor_host/system/stats/$table");
        $stats = json_decode($data,true);
        if ($stats==null) die("Error: could not load $table data from heatpumpmonitor.org");

        foreach ($stats as $system) {
            $system_stats_class->save_stats_table("system_stats_".$table."_v2",$system);
        }
        print count($stats)." systems\n";
    }
}

// -------------------------------------------------------------------------------------
// 4. Load monthly summaries
// -------------------------------------------------------------------------------------
if ($load_monthly_stats) {
    foreach ($systems as $system) {
        print "- Loading monthly stats for system: $system->id ";
        // https://heatpumpmonitor.org/system/stats/monthly?id=2
        $data = file_get_contents("$heatpumpmonitor_host/system/stats/monthly?id=$system->id");
        $stats = json_decode($data,true);
        if ($stats==null) {
            print "Error: could not load monthly data from heatpumpmonitor.org\n";
            print $data;
            continue;
        }
        foreach ($stats as $month) {
            $system_stats_class->save_stats_table('system_stats_monthly_v2',$month);
        }
        print count($stats)." months\n";
    }
}

// -------------------------------------------------------------------------------------
// 5. Load daily data
// -------------------------------------------------------------------------------------
if ($load_daily_stats) {
    foreach ($systems as $system) {
        $id = $system->id;

        print "- Loading daily stats for system: $id ";
        $days = 0;

        // Initialize cURL session to download the CSV
        $apiUrl = 'https://heatpumpmonitor.org/system/stats/export/daily?id='.$id;
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

            // Read the rest of the file
            while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
                // Build an associative array from the CSV data
                $row = array();
                foreach ($header as $i => $field) {
                    $row[$field] = $data[$i];
                }

                // Save the data to the database
                $system_stats_class->save_day($row['id'], $row);
                $days++;

                // print a . every 1000 rows
                if ($days % 100 == 0) {
                    print ".";
                }
            }
        }
        print " $days days\n";
    }
}

print "Done\n";

function confirm($message) {
    echo "$message (y/n): ";
    $handle = fopen ("php://stdin","r");
    $line = fgets($handle);
    return trim($line) == 'y';
}