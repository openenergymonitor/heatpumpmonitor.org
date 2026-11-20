<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function dashboard_controller() {
    global $route, $session, $system, $mysqli, $user;

    // Default to public or user mode
    $readkey = "";
    $private_mode = false;
    $session_userid = $session['userid'];

    // if a read key is provided and no user session exists
    // then switch to private mode
    // A readkey is a 32 char hex emoncms apikey (the same rule the lookup
    // applies); anything else is discarded so it never reaches the view.
    if (isset($_GET['readkey']) && !$session['userid'] && preg_match('/^[a-f0-9]{32}$/', $_GET['readkey'])) {
        $readkey = $_GET['readkey'];
        $session_userid = $user->get_userid_from_apikey_read($readkey);
        $private_mode = true;
    }

    // HTML view
    if ($route->action == "") {
        $systemid = (int) $_GET['id'];
        $route->format = "html";
        $system_data = $system->get($session_userid,$systemid);
        if (is_array($system_data) && isset($system_data['success']) && $system_data['success']==false) {
            // 
        } else {
            // Measured design parameters (scripts/measurements/system_measurements.php),
            // read after the access check above
            require_once "Modules/measurement/measurement_model.php";
            $measurement = new Measurement($mysqli, false);

            // Rated flow rate range of the matched heat pump model, L/min
            $heatpump_model_id = $system->get_heatpump_model_id($systemid);
            $heatpump_flowrate = array("min" => null, "max" => null);
            if ($heatpump_model_id) {
                $stmt = $mysqli->prepare("SELECT min_flowrate, max_flowrate FROM heatpump_model WHERE id = ?");
                $stmt->bind_param("i", $heatpump_model_id);
                $stmt->execute();
                $stmt->bind_result($min_flowrate, $max_flowrate);
                if ($stmt->fetch()) $heatpump_flowrate = array("min" => $min_flowrate, "max" => $max_flowrate);
                $stmt->close();
            }

            return view("Modules/dashboard/myheatpump.php", array(
                "measurements"=>$measurement->get_system($systemid),
                "id"=>$systemid,
                "system_data"=>$system_data,
                "heatpump_model_id"=>$heatpump_model_id,
                "heatpump_flowrate"=>$heatpump_flowrate,
                "apikey"=>$readkey,
                "private_mode"=>$private_mode
            ));
        }
    }

    return false;
}