<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function installer_controller() {

    global $session, $route, $mysqli;

    // Views (HTML)
    if ($route->format == "html") {
        if ($route->action == "") {
            return view("Modules/installer/views/installer_list.php", array(
                'userid' => $session['userid'],
                'admin' => $session['admin']
            ));
        }

        if ($route->action == "unmatched" && $session['admin']) {
            return view("Modules/installer/views/installer_unmatched.php", array());
        }
    }

    // API (JSON)
    if ($route->format == "json") {

        require "Modules/installer/installer_model.php";
        $installer_model = new Installer($mysqli);

        if ($route->action == "list") {
            return $installer_model->get_list();
        }

        // Add a new installer
        if ($route->action == "add" && $session['admin']) {

            $name = post('name', true);
            $url = post('url', true);
            $logo = post('logo', true);
            $systems = post('systems', true);
            
            return $installer_model->add($name, $url, $logo, $systems);
        }

        // Edit an existing installer
        if ($route->action == "edit" && $session['admin']) {

            $id = (int) post('id', true);
            $name = post('name', true);
            $url = post('url', true);
            $logo = post('logo', true);
            $systems = post('systems', true);
            
            return $installer_model->edit($id, $name, $url, $logo, $systems);
        }

        // Temporary for testing, populate the database with installers from system_meta
        if ($route->action == "unmatched" && $session['admin']) {
            return $installer_model->unmatched();
        }
    }

    return false;
}