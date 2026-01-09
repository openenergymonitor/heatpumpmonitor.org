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
require "Modules/dashboard/myheatpump_schema.php";

require ("Modules/user/user_model.php");
$user_class = new User($mysqli, false); // false for rememberme in CLI context

require ("Modules/system/system_model.php");
$system_class = new System($mysqli);

require ("Modules/system/system_stats_model.php");
$system_stats_class = new SystemStats($mysqli,$system_class);
// Manufacturer & Heatpump models
require ("Modules/manufacturer/manufacturer_model.php");
$manufacturer_class = new Manufacturer($mysqli);
require ("Modules/heatpump/heatpump_model.php");
$heatpump_class = new Heatpump($mysqli,$manufacturer_class);

require ("Modules/system/system_photos_model.php");
$system_photos = new SystemPhotos($mysqli, $system_class);

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
$load_dashboard_daily = 0;
$load_manufacturers = 0;
$load_heatpump_models = 0;

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
    // If the variable is a JSON array of system ids, load those systems only else load all systems
    if (substr($_ENV["LOAD_MONTHLY_STATS"],0,1)=="[") {
        $load_monthly_stats = json_decode($_ENV["LOAD_MONTHLY_STATS"],true);
    } else {
        $load_monthly_stats = (int) $_ENV["LOAD_MONTHLY_STATS"];
    }
} else {
    if (confirm("Would you like to load monthly stats? This takes a little while, please only do this if required so as not to overload the server.")) $load_monthly_stats = 1;
}
if ($reload_all) $load_monthly_stats = 1;

// Check if we should load daily stats
if (isset($_ENV["LOAD_DAILY_STATS"])) {
    $load_daily_stats = (int) $_ENV["LOAD_DAILY_STATS"];
    // If the variable is a JSON array of system ids, load those systems only else load all systems
    if (substr($_ENV["LOAD_DAILY_STATS"],0,1)=="[") {
        $load_daily_stats = json_decode($_ENV["LOAD_DAILY_STATS"],true);
    } else {
        $load_daily_stats = (int) $_ENV["LOAD_DAILY_STATS"];
    }
} else {
    if (confirm("Would you like to load daily stats? This takes ages, please only do this if required so as not to overload the server.")) $load_daily_stats = 1;
}
if ($reload_all) $load_daily_stats = 1;

// Check if we should load manufacturers
if (isset($_ENV["LOAD_MANUFACTURERS"])) {
    $load_manufacturers = (int) $_ENV["LOAD_MANUFACTURERS"];    
} else {
    if (confirm("Would you like to load manufacturers?")) $load_manufacturers = 1;
}
if ($reload_all) $load_manufacturers = 1;

// Check if we should load heatpump models
if (isset($_ENV["LOAD_HEATPUMP_MODELS"])) {
    $load_heatpump_models = (int) $_ENV["LOAD_HEATPUMP_MODELS"];    
} else {
    if (confirm("Would you like to load heatpump models?")) $load_heatpump_models = 1;
}
if ($reload_all) $load_heatpump_models = 1;

