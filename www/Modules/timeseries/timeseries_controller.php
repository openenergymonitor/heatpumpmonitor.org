<?php

// Restricts direct access to this script
defined('EMONCMS_EXEC') or die('Restricted access');

// Main controller function for handling time series data-related actions
function timeseries_controller() {

    // Import global variables required by this function
    global $session, $route, $system, $mysqli, $system_stats, $settings;
    
    // Handle the "available" action: fetch system configuration details
    if ($route->action == "available") {
        $route->format = "json"; // Set the response format to JSON
        $system_id = (int) get("id",true); // Retrieve and validate the system ID
        
        $available = array(); // Initialises an array to store available configurations
        
        // Fetch system configuration details with metadata
        $config = $system_stats->get_system_config_with_meta($session['userid'], $system_id);

        // If an error occurs, return the configuration without change
        if (is_array($config) && isset($config['success'])) {
            return $config;
        }
        
        // Remove sensitive data from the configuration
        unset($config->server);
        unset($config->apikey);

        // Remove unnecessary feed identifiers for security and simplicity
        foreach ($config->feeds as $f) {
            unset($f->feedid);
        }
        
        // Return the cleaned configuration
        return $config;
    }
    
    // Handle the "data" action: fetch time series data
    if ($route->action == "data") {
        $route->format = "json"; // Set the response format to JSON

        // Retrieve and validate the inputs
        $system_id = (int) get("id",true); // System ID
        $feed_keys = explode(",",get("feeds",true)); // Feed keys
        $start = get("start",true); // Start time
        $end = get("end",true); // End time
        $interval = (int) get("interval",true); // Data interval
        $average = (int) get("average",false,0); // Average flag with default:0
        $delta = (int) get("delta",false,0); // Delta flag with default:0
        $timeformat = get("timeformat",false,"unixms"); // Time format with default:UNIX ms

        // Validate the time format
        if (!in_array($timeformat,array("unix","unixms","excel","iso8601","notime"))) {
            return array('success'=>false, 'message'=>'Invalid time format');
        }
        
        // Fetch system configuration
        $config = $system_stats->get_system_config_with_meta($session['userid'], $system_id);
        
        // If an error occurs, return the configuration without change
        if (is_array($config) && isset($config['success'])) {
            return $config;
        }

        // Prepare feed IDS and a mapping between feed IDS and feed keys
        $feedids = array();
        $feed_map = array();
        foreach ($config->feeds as $key=>$f) {
            if (in_array($key,$feed_keys)) {
                $feedids[] = $f->feedid; // Add feed ID to the list
                $feed_map[$f->feedid] = $key; // Map feed ID to the feed key
            }
        }

        // Prepare the API key string if available
        $apikeystr = "";
        if ($config->apikey) $apikeystr = "&apikey=".$config->apikey;
        
        // Construct the URL for fetching data from the backend server
        $url = "$config->server/feed/data.json?ids=".implode(",",$feedids)."&start=$start&end=$end&interval=$interval&average=$average&dela=$delta&skipmissing=0&timeformat=$timeformat".$apikeystr;

        // Fetch the result from the constructed URL and decode as JSON
        $result = json_decode(file_get_contents($url));

        // Remap the results into a format keyed by feed keys
        $remapped = array();
        if ($result) {
            foreach ($result as $data) {
                $key = $feed_map[$data->feedid]; // Get the feed key from the mapping
                $remapped[$key] = $data->data; // Assign data to the corresponding feed key
            }
        }

        // Return the remapped data
        return $remapped;
    }

    // Returns false if no valid action was matched
    return false;
}
