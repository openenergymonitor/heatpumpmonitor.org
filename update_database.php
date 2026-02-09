<?php
$dir = dirname(__FILE__);
chdir("$dir/www");

define('EMONCMS_EXEC', 1);
require "Lib/load_database.php";
require "Lib/dbschemasetup.php";

$schema = array();

// Automatically scan and load all schema files from Modules
$modules_dir = "Modules";
if ($handle = opendir($modules_dir)) {
    while (false !== ($module = readdir($handle))) {
        if ($module != "." && $module != ".." && is_dir($modules_dir . "/" . $module)) {
            $schema_file = $modules_dir . "/" . $module . "/" . $module . "_schema.php";
            if (file_exists($schema_file)) {
                echo "Loading schema: $schema_file\n";
                require $schema_file;
            }
        }
    }
    closedir($handle);
}

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
