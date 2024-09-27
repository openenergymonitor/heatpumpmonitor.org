<?php
$dir = dirname(__FILE__);
chdir("$dir/../www");

require "Lib/load_database.php";

require("Modules/user/user_model.php");
$user = new User($mysqli,false);

require ("Modules/system/system_model.php");
$system = new System($mysqli);
$systems = $system->list_admin();

foreach ($systems as $system) {
    if ($system->mid_metering == 0) continue;

    // Check for 'OpenEnergyMonitor' in electric_meter
    if (strpos($system->electric_meter, 'OpenEnergyMonitor') !== false) {
        print "System: " . $system->id." ".$system->electric_meter . "\n";
        // SET MID_METERING = 0
        $mysqli->query("UPDATE system_meta SET mid_metering = 0 WHERE id = $system->id");
    }
}