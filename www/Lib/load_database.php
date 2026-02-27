<?php

defined('EMONCMS_EXEC') or die('Restricted access');
error_reporting(E_ALL);
ini_set('display_errors', 'on');

// Very basic loading of settings.php
// no thourough checking that setting keys as available here
// assumes settings file follows the structure given in the example.

// settings.php should not be present when using a docker environment

if (file_exists("settings.php")) {
    require "settings.php";
    if (!isset($settings["sql"])) {
        echo "No sql settings found in settings.php<br />";
        die();
    }
    
    // Just providing a notice to the user here rather than automatic switchover to using env variables.
    if (isset($_ENV["DOCKER_ENV"])) {
        die("Please remove settings.php to use docker environment.");
    }
    
} else if (file_exists("env.settings.php")) {
    require "env.settings.php";
}

if (!isset($settings['emoncms_host'])) {
    $settings['emoncms_host'] = "https://emoncms.org";
}

$mysqli = @new mysqli(
    $settings["sql"]["server"],
    $settings["sql"]["username"],
    $settings["sql"]["password"],
    $settings["sql"]["database"],
    $settings["sql"]["port"]
);
    

if ($mysqli->connect_error) {
    die("Can't connect to database, please check mysql credentials in settings.php");
}
// Set charset to utf8
$mysqli->set_charset("utf8");

// ----------------------------------------------------------------------------------
// Emoncms mysql database connection
// ----------------------------------------------------------------------------------
$emoncms_mysqli = new mysqli(
    $settings['emoncms_credentials']['server'],
    $settings['emoncms_credentials']['username'],
    $settings['emoncms_credentials']['password'],
    $settings['emoncms_credentials']['database'],
    $settings['emoncms_credentials']['port']
);

if ($emoncms_mysqli->connect_error) {
    die("Can't connect to emoncms database, please check mysql credentials in settings.php");
}

// Set charset to utf8
$emoncms_mysqli->set_charset("utf8");

// ----------------------------------------------------------------------------------
// Check if redis class exists
// ----------------------------------------------------------------------------------
if (class_exists('Redis')) {
    $redis = new Redis();
    $connected = $redis->connect('localhost');    
} else {
    $redis = false;
    $connected = false;
}


