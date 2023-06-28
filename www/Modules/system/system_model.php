<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class System
{
    private $mysqli;
    public $schema;

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;

        $schema = array();
        require "Modules/system/system_schema.php";
        $this->schema = $schema['form'];
    }

    public function list($userid=false) {
        if ($userid===false) {
            $result = $this->mysqli->query("SELECT * FROM form");
        } else {
            $userid = (int) $userid;
            $result = $this->mysqli->query("SELECT * FROM form WHERE userid='$userid'");
        }
        $list = array();
        while ($row = $result->fetch_object()) {
            // convert numeric strings to numbers
            foreach ($row as $key=>$value) {
                if ($this->schema[$key]["code"]=='i') $row->$key = (int) $value;
                if ($this->schema[$key]["code"]=='d') $row->$key = (float) $value;
            }
            if ($row->stats!=null) {
                $row->stats = json_decode($row->stats);
            }
            if ($row->stats==null) {
                unset($row->stats);
            }
            $list[] = $row;
        }
        return $list;
    }

    public function create($userid) {
        $userid = (int) $userid;
        $this->mysqli->query("INSERT INTO form (userid) VALUES ('$userid')");
        $systemid = $this->mysqli->insert_id;
        return array("success"=>true, "id"=>$systemid);
    }

    public function get($userid,$systemid) {
        $userid = (int) $userid;
        $systemid = (int) $systemid;
        $result = $this->mysqli->query("SELECT * FROM form WHERE id='$systemid'");
        if (!$row = $result->fetch_object()) {
            return array("success"=>false, "message"=>"System does not exist");
        }
        if ($userid!=$row->userid) {
            return array("success"=>false, "message"=>"Invalid access");
        }
        return $row;
    }

    public function save($userid,$systemid,$form_data) {
        $userid = (int) $userid;
        $systemid = (int) $systemid;

        // Loop through the form schema and generate the query
        $query = array();
        $codes = array();
        $values = array();
        
        foreach ($this->schema as $key=>$value) {
            if ($this->schema[$key]['editable']) {
                if (isset($form_data->$key)) {
                    $values[] = $form_data->$key;
                    $query[] = $key."=?";
                    $codes[] = $this->schema[$key]["code"];
                }
            }
        }
        // Add userid to the end
        $values[] = $userid;
        $codes[] = "i";
        $values[] = $systemid;
        $codes[] = "i";

        // convert to string
        $query = implode(",",$query);
        $codes = implode("",$codes);
        
        // Prepare and execute the query with error checking
        if (!$stmt = $this->mysqli->prepare("UPDATE form SET $query WHERE userid=? AND id=?")) {
            return array("success"=>false,"message"=>"Prepare failed: (" . $this->mysqli->errno . ") " . $this->mysqli->error);
        }
        if (!$stmt->bind_param($codes, ...$values)) {
            return array("success"=>false,"message"=>"Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error);
        }
        if (!$stmt->execute()) {
            return array("success"=>false,"message"=>"Execute failed: (" . $stmt->errno . ") " . $stmt->error);
        }

        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected==0) {
            return array("success"=>true,"message"=>"No changes");
        }  else {
            return array("success"=>true,"message"=>"Saved");
        }
    }

    public function save_stats($userid,$stats) {
        $userid = (int) $userid;
        
        $stats = [
            "month_elec" => $stats->last30->elec_kwh,
            "month_heat" => $stats->last30->heat_kwh,
            "month_cop"  => $stats->last30->cop,
            "year_elec"  => $stats->last365->elec_kwh,
            "year_heat"  => $stats->last365->heat_kwh,
            "year_cop"   => $stats->last365->cop,
            "since"      => $stats->last365->since,
            "stats"      => $stats
        ];
        $keys = array_keys($stats);

        $query = array();
        $codes = array();
        $values = array();

        $stats['stats'] = json_encode($stats['stats']);

        foreach ($keys as $key) {
            if (isset($stats[$key])) {
                $values[] = $stats[$key];
                $query[] = $key."=?";
                $codes[] = $this->schema[$key]["code"];
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

    public function delete($userid,$systemid) {
        $userid = (int) $userid;
        $systemid = (int) $systemid;
        $result = $this->mysqli->query("SELECT * FROM form WHERE id='$systemid'");
        if (!$row = $result->fetch_object()) {
            return array("success"=>false, "message"=>"System does not exist");
        }
        if ($userid!=$row->userid) {
            return array("success"=>false, "message"=>"Invalid access");
        }

        $this->mysqli->query("DELETE FROM form WHERE id='$systemid'");
        return array("success"=>true, "message"=>"Deleted");
    }
}