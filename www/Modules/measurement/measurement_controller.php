<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function measurement_controller()
{
    global $session, $route, $mysqli, $system, $system_stats;

    require_once "Modules/measurement/measurement_model.php";
    $measurement = new Measurement($mysqli, $system_stats);
    $route->format = "json";

    $userid = isset($session['userid']) ? (int) $session['userid'] : 0;

    // measurement/list.json?mode=public|user|admin
    // Values for the system list columns, keyed by system id
    if ($route->action == "list") {
        $mode = "public";
        if (isset($_GET['mode'])) {
            if ($_GET['mode'] == "user" && $userid) {
                $mode = "user";
            } else if ($_GET['mode'] == "admin" && $userid && $system->is_admin($userid)) {
                $mode = "admin";
            }
        }
        return $measurement->get_list($userid, $mode);
    }

    // measurement/system.json?id=: all measurements for one system, with details
    if ($route->action == "system") {
        $systemid = (int) get("id", true);
        if (!$system->has_read_access($userid, $systemid)) {
            return array("success" => false, "message" => "Invalid access");
        }
        return $measurement->get_system($systemid);
    }

    return false;
}
