<?php

// $data_obj = file_get_contents("https://heatpumpmonitor.org/data.json");
$data_obj = file_get_contents("data.json");

$data = json_decode($data_obj);

chdir("/var/www/heatpumpmonitororg");
require "www/Lib/load_database.php";
require "www/core.php";

// Clear all existing data
$result = $mysqli->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $mysqli->query("DROP TABLE IF EXISTS `$row[0]`");
}

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

    $result = create_user($mysqli, $row->email, $row->name, $row->url);
    if (!$result['success']) {
        print $result['message']."\n";
        continue;
    } else {
        $userid = $result['userid'];
    }
    

    // ---------------------------------------
    // Translate existing format to new format
    // ---------------------------------------
    
    /*
        "submitted": "11\/5\/2022 22:10:10",    => "published" convert to timestamp
        "location": "Selby",                    => "location"
        "hp_model": "Mitsubishi Ecodan",        => "hp_model"
        "hp_type": "Air Source",                => "hp_type"
        "hp_output": "11.2",                    => "hp_output"
        "emitters": "New radiators",
        "heat_demand": "22000",                 => "heat_demand"
        "notes": "",
        "property": "Detached",                 => "property"
        "floor_area": "134",                    => "floor_area" 
        "heat_loss": "9.4",                     => "heat_loss"
        "url": "https:\/\/emoncms.org/ashp",    => "url"
        "share": "Yes",                         => "share" convert to boolean
        "age": "1940 to 1982",                  => "age"
        "insulation": "Cavity wall, plus some loft insulation",
        "flow_temp": "45C",                     
        "buffer": "No",
        "freeze": "Glycol\/water mixture",
        "zone": "1",
        "controls": "Custom software",
        "refrigerant": "R32",                   => "refrigerant"
        "dhw": "Daily cycle at noon to 45C",
        "legionella": "None",
        "approved\r": "Yes\r"                   => "approved" convert to boolean
    */

    $form_data = array();
    $form_data['last_updated'] = strtotime($row->submitted);
    $form_data['location'] = $row->location;
    $form_data['hp_model'] = $row->hp_model;
    $form_data['hp_output'] = $row->hp_output;
    $form_data['heat_demand'] = $row->heat_demand;
    $form_data['floor_area'] = $row->floor_area;
    $form_data['heat_loss'] = $row->heat_loss;
    $form_data['url'] = $row->url;

    // HP Type
    if (!in_array($row->hp_type, $system->schema_meta['hp_type']['options']))
        die("Error: hp_type: ".$row->hp_type." not in options\n");
    $form_data['hp_type'] = $row->hp_type;

    // Property
    if (!in_array($row->property, $system->schema_meta['property']['options']))
        die("Error: property: ".$row->property." not in options\n");
    $form_data['property'] = $row->property;

    // Age
    if (!in_array($row->age, $system->schema_meta['age']['options']))
        die("Error: age: ".$row->age." not in options\n");
    $form_data['age'] = $row->age;

    // Refrigerant
    $row->refrigerant = strtoupper($row->refrigerant);
    if ($row->refrigerant!='' && !in_array($row->refrigerant, $system->schema_meta['refrigerant']['options']))
        die("Error: refrigerant: ".$row->refrigerant." not in options\n");
    $form_data['refrigerant'] = $row->refrigerant;

    // Freeze
    if ($row->freeze!='' && !in_array($row->freeze, $system->schema_meta['freeze']['options']))
        die("Error: freeze: ".$row->freeze." not in options\n");
    $form_data['freeze'] = $row->freeze;

    // Insulation
    if (in_array($row->insulation, $system->schema_meta['insulation']['options'])) {
        $form_data['insulation'] = $row->insulation;
        $row->insulation = '';
    }

    // Buffer
    if ($row->buffer=='Yes') $form_data['hydraulic_separation'] = 'Buffer';

    // Zone
    if ($row->zone=='1') {
        $form_data['zone_number'] = 1;
        $row->zone = '';
    }
    if ($row->zone=='2') {
        $form_data['zone_number'] = 2;
        $row->zone = '';
    }

    $row->emitters = str_replace(" ","",$row->emitters);
    $row->emitters = explode(",",$row->emitters);

    // new radiators
    if (in_array('Newradiators', $row->emitters)) {
        $form_data['new_radiators'] = 1;
        $row->emitters = array_diff($row->emitters, array('Newradiators'));
    }
    // old radiators
    if (in_array('Existingradiators', $row->emitters)) {
        $form_data['old_radiators'] = 1;
        $row->emitters = array_diff($row->emitters, array('Existingradiators'));
    }
    // UFH
    if (in_array('Underfloorheating', $row->emitters)) {
        $form_data['ufh'] = 1;
        $row->emitters = array_diff($row->emitters, array('Underfloorheating'));
    }
    $row->emitters = implode(",",$row->emitters);

    // If flow temp is two digits or two digits and C
    if (preg_match('/^[0-9]{2}C?$/', $row->flow_temp)) {
        $form_data['flow_temp'] = $row->flow_temp;
        $row->flow_temp = '';
    }

    $notes = "";
    if (trim($row->emitters)!="") $notes .= "Emitters: ".$row->emitters." | ";
    if (trim($row->insulation)!="") $notes .= "Insulation: ".$row->insulation." | ";
    if (trim($row->flow_temp)!="") $notes .= "Flow temp: ".$row->flow_temp." | ";
    if (trim($row->zone)!="") $notes .= "Zone: ".$row->zone." | ";
    if (trim($row->controls)!="") $notes .= "Controls: ".$row->controls." | ";
    if (trim($row->dhw)!="") $notes .= "DHW: ".$row->dhw." | ";
    if (trim($row->legionella)!="") $notes .= "Legionella: ".$row->legionella." | ";
    if (trim($row->notes)!="") $notes .= "Notes: ".$row->notes." |";

    $form_data['notes'] = $notes;

    if (trim($row->share)=="Yes") $form_data['share'] = 1; else $form_data['share'] = 0;

    $form_data = json_decode(json_encode($form_data));

    $r = $system->save($userid, false, $form_data, false);
    if (!$r['success']) {
        print $r['message']."\n";
    } else {
        print "system ".$r['systemid']." created\n";

        if (trim($row->approved)=="Yes") {
            $mysqli->query("UPDATE system_meta SET published=1 WHERE id='".$r['systemid']."'");
        }

    }
}


