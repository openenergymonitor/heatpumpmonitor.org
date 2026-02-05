<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function user_controller() {

    global $session, $route, $user, $path;

    // ------------------------------------------------------------------------------------------------

    // HTML Views
    if ($route->format=="html") {

        // Login view: no session
        if ($route->action=="login") {
            if ($session['userid']) {
                header('Location: '.$path);
                exit();
            } else {
                return view("Modules/user/Views/login/login_view.php", array());
            }
        }

        // Views that require an active session:
        if ($session['userid']) {
            // Account view: requires session
            if ($route->action=="account") {
                return view("Modules/user/Views/account/account_view.php", array(
                    'account'=>$user->get($session['userid'])
                ));
            }

            // Sub accounts requires active session
            if ($route->action=="subaccounts") {
                return view("Modules/user/Views/subaccount/subaccount_view.php", array());
            }

            // Admin view: requires admin session
            if ($route->action=="admin" && $route->subaction=="" && $session['admin']) {
                return view("Modules/user/Views/admin/admin_view.php", array());
            }
        }
    }

    // ------------------------------------------------------------------------------------------------

    $route->format = "json";

    // ------------------------------------------------------------------------------------------------
    // ACTIONS WITHOUT AN ACTIVE SESSION ONLY
    // ------------------------------------------------------------------------------------------------ 
    if (!$session['userid']) {

        // Login requires no session
        if ($route->action=="login" && !$session['userid']) {
            return $user->login(
                post("username",true),
                post("password", true),
                post("rememberme", false)
            );
        }

        // Password reset requires no session
        if ($route->action == 'passwordreset' && !$session['userid']) {
            return  $user->passwordreset(
                post('username'),
                post('email')
            );
        }

    // ------------------------------------------------------------------------------------------------
    // ACTIONS WITH AN ACTIVE SESSION ONLY
    // ------------------------------------------------------------------------------------------------ 
    } else if ($session['userid']) {
    
        // Logout requires active session
        if ($route->action=="logout") {
            $user->logout();
            header("Location: ".$path);
            exit();
        }

        // Sub accounts requires active session
        if ($route->action=="subaccounts") {
            return $user->get_sub_accounts_with_system_details($session['userid']);
        }

        // Update sub account details requires active session
        if ($route->action=="update-subaccount") {
            $data = json_decode(file_get_contents('php://input'), true);
            if ($data===null) {
                return array("success"=>false, "message"=>"Invalid JSON data.");
            }
            return $user->update_sub_account($session['userid'], $data);
        }

        // Change user password requires active session
        if ($route->action=="changepassword") {
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

        // ------------------------------------------------------------------------------------------------
        // ADMIN ONLY ACTIONS BELOW HERE
        // ------------------------------------------------------------------------------------------------
        if ($session['admin']) {

            // Admin user list requires admin session
            // Search by username or email if 'search' parameter provided
            if ($route->action=="admin" && $route->subaction=="list") {
                $search = get('search');
                return $user->admin_user_list($search);
            }

            // Switch user requires admin session
            if ($route->action=="switch") {
                $userid = get('userid');
                $user->admin_switch_user($userid);
                header("Location: ".$path."system/list/user");
            }
        }
    }

    // ------------------------------------------------------------------------------------------------
    
    $route->format = "html";
    return false;
}