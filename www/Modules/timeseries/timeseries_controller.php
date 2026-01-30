<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function timeseries_controller() {

    global $session, $route, $system, $redis, $mysqli, $system_stats, $settings;

    chdir("/var/www/emoncms");
    require_once "Lib/enum.php";
    require_once "Modules/feed/feed_model.php";
    $feed_model = new Feed($mysqli, $redis, $settings["feed"]);
    
    if ($route->action == "available") {
        $route->format = "json";
        $system_id = (int) get("id",true);
        
        $available = array();
        
        $config = $system_stats->get_system_config_with_meta($session['userid'], $system_id);
        if (is_array($config) && isset($config['success'])) {
            return $config;
        }
        
        unset($config->server);
        unset($config->apikey);
        foreach ($config->feeds as $f) {
            unset($f->feedid);
        }
     
        return $config;
    }

    if ($route->action == "values") {
        $route->format = "json";
        $system_id = (int) get("id",true);
        $feed_keys = explode(",",get("feeds",true));
        
        $available = array();
        
        $config = $system_stats->get_system_config_with_meta($session['userid'], $system_id);
        if (is_array($config) && isset($config['success'])) {
            return $config;
        }
        if (!$config->apikey) return false;

        $requested_feeds = array();

        foreach ($config->feeds as $key=>$f) {
            if (in_array($key,$feed_keys)) {
                $id = (int) $f->feedid;
                $lastvalue = $redis->hmget("feed:$id",array('time','value'));
                $requested_feeds[$key] = array(
                    "time" => (int) $lastvalue['time'],
                    "value" => (float) $lastvalue['value']
                );
            }
        }

        return $requested_feeds;
    }
    
    if ($route->action == "data") {
        $route->format = "json";

        $system_id = (int) get("id",true);
        $feed_keys = explode(",",get("feeds",true));
        $start = get("start",true);
        $end = get("end",true);
        $interval = (int) get("interval",true);
        $average = (int) get("average",false,0);
        $delta = (int) get("delta",false,0);
        $timeformat = get("timeformat",false,"unixms");

        if (!in_array($timeformat,array("unix","unixms","excel","iso8601","notime"))) {
            return array('success'=>false, 'message'=>'Invalid time format');
        }
        
        $config = $system_stats->get_system_config_with_meta($session['userid'], $system_id);
        if (is_array($config) && isset($config['success'])) {
            return $config;
        }

        $result = array();

        foreach ($config->feeds as $key=>$f) {
            if (in_array($key,$feed_keys)) {

                // Fixed parameters
                $skipmissing = 0;
                $limitinterval = 0;
                $csv = false;
                $timezone = "UTC";
                $dp = -1;

                $result[$key] = $feed_model->get_data($f->feedid,$start,$end,$interval,$average,$timezone,$timeformat,$csv,$skipmissing,$limitinterval,$delta,$dp);
            }
        }
        return $result;
    }

    return false;
}
