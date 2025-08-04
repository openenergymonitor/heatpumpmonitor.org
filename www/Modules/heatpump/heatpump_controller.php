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

    require "Modules/manufacturer/manufacturer_model.php";
    $manufacturer_model = new Manufacturer($mysqli);

    require "Modules/heatpump/heatpump_model.php";
    $heatpump_model = new Heatpump($mysqli, $manufacturer_model);

    if ($route->action == "list") {
        $route->format = "json";
        return $heatpump_model->get_list();
    }

    if ($route->action == "get") {
        $route->format = "json";
        return $heatpump_model->get(get("id"));
    }

    if ($route->action == "add" && $session['admin']) {
        $route->format = "json";
        if (isset($_POST['manufacturer_id']) && isset($_POST['model']) && isset($_POST['capacity'])) {
            $manufacturer_id = (int)$_POST['manufacturer_id'];
            $model = trim($_POST['model']);
            $capacity = (float)$_POST['capacity'];
            return $heatpump_model->add($manufacturer_id, $model, $capacity);
        } else {
            return array("error" => "Missing parameters for adding heatpump");
        }
    }

    if ($route->action == "update" && $session['admin']) {
        $route->format = "json";
        if (isset($_POST['id']) && isset($_POST['manufacturer_id']) && isset($_POST['model']) && isset($_POST['capacity'])) {
            $id = (int)$_POST['id'];
            $manufacturer_id = (int)$_POST['manufacturer_id'];
            $model = trim($_POST['model']);
            $capacity = (float)$_POST['capacity'];
            return $heatpump_model->update($id, $manufacturer_id, $model, $capacity);
        } else {
            return array("error" => "Missing parameters for editing heatpump");
        }
    }

    if ($route->action == "populate" && $session['admin']) {
        $route->format = "json";
        return $heatpump_model->populate_table();
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