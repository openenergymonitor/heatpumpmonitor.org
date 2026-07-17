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
        if (isset($_GET['id'])) {
            $system_id = (int) $_GET['id'];
            return $signature_model->get_episodes($system_id);
        } else {
            return array("error" => "No system ID provided");
        }
    }
}
