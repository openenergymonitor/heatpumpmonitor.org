<?php
// Baseline benchmark: the per-system processing loop of scripts/aggregate_v2_last7.php
// (lines 116-252) run unchanged against a synthetic dataset through the real
// emoncms Feed model. Only the metadata sources differ: the system list comes
// from manifest.json, feeds/app from the synthetic database, and nothing is posted.
//
// php baseline.php DATASET_DIR START END OUT.json

define('EMONCMS_EXEC', 1);
$dataset = rtrim($argv[1], "/");
$start = (int) $argv[2];
$end = (int) $argv[3];
$outfile = $argv[4];

$manifest = json_decode(file_get_contents("$dataset/manifest.json"));
$interval = 60;
$outsideT_min = -40;
$outsideT_max = 50;
$heat_max = 100000;
$elec_max = 20000;
$flagged = array();
$settings = array('log' => array('enabled' => false));

$emoncms_mysqli = new mysqli("localhost", "emoncms", "emoncms", $manifest->db, 3306);
$redis = new Redis();
$redis->connect("127.0.0.1");
$redis->select(9);   // keep synthetic feed:* keys away from the local emoncms
$redis->flushDB();

$feed_settings = array(
    'phpfina'       => array('datadir'  => "$dataset/phpfina/"),
    'phptimeseries' => array('datadir'  => "$dataset/phptimeseries/"),
    'max_datapoints'        => 70000,
    'redisbuffer' => array('enabled' => false)
);

chdir("/var/www/emoncms");
require_once "Lib/enum.php";
require_once "Lib/EmonLogger.php";
if (!function_exists('tr')) { function tr($s) { return $s; } }
$log = new EmonLogger(__FILE__);
require_once "Modules/feed/feed_model.php";
$feed = new Feed($emoncms_mysqli,$redis,$feed_settings);

$t_start = microtime(true);
$t_get_data = 0;

$count_all = 0;
$total_dp = ($end - $start) / $interval;
$elec_sum = array_fill(0, $total_dp, 0);
$heat_sum = array_fill(0, $total_dp, 0);
$numsys = array_fill(0, $total_dp, 0);
$elec_sum_per_kw = array_fill(0, $total_dp, 0);
$heat_sum_per_kw = array_fill(0, $total_dp, 0);
$capacity_sum = array_fill(0, $total_dp, 0);
$numsys_per_kw = array_fill(0, $total_dp, 0);
$outside_temp_sum = array_fill(0, $total_dp, 0);
$outside_temp_numsys = array_fill(0, $total_dp, 0);

