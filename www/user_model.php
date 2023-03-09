<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class User
{

    public function __construct()
    {
    
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
        
        if (PHP_VERSION_ID>=70300) {
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
        
        $session = array();
        
        if (isset($_SESSION['admin'])) $session['admin'] = $_SESSION['admin']; else $session['admin'] = 0;
        if (isset($_SESSION['userid'])) $session['userid'] = $_SESSION['userid']; else $session['userid'] = 0;
        if (isset($_SESSION['username'])) $session['username'] = $_SESSION['username']; else $session['username'] = '';
        
        return $session;
    }
    
    
    public function login($username, $password)
    {
        if (!$username || !$password) return array('success'=>false, 'message'=>_("Username or password empty"));


        if (!$result = http_request("POST", "https://emoncms.org/user/auth.json", array("username"=>$username,"password"=>$password))) {
            return array('success'=>false, 'message'=>_("Login error"));  
        }
        
        $result = json_decode($result);

        if (isset($result->success) && $result->success===true) {
            session_regenerate_id();
            $userid = (int) $result->userid;
            
            $_SESSION['userid'] = $userid;
            $_SESSION['username'] = $username;
            $_SESSION['apikey_read'] = $result->apikey_read;
            $_SESSION['apikey_write'] = $result->apikey_write;
            
            // $profile = json_decode(http_request("POST","https://emoncms.org/user/get.json",array("id"=>$userid, "apikey"=>$result->apikey_write)));
            // echo json_encode($profile);
            
            return array('success'=>true, 'message'=>_("Login successful"));
        } else {
            return $result;
        }
    }
    
    public function logout()
    {
        session_unset();
        session_destroy();
    }
}
