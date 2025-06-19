<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

// Histogram controller
function map_controller() {

    global $route, $session, $system_stats;

    // HTML view
    if ($route->action == "") {
        $route->format = "html";
        return view("Modules/map/map_view.php", array("userid"=>$session['userid']));
    }

    return false;
}
