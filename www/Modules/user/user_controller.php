<?php

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
        return view("Modules/user/admin_view.php", array());  
    }

    if ($route->action=="logout" && $session['userid']) {
        $user->logout();
        header("Location: ".$path);
        exit();
    }
    
    return false;
}