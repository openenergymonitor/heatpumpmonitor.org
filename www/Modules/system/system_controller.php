<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function system_controller() {

    global $session, $route, $system, $mysqli, $system_stats;



    if ($route->action=="new") {
        $route->format = "html";
        if ($session['userid']) {
            $system_data = $system->new();
            return view("Modules/system/system_view.php", array(
                "mode"=>"edit", 
                "system_data"=>$system_data, 
                'admin'=>$session['admin'], 
                'schema'=>$system->schema_meta,
                'system_stats_monthly'=>$system_stats->schema['system_stats_monthly']
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
                'system_stats_monthly'=>$system_stats->schema['system_stats_monthly']
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
            'system_stats_monthly'=>$system_stats->schema['system_stats_monthly']
        ));
    }

    if ($route->action=="list") {
        if ($route->format=="html") {
            // Public list view
            if ($route->subaction=="public") {
                return view("Modules/system/system_list.php",array(
                    "mode"=>"public",
                    "systems"=>$system->list_public($session['userid']),
                    "columns"=>$system->get_columns()
                ));

            // User list view
            } else if ($route->subaction=="user") {
                if ($session['userid']) {
                    return view("Modules/system/system_list.php",array(
                        "mode" => "user",
                        "systems"=>$system->list_user($session['userid']),
                        "columns"=>$system->get_columns()
                    ));
                }

            // Admin list view
            } else if ($route->subaction=="admin") {
                if ($session['userid'] && $session['admin']) {
                    return view("Modules/system/system_list.php",array(
                        "mode" => "admin",
                        "systems"=>$system->list_admin(),
                        "columns"=>$system->get_columns()
                    ));
                }
            // Original
            } else if ($route->subaction=="original") {
                return view("Modules/system/original/main.php",array());
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

        // stats/last30
        if ($route->subaction == "last30") { 
            return $system_stats->get_last30($system_id);

        // stats/last365
        } else if ($route->subaction == "last365") {
            return $system_stats->get_last365($system_id);

        // stats?start=2016-01-01&end=2016-01-02
        } else if ($route->subaction == "") {
            return $system_stats->get_monthly(
                get('start',true),
                get('end',true),
                $system_id
            );
        }
    }

    if ($route->action=="monthly") {
        $route->format = "json";
        return $system_stats->system_get_monthly(get('id',true));
    }

    if ($route->action=="get") {
        $route->format = "json";
        if ($session['userid']) {
            $systemid = get("id",false);
            return $system->get($session['userid'],$systemid);
        }    
    }

    if ($route->action=="save") {
        $route->format = "json";
        if ($session['userid']) {
            $input = json_decode(file_get_contents('php://input'));
            return $system->save($session['userid'],$input->id,$input->data);
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
            $system_data = $system->get($session['userid'],$systemid);

            $result = $system_stats->load_from_url($system_data->url);
            if (isset($result['success']) && $result['success']) {
                if ($system->has_access($session['userid'], $systemid)) {
                    $system_stats->save_last30($systemid, $result['stats']); 
                    $system_stats->save_last365($systemid, $result['stats']);
                    return array('success'=>true, 'message'=>'stats loaded');
                } else {
                    return array('success'=>false, 'message'=>'access denied');
                }
            } else {
                return array('success'=>false, 'message'=>'error loading stats');
            }
        }
    }

    if ($route->action=="loadmonthlystats") {
        $route->format = "json";
        if ($session['userid']) {

            $systemid = get("id",false);

            if ($system->has_access($session['userid'], $systemid)) {

                $system_data = $system->get($session['userid'],$systemid);

                // timestamp start of July
                $date = new DateTime();
                // set timezone Europe/London
                $date->setTimezone(new DateTimeZone('Europe/London'));
                $date->setDate(2022, 6, 1);
                $date->setTime(0, 0, 0);
                $start = $date->getTimestamp();

                while (true) {
                    // +1 month
                    $date->modify('+1 month');
                    $end = $date->getTimestamp();

                    $stats = $system_stats->load_from_url($system_data->url,$start,$end);
                    if (isset($stats['success']) && !$stats['success']) {
                        break;
                    }
                    $system_stats->save_monthly($systemid,$start,$stats['stats']);
                    if ($end>time()) break;

                    $start = $end;
                }
                return array('success'=>true, 'message'=>'monthly stats loaded');
            } else {
                return array('success'=>false, 'message'=>'access denied');
            }
        }
    }

    return false;
}
