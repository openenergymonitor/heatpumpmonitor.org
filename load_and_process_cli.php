<?php

$dir = dirname(__FILE__);
chdir("$dir/www");

$fp = fopen("/opt/openenergymonitor/heatpumpmonitor/hpmon.lock", "w");
if (! flock($fp, LOCK_EX | LOCK_NB)) { echo "Already running\n"; die; }

define('EMONCMS_EXEC', 1);
require "Lib/load_database.php";

require("Modules/user/user_model.php");
$user = new User($mysqli, false);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

require ("Modules/system/system_stats_model.php");
$system_stats = new SystemStats($mysqli,$system);

logger("############################################");
logger("HeatpumpMonitor.org CLI: ".date("Y-m-d H:i:s"));
logger("############################################");
logger("Settings:");
logger("- directory: $dir");

$starttimer = time();

$single_system = false;
if (isset($argv[1])) {
    $single_system = (int) $argv[1];
    if ($single_system<1) {
        $single_system = false;
    } else {
        logger("- single system: $single_system");
    }
}
if (!$single_system) {
    logger("- all systems");
}

$reload = false;
if (isset($argv[2])) {
    if ($argv[2] == "all") {
        $reload = true;
        logger("- reload all");
    }
}
if (!$reload) {
    logger("- load new data only");
}

// Load all systems
$systemlist = $system->list_admin();

load_daily_stats($systemlist, $single_system, $reload);
//process_rolling_stats($systemlist, $single_system, $reload);
//process_monthly_stats($systemlist, $single_system, $reload);

function load_daily_stats($systemlist, $single_system, $reload) {
    global $user;
    
    $loaded_systems = 0;

    foreach ($systemlist as $row) {
        $systemid = $row->id;
        if ($single_system && $systemid!=$single_system) continue;
        
        $userid = (int) $row->userid;
        //if ($systemid<426) continue;
  
        if ($user_data = $user->get($userid)) {
            load_daily_stats_system($row, $reload);

            process_rolling_stats($systemlist, $systemid, $reload);
            process_monthly_stats($systemlist, $systemid, $reload);
            $loaded_systems ++;
        }
    }
    logger("- loaded systems: ".$loaded_systems);
}

