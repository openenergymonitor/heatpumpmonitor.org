<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class HeatpumpTests
{
    private $mysqli;
    private $user;
    private $manufacturer_model;
    private $heatpump_model;

    public function __construct($mysqli, $user, $heatpump_model = null)
    {
        $this->mysqli = $mysqli;
        $this->user = $user;
        $this->heatpump_model = $heatpump_model;
    }

    public function get_cap_tests($test_type, $model_id)
    {
        // Validate test type
        if ($test_type !== 'max' && $test_type !== 'min') {
            return array("error" => "Invalid test type");
        }

        $model_id = (int)$model_id;
        $result = $this->mysqli->query("SELECT * FROM heatpump_{$test_type}_cap_test WHERE model_id = $model_id ORDER BY heat DESC");
        $tests = array();
        while ($row = $result->fetch_object()) {

            $system_id = (int)$row->system_id;
            // Get heatpump model from system_meta
            $result2 = $this->mysqli->query("SELECT hp_model,hp_output,refrigerant FROM system_meta WHERE id = $system_id");
            if ($system = $result2->fetch_object()) {
                $row->system_hp_model = $system->hp_model;
                $row->system_hp_output = $system->hp_output;
                $row->system_refrigerant = $system->refrigerant;
            } else {
                $row->system_hp_model = null;
                $row->system_hp_output = null;
                $row->system_refrigerant = null;
            }


            $tests[] = $row;
        }
        return $tests;
    }
    public function add_cap_test($test_type, $userid, $model_id, $data, $review_status = 0, $review_comment = '') {

        // Validate test type
        if ($test_type !== 'max' && $test_type !== 'min') {
            return array("success" => false, "error" => "Invalid test type");
        }
        // These checks should really use external validation functions

        // Is this a valid user id?
        $userid = (int)$userid;
        if (!$this->user->userid_exists($userid)) {
            return array("success" => false, "error" => "Invalid user ID");
        }

        // Is this a valid model ID?
        $model_id = (int)$model_id;
        $result = $this->mysqli->query("SELECT id FROM heatpump_model WHERE id = $model_id");
        if ($result->num_rows === 0) return array("success" => false, "error" => "Invalid model ID");

        // Is this a valid system ID?
        $system_id = (int)$data['system_id'];
        $result = $this->mysqli->query("SELECT id FROM system_meta WHERE id = $system_id");
        if ($result->num_rows === 0) return array("success" => false, "error" => "Invalid system ID");

        // ------------------------------------------------------------

        $test_url = $data['test_url'];
        $start = (int)$data['start'];
        $end = (int)$data['end'];
        $date = $data['date'];
        $data_length = (int)$data['data_length'];
        $flowT = (float)$data['flowT'];
        $outsideT = (float)$data['outsideT'];
        $elec = (float)$data['elec'];
        $heat = (float)$data['heat'];
        $cop = (float)$data['cop'];
        $flowrate = (float) $data['flowrate'];

        $review_status = (int)$review_status;
        $created = date('Y-m-d H:i:s'); // Current timestamp

        $stmt = $this->mysqli->prepare("INSERT INTO heatpump_{$test_type}_cap_test (model_id, system_id, test_url, start, end, date, data_length, flowT, outsideT, elec, heat, cop, flowrate, userid, review_status, review_comment, created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisiisiddddddiiss", $model_id, $system_id, $test_url, $start, $end, $date, $data_length, $flowT, $outsideT, $elec, $heat, $cop, $flowrate, $userid, $review_status, $review_comment, $created);
        if (!$stmt->execute()) {
            return array("success" => false, "error" => "Failed to add {$test_type} capacity test: " . $stmt->error);
        }

        // Only send notification if user submitting is not admin
        if (!$this->user->is_admin($userid)) {
            $this->send_test_notification($test_type, $this->mysqli->insert_id);
        }
        
        return array("success" => true, "id" => $this->mysqli->insert_id);
    }

    public function delete_cap_test($test_type, $userid, $id) {

        // Validate test type
        if ($test_type !== 'max' && $test_type !== 'min') {
            return array("error" => "Invalid test type");
        }

        // Validate user ID
        $userid = (int)$userid;
        if (!$this->user->userid_exists($userid)) {
            return array("error" => "Invalid user ID");
        }
        $is_admin = $this->user->is_admin($userid);
        $is_test_owner = $this->test_owner($test_type, $userid, $id);

        // if not test owner or admin, return error
        if (!$is_test_owner && !$is_admin) {
            return array("error" => "You do not have permission to delete this test");
        }

        $id = (int)$id;
        $stmt = $this->mysqli->prepare("DELETE FROM heatpump_{$test_type}_cap_test WHERE id = ?");
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            return array("error" => "Failed to delete {$test_type} capacity test: " . $stmt->error);
        }
        
        return array("success" => true);
    }

    public function test_owner($test_type, $userid, $id) {
        // Validate test type
        if ($test_type !== 'max' && $test_type !== 'min') {
            return false; // Invalid test type
        }
        
        // Validate user ID
        $userid = (int)$userid;
        $id = (int)$id;

        $result = $this->mysqli->query("SELECT userid FROM heatpump_{$test_type}_cap_test WHERE id = $id");
        if ($result->num_rows === 0) {
            return false; // Test not found
        }

        $row = $result->fetch_object();
        return $row->userid == $userid; // Check if the user is the owner of the test
    }

    /*
     * Update status and message for a heatpump test (admin only)
     * 
     * @param int $id
     * @param int $status
     * @param string $message
     * @return array
     */
    public function update_status($test_type, $id, $status, $message = '') {
        // Validate test type
        if ($test_type !== 'max' && $test_type !== 'min') {
            return array("success" => false, "message" => "Invalid test type");
        }

        // Validate inputs
        $id = (int) $id;
        $status = (int) $status;
        $message = trim($message);
        
        // Check if test exists
        $check_stmt = $this->mysqli->prepare("SELECT id FROM heatpump_{$test_type}_cap_test WHERE id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows === 0) {
            $check_stmt->close();
            return array("success" => false, "message" => "Test not found");
        }
        $check_stmt->close();
        
        // Update the review status and comment
        $stmt = $this->mysqli->prepare("UPDATE heatpump_{$test_type}_cap_test SET review_status = ?, review_comment = ? WHERE id = ?");
        $stmt->bind_param("isi", $status, $message, $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            return array("success" => true, "message" => "Test status updated successfully");
        } else {
            $error = $stmt->error;
            $stmt->close();
            return array("success" => false, "message" => "Failed to update test status: " . $error);
        }
    }


    public function send_test_notification($test_type, $test_id) {
        // Validate test type
        if ($test_type !== 'max' && $test_type !== 'min') {
            return; // Invalid test type
        }
        
        $test_id = (int) $test_id;
    
        // Get test data
        $result = $this->mysqli->query("SELECT * FROM heatpump_{$test_type}_cap_test WHERE id = $test_id");
        if (!$test = $result->fetch_object()) {
            return; // Test not found
        }
        $model_id = (int)$test->model_id;

        // Get username
        if (!$user = $this->user->get($test->userid)) {
            return; // User not found
        }
        $test->user_name = $user->name;
        $test->username = $user->username;

        // Get model name
        if (!$model = $this->heatpump_model->get($model_id)) {
            return; // Model not found
        }
        $test->model = $model['name'];
        $test->manufacturer = $model['manufacturer_name'];

        // Get admin users email addresses
        $admin_result = $this->mysqli->query("SELECT email FROM users WHERE admin=1");
        if ($admin_result === false) {
            return; // Query failed
        }
        
        $emails = array();
        while ($row = $admin_result->fetch_object()) {
            $emails[] = array("email"=>$row->email);
        }

        // Create email content
        $model_name = $test->manufacturer . " " . $test->model;
        $model_url = "https://dev.heatpumpmonitor.org/heatpump/view?id=" . $test->model_id;

        $subject = "New {$test_type} capacity test submitted for $model_name by {$test->user_name}";

        $text = "A new {$test_type} capacity test has been submitted for $model_name by {$test->user_name} ({$test->username}). Please review at: $model_url";

        $html = "<h3>New {$test_type} Capacity Test Submitted</h3>";
        $html .= "<p><strong>Heat Pump Model:</strong> <a href=\"$model_url\">$model_name</a></p>";
        $html .= "<p><strong>Submitted by:</strong> {$test->user_name} ({$test->username})</p>";
        $html .= "<p><strong>Test Date:</strong> {$test->date}</p>";
        
        $html .= "<h4>Test Summary:</h4>";
        $html .= "<ul>";
        $html .= "<li><strong>Outside Temperature:</strong> {$test->outsideT}°C</li>";
        $html .= "<li><strong>Flow Temperature:</strong> {$test->flowT}°C</li>";
        $html .= "<li><strong>Heat Output:</strong> {$test->heat} kW</li>";
        $html .= "<li><strong>Electrical Input:</strong> {$test->elec} kW</li>";
        $html .= "<li><strong>COP:</strong> {$test->cop}</li>";
        $html .= "<li><strong>Flow Rate:</strong> {$test->flowrate} L/min</li>";
        $html .= "<li><strong>Data Length:</strong> {$test->data_length} minutes</li>";
        $html .= "</ul>";
        
        if (!empty($test->test_url)) {
            $html .= "<p><strong>Test Data URL:</strong> <a href=\"{$test->test_url}\">{$test->test_url}</a></p>";
        }
        
        $html .= "<p><a href=\"$model_url\">View Heat Pump Model Page</a></p>";

        // Send email notification
        require_once "Lib/email.php";
        $email_class = new Email();
        $email_class->send(array(
            "to" => $emails,
            "subject" => $subject,
            "text" => $text,
            "html" => $html
        ));
    }
}