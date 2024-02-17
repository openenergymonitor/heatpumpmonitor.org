<?php
$dir = dirname(__FILE__);
chdir("$dir/www");

require "Lib/load_database.php";

require("Modules/user/user_model.php");
$user = new User($mysqli, false);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

require ("Modules/system/system_stats_model.php");
$system_stats = new SystemStats($mysqli,$system);

print "Updating rolling stats: ".date("Y-m-d H:i:s")."\n";
print "- directory: $dir\n";


$date = new DateTime();
$date->setTimezone(new DateTimeZone('Europe/London'));
$date->modify("midnight");
$end = $date->getTimestamp();

$date->modify("-30 days");
$start_last30 = $date->getTimestamp();

$date->setTimestamp($end);
$date->modify("-365 days");
$start_last365 = $date->getTimestamp();

//$start = microtime(true);
$data = $system->list_admin();
foreach ($data as $meta) {
    $systemid = $meta->id;
    if ($meta->id!=116) continue;
    $userid = (int) $meta->userid;
    if ($user_data = $user->get($userid)) {
    
        // get data period
        $result = $system_stats->get_data_period($meta->url);
        if (!$result['success']) {
            print "- error loading data period\n";
            continue;
        }

        $data_start = $result['period']->start;
        $data_end = $result['period']->end;
        $start = $data_start;

        for ($x=0; $x<50; $x++) {

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
            // +30 days
            $date->modify("+60 days");
            $end = $date->getTimestamp();
            if ($end>$data_end) {
                $end = $data_end;
            }
            $date->setTimestamp($end);
            $end_str = $date->format("Y-m-d");

            print "- start: ".$start_str." end: ".$end_str."\n";

            $result = $system_stats->load_from_url($meta->url, $start, $end, 'getdaily');

            // split csv into array, first line is header
            $csv = explode("\n", $result);
            $fields = str_getcsv($csv[0]);
            if ($fields[0]!="timestamp") die("error");

            $days = 0;
            // for each line, split into array
            for ($i=1; $i<count($csv); $i++) {
                if ($csv[$i]) {
                    $values = str_getcsv($csv[$i]);

                    $row = array();
                    for ($j=0; $j<count($fields); $j++) {
                        $row[$fields[$j]] = $values[$j];
                    }
                    $system_stats->save_day($systemid, $row);
                    $days++;
                }
            }
            print "- days: $days\n";

            if ($end==$data_end) {
                break;
            }
        }
    }
}
//$end = microtime(true);
//$duration = $end - $start;
print "- systems: ".count($data)."\n";
//print "- duration: ".number_format($duration,1,'.',',')."s\n";