function create_user($mysqli, $email, $name, $url_in) {

    $url = parse_url($url_in);
    $params = array();
    if (isset($url['query'])) {
        parse_str($url['query'], $params);
    }
    $readkey = "";
    if (isset($params['readkey'])) {
        $readkey = $params['readkey'];
    }

    if (!isset($url['host'])) {
        return array("success"=>false, "message"=>"Missing host in URL $name $url_in");
    }

    $add_system_to_existing_user = false;

    // validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return array("success"=>false, "message"=>"Invalid email $email");
    }

    // lower case and remove spaces
    $username = str_replace(" ","", strtolower($name));

    // check if username already exists
    $result_u = $mysqli->query("SELECT id,email FROM users WHERE username='$username'");
    if ($result_u->num_rows>0) {
        $row_u = $result_u->fetch_object();
        if ($row_u->email!=$email) {
            $username .= "2";
            // return array("success"=>false, "message"=>"Username $username already exists with different email");
        }
        $add_system_to_existing_user = $row_u->id;
    }

    // if url contains read key


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
            return array("success"=>false, "message"=>"No feeds found");
        }
    }

    if (!$add_system_to_existing_user) {
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
    } else {
        $userid = $add_system_to_existing_user;
    }
    return array("success"=>true, "userid"=>$userid);
}