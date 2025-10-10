<?php
$dir = dirname(__FILE__);
chdir("$dir/www");

define('EMONCMS_EXEC', 1);
require "Lib/load_database.php";
require "core.php";

require ("Modules/system/system_model.php");
$system_class = new System($mysqli);

require ("Modules/system/system_stats_model.php");
$system_stats_class = new SystemStats($mysqli,$system_class);

$data = $system_class->list_admin();
foreach ($data as $row) {
    $id = (int) $row->id;
    $userid = (int) $row->userid;

    $url_parts = parse_url($row->url);

    if ($url_parts['host'] == "emoncms.org") { 
        
        $readkey = false;

        if (isset($url_parts['query'])) {
            parse_str($url_parts['query'], $url_args);
            if (isset($url_args['readkey'])) {
                $readkey = $url_args['readkey'];
            }
        }

        if ($readkey) {
            $feeds = json_decode(file_get_contents("https://emoncms.org/feed/list.json?apikey=$readkey"));
            if (isset($feeds[0]->userid)) {
                $uid = (int) $feeds[0]->userid;
                print "uid: $uid\n";
                $mysqli->query("UPDATE system_meta SET emoncmsorg_userid='$uid' WHERE `id`='$id'");
            }
        } else {
            $parts = explode("/",$url_parts['path']);
            $username = $parts[1];
            $feeds = json_decode(file_get_contents("https://emoncms.org/$username/feed/list.json"));
            if (isset($feeds[0]->userid)) {
                $uid = (int) $feeds[0]->userid;
                print "uid: $uid\n";
                $mysqli->query("UPDATE system_meta SET emoncmsorg_userid='$uid' WHERE `id`='$id'");
            }

        }
    }
}
