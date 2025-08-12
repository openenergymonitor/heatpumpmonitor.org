<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class HeatpumpTests
{
    private $mysqli;
    private $user;

    public function __construct($mysqli, $user)
    {
        $this->mysqli = $mysqli;
        $this->user = $user;
    }

    public function get_max_cap_tests($model_id)
    {
        $model_id = (int)$model_id;
        $result = $this->mysqli->query("SELECT * FROM heatpump_max_cap_test WHERE model_id = $model_id ORDER BY heat DESC");
        $tests = array();
        while ($row = $result->fetch_object()) {
            $tests[] = $row;
        }
        return $tests;
    }
    public function add_max_cap_test($userid, $model_id, $data, $review_status = 0, $review_comment = '') {

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

        $stmt = $this->mysqli->prepare("INSERT INTO heatpump_max_cap_test (model_id, system_id, test_url, start, end, date, data_length, flowT, outsideT, elec, heat, cop, flowrate, userid, review_status, review_comment, created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisiisiddddddiiss", $model_id, $system_id, $test_url, $start, $end, $date, $data_length, $flowT, $outsideT, $elec, $heat, $cop, $flowrate, $userid, $review_status, $review_comment, $created);
        if (!$stmt->execute()) {
            return array("success" => false, "error" => "Failed to add max capacity test: " . $stmt->error);
        }
        
        return array("success" => true, "id" => $this->mysqli->insert_id);
    }

    public function delete_max_cap_test($userid, $id) {
        // Validate user ID
        $userid = (int)$userid;
        if (!$this->user->userid_exists($userid)) {
            return array("error" => "Invalid user ID");
        }
        $is_admin = $this->user->is_admin($userid);
        $is_test_owner = $this->test_owner($userid, $id);

        // if not test owner or admin, return error
        if (!$is_test_owner && !$is_admin) {
            return array("error" => "You do not have permission to delete this test");
        }

        $id = (int)$id;
        $stmt = $this->mysqli->prepare("DELETE FROM heatpump_max_cap_test WHERE id = ?");
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            return array("error" => "Failed to delete max capacity test: " . $stmt->error);
        }
        
        return array("success" => true);
    }

    public function test_owner($userid, $id) {
        // Validate user ID
        $userid = (int)$userid;
        $id = (int)$id;

        $result = $this->mysqli->query("SELECT userid FROM heatpump_max_cap_test WHERE id = $id");
        if ($result->num_rows === 0) {
            return false; // Test not found
        }

        $row = $result->fetch_object();
        return $row->userid == $userid; // Check if the user is the owner of the test
    }

}