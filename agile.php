<?php
$dir = dirname(__FILE__);
chdir("$dir/www");

$admin_userid = 2;

require "Lib/load_database.php";

require("Modules/user/user_model.php");
$user = new User($mysqli, false);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

require ("Modules/system/system_stats_model.php");
$system_stats = new SystemStats($mysqli,$system);

$data = $system->list_admin();

$i = 0;

$date = new DateTime();
$date->setTimezone(new DateTimeZone('Europe/London'));
$date->modify("midnight");
$end = $date->getTimestamp();

$starts = array();

$date->modify("-7 days");
$starts['last7'] = $date->getTimestamp();

$date->setTimestamp($end);
$date->modify("-30 days");
$starts['last30'] = $date->getTimestamp();

$date->setTimestamp($end);
$date->modify("-90 days");
$starts['last90'] = $date->getTimestamp();

$date->setTimestamp($end);
$date->modify("-365 days");
$starts['last365'] = $date->getTimestamp();

// Half hour interval
$interval = 1800;

// Set start time to the start of the last 30 days
$start = $starts['last365'];

// Round to nearest interval
$start = floor($start/$interval)*$interval;
$end = ceil($end/$interval)*$interval;


$average = 0;
$delta = 1;
$timeformat = "notime";

// Load agile data feedid 473228 (AGILE-22-07-22:K_Southern_Wales)
$url = "https://emoncms.org/feed/data.json?id=473228&start=$start&end=$end&interval=$interval&average=0&delta=0&skipmissing=0&timeformat=$timeformat";
$agile = json_decode(file_get_contents($url));

foreach ($data as $row) {
    $userid = (int) $row->userid;
    $systemid = (int) $row->id;

    // if ($systemid!=46) continue;

    print $systemid."\n";
    

    $config = $system_stats->get_system_config_with_meta($userid, $systemid);
    if (is_array($config) && isset($config['success'])) {
        print "Error: ".$config['message']."\n";
        print $row->url."\n";
        continue;
    }

    if (isset($config->feeds)) {
        // print heatpump_elec_kwh
        if (isset($config->feeds->heatpump_elec_kwh)) {

            // append apikey if set
            $apikeystr = "";
            if ($config->apikey) $apikeystr = "&apikey=".$config->apikey;
            // Load heatpump_elec_kwh feed data
            $url = "$config->server/feed/data.json?id=".$config->feeds->heatpump_elec_kwh->feedid."&start=$start&end=$end&interval=$interval&average=0&delta=1&skipmissing=0&timeformat=$timeformat".$apikeystr;
            $result = json_decode(file_get_contents($url));

            // Test for feed count mismatch
            if (count($result)!=count($agile)) {
                print "Feed count mismatch\n";
                continue;
            }

            $sum_kwh = array();
            $sum_cost = array();

            $periods = array('last7','last30','last90','last365');
            foreach ($periods as $period) {
                $sum_kwh[$period] = 0;
                $sum_cost[$period] = 0;
            }

            for ($j=0; $j<count($result); $j++) {
                $time = $start + $j*$interval;
                $kwh = $result[$j];
                if ($kwh!=null) {
                    $unit_cost = $agile[$j]*0.01;

                    foreach ($periods as $period) {
                        if ($time>=$starts[$period]) {
                            $sum_kwh[$period] += $kwh;
                            $sum_cost[$period] += $kwh*$unit_cost;
                        }
                    }
                }
            }

            foreach ($periods as $period) {
                if ($sum_kwh[$period]>0) {
                    $unit_cost = $sum_cost[$period]/$sum_kwh[$period];
                    $unit_cost_vat = $unit_cost*1.05*100;
                    print "$period Cost: ".number_format($sum_cost[$period],2)." kWh: ".number_format($sum_kwh[$period],2)." Unit cost: ".number_format($unit_cost,3)." Unit cost with VAT: ".number_format($unit_cost_vat,3)."\n";
                    $mysqli->query("UPDATE system_stats_".$period."_v2 SET `unit_rate_agile` = '$unit_cost_vat' WHERE `id` = $systemid");

                }
            }

            

            print "\n";

        }
    }

    $i++;
    // if ($i>10) break;

}