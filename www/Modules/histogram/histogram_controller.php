<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

// Histogram controller
function histogram_controller() {

    global $route, $session, $system_stats;

    // HTML view
    if ($route->action == "") {
        $route->format = "html";
        return view("Modules/histogram/histogram_view.php", array("userid"=>$session['userid']));
    }

    // System id required
    $systemid = (int) get('id',true);
    // Load system config
    $config = $system_stats->get_system_config($session['userid'], $systemid);    

    // Get kWh vs COP histogram
    if ($route->action=="kwh_at_cop") {
        $route->format = "json";
        // Parameters
        $params = array(
            "elec"=>$config->elec,
            "heat"=>$config->heat,
            "start"=>get('start',true),
            "end"=>get('end',true),
            "div"=>0.1,
            "interval"=>300,
            "x_min"=>get('x_min',true),
            "x_max"=>get('x_max',true)
        );
        if ($config->apikey!="") $params['apikey'] = $config->apikey;
        $result = file_get_contents("$config->server/histogram/data/kwh_at_cop?".http_build_query($params));
        $output = json_decode($result);
        if ($output==null) $output = $result;
        return $output;
    }

    // Get kWh vs temperature histogram
    if ($route->action=="kwh_at_flow") {
        $route->format = "json";
        // Parameters
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
        return $output;
    }
    
    // Get kWh vs temperature histogram
    if ($route->action=="kwh_at_outside") {
        $route->format = "json";
        // Parameters
        $params = array(
            "power"=>$config->heat,
            "temperature"=>$config->outsideT,
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
        return $output;
    }

    // Get kWh vs power histogram
    if ($route->action=="kwh_at_power_elec") {
        $route->format = "json";
        // Parameters
        $params = array(
            "power"=>$config->elec,
            "start"=>$_GET['start'],
            "end"=>$_GET['end'],
            "div"=>20,
            "interval"=>300,
            "x_min"=>$_GET['x_min'],
            "x_max"=>$_GET['x_max']
        );
        if ($config->apikey!="") $params['apikey'] = $config->apikey;
        $result = file_get_contents("$config->server/histogram/data/kwh_at_power?".http_build_query($params));
        $output = json_decode($result);
        if ($output==null) $output = $result;
        return $output;
    }
    
    if ($route->action=="kwh_at_power_heat") {
        $route->format = "json";
        // Parameters
        $params = array(
            "power"=>$config->heat,
            "start"=>$_GET['start'],
            "end"=>$_GET['end'],
            "div"=>100,
            "interval"=>300,
            "x_min"=>$_GET['x_min'],
            "x_max"=>$_GET['x_max']
        );
        if ($config->apikey!="") $params['apikey'] = $config->apikey;
        $result = file_get_contents("$config->server/histogram/data/kwh_at_power?".http_build_query($params));
        $output = json_decode($result);
        if ($output==null) $output = $result;
        return $output;
    }
    
    
    
    // Get kWh vs temperature histogram
    if ($route->action=="kwh_at_flow_minus_outside") {
        $route->format = "json";
        // Parameters
        $params = array(
            "power"=>$config->heat,
            "flow"=>$config->flowT,
            "outside"=>$config->outsideT,
            "start"=>$_GET['start'],
            "end"=>$_GET['end'],
            "div"=>0.5,
            "interval"=>300,
            "x_min"=>$_GET['x_min'],
            "x_max"=>$_GET['x_max']
        );
        if ($config->apikey!="") $params['apikey'] = $config->apikey;
        $result = file_get_contents("$config->server/histogram/data/kwh_at_flow_minus_outside?".http_build_query($params));
        $output = json_decode($result);
        if ($output==null) $output = $result;
        return $output;
    }
    
    // Get kWh vs temperature histogram
    if ($route->action=="kwh_at_ideal_carnot") {
        $route->format = "json";
        // Parameters
        $params = array(
            "power"=>$config->heat,
            "flow"=>$config->flowT,
            "outside"=>$config->outsideT,
            "start"=>$_GET['start'],
            "end"=>$_GET['end'],
            "div"=>0.1,
            "interval"=>300,
            "x_min"=>$_GET['x_min'],
            "x_max"=>$_GET['x_max']
        );
        if ($config->apikey!="") $params['apikey'] = $config->apikey;
        $result = file_get_contents("$config->server/histogram/data/kwh_at_ideal_carnot?".http_build_query($params));
        $output = json_decode($result);
        if ($output==null) $output = $result;
        return $output;
    }

    // Get flow temperature curve
    // Not yet implemented in the histogram view
    if ($route->action=="flow_temp_curve") {
        // Parameters
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
        return $result;           
    }

    return false;
}
