<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function dashboard_controller() {
    global $route, $session, $system;
    // HTML view
    if ($route->action == "") {
        $systemid = $_GET['id'];
        $route->format = "html";
        $system_data = $system->get($session['userid'],$systemid);

        return view("Modules/dashboard/myheatpump.php", array(
            "id"=>$systemid,
            "system_data"=>$system_data
        ));
    }
    return false;
}
