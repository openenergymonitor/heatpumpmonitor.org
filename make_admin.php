<?php
$dir = dirname(__FILE__);
chdir("$dir/www");

// Just a small script to make a user an admin
// set username here:
$username = "midterrace";

// Load database
define('EMONCMS_EXEC', 1);
require "Lib/load_database.php";

// Load user model
require("Modules/user/user_model.php");
$user = new User($mysqli);

// Get userid from username
$userid = $user->get_id($username);
print "Userid: ".$userid."\n";

// Set user as admin check rows affected
$mysqli->query("UPDATE users SET admin=1 WHERE id='$userid'");
if ($mysqli->affected_rows==0) {
    print "Error: failed to set user as admin\n";
}