function load_daily_stats_system($meta, $reload) {

    global $mysqli, $user, $system, $system_stats;

    $systemid = $meta->id;

    $url = parse_url($meta->url);
    $host = "";
    if (isset($url['host'])) {
        $host = $url['host'];
    }

    logger("----------------------------------");
    logger("System: ".$meta->id.", Host: ".$host);
    logger("----------------------------------");

    if ($reload !== false) {
        // Clearing daily data on data server
        $result = $system_stats->clear_daily($meta->url);
        if (isset($result['success']) && $result['success']) {
            logger("- daily data cleared");
        } else {
            logger(json_encode($result));
            logger("- error clearing daily data");
            return false;
        }
    }

    for ($i=0; $i<1000; $i++) {
        $result = $system_stats->process_data($meta->url,10);

        if ($result == null || !isset($result['days_left'])) {
            logger(json_encode($result));
            logger("- error loading data");
            return false;
        }
        
        if ($result['days_left'] <= 0) {
            logger("- days left: ".$result['days_left']);
            
            // Enable daily mode in dashboard
            $system_stats->enable_daily_mode($meta->url);

            break;
        } else {
            logger("- days left: ".$result['days_left']);
            sleep(1);
        }
    }

    // get data period
    $result = $system_stats->get_data_period($meta->url);

    if (!$result['success']) {
        logger("- error loading data period");
        return false;
    }

    $end = false;

    $data_start = $result['period']->start;
    $data_end = $result['period']->end;
    $start = $data_start;

    if ($reload !== false) {
        $mysqli->query("DELETE FROM system_stats_daily WHERE `id`='$systemid'");
        $mysqli->query("DELETE FROM system_stats_all_v2 WHERE `id`='$systemid'");
        $mysqli->query("DELETE FROM system_stats_last365_v2 WHERE `id`='$systemid'");
        $mysqli->query("DELETE FROM system_stats_last90_v2 WHERE `id`='$systemid'");
        $mysqli->query("DELETE FROM system_stats_last30_v2 WHERE `id`='$systemid'");
        $mysqli->query("DELETE FROM system_stats_last7_v2 WHERE `id`='$systemid'");
        $mysqli->query("DELETE FROM system_stats_monthly_v2 WHERE `id`='$systemid'");
    }

    for ($x=0; $x<200; $x++) {

        $last_start = $start;
        
        // get most recent entry in db
        $result = $mysqli->query("SELECT MAX(timestamp) AS timestamp FROM system_stats_daily WHERE `id`='$systemid'");
        if ($row = $result->fetch_assoc()) {
            if ($row['timestamp']>$data_start) {
                $start = $row['timestamp'];
            }
        }

        // datatime get midnight
        $date = new DateTime();
        $date->setTimezone(new DateTimeZone("Europe/London"));
        $date->setTimestamp($start);
        $date->modify("midnight");
        
        $start = $date->getTimestamp();
        $start_str = $date->format("Y-m-d");

        if ($host != "emoncms.org") {
            //echo "host != emoncms.org\n";
            //break;
        }

        // +30 days
        if ($host=="emoncms.org") {
            $date->modify("+364 days");
        } else {
            $date->modify("+7 days"); 
        }

        $last_end = $end;
        $end = $date->getTimestamp();
        if ($end>$data_end) {
            $end = $data_end;
        }
        $date->setTimestamp($end);
        $end_str = $date->format("Y-m-d");

        logger("- start: ".$start_str." end: ".$end_str);
        if ($start_str==$end_str) {
            echo "start_str == end_str\n";
            break;
        }

        if ($end<=$start) {
            echo "end <= start\n";
            break;
        }

        if ($x>1) {
            if ($start==$last_start) {
                echo "start == last_start\n";
                break;
            }    
            if ($end==$last_end) {
                echo "end == last_end\n";
                break;
            }
        }

        if ($result = $system_stats->load_from_url($meta->url, $start, $end, 'getdailydata')) 
        {
            // split csv into array, first line is header
            $csv = explode("\n", $result);
            $fields = str_getcsv($csv[0]);
            if ($fields[1]!="timestamp") {
                echo $result;
                break;
            }

            $days = 0;
            // for each line, split into array
            for ($i=1; $i<count($csv); $i++) {
                if ($csv[$i]) {
                    $values = str_getcsv($csv[$i]);

                    $row = array();
                    for ($j=0; $j<count($fields); $j++) {
                        $row[$fields[$j]] = $values[$j];
                        // if '' then set to null
                        if ($row[$fields[$j]]=="") $row[$fields[$j]] = null;
                    }

                    $system_stats->save_day($systemid, $row);
                    $days++;
                }
            }
            logger("- days: $days");
        }
        sleep(1);
        

        if ($end==$data_end) {
            break;
        }
    }
}

