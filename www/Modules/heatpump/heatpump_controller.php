<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function heatpump_controller() {

    global $session, $route, $user, $mysqli, $settings, $system, $system_stats;

    if ($route->action == "") {
        return view("Modules/heatpump/views/heatpump_list.php", array());
    }

    if ($route->action == "view") {

        if (!isset($_GET['id'])) {
            return false;
        }

        $id = (int) $_GET['id'];

        $mode = "view";
        if ($session['admin']) $mode = "admin";

        return view("Modules/heatpump/views/heatpump_view.php", array(
            "id" => $id,
            "mode" => $mode
        ));
    }

    // API
    require "Modules/heatpump/heatpump_model.php";
    $heatpump_model = new Heatpump($mysqli);

    if ($route->action == "list") {
        $route->format = "json";
        return $heatpump_model->get_list();
    }

    if ($route->action == "get") {
        $route->format = "json";
        return $heatpump_model->get(get("id"));
    }

    if ($route->action == "max_cap_test") {
        if ($route->subaction == "load") {
            $route->format = "json";
            $data = json_decode(file_get_contents('php://input'), true);
            $stats = $system_stats->load_from_url($data["url"]);

            $date = new DateTime();
            $date->setTimezone(new DateTimeZone('Europe/London'));
            $date->setTimestamp($stats->start);
            $datestr = $date->format('jS M Y H:i');

            return array(
                "start" => $stats->start,
                "end" => $stats->end,
                "date" => $datestr,
                "elec" => $stats->stats->combined->elec_mean,
                "heat" => $stats->stats->combined->heat_mean,
                "cop" => $stats->stats->combined->cop,
                "flowT" => $stats->stats->combined->flowT_mean,
                "outsideT" => $stats->stats->combined->outsideT_mean,
                "data_length" => $stats->stats->combined->data_length
            );
        }
    }
}