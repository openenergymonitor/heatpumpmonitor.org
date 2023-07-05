<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function system_controller() {

    global $session, $route, $system;

    if ($route->action=="new") {
        $route->format = "html";
        if ($session['userid']) {
            $system_data = $system->new();
            return view("Modules/system/system_view.php", array("system_data"=>$system_data, 'admin'=>$session['admin']));
        }
    }

    if ($route->action=="edit") {
        $route->format = "html";
        if ($session['userid']) {
            $systemid = get("id",false);
            $system_data = $system->get($session['userid'],$systemid);
            return view("Modules/system/system_view.php", array("system_data"=>$system_data, 'admin'=>$session['admin']));
        }
    }

    if ($route->action=="list") {
        $route->format = "html";
        if ($session['userid']) {
            $systems = $system->list_user($session['userid']);
            return view("Modules/system/system_list.php",array("admin"=>false, "systems"=>$systems));
        }
    }

    if ($route->action=="admin") {
        $route->format = "html";
        if ($session['userid'] && $session['admin']) {
            $systems = $system->list_admin();
            return view("Modules/system/system_list.php",array("admin"=>true, "systems"=>$systems));
        }
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
            return $system->load_stats_from_url($input->url);
        }
    }

    return false;
}