<?php

chdir("/var/www/heatpumpmonitororg");
require "www/Lib/load_database.php";
require "www/Lib/dbschemasetup.php";

$schema = array();

require "www/Modules/user/user_schema.php";
require "www/Modules/system/system_schema.php";

print json_encode(db_schema_setup($mysqli, $schema, true))."\n";