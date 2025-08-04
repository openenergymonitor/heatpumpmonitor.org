<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function manufacturer_controller() {

    global $session, $route, $user, $mysqli, $settings, $system, $system_stats;

    if ($route->action == "") {
        return view("Modules/manufacturer/views/manufacturer_list.php", array());
    }

    require "Modules/manufacturer/manufacturer_model.php";
    $manufacturer_model = new Manufacturer($mysqli);

    // List all manufacturers
    if ($route->action == "list") {
        $route->format = "json";
        return $manufacturer_model->get_all();
    }

    // Edit name and website of a manufacturer
    if ($route->action == "update" && $session['admin']) {
        $route->format = "json";
        if (isset($_POST['id']) && isset($_POST['name'])) {
            $id = (int)$_POST['id'];
            $name = $_POST['name'];
            $website = isset($_POST['website']) ? $_POST['website'] : "";
            return $manufacturer_model->edit($id, $name, $website);
        } else {
            return array("error" => "Missing parameters for editing manufacturer");
        }
    }

    // Delete a manufacturer
    if ($route->action == "delete" && $session['admin']) {
        $route->format = "json";
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            return $manufacturer_model->delete($id);
        } else {
            return array("error" => "No manufacturer ID provided");
        }
    }
}