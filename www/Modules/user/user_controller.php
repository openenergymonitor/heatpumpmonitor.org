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
            return $user->login(
                post("username",true),
                post("password", true),
                post("rememberme", false)
            );
        }
    }

    if ($route->action=="account" && $session['userid']) {
        return view("Modules/user/account_view.php", array(
            'account'=>$user->get($session['userid'])
        ));  
    }

    if ($route->action=="admin" && $session['admin']) {
        if ($route->subaction=="") {
            return view("Modules/user/admin_view.php", array());
        }
        else if ($route->subaction=="list") {
            $route->format = "json";
            $search = get('search');
            return $user->admin_user_list($search);
        }
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


    // Sub accounts requires active session
    if ($route->action=="subaccounts" && $session['userid']) {
        if ($route->format=="html") {
            return view("Modules/user/Views/subaccount/subaccount_view.php", array());
        } else {
            return $user->get_sub_accounts($session['userid']);
        }
    }


    // JSON API
    $route->format = "json";


    // Change user password requires active session
    if ($route->action=="changepassword" && $session['userid']) {
        $new_password = post('new');
        if (empty($new_password)) {
            return array("success"=>false, "message"=>"New password cannot be empty.");
        }
        return $user->change_password(
            $session['userid'],
            post('old'),
            $new_password
        );
    }

    // Password reset requires no session
    if ($route->action == 'passwordreset' && !$session['userid']) {
        return  $user->passwordreset(
            post('username'),
            post('email')
        );
    }

    
    $route->format = "html";
    return false;
}