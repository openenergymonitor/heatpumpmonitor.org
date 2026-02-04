<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class User
{
    private $mysqli;
    private $emoncms_mysqli;
    private $host;
    private $rememberme;

    public function __construct($mysqli, $rememberme)
    {
        $this->mysqli = $mysqli;

        global $emoncms_mysqli;
        $this->emoncms_mysqli = $emoncms_mysqli;

        $this->rememberme = $rememberme;

        global $settings;
        $this->host = $settings['emoncms_host'];
    }

    public function emon_session_start()
    {
        $cookie_params = session_get_cookie_params();

        //name of cookie 
        session_name('HPMON_ORG_SESSID');
        //get subdir installation 
        $cookie_params['path'] = dirname($_SERVER['SCRIPT_NAME']);
        // Add a slash if the last character isn't already a slash
        if (substr($cookie_params['path'], -1) !== '/')
            $cookie_params['path'] .= '/';
        //not pass cookie to javascript 
        $cookie_params['httponly'] = true;
        $cookie_params['samesite'] = 'Strict';

        if (is_https()) {
            $cookie_params['secure'] = true;
        }

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params($cookie_params);
        } else {
            session_set_cookie_params(
                $cookie_params['lifetime'],
                $cookie_params['path'],
                $cookie_params['domain'],
                $cookie_params['secure'],
                $cookie_params['httponly']
            );
        }

        session_start();

        // If no session, check for remember me cookie
        if (!isset($_SESSION['userid']) || !$_SESSION['userid']) {
            $userid = $this->rememberme->login_from_cookie();
            if ($userid) {
                if (!$this->create_session($userid)) {
                    $this->logout();
                }
            }
        }

        $session = array();

        if (isset($_SESSION['admin'])) $session['admin'] = $_SESSION['admin'];
        else $session['admin'] = 0;
        if (isset($_SESSION['userid'])) $session['userid'] = $_SESSION['userid'];
        else $session['userid'] = 0;
        if (isset($_SESSION['username'])) $session['username'] = $_SESSION['username'];
        else $session['username'] = '';
        if (isset($_SESSION['email'])) $session['email'] = $_SESSION['email'];
        else $session['email'] = '';

        return $session;
    }

    public function create_session($userid) {
        $userid = (int) $userid;
        $result = $this->emoncms_mysqli->query("SELECT id,username,email,admin FROM users WHERE id='$userid'");
        if ($result->num_rows == 0) {
            return false;
        } else {
            $row = $result->fetch_object();
            session_regenerate_id();
            $_SESSION['userid'] = $row->id;
            $_SESSION['username'] = $row->username;
            $_SESSION['admin'] = $row->admin;
            $_SESSION['email'] = $row->email;
            $this->update_last_login($userid);
            return true;
        }
    }
    
    public function login($username, $password, $rememberme = false)
    {
        if (!$username || !$password) return array('success'=>false, 'message'=>"Username or password empty");

        // filter out all except for alphanumeric white space and dash
        // if (!ctype_alnum($username))
        $username_out = preg_replace('/[^\p{N}\p{L}_\s\-]/u','',$username);
        if ($username_out!=$username) return array('success'=>false, 'message'=>"Username must only contain a-z 0-9 dash and underscore");

        if (!$userid = $this->get_id($username)) {
            return array('success'=>false, 'message'=>"Username does not exist");
        }

        $result = $this->emoncms_mysqli->query("SELECT * FROM users WHERE id = '$userid'");
        if (!$result) return array('success'=>false, 'message'=>"Database error");

        $userData = $result->fetch_object();
        
        if (isset($userData->email_verified) && !$userData->email_verified) return array('success'=>false, 'message'=>"Please verify email address");

        $hash = hash('sha256', $userData->salt . hash('sha256', $password));

        if ($hash != $userData->password) {
            return array('success'=>false, 'message'=>"Incorrect password");
        }
        else
        {
            // Default write access
            // if (!isset($userData->access)) $userData->access = 2;
            
            if ($userData->term>0) {
                $d = new DateTime();
                $d->setTimestamp($userData->term);
                $d->modify("+4 weeks");
                if ((time()-$d->getTimestamp())>0) {
                    // $this->log->error("Login: Account archived message:$username");             
                    return array('success'=>false, 'message'=>"This account has been archived.<br>Please contact us if you wish to restore the account:<br>support@openenergymonitor.zendesk.com");
                }
            }
            
            if ($userData->archived==1) {
                // $this->log->error("Login: Account archived message:$username");             
                return array('success'=>false, 'message'=>"This account has been archived.<br>Please contact us if you wish to restore the account:<br>support@openenergymonitor.zendesk.com");
            }

            // Read only access is not currently supported
            // only allow login if access level is == 2
            if (!isset($userData->access) || $userData->access<2) {
                return array('success'=>false, 'message'=>"Login disabled for this account");
            }
            
            // If no access via login
            if ($userData->access==0) {
                return array('success'=>false, 'message'=>"Login disabled for this account");
            }
        
            // Ensure session is active before regenerating
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $this->update_last_login($userid);

            session_regenerate_id(true);
            $_SESSION['userid'] = $userData->id;
            $_SESSION['username'] = $username;
            
            if ($userData->access>0) { 
                $_SESSION['read'] = 1;
            }
            if ($userData->access>1) {
                $_SESSION['write'] = 1;
                $_SESSION['admin'] = $userData->admin;
            }
            $_SESSION['lang'] = $userData->language;
            $_SESSION['timezone'] = $userData->timezone;
            $_SESSION['emailverified'] = $userData->email_verified;
            $_SESSION['gravatar'] = $userData->gravatar;
            $_SESSION['email'] = $userData->email;

            if ($rememberme) {
                $this->rememberme->remember_me($userid);
            }
            
            return array('success' => true, 'message' => _("Login successful"));
        }
    }

    public function update_last_login($userid) {
        $userid = (int) $userid;
        $last_login = time();
        $stmt = $this->emoncms_mysqli->prepare("UPDATE users SET lastactive=? WHERE id=?");
        $stmt->bind_param("ii", $last_login, $userid);
        $stmt->execute();
        $stmt->close();
    }

    public function logout()
    {
        if (isset($_SESSION['userid'])) {
            $userid = (int) $_SESSION['userid'];
            $this->rememberme->logout($userid);
        }
        session_unset();
        session_destroy();
    }

    public function passwordreset($username,$emailto)
    {
        // if null or empty
        if (!$username || !$emailto) return array('success'=>false, 'message'=>"Username or email empty");
        
        $username_out = preg_replace('/[^\p{N}\p{L}_\s\-]/u','',$username);
        if (!filter_var($emailto, FILTER_VALIDATE_EMAIL)) return array('success'=>false, 'message'=>"Email address format error");

        $stmt = $this->emoncms_mysqli->prepare("SELECT id FROM users WHERE username=? AND email=?");
        $stmt->bind_param("ss",$username_out,$emailto);
        $stmt->execute();
        $stmt->bind_result($userid);
        $stmt->fetch();
        $stmt->close();
        
        if ($userid!==false && $userid>0)
        {
            // Generate new random password
            // 8 characters long with letter, numbers and mixed case
            $newpass = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);

            // Hash and salt
            $hash = hash('sha256', $newpass);
            $salt = generate_secure_key(16);
            $password = hash('sha256', $salt . $hash);

            require_once "Lib/email.php";
            $email_class = new Email();
            $email_class->send(array(
                "to" => array(array("email" => $emailto)),
                "subject" => "HeatpumpMonitor.org password reset",
                "text" => "A password reset was requested for your HeatpumpMonitor account.\n\nYou can now login with password: $newpass",
                "html" => "<p>A password reset was requested for your HeatpumpMonitor account.</p><p>Your can now login with password: <b>$newpass</b> </p>"
            ));

            // Save password and salt
            $stmt = $this->emoncms_mysqli->prepare("UPDATE users SET password = ?, salt = ? WHERE id = ?");
            $stmt->bind_param("ssi", $password, $salt, $userid);
            $stmt->execute();
            $stmt->close();

            return array('success'=>true, 'message'=>"Password recovery email sent!");
        } else {
            return array('success'=>false, 'message'=>"Invalid username or email");
        }
    }
    public function change_password($userid, $old, $new)
    {
        $userid = (int) $userid;

        if (strlen($old) < 4 || strlen($old) > 250) return array('success'=>false, 'message'=>"Password length error");
        if (strlen($new) < 4 || strlen($new) > 250) return array('success'=>false, 'message'=>"Password length error");

        // 1) check that old password is correct
        $result = $this->emoncms_mysqli->query("SELECT password, salt FROM users WHERE id = '$userid'");
        $row = $result->fetch_object();
        $hash = hash('sha256', $row->salt . hash('sha256', $old));

        if ($hash == $row->password)
        {
            // 2) Save new password
            $hash = hash('sha256', $new);
            $salt = generate_secure_key(16);
            $password = hash('sha256', $salt . $hash);

            $stmt = $this->emoncms_mysqli->prepare("UPDATE users SET password = ?, salt = ? WHERE id = ?");
            $stmt->bind_param("ssi", $password, $salt, $userid);
            $stmt->execute();
            $stmt->close();
            
            return array('success'=>true, 'message'=>"Password updated successfully");
        }
        else
        {
            // $ip_address = get_client_ip_env();
            // $this->log->error("change_password: old password incorect ip:$ip_address");
            return array('success'=>false, 'message'=>"Old password incorect");
        }
    }

    public function get_id($username)
    {
        if (!ctype_alnum($username)) return 0;

        if (!$stmt = $this->emoncms_mysqli->prepare("SELECT id FROM users WHERE username = ?")) {
            return 0;
        }
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($id);
        if (!$stmt->fetch()) {
            $stmt->close();
            return 0;
        } else {
            $stmt->close();
            return $id;
        }
    }

    public function get($userid)
    {
        $userid = (int) $userid;
        $result = $this->emoncms_mysqli->query("SELECT id,username,email,timezone FROM users WHERE id='$userid'");
        if ($result->num_rows == 0) {
            return false;
        } else {
            $row = $result->fetch_object();
            $row->sub_account_count = $this->count_sub_accounts($userid);

            return $row;
        }
    }

    // count sub accounts for admin user
    public function count_sub_accounts($admin_userid)
    {
        $admin_userid = (int) $admin_userid;
        $result = $this->emoncms_mysqli->query("SELECT COUNT(*) AS sub_account_count FROM billing_linked WHERE adminuser='$admin_userid'");
        if ($linked_row = $result->fetch_object()) {
            return (int) $linked_row->sub_account_count;
        } else {
            return 0;
        }
    }

    public function admin_user_list($searchstr = '') {

        // If search term is empty return empty array
        if ($searchstr == '') {
            return array();
        }

        // Sanitize search string
        $searchstr = trim($searchstr);
        $searchstr = preg_replace('/[^\p{N}\p{L}_\s\-@.]/u','',$searchstr);
        if (strlen($searchstr) < 2) {
            return array();
        }

        $orderby = 'id';
        $order = 'ASC';
        $limit = 100;

        // Use prepared statement with LIKE clause for safe searching
        $search_param = "%$searchstr%";
        $stmt = $this->emoncms_mysqli->prepare(
            "SELECT id, username, email, lastactive FROM users 
             WHERE username LIKE ? OR email LIKE ? OR id = ? 
             ORDER BY $orderby $order LIMIT ?"
        );
        
        if (!$stmt) {
            return array();
        }

        // Try to convert search string to int for ID search, or use 0 if not numeric
        $search_id = is_numeric($searchstr) ? (int)$searchstr : 0;
        
        $stmt->bind_param("ssii", $search_param, $search_param, $search_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $users = array();
        while ($row = $result->fetch_object()) {
            $row->id = (int) $row->id;

            $accounts = $this->get_user_accounts($row->id);
            $row->subaccounts = count($accounts) - 1;

            // Fetch count of systems
            $row->systems = 0;

            foreach ($accounts as $account_id) {
                $system_result = $this->mysqli->query("SELECT COUNT(*) AS system_count FROM system_meta WHERE userid='{$account_id}'");
                if ($system_row = $system_result->fetch_object()) {
                    $row->systems += (int) $system_row->system_count;
                }
            }

            $users[] = $row;
        }
        
        $stmt->close();

        return $users;
    }

    public function admin_switch_user($userid) {
        $userid = (int) $userid;
        $result = $this->emoncms_mysqli->query("SELECT id,username,email FROM users WHERE id='$userid'");
        if ($result->num_rows == 0) {
            return false;
        } else {
            $row = $result->fetch_object();
            session_regenerate_id();
            $_SESSION['userid'] = $row->id;
            $_SESSION['username'] = $row->username;
            $_SESSION['admin'] = 0;
            $_SESSION['email'] = $row->email;

            return true;
        }
    }

    // Userid exists
    public function userid_exists($userid) {
        $userid = (int) $userid;
        $result = $this->emoncms_mysqli->query("SELECT id FROM users WHERE id='$userid'");
        if ($result->num_rows > 0) {
            return true;
        } else {
            return false;
        }
    }

    // User is admin
    public function is_admin($userid) {
        $userid = (int) $userid;
        $result = $this->emoncms_mysqli->query("SELECT admin FROM users WHERE id='$userid'");
        if ($result->num_rows == 0) {
            return false;
        } else {
            $row = $result->fetch_object();
            return $row->admin ? true : false;
        }
    }

    // Get userid from apikey read
    public function get_userid_from_apikey_read($apikey_read) {

        // Sanitize apikey 32 char hex
        if (!preg_match('/^[a-f0-9]{32}$/', $apikey_read)) {
            return false;
        }

        $stmt = $this->emoncms_mysqli->prepare("SELECT id FROM users WHERE apikey_read = ?");
        $stmt->bind_param("s", $apikey_read);
        $stmt->execute();
        $stmt->bind_result($id);
        if ($stmt->fetch()) {
            $stmt->close();
            return (int) $id;
        } else {
            $stmt->close();
            return false;
        }
    }

    // Get list of linked users for an admin user
    public function get_user_accounts($admin_userid)
    {
        $admin_userid = (int) $admin_userid;
        $accounts = array($admin_userid);

        // Get linked users
        $result = $this->emoncms_mysqli->query("SELECT linkeduser FROM billing_linked WHERE adminuser='$admin_userid'");
        while ($row = $result->fetch_object()) {
            $accounts[] = (int) $row->linkeduser;
        }

        return $accounts;
    }

    // Get list of sub accounts with usernames, access levels etc
    public function get_sub_accounts_with_system_details($userid) {
        $userid = (int) $userid;

        // Get sub accounts from local accounts table
        $result = $this->emoncms_mysqli->query("SELECT u.id, u.username, u.email, u.access, u.lastactive FROM billing_linked a JOIN users u ON a.linkeduser = u.id WHERE a.adminuser = '$userid' ORDER BY u.id ASC");
        $accounts = array();
        while ($row = $result->fetch_object()) {

            $row->id = (int) $row->id;
            $row->access = (int) $row->access;

            // Load system details from system_meta for each sub account - if the user has one system
            // system location, hp_manufacturer, hp_model, hp_output
            $system_result = $this->mysqli->query("SELECT location, hp_manufacturer, hp_model, hp_output FROM system_meta WHERE userid='{$row->id}' LIMIT 1");
            if ($system_row = $system_result->fetch_object()) {
                $row->system_location = $system_row->location;
                $row->hp_manufacturer = $system_row->hp_manufacturer;
                $row->hp_model = $system_row->hp_model;
                $row->hp_output = $system_row->hp_output;
            } else {
                $row->system_location = '';
                $row->hp_manufacturer = '';
                $row->hp_model = '';
                $row->hp_output = '';
            }

            $accounts[] = $row;
        }

        return array(
            'success' => true,
            'accounts' => $accounts
        );
    }

    // Update sub account details
    public function update_sub_account($admin_userid, $data) {
        $admin_userid = (int) $admin_userid;

        // Validate data
        if (!isset($data['sub_account_userid'])) {
            return array('success' => false, 'message' => 'Sub account userid missing');
        }

        $sub_account_userid = (int) $data['sub_account_userid'];

        // First check if the user exists
        if (!$this->userid_exists($sub_account_userid)) {
            return array('success' => false, 'message' => 'Sub account userid does not exist');
        }

        // Check that sub account belongs to admin user
        $result = $this->emoncms_mysqli->query("SELECT COUNT(*) as count FROM billing_linked WHERE adminuser='$admin_userid' AND linkeduser='$sub_account_userid'");
        $row = $result->fetch_object();
        if ($row->count == 0) {
            return array('success' => false, 'message' => 'Sub account does not belong to admin user');
        }

        // Start by changing username if modified, return errors if any
        if (isset($data['username'])) {
            $result = $this->change_username($sub_account_userid, $data['username']);
            if (!$result['success']) return $result;
        }
        
        // Change email if modified, return errors if any
        if (isset($data['email'])) {
            $result = $this->change_email($sub_account_userid, $data['email']);
            if (!$result['success']) return $result;
        }

        // Change access level if modified, return errors if any
        if (isset($data['access'])) {
            $result = $this->change_access_level($sub_account_userid, $data['access']);
            if (!$result['success']) return $result;
        }

        // If password provided, change password
        if (isset($data['password']) && !empty($data['password'])) {
            $result = $this->change_password_no_check($sub_account_userid, $data['password']);
            if (!$result['success']) return $result;
        }

        return array('success' => true, 'message' => 'Sub account updated successfully');
    }

    // Change user account username
    public function change_username($userid, $username)
    {
        // if (isset($_SESSION['cookielogin']) && $_SESSION['cookielogin']==true) {
        // return array('success'=>false, 'message'=>tr("As you are using a cookie based remember me login, please logout and log back in to change email"));
        // }

        $userid = (int) $userid;
        if (strlen($username) < 3 || strlen($username) > 30) return array('success'=>false, 'message'=>"Username length error");
        if (!ctype_alnum($username)) return array('success'=>false, 'message'=>"Username must only contain a-z and 0-9 characters");

        $userid_from_username = $this->get_id($username);

        if (!$userid_from_username) {
            $stmt = $this->emoncms_mysqli->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->bind_param("si", $username, $userid);
            $stmt->execute();
            $stmt->close();
            return array('success'=>true, 'message'=>"Username updated");
        } else {
            return array('success'=>false, 'message'=>"Username already exists");
        }
    }

    // Change user account email
    public function change_email($userid, $email)
    {
        // if (isset($_SESSION['cookielogin']) && $_SESSION['cookielogin']==true) {
        // return array('success'=>false, 'message'=>tr("As you are using a cookie based remember me login, please logout and log back in to change email"));
        // }

        $userid = (int) $userid;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return array('success'=>false, 'message'=>"Email address format error");
        }

        $stmt = $this->emoncms_mysqli->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->bind_param("si", $email, $userid);
        $stmt->execute();
        $stmt->close();

        return array('success'=>true, 'message'=>"Email updated");
    }

    // Change user account access level
    public function change_access_level($userid, $access_level)
    {
        $userid = (int) $userid;
        $access_level = (int) $access_level;
        if ($access_level < 0 || $access_level > 2) {
            return array('success'=>false, 'message'=>"Access level value error");
        }

        $stmt = $this->emoncms_mysqli->prepare("UPDATE users SET access = ? WHERE id = ?");
        $stmt->bind_param("ii", $access_level, $userid);
        $stmt->execute();
        $stmt->close();
        return array('success'=>true, 'message'=>"Access level updated");
    }

    // Change password (without old password check!)
    public function change_password_no_check($userid, $new) {
        $userid = (int) $userid;

        if (strlen($new) < 8 || strlen($new) > 250) return array('success'=>false, 'message'=>"Password length error");

        // Save new password
        $hash = hash('sha256', $new);
        $salt = generate_secure_key(16);
        $password = hash('sha256', $salt . $hash);

        $stmt = $this->emoncms_mysqli->prepare("UPDATE users SET password = ?, salt = ? WHERE id = ?");
        $stmt->bind_param("ssi", $password, $salt, $userid);
        $stmt->execute();
        $stmt->close();
        
        return array('success'=>true, 'message'=>"Password updated successfully");
    }

}
