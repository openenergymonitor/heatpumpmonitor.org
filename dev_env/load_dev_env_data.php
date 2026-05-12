<?php

// -------------------------------------------------------------------------------------
// This script pulls in public data from heatpumpmonitor.org and loads it into the database
// private data is not loaded and is replaced with dummy data
//
// Data split (see www/Modules/user/user_model.php, Lib/load_database.php):
// - heatpumpmonitor DB ($mysqli): system_meta, stats aggregates, manufacturers, heatpumps, etc.
// - emoncms DB ($emoncms_mysqli): users, accounts (sub-accounts), myheatpump_daily_stats (per-day app rows)
// - heatpumpmonitor "user_schema" only defines user_sessions (remember-me); there is no local users table
// -------------------------------------------------------------------------------------

// Set the host to pull data from
$heatpumpmonitor_host = "https://heatpumpmonitor.org";

// Change to www directory (script may live at repo root or in dev_env/)
$dir = dirname(__FILE__);

if (is_dir("/var/www/heatpumpmonitororg")) {
    chdir("/var/www/heatpumpmonitororg");
} elseif (is_dir("$dir/www")) {
    chdir("$dir/www");
} elseif (is_dir("$dir/../www")) {
    chdir("$dir/../www");
} else {
    die("Error: could not find heatpumpmonitor.org directory");
}

// Load the database
define('EMONCMS_EXEC', 1);
require "Lib/load_database.php";
require "core.php";
require "Lib/dbschemasetup.php";
require "Lib/load_module_schemas.php";

// Same module schema discovery as update_database.php
$schema = load_module_schemas();
unset($schema['system_stats_daily']);

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
// 2. Create users (emoncms: users + accounts; matches User model emoncms_mysqli)
// -------------------------------------------------------------------------------------

