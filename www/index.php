<?php
/*
All HeatpumpMonitor.org code is released under the GNU Affero General Public License.
See COPYRIGHT.txt and LICENSE.txt.
Part of the OpenEnergyMonitor project:
http://openenergymonitor.org
*/

// This is the front controller for the HeatpumpMonitor.org web application
// It is responsible for routing requests to the correct controller and action
// It also loads the necessary models and views

// This is a very simple and lightweight MVC implementation.
// Designed to keep dependencies to a minimum and to implement only the basics.

// Load mysql & redis database
require "Lib/load_database.php";
require "core.php";
require "route.php";

// User model is required for session management
// RememberMe model is required for remember me functionality
require("Modules/user/rememberme_model.php");
$rememberme = new RememberMe($mysqli);
require("Modules/user/user_model.php");
$user = new User($mysqli,$rememberme);

// System model is used for loading system meta data
require ("Modules/system/system_model.php");
$system = new System($mysqli);

// System stats model is used for loading system stats data
require ("Modules/system/system_stats_model.php");
$system_stats = new SystemStats($mysqli,$system);

// Path and route
$path = get_application_path(false);
$route = new Route(get('q'), server('DOCUMENT_ROOT'), server('REQUEST_METHOD'));

// Session
$session = $user->emon_session_start();

// Default route
if ($route->controller=="") {
    // If public mode is enabled, show public systems
    if ($settings['public_mode_enabled']) {
        $route->controller = "system";
        $route->action = "list";
        $route->subaction = "public";
    } else {
        // If user is not logged in, show login page
        if (!$session['userid']) {
            $route->controller = "user";
            $route->action = "login";
        } else {
            // If user is logged in, show user systems
            $route->controller = "system";
            $route->action = "list";
            $route->subaction = "user";
        }
    }
}

switch ($route->controller) {

    // These are the main modules
    // Ideally all functionality should be moved into modules

    case "user":
        require "Modules/user/user_controller.php";
        $output = user_controller();
        break;

    case "system":
        require "Modules/system/system_controller.php";
        $output = system_controller();
        break;

    case "heatpump":
        require "Modules/heatpump/heatpump_controller.php";
        $output = heatpump_controller();
        break;

    case "dashboard":
        require "Modules/dashboard/dashboard_controller.php";
        $output = dashboard_controller();
        break;

    case "timeseries":
        require "Modules/timeseries/timeseries_controller.php";
        $output = timeseries_controller();
        break;

    case "histogram":
        require "Modules/histogram/histogram_controller.php";
        $output = histogram_controller();
        break;

    case "map":
        require "Modules/map/map_controller.php";
        $output = map_controller();
        break;

    // These are individual pages that are not *yet* part of the main modules

    case "heatloss":
        $route->format = "html";

        $systemid = 2;
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

    case "coldest":
        $route->format = "html";

        $systemid = 2;
        if (isset($_GET['id'])) $systemid = (int) $_GET['id'];
        
        $output = view("views/coldest.php", array(
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

    case "compare":
        $route->format = "html";
        $output = view("views/compare.php", array("userid"=>$session['userid']));
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
}

// The final step is to output the content
// The output format is determined by the route format
// html, json, text

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

// That's it! We welcome contributions to the HeatpumpMonitor.org project
