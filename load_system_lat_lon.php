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

    // Skip systems that already have coordinates or have no location set
    // if (!empty($system->latitude) && !empty($system->longitude)) continue;
    if (empty($location)) continue;

    $apikey = $settings['opencagedata_api_key'];
    $url = "https://api.opencagedata.com/geocode/v1/json?q=" . urlencode($location) . "&key=$apikey";
    $json = @file_get_contents($url);
    if ($json === false) {
        echo "Error: API request failed for system $id (location: $location)\n";
        continue;
    }

    $data = json_decode($json);
    if (isset($data->results[0]->geometry->lat) && isset($data->results[0]->geometry->lng)) {
        // 1 dp ~11 km
        // 2 dp ~1.1 km
        // 3 dp ~110 m **
        // 4 dp ~11 m
        // 5 dp ~1.1 m
        // Add ~500m randomisation (~0.0045 degrees) to anonymise exact location
        $lat_offset = (mt_rand(-45, 45)) / 10000.0;
        $lng_offset = (mt_rand(-45, 45)) / 10000.0;
        $latitude = round($data->results[0]->geometry->lat + $lat_offset, 4);
        $longitude = round($data->results[0]->geometry->lng + $lng_offset, 4);

        echo "Updating system $id ($location) with latitude $latitude and longitude $longitude\n";
        $stmt = $mysqli->prepare("UPDATE system_meta SET latitude = ?, longitude = ? WHERE id = ?");
        $stmt->bind_param("ddi", $latitude, $longitude, $id);
        if (!$stmt->execute()) {
            echo "Error updating system $id: " . $stmt->error . "\n";
        }
    } else {
        echo "No result found for system $id (location: $location)\n";
    }

    sleep(1); // respect OpenCage rate limit
}