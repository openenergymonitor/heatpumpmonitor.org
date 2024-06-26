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
        $output = view("views/compare.php", array("userid"=>$session['userid']));
        break;

    case "heatloss":
        $route->format = "html";

        $systemid = 1;
        if (isset($_GET['id'])) $systemid = (int) $_GET['id'];
        
        $output = view("views/heatloss.php", array(
            "userid"=>$session['userid'],
            "systemid"=>$systemid 
        ));
        break;

    case "daily":
        $route->format = "html";

        $systemid = 2;
        if (isset($_GET['id'])) $systemid = (int) $_GET['id'];
        
        $output = view("views/daily.php", array(
            "userid"=>$session['userid'],
            "systemid"=>$systemid,
            "stats_schema"=>$system_stats->schema['system_stats_daily']  
        ));
        break;

    case "monthly":
        $route->format = "html";
        
        $systemid = 2;
        if (isset($_GET['id'])) $systemid = (int) $_GET['id'];
        
        $output = view("views/monthly.php", array(
            "userid"=>$session['userid'],
            "systemid"=>$systemid,   
            'system_stats_monthly'=>$system_stats->schema['system_stats_monthly_v2']
        ));
        break;

    case "about":
        $route->format = "html";
        $output = view("views/about.php", array(
            "userid"=>$session['userid'],
            "number_of_systems"=>$system->count_public()
            
        ));
        break;
        
    case "api-helper":
        $route->format = "html";
        $output = view("views/api.php", array("userid"=>$session['userid']));
        break;


    case "user":
        require "Modules/user/user_controller.php";
        $output = user_controller();
        break;

    case "system":
        require "Modules/system/system_controller.php";
        $output = system_controller();
        break;

    case "timeseries":
        require "Modules/timeseries/timeseries_controller.php";
        $output = timeseries_controller();
        break;

    case "histogram":
        require "Modules/histogram/histogram_controller.php";
        $output = histogram_controller();
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
