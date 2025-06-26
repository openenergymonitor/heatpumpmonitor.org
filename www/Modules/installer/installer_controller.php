<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function installer_controller() {

    global $session, $route, $mysqli;

    // Views (HTML)
    if ($route->format == "html") {
        if ($route->action == "list" && $session['admin']) {
            return view("Modules/installer/installer_view.php", array());
        }
    }

    // API (JSON)
    if ($route->format == "json") {
        require "Modules/installer/installer_model.php";
        $installer_model = new Installer($mysqli);

        if ($route->action == "list") {
            return $installer_model->get_list();
        }

        // Temporary for testing, populate the database with installers from system_meta
        if ($route->action == "populate" && $session['admin']) {
            return $installer_model->populate();
        }
    }

    return false;
}