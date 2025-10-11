<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class User
{
    private $mysqli;
    private $rememberme;
    private $email_verification = true;

    public function __construct($mysqli, $rememberme)
    {
        $this->mysqli = $mysqli;
        $this->rememberme = $rememberme;
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
                $this->create_session($userid);
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
        $result = $this->mysqli->query("SELECT id,username,email,admin FROM users WHERE id='$userid'");
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
    
    public function login_using_emoncms($username, $password)
    {
        if (!$username || !$password) return array('success' => false, 'message' => _("Username or password empty"));

        $host = "https://emoncms.org";

        // Login using emoncms.org/user/auth.json
        if (!$result = http_request("POST", $host."/user/auth.json", array("username" => $username, "password" => $password))) {
            return array('success' => false, 'message' => _("Login error"));
        }
        $result = json_decode($result);
        if (!isset($result->success) || $result->success !== true) return $result;

        $userid = (int) $result->userid;
        $apikey_read = $result->apikey_read;
        $apikey_write = $result->apikey_write;

        // Fetch email using emoncms.org/user/get.json
        $user_get = json_decode(file_get_contents($host."/user/get.json?apikey=" . $apikey_write));
        $email = $user_get->email;

        if (!$this->userid_exists($userid)) {
            // Create new user fetch userid
            $stmt = $this->mysqli->prepare("INSERT INTO users (id, username, email, apikey_read, apikey_write, admin) VALUES (?, ?, ?, ?, ?, 0)");
            $stmt->bind_param("issss", $userid, $username, $email, $apikey_read, $apikey_write);
            $stmt->execute();
            $stmt->close();
        } else {
            // Update fields in users table
            $stmt = $this->mysqli->prepare("UPDATE users SET username=?, email=?, apikey_read=?, apikey_write=? WHERE id=?");
            $stmt->bind_param("ssssi", $username, $email, $apikey_read, $apikey_write, $userid);
            $stmt->execute();
            $stmt->close();
        }
        $admin = $this->is_admin($userid) ? 1 : 0;

        session_regenerate_id();
        $_SESSION['userid'] = $userid;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        $_SESSION['admin'] = $admin;

        $this->update_last_login($userid);

        // Remember me
        $this->rememberme->remember_me($userid);

        return array('success' => true, 'message' => _("Login successful"));
    }

    public function login_using_dev_env($username, $password)
    {
        if ($username === 'admin' && $password === 'admin') {
            session_regenerate_id();
            $_SESSION['userid'] = 1;
            $_SESSION['username'] = 'admin';
            $_SESSION['email'] = 'admin@localhost';
            $_SESSION['admin'] = 1;
            return array('success' => true, 'message' => _("Login successful"));
        } else {
            return array('success' => false, 'message' => _("Invalid username or password"));
        }
    }

    public function update_last_login($userid) {
        $userid = (int) $userid;
        $last_login = time();
        $stmt = $this->mysqli->prepare("UPDATE users SET last_login=? WHERE id=?");
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

    public function get_id($username)
    {
        if (!ctype_alnum($username)) return 0;

        if (!$stmt = $this->mysqli->prepare("SELECT id FROM users WHERE username = ?")) {
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
        $result = $this->mysqli->query("SELECT id,username,`name`,email FROM users WHERE id='$userid'");
        if ($result->num_rows == 0) {
            return false;
        } else {
            return $result->fetch_object();
        }
    }

    public function admin_user_list() {

        $result = $this->mysqli->query("SELECT id,username,`name`,email,created,last_login,`admin` FROM users");
        $users = array();
        while ($row = $result->fetch_object()) {

            $row->id = (int) $row->id;

            // Count number of systems in system_meta
            $result2 = $this->mysqli->query("SELECT id FROM system_meta WHERE userid='$row->id'");
            $row->systems = $result2->num_rows;


            $row->admin = $row->admin ? 'Yes' : '';
            $users[] = $row;
        }
        return $users;
    }

    public function admin_switch_user($userid) {
        $userid = (int) $userid;
        $result = $this->mysqli->query("SELECT id,username,email FROM users WHERE id='$userid'");
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
        $result = $this->mysqli->query("SELECT id FROM users WHERE id='$userid'");
        if ($result->num_rows > 0) {
            return true;
        } else {
            return false;
        }
    }

    // User is admin
    public function is_admin($userid) {
        $userid = (int) $userid;
        $result = $this->mysqli->query("SELECT admin FROM users WHERE id='$userid'");
        if ($result->num_rows == 0) {
            return false;
        } else {
            $row = $result->fetch_object();
            return $row->admin ? true : false;
        }
    }

    // Get list of sub accounts
    public function get_sub_accounts($userid) {
        $userid = (int) $userid;

        // Get this username
        $result = $this->mysqli->query("SELECT apikey_write FROM users WHERE id='$userid'");
        if (!$row = $result->fetch_object()) {
            return array(
                'success' => false,
                'message' => "User not found"
            );
        }
        
	    if (!$row->apikey_write) {
            return array(
                'success' => false,
                'message' => "No apikey found"
            );
        }

        // Get sub accounts
        $result = file_get_contents("https://emoncms.org/account/list.json?apikey=$row->apikey_write");
        $accounts_all_data = json_decode($result);

        $accounts = array();
        foreach ($accounts_all_data as $account) {
            $accounts[] = array(
                'userid' => (int) $account->id,
                'username' => $account->username
            );
        }

        return array(
            'success' => true,
            'accounts' => $accounts
        );
    }

}
