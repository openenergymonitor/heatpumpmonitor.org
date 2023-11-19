<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class RememberMe
{
    private $mysqli;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }

    // Generate tokens
    public function generate_tokens() {
        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
        return [$selector, $validator, $selector . ':' . $validator];
    }

    // Parse token
    public function parse_token($token) {
        $parts = explode(':', $token);
    
        if ($parts && count($parts) == 2) {
            return [$parts[0], $parts[1]];
        }
        return false;
    }

    // Insert user token
    public function insert_user_token($userid, $selector, $hash_validator, $expires) {
        $stmt = $this->mysqli->prepare("INSERT INTO user_sessions (userid, selector, hash_validator, expires) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $userid, $selector, $hash_validator, $expires);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    // Find user token by selector
    public function find_user_token_by_selector($selector) {
        $now = time();
        $stmt = $this->mysqli->prepare("SELECT * FROM user_sessions WHERE selector = ? AND expires >= ?");
        $stmt->bind_param("si", $selector, $now);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        return $result->fetch_object();
    }

    // Delete user token
    public function delete_user_token($userid) {
        $stmt = $this->mysqli->prepare("DELETE FROM user_sessions WHERE userid = ?");
        $stmt->bind_param("i", $userid);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function remember_me($userid, $day = 30)
    {
        [$selector, $validator, $token] = $this->generate_tokens();
    
        // remove all existing token associated with the user id
        $this->delete_user_token($userid);
    
        // set expiration date
        $expiry = time() + (3600 * 24 * $day);
    
        // insert a token to the database
        $hash_validator = password_hash($validator, PASSWORD_DEFAULT);
    
        if ($this->insert_user_token($userid, $selector, $hash_validator, $expiry)) {
            setcookie('remember_me', $token, $expiry);
        }
    }

    // Forget me
    public function logout($userid) {

        // delete the user token
        $this->delete_user_token($userid);

        // remove the remember_me cookie
        if (isset($_COOKIE['remember_me'])) {
            unset($_COOKIE['remember_me']);
            setcookie('remember_me', null, -1);
        }
    }

    // Is logged in?
    public function login_from_cookie() {

        // checkk if the remember_me cookie exist
        if (!isset($_COOKIE['remember_me'])) {
            return false;
        }

        // check the remember_me in cookie
        $token = filter_input(INPUT_COOKIE, 'remember_me', FILTER_SANITIZE_SPECIAL_CHARS);

        // parse the token to get the selector and validator
        if (!$result = $this->parse_token($token)) {
            return false;
        }
        [$selector, $validator] = $result;

        // check the selector
        $user = $this->find_user_token_by_selector($selector);
        if (!$user) {
            return false;
        }

        // check the validator
        if (password_verify($validator, $user->hash_validator)) {
            return $user->userid;
        }
        return false;
    }
}