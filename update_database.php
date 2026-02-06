<?php
$dir = dirname(__FILE__);
chdir("$dir/www");

define('EMONCMS_EXEC', 1);
require "Lib/load_database.php";
require "Lib/dbschemasetup.php";

$schema = array();

// just user sessions
require "Modules/user/user_schema.php";
require "Modules/system/system_schema.php";
require "Modules/heatpump/heatpump_schema.php";
require "Modules/installer/installer_schema.php";
require "Modules/manufacturer/manufacturer_schema.php";

// Remove array item $schema['system_stats_daily']
unset($schema['system_stats_daily']);


echo "Proposed database schema update:\n";

print json_encode(db_schema_setup($mysqli, $schema, false))."\n";

echo "Apply database schema update? (y/n) ";
$handle = fopen ("php://stdin","r");
$line = fgets($handle);
if(trim($line) != 'y'){
    echo "ABORTING!\n";
    exit;
}

print json_encode(db_schema_setup($mysqli, $schema, true))."\n";