// Check if we should replicate dashboard daily data (myheatpump daily)
if (isset($_ENV["LOAD_DASHBOARD_DAILY"])) {
    // If the variable is a JSON array of system ids, load those systems only else load all systems
    if (substr($_ENV["LOAD_DASHBOARD_DAILY"],0,1)=="[") {
        $load_dashboard_daily = json_decode($_ENV["LOAD_DASHBOARD_DAILY"],true);
    } else {
        $load_dashboard_daily = (int) $_ENV["LOAD_DASHBOARD_DAILY"];
    }
} else {
    if (confirm("Would you like to replicate dashboard daily data (myheatpump)?")) $load_dashboard_daily = 1;
}
if ($reload_all) $load_dashboard_daily = 1;


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
// 2b. Create admin user with sub-accounts (to replicate issue #112)
// -------------------------------------------------------------------------------------
if ($load_users) {
    print "- Creating admin user with sub-accounts for testing issue #112\n";
    
    // Create an installer admin user (similar to Libtek - user 45903)
    $installer_admin_id = 9000;
    $installer_username = "installer_admin";
    $installer_email = "installer@example.com";
    $created = time();
    $admin = 0; // Not a system admin, just an installer admin
    
    // Check if installer admin already exists
    $result = $mysqli->query("SELECT * FROM users WHERE id='$installer_admin_id'");
    if ($result->num_rows == 0) {
        $stmt = $mysqli->prepare("INSERT INTO users (id, username, email, created, admin) VALUES (?,?,?,?,?)");
        $stmt->bind_param("isssi", $installer_admin_id, $installer_username, $installer_email, $created, $admin);
        $stmt->execute();
        $stmt->close();
        print "  Created installer admin user: $installer_username (id: $installer_admin_id)\n";
    }
    
    // Create two sub-accounts for this installer admin
    $sub_accounts = [
        ['id' => 9001, 'username' => 'subaccount1', 'email' => 'sub1@example.com'],
        ['id' => 9002, 'username' => 'subaccount2', 'email' => 'sub2@example.com']
    ];
    
    $created_sub_accounts = 0;
    foreach ($sub_accounts as $sub) {
        // Check if sub-account already exists
        $result = $mysqli->query("SELECT * FROM users WHERE id='{$sub['id']}'");
        if ($result->num_rows == 0) {
            $stmt = $mysqli->prepare("INSERT INTO users (id, username, email, created, admin) VALUES (?,?,?,?,?)");
            $admin_val = 0;
            $stmt->bind_param("isssi", $sub['id'], $sub['username'], $sub['email'], $created, $admin_val);
            $stmt->execute();
            $stmt->close();
            $created_sub_accounts++;
            print "  Created sub-account: {$sub['username']} (id: {$sub['id']})\n";
        }
        
        // Link sub-account to installer admin in accounts table
        $result = $mysqli->query("SELECT * FROM accounts WHERE adminuser='$installer_admin_id' AND linkeduser='{$sub['id']}'");
        if ($result->num_rows == 0) {
            $stmt = $mysqli->prepare("INSERT INTO accounts (adminuser, linkeduser) VALUES (?,?)");
            $stmt->bind_param("ii", $installer_admin_id, $sub['id']);
            $stmt->execute();
            $stmt->close();
            print "  Linked sub-account {$sub['username']} to installer admin\n";
        }
    }
}

