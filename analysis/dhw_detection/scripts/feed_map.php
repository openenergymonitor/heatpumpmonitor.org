<?php
// Write the feed ids of every public system's model inputs and hot-water flag, for
// scripts/predict.py --source phpfina MAP. Run on the server that holds the feed files:
//
//   php scripts/feed_map.php > feed_map.json
//
// The ids come from each system's myheatpump app config, as the public API uses them.
// predict.py reads a system's PHPFina files directly when all its feeds are PHPFina
// (engine 5), and fetches the rest (PHPTimeSeries) through the public API.

define('EMONCMS_EXEC', 1);
chdir(dirname(__FILE__)."/../../../www");
require "Lib/load_database.php";

define('PHPFINA_DIR', "/var/opt/emoncms/phpfina/");
$keys = array("heatpump_elec", "heatpump_heat", "heatpump_flowT", "heatpump_returnT", "heatpump_flowrate", "heatpump_dhw");

$systems = array();
$result = $mysqli->query("SELECT id, app_id FROM system_meta WHERE share=1 AND published=1 ORDER BY id");
while ($s = $result->fetch_object()) {
    $app = $emoncms_mysqli->query("SELECT config FROM app WHERE id = ".(int) $s->app_id);
    if (!$app || !($row = $app->fetch_object())) continue;
    $config = json_decode($row->config);

    $feeds = array();
    foreach ($keys as $key) {
        if (!isset($config->$key) || !(int) $config->$key) continue;
        $feedid = (int) $config->$key;
        $feed = $emoncms_mysqli->query("SELECT engine FROM feeds WHERE id = $feedid");
        if (!$feed || !($f = $feed->fetch_object())) continue;
        $feeds[$key] = array("id" => $feedid, "engine" => (int) $f->engine);
    }
    $systems[(int) $s->id] = (object) $feeds;
}

echo json_encode(array("datadir" => PHPFINA_DIR, "systems" => (object) $systems), JSON_PRETTY_PRINT)."\n";
