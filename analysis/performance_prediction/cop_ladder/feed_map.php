<?php
// Write the feed ids each public system needs for the COP ladder test, as JSON.
// Run on the server that holds the feed files:
//
//   php feed_map.php > feed_map.json
//
// Same approach as analysis/dhw_detection/scripts/feed_map.php, plus outside temperature.

define('EMONCMS_EXEC', 1);
chdir("/opt/openenergymonitor/heatpumpmonitor/www");
require "Lib/load_database.php";

define('PHPFINA_DIR', "/var/opt/emoncms/phpfina/");
$keys = array("heatpump_elec", "heatpump_heat", "heatpump_flowT", "heatpump_returnT",
              "heatpump_outsideT", "heatpump_dhw");

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
