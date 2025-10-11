<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function dashboard_controller() {
    global $route, $session, $system, $mysqli;
    // HTML view
    if ($route->action == "") {
        $systemid = (int) $_GET['id'];
        $route->format = "html";
        $system_data = $system->get($session['userid'],$systemid);
        if (is_array($system_data) && isset($system_data['success']) && $system_data['success']==false) {
            // 
        } else {
            return view("Modules/dashboard/myheatpump.php", array(
                "id"=>$systemid,
                "system_data"=>$system_data
            ));
        }
    }

    // API actions
    if ($route->action == "getstats") {
        $route->format = "json";

        $systemid = (int) get('id', true);
        $start = (int) get('start', true);
        $end = (int) get('end', true);

        // Fetch url from system_meta 
        $result = $mysqli->query("SELECT url FROM system_meta WHERE id=$systemid AND published=1 AND share=1");
        if (!$row = $result->fetch_object()) {
            return array("error" => "System not found");
        }
        $url = $row->url;

        // Replace app/view with app/getstats
        $url = preg_replace('/app\/view/', 'app/getstats', $url);
        // Add start and end parameters
        $url .= "&start=$start&end=$end";

        $stats = @file_get_contents($url);
        if ($stats === false) {
            return array("error" => "Failed to load stats from URL");
        }
        return json_decode($stats);
    }

    return false;
}
