<?php

require "Lib/load_database.php";

require "core.php";
require "route.php";
require("Modules/user/user_model.php");
$user = new User($mysqli);
require ("Modules/system/system_model.php");
$system = new System($mysqli);

$path = get_application_path(false);
$route = new Route(get('q'), server('DOCUMENT_ROOT'), server('REQUEST_METHOD'));

$session = $user->emon_session_start();

switch ($route->controller) {

    case "":
        $route->format = "html";
        $output = view("views/main.php", array("userid"=>$session['userid']));
        break;

    case "stats":
        $route->format = "html";
        $output = view("views/stats.html", array("userid"=>$session['userid']));
        break;
        
    case "costs":
        $route->format = "html";
        $output = view("views/costs.html", array("userid"=>$session['userid']));
        break;
        
    case "graph":
        $route->format = "html";
        $output = view("views/graph.html", array("userid"=>$session['userid']));
        break;

    case "compare":
        $route->format = "html";
        $output = view("views/compare.html", array("userid"=>$session['userid']));
        break;

    case "user":
        require "Modules/user/user_controller.php";
        $output = user_controller();
        break;

    case "system":
        require "Modules/system/system_controller.php";
        $output = system_controller();
        break;

    case "data": 
        $route->format = "json";
        $output = $system->list_public($session['userid']);
        break;
        
    case "api":
        $route->format = "json";
        
        $data_obj = $system->list_public($session['userid']);

        if (isset($_GET['system'])) {
            // Find ID
            $system = false;
            $id = (int) $_GET['system'];
            foreach ($data_obj as $key=>$row) {
                if ($row->id==$id) {
                    $system = $row;
                    break;
                }
            }
            if ($system) {
                if ($route->action=="data") {
                    
                    $url_parts = parse_url($system->url);
                    $server = $url_parts['scheme'] . '://' . $url_parts['host'];
                    # check if url was to /app/view instead of username
                    if (preg_match('/^(.*)\/app\/view$/', $url_parts['path'], $matches)) {
                      $getconfig = "$server$matches[1]/app/getconfig";
                    } else {
                      $getconfig = $server . $url_parts['path'] . "/app/getconfig";
                    }        
                    
                    $apikeystr = "";     
                    # if url has query string, pull out the readkey
                    if (isset($url_parts['query'])) {
                      parse_str($url_parts['query'], $url_args);
                      if (isset($url_args['readkey'])) {
                        $readkey = $url_args['readkey'];
                        $getconfig .= '?' . $url_parts['query'];
                        $apikeystr = "&apikey=".$readkey;
                      }
                    }
                    
                    $config = json_decode(file_get_contents($getconfig));
                    
                    $elec_feedid = (int) $config->config->heatpump_elec;
                    $heat_feedid = (int) $config->config->heatpump_heat;
                    $flowT_feedid = (int) $config->config->heatpump_flowT;
                    $returnT_feedid = (int) $config->config->heatpump_returnT;
                    $outsideT_feedid = (int) $config->config->heatpump_outsideT;

                    $output = $config->config;

                    $start = $_GET['start'];
                    $end = $_GET['end'];
                    $interval = $_GET['interval']; 
                    
                    if ($route->subaction=="all") {
                    
                        $result = json_decode(file_get_contents("$server/feed/data.json?ids=$elec_feedid,$heat_feedid,$outsideT_feedid,$flowT_feedid,$returnT_feedid&start=$start&end=$end&interval=$interval&average=1&skipmissing=0&timeformat=notime".$apikeystr));
                        
                        $output = array(
                          "elec"=>$result[0]->data,
                          "heat"=>$result[1]->data,
                          "outsideT"=>$result[2]->data,
                          "flowT"=>$result[3]->data,
                          "returnT"=>$result[4]->data
                        );   
                    }
                } else {
                    $output = $system;
                }
            } else {
                $output = array("success"=>false, "message"=>"invalid system id");
            }
        } else {
            $output = $data_obj;
        }
        
        break;
}

switch ($route->format) {

    case "html":
        echo view("theme/theme.php", array("content"=>$output, "route"=>$route, "session"=>$session));
        break;
        
    case "json":
        header('Content-Type: application/json');
        echo json_encode($output);   
        break; 
}
