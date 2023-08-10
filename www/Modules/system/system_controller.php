<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function system_controller() {

    global $session, $route, $system, $mysqli;

    require ("Modules/system/system_stats_model.php");
    $system_stats = new SystemStats($mysqli,$system);

    if ($route->action=="new") {
        $route->format = "html";
        if ($session['userid']) {
            $system_data = $system->new();
            return view("Modules/system/system_view.php", array("mode"=>"edit", "system_data"=>$system_data, 'admin'=>$session['admin'], 'schema'=>$system->schema_meta));
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
        $route->format = "html";

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
            $input = json_decode(file_get_contents('php://input'));
            $stats = $system_stats->load_from_url($input->url);
            if ($stats !== false) {
                if ($system->has_access($session['userid'], $input->systemid)) {
                    $system_stats->save_last30($input->systemid, $stats); 
                    $system_stats->save_last365($input->systemid, $stats);
                }
            }
            return $stats;
        }
    }

    return false;
}