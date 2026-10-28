<?php
define('EMONCMS_EXEC', 1);
$dir = dirname(__FILE__);
require "$dir/config.php";
chdir("$dir/../www");

require "core.php";
require "Lib/load_database.php";

require("Modules/user/user_model.php");
$user = new User($mysqli,false);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

// System stats model is used for loading system stats data
require ("Modules/system/system_stats_model.php");
$system_stats = new SystemStats($mysqli,$system);



$interval = 60; // 1 minute intervals

// Plausible outside air temperature range, values outside this are
// treated as sensor/unit errors and excluded from the aggregate
$outsideT_min = -40;
$outsideT_max = 50;

// Plausible per-system power limits (W). Datapoints outside these are treated
// as bad data (e.g. a feed scaled 1000x), excluded from the aggregate and the
// system is flagged in the summary at the end of the run
$heat_max = 100000;
$elec_max = 20000;
$flagged = array();

// --------------------------------------------------------------------------------
$emoncms_mysqli = @new mysqli(
    "localhost",
    "emoncms",
    "emoncms",
    "emonphp",
    3306
);

$feed_settings = array(
    'phpfina'       => array('datadir'  => '/var/opt/emoncms/phpfina/'),
    'phptimeseries' => array('datadir'  => '/var/opt/emoncms/phptimeseries/'),
    'max_datapoints'        => 70000
);

chdir("/var/www/emoncms");
require_once "Lib/enum.php";
//require_once "Lib/EmonLogger.php";
$log = new EmonLogger(__FILE__);

require_once "Modules/feed/feed_model.php";
$feed = new Feed($emoncms_mysqli,$redis,$feed_settings);
// --------------------------------------------------------------------------------

$date = new DateTime();
$date->setTimezone(new DateTimeZone("Europe/London"));

// end is nearest hour
$end = $date->getTimestamp();
$end = floor($end / 3600) * 3600;

$date->setTimestamp($end);
$date->modify("-7 days");
$start = $date->getTimestamp();

// Recalculate March 2026
// Set start date to midnight 1st March 2026
// Set end date to midnight +1 month 1st April 2026

/*
$date = new DateTime();
$date->setTimezone(new DateTimeZone("Europe/London"));


$date->setDate(2024,11,20);
$date->setTime(0,0,0);
$start = $date->getTimestamp();
$date->modify("+1 week");
$end = $date->getTimestamp();

*/


$count_all = 0;
$elec_sum  = array();
$heat_sum  = array();
$numsys = array();
$elec_sum_per_kw = array();
$heat_sum_per_kw = array();
$capacity_sum = array();
$numsys_per_kw = array();

$outside_temp_sum = array();
$outside_temp_numsys = array();

$total_dp = ($end - $start) / $interval;

for ($i=0; $i<$total_dp; $i++) {
    $elec_sum[$i] = 0;
    $heat_sum[$i] = 0;
    $numsys[$i] = 0;
    $elec_sum_per_kw[$i] = 0;
    $heat_sum_per_kw[$i] = 0;
    $capacity_sum[$i] = 0;
    $numsys_per_kw[$i] = 0;

    $outside_temp_sum[$i] = 0;
    $outside_temp_numsys[$i] = 0;
}

