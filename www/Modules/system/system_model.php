<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class System
{
    private $mysqli;
    public $schema_meta;
    public $schema_stats;

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;

        $schema = array();
        require "Modules/system/system_schema.php";
        $this->schema_meta = $schema['system_meta'];
        $this->schema_stats = $schema['system_stats'];

        $this->schema_meta = $this->populate_codes($this->schema_meta);
        $this->schema_stats = $this->populate_codes($this->schema_stats);
    }

    // Returns a list of public systems
    public function list_public($userid=false) {
        $result = $this->mysqli->query("SELECT * FROM system_meta JOIN system_stats ON system_meta.id = system_stats.id WHERE share=1 AND published=1 OR userid='$userid'");
        $list = array();
        while ($row = $result->fetch_object()) {
            $list[] = $this->typecast($row);
        }
        return $list;
    }

    // All systems
    public function list_admin() {
        $result = $this->mysqli->query("SELECT system_meta.*,system_stats.*,users.name,users.username,users.email FROM system_meta JOIN system_stats ON system_meta.id = system_stats.id JOIN users ON system_meta.userid = users.id");
        $list = array();
        while ($row = $result->fetch_object()) {
            $list[] = $this->typecast($row);
        }
        return $list;
    }

    // User systems
    public function list_user($userid=false) {
        $result = $this->mysqli->query("SELECT * FROM system_meta JOIN system_stats ON system_meta.id = system_stats.id WHERE userid='$userid'");
        $list = array();
        while ($row = $result->fetch_object()) {
            $list[] = $this->typecast($row);
        }
        return $list;
    }

    public function create($userid) {
        $userid = (int) $userid;
        $this->mysqli->query("INSERT INTO system_meta (userid) VALUES ('$userid')");
        $systemid = $this->mysqli->insert_id;

        $this->mysqli->query("INSERT INTO system_stats (id) VALUES ('$systemid')");

        return $systemid;
    }

    // Returns blank form data following the schema
    public function new() {
        $form_data = new stdClass();
        foreach ($this->schema_meta as $key=>$schema_row) {
            // if editable
            if ($schema_row['editable']) {
                $form_data->$key = '';
                if ($schema_row["code"]=='i') $form_data->$key = (int) $form_data->$key;
                if ($schema_row["code"]=='d') $form_data->$key = (float) $form_data->$key;   
            }
        }
        $form_data->id = false;
        return $form_data;
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
        return $this->typecast($row);
    }

    public function validate($form_data) {
        $error_log = array();
        $warning_log = array();

        foreach ($this->schema_meta as $key=>$value) {
            if ($this->schema_meta[$key]['editable']) {
                if (isset($form_data->$key) || $form_data->$key===null) {
                    if ($form_data->$key===null || $form_data->$key==='') {
                        if (!$this->schema_meta[$key]['optional']) {
                            $error_log[] = array("key"=>$key,"message"=>"required");
                        }
                    }
                } else {
                    $error_log[] = array("key"=>$key,"message"=>"missing");
                }
            }
        }
        if (isset($form_data->share) && !$form_data->share) {
            $warning_log[] = array("message"=>"Share is un-ticked? Please tick share if you are happy to share your submission publicly");
        }
        
        if(count($error_log)) {
            return array("success"=>false, "message"=>"Error saving", "error_log"=>$error_log);
        }
        return array("success"=>true, "warning_log"=>$warning_log);
    }

    public function save($userid,$systemid,$form_data,$validate=true) {
        $userid = (int) $userid;
        $systemid = (int) $systemid;

        if ($validate) {
            $validate_result = $this->validate($form_data);
            if ($validate_result['success']==false) {
                return $validate_result;
            }
        } else {
            $validate_result = array("success"=>true, "warning_log"=>array());
        }

        $new_system = false;
        if ($systemid==false) {
            $systemid = $this->create($userid);
            $new_system = $systemid;
        }

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

        // Check for admin published
        if ($this->is_admin($userid)) {
            if (isset($form_data->published)) {
                $values[] = $form_data->published;
                $query[] = "published=?";
                $codes[] = "i";
                if ($original->published!=$form_data->published) {
                    $change_log[] = array("key"=>"published","old"=>$original->published,"new"=>$form_data->published);
                }
            }
        } else {
            // require admin to publish
            // this could be removed.. 
            // $values[] = 0;
            // $query[] = "published=?";
            // $codes[] = "i";        
        }



        if (!count($values)) {
            return array("success"=>false, "message"=>"No changes");
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
            return array(
                "success"=>true,
                "message"=>"Saved",
                "change_log"=>$change_log,
                "warning_log"=>$validate_result["warning_log"],
                "new_system"=>$new_system
            );
        }
    }

    public function get_system_config($userid, $systemid)
    {
        // get config if owned by user or public
        $userid = (int) $userid;
        $result = $this->mysqli->query("SELECT url FROM system_meta WHERE id='$systemid' AND ((share=1 AND published=1) OR userid='$userid')");
        $row = $result->fetch_object();

        $url_parts = parse_url($row->url);
        $server = $url_parts['scheme'] . '://' . $url_parts['host'];
        // check if url was to /app/view instead of username
        if (preg_match('/^(.*)\/app\/view$/', $url_parts['path'], $matches)) {
            $getconfig = "$server$matches[1]/app/getconfig";
        } else {
            $getconfig = $server . $url_parts['path'] . "/app/getconfig";
        }
        // if url has query string, pull out the readkey
        $readkey = '';
        if (isset($url_parts['query'])) {
            parse_str($url_parts['query'], $url_args);
            if (isset($url_args['readkey'])) {
                $readkey = $url_args['readkey'];
                $getconfig .= '?' . $url_parts['query'];
            }
        }

        $config = json_decode(file_get_contents($getconfig));

        $output = new stdClass();
        $output->elec = (int) $config->config->heatpump_elec;
        $output->heat = (int) $config->config->heatpump_heat;
        $output->flowT = (int) $config->config->heatpump_flowT;
        $output->returnT = (int) $config->config->heatpump_returnT;

        if (isset($config->config->heatpump_outsideT)) {
            $output->outsideT = (int) $config->config->heatpump_outsideT;
        }
        $output->server = $server;
        $output->apikey = $readkey;

        return $output;
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

        $stats = array(
            'start' => $stats->start,
            'end' => $stats->end,
            // 'interval' => $stats->interval, // error here not needed for now
            'datapoints' => $stats->datapoints,
            'standby_threshold' => $stats->standby_threshold,
            'full_period_elec_kwh' => $stats->full_period->elec_kwh,
            'full_period_heat_kwh' => $stats->full_period->heat_kwh,
            'full_period_cop' => $stats->full_period->cop,
            'standby_kwh' => $stats->standby_kwh,
            'when_running_elec_kwh' => $stats->when_running->elec_kwh,
            'when_running_heat_kwh' => $stats->when_running->heat_kwh,
            'when_running_cop' => $stats->when_running->cop,
            'when_running_elec_W' => $stats->when_running->elec_W,
            'when_running_heat_W' => $stats->when_running->heat_W,
            'when_running_flowT' => $stats->when_running->flowT,
            'when_running_returnT' => $stats->when_running->returnT,
            'when_running_flow_minus_return' => $stats->when_running->flow_minus_return,
            'when_running_outsideT' => $stats->when_running->outsideT,
            'when_running_flow_minus_outside' => $stats->when_running->flow_minus_outside,
            'when_running_carnot_prc' => $stats->when_running->carnot_prc,
            'last_365_elec_kwh' => $stats->last365->elec_kwh,
            'last_365_heat_kwh' => $stats->last365->heat_kwh,
            'last_365_cop' => $stats->last365->cop,
            'last_365_since' => $stats->last365->since,
            'last_30_elec_kwh' => $stats->last30->elec_kwh,
            'last_30_heat_kwh' => $stats->last30->heat_kwh,
            'last_30_cop' => $stats->last30->cop,
            'last_30_since' => $stats->last30->since
        );

        $keys = array_keys($stats);

        $query = array();
        $codes = array();
        $values = array();

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

    public function typecast($row) {
        foreach ($this->schema_meta as $key=>$schema_row) {
            if (isset($row->$key)) {
                if ($schema_row["code"]=='i') $row->$key = (int) $row->$key;
                if ($schema_row["code"]=='d') $row->$key = (float) $row->$key;
            } else {
                if ($schema_row["code"]=='i') $row->$key = 0;
                if ($schema_row["code"]=='d') $row->$key = 0.0;
            }
        }
        foreach ($this->schema_stats as $key=>$schema_row) {
            if (isset($row->$key)) {
                if ($schema_row["code"]=='i') $row->$key = (int) $row->$key;
                if ($schema_row["code"]=='d') $row->$key = (float) $row->$key;
            } else {
                if ($schema_row["code"]=='i') $row->$key = 0;
                if ($schema_row["code"]=='d') $row->$key = 0.0;
            }
        }
        if (isset($row->stats) && $row->stats!=null) {
            $row->stats = json_decode($row->stats);
        }
        return $row;
    }

    public function populate_codes($schema) {
        // populate schema codes based on type
        foreach ($schema as $key=>$value) {
            if (strpos($schema[$key]['type'],'varchar')!==false) $schema[$key]['code'] = 's';
            else if (strpos($schema[$key]['type'],'text')!==false) $schema[$key]['code'] = 's';
            else if (strpos($schema[$key]['type'],'int')!==false) $schema[$key]['code'] = 'i';
            else if (strpos($schema[$key]['type'],'float')!==false) $schema[$key]['code'] = 'd';
            else if (strpos($schema[$key]['type'],'bool')!==false) $schema[$key]['code'] = 'b';
        }
        return $schema;
    }

    // get columns
    public function get_columns() {
        $columns = array();
        foreach ($this->schema_meta as $key=>$row) {
            // name and group for each key
            if (!isset($row['group']) || !isset($row['name'])) continue;
            $columns[$key] = array("name"=>$row['name'], "group"=>$row['group']);
        }
        foreach ($this->schema_stats as $key=>$row) {
            // name and group for each key
            if (!isset($row['group']) || !isset($row['name'])) continue;
            $columns[$key] = array("name"=>$row['name'], "group"=>$row['group']);
        }
        return $columns;
    }
}
