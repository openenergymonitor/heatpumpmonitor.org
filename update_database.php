<?php
$dir = dirname(__FILE__);
chdir("$dir/www");

define('EMONCMS_EXEC', 1);
require "Lib/load_database.php";
require "Lib/dbschemasetup.php";
require "Lib/load_module_schemas.php";

echo "Loading module schemas from Modules/*/*_schema.php\n";
$schema = load_module_schemas();

// Raw per-day MyHeatPump rows live only on the emoncms DB (system_stats_daily; id = app id).
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
