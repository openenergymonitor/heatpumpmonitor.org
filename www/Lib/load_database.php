<?php

define('EMONCMS_EXEC', 1);

error_reporting(E_ALL);
ini_set('display_errors', 'on');

require "settings.php";

if (!isset($settings["sql"])) {
    echo "No sql settings found in settings.php<br />";
    die();
}

$mysqli = @new mysqli(
    $settings["sql"]["server"],
    $settings["sql"]["username"],
    $settings["sql"]["password"],
    $settings["sql"]["database"],
    $settings["sql"]["port"]
);

if ($mysqli->connect_error) {
    echo "Can't connect to database, please verify credentials/configuration in settings.ini<br />";
    if ($settings["display_errors"]) {
        echo "Error message: <b>" . $mysqli->connect_error . "</b>";
    }
    die();
}
// Set charset to utf8
$mysqli->set_charset("utf8");