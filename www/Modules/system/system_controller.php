<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function system_controller() {

    global $session, $route, $user, $system, $mysqli, $system_stats, $system_photos, $settings;



    if ($route->action=="new") {
        $route->format = "html";
        if ($session['userid']) {
            $system_data = $system->new();
            return view("Modules/system/system_view.php", array(
                "mode"=>"edit", 
                "system_data"=>$system_data, 
                'admin'=>$session['admin'], 
                'schema'=>$system->schema_meta,
                'email'=>"",
                'system_stats_monthly'=>$system_stats->schema['system_stats_monthly_v2']
            ));
        }
    }

    if ($route->action=="edit") {
        $route->format = "html";
        if ($session['userid']) {
            $systemid = get("id",false);
            $system_data = $system->get($session['userid'],$systemid);

            if ($session['admin']) {
                $u = $user->get($system_data->userid);
                $email = $u->email;
            } else {
                $email = "";
            }
            
            return view("Modules/system/system_view.php", array(
                "mode"=>"edit", 
                "system_data"=>$system_data, 
                'admin'=>$session['admin'], 
                'schema'=>$system->schema_meta,
                'email'=>$email,
                'system_stats_monthly'=>$system_stats->schema['system_stats_monthly_v2']
            ));
        }
    }

    if ($route->action=="view") {
        $route->format = "html";
        $systemid = get("id",false);
        $system_data = $system->get($session['userid'],$systemid);

        if ($session['admin']) {
            $u = $user->get($system_data->userid);
            $email = $u->email;
        } else {
            $email = "";
        }

        return view("Modules/system/system_view.php", array(
            "mode"=>"view", 
            "system_data"=>$system_data, 
            'admin'=>$session['admin'], 
            'schema'=>$system->schema_meta,
            'email'=>$email,
            'system_stats_monthly'=>$system_stats->schema['system_stats_monthly_v2']
        ));
    }
    
    if ($route->action=="log" && $session['admin']) {
        if ($route->format=="json") {
            $system_id = get("id",false);
            return $system->get_changes($system_id);
        } else {
            $route->format = "html";
            $system_id =  get("id",false);
            return view("Modules/system/system_log_view.php", array(
                "system_id"=>$system_id
            ));
        }
    }

    if ($route->action=="list") {
        if ($route->format=="html") {
            // Public list view
            if ($route->subaction=="public" && $settings['public_mode_enabled']) {
                return view("Modules/system/system_list.php",array(
                    "mode"=>"public",
                    "systems"=>$system->list_public(),
                    "columns"=>$system->get_columns(),
                    "stats_columns"=>$system_stats->schema['system_stats_monthly_v2']
                ));

            // User list view
            } else if ($route->subaction=="user") {
                if ($session['userid']) {
                    return view("Modules/system/system_list.php",array(
                        "mode" => "user",
                        "systems"=>$system->list_user($session['userid']),
                        "columns"=>$system->get_columns(),
                        "stats_columns"=>$system_stats->schema['system_stats_monthly_v2']
                    ));
                }

            // Admin list view
            } else if ($route->subaction=="admin") {
                if ($session['userid'] && $session['admin']) {
                    return view("Modules/system/system_list.php",array(
                        "mode" => "admin",
                        "systems"=>$system->list_admin(),
                        "columns"=>$system->get_columns(),
                        "stats_columns"=>$system_stats->schema['system_stats_monthly_v2']
                    ));
                }
            }
        } else {
            // Public list view
            if ($route->subaction=="public") {
                return $system->list_public();

            // User list view
            } else if ($route->subaction=="user") {
                if ($session['userid']) {
                    return $system->list_user($session['userid']);
                }

            // Admin list view
            } else if ($route->subaction=="admin") {
                if ($session['userid'] && $session['admin']) {
                    return $system->list_admin();
                }
            }
        }
    }

    // Return system stats
    if ($route->action=="stats") {
        $route->format = "json";
        
        $system_id = false;
        if (isset($_GET['id'])) {
            $system_id = (int) $_GET['id'];
        }

        $mode = "public";
        if (isset($_GET['mode'])) {
            if ($_GET['mode']=="user") {
                $mode = "user";
            } else if ($_GET['mode']=="admin" && $system->is_admin($session['userid'])) {
                $mode = "admin";
            }
        }

        // check userid has access to system
        if ($system_id!==false && !$system->has_read_access($session['userid'], $system_id)) {
            return array("success"=>false, "message"=>"Invalid access");
        }

        // stats?start=2016-01-01&end=2016-01-02
        if ($route->subaction == "") {
            return $system_stats->get_monthly(
                get('start',true),
                get('end',true),
                $system_id
            );
            
        // stats/last7
        } else if ($route->subaction == "last7") { 
            return $system_stats->get_last7($session['userid'], $system_id, $mode);

        // stats/last30
        } else if ($route->subaction == "last30") { 
            return $system_stats->get_last30($session['userid'], $system_id, $mode);

        // stats/last90
        } else if ($route->subaction == "last90") { 
            return $system_stats->get_last90($session['userid'], $system_id, $mode);

        // stats/last365
        } else if ($route->subaction == "last365") {
            return $system_stats->get_last365($session['userid'], $system_id, $mode);

        // stats/all
        } else if ($route->subaction == "all") {
            return $system_stats->get_all($session['userid'], $system_id, $mode);

        // stats/custom
        } else if ($route->subaction == "custom") {
            return $system_stats->get_custom($session['userid'], $system_id, $mode);

        // stats/custom
        // } else if ($route->subaction == "custom") {
        //     return false;
        //     return $system_stats->get_custom($session['userid'], $system_id, $mode,
        //         get('start',true),
        //         get('end',true)
        //     );

        } else if ($route->subaction == "export") {
            if ($route->subaction2 == "daily") {
                $system_stats->export_daily($system_id);
            }
        } else if ($route->subaction == "daily") {
            $route->format = "text";
            
            return $system_stats->get_daily(
                $system_id,
                get('start',false),
                get('end',false),   
                get('fields',false),     
            );
        } else if ($route->subaction=="monthly") {
            $route->format = "json";
            return $system_stats->system_get_monthly($system_id);
        }
    }

    if ($route->action=="get") {
        $route->format = "json";
        $systemid = get("id",true);
        return $system->get($session['userid'],$systemid);  
    }

    if ($route->action=="save") {
        $route->format = "json";
        if ($session['userid']) {
            $input = json_decode(file_get_contents('php://input'));
            return $system->save($session['userid'],$input->id,$input->data);
        }
    }

    // check if user has access to system
    // hasaccess?id=1
    if ($route->action=="hasaccess") {
        $route->format = "text";
        if ($session['userid']) {
            $systemid = get("id",false);
            if ($system->has_write_access($session['userid'],$systemid)) {
                return 1;
            } else {
                return 0;
            }
        } else {
            return 0;
        }
    }

    if ($route->action=="delete") {
        $route->format = "json";
        if ($session['userid']) {
            $systemid = (int) get("id",true);
            return $system->delete($session['userid'],$systemid);
        }
    }

    if ($route->action=="loadstats") {
        $route->format = "json";
        // return array("success"=>false, "message"=>"Reloading stats temporarily disabled");

        if ($session['userid']) {
            $systemid = (int) get("id",false);
            
            // Check if user has access
            if ($system->has_write_access($session['userid'],$systemid)==false) {
                return array("success"=>false, "message"=>"Invalid access");
            }
            
            $fp = fopen("/opt/openenergymonitor/heatpumpmonitor/hpmon.lock", "w");
            if (!flock($fp, LOCK_EX | LOCK_NB)) {
                return array("success"=>false, "message"=>"Already running");
            }
            fclose($fp);
            
            // Use escapeshellarg and explicit casting to avoid command injection
            $systemid_arg = escapeshellarg((int)$systemid);
            $logfile = '/var/log/heatpumpmonitor/reload' . (int)$systemid . '.log';
            $cmd = "php /opt/openenergymonitor/heatpumpmonitor/load_and_process_cli.php {$systemid_arg} all > " . escapeshellarg($logfile) . " 2>&1 &";
            shell_exec($cmd);
            return array("success"=>false, "message"=>"Loading data and processing in background, check back in 5 minutes.");
        }
    }

    // Load reload log
    if ($route->action=="reloadlog") {
        $route->format = "text";

        if ($session['userid']) {
            $systemid = (int) get("id",false);
            
            // Check if user has access
            if ($system->has_write_access($session['userid'],$systemid)==false) {
                return array("success"=>false, "message"=>"Invalid access");
            }

            // check if file exists
            if (file_exists("/var/log/heatpumpmonitor/reload$systemid.log")) {
                $log = file_get_contents("/var/log/heatpumpmonitor/reload$systemid.log");
                return $log;
            } else {
                return "No log file found";
            }
        }
    }

    // Get list off myheatpump apps associated with linked emoncms account
    if ($route->action=="available") {
        $route->format = "json";
        if ($session['userid']) {
            return $system->available_apps($session['userid']);
        }
    }

    // Upload photo endpoint
    if ($route->action=="upload-photo") {
        $route->format = "json";
        if ($session['userid']) {
            return $system_photos->upload_photo($session['userid']);
        }
        return array("success" => false, "message" => "Authentication required");
    }

    // Get photos for a system
    if ($route->action=="photos") {
        $route->format = "json";
        $system_id = get("id", false);
        if ($system_id) {
            return $system_photos->get_photos($session['userid'], $system_id);
        }
        return array("success" => false, "message" => "System ID required");
    }

    // Delete photo
    if ($route->action=="delete-photo") {
        $route->format = "json";
        if ($session['userid']) {
            $photo_id = get("photo_id", false);
            if ($photo_id) {
                return $system_photos->delete_photo($session['userid'], $photo_id);
            }
            return array("success" => false, "message" => "Photo ID required");
        }
        return array("success" => false, "message" => "Authentication required");
    }


    return false;
}
