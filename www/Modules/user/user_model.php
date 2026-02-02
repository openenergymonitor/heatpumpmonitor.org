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
    
    public function login($username, $password)
    {
        if (!$username || !$password) return array('success'=>false, 'message'=>tr("Username or password empty"));

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
            if (!isset($userData->access)) $userData->access = 2;
            
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

            $this->rememberme->remember_me($userid);

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
        $result = $this->emoncms_mysqli->query("SELECT id,username,email FROM users WHERE id='$userid'");
        if ($result->num_rows == 0) {
            return false;
        } else {
            return $result->fetch_object();
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
            "SELECT id, username, email FROM users 
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

    // Get list of sub accounts
    public function get_sub_accounts($userid) {
        $userid = (int) $userid;

        // Get sub accounts from local accounts table
        $result = $this->emoncms_mysqli->query("SELECT u.id, u.username FROM billing_linked a JOIN users u ON a.linkeduser = u.id WHERE a.adminuser = '$userid'");
        $accounts = array();
        while ($row = $result->fetch_object()) {
            $accounts[] = array(
                'userid' => (int) $row->id,
                'username' => $row->username
            );
        }

        return array(
            'success' => true,
            'accounts' => $accounts
        );
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
}
