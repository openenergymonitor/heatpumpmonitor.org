<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function system_controller() {

    global $session, $route, $system, $mysqli, $system_stats, $settings;



    if ($route->action=="new") {
        $route->format = "html";
        if ($session['userid']) {
            $system_data = $system->new();
            return view("Modules/system/system_view.php", array(
                "mode"=>"edit", 
                "system_data"=>$system_data, 
                'admin'=>$session['admin'], 
                'schema'=>$system->schema_meta,
                'system_stats_monthly'=>$system_stats->schema['system_stats_monthly_v2']
            ));
        }
    }

    if ($route->action=="edit") {
        $route->format = "html";
        if ($session['userid']) {
            $systemid = get("id",false);
            $system_data = $system->get($session['userid'],$systemid);
            return view("Modules/system/system_view.php", array(
                "mode"=>"edit", 
                "system_data"=>$system_data, 
                'admin'=>$session['admin'], 
                'schema'=>$system->schema_meta,
                'system_stats_monthly'=>$system_stats->schema['system_stats_monthly_v2']
            ));
        }
    }

    if ($route->action=="view") {
        $route->format = "html";
        $systemid = get("id",false);
        $system_data = $system->get($session['userid'],$systemid);
        return view("Modules/system/system_view.php", array(
            "mode"=>"view", 
            "system_data"=>$system_data, 
            'admin'=>$session['admin'], 
            'schema'=>$system->schema_meta,
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
                    "systems"=>$system->list_public($session['userid']),
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
                return $system->list_public($session['userid']);

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

        // stats?start=2016-01-01&end=2016-01-02
        if ($route->subaction == "") {
            return $system_stats->get_monthly(
                get('start',true),
                get('end',true),
                $system_id
            );
            
        // stats/last7
        } else if ($route->subaction == "last7") { 
            return $system_stats->get_last7($system_id);

        // stats/last30
        } else if ($route->subaction == "last30") { 
            return $system_stats->get_last30($system_id);

        // stats/last90
        } else if ($route->subaction == "last90") { 
            return $system_stats->get_last90($system_id);

        // stats/last365
        } else if ($route->subaction == "last365") {
            return $system_stats->get_last365($system_id);

        // stats/all
        } else if ($route->subaction == "all") {
            return $system_stats->get_all($system_id);

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
            return $system_stats->system_get_monthly(get('id',true));
        }
    }

    if ($route->action=="monthly") {
        $route->format = "json";
        return $system_stats->system_get_monthly(get('id',true));
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
            if ($system->has_access($session['userid'],$systemid)) {
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
            $systemid = get("id",false);
            return $system->delete($session['userid'],$systemid);
        }
    }

    if ($route->action=="loadstats") {
        $route->format = "json";
        if ($session['userid']) {
            $systemid = get("id",false);
            
            // Check if user has access
            if ($system->has_access($session['userid'],$systemid)==false) {
                return array("success"=>false, "message"=>"Invalid access");
            }
            
            $fp = fopen("/home/oem/hpmon_main/hpmon.lock", "w");
            if (!flock($fp, LOCK_EX | LOCK_NB)) {
                return array("success"=>false, "message"=>"Already running");
            }
            fclose($fp);
            
            shell_exec("php /home/oem/hpmon_main/load_and_process_cli.php $systemid all > /dev/null 2>&1 &");
            return array("success"=>false, "message"=>"Loading data and processing in background, check back in 5 minutes.");
        }
    }


    return false;
}
