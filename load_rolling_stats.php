<?php
$dir = dirname(__FILE__);
chdir("$dir/www");

require "Lib/load_database.php";

require("Modules/user/user_model.php");
$user = new User($mysqli);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

require ("Modules/system/system_stats_model.php");
$system_stats = new SystemStats($mysqli,$system);

print "Updating rolling stats: ".date("Y-m-d H:i:s")."\n";
print "- directory: $dir\n";

$start = microtime(true);
$data = $system->list_admin();
foreach ($data as $row) {
   // if ($row->id!=1) continue;
    $userid = (int) $row->userid;
    if ($user_data = $user->get($userid)) {

        $result = $system_stats->load_from_url($row->url);
        if (isset($result['success']) && $result['success']) {
            $system_stats->save_last30($row->id, $result['stats']);
            $system_stats->save_last365($row->id, $result['stats']);
            // print json_encode($result['stats']) . "\n";        
        } else {
            print "ERROR: ".$result['message']."\n";
        }
    }
}
$end = microtime(true);
$duration = $end - $start;
print "- systems: ".count($data)."\n";
print "- duration: ".number_format($duration,1,'.',',')."s\n";