function process_rolling_stats($systemlist, $single_system, $reload) {
    global $mysqli, $user, $system, $system_stats;

    $date = new DateTime();
    $date->setTimezone(new DateTimeZone('Europe/London'));
    $date->modify("midnight");
    $end = $date->getTimestamp()+(3600*24);

    $date->modify("-7 days");
    $start_last7 = $date->getTimestamp();

    $date->setTimestamp($end);
    $date->modify("-30 days");
    $start_last30 = $date->getTimestamp();

    $date->setTimestamp($end);
    $date->modify("-90 days");
    $start_last90 = $date->getTimestamp();

    $date->setTimestamp($end);
    $date->modify("-365 days");
    $start_last365 = $date->getTimestamp();

    $processed_systems = 0;
    
    foreach ($systemlist as $row) {
        $systemid = $row->id;
        if ($single_system && $systemid!=$single_system) continue;
        
        $userid = (int) $row->userid;
        if ($user_data = $user->get($userid)) {

            // ALL
            $stats = $system_stats->process_from_daily($systemid,false,false);
            if ($stats == false) continue;
            $mysqli->query("DELETE FROM system_stats_all_v2 WHERE id=$systemid");
            $system_stats->save_stats_table('system_stats_all_v2',$stats);
        
            // Last 365 days
            $stats = $system_stats->process_from_daily($systemid,$start_last365,$end);
            if ($stats == false) continue;
            $mysqli->query("DELETE FROM system_stats_last365_v2 WHERE id=$systemid");
            $system_stats->save_stats_table('system_stats_last365_v2',$stats);

            // Last 90 days
            $stats = $system_stats->process_from_daily($systemid,$start_last90,$end);
            if ($stats == false) continue;
            $mysqli->query("DELETE FROM system_stats_last90_v2 WHERE id=$systemid");
            $system_stats->save_stats_table('system_stats_last90_v2',$stats);

            // Last 30 days
            $stats = $system_stats->process_from_daily($systemid,$start_last30,$end);
            if ($stats == false) continue;
            $mysqli->query("DELETE FROM system_stats_last30_v2 WHERE id=$systemid");
            $system_stats->save_stats_table('system_stats_last30_v2',$stats);

            // Last 7 days
            $stats = $system_stats->process_from_daily($systemid,$start_last7,$end);
            if ($stats == false) continue;
            $mysqli->query("DELETE FROM system_stats_last7_v2 WHERE id=$systemid");
            $system_stats->save_stats_table('system_stats_last7_v2',$stats);

            $processed_systems ++;            
        }
    }
    logger("- processed rolling systems: ".$processed_systems);
}

function process_monthly_stats($systemlist, $single_system, $reload) {
    global $mysqli, $user, $system, $system_stats;
    
    $date = new DateTime();
    $date->setTimezone(new DateTimeZone('Europe/London'));
    $date->modify("midnight");
    $end = $date->getTimestamp();

    $date->modify("-30 days");
    $start_last30 = $date->getTimestamp();

    $date->setTimestamp($end);
    $date->modify("-365 days");
    $start_last365 = $date->getTimestamp();

    $processed_systems = 0;
    
    foreach ($systemlist as $row) {
        $systemid = $row->id;
        if ($single_system && $systemid!=$single_system) continue;
        
        $userid = (int) $row->userid;
        if ($user_data = $user->get($userid)) {
            
            // Get earliest day of data for this system
            $result = $mysqli->query("SELECT MIN(timestamp) AS start FROM system_stats_daily WHERE id = $systemid");
            $row = $result->fetch_object();
            $data_start = $row->start;
            if ($data_start == null) {
                continue;
            }

            // Get last day of data for this system
            $result = $mysqli->query("SELECT MAX(timestamp) AS end FROM system_stats_daily WHERE id = $systemid");
            $row = $result->fetch_object();
            $data_end = $row->end;
            if ($data_end == null) {
                continue;
            }

            $date->setTimestamp($data_start);
            // modify to start of month
            $date->modify("midnight first day of this month");
            $start = $date->getTimestamp();
            $start_str = $date->format("Y-m-d");
            $date->modify("+1 month");
            $end = $date->getTimestamp();
            $end_str = $date->format("Y-m-d");

            // for each month
            while ($start < $data_end) {

                $stats = $system_stats->process_from_daily($systemid,$start,$end);
                if ($stats == false) {
                    logger("No data for system $systemid");
                    break;
                }
                
                $mysqli->query("DELETE FROM system_stats_monthly_v2 WHERE id=$systemid AND timestamp=$start");
                $system_stats->save_stats_table('system_stats_monthly_v2',$stats);

                $start = $end;
                $date->modify("+1 month");
                $end = $date->getTimestamp();
                $start_str = $end_str;
                $end_str = $date->format("Y-m-d");

            }
            
            $processed_systems ++;
        }
    }
    logger("- processed monthly systems: ".$processed_systems);

    global $starttimer;
    $elapsed = time()-$starttimer;
    logger("- ".$elapsed);
}

function logger($message) {
    print $message."\n";
}
