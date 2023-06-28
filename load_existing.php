<?php

$data_obj = file_get_contents("https://heatpumpmonitor.org/data.json");
$data = json_decode($data_obj);

chdir("/var/www/heatpumpmonitororg");
require "www/Lib/load_database.php";

require("Modules/user/user_model.php");
$user = new User($mysqli);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

foreach ($data as $row) {

    // if url contains string emoncms.org
    // fetch userid from feed list
    if (strpos($row->url, "emoncms.org") !== false) {
        // if url contains read key
        if (strpos($row->url,"readkey=") !== false) {
            $spit = explode("readkey=", $row->url);
            $readkey = trim($spit[1]);
            $feeds = json_decode(file_get_contents("https://emoncms.org/feed/list.json?apikey=".$readkey));
            $userid = $feeds[0]->userid;
        } else {

            if (strpos($row->url,"/app/view?name=") !== false) {
                $spit = explode("/app/view?name=", $row->url);
                $row->url = trim($spit[0]);
            }

            $feeds = json_decode(file_get_contents($row->url."/feed/list.json"));
            if ($feeds!=null && count($feeds)>0) {
                $userid = $feeds[0]->userid;
                $readkey = "";
            } else {
                echo "Error: no feeds found $row->url\n";
                die;
            }
        }

        // Check if user exists with userid in heatpumpmonitor.org database using mysqli
        $result = $mysqli->query("SELECT * FROM users WHERE id='$userid'");
        if ($result->num_rows==0) {
            $username = "";
            $email = "";
            $apikey_read = $readkey;
            $apikey_write = "";

            $stmt = $mysqli->prepare("INSERT INTO users (id, username, email, apikey_read, apikey_write) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $userid, $username, $email, $apikey_read, $apikey_write);
            $stmt->execute();
            $stmt->close();

            print "user $userid created\n";
        }

        $form_data = array();

        foreach ($system->schema as $key=>$schema_row) {
            if ($schema_row['editable']==true) {
                if (isset($row->$key)) {
                    print $key.": ".$row->$key."\n";
                    $form_data[$key] = $row->$key;
                }
            }
        }

        $form_data = json_decode(json_encode($form_data));

        $result = $system->create($userid);
        $systemid = $result['id'];

        $r = $system->save($userid, $systemid, $form_data);
        print json_encode($r)."\n";
        print "system saved\n";

        usleep(10000);
    }
}