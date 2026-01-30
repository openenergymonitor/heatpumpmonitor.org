<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function dashboard_controller() {
    global $route, $session, $system, $mysqli, $user;

    // Default to public or user mode
    $readkey = "";
    $private_mode = false;
    $session_userid = $session['userid'];

    // if a read key is provided and no user session exists
    // then switch to private mode
    if (isset($_GET['readkey']) && !$session['userid']) {
        $readkey = $_GET['readkey'];
        $session_userid = $user->get_userid_from_apikey_read($readkey);
        $private_mode = true;
    }

    // HTML view
    if ($route->action == "") {
        $systemid = (int) $_GET['id'];
        $route->format = "html";
        $system_data = $system->get($session_userid,$systemid);
        if (is_array($system_data) && isset($system_data['success']) && $system_data['success']==false) {
            // 
        } else {
            return view("Modules/dashboard/myheatpump.php", array(
                "id"=>$systemid,
                "system_data"=>$system_data,
                "apikey"=>$readkey,
                "private_mode"=>$private_mode
            ));
        }
    }

    return false;
}