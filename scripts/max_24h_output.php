<?php
// CLI only: this lives outside www/ and must never be served
if (PHP_SAPI !== 'cli') exit(1);

chdir(dirname(__DIR__)."/www");

define('EMONCMS_EXEC', 1);
require "core.php";
require "Lib/load_database.php";

require("Modules/user/user_model.php");
$user = new User($mysqli,false);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

require ("Modules/system/system_stats_model.php");
$system_stats = new SystemStats($mysqli,$system);

// tabs
print "location\theatpump output\theatpump model\theat loss survey\theat demand\tdiv 2.9\tkW1\t%1\tOT\tkW2\t%2\tOT\tkW3\t%3\tOT\tstart date\n";

$data = $system->list_admin();
foreach ($data as $row) {
    $userid = (int) $row->userid;
    if ($user_data = $user->get($userid)) {

        // Feed ids and read key come from the system's emoncms app (app_id +
        // readkey) on the configured emoncms host. Never from the url field:
        // users edit it, and it would point these requests at any host,
        // internal services and file:// included.
        $config = $system_stats->get_system_config_with_meta($userid, (int) $row->id);
        if (is_object($config) && isset($config->feeds->heatpump_heat_kwh->feedid)) {
            $server = $config->server;
            $feedid = (int) $config->feeds->heatpump_heat_kwh->feedid;

            if (isset($config->feeds->heatpump_outsideT->feedid)) {
                $outside_temp_feedid = (int) $config->feeds->heatpump_outsideT->feedid;
            } else {
                $outside_temp_feedid = false;
            }

            $end = time();
            // 1 year ago
            $start = $end - 365 * 24 * 60 * 60;

            $apikeystr = "";
            if ($config->apikey) $apikeystr = "&apikey=".urlencode($config->apikey);

            // get start date of feed
            $result = file_get_contents("$server/feed/getmeta.json?id=$feedid$apikeystr");
            $result = json_decode($result);

            $date = new DateTime();
            $date->setTimezone(new DateTimeZone('Europe/London'));
            $date->setTimestamp($result->start_time);
            $start_date = $date->format('d M Y');

            // print $server . "\n";
            $result = file_get_contents("$server/feed/data.json?id=$feedid&start=$start&end=$end&skipmissing=0&limitinterval=1&average=0&delta=1&interval=daily$apikeystr");
            $result = json_decode($result);
            
            // order array by value descending
            usort($result, function($a, $b) {
                return $b[1] <=> $a[1];
            });
            

            $csv = [];
            $csv[] = $row->location;
            $csv[] = $row->hp_output;
            $csv[] = $row->hp_model;
            $csv[] = $row->heat_loss;
            $csv[] = $row->heat_demand;
            $csv[] = number_format($row->heat_demand/2900,1);
            for ($i=0; $i<3; $i++) {

                $timestamp = $result[$i][0]*0.001;

                // get average outside temp for this day
                // print "$timestamp\n";
                if ($outside_temp_feedid) {
                    $start2 = $timestamp;
                    $end2 = $timestamp + 24*3600;
                    $result2 = file_get_contents("$server/feed/data.json?id=$outside_temp_feedid&start=$start2&end=$end2&skipmissing=0&limitinterval=1&average=1&delta=0&timeformat=unixms&interval=86400$apikeystr");
                    $result2 = json_decode($result2);
                    if (isset($result2[0]) && isset($result2[0][1])) {
                        $outside_temp = $result2[0][1];
                    } else {
                        $outside_temp = 85;
                    }
                } else {
                    $outside_temp = 85;
                }

                $heat_kwh = $result[$i][1];
                $heat_kw = $heat_kwh / 24.0;
                // format stirng with %
                $csv[] = number_format($heat_kw, 2);
                $csv[] = number_format(100*$heat_kw / $row->hp_output, 0)."";
                $csv[] = number_format($outside_temp, 1);
            }
            $csv[] = $start_date;
            print implode("\t", $csv). "\n";
        }
        // die;
    }
}
