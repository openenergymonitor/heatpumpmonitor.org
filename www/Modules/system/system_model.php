<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class System
{
    private $mysqli;
    public $schema_meta;

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;

        $schema = array();
        require "Modules/system/system_schema.php";
        $this->schema_meta = $schema['system_meta'];
        $this->schema_meta = $this->populate_codes($this->schema_meta);
    }

    // Returns a list of public systems
    public function list_public($userid=false) {
        $userid = (int) $userid;
        $result = $this->mysqli->query("SELECT * FROM system_meta WHERE share=1 AND published=1 OR userid='$userid'");
        $list = array();
        while ($row = $result->fetch_object()) {
            unset($row->url);
            unset($row->userid);
            unset($row->get_emoncmsorg_userid);
            $list[] = $this->typecast($row);
        }
        return $list;
    }

    // Return number of public systems
    public function count_public() {
        $result = $this->mysqli->query("SELECT COUNT(*) as count FROM system_meta WHERE share=1 AND published=1");
        $row = $result->fetch_object();
        return $row->count;
    }

    // All systems
    public function list_admin() {
        $result = $this->mysqli->query("SELECT system_meta.*,users.name,users.username,users.email FROM system_meta JOIN users ON system_meta.userid = users.id ORDER BY system_meta.id");
        $list = array();
        while ($row = $result->fetch_object()) {
            if (!isset($row->emoncmsorg_userid)) {
                $row->emoncmsorg_userid = $this->get_emoncmsorg_userid($row->userid);
                if ($row->emoncmsorg_userid) $row->emoncmsorg_userid = (int) $row->emoncmsorg_userid;
            }
            $list[] = $this->typecast($row);
        }
        return $list;
    }

    // Get emoncmsorg userid
    public function get_emoncmsorg_userid($userid) {
        $userid = (int) $userid;
        $result = $this->mysqli->query("SELECT emoncmsorg_userid FROM emoncmsorg_link WHERE userid='$userid'");
        if ($result->num_rows > 0) {
            $row = $result->fetch_object();
            return $row->emoncmsorg_userid;
        } else {
            return false;
        }
    }

    // User systems
    public function list_user($userid=false) {
        $userid = (int) $userid;
        $result = $this->mysqli->query("SELECT * FROM system_meta WHERE userid='$userid'");
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

        return $systemid;
    }

    // Returns blank form data following the schema
    public function new() {
        $form_data = new stdClass();
        foreach ($this->schema_meta as $key=>$schema_row) {
            // if editable
            if ($schema_row['editable']) {
                $form_data->$key = '';
                // set default value
                if (isset($schema_row['default'])) {
                    $form_data->$key = $schema_row['default'];
                }

                //if ($schema_row["code"]=='i') $form_data->$key = (int) $form_data->$key;
                //if ($schema_row["code"]=='d') $form_data->$key = (float) $form_data->$key;
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
        $row = $this->typecast($row);
        
        // If user is an admin return system
        if ($this->is_admin($userid)) {
            return $row;
        }
        
        // If it's the users system then return system
        if ($userid == $row->userid) {
            return $row;
        }
        
        // If public then return system
        if ($row->share==1 && $row->published==1) {
            unset($row->url);
            return $row;
        }
        
        return array("success"=>false, "message"=>"Invalid access");
    }

    public function validate($userid,$form_data,$full_validation=false) {
        $error_log = array();
        $warning_log = array();

        foreach ($this->schema_meta as $key=>$value) {
            if ($this->schema_meta[$key]['editable']) {
                if ($full_validation) {
                    // Check if key is set, report missing
                    if (!isset($form_data->$key)) {
                        $error_log[] = array("key"=>$key,"message"=>"missing");
                    // Check if key is empty, report required
                    // if not optional or admin
                    } else if ($form_data->$key===null || $form_data->$key==='') {
                        if (!$this->schema_meta[$key]['optional'] && $this->is_admin($userid)==false) {
                            $error_log[] = array("key"=>$key,"message"=>"required");
                        }
                    }
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

        // Make sure first form submission has all fields validated
        $full_validation = false;
        if ($systemid==false) $full_validation = true;

        if ($validate) {
            $validate_result = $this->validate($userid,$form_data,$full_validation);
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
            // $this->send_change_notification($userid,$systemid,$change_log);
            $this->log_changes($systemid,$userid,$change_log);

            if ($new_system) {
                // Sent new system notification
                $this->send_change_notification($userid,$systemid,$change_log,true);
            }

            // If location is in change_log then update latitude and longitude
            foreach ($change_log as $change) {
                if ($change['key']=='location') {
                    // Get latitude and longitude from location
                    $this->update_lat_lon($systemid, $change['new']);
                }
            }

            // $this->computed_fields($systemid);

            // Update last updated time
            $now = time();
            $this->mysqli->query("UPDATE system_meta SET last_updated='$now' WHERE id='$systemid'");
            return array(
                "success"=>true,
                "message"=>"Saved",
                "change_log"=>$change_log,
                "warning_log"=>$validate_result["warning_log"],
                "systemid"=>$systemid,
                "new_system"=>$new_system
            );
        }
    }

    public function computed_fields($systemid=false) {
        $systemid = (int) $systemid;
        $where = "";
        if ($systemid) $where = "WHERE id='$systemid'";        
        
        // kwh_m2
        $result = $this->mysqli->query("SELECT id,heat_demand,floor_area FROM system_meta $where");
        while ($row = $result->fetch_object()) {
            $kwh_m2 = 0;
            if ($row->floor_area>0) $kwh_m2 = round($row->heat_demand / $row->floor_area);
            $this->mysqli->query("UPDATE system_meta SET kwh_m2='$kwh_m2' WHERE id='$row->id'");
        }
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

    public function get_system_userid($systemid) {
        $systemid = (int) $systemid;
        $result = $this->mysqli->query("SELECT userid FROM system_meta WHERE id='$systemid'");
        if (!$row = $result->fetch_object()) {
            return false;
        }
        return $row->userid;
    }

    public function get_user_name($userid) {
        $userid = (int) $userid;
        $result = $this->mysqli->query("SELECT name,username FROM users WHERE id='$userid'");
        if (!$row = $result->fetch_object()) {
            return false;
        }
        return $row;
    }

    public function send_change_notification($userid,$systemid,$change_log, $new_system=false) {
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

        // Is system published
        $result = $this->mysqli->query("SELECT published FROM system_meta WHERE id='$systemid'");
        $row = $result->fetch_object();
        if ($row->published) {
            $published_str = "";
        } else {
            $published_str = " (unpublished, admin review required)";
        }

        // Get system owner username and name
        $system_userid = $this->get_system_userid($systemid);
        $result = $this->get_user_name($system_userid);
        $system_username = $result->username;
        $system_name = $result->name;

        $by = "";
        if ($userid!=$system_userid) {
            $result = $this->get_user_name($userid);
            $by = "by $result->name";
        }

        if ($new_system) {
            $subject = "New system $systemid user $system_name ($system_username) has been created $by $published_str";
            $text = "New system $systemid user $system_name ($system_username) has been created $by";

            $html = "<h3>New system $systemid user $system_name ($system_username) has been created $by</h3>";
            $html .= "<p>$change_count fields set</p>";

            $html .= "<ul>";
            foreach ($change_log as $change) {
                // as list
                $html .= "<li><b>".$change['key']."</b>: <b>".$change['new']."</b></li>";
            }
            $html .= "</ul>";

        } else {
            $subject = "System $systemid user $system_name ($system_username) has been updated $by $published_str";
            $text = "System $systemid has been updated, $change_count fields updated";

            $html = "<h3>System $systemid user $system_name ($system_username) has been updated $by</h3>";
            $html .= "<p>$change_count fields updated</p>";

            $html .= "<ul>";
            foreach ($change_log as $change) {
                // as list
                $html .= "<li><b>".$change['key']."</b> changed from <b>".$change['old']."</b> to <b>".$change['new']."</b></li>";
            }
            $html .= "</ul>";
        }

        // Move this to background task
        require_once "Lib/email.php";
        $email_class = new Email();
        $email_class->send(array(
            "to" => $emails,
            "subject" => $subject,
            "text" => $text,
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
            $helper = "";
            if (isset($row['helper'])) $helper = $row['helper'];
            $colum = array("name"=>$row['name'], "group"=>$row['group'], "helper"=>$helper);
            if (isset($row['options'])) $colum['options'] = $row['options'];
            $columns[$key] = $colum;
        }
        /*
        foreach ($this->schema_stats_monthly as $key=>$row) {
            // name and group for each key
            if (!isset($row['group']) || !isset($row['name'])) continue;
            $columns[$key] = array("name"=>$row['name'], "group"=>$row['group']);
        }*/


        return $columns;
    }

    // --------------------------------------------------------------------------------
    // System meta changes log
    // --------------------------------------------------------------------------------

    // Convert change log in to log calls
    public function log_changes($systemid,$userid,$change_log) {
        $timestamp = time();
        foreach ($change_log as $change) {
            $this->log($timestamp,$systemid,$userid,$change['key'],$change['old'],$change['new']);
        }
    }

    // System log
    public function log($timestamp,$systemid,$userid,$field,$old_value,$new_value) {
        $timestamp = (int) $timestamp;
        $systemid = (int) $systemid;
        $userid = (int) $userid;

        if (!$stmt = $this->mysqli->prepare("INSERT INTO system_meta_changes (timestamp,systemid,userid,field,old_value,new_value) VALUES (?,?,?,?,?,?)")) {
            return false;
        }
        if (!$stmt->bind_param("iiisss", $timestamp,$systemid,$userid,$field,$old_value,$new_value)) {
            return false;
        }
        if (!$stmt->execute()) {
            return false;
        }
        $stmt->close();
        return true;
    }

    // Get system changes log
    public function get_changes($systemid = false) {
        // If systemid is set then get changes for that system
        $where = "";
        if ($systemid) {
            $systemid = (int) $systemid;
            $where = "WHERE systemid='$systemid'";
        }

        // Get list of usernames
        $result = $this->mysqli->query("SELECT id,username,admin FROM users");
        $users = array();
        while ($row = $result->fetch_object()) {
            $users[$row->id] = $row;
        }

        // Get changes
        $result = $this->mysqli->query("SELECT * FROM system_meta_changes $where ORDER BY timestamp DESC LIMIT 1000");
        $list = array();
        while ($row = $result->fetch_object()) {

            // Convert timestamp to date
            $date = new DateTime();
            // Europe/London timezone
            $date->setTimezone(new DateTimeZone('Europe/London'));
            $date->setTimestamp($row->timestamp);
            // 12th Dec 2024 08:00
            $row->datetime = $date->format('jS M Y H:i');

            // add username
            $row->username = $users[$row->userid]->username;
            $row->admin = $users[$row->userid]->admin;

            $list[] = $row;
        }
        return $list;
    }

    // get available apps
    public function available_apps($userid) {
        $userid = (int) $userid;

        // Get this username
        $result = $this->mysqli->query("SELECT username FROM users WHERE id='$userid'");
        $row = $result->fetch_object();
        $username = $row->username;

        // Get emoncmsorg apikey
        $result = $this->mysqli->query("SELECT * FROM emoncmsorg_link WHERE userid='$userid'");
        if (!$row = $result->fetch_object()) {
            return array();
        }

        // Master account
        $myheatpump_apps = $this->append_app_list(array(), $username, $row->emoncmsorg_apikey_read);

        // Get sub accounts
        $result = file_get_contents("https://emoncms.org/account/list.json?apikey=$row->emoncmsorg_apikey_write");
        if (!$result) return $myheatpump_apps;

        $accounts = json_decode($result);
        if (!$accounts) return $myheatpump_apps;
        
        foreach ($accounts as $account) {
            $myheatpump_apps = $this->append_app_list($myheatpump_apps, $account->username, $account->apikey_read);
        }

        return $myheatpump_apps;
    }

    private function append_app_list($myheatpump_apps, $username, $readkey) {
        $result = file_get_contents("https://emoncms.org/app/list.json?apikey=".$readkey);
        if (!$result) return $myheatpump_apps;

        $apps = json_decode($result);
        if (!$apps) return $myheatpump_apps;

        foreach ($apps as $app) {
            if ($app->app=="myheatpump") {
                $app->username = $username;
                // Generate url
                $url = "https://emoncms.org/app/view?name=".$app->name."&readkey=".$readkey;
                $app->url = $url;

                // Check if app is already added, skip if it is
                $result = $this->mysqli->query("SELECT id FROM system_meta WHERE url='$url'");
                if ($result->num_rows>0) {
                    $app->in_use = 1;
                } else {
                    $app->in_use = 0;
                }

                $myheatpump_apps[] = array(
                    "id" => (int) $app->id,
                    "username" => $app->username,
                    "name" => $app->name,
                    "url" => $app->url,
                    "public" => (int) $app->public,
                    "in_use" => $app->in_use
                );
            }
        }
        return $myheatpump_apps;
    }

    public function update_lat_lon($systemid, $location) {
        global $settings;
        if (!isset($settings['opencagedata_api_key'])) return;
        $apikey = $settings['opencagedata_api_key'];

        $id = (int) $systemid;

        $location = urlencode($location);
        $url = "https://api.opencagedata.com/geocode/v1/json?q=$location&key=$apikey";
        $json = file_get_contents($url);
        $data = json_decode($json);
        if (isset($data->results[0]->geometry->lat) && isset($data->results[0]->geometry->lng)) {
            // 1 dp ~11 km
            // 2 dp ~1.1 km
            // 3 dp ~110 m **
            // 4 dp ~11 m
            // 5 dp ~1.1 m
            $latitude = round($data->results[0]->geometry->lat, 3);
            $longitude = round($data->results[0]->geometry->lng, 3);

            $stmt = $this->mysqli->prepare("UPDATE system_meta SET latitude = ?, longitude = ? WHERE id = ?");
            $stmt->bind_param("ddi", $latitude, $longitude, $id);
            $stmt->execute();
        }
    }
}
