<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function user_controller() {

    global $session, $route, $user, $path;

    // ------------------------------------------------------------------------------------------------

    // Server-side gravatar proxy, see User::get_gravatar. Responds with image
    // bytes rather than a view, so it returns nothing and exits here.
    if ($route->action=="gravatar" && $session['userid']) {
        $hash = get('hash');

        // Only ever the visitor's own avatar. Both call sites, the navbar and
        // the account page, render the session user's address and nothing
        // else, so an arbitrary hash is never legitimate here. Left open, any
        // logged in account could use the proxy to find out whether some other
        // address has a gravatar, and could grow the shared cache directory
        // without limit: gravatar.com answers 200 for an unknown address, so
        // every distinct hash and size combination writes a file.
        $own_avatar = $user->gravatar_hash_matches($hash, $session['email']);
        if (!$own_avatar) {
            // The session address can lag the users table, as changing a sub
            // account's email does not touch that account's live session.
            // Fall back to the stored one so their avatar keeps working
            // rather than dropping to the placeholder until they log in again.
            $account = $user->get($session['userid']);
            $own_avatar = $account && $user->gravatar_hash_matches($hash, $account->email);
        }
        if (!$own_avatar) {
            header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
            exit();
        }

        $avatar = $user->get_gravatar($hash, (int) get('s'));
        if ($avatar === false) {
            header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
            exit();
        }
        header("Content-Type: ".$avatar['mime']);
        header("Content-Length: ".strlen($avatar['content']));
        header("Cache-Control: private, max-age=86400");
        echo $avatar['content'];
        exit();
    }

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

        // Redeem an emailed password reset link. Tokens are issued into the
        // shared users table by passwordreset() here, or by emoncms.org, and
        // redeem on either site. Reachable with or without a session: someone
        // logged in on this browser is still entitled to finish a reset.
        if ($route->action=="passwordreset-confirm") {
            // The token is checked before rendering the form so an expired or
            // used link says so up front. Missing key falls through to the same
            // "invalid link" message as a bad one.
            $key = get('key', false, '');
            return view("Modules/user/Views/login/passwordreset_confirm_view.php", array(
                'key' => $key,
                'key_valid' => $user->passwordreset_key_is_valid($key)
            ));
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

    // Password reset redemption: session independent, see the view route above
    if ($route->action=="passwordreset-confirm" && $route->method=="POST") {
        return $user->passwordreset_confirm(post('key'), post('password'));
    }

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