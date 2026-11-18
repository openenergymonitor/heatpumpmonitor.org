<?php
// Enable the Guild of Heating Engineers badge on all systems belonging to a user and their sub accounts
// Usage: php set_guild_badge.php userid [--save]  (dry run unless --save given)
define('EMONCMS_EXEC', 1);

if (!isset($argv[1]) || (int) $argv[1] <= 0) {
    print "Usage: php set_guild_badge.php userid [--save]\n";
    exit(1);
}
$userid = (int) $argv[1];
$save = in_array('--save', $argv);

$dir = dirname(__FILE__);
chdir("$dir/../../www");

require "Lib/load_database.php";

// Admin user plus linked sub accounts
$userids = array($userid);
$result = $emoncms_mysqli->query("SELECT linkeduser FROM accounts WHERE adminuser = $userid");
while ($row = $result->fetch_object()) {
    $userids[] = (int) $row->linkeduser;
}
$userids_list = implode(",", $userids);
print "Users: $userids_list\n";

$result = $mysqli->query("SELECT id, userid, location, guild FROM system_meta WHERE userid IN ($userids_list)");
while ($row = $result->fetch_object()) {
    print $row->id." user=".$row->userid." ".$row->location." guild=".(int)$row->guild."\n";
}

if (!$save) {
    print "Dry run, use --save to apply\n";
    exit(0);
}

$mysqli->query("UPDATE system_meta SET guild = 1 WHERE userid IN ($userids_list)");
print "Updated ".$mysqli->affected_rows." systems\n";
