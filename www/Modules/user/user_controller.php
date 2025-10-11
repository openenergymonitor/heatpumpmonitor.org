<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function user_controller() {

    global $mysqli, $session, $route, $user, $path, $settings;

    if ($route->action=="login") {
        if ($route->format=="html") {
            if (!$session['userid']) {
                return view("Modules/user/login_view.php", array("result"=>false));  
            } else {
                header('Location: '.$path);
            }
        } else if ($route->format=="json") {
            if ($settings['dev_env_login_enabled']) {
                return $user->login_using_dev_env(post("username", true),post("password", true));
            } else {
                return $user->login_using_emoncms(post("username", true),post("password", true));
            }
        }
    }

    if ($route->action=="view" && $session['userid']) {
        return view("Modules/user/account_view.php", array('account'=>$user->get($session['userid'])));  
    }

    if ($route->action=="admin" && $session['admin']) {
        $users = $user->admin_user_list();
        return view("Modules/user/admin_view.php", array("users"=>$users));  
    }

    if ($route->action=="switch" && $session['admin']) {
        $userid = get('userid');
        $user->admin_switch_user($userid);
        header("Location: ".$path."user/view");
        exit();
    }

    if ($route->action=="logout" && $session['userid']) {
        $user->logout();
        header("Location: ".$path);
        exit();
    }
    
    return false;
}