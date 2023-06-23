<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class Form
{
    private $mysqli;
    private $schema;

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;

        $schema = array();
        require "form_schema.php";
        $this->schema = $schema;
    }

    public function get_list() {
        $result = $this->mysqli->query("SELECT * FROM form");
        $list = array();
        while ($row = $result->fetch_object()) {
            $list[] = $row;
        }
        return $list;
    }

    public function get_form($userid) {
        $userid = (int) $userid;
        $result = $this->mysqli->query("SELECT * FROM form WHERE userid='$userid'");
        if (!$row = $result->fetch_object()) {
            $this->mysqli->query("INSERT INTO form (userid) VALUES ('$userid')");
            $result = $this->mysqli->query("SELECT * FROM form WHERE userid='$userid'");
            $row = $result->fetch_object();
        }

        return $row;
    }

    public function save_form($userid,$form_data) {
        $userid = (int) $userid;

        // Loop through the form schema and generate the query
        $query = array();
        $codes = array();
        $values = array();
        foreach ($this->schema["form"] as $key=>$value) {
            if (isset($form_data->$key)) {
                $values[] = $form_data->$key;
                $query[] = $key."=?";
                $codes[] = $this->schema["form"][$key]["code"];
            }
        }
        // Add userid to the end
        $values[] = $userid;
        $codes[] = "i";
        // convert to string
        $query = implode(",",$query);
        $codes = implode("",$codes);
        
        // Prepare and execute the query with error checking
        if (!$stmt = $this->mysqli->prepare("UPDATE form SET $query WHERE userid=?")) {
            return array("success"=>false,"message"=>"Prepare failed: (" . $this->mysqli->errno . ") " . $this->mysqli->error);
        }
        if (!$stmt->bind_param($codes, ...$values)) {
            return array("success"=>false,"message"=>"Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error);
        }
        if (!$stmt->execute()) {
            return array("success"=>false,"message"=>"Execute failed: (" . $stmt->errno . ") " . $stmt->error);
        }
        $stmt->close();

        return array("success"=>true,"message"=>"Saved");
    }
}