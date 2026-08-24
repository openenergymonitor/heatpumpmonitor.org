<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class User
{
    private $mysqli;
    private $emoncms_mysqli;
    private $host;
    private $rememberme;
    private $redis;
    private $log;

    private $email_verification = false;

    // Must match User::$password_reset_window in the emoncms codebase: this
    // site issues the reset token, emoncms.org redeems it
    private $password_reset_window = 3600;

    public function __construct($mysqli, $rememberme)
    {
        $this->mysqli = $mysqli;

        global $emoncms_mysqli;
        $this->emoncms_mysqli = $emoncms_mysqli;

        $this->rememberme = $rememberme;

        global $redis;
        $this->redis = $redis;

        $this->log = new EmonLogger(__FILE__);

        global $settings;
        $this->host = $settings['emoncms_host'];

        if (isset($settings["email_verification"])) {
            $this->email_verification = $settings["email_verification"];
        }
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

        if (!isset($_SESSION['userid']) || !$_SESSION['userid']) {
            // No session: try the remember me cookie
            $userid = $this->rememberme->login();
            if ($userid) {
                if (!$this->create_session($userid, true)) {
                    $this->logout();
                }
            } else if ($this->rememberme->loginTokenWasInvalid()) {
                // A token was presented that had already been rotated past,
                // which means someone is replaying a copied cookie. Rememberme
                // has revoked every token on the account by this point, on both
                // sites; make sure nothing is left standing here either.
                $this->logout();
            }
        } else if (!empty($_SESSION['cookielogin'])) {
            // Session was resumed from a cookie rather than from the login form.
            // Re-check the token on every request so that a revocation, whether
            // from a password reset here or on emoncms.org, from a logout
            // elsewhere, or from the theft detection above, ends this session
            // rather than waiting for it to expire on its own.
            if (!$this->rememberme->cookieIsValid($_SESSION['userid'])) {
                $this->logout();
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
        if (isset($_SESSION['cookielogin'])) $session['cookielogin'] = $_SESSION['cookielogin'];
        else $session['cookielogin'] = 0;

        return $session;
    }

    /**
     * Start a session for a user who has not just typed their password.
     *
     * Only the remember me cookie path reaches this. It applies the same account
     * state checks as login(): without them an account that has since been
     * archived, or had its login access revoked, would keep resuming a session
     * from a cookie issued before the restriction was applied, indefinitely.
     *
     * Deliberately does not grant admin. Admin here can list every account and
     * switch into any of them, which is not something a cookie sitting on a
     * laptop for three months should be able to do; an admin logs in with their
     * password for that. emoncms.org withholds admin on this path for the same
     * reason.
     *
     * @param int  $userid
     * @param bool $cookielogin true when the session came from a cookie
     * @return bool
     */
    public function create_session($userid, $cookielogin = false) {
        $userid = (int) $userid;
        $result = $this->emoncms_mysqli->query("SELECT id,username,email,admin,access,archived,term FROM users WHERE id='$userid'");
        if (!$result || $result->num_rows == 0) {
            return false;
        } else {
            $row = $result->fetch_object();

            if ($this->account_login_denied($row)) {
                $this->log->error("create_session: refused for restricted account userid:$userid");
                return false;
            }

            session_regenerate_id(true);
            $_SESSION['userid'] = $row->id;
            $_SESSION['username'] = $row->username;
            $_SESSION['email'] = $row->email;
            if ($cookielogin) {
                $_SESSION['admin'] = 0;
                $_SESSION['cookielogin'] = true;
            } else {
                $_SESSION['admin'] = $row->admin;
            }
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
            return array('success'=>false, 'message'=>"Invalid username or password");
        }

        $result = $this->emoncms_mysqli->query("SELECT * FROM users WHERE id = '$userid'");
        if (!$result) return array('success'=>false, 'message'=>"Database error");

        $userData = $result->fetch_object();
        
        if ($this->email_verification && isset($userData->email_verified) && !$userData->email_verified) return array('success'=>false, 'message'=>"Please verify email address");

        // Reads all three formats: the legacy sha256, bcrypt and argon2id. The
        // account may have been migrated off sha256 by a login on emoncms.org,
        // which shares this users table, so this site has to be able to read
        // whatever that one wrote. See Lib/password.php.
        if (!verify_password($password, $userData->password, $userData->salt))
        {
            $this->log->error("Login: incorrect password username:$username ip:".get_client_ip_env());
            return array('success'=>false, 'message'=>"Invalid username or password");
        }
        else
        {
            // Upgrade the stored hash if it is still sha256, or was written with
            // weaker parameters than settings now ask for
            $this->upgrade_password_hash($userData->id, $password, $userData->password);

            // Default write access
            // if (!isset($userData->access)) $userData->access = 2;

            if ($denied = $this->account_login_denied($userData)) {
                if ($denied === 'archived') {
                    $this->log->error("Login: account archived username:$username");
                    return array('success'=>false, 'message'=>"This account has been archived.<br>Please contact us if you wish to restore the account:<br>support@openenergymonitor.zendesk.com");
                }
                return array('success'=>false, 'message'=>"Login disabled for this account");
            }

            // Ensure session is active before regenerating
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $this->update_last_login($userid);

            // The user still has their password, so any reset link sitting in
            // their inbox is no longer needed and should stop working now rather
            // than at the end of its window. Matches emoncms.org, which clears
            // the same columns on its own login.
            $this->clear_password_reset_token($userData->id);

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
                if (!$this->rememberme->createCookie($userid)) {
                    $this->logout();
                    return array('success'=>false, 'message'=>"Error creating rememberme cookie, try login without rememberme");
                }
            } else {
                // Logging in without the box ticked retires any cookie this
                // browser was still holding, rather than leaving it live
                $this->rememberme->clearCookie();
            }

            return array('success' => true, 'message' => _("Login successful"));
        }
    }

    /**
     * Replace a stored hash with a current one, after the password has been
     * verified against it.
     *
     * This is how accounts move off the old sha256 scheme: there is no way to
     * convert a stored hash without the password, so each account is upgraded at
     * the one moment the password is in memory, a successful authentication.
     * emoncms.org does the same on its own login, against the same table, so an
     * account migrates whichever site its owner happens to use first.
     *
     * Never allowed to fail the authentication that triggered it: the caller has
     * already verified the password, so a write failure here means the account
     * stays on the old format and is retried next time, not that the user is
     * refused.
     *
     * @param int    $userid
     * @param string $password verified plaintext
     * @param string $stored   hash it was verified against
     * @return void
     */
    private function upgrade_password_hash($userid, $password, $stored)
    {
        $userid = (int) $userid;
        if ($userid < 1) return;
        if (!password_needs_upgrade($stored)) return;

        try {
            $new_hash = hash_password($password);
            $salt = '';

            // Guarded on the hash it was verified against, so a concurrent
            // password change, here or on emoncms.org, is not overwritten by
            // this upgrade
            $stmt = $this->emoncms_mysqli->prepare("UPDATE users SET password=?, salt=? WHERE id=? AND password=?");
            $stmt->bind_param("ssis", $new_hash, $salt, $userid, $stored);
            $stmt->execute();
            $upgraded = $stmt->affected_rows;
            $stmt->close();

            if ($upgraded > 0) {
                $algo = password_hash_config();
                $this->log->info("upgrade_password_hash: upgraded to ".$algo['name']." userid:$userid");
            }
        } catch (Exception $e) {
            $this->log->warn("upgrade_password_hash failed userid:$userid ".$e->getMessage());
        }
    }

    /**
     * Clear any outstanding password reset token for a user.
     *
     * Called whenever the account holder proves they still have the password. A
     * live emailed link is only meant to cover the case where they have lost it,
     * so leaving one usable for the rest of the window is an unnecessary window
     * for whoever can read that mailbox.
     *
     * @param int $userid
     * @return void
     */
    private function clear_password_reset_token($userid)
    {
        $userid = (int) $userid;
        if ($userid < 1) return;

        // This runs on the login path, so it must never be able to break a
        // login: where the emoncms schema update has not been applied the
        // columns are missing and prepare() throws
        try {
            $stmt = $this->emoncms_mysqli->prepare("UPDATE users SET password_reset_hash='', password_reset_expires=0 WHERE id=? AND password_reset_hash!=''");
            $stmt->bind_param("i", $userid);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            $this->log->warn("clear_password_reset_token failed userid:$userid ".$e->getMessage());
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
        // Retires this browser's token only, not every token on the account: a
        // logout on one machine should not sign the account out on the others.
        // Theft detection and password resets are the paths that revoke all of
        // them, see Rememberme::clearAllTriplets().
        $this->rememberme->clearCookie();
        session_unset();
        session_destroy();
    }

    /**
     * Send a password reset link.
     *
     * HeatpumpMonitor accounts are emoncms.org accounts: this site has no users
     * table of its own, it reads and writes emoncms's. So this issues a reset
     * token into the shared users table and emails a link that lands on
     * emoncms.org, which owns the credential and is the only place a password
     * is actually changed (User::passwordreset_confirm there).
     *
     * This deliberately does NOT change the password. It is reachable by anyone
     * knowing a username and an email address, so setting a new credential here
     * let a stranger lock any account out of both sites at will.
     *
     * The response is the same whether or not an account matched, so it cannot
     * be used to test which usernames or addresses are registered.
     *
     * @param string $username
     * @param string $emailto
     * @return array
     */
    public function passwordreset($username,$emailto)
    {
        // Sent whatever happens below: never reveal whether an account matched
        $generic = array('success'=>true, 'message'=>"If that username and email match an account, a password reset link has been sent.");

        if ($this->is_rate_limited('passwordreset', 3, 900)) {
            return array('success'=>false, 'message'=>"Too many attempts, please try again later");
        }

        // if null or empty
        if (!$username || !$emailto) return $generic;

        // Usernames are emoncms usernames: a-z 0-9 only
        if (!ctype_alnum($username) || strlen($username) < 3 || strlen($username) > 30) return $generic;
        if (!filter_var($emailto, FILTER_VALIDATE_EMAIL)) return $generic;

        $stmt = $this->emoncms_mysqli->prepare("SELECT id,access,archived,term FROM users WHERE username=? AND email=?");
        $stmt->bind_param("ss",$username,$emailto);
        $stmt->execute();
        $userData = $stmt->get_result()->fetch_object();
        $stmt->close();

        if (!$userData || $userData->id < 1) return $generic;

        // Don't send a reset for an account that could not log in afterwards
        // anyway. Indistinguishable from success to the caller.
        if ($this->account_login_denied($userData)) return $generic;

        $userid = (int) $userData->id;

        // Only the hash is stored, so read access to the users table does not
        // yield usable reset links. emoncms.org validates against the same hash.
        $token = generate_secure_key(32);
        $token_hash = hash('sha256', $token);
        $expires = time() + $this->password_reset_window;

        $stmt = $this->emoncms_mysqli->prepare("UPDATE users SET password_reset_hash=?, password_reset_expires=? WHERE id=?");
        if (!$stmt) {
            // Columns missing means the emoncms schema update has not been run
            return array('success'=>false, 'message'=>"Password reset is unavailable, please contact support");
        }
        $stmt->bind_param("sii", $token_hash, $expires, $userid);
        $stmt->execute();
        $stmt->close();

        $reset_link = rtrim($this->host,'/')."/user/passwordreset-confirm?key=".$token;
        $minutes = (int) round($this->password_reset_window / 60);

        require_once "Lib/email.php";
        $email_class = new Email();
        $email_class->send(array(
            "to" => array(array("email" => $emailto)),
            "subject" => "HeatpumpMonitor.org password reset",
            "text" => "A password reset was requested for your HeatpumpMonitor account.\n\n"
                     ."To choose a new password open this link:\n$reset_link\n\n"
                     ."The link can be used once and expires in $minutes minutes. "
                     ."If you did not request this you can ignore this email, your password has not been changed.",
            "html" => "<p>A password reset was requested for your HeatpumpMonitor account.</p>"
                     ."<p>To choose a new password follow this link: <a href=\"$reset_link\">$reset_link</a></p>"
                     ."<p>The link can be used once and expires in $minutes minutes. "
                     ."If you did not request this you can ignore this email, your password has not been changed.</p>"
        ));

        return $generic;
    }

    /**
     * Whether an account is barred from being given a session.
     *
     * The single gate for every path that starts one: the login form, the
     * remember me cookie in create_session(), and passwordreset(), which uses it
     * to avoid issuing a reset that could not be used afterwards. Keeping the
     * rules in one place is the point, as the cookie path previously skipped
     * them entirely.
     *
     * Returns the reason as a string so the caller can pick the right message,
     * or false when the account may log in. An account marked for termination
     * only counts as archived once the 4 week grace period after `term` has
     * elapsed; inside that window it is still a live account.
     *
     * @param object $userData row from the users table
     * @return string|false 'archived', 'disabled', or false
     */
    private function account_login_denied($userData)
    {
        if (isset($userData->term) && $userData->term > 0) {
            $d = new DateTime();
            $d->setTimestamp($userData->term);
            $d->modify("+4 weeks");
            if ((time() - $d->getTimestamp()) > 0) return 'archived';
        }
        if (isset($userData->archived) && $userData->archived == 1) return 'archived';
        // Read only access is not currently supported here, so a login needs
        // access 2. 0 is no access at all, 1 is read only.
        if (!isset($userData->access) || $userData->access < 2) return 'disabled';
        return false;
    }

    /**
     * Fixed window rate limit, keyed on the requesting IP.
     *
     * This site talks to the emoncms database directly rather than through
     * emoncms.org's HTTP endpoints, so emoncms's own limiter never sees these
     * requests. Without this, the shared reset flow would be rate limited on
     * one site and wide open on the other.
     *
     * @param string $action
     * @param int    $limit   max attempts in the window
     * @param int    $window  seconds
     * @return bool
     */
    private function is_rate_limited($action, $limit, $window)
    {
        if (!$this->redis) return false;

        $ip = get_client_ip_env();
        if (empty($ip)) return false;

        $key = "hpmon:ratelimit:{$action}:".$ip;
        $attempts = $this->redis->incr($key);
        if ($attempts === 1) {
            $this->redis->expire($key, $window);
        }
        return $attempts > $limit;
    }
    public function change_password($userid, $old, $new)
    {
        $userid = (int) $userid;

        if (strlen($old) < 4 || strlen($old) > 250) return array('success'=>false, 'message'=>"Password length error");
        if (strlen($new) < 4 || strlen($new) > 250) return array('success'=>false, 'message'=>"Password length error");

        // 1) check that old password is correct
        $result = $this->emoncms_mysqli->query("SELECT password, salt FROM users WHERE id = '$userid'");
        $row = $result->fetch_object();

        if (verify_password($old, $row->password, $row->salt))
        {
            // 2) Save the new password in the configured algorithm, bcrypt or
            // argon2id, and clear any legacy salt with it. Both sites read this
            // row, so it has to be written the way Lib/password.php defines.
            $password = hash_password($new);
            $salt = '';

            $stmt = $this->emoncms_mysqli->prepare("UPDATE users SET password = ?, salt = ? WHERE id = ?");
            $stmt->bind_param("ssi", $password, $salt, $userid);
            $stmt->execute();
            $stmt->close();

            // Changing the password is only meaningful if it also ends logins
            // held with the old one. Covers the cookies on both sites, since
            // both store their tokens in the same table.
            $this->rememberme->clearAllTriplets($userid);

            // An outstanding reset link would otherwise stay redeemable against
            // the password just set
            $this->clear_password_reset_token($userid);

            return array('success'=>true, 'message'=>"Password updated successfully");
        }
        else
        {
            $this->log->error("change_password: old password incorrect userid:$userid ip:".get_client_ip_env());
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
        $result = $this->emoncms_mysqli->query("SELECT COUNT(*) AS sub_account_count FROM accounts WHERE adminuser='$admin_userid'");
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
                $account_id = (int) $account_id;
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
        $result = $this->emoncms_mysqli->query("SELECT linkeduser FROM accounts WHERE adminuser='$admin_userid'");
        while ($row = $result->fetch_object()) {
            $accounts[] = (int) $row->linkeduser;
        }

        return $accounts;
    }

    // Get list of sub accounts with usernames, access levels etc
    public function get_sub_accounts_with_system_details($userid) {
        $userid = (int) $userid;

        // Get sub accounts from local accounts table
        $result = $this->emoncms_mysqli->query("SELECT u.id, u.username, u.email, u.access, u.lastactive FROM accounts a JOIN users u ON a.linkeduser = u.id WHERE a.adminuser = '$userid' ORDER BY u.id ASC");
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
        $result = $this->emoncms_mysqli->query("SELECT COUNT(*) as count FROM accounts WHERE adminuser='$admin_userid' AND linkeduser='$sub_account_userid'");
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
    // Only reachable from update_sub_account(), where an account admin sets a
    // password for a sub account they own.
    public function change_password_no_check($userid, $new) {
        $userid = (int) $userid;

        if (strlen($new) < 8 || strlen($new) > 250) return array('success'=>false, 'message'=>"Password length error");

        // Save the new password in the configured algorithm and clear any legacy
        // salt. This used to write a single sha256 round, which quietly moved a
        // sub account back off bcrypt every time its admin set a password.
        $password = hash_password($new);
        $salt = '';

        $stmt = $this->emoncms_mysqli->prepare("UPDATE users SET password = ?, salt = ? WHERE id = ?");
        $stmt->bind_param("ssi", $password, $salt, $userid);
        $stmt->execute();
        $stmt->close();

        // The sub account holder's existing logins were held with a password
        // they no longer have
        $this->rememberme->clearAllTriplets($userid);
        $this->clear_password_reset_token($userid);

        $this->log->info("change_password_no_check: password set for sub account userid:$userid");

        return array('success'=>true, 'message'=>"Password updated successfully");
    }

}
