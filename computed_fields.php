<?php
$dir = dirname(__FILE__);
chdir("$dir/www");

require "Lib/load_database.php";
require "core.php";

require ("Modules/system/system_model.php");
$system = new System($mysqli);

$system->computed_fields(false);
