<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function user_controller() {

    global $mysqli, $session, $route, $user, $path;

    if ($route->action=="login") {
        if ($route->format=="html") {
            if (!$session['userid']) {
                return view("Modules/user/login_view.php", array("result"=>false));  
            } else {
                header('Location: '.$path);
            }
        } else if ($route->format=="json") {
            return $user->login(post("username"),post("password"),post("emoncmsorg"));
        }
    }

    if ($route->action=="register") {
        $route->format = "json";
        $password1 = post("password");
        $password2 = post("password2");
        if ($password1!=$password2) {
            return array("success"=>false,"message"=>"Passwords do not match");
        }
        return $user->register(post("username"),$password1,post("email"));
    }

    if ($route->action == 'verify') {
        $email = get('email',true);
        $key = get('key',true);
        $result = $user->verify_email($email,$key);
        return view("Modules/user/login_view.php", array("result"=>$result));  
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

    if ($route->action=="welcome" && $session['admin']) {
        $route->format = "json";
        $userid = get('userid');
        return $user->send_welcome_email($userid);
    }

    if ($route->action=="logout" && $session['userid']) {
        $user->logout();
        header("Location: ".$path);
        exit();
    }
    
    return false;
}