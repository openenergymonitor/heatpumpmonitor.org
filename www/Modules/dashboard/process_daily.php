<?php

// Check if script is run from command line
if (php_sapi_name() != 'cli') {
    die("This script can only be run from the command line");
}

require "myheatpump_process.php";
require "/var/www/emoncms/Lib/load_emoncms.php";

require_once "Modules/app/app_model.php";
$appconfig = new AppConfig($mysqli, array());

$userid = 40;

require_once "Modules/app/apps/OpenEnergyMonitor/myheatpump/myheatpump_model.php";
$myheatpump = new MyHeatPump($mysqli,$redis,$feed,$appconfig);

$apps = $appconfig->get_list($userid);
foreach ($apps as $app) {
    if ($app->app == "myheatpump") {
        $result = $myheatpump->process_daily($app->id,600);
        print json_encode($result, JSON_PRETTY_PRINT);
        die;
    }
}
