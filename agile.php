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

// Generate Octopus Cosy data
// Day rate: 24.51 p/kWh
// Cosy rate 12.01 p/kWh (04:00-07:00 & 13:00-16:00)
// Peak rate 35.55 p/kWh (16:00-19:00)

$cosy = array();
for ($j=0; $j<count($agile); $j++) {
    $time = $start + $j*$interval;
    $date->setTimestamp($time);
    $hour = $date->format('G');
    if ($hour>=4 && $hour<7) $cosy[$j] = 12.01;
    else if ($hour>=13 && $hour<16) $cosy[$j] = 12.01;
    else if ($hour>=16 && $hour<19) $cosy[$j] = 35.55;
    else $cosy[$j] = 24.51;
}


// Generate Octopus Go data
// Day rate: 27.26 p/kWh
// Night rate 8.00 p/kWh (00:30-05:30)
$go = array();
for ($j=0; $j<count($agile); $j++) {
    $time = $start + $j*$interval;
    $date->setTimestamp($time);
    // get hour + minutes / 60
    $hour = $date->format('G') + $date->format('i')/60;
    if ($hour>=0.5 && $hour<5.5) $go[$j] = 8.00;
    else $go[$j] = 27.26;
}

$eon_next_pumped_v2 = array();
// 00:00-04:00: 25.67 p/kWh
// 04:00-07:00: 11 p/kWh
// 07:00-13:00: 25.67 p/kWh
// 13:00-16:00: 11 p/kWh
// 16:00-19:00: 32.91 p/kWh
// 19:00-23:59: 25.67 p/kWh
for ($j=0; $j<count($agile); $j++) {
    $time = $start + $j*$interval;
    $date->setTimestamp($time);
    $hour = $date->format('G');

    // default to 25.67
    $eon_next_pumped_v2[$j] = 25.67;

    if ($hour>=4 && $hour<7) $eon_next_pumped_v2[$j] = 11;
    else if ($hour>=13 && $hour<16) $eon_next_pumped_v2[$j] = 11;
    else if ($hour>=16 && $hour<19) $eon_next_pumped_v2[$j] = 32.91;
}

