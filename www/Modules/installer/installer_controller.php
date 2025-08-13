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
    }

    // API (JSON)
    if ($route->format == "json") {

        require "Modules/installer/installer_model.php";
        $installer_model = new Installer($mysqli);

        if ($route->action == "list") {
            $system_count = (int) get('system_count', false, false);
            return $installer_model->get_list($system_count);
        }

        // Add a new installer
        if ($route->action == "add" && $session['admin']) {

            $name = post('name', true);
            $url = post('url', true);
            $color = post('color', true);
            
            return $installer_model->add($name, $url, $color);
        }

        // Edit an existing installer
        if ($route->action == "edit" && $session['admin']) {

            $id = (int) post('id', true);
            $name = post('name', true);
            $url = post('url', true);
            $color = post('color', true);
            
            return $installer_model->edit($id, $name, $url, $color);
        }

        // Temporary for testing, populate the database with installers from system_meta
        if ($route->action == "unmatched" && $session['admin']) {
            return $installer_model->unmatched();
        }

        // Load logo from URL
        if ($route->action == "load_logo" && $session['admin']) {
            $url = post('url', true);

            $image = $installer_model->fetch_installer_logo($url);
            if ($image === false) {
                return array('success' => false, 'message' => 'Failed to load logo from URL');
            }
            file_put_contents("theme/img/installers/".$image['filename'], $image['data']);

            return array('success' => true, 'logo' => $image['filename']);
        }

        // Get dominant color from logo
        if ($route->action == "get_dominant_color" && $session['admin']) {
            $logo = post('logo', true);
            if (empty($logo)) {
                return array('success' => false, 'message' => 'Logo path is required');
            }

            $logo_path = 'theme/img/installers/' . $logo;
            if (!file_exists($logo_path)) {
                return array('success' => false, 'message' => 'Logo file does not exist');
            }

            $color = $installer_model->get_dominant_color($logo_path);
            if ($color === false) {
                return array('success' => false, 'message' => 'Failed to get dominant color');
            }

            return array('success' => true, 'color' => $color);
        }

        // Delete an installer
        if ($route->action == "delete" && $session['admin']) {
            $id = (int) post('id', true);
            return $installer_model->delete($id);
        }
    }

    return false;
}