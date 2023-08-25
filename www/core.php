<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

/*

    All Emoncms code is released under the GNU Affero General Public License.
    See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org

*/

// no direct access
// defined('EMONCMS_EXEC') or die('Restricted access');

function is_https() {
    // Detect if we are running HTTPS or proxied HTTPS
    if (server('HTTPS') == 'on') {
        // Web server is running native HTTPS
        return true;
    } elseif (server('HTTP_X_FORWARDED_PROTO') == "https") {
        // Web server is running behind a proxy which is running HTTPS
        return true;
    } elseif (request_header('HTTP_X_FORWARDED_PROTO') == "https") {
        return true;
    }
    return false;
}

function get_application_path($manual_domain=false)
{
    if (is_https()) {
        $proto = "https";
    } else {
        $proto = "http";
    }
    
    if ($manual_domain) {
        return "$proto://".$manual_domain."/";
    }

    if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
        $path = dirname("$proto://" . server('HTTP_X_FORWARDED_HOST') . server('SCRIPT_NAME')) . "/";
    } else {
        $path = dirname("$proto://" . server('HTTP_HOST') . server('SCRIPT_NAME')) . "/";
    }

    return $path;
}

function view($filepath, array $args = array())
{
    global $path;
    $args['path'] = $path;
    $content = '';
    if (file_exists($filepath)) {
        extract($args);
        ob_start();
        include "$filepath";
        $content = ob_get_clean();
    }
    return $content;
}
/**
 * strip slashes from GET values or null if not set
 *
 * @param string $index name of $_GET item
 *
 **/
function get($index,$error_if_missing=false,$default=null)
{
    $val = $default;
    if (isset($_GET[$index])) {
        $val = rawurldecode($_GET[$index]);
    } else if ($error_if_missing) {
        header('Content-Type: text/plain');
        die("missing $index parameter");
    }
    if(!is_null($val)){
    $val = stripslashes($val);
	}
    return $val;
}
/**
 * strip slashes from POST values or null if not set
 *
 * @param string $index name of $_POST item
 *
 **/
function post($index,$error_if_missing=false,$default=null)
{
    $val = $default;
    if (isset($_POST[$index])) {
        // PHP automatically converts POST names with brackets `field[]` to type array
        if (!is_array($_POST[$index])) {
            $val = rawurldecode($_POST[$index]); // does not decode the plus symbol into spaces
        } else {
            // sanitize the array values
            $SANTIZED_POST  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
            if (!empty($SANTIZED_POST[$index])) {
                $val = $SANTIZED_POST[$index];
            }
        }
    } else if ($error_if_missing) {
        header('Content-Type: text/plain');
        die("missing $index parameter");
    }
    
    if (is_array($val)) {
        $val = array_map("stripslashes", $val);
    }	
	if(!is_null($val)){
        $val = stripslashes($val);
	}
    return $val;
}
/**
 * strip slashes from POST or GET values or null if not set
 *
 * @param string $index name of $_POST or $_GET item
 *
 **/
function prop($index,$error_if_missing=false,$default=null)
{
    $val = $default;
    if (isset($_GET[$index])) {
        $val = $_GET[$index];
    }
    else if (isset($_POST[$index])) {
        $val = $_POST[$index];
    }
    else if ($error_if_missing) {
        header('Content-Type: text/plain');
        die("missing $index parameter");
    }
    
    if (is_array($val)) {
        $val = array_map("stripslashes", $val);
    } else {
        $val = stripslashes($val);
    }
    return $val;
}

function request_header($index)
{
   $val = null;
   $headers = apache_request_headers();
   if (isset($headers[$index])) {
        $val = $headers[$index];
  }
  return $val;
}


function server($index)
{
    $val = null;
    if (isset($_SERVER[$index])) {
        $val = $_SERVER[$index];
    }
    return $val;
}

function delete($index)
{
    parse_str(file_get_contents("php://input"), $_DELETE);//create array with posted (DELETE) method) values
    $val = null;
    if (isset($_DELETE[$index])) {
        $val = $_DELETE[$index];
    }
    
    if (is_array($val)) {
        $val = array_map("stripslashes", $val);
    } else {
        $val = stripslashes($val);
    }
    return $val;
}
function put($index)
{
    parse_str(file_get_contents("php://input"), $_PUT);//create array with posted (PUT method) values
    $val = null;
    if (isset($_PUT[$index])) {
        $val = $_PUT[$index];
    }
    
    if (is_array($val)) {
        $val = array_map("stripslashes", $val);
    } else {
        $val = stripslashes($val);
    };
    return $val;
}

function http_request($method, $url, $data)
{
    $options = array();
    
    if ($method=="GET") {
        $urlencoded = http_build_query($data);
        $url = "$url?$urlencoded";
    } elseif ($method=="POST") {
        $options[CURLOPT_POST] = 1;
        $options[CURLOPT_POSTFIELDS] = $data;
    }
    
    $options[CURLOPT_URL] = $url;
    $options[CURLOPT_RETURNTRANSFER] = 1;
    $options[CURLOPT_CONNECTTIMEOUT] = 2;
    $options[CURLOPT_TIMEOUT] = 5;

    $curl = curl_init();
    curl_setopt_array($curl, $options);
    $resp = curl_exec($curl);
    curl_close($curl);
    return $resp;
}

function emoncms_error($message)
{
    return array("success"=>false, "message"=>$message);
}

// ---------------------------------------------------------------------------------------------------------
/**
 * return ip address of requesting machine
 * the ip address can be stored in different variables by the system.
 * which variable name may change dependant on different system setups.
 * this function *should return an acceptible value in most cases
 * @todo: more testing on different hardware/opperating systems/proxy servers etc.
 *
 * @return string
 */
function get_client_ip_env()
{
    $ipaddress = filter_var(getenv('REMOTE_ADDR'), FILTER_VALIDATE_IP);
    if (empty($ipaddress)) {
        $ipaddress = '';
    }
    return $ipaddress;
}

// ---------------------------------------------------------------------------------------------------------
// Generate secure key
// ---------------------------------------------------------------------------------------------------------
function generate_secure_key($length) {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($length));
    } else {
        return bin2hex(openssl_random_pseudo_bytes($length));
    }
}
