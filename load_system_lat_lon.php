<?php
$dir = dirname(__FILE__);
chdir("$dir/www");

define('EMONCMS_EXEC', 1);
require "Lib/load_database.php";

if (!isset($settings['opencagedata_api_key'])) {
    echo "OpenCageData API key not set in settings.php\n";
    exit(1);
}

require ("Modules/system/system_model.php");
$system = new System($mysqli);

$systems = $system->list_admin();
foreach ($systems as $system) {
    $id = (int) $system->id;
    $location = $system->location;

    // mysql query to check if latitude and longitude are set
    $result = $mysqli->query("SELECT latitude, longitude FROM system_meta WHERE id = $id");
    $row = $result->fetch_object();

    if ($row->latitude == null || $row->longitude == null) {
        $location = urlencode($location);
        $apikey = $settings['opencagedata_api_key'];
        $url = "https://api.opencagedata.com/geocode/v1/json?q=$location&key=$apikey";
        $json = file_get_contents($url);
        $data = json_decode($json);
        if (isset($data->results[0]->geometry->lat) && isset($data->results[0]->geometry->lng)) {
            // 1 dp ~11 km
            // 2 dp ~1.1 km
            // 3 dp ~110 m **
            // 4 dp ~11 m
            // 5 dp ~1.1 m
            $latitude = round($data->results[0]->geometry->lat, 3);
            $longitude = round($data->results[0]->geometry->lng, 3);

            echo "Updating system $id with latitude $latitude and longitude $longitude\n";
            $stmt = $mysqli->prepare("UPDATE system_meta SET latitude = ?, longitude = ? WHERE id = ?");
            $stmt->bind_param("ddi", $latitude, $longitude, $id);
            if (!$stmt->execute()) {
                echo "Error updating system $id: " . $stmt->error . "\n";
            }
        }
    }
}