if ($load_users) {
    // Get list of userid's
    global $emoncms_mysqli;

    $dev_password_plain = 'password';
    load_dev_ensure_emoncms_accounts_table($emoncms_mysqli);
    print "- Seeding emoncms users (passwords: user admin => admin; all other seeded users => $dev_password_plain)\n";

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

        $esc = $emoncms_mysqli->real_escape_string($username);
        $result = $emoncms_mysqli->query("SELECT id FROM users WHERE username='$esc'");
        if ($result && $result->num_rows==0) {
            if (load_dev_insert_emoncms_user($emoncms_mysqli, $userid, $username, $email, $admin, $admin ? "admin" : $dev_password_plain)) {
                $created_users++;
            }
        }
        $index++;
    }
    print "- Created ".$created_users." emoncms users\n";

    print "- Creating installer admin + sub-accounts (issue #112 testing)\n";

    $installer_admin_id = 9000;
    $installer_username = "installer_admin";
    $installer_email = "installer@example.com";

    $result = $emoncms_mysqli->query("SELECT id FROM users WHERE id='$installer_admin_id'");
    if ($result && $result->num_rows == 0) {
        if (load_dev_insert_emoncms_user($emoncms_mysqli, $installer_admin_id, $installer_username, $installer_email, 0, $dev_password_plain)) {
            print "  Created installer admin user: $installer_username (id: $installer_admin_id)\n";
        }
    }

    // Create two sub-accounts for this installer admin
    $sub_accounts = [
        ['id' => 9001, 'username' => 'subaccount1', 'email' => 'sub1@example.com'],
        ['id' => 9002, 'username' => 'subaccount2', 'email' => 'sub2@example.com']
    ];

    foreach ($sub_accounts as $sub) {
        $result = $emoncms_mysqli->query("SELECT id FROM users WHERE id='{$sub['id']}'");
        if ($result && $result->num_rows == 0) {
            if (load_dev_insert_emoncms_user($emoncms_mysqli, $sub['id'], $sub['username'], $sub['email'], 0, $dev_password_plain)) {
                print "  Created sub-account: {$sub['username']} (id: {$sub['id']})\n";
            }
        }

        // Link sub-account to installer admin in accounts table        $result = $emoncms_mysqli->query("SELECT adminuser FROM accounts WHERE adminuser='$installer_admin_id' AND linkeduser='{$sub['id']}'");
        if ($result && $result->num_rows == 0) {
            $stmt = $emoncms_mysqli->prepare("INSERT INTO accounts (adminuser, linkeduser) VALUES (?,?)");
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
// 3c. Link system_meta to the EmonCMS myheatpump app (testdata profile only)
// Only fires when load_emoncms_testdata has run before us and an app exists in the
// emoncms DB. Picks the single myheatpump app per user and points that user's first
// system_meta row at it (app_id + readkey from emoncms users.apikey_read).
// -------------------------------------------------------------------------------------
if ($load_system_meta) {
    global $emoncms_mysqli;
    $hpm_app_name = getenv('HPM_APP_NAME') ?: 'My Heatpump';

    // app table may not exist yet if emoncms hasn't created its schema (load_emoncms_testdata
    // not run). Detect and skip gracefully.
    $tbl = $emoncms_mysqli->query("SHOW TABLES LIKE 'app'");
    if ($tbl && $tbl->num_rows > 0) {
        $sql = "SELECT a.userid, a.id AS app_id, u.apikey_read"
             ." FROM app a JOIN users u ON u.id=a.userid"
             ." WHERE a.app='myheatpump' AND a.name=?";
        $stmt = $emoncms_mysqli->prepare($sql);
        $stmt->bind_param('s', $hpm_app_name);
        $stmt->execute();
        $res = $stmt->get_result();
        $linked = 0;
        while ($app_row = $res->fetch_assoc()) {
            $userid = (int) $app_row['userid'];
            $app_id = (int) $app_row['app_id'];
            $readkey = $app_row['apikey_read'];
            // Pick the user's first system_meta row that does not yet have an app_id.
            $sm = $mysqli->query("SELECT id FROM system_meta WHERE userid='$userid' AND (app_id IS NULL OR app_id=0) ORDER BY id ASC LIMIT 1");
            if ($sm && ($sm_row = $sm->fetch_assoc())) {
                $sysid = (int) $sm_row['id'];
                $up = $mysqli->prepare("UPDATE system_meta SET app_id=?, readkey=? WHERE id=?");
                $up->bind_param('isi', $app_id, $readkey, $sysid);
                $up->execute();
                $up->close();
                $linked++;
                print "- Linked system_meta id=$sysid -> emoncms app_id=$app_id (user=$userid)\n";
            }
        }
        $stmt->close();
        if ($linked === 0) {
            print "- No system_meta rows linked to emoncms apps (run load_emoncms_testdata to seed)\n";
        }
    } else {
        print "- emoncms `app` table not present; skipping app_id linking\n";
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
// 5. Load daily data into emoncms myheatpump_daily_stats (id = emoncms app id)
// The table itself is created by the emoncms myheatpump app's postprocess (run by
// load_emoncms_testdata before this script). We just append the historical rows the
// production heatpumpmonitor.org has on file for each system, remapping `id` from the
// HPM systemid to the local emoncms app_id (looked up via system_meta.app_id).
// -------------------------------------------------------------------------------------
if ($load_daily_stats) {
    global $emoncms_mysqli;

    // We unset $schema['system_stats_daily'] above so HPM does not create it locally; reload
    // just the column definitions here for building the prepared INSERT.
    $stats_field_schema = array();
    $tmp_schema = array();
    $schema_backup = $schema;
    $schema = array();
    require "Modules/system/system_schema.php";
    $stats_field_schema = $schema['system_stats_daily'];
    $schema = $schema_backup;

    if (is_int($load_daily_stats)) {
        $system_ids = array();
        foreach ($systems as $system) {
            $system_ids[] = $system->id;
        }
    } else {
        $system_ids = $load_daily_stats;
    }
    foreach ($system_ids as $system_id) {
        print "- Loading daily stats for system: $system_id ";
        $days = 0;

        $app_id = $system_stats_class->get_app_id($system_id);
        if ($app_id === false || (int) $app_id <= 0) {
            print "skip (no app_id; run load_emoncms_testdata first)\n";
            continue;
        }
        $app_id = (int) $app_id;

        $emoncms_mysqli->query("DELETE FROM myheatpump_daily_stats WHERE id='$app_id'");

        $apiUrl = 'https://heatpumpmonitor.org/system/stats/export/daily?id='.$system_id;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $csvData = curl_exec($ch);

        $tempFilePath = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tempFilePath, $csvData);

        if (($handle = fopen($tempFilePath, 'r')) !== FALSE) {
            $header = fgetcsv($handle, 2000, ",", escape: "\\");

            while (($data = fgetcsv($handle, 2000, ",", escape: "\\")) !== FALSE) {
                $row = array();
                foreach ($header as $i => $field) {
                    $row[$field] = $data[$i];
                }
                // myheatpump_daily_stats.id = emoncms app id, not HPM systemid
                $row['id'] = $app_id;

                load_dev_insert_myheatpump_daily_row($emoncms_mysqli, $stats_field_schema, $row);
                $days++;

                if ($days % 100 == 0) {
                    print ".";
                }
            }
            fclose($handle);
        }
        @unlink($tempFilePath);
        print " $days days\n";
    }
}

print "Done\n";

/** Same hashing as www/Modules/user/user_model.php::login and emoncms user registration */
function load_dev_emoncms_user_password_hash($plain_password, $salt)
{
    return hash('sha256', $salt . hash('sha256', $plain_password));
}

function load_dev_uuid_v4()
{
    $b = random_bytes(16);
    $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
    $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

/** HeatpumpMonitor sub-account links; lives on emoncms DB with users (see User::get_user_accounts). */
function load_dev_ensure_emoncms_accounts_table($db)
{
    $db->query(
        "CREATE TABLE IF NOT EXISTS accounts (
            adminuser INT(11) NOT NULL,
            linkeduser INT(11) NOT NULL,
            PRIMARY KEY (adminuser, linkeduser)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function load_dev_insert_emoncms_user($db, $id, $username, $email, $admin, $plain_password)
{
    $salt = generate_secure_key(16);
    $passhash = load_dev_emoncms_user_password_hash($plain_password, $salt);
    $api_read = generate_secure_key(16);
    $api_write = generate_secure_key(16);
    $uuid = load_dev_uuid_v4();
    $timezone = 'Europe/London';
    $access = 2;
    $lastactive = time();
    $email_verified = 1;

    $stmt = $db->prepare(
        "INSERT INTO users (id, username, password, email, salt, apikey_read, apikey_write, timezone, uuid, admin, access, lastactive, email_verified) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    if (!$stmt) {
        echo "  prepare users INSERT failed: ".$db->error."\n";
        return false;
    }
    $stmt->bind_param(
        "issssssssiiii",
        $id,
        $username,
        $passhash,
        $email,
        $salt,
        $api_read,
        $api_write,
        $timezone,
        $uuid,
        $admin,
        $access,
        $lastactive,
        $email_verified
    );
    if (!$stmt->execute()) {
        echo "  INSERT users failed (id=$id, username=$username): ".$db->error."\n";
        $stmt->close();
        return false;
    }
    $stmt->close();
    return true;
}

/**
 * Insert one daily-stats row into the emoncms myheatpump_daily_stats table.
 * Mirrors SystemStats::save_stats_table but uses the supplied $emoncms_mysqli connection.
 */
function load_dev_insert_myheatpump_daily_row($db, $field_schema, $row)
{
    $fields = array();
    $qmarks = array();
    $codes = '';
    $values = array();
    foreach ($field_schema as $field => $fs) {
        if (array_key_exists($field, $row)) {
            $fields[] = $field;
            $qmarks[] = '?';
            $codes .= $fs['code'];
            $values[] = $row[$field];
        }
    }
    $sql = "INSERT INTO myheatpump_daily_stats (".implode(',', $fields).") VALUES (".implode(',', $qmarks).")";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        echo "  prepare myheatpump_daily_stats INSERT failed: ".$db->error."\n";
        return;
    }
    $stmt->bind_param($codes, ...$values);
    $stmt->execute();
    $stmt->close();
}

function confirm($message) {
    echo "$message (y/n): ";
    $handle = fopen ("php://stdin","r");
    $line = fgets($handle);
    return trim($line) == 'y';
}