foreach ($data as $row) {
    $userid = (int) $row->userid;
    $systemid = (int) $row->id;

    //if ($systemid!=150) continue;

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
            $sum_cost_agile = array();
            $sum_cost_cosy = array();
            $sum_cost_go = array();
            $sum_cost_eon_next_pumped_v2 = array();

            $months_kwh = array();
            $months_agile_cost = array();
            $months_cosy_cost = array();
            $months_go_cost = array();
            $months_eon_next_pumped_v2_cost = array();

            $periods = array('last7','last30','last90','last365');
            foreach ($periods as $period) {
                $sum_kwh[$period] = 0;
                $sum_cost_agile[$period] = 0;
                $sum_cost_cosy[$period] = 0;
                $sum_cost_go[$period] = 0;
                $sum_cost_eon_next_pumped_v2[$period] = 0;
            }

            for ($j=0; $j<count($result); $j++) {
                $time = $start + $j*$interval;
                $kwh = $result[$j];
                if ($kwh!=null) {
                    $unit_cost_agile = $agile[$j]*0.01;
                    $unit_cost_cosy = $cosy[$j]*0.01;
                    $unit_cost_go = $go[$j]*0.01;
                    $unit_cost_eon_next_pumped_v2 = $eon_next_pumped_v2[$j]*0.01;

                    foreach ($periods as $period) {
                        if ($time>=$starts[$period]) {
                            $sum_kwh[$period] += $kwh;
                            $sum_cost_agile[$period] += $kwh*$unit_cost_agile;
                            $sum_cost_cosy[$period] += $kwh*$unit_cost_cosy;
                            $sum_cost_go[$period] += $kwh*$unit_cost_go;
                            $sum_cost_eon_next_pumped_v2[$period] += $kwh*$unit_cost_eon_next_pumped_v2;
                        }
                    }

                    // Allocate to months by start time
                    $date->setTimestamp($time);
                    // get timestamp of start of month
                    $date->modify("midnight first day of this month");
                    // 00:00:00
                    $date->setTime(0,0,0);

                    $month = $date->getTimestamp();

                    if (!isset($months_kwh[$month])) {
                        $months_kwh[$month] = 0;
                        $months_agile_cost[$month] = 0;
                        $months_cosy_cost[$month] = 0;
                        $months_go_cost[$month] = 0;
                        $months_eon_next_pumped_v2_cost[$month] = 0;
                    }

                    $months_kwh[$month] += $kwh;
                    $months_agile_cost[$month] += $kwh*$unit_cost_agile;
                    $months_cosy_cost[$month] += $kwh*$unit_cost_cosy;
                    $months_go_cost[$month] += $kwh*$unit_cost_go;
                    $months_eon_next_pumped_v2_cost[$month] += $kwh*$unit_cost_eon_next_pumped_v2;
                }
            }

            foreach ($periods as $period) {
                if ($sum_kwh[$period]>0) {
                    $unit_cost_agile = $sum_cost_agile[$period]/$sum_kwh[$period];
                    $unit_cost_agile_vat = $unit_cost_agile*1.05*100;
                    $unit_cost_cosy = $sum_cost_cosy[$period]/$sum_kwh[$period]*100;
                    $unit_cost_go = $sum_cost_go[$period]/$sum_kwh[$period]*100;
                    $unit_cost_eon_next_pumped_v2 = $sum_cost_eon_next_pumped_v2[$period]/$sum_kwh[$period]*100;

                    print "$period Agile: £".number_format($sum_cost_agile[$period],2).", ".number_format($sum_kwh[$period],2)." kWh, ".number_format($unit_cost_agile_vat,3)." p/kWh\n";
                    print "$period Cosy: £".number_format($sum_cost_cosy[$period],2).", ".number_format($sum_kwh[$period],2)." kWh, ".number_format($unit_cost_cosy,3)." p/kWh\n";                    
                    print "$period Go: £".number_format($sum_cost_go[$period],2).", ".number_format($sum_kwh[$period],2)." kWh, ".number_format($unit_cost_go,3)." p/kWh\n";
                    print "$period EON Next Pumped V2: £".number_format($sum_cost_eon_next_pumped_v2[$period],2).", ".number_format($sum_kwh[$period],2)." kWh, ".number_format($unit_cost_eon_next_pumped_v2,3)." p/kWh\n";

                    $mysqli->query("UPDATE system_stats_".$period."_v2 SET `unit_rate_agile` = '$unit_cost_agile_vat' WHERE `id` = $systemid");
                    $mysqli->query("UPDATE system_stats_".$period."_v2 SET `unit_rate_cosy` = '$unit_cost_cosy' WHERE `id` = $systemid");
                    $mysqli->query("UPDATE system_stats_".$period."_v2 SET `unit_rate_go` = '$unit_cost_go' WHERE `id` = $systemid");
                    $mysqli->query("UPDATE system_stats_".$period."_v2 SET `unit_rate_eon_next_pumped_v2` = '$unit_cost_eon_next_pumped_v2' WHERE `id` = $systemid");

                }
            }

            // Monthly data
            foreach ($months_kwh as $month => $kwh) {
                if ($kwh>0) {
                    $unit_cost_agile = $months_agile_cost[$month]/$kwh;
                    $unit_cost_agile_vat = $unit_cost_agile*1.05*100;
                    $unit_cost_cosy = $months_cosy_cost[$month]/$kwh*100;
                    $unit_cost_go = $months_go_cost[$month]/$kwh*100;
                    $unit_cost_eon_next_pumped_v2 = $months_eon_next_pumped_v2_cost[$month]/$kwh*100;

                    $date->setTimestamp($month);
                    $monthstr = $date->format('M Y');

                    print "$monthstr Agile: £".number_format($months_agile_cost[$month],2).", ".number_format($kwh,2)." kWh, ".number_format($unit_cost_agile_vat,3)." p/kWh\n";
                    print "$monthstr Cosy: £".number_format($months_cosy_cost[$month],2).", ".number_format($kwh,2)." kWh, ".number_format($unit_cost_cosy,3)." p/kWh\n";
                    print "$monthstr Go: £".number_format($months_go_cost[$month],2).", ".number_format($kwh,2)." kWh, ".number_format($unit_cost_go,3)." p/kWh\n";
                    print "$monthstr EON Next Pumped V2: £".number_format($months_eon_next_pumped_v2_cost[$month],2).", ".number_format($kwh,2)." kWh, ".number_format($unit_cost_eon_next_pumped_v2,3)." p/kWh\n";

                    $mysqli->query("UPDATE system_stats_monthly_v2 SET `unit_rate_agile` = '$unit_cost_agile_vat' WHERE `id` = $systemid AND `timestamp` = $month");
                    $mysqli->query("UPDATE system_stats_monthly_v2 SET `unit_rate_cosy` = '$unit_cost_cosy' WHERE `id` = $systemid AND `timestamp` = $month");
                    $mysqli->query("UPDATE system_stats_monthly_v2 SET `unit_rate_go` = '$unit_cost_go' WHERE `id` = $systemid AND `timestamp` = $month");
                    $mysqli->query("UPDATE system_stats_monthly_v2 SET `unit_rate_eon_next_pumped_v2` = '$unit_cost_eon_next_pumped_v2' WHERE `id` = $systemid AND `timestamp` = $month");

                }
            }

            

            print "\n";

        }
    }

    $i++;
    // if ($i>10) break;

}
