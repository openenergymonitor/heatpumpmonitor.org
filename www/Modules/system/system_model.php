<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class System
{
    private $mysqli;
    private $host;
    public $schema_meta;
    private $emoncms_mysqli = false;

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;

        global $settings;
        $this->host = $settings['emoncms_host'];

        $schema = array();
        require "Modules/system/system_schema.php";
        $this->schema_meta = $schema['system_meta'];
        $this->schema_meta = $this->populate_codes($this->schema_meta);
    }

    /**
     * Build a SQL query with common JOINs for photo count, manufacturer, and heat pump model
     * 
     * @param string $select Additional SELECT fields (default: "sm.*")
     * @param string $additionalJoins Additional JOIN clauses (e.g., "JOIN users u ON sm.userid = u.id")
     * @param string $where WHERE clause (without WHERE keyword)
     * @param string $orderBy ORDER BY clause (without ORDER BY keyword, default: "sm.id")
     * @return string The complete SQL query
     */
    private function build_system_list_query($select = "sm.*", $additionalJoins = "", $where = "", $orderBy = "sm.id") {
        $query = "SELECT $select, COALESCE(pc.photo_count, 0) as photo_count,
                    m.id as manufacturer_id, hm.id as heatpump_model_id
                  FROM system_meta sm 
                  $additionalJoins
                  LEFT JOIN (
                      SELECT system_id, COUNT(*) as photo_count 
                      FROM system_images 
                      GROUP BY system_id
                  ) pc ON sm.id = pc.system_id 
                  LEFT JOIN manufacturers m ON sm.hp_manufacturer = m.name
                  LEFT JOIN heatpump_model hm ON m.id = hm.manufacturer_id 
                      AND sm.hp_model = hm.name
                      AND CAST(hm.capacity AS DECIMAL(10,2)) = sm.hp_output
                      AND (hm.refrigerant = sm.refrigerant OR sm.refrigerant IS NULL OR hm.refrigerant IS NULL)";
        
        if ($where) {
            $query .= " WHERE $where";
        }
        
        if ($orderBy) {
            $query .= " ORDER BY $orderBy";
        }
        
        return $query;
    }

    /**
     * Process result rows from system list queries
     * Adds heatpump_url if a matching heat pump model is found
     * 
     * @param object $row The database row to process
     * @param bool $removePrivateFields Whether to remove private fields (url, userid, etc.)
     * @return object The processed row
     */
    private function process_system_row($row, $removePrivateFields = false) {
        global $path;

        $row = $this->typecast($row);
        
        // Add heatpump_url if both manufacturer and model are matched
        if ($row->heatpump_model_id) {
            $row->heatpump_url = $path . 'heatpump/view?id=' . (int)$row->heatpump_model_id;
        }

        // Get last updated times
        $row->heatpump_elec_feedid = false;
        $row->heatpump_heat_feedid = false;
        $row->heatpump_elec_ago = 876000;
        $row->heatpump_heat_ago = 876000;
        $row->heatpump_max_age = 876000;

        if (!$removePrivateFields) {
            if ($last_updated = $this->get_last_updated($row->app_id)) {
                $row->heatpump_elec_feedid = $last_updated['elec_feedid'];
                $row->heatpump_heat_feedid = $last_updated['heat_feedid'];
                $row->heatpump_elec_ago = $last_updated['elec_ago'];
                $row->heatpump_heat_ago = $last_updated['heat_ago'];
                $row->heatpump_max_age = $last_updated['max_age'];
            }
        }
        
        // Remove private fields for public lists
        if ($removePrivateFields) {
            unset($row->url);
            unset($row->userid);
            unset($row->app_id);
            unset($row->readkey);
        }
        
        return $row;
    }

    // Returns a list of public systems
    public function list_public($userid=false) {
        $userid = (int) $userid;
        $query = $this->build_system_list_query(
            "sm.*",
            "",
            "sm.share=1 AND sm.published=1",
            ""
        );
        
        $result = $this->mysqli->query($query);
        $list = array();
        while ($row = $result->fetch_object()) {
            $list[] = $this->process_system_row($row, true);
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

        // Connect to emoncms database for app info (function in www/core.php)
        $this->emoncms_mysqli = connect_emoncms_database();

        $query = $this->build_system_list_query(
            "sm.*, u.username",
            "JOIN users u ON sm.userid = u.id",
            "",
            "sm.id"
        );
        
        $result = $this->mysqli->query($query);
        $list = array();
        while ($row = $result->fetch_object()) {
            $list[] = $this->process_system_row($row, false);
        }
        return $list;
    }

    // User systems
    public function list_user($userid=false) {
        $userid = (int) $userid;

        // Connect to emoncms database for app info
        $this->emoncms_mysqli = connect_emoncms_database();

        // Get user's own systems
        $query = $this->build_system_list_query(
            "sm.*, u.username",
            "JOIN users u ON sm.userid = u.id",
            "sm.userid='$userid'",
            "sm.id"
        );
        
        $result = $this->mysqli->query($query);
        $list = array();
        while ($row = $result->fetch_object()) {
            $list[] = $this->process_system_row($row, false);
        }

        // Add any systems from sub-accounts
        $query = $this->build_system_list_query(
            "sm.*, u.username",
            "JOIN users u ON sm.userid = u.id JOIN accounts a ON u.id = a.linkeduser",
            "a.adminuser='$userid'",
            "sm.id"
        );
        
        $result = $this->mysqli->query($query);
        while ($row = $result->fetch_object()) {
            $list[] = $this->process_system_row($row, false);
        }

        // Add systems from system_access table
        // $result = $this->mysqli->query("SELECT system_meta.*,users.username FROM system_meta JOIN users ON system_meta.userid = users.id JOIN system_access ON system_meta.id = system_access.systemid WHERE system_access.userid='$userid' AND system_access.access>0 ORDER BY system_meta.id");
        // while ($row = $result->fetch_object()) {
        //     $list[] = $this->typecast($row);
        // }

        return $list;
    }

    public function create($userid) {
        $userid = (int) $userid;

        $this->mysqli->query("INSERT INTO system_meta (userid) VALUES ('$userid')");
        $systemid = $this->mysqli->insert_id;

        return $systemid;
    }

    // Returns blank form data following the schema
    public function new($userid=false) {

        $userid = (int) $userid;

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


        // Pre-fill installer details from user's latest system
        $user_system_list = $this->list_user($userid);
        if (count($user_system_list)) {
            // Get latest system
            $latest_system = end($user_system_list);

            $fields_to_copy = array(
                "installer_name",
                "installer_url",
                "installer_logo",
                "heatgeek",
                "heatingacademy"
            );

            foreach ($fields_to_copy as $field) {
                if (isset($latest_system->$field)) {
                    $form_data->$field = $latest_system->$field;
                }
            }

        }

        return $form_data;
    }

    public function get($userid,$systemid) {
        $userid = (int) $userid;
        $systemid = (int) $systemid;

        // Check if user has access
        if ($this->has_read_access($userid,$systemid)===false) {
            return array("success"=>false, "message"=>"Invalid access");
        }
        
        $result = $this->mysqli->query("SELECT * FROM system_meta WHERE id='$systemid'");
        if (!$row = $result->fetch_object()) {
            return array("success"=>false, "message"=>"System does not exist");
        }

        $row = $this->typecast($row);
        // remove readkey and app_id

        if ($this->has_write_access($userid,$systemid)===false) {
            unset($row->url);
            unset($row->userid);
            unset($row->app_id);
            unset($row->readkey);
        }
        return $row;
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
                        // Only enforce required fields if the system is set to be shared
                        $is_sharing = isset($form_data->share) && $form_data->share;
                        if (!$this->schema_meta[$key]['optional'] && $this->is_admin($userid)==false && $is_sharing) {
                            $error_log[] = array("key"=>$key,"message"=>"required");
                        }
                    }
                }

                // Sanitise data types
                if (isset($form_data->$key) && $form_data->$key !== null && $form_data->$key !== '') {
                    $type = $this->schema_meta[$key]['type'];
                    
                    // Handle numeric types
                    if ($type === 'float') {
                        if (!is_numeric($form_data->$key)) {
                            $error_log[] = array("key"=>$key,"message"=>"must be a number");
                        } else {
                            $form_data->$key = (float) $form_data->$key;
                        }
                    } 
                    else if (strpos($type, 'int') === 0) {
                        // Handles int(11), tinyint(1), etc.
                        if (!is_numeric($form_data->$key)) {
                            $error_log[] = array("key"=>$key,"message"=>"must be an integer");
                        } else {
                            $form_data->$key = (int) $form_data->$key;
                        }
                    }
                    else if ($type === 'bool' || $type === 'tinyint(1)') {
                        // Convert to boolean/tinyint
                        if ($form_data->$key===1 || $form_data->$key===true || $form_data->$key==='1' || $form_data->$key==='true') {
                            $form_data->$key = 1;
                        } else {
                            $form_data->$key = 0;
                        }
                    }
                    // Handle all text types
                    else {
                        // Validate text content

                        // If the text/varchar has options then check that the value is in the options list
                        if (isset($this->schema_meta[$key]['options'])) {
                            if (!in_array($form_data->$key, $this->schema_meta[$key]['options'])) {
                                $error_log[] = array("key"=>$key,"message"=>"invalid option");
                            }
                        } else {

                            // No options so validate text
                            $filter_regex = false;
                            if (isset($this->schema_meta[$key]['filter_regex'])) {
                                $filter_regex = $this->schema_meta[$key]['filter_regex'];
                            }

                            $validation_result = $this->validate_text($form_data->$key, $filter_regex);
                            if ($validation_result['success']==false) {
                                $error_log[] = array("key"=>$key,"message"=>$validation_result['message']);
                            } else {
                                $form_data->$key = $validation_result['text'];
                            }
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

    public function validate_text($original_text, $filter_regex=false) {

        if (is_numeric($original_text)) {
            $original_text = (string) $original_text;
        }

        // Check for html tags 
        $text = strip_tags($original_text);
        if ($text !== $original_text) {
            return array("success"=>false, "message"=>"HTML tags are not allowed");
        }
        
        // Block common XSS patterns (always enforced)
        $xss_patterns = array(
            '/on\w+\s*=/i',           // onclick=, onerror=, onload=, etc.
            '/javascript:/i',          // javascript: protocol
            '/data:text\/html/i',      // data: protocol
            '/<script/i',              // script tags (backup)
            '/<iframe/i',              // iframe tags
            '/expression\s*\(/i'       // CSS expression()
        );
        
        foreach ($xss_patterns as $pattern) {
            if (preg_match($pattern, $original_text)) {
                return array("success"=>false, "message"=>"Potentially malicious content detected");
            }
        }
        
        // Apply character filtering
        if ($filter_regex) {
            // Special case: URL validation
            if ($filter_regex === 'url') {
                if (!filter_var($original_text, FILTER_VALIDATE_URL)) {
                    return array("success"=>false, "message"=>"Invalid URL format");
                }
            } 
            // Standard regex filtering
            else {
                $text = preg_replace($filter_regex, '', $original_text);
                if ($text !== $original_text) {
                    return array("success"=>false, "message"=>"Invalid characters detected");
                }
            }
        }

        // Trim whitespace
        $text = trim($original_text);

        return array("success"=>true, "text"=>$text);
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

        // Check that the app_id, readkey and userid are valid
        // Check that the readkey matches the userid
        // If they are valid and the userid has not been created on heatpumpmonitor.org we also create the user




        $new_system = false;
        if ($systemid==false) {

            // Determine owner of system based on app_id first
            $available_apps = $this->available_apps($userid);

            // Foreach available_apps check if form_data->app_id and form_data->readkey match
            $system_owner = $userid;
            foreach ($available_apps as $app) {
                if (isset($form_data->app_id) && isset($form_data->readkey)) {
                    if ($app['id']==$form_data->app_id && $app['readkey']==$form_data->readkey) {
                        // Get the userid for this app
                        $system_owner = $app['userid'];
                        break;
                    }
                }
            }

            $systemid = $this->create($system_owner);
            $new_system = $systemid;
        }

        // Check if user has access
        if ($this->has_write_access($userid,$systemid)==false) {
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
        if ($this->has_write_access($userid,$systemid)==false) {
            return array("success"=>false, "message"=>"Invalid access");
        }
        // Delete the system
        $this->mysqli->query("DELETE FROM system_meta WHERE id='$systemid'");

        // Delete any access control entries
        // $this->mysqli->query("DELETE FROM system_access WHERE systemid='$systemid'");

        return array("success"=>true, "message"=>"Deleted");
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
        
        // Calculate and add monitoring boundary
        $boundary_data = $this->calculate_boundary($row);
        $row->boundary_code = $boundary_data['boundary_code'];
        $row->boundary_metering = $boundary_data['boundary_metering'];
        
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
        $result = $this->mysqli->query("SELECT username, apikey_read, apikey_write FROM users WHERE id='$userid'");
        if (!$row = $result->fetch_object()) {
            return array(
                "success"=>false,
                "message"=>"User does not exist"
            );
        }
        
        if (!$row->apikey_read) {
            return array(
                "success"=>false,
                "message"=>"User does not have a read API key"
            );
        }

        // Master account
        $myheatpump_apps = $this->append_app_list(array(), $userid, $row->username, $row->apikey_read);

        // Get sub accounts from local accounts table
        $result = $this->mysqli->query("SELECT u.id, u.username, u.apikey_read FROM accounts a JOIN users u ON a.linkeduser = u.id WHERE a.adminuser = '$userid'");
        $accounts = array();
        while ($row = $result->fetch_object()) {
            $accounts[] = array(
                'userid' => (int) $row->id,
                'username' => $row->username,
                'apikey_read' => $row->apikey_read
            );
        }
        
        foreach ($accounts as $account) {
            $myheatpump_apps = $this->append_app_list($myheatpump_apps, $account['userid'], $account['username'], $account['apikey_read']);
        }

        return $myheatpump_apps;
    }

    private function append_app_list($myheatpump_apps, $userid, $username, $readkey) {

        $userid = (int) $userid;

        $emoncms_mysqli = connect_emoncms_database();

        $result = $emoncms_mysqli->query("SELECT * FROM app WHERE userid='$userid'");
        while ($app_row = $result->fetch_object()) {
            if (!isset($app_row->app)) {
                continue;
            }

            if ($app_row->app=="myheatpump") {

                // Generate url
                $url = $this->host."/app/view?name=".$app_row->name."&readkey=".$readkey;

                // Check if app is already added, skip if it is
                $result = $this->mysqli->query("SELECT id FROM system_meta WHERE url='$url'");
                if ($result->num_rows>0) {
                    $app_row->in_use = 1;
                } else {
                    $app_row->in_use = 0;
                }

                $myheatpump_apps[] = array(
                    "id" => (int) $app_row->id,
                    "userid" => $userid,
                    "username" => $username,
                    "name" => $app_row->name,
                    "readkey" => $readkey,
                    "url" => $url,
                    "public" => (int) $app_row->public,
                    "in_use" => $app_row->in_use
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
    
    // Access control

    public function has_write_access($userid,$systemid) {
        $userid = (int) $userid;
        $systemid = (int) $systemid;

        $result = $this->mysqli->query("SELECT userid FROM system_meta WHERE id='$systemid'");
        if (!$row = $result->fetch_object()) {
            return false;
        }

        // 1. The user owns the system
        if ($userid === intval($row->userid)) {
            return true;
        }

        // 2. The user is an admin
        if ($this->is_admin($userid)) {
            return true;
        }

        // 3. The user is an admin of the account that owns the system
        $result = $this->mysqli->query("SELECT COUNT(*) as count FROM accounts WHERE linkeduser='{$row->userid}' AND adminuser='$userid'");
        if ($row = $result->fetch_object()) {
            if ($row->count>0) {
                return true;
            }
        }

        // 3. User has been granted write access via the system_access table
        // $result = $this->mysqli->query("SELECT access FROM system_access WHERE systemid='$systemid' AND userid='$userid'");
        // if ($row = $result->fetch_object()) {
        //     if ($row->access==2) {
        //         return true;
        //     }
        // }

        return false;
    }

    public function has_read_access($userid, $systemid) {
        $userid = (int) $userid;
        $systemid = (int) $systemid;

        // A user has read access to a system:
        // - in all cases if the user is an admin
        // - if the user owns the system
        // - if the system is both shared and published
        
        $result = $this->mysqli->query("SELECT userid,share,published FROM system_meta WHERE id='$systemid'");
        if (!$row = $result->fetch_object()) {
            return false;
        }

        // 1. The system is public anyway
        if (intval($row->share) === 1 && intval($row->published) === 1) {
            return true;
        }

        // 2. The user owns the system
        if ($userid === (int) $row->userid) {
            return true;
        }

        // 3. The user is an admin
        if ($this->is_admin($userid)) {
            return true;
        }

        // 4. The user is an admin of the account that owns the system
        $result = $this->mysqli->query("SELECT COUNT(*) as count FROM accounts WHERE linkeduser='{$row->userid}' AND adminuser='$userid'");
        if ($row = $result->fetch_object()) {
            if ($row->count>0) {
                return true;
            }
        }

        // 4. User has been granted access via the system_access table
        // $result = $this->mysqli->query("SELECT access FROM system_access WHERE systemid='$systemid' AND userid='$userid'");
        // if ($row = $result->fetch_object()) {
        //     if ($row->access>0) {
        //         return true;
        //     }
        // }

        return false;
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

    /**
     * Calculate monitoring boundary (H1-H4) based on system configuration
     * 
     * Based on SEPEMO (Seasonal Performance factor and Monitoring) definitions:
     * H1: Only includes the energy input to the heat pump compressor
     * H2: Includes compressor and source fan(s) or brine pump(s)
     * H3: Includes all energy inputs from H2 plus additional auxiliary energy (backup/immersion heaters)
     * H4: Covers all energy inputs from H3, plus building circulation pump(s) or fans
     * 
     * @param object $system System metadata object
     * @return array Array with 'boundary_code' (int 1-4) and 'boundary_metering' (object with boolean flags)
     */
    public function calculate_boundary($system) {
        $type = isset($system->hp_type) ? $system->hp_type : '';
        
        // Brine pump
        $metering_inc_brine = isset($system->metering_inc_brine_pumps) ? $system->metering_inc_brine_pumps : 0;
        
        // Backup heater
        $uses_backup = isset($system->uses_backup_heater) ? $system->uses_backup_heater : 0;
        $metering_inc_backup = isset($system->metering_inc_boost) ? $system->metering_inc_boost : 0;
        
        // Immersion
        $uses_immersion = isset($system->legionella_immersion) ? $system->legionella_immersion : 0;
        $metering_inc_immersion = isset($system->metering_inc_immersion) ? $system->metering_inc_immersion : 0;
        
        // Primary pumps
        $metering_inc_primary_pump = isset($system->metering_inc_central_heating_pumps) ? $system->metering_inc_central_heating_pumps : 0;
        
        // Secondary pumps
        $hydraulic_separation = isset($system->hydraulic_separation) ? $system->hydraulic_separation : 'None';
        $metering_inc_secondary_pumps = isset($system->metering_inc_secondary_heating_pumps) ? $system->metering_inc_secondary_heating_pumps : 0;
        
        // Start at boundary 4
        $boundary_code = 4;
        
        // Calculate metering flags
        $is_ground_or_water = ($type == "Ground Source" || $type == "Water Source");
        $is_air_to_air = ($type == "Air-to-Air");
        
        $brine_pump_metered = $is_ground_or_water ? ($metering_inc_brine == 1) : null;
        $primary_pump_metered = $metering_inc_primary_pump == 1;
        $has_hydraulic_separation = $hydraulic_separation != 'None';
        $secondary_pumps_metered = $has_hydraulic_separation ? ($metering_inc_secondary_pumps == 1) : null;
        $immersion_heater_used = $uses_immersion == 1;
        $immersion_heater_metered = $immersion_heater_used ? ($metering_inc_immersion == 1) : null;
        $backup_heater_used = $uses_backup == 1;
        $backup_heater_metered = $backup_heater_used ? ($metering_inc_backup == 1) : null;
        
        // If hydraulic separation is used and secondary pumps are not metered then boundary cannot be higher than 3
        if ($has_hydraulic_separation && $secondary_pumps_metered === false) {
            $boundary_code = 3;
        }
        
        // If primary pumps are not metered then boundary cannot be higher than 3
        if (!$primary_pump_metered) {
            $boundary_code = 3;
        }
        
        // If immersion heater is used and not metered then boundary cannot be higher than 2
        if ($immersion_heater_used && $immersion_heater_metered === false) {
            $boundary_code = 2;
        }
        
        // If backup heater is used and not metered then boundary cannot be higher than 2
        if ($backup_heater_used && $backup_heater_metered === false) {
            $boundary_code = 2;
        }
        
        // If brine pump is used and not metered then boundary cannot be higher than 1
        if ($is_ground_or_water && $brine_pump_metered === false) {
            $boundary_code = 1;
        }
        
        // Air to air is always 2
        if ($is_air_to_air) {
            $boundary_code = 2;
        }
        
        // Return structured boundary information
        return array(
            'boundary_code' => $boundary_code,
            'boundary_metering' => array(
                'compressor' => true,
                'source_fan_or_brine' => $is_ground_or_water ? $brine_pump_metered : !$is_air_to_air,
                'brine_pump_metered' => $brine_pump_metered,
                'primary_pump_metered' => $primary_pump_metered,
                'secondary_pumps_metered' => $secondary_pumps_metered,
                'immersion_heater_used' => $immersion_heater_used,
                'immersion_heater_metered' => $immersion_heater_metered,
                'backup_heater_used' => $backup_heater_used,
                'backup_heater_metered' => $backup_heater_metered,
                'hydraulic_separation' => $has_hydraulic_separation ? $hydraulic_separation : null
            )
        );
    }

    // Get last updated time for heatpump_elec and heatpump_heat
    public function get_last_updated($app_id) {
        $app_id = (int) $app_id;
        global $redis;

        if ($app_id==0) {
            return false;
        }

        // Get app row
        $result = $this->emoncms_mysqli->query("SELECT * FROM app WHERE id='$app_id' LIMIT 1");
        if (!$app_row = $result->fetch_object()) {
            return false;
        }

        // Get app config
        $config = json_decode($app_row->config);

        $now = time();
        $heatpump_elec_feedid = false;
        $heatpump_heat_feedid = false;

        // Oldest time is 100 years ago

        $elec_ago = 876000;
        $heat_ago = 876000;
        $max_age = 876000;
        if (isset($config->heatpump_elec)) {
            $heatpump_elec_feedid = (int) $config->heatpump_elec;
            $elec_last_updated = $redis->hget("feed:$heatpump_elec_feedid",'time');
            if ($elec_last_updated>0) {
                $elec_ago = ($now - $elec_last_updated)/3600;
                $max_age = $elec_ago;
            }
        }

        if (isset($config->heatpump_heat)) {
            $heatpump_heat_feedid = (int) $config->heatpump_heat;
            $heat_last_updated = $redis->hget("feed:$heatpump_heat_feedid",'time');
            if ($heat_last_updated>0) {
                $heat_ago = ($now - $heat_last_updated)/3600;
                if ($max_age===876000 || $heat_ago>$max_age) {
                    $max_age = $heat_ago;
                }
            }
        }


        return array(
            "elec_feedid" => $heatpump_elec_feedid,
            "heat_feedid" => $heatpump_heat_feedid,
            "elec_ago" => $elec_ago,
            "heat_ago" => $heat_ago,
            "max_age" => $max_age
        );
    }
}
