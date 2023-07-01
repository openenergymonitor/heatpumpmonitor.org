<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class System
{
    private $mysqli;
    public $schema;
    public $schema_stats;

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;

        $schema = array();
        require "Modules/system/system_schema.php";
        $this->schema_meta = $schema['system_meta'];
        $this->schema_stats = $schema['system_stats'];
    }

    public function list($userid=false) {

        if ($userid===false) {
            $admin = false;
            $result = $this->mysqli->query("SELECT * FROM system_meta JOIN system_stats ON system_meta.id = system_stats.id");            
        } else {
            $userid = (int) $userid;
            $admin = $this->is_admin($userid);
            $result = $this->mysqli->query("SELECT * FROM system_meta JOIN system_stats ON system_meta.id = system_stats.id WHERE userid='$userid'");
        }
        $list = array();
        while ($row = $result->fetch_object()) {
            // convert numeric strings to numbers
            foreach ($this->schema_meta as $key=>$schema_row) {
                if (isset($row->$key) || $row->$key==null) {
                    if ($schema_row["code"]=='i') $row->$key = (int) $row->$key;
                    if ($schema_row["code"]=='d') $row->$key = (float) $row->$key;
                }
            }
            foreach ($this->schema_stats as $key=>$schema_row) {
                if (isset($row->$key) || $row->$key==null) {
                    if ($schema_row["code"]=='i') $row->$key = (int) $row->$key;
                    if ($schema_row["code"]=='d') $row->$key = (float) $row->$key;
                }
            }
            if ($row->stats!=null) {
                $row->stats = json_decode($row->stats);
            }
            if ($row->stats==null) {
                unset($row->stats);
            }

            // Remove non public fields
            // automate this?
            // maybe no need for email in form given that it's in the user table?
            if ($admin==false && $userid==false) {
                unset($row->email);
            }

            $list[] = $row;
        }
        return $list;
    }

    public function create($userid) {
        $userid = (int) $userid;
        $this->mysqli->query("INSERT INTO system_meta (userid) VALUES ('$userid')");
        $systemid = $this->mysqli->insert_id;

        $this->mysqli->query("INSERT INTO system_stats (id) VALUES ('$systemid')");

        return array("success"=>true, "id"=>$systemid);
    }

    public function get($userid,$systemid) {
        $userid = (int) $userid;
        $systemid = (int) $systemid;
        $result = $this->mysqli->query("SELECT * FROM system_meta WHERE id='$systemid'");
        if (!$row = $result->fetch_object()) {
            return array("success"=>false, "message"=>"System does not exist");
        }
        if ($userid!=$row->userid && $this->is_admin($userid)==false) {
            return array("success"=>false, "message"=>"Invalid access");
        }

        foreach ($this->schema_meta as $key=>$schema_row) {
            if (isset($row->$key) || $row->$key==null) {
                if ($schema_row["code"]=='i') $row->$key = (int) $row->$key;
                if ($schema_row["code"]=='d') $row->$key = (float) $row->$key;
            }
        }

        return $row;
    }

    public function save($userid,$systemid,$form_data) {
        $userid = (int) $userid;
        $systemid = (int) $systemid;

        // Check if user has access
        if ($this->has_access($userid,$systemid)==false) {
            return array("success"=>false, "message"=>"Invalid access");
        }

        // Compile change log
        $original = $this->get($userid, $systemid);
        
        // Loop through the form schema and generate the query
        $query = array();
        $codes = array();
        $values = array();
        $change_log = array();
        
        foreach ($this->schema_meta as $key=>$value) {
            if ($this->schema_meta[$key]['editable']) {
                if (isset($form_data->$key)) {
                    $values[] = $form_data->$key;
                    $query[] = $key."=?";
                    $codes[] = $this->schema_meta[$key]["code"];

                    if ($original->$key!=$form_data->$key) {
                        $change_log[] = array("key"=>$key,"old"=>$original->$key,"new"=>$form_data->$key);
                    }
                }
            }
        }
        // Add systemid to the end
        $values[] = $systemid;
        $codes[] = "i";

        // convert to string
        $query = implode(",",$query);
        $codes = implode("",$codes);
        
        // Prepare and execute the query with error checking
        if (!$stmt = $this->mysqli->prepare("UPDATE system_meta SET $query WHERE id=?")) {
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
            $this->send_change_notification($userid,$systemid,$change_log);

            // Update last updated time
            $now = time();
            $this->mysqli->query("UPDATE system_meta SET last_updated='$now' WHERE id='$systemid'");
            return array("success"=>true,"message"=>"Saved", "change_log"=>$change_log);
        }
    }

    public function load_stats_from_url($url) {
        # decode the url to separate out any args
        $url_parts = parse_url($url);
        $server = $url_parts['scheme'] . '://' . $url_parts['host'];

        # check if url was to /app/view instead of username
        if (preg_match('/^(.*)\/app\/view$/', $url_parts['path'], $matches)) {
            $getstats = "$server$matches[1]/app/getstats";
        } else {
            $getstats = $server . $url_parts['path'] . "/app/getstats";
        }

        # if url has query string, pull out the readkey
        if (isset($url_parts['query'])) {
            parse_str($url_parts['query'], $url_args);
            if (isset($url_args['readkey'])) {
                // $readkey = $url_args['readkey'];
                $getstats .= '?' . $url_parts['query'];
            }
        }

        if (!$stats = json_decode(file_get_contents($getstats))) {
            return false;
        } else {
            return $stats;
        }
    }

    public function save_stats($systemid,$stats) {
        $systemid = (int) $systemid;
        
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
                $codes[] = $this->schema_stats[$key]["code"];
            }
        }

        // Add systemid to the end
        $values[] = $systemid;
        $codes[] = "i";
        // convert to string
        $query = implode(",",$query);
        $codes = implode("",$codes);

        // Prepare and execute the query with error checking
        if (!$stmt = $this->mysqli->prepare("UPDATE system_stats SET $query WHERE id=?")) {
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

        // Check if user has access
        if ($this->has_access($userid,$systemid)==false) {
            return array("success"=>false, "message"=>"Invalid access");
        }
        // Delete the system
        $this->mysqli->query("DELETE FROM system_meta WHERE id='$systemid'");
        $this->mysqli->query("DELETE FROM system_stats WHERE id='$systemid'");
        return array("success"=>true, "message"=>"Deleted");
    }

    public function has_access($userid,$systemid) {
        $userid = (int) $userid;
        $systemid = (int) $systemid;
        $result = $this->mysqli->query("SELECT userid FROM system_meta WHERE id='$systemid'");
        if (!$row = $result->fetch_object()) {
            return false;
        }
        if ($userid!=$row->userid && $this->is_admin($userid)==false) {
            return false;
        }
        return true;
    }

    public function is_admin($userid) {
        $userid = (int) $userid;
        $result = $this->mysqli->query("SELECT admin FROM users WHERE id='$userid'");
        if (!$row = $result->fetch_object()) {
            return false;
        }
        if ($row->admin==1) {
            return true;
        } else {
            return false;
        }
    }

    public function send_change_notification($userid,$systemid,$change_log) {
        $userid = (int) $userid;
        $systemid = (int) $systemid;
        $change_count = count($change_log);

        global $settings;
        if (isset($settings['change_notifications_enabled'])) {
            if ($settings['change_notifications_enabled']==false) {
                return;
            }
        } else {
            return;
        }

        // Get admin users email addresses
        $result = $this->mysqli->query("SELECT email FROM users WHERE admin=1");
        $emails = array();
        while ($row = $result->fetch_object()) {
            $emails[] = array("email"=>$row->email);
        }

        $html = "<h3>System $systemid has been updated</h3>";
        $html .= "<p>$change_count fields updated</p>";
        $html .= "<ul>";
        foreach ($change_log as $change) {
            // as list
            $html .= "<li><b>".$change['key']."</b> changed from <b>".$change['old']."</b> to <b>".$change['new']."</b></li>";
        }
        $html .= "</ul>";

        // Move this to background task
        require_once "Lib/email.php";
        $email_class = new Email();
        $email_class->send(array(
            "to" => $emails,
            "subject" => "System $systemid has been updated",
            "text" => "System $systemid has been updated, $change_count fields updated",
            "html" => $html
        ));
    }
}