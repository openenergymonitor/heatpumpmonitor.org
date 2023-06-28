<?php

chdir("/var/www/heatpumpmonitororg");
require "www/Lib/load_database.php";

require("Modules/user/user_model.php");
$user = new User($mysqli);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

$data = $system->list();
foreach ($data as $row) {
    $userid = (int) $row->userid;
    if ($user_data = $user->get($userid)) {
        // $apikey_read = $user_data->apikey_read;
        // print "$apikey_read\n";
        $stats = scrapeEnergyValues($row->url);
        if ($stats !== false) {
            $system->save_stats($userid, $stats);
            print json_encode($stats) . "\n";        
        }
    }
}

function scrapeEnergyValues($url)
{
    # decode the url to separate out any args
    $url_parts = parse_url($url);
    $server = $url_parts['scheme'] . '://' . $url_parts['host'];

    # check if url was to /app/view instead of username
    if (preg_match('/^(.*)\/app\/view$/', $url_parts['path'], $matches)) {
        $getstats = "$server$matches[1]/app/getstats";
    } else {
        $getstats = $server . $url_parts['path'] . "/app/getstats";
    }

    # if url has query string, pull out the readkey
    if (isset($url_parts['query'])) {
        parse_str($url_parts['query'], $url_args);
        if (isset($url_args['readkey'])) {
            // $readkey = $url_args['readkey'];
            $getstats .= '?' . $url_parts['query'];
        }
    }

    echo "- fetch stats\n";
    if (!$stats = json_decode(file_get_contents($getstats))) {
        echo "  - failed to fetch stats\n";
        return false;
    } else {
        return $stats;
    }
}