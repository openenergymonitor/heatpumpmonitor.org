<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function timeseries_controller() {

    global $session, $route, $system, $redis, $mysqli, $system_stats, $settings, $user;
    
    // Read key access option
    if (isset($_GET['readkey']) && !$session['userid']) {
        $readkey = $_GET['readkey'];
        $session['userid'] = $user->get_userid_from_apikey_read($readkey);   
    } else {
        $readkey = "";
    }


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
        
    if ($route->action == "values_slow") {
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

        // emoncms now requires numeric timestamps, resolve date strings here
        if (!is_numeric($start)) $start = strtotime($start);
        if (!is_numeric($end)) $end = strtotime($end);
        if ($start === false || $end === false) {
            return array('success'=>false, 'message'=>'Invalid start or end time');
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

        // start/end are raw request values: url encode everything rather than
        // interpolating so extra parameters cannot be injected into the call
        $query = array(
            "ids" => implode(",",$feedids),
            "start" => $start,
            "end" => $end,
            "interval" => $interval,
            "average" => $average,
            "delta" => $delta,
            "skipmissing" => 0,
            "timeformat" => $timeformat
        );
        if ($config->apikey) $query["apikey"] = $config->apikey;

        $url = "$config->server/feed/data.json?".http_build_query($query);

        $result = json_decode(file_get_contents($url));

        if ($result === null) {
            return array('success'=>false, 'message'=>'Invalid response from emoncms');
        }
        // emoncms returns an error object rather than a list of feeds
        if (isset($result->success)) {
            return array('success'=>false, 'message'=>isset($result->message) ? $result->message : 'Request failed');
        }

        $remapped = array();

        foreach ($result as $data) {
            if (!isset($data->feedid) || !isset($feed_map[$data->feedid])) continue;
            $remapped[$feed_map[$data->feedid]] = $data->data;
        }

        return $remapped;
    }

    return false;
}
