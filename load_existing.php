<?php

$data_obj = file_get_contents("https://heatpumpmonitor.org/data.json");
$data = json_decode($data_obj);

chdir("/var/www/heatpumpmonitororg");
require "www/Lib/load_database.php";

// Clear all existing data
$mysqli->query("TRUNCATE TABLE users");
$mysqli->query("TRUNCATE TABLE emoncmsorg_link");
$mysqli->query("TRUNCATE TABLE form");

require("Modules/user/user_model.php");
$user = new User($mysqli);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

foreach ($data as $row) {
    print $row->url."\n";
    $url = parse_url($row->url);
    $params = array();
    if (isset($url['query'])) {
        parse_str($url['query'], $params);
    }

    $readkey = "";
    $username = "";
    $email = "";

    // if url contains read key

    if (isset($params['readkey'])) {
        $readkey = $params['readkey'];
    }

    // fetch userid from emoncms.org if host is emoncms.org
    $emoncmsorg_userid = false;
    if ($url['host']=="emoncms.org") {
        if (isset($params['readkey'])) {
            $feeds = json_decode(file_get_contents($url['scheme']."://".$url['host']."/feed/list.json?apikey=".$readkey));
        } else {
            $split = explode("/app/view",$url['path']);
            $username = str_replace("/","", $split[0]);
            $feeds = json_decode(file_get_contents($url['scheme']."://".$url['host']."/".$username."/feed/list.json"));
        }
        if ($feeds!=null && count($feeds)>0) {
            $emoncmsorg_userid = $feeds[0]->userid;
        } else {
            die("Error: no feeds found\n");
        }
    }
    
    $stmt = $mysqli->prepare("INSERT INTO users (username, email, admin) VALUES (?, ?, 0)");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $userid = $stmt->insert_id;
    $stmt->close();

    if ($url['host']=="emoncms.org") {
        $stmt = $mysqli->prepare("INSERT INTO emoncmsorg_link (userid, emoncmsorg_userid, emoncmsorg_apikey_read) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $userid, $emoncmsorg_userid, $readkey);
        $stmt->execute();
        $stmt->close();
    }

    print "user $userid created\n";

    $form_data = array();

    foreach ($system->schema as $key=>$schema_row) {
        if ($schema_row['editable']==true) {
            if (isset($row->$key)) {
                print "-- ".$key.": ".$row->$key."\n";
                $form_data[$key] = $row->$key;
            }
        }
    }

    $form_data = json_decode(json_encode($form_data));

    $result = $system->create($userid);
    $systemid = $result['id'];

    print "system $systemid created\n";

    $r = $system->save($userid, $systemid, $form_data);
    print json_encode($r)."\n";
    print "system saved\n";

    usleep(10000);
}