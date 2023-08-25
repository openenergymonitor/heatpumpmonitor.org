<?php

chdir("/var/www/heatpumpmonitororg");
require "www/Lib/load_database.php";
require "www/core.php";

require ("Modules/system/system_model.php");
$system = new System($mysqli);

$system->computed_fields(false);