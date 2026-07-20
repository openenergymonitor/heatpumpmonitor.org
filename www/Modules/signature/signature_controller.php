<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function signature_controller() {

    global $session, $route, $user, $mysqli, $settings, $system, $system_stats;

    if ($route->action == "") {
        return view("Modules/signature/views/signature_view.php", array());
    }

    require "Modules/signature/signature_model.php";
    $signature_model = new Signature($mysqli);

    // List all episodes for a system
    if ($route->action == "list") {
        $route->format = "json";
        if (!isset($_GET['id'])) {
            return array("error" => "No system ID provided");
        }
        $system_id = (int) $_GET['id'];

        // Only return episodes the user is allowed to see. has_read_access grants
        // access to public/published systems (so anonymous viewing works), plus
        // the system's owner and admins.
        $userid = isset($session['userid']) ? (int) $session['userid'] : 0;
        if (!$system->has_read_access($userid, $system_id)) {
            return array("error" => "Access denied");
        }

        return $signature_model->get_episodes($system_id);
    }

    // List all systems with a count of signatures for each
    if ($route->action == "systems") {
        $route->format = "json";
        return $signature_model->get_system_counts();
    }
}
