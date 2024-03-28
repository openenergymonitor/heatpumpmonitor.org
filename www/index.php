<?php

require "Lib/load_database.php";

require "core.php";
require "route.php";

require("Modules/user/rememberme_model.php");
$rememberme = new RememberMe($mysqli);
require("Modules/user/user_model.php");
$user = new User($mysqli,$rememberme);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

require ("Modules/system/system_stats_model.php");
$system_stats = new SystemStats($mysqli,$system);

$path = get_application_path(false);
$route = new Route(get('q'), server('DOCUMENT_ROOT'), server('REQUEST_METHOD'));

$session = $user->emon_session_start();

if ($route->controller=="") {
    if ($settings['public_mode_enabled']) {
        $route->controller = "system";
        $route->action = "list";
        $route->subaction = "public";
    } else {
        if (!$session['userid']) {
            $route->controller = "user";
            $route->action = "login";
        } else {
            $route->controller = "system";
            $route->action = "list";
            $route->subaction = "user";
        }
    }

}

switch ($route->controller) {

    case "graph":
        $route->format = "html";
        $output = view("views/graph2.php",array(
            "mode"=>"public",
            "systems"=>$system->list_public($session['userid']),
            "columns"=>$system->get_columns(),
            "stats_columns"=>$system_stats->schema['system_stats_monthly_v2']

        ));        
        break;

    case "compare":
        $route->format = "html";
        $output = view("views/compare.html", array("userid"=>$session['userid']));
        break;

    case "daily":
        $route->format = "html";

        $systemid = 1;
        if (isset($_GET['id'])) $systemid = (int) $_GET['id'];

        $system_data = $system->get($session['userid'],$systemid);
        
        $output = view("views/daily.php", array(
            "userid"=>$session['userid'],
            "systemid"=>$systemid,
            "system_data"=>$system_data   
        ));
        break;

    case "monthly":
        $route->format = "html";
        $output = view("views/monthly.php", array(
            "userid"=>$session['userid'],
            'system_stats_monthly'=>$system_stats->schema['system_stats_monthly_v2']
        ));
        break;

    case "histogram":
        $route->format = "html";
        $output = view("views/histogram.html", array("userid"=>$session['userid']));
        break;

    case "user":
        require "Modules/user/user_controller.php";
        $output = user_controller();
        break;

    case "system":
        require "Modules/system/system_controller.php";
        $output = system_controller();
        break;
        
    case "api":
        $route->format = "json";
        
        if (isset($_GET['system'])) {
            $config = $system_stats->get_system_config($session['userid'], (int) $_GET['system']);

            if ($route->action=="all") {
                $start = $_GET['start'];
                $end = $_GET['end'];
                $interval = $_GET['interval']; 
                $feeds = array($config->elec, $config->heat, $config->outsideT, $config->flowT, $config->returnT);
                $apikeystr = "";
                if ($config->apikey!="") $apikeystr = "&apikey=".$config->apikey;
                $result = json_decode(file_get_contents("$config->server/feed/data.json?ids=".implode(",",$feeds)."&start=$start&end=$end&interval=$interval&average=1&skipmissing=0&timeformat=notime".$apikeystr));
                
                $output = array(
                    "elec"=>$result[0]->data,
                    "heat"=>$result[1]->data,
                    "outsideT"=>$result[2]->data,
                    "flowT"=>$result[3]->data,
                    "returnT"=>$result[4]->data
                );
            }

            else if ($route->action=="histogram") {
                if ($route->subaction=="kwh_at_cop") {
                    // test
                    //$config->elec = 192;
                    //$config->heat = 163;
                    //$config->apikey = "b33c4080a2b7f5ee3b041bec1201d5bb";
                    //$config->server = "http://localhost/emoncms";
                    // convert array of params into url string
                    $params = array(
                        "elec"=>$config->elec,
                        "heat"=>$config->heat,
                        "start"=>$_GET['start'],
                        "end"=>$_GET['end'],
                        "div"=>0.1,
                        "interval"=>300,
                        "x_min"=>$_GET['x_min'],
                        "x_max"=>$_GET['x_max']
                    );
                    if ($config->apikey!="") $params['apikey'] = $config->apikey;
                    $result = file_get_contents("$config->server/histogram/data/kwh_at_cop?".http_build_query($params));
                    $output = json_decode($result);
                    if ($output==null) $output = $result;

                } else if ($route->subaction=="kwh_at_temperature") {
                    // test
                   // $config->heat = 163;
                    //$config->flowT = 363;
                    //$config->apikey = "b33c4080a2b7f5ee3b041bec1201d5bb";
                    //$config->server = "http://localhost/emoncms";
                    // convert array of params into url string
                    $params = array(
                        "power"=>$config->heat,
                        "temperature"=>$config->flowT,
                        "start"=>$_GET['start'],
                        "end"=>$_GET['end'],
                        "div"=>0.5,
                        "interval"=>300,
                        "x_min"=>$_GET['x_min'],
                        "x_max"=>$_GET['x_max']
                    );
                    if ($config->apikey!="") $params['apikey'] = $config->apikey;
                    $result = file_get_contents("$config->server/histogram/data/kwh_at_temperature?".http_build_query($params));
                    $output = json_decode($result);
                    if ($output==null) $output = $result;                    
                } else if ($route->subaction=="flow_temp_curve") {
                    // test
                   // $config->heat = 163;
                    //$config->flowT = 363;
                    //$config->apikey = "b33c4080a2b7f5ee3b041bec1201d5bb";
                    //$config->server = "http://localhost/emoncms";
                    // convert array of params into url string
                    $params = array(
                        "outsideT"=>$config->outsideT,
                        "flowT"=>$config->flowT,
                        "heat"=>$config->heat,
                        "start"=>$_GET['start'],
                        "end"=>$_GET['end'],
                        "div"=>0.5,
                        "interval"=>300,
                        "x_min"=>$_GET['x_min'],
                        "x_max"=>$_GET['x_max']
                    );
                    if ($config->apikey!="") $params['apikey'] = $config->apikey;
                    $result = file_get_contents("$config->server/histogram/data/flow_temp_curve?".http_build_query($params));
                    // $output = json_decode($result);
                    // if ($output==null) $output = $result;
                    $route->format = "text";
                    $output = $result;                  
                }
            }
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
        
    case "text":
        header('Content-Type: text/plain');
        echo $output;
        break;
}
