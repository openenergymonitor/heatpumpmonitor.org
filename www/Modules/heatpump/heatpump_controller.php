<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function heatpump_controller() {

    global $session, $route, $user, $mysqli, $settings, $system, $system_stats, $path;

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
        
        // Load heatpump model to get its details for winter stats
        require "Modules/manufacturer/manufacturer_model.php";
        $manufacturer_model = new Manufacturer($mysqli);
        require "Modules/heatpump/heatpump_model.php";
        $heatpump_model = new Heatpump($mysqli, $manufacturer_model);
        $heatpump = $heatpump_model->get($id, false);
        
        // Get winter stats for this heat pump model
        $winter_stats = null;
        if ($heatpump && !isset($heatpump['success'])) {
            $winter_stats = $system_stats->get_winter_stats_for_heatpump(
                $heatpump['manufacturer_name'],
                $heatpump['name'],
                $heatpump['refrigerant'],
                $heatpump['capacity']
            );
        }
        
        return view("Modules/heatpump/views/heatpump_view.php", array(
            "id" => $id,
            "mode" => $mode,
            "userid" => $session['userid'] ?? 0,
            "winter_stats" => $winter_stats
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
        $min_flowrate = post("min_flowrate", false);
        $max_flowrate = post("max_flowrate", false);
        $max_current = post("max_current", false);
        // Add heat pump model
        return $heatpump_model->add($manufacturer_id, $model, $refrigerant, $type, $capacity, $min_flowrate, $max_flowrate, $max_current);
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
        $min_flowrate = post("min_flowrate", false);
        $max_flowrate = post("max_flowrate", false);
        $max_current = post("max_current", false);
        // Update heat pump model
        return $heatpump_model->update($id, $manufacturer_id, $model, $refrigerant, $type, $capacity, $min_flowrate, $max_flowrate, $max_current);
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
    
    // Upload image
    if ($route->action == "upload-image" && $session['admin']) {
        $route->format = "json";
        if (!isset($_FILES['image'])) {
            return array("success" => false, "message" => "No image uploaded");
        }
        if (!isset($_POST['heatpump_id'])) {
            return array("success" => false, "message" => "Heat pump ID required");
        }
        $heatpump_id = (int) $_POST['heatpump_id'];
        return $heatpump_model->upload_image($heatpump_id, $_FILES['image']);
    }
    
    // Delete image
    if ($route->action == "delete-image" && $session['admin']) {
        $route->format = "json";
        if (!isset($_POST['heatpump_id'])) {
            return array("success" => false, "message" => "Heat pump ID required");
        }
        $heatpump_id = (int) $_POST['heatpump_id'];
        return $heatpump_model->delete_image($heatpump_id);
    }

    if ($route->action == "populate" && $session['admin']) {
        $route->format = "json";
        return $heatpump_model->populate_table();
    }

    if ($route->action == "unmatched_list" && $session['admin']) {
        $route->format = "json";
        return $heatpump_model->get_unmatched_list();
    }

    if ($route->action == "max_cap_test" || $route->action == "min_cap_test") {
        $test_type = "max";
        if ($route->action == "min_cap_test") {
            $test_type = "min";
        }

        if ($route->subaction == "list") {
            $route->format = "json";
            if (!isset($_GET['id'])) {
                return array("error" => "Missing model_id parameter");
            }
            $model_id = (int)$_GET['id'];
            return $heatpump_tests->get_cap_tests($test_type,$model_id);
        }

        if ($route->subaction == "load" && $session['userid']>0) {
            $route->format = "json";

            // Is this a new test or an update to an existing test?
            // Must be admin to update existing test
            $test_id = false;
            if (isset($_GET['test_id']) && $session['admin']) {
                $test_id = (int)$_GET['test_id'];
            }

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
            // id
            if (!isset($url_args['id'])) {
                return array("error" => "Missing id parameter in URL");
            }
            // start
            if (!isset($url_args['start'])) {
                return array("error" => "Missing start parameter in URL");
            }
            // end
            if (!isset($url_args['end'])) {
                return array("error" => "Missing end parameter in URL");
            }

            $system_id = (int)$url_args['id'];
            $start = (int)$url_args['start'];
            $end = (int)$url_args['end'];

            // Verify system exists
            $stmt = $mysqli->prepare("SELECT 1 FROM system_meta WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $system_id);
            $stmt->execute();
            if (!$stmt->get_result()->num_rows) {
                $stmt->close();
                return array("error" => "System not found");
            }
            $stmt->close();

            $stats = json_decode(file_get_contents($path."dashboard/getstats?id=$system_id&start=$start&end=$end"));
            if ($stats === null) {
                return array("error" => "Failed to load or parse stats from URL");
            }
            if (!isset($stats->start)) {
                return array("error" => "Invalid stats data: missing start time");
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
                'test_url' => "",
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

            $result = $heatpump_tests->add_cap_test($test_type,$userid, $model_id, $test_object, $review_status, $review_comment, $test_id);
            if (!$result['success']) {
                return array("error" => $result['error']);
            }

            return $test_object;
        }

        // Delete min/max capacity test
        if ($route->subaction == "delete" && $session['userid'] > 0) {
            $route->format = "json";
            $id = (int) get("id", true);
            return $heatpump_tests->delete_cap_test($test_type,$session['userid'], $id);
        }

        // Update test status (admin only)
        if ($route->subaction == "update_status" && $session['admin']) {
            $route->format = "json";
            $id = (int) post("id", true);
            $status = (int) post("status", true);
            $message = trim(post("message", true));

            return $heatpump_tests->update_status($test_type, $id, $status, $message);
        }
    }
}