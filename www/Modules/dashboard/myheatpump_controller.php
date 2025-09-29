<?php
/*
 All Emoncms code is released under the GNU Affero General Public License.
 See COPYRIGHT.txt and LICENSE.txt.
 
 ---------------------------------------------------------------------
 Emoncms - open source energy visualisation
 Part of the OpenEnergyMonitor project:
 http://openenergymonitor.org
 */

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function myheatpump_app_controller($route,$app,$appconfig,$apikey)
{
    global $path, $session, $settings, $mysqli, $redis, $user;
    $v = 2;

    if (isset($app->userid) && !$user_timezone = $user->get_timezone($app->userid)) {
        $user_timezone = 'Europe/London';
    }
    if (is_numeric($user_timezone)) $user_timezone = "Europe/London";
    // if timezone UTC set to Europe/London
    if ($user_timezone == "UTC") $user_timezone = "Europe/London";

    require_once "Modules/feed/feed_model.php";
    $settings['feed']['max_datapoints'] = 100000;
    $settings['feed']['max_datapoints'] = 1200000;

    $feed = new Feed($mysqli,$redis,$settings['feed']);
    require_once "Modules/app/apps/OpenEnergyMonitor/myheatpump/myheatpump_process.php";
    require_once "Modules/app/apps/OpenEnergyMonitor/myheatpump/myheatpump_waft.php";
    require_once "Modules/app/apps/OpenEnergyMonitor/myheatpump/myheatpump_model.php";
    $myheatpump = new MyHeatPump($mysqli,$redis,$feed,$appconfig, $user_timezone);

    if ($route->action == "view" || $route->action == "") {
        $route->format = "html";
        $result = "\n<!-- global app css and js -->";
        $result .= "\n" . '<link href="' . $path . 'Modules/app/Views/css/app.css?v=' . $v . '" rel="stylesheet">';
        $result .= "\n" . '<script src="' . $path . 'Modules/app/Views/js/app.js?v=' . $v . '"></script>';
        $result .= "\n\n <!-- app specific view -->\n";

        $dir = $appconfig->get_app_dir($app->app);
        $result .= view($dir.$app->app.".php",array("id"=>$app->id, "name"=>$app->name, "public"=>$app->public, "appdir"=>$dir, "config"=>$app->config, "apikey"=>$apikey));
        return $result;
    }

    else if ($route->action == "getconfig") {
        $route->format = "json";
        return $app;
    }
    
    else if ($route->action == "getconfigmeta") {
        
        $result = array();
        $result["feeds"] = array();
        
        $result["start_date"] = 0;
        
        if (isset($app->config)) {
        
            if (isset($app->config->start_date)) {
                $result["start_date"] = (int) $app->config->start_date;
            }
        
            $feeds = array(
                "heatpump_elec",
                "heatpump_elec_kwh",
                "heatpump_heat", 
                "heatpump_heat_kwh", 
                "heatpump_flowT",
                "heatpump_returnT",
                "heatpump_flowrate",
                "heatpump_roomT",
                "heatpump_outsideT",
                "heatpump_dhw",
                "heatpump_ch",
                "heatpump_targetT",
                "heatpump_dhwT",
                "heatpump_dhwTargetT",
            );

            require_once "Modules/feed/feed_model.php";
            $feed = new Feed($mysqli,$redis,$settings['feed']);

            foreach ($feeds as $feed_name) {
                if (isset($app->config->$feed_name)) {
                    $feedid = (int) $app->config->$feed_name;
                    
                    if (!$feedid) continue;
                    
                    $feed_meta = array();
                    $meta = $feed->get_meta($feedid);
                    
                    $feed_meta['feedid'] = $feedid;
                    
                    if (isset($meta->start_time)) {
                        $feed_meta['start_time'] = $meta->start_time;
                    }
                    
                    if (isset($meta->end_time)) {
                        $feed_meta['end_time'] = $meta->end_time;
                    }

                    if (isset($meta->interval)) {
                        $feed_meta['interval'] = $meta->interval;
                    }

                    if (isset($meta->npoints)) {
                        $feed_meta['npoints'] = $meta->npoints;
                    }
                    
                    $result["feeds"][$feed_name] = $feed_meta;
                }
            }
        }
        return $result;
    }

    else if ($route->action == "getdailydatarange") {
        $route->format = "json";
        return $myheatpump->get_daily_range($app->id);
    }

    else if ($route->action == "getdailydata") {
        $route->format = "text";
        $start = (int) get('start',true);
        $end = (int) get('end',true);
        return $myheatpump->get_daily($app->id,$start,$end);
    }

    else if ($route->action == "getstats") {
        $route->format = "json";
        $start = (int) get('start',false);
        $end = (int) get('end',false);

        if (!$start || !$end) {
            $date = new DateTime();
            $date->setTimezone(new DateTimeZone($user_timezone));
            $date->setTime(0,0,0);
            $end = $date->getTimestamp();
            $date->modify("-1 day");
            $start = $date->getTimestamp();
        }

        return $myheatpump->get_stats($app->id,$start,$end);
    }

    // Get totals
    else if ($route->action == "gettotals") {
        $route->format = "json";
        // Start and end time optional
        $start = (int) get('start',false,false);
        $end = (int) get('end',false,false);
        return $myheatpump->process_from_daily($app->id,$start,$end);
    }

    // Process daily data
    else if ($route->action == "processdaily") {
        $route->format = "json";
        $timeout = (int) get('timeout',false,1);
        return $myheatpump->process_daily($app->id,$timeout);
    }

    // enabledailymode
    else if ($route->action == "enabledailymode") {
        $route->format = "json";

        // Load the app config
        $result = $mysqli->query("SELECT * FROM app WHERE `id`='".$app->id."'");
        $app = $result->fetch_object();
        $config = json_decode($app->config);

        if (!isset($config->enable_process_daily)) {
            $config->enable_process_daily = true;
        }

        // Update the app config, use prepared statement
        $config = json_encode($config);
        $stmt = $mysqli->prepare("UPDATE app SET `config`=? WHERE `id`=?");
        $stmt->bind_param("si",$config,$app->id);
        $stmt->execute();
        $stmt->close();

        return array("success"=>true, "config"=>json_decode($config));
    }

    // Clear daily data
    else if ($route->action == "cleardaily") {
        $route->format = "json";
        $app_id = (int) $app->id;
        
        if (isset($settings['app']) && isset($settings['app']['clearkey'])) {
            if (isset($_GET['clearkey']) && $_GET['clearkey'] == $settings['app']['clearkey']) {
                $mysqli->query("DELETE FROM myheatpump_daily_stats WHERE `id`='".$app_id."'");
                return array("success"=>true);        
            }
        }
        
        if (!$session["write"]) return array("success"=>false, "message"=>"Permission denied");
        if ($app->userid != $session["userid"]) return array("success"=>false, "message"=>"Permission denied");
        $mysqli->query("DELETE FROM myheatpump_daily_stats WHERE `id`='".$app_id."'");
        return array("success"=>true);
    }

    // Clear last 60 days
    else if ($route->action == "clearlast60days") {
        $route->format = "json";
        if (!$session["write"]) return array("success"=>false, "message"=>"Permission denied");
        if ($app->userid != $session["userid"]) return array("success"=>false, "message"=>"Permission denied");
        $mysqli->query("DELETE FROM myheatpump_daily_stats WHERE `id`='".$app->id."' AND `timestamp`>='".(time()-60*24*3600)."'");
        return array("success"=>true);
    }

    else if ($route->action == "weightedaverages") {
        $route->format = "json";
        $start = (int) get('start',false);
        $end = (int) get('end',false);

        if (!$start || !$end) {
            $date = new DateTime();
            // Europe/London is UTC+1
            $date->setTimezone(new DateTimeZone($user_timezone));
            // Set end to midnight start of today
            $date->setTime(0, 0, 0);
            $end = $date->getTimestamp();
            // start to 1 month ago
            $date->modify('-1 year');
            $start = $date->getTimestamp();         
        }
   
        if ($end<$start) {
            return array("success"=>false, "message"=>"End date is before start date");
        }

        return $myheatpump->get_weightedaverages($app->id,$start,$end);
    }

}
