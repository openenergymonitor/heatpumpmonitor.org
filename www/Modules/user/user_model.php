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

    public function login($username, $password, $with_emoncmsorg = false)
    {
        if (!$username || !$password) return array('success' => false, 'message' => _("Username or password empty"));

        if ($with_emoncmsorg) {
            return $this->login_using_emoncmsorg($username, $password);
        } else {
            return $this->login_local($username, $password);
        }
    }

    public function login_using_emoncmsorg($username, $password)
    {
        // Login using emoncms.org/user/auth.json
        if (!$result = http_request("POST", "https://emoncms.org/user/auth.json", array("username" => $username, "password" => $password))) {
            return array('success' => false, 'message' => _("Login error"));
        }
        $result = json_decode($result);
        if (!isset($result->success) || $result->success !== true) return $result;

        $emoncmsorg_userid = (int) $result->userid;
        $emoncmsorg_apikey_read = $result->apikey_read;
        $emoncmsorg_apikey_write = $result->apikey_write;

        // Fetch email using emoncms.org/user/get.json
        $user_get = json_decode(file_get_contents("https://emoncms.org/user/get.json?apikey=" . $emoncmsorg_apikey_write));
        $email = $user_get->email;

        // Check if emoncmsorg link exists
        $result = $this->mysqli->query("SELECT userid FROM emoncmsorg_link WHERE emoncmsorg_userid='$emoncmsorg_userid'");
        if ($result->num_rows == 0) {
            // Create new user fetch userid
            $stmt = $this->mysqli->prepare("INSERT INTO users (username, email, admin) VALUES (?, ?, 0)");
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $userid = (int) $stmt->insert_id;
            $stmt->close();

            // Create emoncmsorg link using prepared statement
            $stmt = $this->mysqli->prepare("INSERT INTO emoncmsorg_link (userid, emoncmsorg_userid, emoncmsorg_apikey_read, emoncmsorg_apikey_write) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $userid, $emoncmsorg_userid, $emoncmsorg_apikey_read, $emoncmsorg_apikey_write);
            $stmt->execute();
            $stmt->close();
        } else {
            $row = $result->fetch_object();
            $userid = (int) $row->userid;
        }

        // Check that the userid exists
        $result = $this->mysqli->query("SELECT admin FROM users WHERE id='$userid'");
        if ($result->num_rows == 0) {
            return array('success' => false, 'message' => _("User does not exist in users table"));
        } else {
            $row = $result->fetch_object();
            $admin = (int) $row->admin;
        }

        // Update fields in users table
        $stmt = $this->mysqli->prepare("UPDATE users SET username=?, email=? WHERE id=?");
        $stmt->bind_param("ssi", $username, $email, $userid);
        $stmt->execute();
        $stmt->close();

        // Update emoncmsorg link
        $stmt = $this->mysqli->prepare("UPDATE emoncmsorg_link SET emoncmsorg_apikey_read=?, emoncmsorg_apikey_write=? WHERE userid=?");
        $stmt->bind_param("ssi", $emoncmsorg_apikey_read, $emoncmsorg_apikey_write, $userid);
        $stmt->execute();

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

    public function login_local($username, $password)
    {
        // filter out all except for alphanumeric white space and dash
        $username_out = preg_replace('/[^\p{N}\p{L}_\s\-]/u', '', $username);
        if ($username_out != $username) return array('success' => false, 'message' => _("Username must only contain a-z 0-9 dash and underscore"));

        if (!$userid = $this->get_id($username)) {
            return array('success' => false, 'message' => _("Username does not exist"));
        }

        // Login using emoncms.org if emoncmsorg link exists
        if ($this->emoncmsorg_link_exists($userid)) {
            return $this->login_using_emoncmsorg($username, $password);
        }

        $result = $this->mysqli->query("SELECT * FROM users WHERE id = '$userid'");
        if (!$result) return array('success' => false, 'message' => _("Database error"));
        $userData = $result->fetch_object();
        
        if ($this->email_verification && isset($userData->email_verified) && !$userData->email_verified) return array('success' => false, 'message' => _("Please verify email address"));

        $hash = hash('sha256', $userData->salt . hash('sha256', $password));

        if ($hash != $userData->hash) {
            return array('success' => false, 'message' => _("Incorrect password"));
        } else {
            session_regenerate_id();
            $_SESSION['userid'] = $userData->id;
            $_SESSION['username'] = $username;
            $_SESSION['admin'] = $userData->admin;
            $_SESSION['email'] = $userData->email;

            $this->update_last_login($userData->id);

            // Remember me
            $this->rememberme->remember_me($userid);

            return array('success' => true, 'message' => _("Login successful"));
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

    public function register($username, $password, $email)
    {
        // Input validation, sanitisation and error reporting
        if (!$username || !$password || !$email) return array('success'=>false, 'message'=>_("Missing username, password or email parameter"));
        if (!ctype_alnum($username)) return array('success'=>false, 'message'=>_("Username must only contain a-z and 0-9 characters"));
        if ($this->get_id($username) != 0) return array('success'=>false, 'message'=>_("Username already exists"));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return array('success'=>false, 'message'=>_("Email address format error"));

        if (strlen($username) < 3 || strlen($username) > 30) return array('success'=>false, 'message'=>_("Username length error"));
        if (strlen($password) < 4 || strlen($password) > 250) return array('success'=>false, 'message'=>_("Password length error"));
        
        $hash = hash('sha256', $password);
        $salt = generate_secure_key(16);
        $hash = hash('sha256', $salt . $hash);

        $created = time();
        $stmt = $this->mysqli->prepare("INSERT INTO users ( username, hash, email, salt, created, admin) VALUES (?,?,?,?,?,0)");
        $stmt->bind_param("ssssi", $username, $hash, $email, $salt, $created);
        $stmt->execute();
        $userid = (int) $stmt->insert_id;
        $stmt->close();
        
        // Email verification
        if ($this->email_verification) {
            return $this->send_verification_email($userid);
        } else {
            session_regenerate_id();
            $_SESSION['userid'] = $userid;
            $_SESSION['username'] = $username;
            $_SESSION['admin'] = 0;
            $_SESSION['email'] = $email; 
            $this->update_last_login($userid);

            // Remember me
            $this->rememberme->remember_me($userid);

            return array('success'=>true, 'verifyemail'=>false, 'userid'=>$userid, 'message'=>"User account created");
        }
    }

    public function send_verification_email($userid)
    {
        $userid = (int) $userid;

        // check that username exists and load email and verification status 
        $stmt = $this->mysqli->prepare("SELECT username, email, email_verified FROM users WHERE id = ?");
        $stmt->bind_param("i", $userid);
        $stmt->execute();
        $stmt->store_result();

        if (!$stmt->num_rows) {
            $stmt->close();
            return array('success'=>false, 'message'=>_("User does not exist"));
        }

        // User exists
        $stmt->bind_result($username, $email, $email_verified);
        $stmt->fetch();
        $stmt->close();
        
        // exit if account is already verified
        if ($email_verified) return array('success'=>false, 'message'=>_("Email already verified"));
        
        // Create new verification key
        $verification_key = generate_secure_key(32);

        // Save new verification key
        $stmt = $this->mysqli->prepare("UPDATE users SET verification_key=? WHERE id=?");
        $stmt->bind_param("si",$verification_key,$userid);
        $stmt->execute();
        $stmt->close();
        
        // Send verification email
        global $path;
        $verification_url = $path."user/verify?email=".urlencode($email)."&key=$verification_key";
        
        require_once "Lib/email.php";
        $email_class = new Email();
        $email_class->send(array(
            "to" => $email,
            "subject" => "Please verify your email address",
            "text" => "Hello $username, please verify your email address by clicking on the following link: $verification_url",
            "html" => view("Modules/user/email_templates/verify_email.php",array(
                "username"=>$username,
                "verification_url"=>$verification_url
            ))
        ));
        
        return array('success'=>true, 'verifyemail'=>true, 'message'=>"Email verification email sent, please check your inbox");
    }

    public function verify_email($email,$verification_key)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return array('success'=>false, 'message'=>_("Email address format error"));
        if (strlen($verification_key)!=64) return array('success'=>false, 'message'=>_("Invalid verification key"));
        
        $stmt = $this->mysqli->prepare("SELECT id,email_verified FROM users WHERE email=? AND verification_key=?");
        $stmt->bind_param("ss",$email,$verification_key);
        $stmt->execute();
        $stmt->store_result();

        if (!$stmt->num_rows) {
            $stmt->close();
            return array('success'=>false, 'message'=>_("Invalid email or verification key"));
        }

        $stmt->bind_result($id,$email_verified);
        $stmt->fetch();
        $stmt->close();
        
        if ($email_verified==0) {
            $stmt = $this->mysqli->prepare("UPDATE users SET email_verified='1' WHERE id=?");
            $stmt->bind_param("i",$id);
            $stmt->execute();
            $stmt->close();
            return array('success'=>true, 'message'=>"Email verified");
        } else {
            return array('success'=>false, 'message'=>"Email already verified");
        }    
    }

    public function send_welcome_email($userid)
    {
        $userid = (int) $userid;

        // check that username exists and load email and verification status 
        $stmt = $this->mysqli->prepare("SELECT username, name, email FROM users WHERE id = ?");
        $stmt->bind_param("i", $userid);
        $stmt->execute();
        $stmt->store_result();

        if (!$stmt->num_rows) {
            $stmt->close();
            return array('success'=>false, 'message'=>_("User does not exist"));
        }

        // User exists
        $stmt->bind_result($username, $name, $email);
        $stmt->fetch();
        $stmt->close();

        require_once "Lib/email.php";
        $email_class = new Email();

        // Check for emoncms.org account link
        if ($this->emoncmsorg_link_exists($userid)) {
            $email_class->send(array(
                "to" => $email,
                "subject" => "Your HeatpumpMonitor.org login",
                "text" => "Hello $name, to login to HeatpumpMonitor.org please use your emoncms.org account details for username: $username\n\nRegards\nHeatpumpMonitor.org",
                // "html" => "<h4>Hello $name,</h4><p>To login to HeatpumpMonitor.org please use your emoncms.org account details for username: $username</p><p>Please login at <a href='https://heatpumpmonitor.org'>https://heatpumpmonitor.org</a></p><p>Regards<br>HeatpumpMonitor.org</p>"
                "html" => view("Modules/user/email_templates/welcome_emoncmsorg.php",array(
                    "name" => $name,
                    "username"=>$username,
                ))
            ));
            // update welcome_email_sent
            $this->mysqli->query("UPDATE users SET welcome_email_sent=UNIX_TIMESTAMP() WHERE id='$userid'");

            return array('success'=>true, 'message'=>"Welcome email sent");
        } else {
            // Generate new random password
            $newpass = hash('sha256',generate_secure_key(16));
            // Hash and salt
            $hash = hash('sha256', $newpass);
            $salt = generate_secure_key(16);
            $hash = hash('sha256', $salt . $hash);

            // Update table
            $stmt = $this->mysqli->prepare("UPDATE users SET hash=?, salt=? WHERE id=?");
            $stmt->bind_param("ssi",$hash,$salt,$userid);
            $stmt->execute();
            $stmt->close();

            require_once "Lib/email.php";
            $email_class = new Email();
            $email_class->send(array(
                "to" => $email,
                "subject" => "Your HeatpumpMonitor.org login",
                "text" => "Hello $name, your HeatpumpMonitor.org login details are:\nUsername: $username\nPassword: $newpass\nPlease login at https://heatpumpmonitor.org\nRegards\nHeatpumpMonitor.org",
                // "html" => "<h4>Hello $name</h4><p>Your HeatpumpMonitor.org login details are:</p><p>Username: $username</p><p>Password: $newpass</p><p>Please login at <a href='https://heatpumpmonitor.org'>https://heatpumpmonitor.org</a></p><p>Regards</p><p>HeatpumpMonitor.org</p>"
                "html" => view("Modules/user/email_templates/welcome_selfinstall.php",array(
                    "name" => $name,
                    "username"=>$username,
                    "password"=>$newpass
                ))    
            ));

            // update welcome_email_sent
            $this->mysqli->query("UPDATE users SET welcome_email_sent=UNIX_TIMESTAMP() WHERE id='$userid'");

            return array('success'=>true, 'message'=>"Welcome email sent");
        }
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
        $result = $this->mysqli->query("SELECT id,username,email FROM users WHERE id='$userid'");
        if ($result->num_rows == 0) {
            return false;
        } else {
            $row = $result->fetch_object();

            // check if emoncmsorg link exists
            $result = $this->mysqli->query("SELECT emoncmsorg_userid FROM emoncmsorg_link WHERE userid='$userid'");
            if ($result->num_rows > 0) {
                $row->emoncmsorg_link = true;
            } else {
                $row->emoncmsorg_link = false;
            }

            return $row;
        }
    }

    // Check if emoncmsorg link exists
    public function emoncmsorg_link_exists($userid)
    {
        $userid = (int) $userid;
        $result = $this->mysqli->query("SELECT emoncmsorg_userid FROM emoncmsorg_link WHERE userid='$userid'");
        if ($result->num_rows > 0) {
            return true;
        } else {
            return false;
        }
    }

    // Get emoncmsorg userid
    public function get_emoncmsorg_userid($userid) {
        $userid = (int) $userid;
        $result = $this->mysqli->query("SELECT emoncmsorg_userid FROM emoncmsorg_link WHERE userid='$userid'");
        if ($result->num_rows > 0) {
            $row = $result->fetch_object();
            return $row->emoncmsorg_userid;
        } else {
            return false;
        }
    }

    public function admin_user_list() {

        $result = $this->mysqli->query("SELECT id,username,`name`,email,created,last_login,welcome_email_sent,`admin` FROM users");
        $users = array();
        while ($row = $result->fetch_object()) {
            if ($emoncmsorg_userid = $this->get_emoncmsorg_userid($row->id)) {
                $row->emoncmsorg_link = $emoncmsorg_userid;
            } else {
                $row->emoncmsorg_link = '';
            }

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

    public function admin_delete_user($userid) {
        $userid = (int) $userid;

        // check if the user has system first and if so return error
        $result = $this->mysqli->query("SELECT id FROM system_meta WHERE userid='$userid'");
        if ($result->num_rows > 0) {
            return array('success'=>false, 'message'=>"User has $result->num_rows systems, cannot delete");
        }

        $result = $this->mysqli->query("SELECT id,username,email FROM users WHERE id='$userid'");
        if ($result->num_rows == 0) {
            return array('success'=>false, 'message'=>"User does not exist");
        } else {
            $row = $result->fetch_object();
            $this->mysqli->query("DELETE FROM users WHERE id='$userid'");
            $this->mysqli->query("DELETE FROM emoncmsorg_link WHERE userid='$userid'");
            return array('success'=>true, 'message'=>"User deleted");
        }
    }
}