$data = $system->list_admin();
foreach ($data as $row) {

    // Skip non public systems
    if (!$row->share) continue;
    if (!$row->published) continue;

    $systemid = $row->id;
    echo "System ".$row->id." ".$row->location." ".$row->app_id."\n";
    $count_all++;

    $hp_output = 1*$row->hp_output;

    $app_config = get_app_config($emoncms_mysqli,$row->app_id);
    if ($app_config && isset($app_config->heatpump_elec) && isset($app_config->heatpump_heat)) {
        echo $app_config->heatpump_elec." ".$app_config->heatpump_heat."\n";

        if ($feed->exist((int)$app_config->heatpump_elec) && $feed->exist((int)$app_config->heatpump_heat)) {
            $feedid = (int)$app_config->heatpump_elec;
            $meta = $feed->get_meta($feedid);

            $heat_feedid = (int)$app_config->heatpump_heat;
            $heat_meta = $feed->get_meta($heat_feedid);

            // check if there is overlap between $start/$end and feed start/end time
            if ($meta->start_time > $end || $meta->end_time < $start) {
                echo "  No data in time range\n";
                continue;
            }

            $elec_data = $feed->get_data($feedid,$start,$end,$interval,1,"UTC","notime",false,0,0,false,0);
            $heat_data = $feed->get_data($heat_feedid,$start,$end,$interval,1,"UTC","notime",false,0,0,false,0);

            // Per-datapoint cooling flags, or false if the system is predominantly
            // heating. Defrost heat loss stays negative: heating-dominated systems
            // return false here, and short negative blips are cleared from the flags.
            $cooling = auto_detect_cooling(array("heatpump_heat"=>$heat_data), $interval);
            if ($cooling !== false) echo "  Cooling detected\n";

            for ($i=0; $i<$total_dp; $i++) {
                if ($elec_data[$i]===null) continue;
                if ($heat_data[$i]===null) continue;

                // Exclude implausible values and flag the system
                if (abs($heat_data[$i]) > $heat_max || abs($elec_data[$i]) > $elec_max) {
                    if (!isset($flagged[$systemid])) {
                        $flagged[$systemid] = array(
                            "location" => $row->location,
                            "userid" => $row->userid,
                            "app_id" => $row->app_id,
                            "count" => 0,
                            "first" => $start + $i * $interval,
                            "last" => 0,
                            "max_heat" => 0,
                            "max_elec" => 0
                        );
                    }
                    $flagged[$systemid]["count"]++;
                    $flagged[$systemid]["last"] = $start + $i * $interval;
                    $flagged[$systemid]["max_heat"] = max($flagged[$systemid]["max_heat"], abs($heat_data[$i]));
                    $flagged[$systemid]["max_elec"] = max($flagged[$systemid]["max_elec"], abs($elec_data[$i]));
                    continue;
                }

                $heat = $heat_data[$i];
                // Sign flip so that cooling output adds to the aggregate
                if ($cooling !== false && !empty($cooling[$i])) $heat = -1 * $heat;

                $elec_sum[$i] += $elec_data[$i];
                $heat_sum[$i] += $heat;
                $numsys[$i]++;

                if ($hp_output>0) {
                    $elec_sum_per_kw[$i] += $elec_data[$i]/$hp_output;
                    $heat_sum_per_kw[$i] += $heat/$hp_output;
                    $capacity_sum[$i] += $hp_output;
                    $numsys_per_kw[$i]++;
                }
            }


        } else {
            echo "  Feed does not exist\n";
        }
    }

    if ($app_config && isset($app_config->heatpump_outsideT)) {
        $outsideT_feedid = (int)$app_config->heatpump_outsideT;
        if ($feed->exist($outsideT_feedid)) {
            $outsideT_data = $feed->get_data($outsideT_feedid,$start,$end,$interval,1,"UTC","notime",false,0,0,false,0);
            for ($i=0; $i<$total_dp; $i++) {
                if ($outsideT_data[$i]===null) continue;
                if ($outsideT_data[$i]<$outsideT_min || $outsideT_data[$i]>$outsideT_max) continue;
                $outside_temp_sum[$i] += $outsideT_data[$i];
                $outside_temp_numsys[$i]++;
            }
        }
    }
}

if ($count_all == 0) {
    echo "No systems found\n";
} else {
    echo "Total systems: $count_all\n";
}

if (count($flagged)) {
    echo "\nWARNING: systems with implausible data (excluded from aggregate):\n";
    foreach ($flagged as $id => $f) {
        echo "  System $id ".$f["location"]." userid ".$f["userid"]." app ".$f["app_id"].": ".$f["count"]." datapoints ";
        echo date("Y-m-d H:i", $f["first"])." to ".date("Y-m-d H:i", $f["last"]).", ";
        echo "max heat ".round($f["max_heat"])." W, max elec ".round($f["max_elec"])." W\n";
    }
    echo "\n";
}

// Total power
$elec_data = array();
$heat_data = array();
$elec_data_kw_nrml = array();
$heat_data_kw_nrml = array();
$capacity_sum_data = array();
$numsys_data = array();

$outside_temp_data = array();

for ($i=0; $i<$total_dp; $i++) {
    $time = $start + $i * $interval;
    $elec_data[] = array($time,$elec_sum[$i]);
    $heat_data[] = array($time,$heat_sum[$i]);
    $elec_data_kw_nrml[] = array($time,$elec_sum_per_kw[$i] * $capacity_sum[$i] / max(1,$numsys_per_kw[$i]));
    $heat_data_kw_nrml[] = array($time,$heat_sum_per_kw[$i] * $capacity_sum[$i] / max(1,$numsys_per_kw[$i]));
    $capacity_sum_data[] = array($time,$capacity_sum[$i]);
    $numsys_data[] = array($time,$numsys[$i]);

    $outside_temp_data[] = array($time, $outside_temp_numsys[$i] > 0 ? $outside_temp_sum[$i] / $outside_temp_numsys[$i] : null);
}