// -------------------------------------------------------------------------------------
// 3. Create manufacturers
// -------------------------------------------------------------------------------------
if ($load_manufacturers) {
    print "- Loading manufacturers list ";
    $data = file_get_contents("$heatpumpmonitor_host/manufacturer/list.json");
    $manufacturers = json_decode($data);
    if ($manufacturers==null) die("Error: could not load manufacturer data from heatpumpmonitor.org\n");
    $created_manufacturers = 0;
    foreach ($manufacturers as $m) {
        $id = (int) $m->id;
        $name = trim($m->name);
        $website = isset($m->website) ? trim($m->website) : "";
        // Skip if exists
        $stmt = $mysqli->prepare("SELECT id FROM manufacturers WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows>0;        
        $stmt->close();
        if ($exists) continue;
        $stmt = $mysqli->prepare("INSERT INTO manufacturers (id,name,website) VALUES (?,?,?)");
        $stmt->bind_param("iss", $id, $name, $website);
        if ($stmt->execute()) $created_manufacturers++;
        $stmt->close();
    }
    print $created_manufacturers." manufacturers\n";
}

// -------------------------------------------------------------------------------------
// 3b. Create heatpump models
// -------------------------------------------------------------------------------------
if ($load_heatpump_models) {
    print "- Loading heatpump models list ";
    $data = file_get_contents("$heatpumpmonitor_host/heatpump/list.json");
    $heatpumps = json_decode($data);
    if ($heatpumps==null) die("Error: could not load heatpump model data from heatpumpmonitor.org\n");
    $created_models = 0;

    // TODO: Download heatpump images if available
    $heatpump_img_dir = "theme/img/heatpumps";
    if (!file_exists($heatpump_img_dir)) {
        if (mkdir($heatpump_img_dir, 0755, true)) {
            chown($heatpump_img_dir, 'www-data');
            chgrp($heatpump_img_dir, 'www-data');
            echo "Created directory: $heatpump_img_dir\n";
        } else {
            echo "Failed to create directory: $heatpump_img_dir\n";
        }
    }

    foreach ($heatpumps as $hp) {
        // Basic fields
        $manufacturer_id = (int) $hp->manufacturer_id;
        $name = trim($hp->name);
        $refrigerant = isset($hp->refrigerant) ? trim($hp->refrigerant) : "";
        $type = isset($hp->type) ? trim($hp->type) : "";
        $capacity = isset($hp->capacity) ? trim($hp->capacity) : "";
        $min_flowrate = (isset($hp->min_flowrate) && $hp->min_flowrate!="" && $hp->min_flowrate!==null) ? (float) $hp->min_flowrate : null;
        $max_flowrate = (isset($hp->max_flowrate) && $hp->max_flowrate!="" && $hp->max_flowrate!==null) ? (float) $hp->max_flowrate : null;
        $max_current = (isset($hp->max_current) && $hp->max_current!="" && $hp->max_current!==null) ? (float) $hp->max_current : null;

        // Ensure manufacturer exists (create if missing)
        if (!$manufacturer_class->get_by_id($manufacturer_id)) {
            $stmt = $mysqli->prepare("INSERT INTO manufacturers (id,name,website) VALUES (?,?,?)");
            $blank = "";
            $stmt->bind_param("iss", $manufacturer_id, $blank, $blank);
            $stmt->execute();
            $stmt->close();
        }

        // Skip if model already exists (by manufacturer + name + refrigerant + capacity)
        if ($heatpump_class->model_exists($manufacturer_id, $name, $refrigerant, $capacity)) continue;

        // Use Heatpump->add (lets DB assign id). We intentionally do not preserve production IDs here.
        $result = $heatpump_class->add($manufacturer_id, $name, $refrigerant, $type, $capacity, $min_flowrate, $max_flowrate, $max_current);
        if ($result['success']) {
            $created_models++;
        } else {
            print "Warning: Failed to add heatpump model: ".$name." (".$result['message'].")\n";
        }
    }
    print $created_models." heatpump models\n";
}

// -------------------------------------------------------------------------------------
// 3. Create systems
// -------------------------------------------------------------------------------------

if ($load_system_meta) {
    $created_systems = 0;
    $system_userid = 1;

    $total_photos = 0;
    $existing_photos = 0;
    $failed_photos = 0;

    $system_img_dir = "theme/img/system";
    if (!file_exists($system_img_dir)) {
        if (mkdir($system_img_dir, 0755, true)) {
            chown($system_img_dir, 'www-data');
            chgrp($system_img_dir, 'www-data');
            echo "  Created directory: $system_img_dir\n";
        } else {
            echo "  Failed to create directory: $system_img_dir\n";
        }
    }

    foreach ($systems as $system) {

        // Check if system already exists
        $result = $mysqli->query("SELECT * FROM system_meta WHERE id='$system->id'");
        if ($result->num_rows == 0) {
            
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
        } else {
            print "- System already exists: $system->id\n";
        }

        if($system->photo_count == 0) {
            continue;
        }
        
        // Fetch photos for this system from production
        $photos_data = file_get_contents("$heatpumpmonitor_host/system/photos?id={$system->id}");
        $photos = json_decode($photos_data, true);
        
        if ($photos === null || !isset($photos['success']) || !$photos['success']) {
            continue;
        }
        
        if (empty($photos['photos'])) {
            continue;
        }
        
        // Create directory for this system
        $system_dir = "$system_img_dir/{$system->id}";
        if (!file_exists($system_dir)) {
            if (!mkdir($system_dir, 0755, true)) {
                echo "  Failed to create directory: $system_dir\n";
                continue;
            }
            chown($system_dir, 'www-data');
            chgrp($system_dir, 'www-data');
        }
        
        // Download each photo
        foreach ($photos['photos'] as $photo) {
            // Skip if no URL
            if (!isset($photo['url'])) {
                continue;
            }
            
            $photo_url = $heatpumpmonitor_host . '/' . $photo['url'];
            $photo_filename = basename($photo['url']);
            $local_path = "$system_dir/$photo_filename";
            
            // Skip if already exists on disk
            if (file_exists($local_path)) {
                // Check if it's in the database
                $result = $system_photos->save_external_photo(
                    $system->id,
                    $photo['url'],
                    $photo
                );
                if ($result['success']) {
                    $existing_photos++;
                } else {
                    $failed_photos++;
                }
                continue;
            }
            
            // Download the photo
            $photo_content = @file_get_contents($photo_url);
            if ($photo_content === false) {
                echo "  Failed to download: $photo_url\n";
                $failed_photos++;
                continue;
            }
            
            // Save the photo
            if (file_put_contents($local_path, $photo_content) === false) {
                echo "  Failed to save: $local_path\n";
                $failed_photos++;
                continue;
            }
            
            // Set permissions
            @chmod($local_path, 0644);
            
            // Save photo metadata to database using helper method
            $result = $system_photos->save_external_photo(
                $system->id,
                $photo['url'],
                $photo
            );
            
            if ($result['success']) {
                $total_photos++;
            } else {
                echo "  Failed to save photo metadata: {$result['message']}\n";
                $failed_photos++;
            }
        }
    }

    $mysqli->query("UPDATE system_meta SET published=1");
    print "- Created ".$created_systems." systems\n";
    
    print "  Downloaded $total_photos photos, $existing_photos existing";
    if ($failed_photos > 0) {
        print " ($failed_photos failed)";
    }
    print "\n";
    
    // Populate app_id and readkey for all systems so timeseries endpoints work
    print "- Populating app_id and readkey for timeseries support\n";
    $result = $mysqli->query("SELECT id FROM system_meta WHERE app_id IS NULL OR app_id=''");
    $systems_without_app = 0;
    while ($row = $result->fetch_object()) {
        $system_id = (int) $row->id;
        // Use system_id as both app_id and readkey for dev environment
        // This allows the timeseries endpoints to work locally
        $app_id = $system_id;
        $readkey = "test_readkey_$system_id";
        $mysqli->query("UPDATE system_meta SET app_id='$app_id', readkey='$readkey' WHERE id='$system_id'");
        $systems_without_app++;
    }
    if ($systems_without_app > 0) {
        print "  Added app configuration to $systems_without_app systems\n";
    }
    
    // Create mock emoncms app config responses for local development
    print "- Creating mock app config cache files\n";
    $cache_dir = "cache/app_config";
    if (!file_exists($cache_dir)) {
        if (mkdir($cache_dir, 0755, true)) {
            print "  Created cache directory: $cache_dir\n";
        }
    }
    
    $result = $mysqli->query("SELECT id FROM system_meta");
    $configs_created = 0;
    while ($row = $result->fetch_object()) {
        $system_id = (int) $row->id;
        
        // Create mock getconfigmeta response
        $config = new stdClass();
        $config->feeds = new stdClass();
        $config->feeds->heatpump_elec = (object)['feedid' => 1];
        $config->feeds->heatpump_heat = (object)['feedid' => 2];
        $config->feeds->heatpump_outsideT = (object)['feedid' => 3];
        $config->feeds->heatpump_flowT = (object)['feedid' => 4];
        $config->feeds->heatpump_returnT = (object)['feedid' => 5];
        
        $cache_file = "$cache_dir/$system_id.json";
        if (file_put_contents($cache_file, json_encode($config))) {
            $configs_created++;
        }
    }
    if ($configs_created > 0) {
        print "  Created $configs_created app config cache files\n";
    }
}

// -------------------------------------------------------------------------------------
// 3b. Create test systems for sub-accounts (to replicate issue #112)
// -------------------------------------------------------------------------------------
if ($load_system_meta) {
    print "- Creating test systems for sub-accounts (issue #112 testing)\n";
    
    // Create systems for sub-account 1 and sub-account 2
    $test_systems = [
        [
            'id' => 99001,
            'userid' => 9001,
            'location' => 'Test Location 1',
            'hp_model' => 'Test Heat Pump A',
            'hp_output' => 5,
            'published' => 1
        ],
        [
            'id' => 99002,
            'userid' => 9002,
            'location' => 'Test Location 2',
            'hp_model' => 'Test Heat Pump B',
            'hp_output' => 8,
            'published' => 1
        ],
        [
            'id' => 99003,
            'userid' => 9002,
            'location' => 'Test Location 2',
            'hp_model' => 'Test Heat Pump C',
            'hp_output' => 5,
            'published' => 0
        ]
    ];
    
    $created_test_systems = 0;
    foreach ($test_systems as $test_system) {
        // Check if system already exists
        $result = $mysqli->query("SELECT * FROM system_meta WHERE id='{$test_system['id']}'");
        if ($result->num_rows == 0) {
            // Create system
            $mysqli->query("INSERT INTO system_meta (id, userid, location, hp_model, hp_output, published) VALUES ('{$test_system['id']}', '{$test_system['userid']}', '{$test_system['location']}', '{$test_system['hp_model']}', '{$test_system['hp_output']}', {$test_system['published']})");
            $created_test_systems++;
            print "  Created test system {$test_system['id']} for user {$test_system['userid']}\n";
        }
    }
    
    if ($created_test_systems > 0) {
        print "  Created $created_test_systems test systems for sub-accounts\n";
        print "  Note: Login as 'installer_admin' to test photo upload to sub-account systems\n";
    }
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
    if( is_int($load_monthly_stats)) {
        // If load_monthly_stats is an integer load all systems
        $system_ids = array();
        foreach ($systems as $system) {
            $system_ids[] = $system->id;
        }
    } else {
        // Else load only the specified systems
        $system_ids = $load_monthly_stats;
    }
    foreach ($system_ids as $system_id) {
        print "- Loading monthly stats for system: $system_id ";

        // Clear existing monthly data for the system
        $mysqli->query("DELETE FROM system_stats_monthly_v2 WHERE id='$system_id'");
        
        // https://heatpumpmonitor.org/system/stats/monthly?id=2
        $data = file_get_contents("$heatpumpmonitor_host/system/stats/monthly?id=$system_id");
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
    if( is_int($load_daily_stats)) {
        // If load_daily_stats is an integer load all systems
        $system_ids = array();
        foreach ($systems as $system) {
            $system_ids[] = $system->id;
        }
    } else {
        // Else load only the specified systems
        $system_ids = $load_daily_stats;
    }
    foreach ($system_ids as $system_id) {
        print "- Loading daily stats for system: $system_id ";
        $days = 0;

        // Clear existing daily data for the system
        $mysqli->query("DELETE FROM system_stats_daily WHERE id='$system_id'");

        // Initialize cURL session to download the CSV
        $apiUrl = 'https://heatpumpmonitor.org/system/stats/export/daily?id='.$system_id;
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
            $header = fgetcsv($handle, 2000, ",", escape: "\\");

            // Read the rest of the file
            while (($data = fgetcsv($handle, 2000, ",", escape: "\\")) !== FALSE) {
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

// -------------------------------------------------------------------------------------
// 6. Replicate dashboard daily data (myheatpump_daily_stats)
// -------------------------------------------------------------------------------------
if ($load_dashboard_daily) {
    // Helper: populate bind codes from myheatpump schema types
    $populate_codes = function($my_schema) {
        foreach ($my_schema as $key => $field) {
            $type = isset($field['type']) ? $field['type'] : '';
            if (strpos($type,'varchar')!==false) $my_schema[$key]['code'] = 's';
            else if (strpos($type,'text')!==false) $my_schema[$key]['code'] = 's';
            else if (strpos($type,'int')!==false) $my_schema[$key]['code'] = 'i';
            else if (strpos($type,'float')!==false) $my_schema[$key]['code'] = 'd';
            else if (strpos($type,'bool')!==false) $my_schema[$key]['code'] = 'b';
            else $my_schema[$key]['code'] = 's';
        }
        return $my_schema;
    };

    // Prepare local schema with bind codes
    $my_schema = $populate_codes($schema['myheatpump_daily_stats']);

    // Helper: insert row into myheatpump_daily_stats based on available fields
    $insert_myheatpump_row = function($mysqli, $my_schema, $row) {
        $fields = array();
        $qmarks = array();
        $codes = array();
        $values = array();
        foreach ($my_schema as $field => $field_schema) {
            if (array_key_exists($field, $row)) {
                $fields[] = $field;
                $qmarks[] = '?';
                $codes[] = $field_schema['code'];
                $values[] = $row[$field];
            }
        }
        if (empty($fields)) return false;
        $stmt = $mysqli->prepare("INSERT INTO myheatpump_daily_stats (".implode(',',$fields).") VALUES (".implode(',',$qmarks).")");
        $stmt->bind_param(implode('', $codes), ...$values);
        $stmt->execute();
        $stmt->close();
        return true;
    };

    // Determine which systems to process
    if (is_int($load_dashboard_daily)) {
        // Integer => load all systems
        $system_ids = array();
        foreach ($systems as $system) {
            $system_ids[] = $system->id;
        }
    } else {
        // Else load only the specified systems
        $system_ids = $load_dashboard_daily;
    }

    foreach ($system_ids as $system_id) {
        $system_id = (int) $system_id;
        print "- Replicating dashboard daily (myheatpump) for system: $system_id ";

        // Clear existing myheatpump daily data for the system
        $mysqli->query("DELETE FROM myheatpump_daily_stats WHERE id='$system_id'");

        $row_count = 0;
        
        // Try to fetch from production app first (if app_id exists)
        $result = $mysqli->query("SELECT app_id FROM system_meta WHERE id='$system_id'");
        if ($result && $result->num_rows > 0) {
            $meta = $result->fetch_object();
            
            if ($meta && $meta->app_id) {
                // Try production app endpoint
                $range_url = "$heatpumpmonitor_host/app/getdailydatarange.json?id=$system_id";
                $range_json = @file_get_contents($range_url);
                if ($range_json !== false) {
                    $range = json_decode($range_json);
                    if ($range !== null && isset($range->start) && isset($range->end)) {
                        $start = (int) $range->start;
                        $end = (int) $range->end;

                        // Fetch CSV daily data from production app
                        $data_url = "$heatpumpmonitor_host/app/getdailydata?id=$system_id&start=$start&end=$end";
                        $csvData = @file_get_contents($data_url);
                        if ($csvData !== false && strlen($csvData) > 0) {
                            // Write CSV to a temporary file for parsing
                            $tempFilePath = tempnam(sys_get_temp_dir(), 'csv');
                            file_put_contents($tempFilePath, $csvData);

                            if (($handle = fopen($tempFilePath, 'r')) !== FALSE) {
                                // Header row
                                $header = fgetcsv($handle, 2000, ",", escape: "\\");
                                // Read remaining rows
                                while (($data = fgetcsv($handle, 2000, ",", escape: "\\")) !== FALSE) {
                                    if (!$data) continue;
                                    $row = array();
                                    foreach ($header as $i => $field) {
                                        // Build row map; ensure id is set to system_id
                                        if ($field === 'id') {
                                            $row[$field] = $system_id;
                                        } else {
                                            $row[$field] = isset($data[$i]) ? $data[$i] : null;
                                        }
                                    }
                                    // Ensure timestamp is integer
                                    if (isset($row['timestamp'])) $row['timestamp'] = (int) $row['timestamp'];
                                    // Insert
                                    $insert_myheatpump_row($mysqli, $my_schema, $row);
                                    $row_count++;
                                    if ($row_count % 100 == 0) { print "."; }
                                }
                                fclose($handle);
                            }
                            @unlink($tempFilePath);
                        }
                    }
                }
            }
        }
        
        // Fallback: populate from system_stats_daily if no app data or if empty
        if ($row_count == 0) {
            // Fetch from system_stats_daily (already loaded from production daily export)
            $result = $mysqli->query("SELECT * FROM system_stats_daily WHERE id='$system_id' ORDER BY timestamp ASC");
            if ($result && $result->num_rows > 0) {
                while ($data = $result->fetch_assoc()) {
                    $insert_myheatpump_row($mysqli, $my_schema, $data);
                    $row_count++;
                    if ($row_count % 100 == 0) { print "."; }
                }
            }
        }
        
        print " $row_count days\n";
    }
}

print "Done\n";

function confirm($message) {
    echo "$message (y/n): ";
    $handle = fopen ("php://stdin","r");
    $line = fgets($handle);
    return trim($line) == 'y';
}