<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function timeseries_controller() {

    global $session, $route, $system, $mysqli, $system_stats, $settings;
    
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

        $feedids = array();
        $feed_map = array(); // Maps feedid to array of feed keys
        foreach ($config->feeds as $key=>$f) {
            if (in_array($key,$feed_keys)) {
                $feedids[] = $f->feedid;
                // Store multiple keys that map to the same feedid
                if (!isset($feed_map[$f->feedid])) {
                    $feed_map[$f->feedid] = array();
                }
                $feed_map[$f->feedid][] = $key;
            }
        }
        
        // Remove duplicate feedids
        $feedids = array_unique($feedids);
        
        $url = "$config->server/feed/list.json?apikey=".$config->apikey;

        $result = json_decode(file_get_contents($url));
        
        $remapped = array();

        if ($result) {
            foreach ($result as $data) {
                if (isset($feed_map[$data->id])) {
                    // Create entries for all feed keys that map to this feedid
                    foreach ($feed_map[$data->id] as $key) {
                        $remapped[$key] = array(
                            "time"=> (int) $data->time,
                            "value"=> (float) $data->value
                        );
                    }
                }
            }
        }

        return $remapped;
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

        $feedids = array();
        $feed_map = array();
        foreach ($config->feeds as $key=>$f) {
            if (in_array($key,$feed_keys)) {
                $feedids[] = $f->feedid;
                $feed_map[$f->feedid] = $key;
            }
        }

        $apikeystr = "";
        if ($config->apikey) $apikeystr = "&apikey=".$config->apikey;
        
        $url = "$config->server/feed/data.json?ids=".implode(",",$feedids)."&start=$start&end=$end&interval=$interval&average=$average&dela=$delta&skipmissing=0&timeformat=$timeformat".$apikeystr;

        $result = json_decode(file_get_contents($url));

        $remapped = array();

        if ($result) {
            foreach ($result as $data) {
                $key = $feed_map[$data->feedid];
                $remapped[$key] = $data->data;
            }
        }

        return $remapped;
    }

    return false;
}