$host = $aggregate_host;
$apikey = $aggregate_apikey;
echo "Feed 476418\n";
echo http_request2("POST","$host?id=476418",array("data"=>json_encode($elec_data),"apikey"=>$apikey));
echo http_request2("POST","$host?id=536674",array("data"=>json_encode($elec_data_kw_nrml),"apikey"=>$apikey));
echo http_request2("POST","$host?id=536693",array("data"=>json_encode($heat_data),"apikey"=>$apikey));
echo http_request2("POST","$host?id=536694",array("data"=>json_encode($heat_data_kw_nrml),"apikey"=>$apikey));
echo http_request2("POST","$host?id=536684",array("data"=>json_encode($capacity_sum_data),"apikey"=>$apikey));
echo http_request2("POST","$host?id=536695",array("data"=>json_encode($outside_temp_data),"apikey"=>$apikey));
echo http_request2("POST","$host?id=476417",array("data"=>json_encode($numsys_data),"apikey"=>$apikey));


// Advance start and end by 15 days
/*
$date->setTimestamp($start);
$date->modify("+7 days");
$start = $date->getTimestamp();
$date->setTimestamp($end);
$date->modify("+7 days");
$end = $date->getTimestamp();*/

//}

// From the main data processing pipeline: returns an array of per-datapoint
// cooling flags, or false if the system is predominantly heating.
function auto_detect_cooling($data, $interval) {

    if (!isset($data["heatpump_heat"])) {
        return false;
    }

    // Enable cooling only if cooling kWh > heating kWh
    $heat_kwh = 0;
    $cool_kwh = 0;
    foreach ($data["heatpump_heat"] as $z => $heat) {
        if ($heat !== null && $heat >= 0) {
            $heat_kwh += $heat * $interval / 3600;
        } else {
            $cool_kwh += -1 * $heat * $interval / 3600;
        }
    }
    // exit if heat is greater than cooling
    if ($heat_kwh > $cool_kwh*4) {
        return false;
    }

    $miniumum_cooling_time = 300;
    // $miniumum_cooling_outsideT = 10;
    $data_heatpump_cooling = [];

    $cool_state = false;
    $cool_start_index = 0;
    $cool_time = 0;
    // $total_cool_time = 0;

    foreach ($data["heatpump_heat"] as $z => $heat) {

        // if ($data["heatpump_outsideT"][$z][1] !== null) {
        //     $outsideT = $data["heatpump_outsideT"][$z][1];
        // }

        if ($heat !== null && $heat < 0) { //  && $outsideT > $miniumum_cooling_outsideT
            if ($cool_time == 0) $cool_start_index = $z;
            $cool_state = true;
            $cool_time += $interval;
            // $total_cool_time += $interval;
        } else {
            if ($cool_state && $cool_time <= $miniumum_cooling_time) {
                // Clear if too short
                for ($y = $cool_start_index; $y < $z; $y++) {
                    $data_heatpump_cooling[$y] = 0;
                }
            }
            $cool_time = 0;
            $cool_state = false;
        }

        $data_heatpump_cooling[] = $cool_state;
    }

    // returns the data object with the cooling flag added
    return $data_heatpump_cooling;
}

function get_app_config($emoncms_mysqli,$app_id) {
    $app_id = (int) $app_id;
    $result = $emoncms_mysqli->query("SELECT config FROM app WHERE id=".$app_id);
    $row_app = $result->fetch_object();
    if (!isset($row_app->config)) {
        return false;
    }
    return json_decode($row_app->config);
}

function http_request2($method, $url, $data)
{
    $options = array();

    if ($method=="GET") {
        $urlencoded = http_build_query($data);
        $url = "$url?$urlencoded";
    } elseif ($method=="POST") {
        $options[CURLOPT_POST] = 1;
        $options[CURLOPT_POSTFIELDS] = http_build_query($data);
    }

    $options[CURLOPT_URL] = $url;
    $options[CURLOPT_RETURNTRANSFER] = 1;
    $options[CURLOPT_CONNECTTIMEOUT] = 2;
    $options[CURLOPT_TIMEOUT] = 5;

    $curl = curl_init();
    curl_setopt_array($curl, $options);
    $resp = curl_exec($curl);
    if (PHP_VERSION_ID < 80000) {
        // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated
        curl_close($curl);
    }
    return $resp;
}