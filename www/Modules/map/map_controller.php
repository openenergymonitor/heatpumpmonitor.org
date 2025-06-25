<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

// Histogram controller
function map_controller() {

    global $route, $session, $settings;

    // HTML view
    if ($route->action == "") {
        $route->format = "html";
        return view("Modules/map/map_view.php", array("userid"=>$session['userid']));
    }

    // API call for location search
    if ($route->action == "search") {
        $route->format = "json";
        // map/search?location=Basingstoke, Hampshire, UK
        $location = isset($_GET['location']) ? $_GET['location'] : '';

        $location = urlencode($location);
        $url = "https://api.opencagedata.com/geocode/v1/json?q=$location&key=".$settings['opencagedata_api_key'];
        $json = file_get_contents($url);
        $data = json_decode($json);
        if (isset($data->results[0]->geometry->lat) && isset($data->results[0]->geometry->lng)) {
            return array(
                "success" => true,
                "lat" => $data->results[0]->geometry->lat,
                "lng" => $data->results[0]->geometry->lng
            );
        } else {
            return array(
                "success" => false,
                "message" => "Location not found"
            );
        }
    }

    return false;
}
