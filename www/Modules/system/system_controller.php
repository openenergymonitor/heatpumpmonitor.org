<?php

function system_controller() {

    global $mysqli, $session, $route, $system;

    if ($route->action=="create") {
        $route->format = "json";
        if ($session['userid']) {
            return $system->create($session['userid']);
        }
    }

    if ($route->action=="edit") {
        $route->format = "html";
        if ($session['userid']) {
            $systemid = get("id",false);
            $system_data = $system->get($session['userid'],$systemid);
            return view("Modules/system/system_view.php", array("system_data"=>$system_data));
        }
    }

    if ($route->action=="list") {
        $route->format = "html";
        if ($session['userid']) {
            $systems = $system->list($session['userid']);
            return view("Modules/system/user_list_view.php",array("systems"=>$systems));
        }
    }

    if ($route->action=="admin") {
        $route->format = "html";
        if ($session['userid']) {
            $systems = $system->list();
            return view("Modules/system/user_list_view.php",array("admin"=>true, "systems"=>$systems));
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

    return false;
}