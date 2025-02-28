<?php
$dir = dirname(__FILE__);
chdir("$dir/www");

require "Lib/load_database.php";
require "Lib/dbschemasetup.php";

$schema = array();

require "Modules/user/user_schema.php";
require "Modules/system/system_schema.php";
require "Modules/heatpump/heatpump_schema.php";
print json_encode(db_schema_setup($mysqli, $schema, true))."\n";