foreach ($manifest->systems as $row) {
    if (!$row->share) continue;
    if (!$row->published) continue;

    $systemid = $row->id;
    $count_all++;
    $hp_output = 1*$row->hp_output;

    $app_config = get_app_config($emoncms_mysqli,$row->app_id);
    if ($app_config && isset($app_config->heatpump_elec) && isset($app_config->heatpump_heat)) {
        if ($feed->exist((int)$app_config->heatpump_elec) && $feed->exist((int)$app_config->heatpump_heat)) {
            $feedid = (int)$app_config->heatpump_elec;
            $meta = $feed->get_meta($feedid);
            $heat_feedid = (int)$app_config->heatpump_heat;
            $heat_meta = $feed->get_meta($heat_feedid);

            if ($meta->start_time > $end || $meta->end_time < $start) {
                continue;
            }

            $t = microtime(true);
            $elec_data = $feed->get_data($feedid,$start,$end,$interval,1,"UTC","notime",false,0,0,false,0);
            $heat_data = $feed->get_data($heat_feedid,$start,$end,$interval,1,"UTC","notime",false,0,0,false,0);
            $t_get_data += microtime(true) - $t;

            $cooling = auto_detect_cooling(array("heatpump_heat"=>$heat_data), $interval);

            for ($i=0; $i<$total_dp; $i++) {
                if ($elec_data[$i]===null) continue;
                if ($heat_data[$i]===null) continue;

                if (abs($heat_data[$i]) > $heat_max || abs($elec_data[$i]) > $elec_max) {
                    if (!isset($flagged[$systemid])) {
                        $flagged[$systemid] = array("count" => 0, "first" => $start + $i * $interval, "last" => 0, "max_heat" => 0, "max_elec" => 0);
                    }
                    $flagged[$systemid]["count"]++;
                    $flagged[$systemid]["last"] = $start + $i * $interval;
                    $flagged[$systemid]["max_heat"] = max($flagged[$systemid]["max_heat"], abs($heat_data[$i]));
                    $flagged[$systemid]["max_elec"] = max($flagged[$systemid]["max_elec"], abs($elec_data[$i]));
                    continue;
                }

                $heat = $heat_data[$i];
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
        }
    }

    if ($app_config && isset($app_config->heatpump_outsideT)) {
        $outsideT_feedid = (int)$app_config->heatpump_outsideT;
        if ($feed->exist($outsideT_feedid)) {
            $t = microtime(true);
            $outsideT_data = $feed->get_data($outsideT_feedid,$start,$end,$interval,1,"UTC","notime",false,0,0,false,0);
            $t_get_data += microtime(true) - $t;
            for ($i=0; $i<$total_dp; $i++) {
                if ($outsideT_data[$i]===null) continue;
                if ($outsideT_data[$i]<$outsideT_min || $outsideT_data[$i]>$outsideT_max) continue;
                $outside_temp_sum[$i] += $outsideT_data[$i];
                $outside_temp_numsys[$i]++;
            }
        }
    }
}

$out = array("elec"=>array(), "heat"=>array(), "elec_kw_nrml"=>array(), "heat_kw_nrml"=>array(),
             "capacity"=>array(), "outsideT"=>array(), "numsys"=>array());
for ($i=0; $i<$total_dp; $i++) {
    $out["elec"][] = $elec_sum[$i];
    $out["heat"][] = $heat_sum[$i];
    $out["elec_kw_nrml"][] = $elec_sum_per_kw[$i] * $capacity_sum[$i] / max(1,$numsys_per_kw[$i]);
    $out["heat_kw_nrml"][] = $heat_sum_per_kw[$i] * $capacity_sum[$i] / max(1,$numsys_per_kw[$i]);
    $out["capacity"][] = $capacity_sum[$i];
    $out["numsys"][] = $numsys[$i];
    $out["outsideT"][] = $outside_temp_numsys[$i] > 0 ? $outside_temp_sum[$i] / $outside_temp_numsys[$i] : null;
}
$out["flagged"] = $flagged;
$out["count_all"] = $count_all;
$out["seconds_total"] = microtime(true) - $t_start;
$out["seconds_get_data"] = $t_get_data;
file_put_contents($outfile, json_encode($out));
$redis->flushDB();
fprintf(STDERR, "systems %d  total %.2fs  get_data %.2fs  flagged %d\n", $count_all, $out["seconds_total"], $t_get_data, count($flagged));

function auto_detect_cooling($data, $interval) {
    if (!isset($data["heatpump_heat"])) return false;
    $heat_kwh = 0;
    $cool_kwh = 0;
    foreach ($data["heatpump_heat"] as $z => $heat) {
        if ($heat !== null && $heat >= 0) {
            $heat_kwh += $heat * $interval / 3600;
        } else {
            $cool_kwh += -1 * $heat * $interval / 3600;
        }
    }
    if ($heat_kwh > $cool_kwh*4) return false;
    $miniumum_cooling_time = 300;
    $data_heatpump_cooling = [];
    $cool_state = false;
    $cool_start_index = 0;
    $cool_time = 0;
    foreach ($data["heatpump_heat"] as $z => $heat) {
        if ($heat !== null && $heat < 0) {
            if ($cool_time == 0) $cool_start_index = $z;
            $cool_state = true;
            $cool_time += $interval;
        } else {
            if ($cool_state && $cool_time <= $miniumum_cooling_time) {
                for ($y = $cool_start_index; $y < $z; $y++) $data_heatpump_cooling[$y] = 0;
            }
            $cool_time = 0;
            $cool_state = false;
        }
        $data_heatpump_cooling[] = $cool_state;
    }
    return $data_heatpump_cooling;
}

function get_app_config($emoncms_mysqli,$app_id) {
    $app_id = (int) $app_id;
    $result = $emoncms_mysqli->query("SELECT config FROM app WHERE id=".$app_id);
    $row_app = $result->fetch_object();
    if (!isset($row_app->config)) return false;
    return json_decode($row_app->config);
}
