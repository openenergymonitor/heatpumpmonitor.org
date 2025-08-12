<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function heatpump_controller() {

    global $session, $route, $user, $mysqli, $settings, $system, $system_stats;

    // List of heat pump models
    if ($route->action == "") {
        $mode = $session['admin'] ? "admin" : "view";
        return view("Modules/heatpump/views/heatpump_list.php", array(
            "mode" => $mode
        ));
    }

    // View heat pump data sheet page
    if ($route->action == "view") {
        $id = (int) get("id", true);
        $mode = $session['admin'] ? "admin" : "view";
        return view("Modules/heatpump/views/heatpump_view.php", array(
            "id" => $id,
            "mode" => $mode,
            "userid" => $session['userid'] ?? 0
        ));
    }

    // List of unmatched heat pump models
    if ($route->action == "unmatched" && $session['admin']) {
        $mode = $session['admin'] ? "admin" : "view";
        return view("Modules/heatpump/views/heatpump_unmatched.php", array(
            "mode" => $mode
        ));
    }

    // API actions

    require "Modules/manufacturer/manufacturer_model.php";
    $manufacturer_model = new Manufacturer($mysqli);

    require "Modules/heatpump/heatpump_model.php";
    $heatpump_model = new Heatpump($mysqli, $manufacturer_model);

    require "Modules/heatpump/heatpump_test_model.php";
    $heatpump_tests = new HeatpumpTests($mysqli, $user, $heatpump_model);


    if ($route->action == "list") {
        $route->format = "json";
        return $heatpump_model->get_list();
    }

    if ($route->action == "get") {
        $route->format = "json";
        return $heatpump_model->get(get("id"));
    }

    if ($route->action == "add" && $session['admin']) {
        $route->format = "json";
        // Get parameters from POST request
        $manufacturer_id = (int) post("manufacturer_id", true);
        $model = trim(post("model", true));
        $refrigerant = trim(post("refrigerant", true));
        $type = trim(post("type", true));
        $capacity = (float) post("capacity", true);
        // Add heat pump model
        return $heatpump_model->add($manufacturer_id, $model, $refrigerant, $type, $capacity);
    }

    if ($route->action == "update" && $session['admin']) {
        $route->format = "json";
        // Get parameters from POST request
        $id = (int) post("id", true);
        $manufacturer_id = (int) post("manufacturer_id", true);
        $model = trim(post("model", true));
        $refrigerant = trim(post("refrigerant", true));
        $type = trim(post("type", true));
        $capacity = (float) post("capacity", true);
        // Update heat pump model
        return $heatpump_model->update($id, $manufacturer_id, $model, $refrigerant, $type, $capacity);
    }

    if ($route->action == "delete" && $session['admin']) {
        $route->format = "json";
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            return $heatpump_model->delete($id);
        } else {
            return array("error" => "Missing heatpump ID for deletion");
        }
    }

    if ($route->action == "populate" && $session['admin']) {
        $route->format = "json";
        return $heatpump_model->populate_table();
    }

    if ($route->action == "unmatched_list" && $session['admin']) {
        $route->format = "json";
        return $heatpump_model->get_unmatched_list();
    }

    if ($route->action == "max_cap_test") {

        if ($route->subaction == "list") {
            $route->format = "json";
            if (!isset($_GET['id'])) {
                return array("error" => "Missing model_id parameter");
            }
            $model_id = (int)$_GET['id'];
            return $heatpump_tests->get_max_cap_tests($model_id);
        }

        if ($route->subaction == "load" && $session['userid']>0) {
            $route->format = "json";

            if (!isset($_GET['id'])) {
                return array("error" => "Missing model_id parameter");
            }
            $model_id = (int)$_GET['id'];

            if (!isset($_POST['url'])) {
                return array("error" => "Missing url parameter");
            }
            $url = $_POST['url'];
            
            $url_parts = parse_url($url);
            if (!isset($url_parts['query'])) {
                return array("error" => "Missing query parameters in URL");
            }

            parse_str($url_parts['query'], $url_args);
            if (!isset($url_args['start']) || !isset($url_args['end'])) {
                return array("error" => "Missing start or end parameters in URL");
            }

            $start = (int)$url_args['start'];
            $end = (int)$url_args['end'];

            $stats = json_decode($system_stats->load_from_url(post("url"), $start, $end, 'getstats'));

            // convert to dashboard URL in order to get system id from system_meta table
            $dashboard_url = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'];

            // remove all query parameters except 'name' and 'readkey' preserving the original order
            $query_params = [];
            if (isset($url_args['name'])) {
                $query_params[] = 'name=' . urlencode($url_args['name']);
            }
            if (isset($url_args['readkey'])) {
                $query_params[] = 'readkey=' . $url_args['readkey'];
            }
            if (!empty($query_params)) {
                $dashboard_url .= '?' . implode('&', $query_params);
            }

            // Get system id with this URL
            $stmt = $mysqli->prepare("SELECT id FROM system_meta WHERE url = ?");
            $stmt->bind_param("s", $dashboard_url);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $system_id = $row['id'];
            } else {
                return array("error" => "System not found for the provided URL");
            }

            $date = new DateTime();
            $date->setTimezone(new DateTimeZone('Europe/London'));
            $date->setTimestamp($stats->start);
            $datestr = $date->format('jS M Y H:i');

            // Estimate flow rate from flow - return 
            $dt = $stats->stats->combined->flowT_mean - $stats->stats->combined->returnT_mean;
            $flowrate = 60 * ($stats->stats->combined->heat_mean / (4150 * $dt));

            $test_object = array(
                'system_id' => $system_id,
                'test_url' => $url,
                'start' => $stats->start,
                'end' => $stats->end,
                'date' => $datestr,
                'data_length' => $stats->stats->combined->data_length,
                'flowT' => $stats->stats->combined->flowT_mean,
                'outsideT' => $stats->stats->combined->outsideT_mean,
                'elec' => $stats->stats->combined->elec_mean,
                'heat' => $stats->stats->combined->heat_mean,
                'cop' => $stats->stats->combined->cop,
                'flowrate' => $flowrate
            );

            $userid = $session['userid'] ?? 0; // Use session user ID or 0 if not logged in
            $review_status = 0; // Default review status
            $review_comment = ''; // Default empty review comment

            $result = $heatpump_tests->add_max_cap_test($userid, $model_id, $test_object, $review_status, $review_comment);
            if (!$result['success']) {
                return array("error" => $result['error']);
            }

            return $test_object;
        }

        // Delete max capacity test
        if ($route->subaction == "delete" && $session['userid'] > 0) {
            $route->format = "json";
            $id = (int) get("id", true);
            return $heatpump_tests->delete_max_cap_test($session['userid'], $id);
        }

        // Update test status (admin only)
        if ($route->subaction == "update_status" && $session['admin']) {
            $route->format = "json";
            $id = (int) post("id", true);
            $status = (int) post("status", true);
            $message = trim(post("message", true));

            return $heatpump_tests->update_status($id, $status, $message);
        }
    }
}