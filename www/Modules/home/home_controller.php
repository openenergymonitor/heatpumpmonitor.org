<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function home_controller() {

    global $route, $session, $system_stats, $redis;

    require "Modules/home/home_model.php";
    $home = new Home($system_stats, $redis);

    // HTML view
    if ($route->action == "") {
        $route->format = "html";
        return view("Modules/home/home_view.php", array(
            "userid"=>$session['userid'],
            "eligible_systems" => $home->eligible_systems(),
            "cooling_systems" => $home->cooling_systems(),
            "mid_metered_count" => $home->mid_metered_count()
        ));
    }

    // All eligible systems with home-description meta and last-365-day stats
    if ($route->action == "eligible_systems") {
        $route->format = "json";
        return $home->eligible_systems();
    }

    // Count of all MID metered systems, any boundary and any data length
    if ($route->action == "mid_metered_count") {
        $route->format = "json";
        return $home->mid_metered_count();
    }

    // Systems actively cooling over the last 7 days
    if ($route->action == "cooling_systems") {
        $route->format = "json";
        return $home->cooling_systems();
    }

    // Latest heat pump topics from the community forum
    if ($route->action == "forum_topics") {
        $route->format = "json";
        return $home->forum_topics();
    }

    return false;
}
