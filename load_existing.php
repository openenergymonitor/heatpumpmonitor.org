<?php

// $data_obj = file_get_contents("https://heatpumpmonitor.org/data.json");
$data_obj = file_get_contents("data.json");

$data = json_decode($data_obj);

chdir("/var/www/heatpumpmonitororg");
require "www/Lib/load_database.php";
require "www/core.php";

// Clear all existing data
$mysqli->query("DROP TABLE users");
$mysqli->query("DROP TABLE emoncmsorg_link");
$mysqli->query("DROP TABLE form");

// Rebuid database
require "www/Lib/dbschemasetup.php";
$schema = array();
require "www/Modules/user/user_schema.php";
require "www/Modules/system/system_schema.php";
db_schema_setup($mysqli, $schema, true);

// Load user and system models
require("Modules/user/user_model.php");
$user = new User($mysqli);
require ("Modules/system/system_model.php");
$system = new System($mysqli);

// For each user in data.json
foreach ($data as $row) {
    print $row->url."\n";
    $url = parse_url($row->url);
    $params = array();
    if (isset($url['query'])) {
        parse_str($url['query'], $params);
    }

    $readkey = "";
    $username = "";
    $name = "";
    $email = "";

    // If we are loading the full dataset including protected data

    if (isset($row->name)) {
        $name = $row->name;
        // lower case and remove spaces
        $username = str_replace(" ","", strtolower($row->name));
    }

    if (isset($row->email)) {
        $email = $row->email;
        // validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            die("Error: invalid email\n");
        }
    }

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

    // Generate new random password
    $newpass = hash('sha256',generate_secure_key(32));
    // Hash and salt
    $hash = hash('sha256', $newpass);
    $salt = generate_secure_key(16);
    $hash = hash('sha256', $salt . $hash);
    
    $stmt = $mysqli->prepare("INSERT INTO users (username, name, email, salt, hash, email_verified, admin) VALUES (?, ?, ?, ?, ?, 1, 0)");
    $stmt->bind_param("sssss", $username, $name, $email, $salt, $hash);
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

    // ---------------------------------------
    // Translate existing format to new format
    // ---------------------------------------

    // Buffer is now a boolean
    if ($row->buffer=="Yes") $row->buffer = 1;
    else $row->buffer = 0;

    // Emitters are now 3 booleans (new_radiators, old_radiators, UFH)
    // check if "New radiators" is in string emitters
    // set new_radiators to 1 if it is
    if (strpos($row->emitters, "New radiators")!==false) $row->new_radiators = 1;
    else $row->new_radiators = 0;
    // Exitings radiators
    if (strpos($row->emitters, "Existing radiators")!==false) $row->old_radiators = 1;
    else $row->old_radiators = 0;
    // Underfloor heating
    if (strpos($row->emitters, "Underfloor heating")!==false) $row->UFH = 1;
    else $row->UFH = 0;

    // Anti freeze protection
    // - Central heat pump water circulation
    // - Anti-freeze valves
    // - Glycol/water mixture

    // ---------------------------------------

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
    // print json_encode($r)."\n";
    print "system saved\n";

    usleep(10000);
    // die;
}