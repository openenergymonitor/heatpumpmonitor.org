<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class SystemStats
{
    private $mysqli;
    private $system;
    private $redis;
    private $host;
    
    public $schema = array();

    public function __construct($mysqli,$system)
    {
        $this->mysqli = $mysqli;
        $this->system = $system;

        global $settings;
        $this->host = $settings['emoncms_host'];
        
        global $redis;
        $this->redis = $redis;

        $schema = array();
        require "Modules/system/system_schema.php";

        $this->schema['system_stats_monthly_v2'] = $this->system->populate_codes($schema['system_stats_monthly_v2']);
        $this->schema['system_stats_last7_v2'] = $this->system->populate_codes($schema['system_stats_last7_v2']);
        $this->schema['system_stats_last30_v2'] = $this->system->populate_codes($schema['system_stats_last30_v2']);
        $this->schema['system_stats_last90_v2'] = $this->system->populate_codes($schema['system_stats_last90_v2']);
        $this->schema['system_stats_last365_v2'] = $this->system->populate_codes($schema['system_stats_last365_v2']);
        $this->schema['system_stats_custom'] = $this->system->populate_codes($schema['system_stats_custom']);
        $this->schema['system_stats_all_v2'] = $this->system->populate_codes($schema['system_stats_all_v2']);
        $this->schema['system_stats_daily'] = $this->system->populate_codes($schema['system_stats_daily']);
    }
    
    // --------------------------------------------------------------------------------------------

    // Get system config with meta data (server, apikey)
    public function get_system_config_with_meta($userid, $systemid)
    {
        $userid = (int) $userid;
        $systemid = (int) $systemid;

        if (!$this->system->has_read_access($userid, $systemid)) {
            return array(
                "success" => false,
                "message" => "Invalid access"
            );
        }

        if ($this->redis && $result = $this->redis->get("appconfig:$systemid")) {
            $config = json_decode($result);
            $config->config_cache = true;
            return $config;
        }

        $result = $this->emoncms_app_request($systemid, 'getconfigmeta');
        if ($result['success']) {
            $config = $result['data'];
            $config->server = $this->host;
            $config->apikey = $result['readkey'];
            if ($config->apikey=="") $config->apikey = false;

            if ($this->redis) {
                $this->redis->set("appconfig:$systemid",json_encode($config));
                $this->redis->expire("appconfig:$systemid",10);
            }
            return $config;
        }
        return $result;
    }

    // Load processed stats from emoncms app
    public function load_from_emoncms($systemid, $start = false, $end = false, $api = 'getstats') {
        $params = array();
        if ($start && $end) {
            $params = array("start"=>$start, "end"=>$end);
        }
        return $this->emoncms_app_request($systemid, $api, $params, 'csv');
    }

    // Trigger processing of daily data
    public function process_data($systemid, $timeout = 5) {
        return $this->emoncms_app_request($systemid, 'processdaily', array('timeout' => (int) $timeout));
    }

    // Clear daily data
    public function clear_daily($systemid) {
        global $settings;
        return $this->emoncms_app_request($systemid, 'cleardaily', array('clearkey' => $settings['clearkey']));
    }

    // Enable daily mode
    public function enable_daily_mode($systemid) {
        return $this->emoncms_app_request($systemid, 'enabledailymode');
    }

    // Get data period
    public function get_data_period($systemid) {
        return $this->emoncms_app_request($systemid, 'getdailydatarange');
    }

    // Make request to emoncms app (generic reusable function)
    private function emoncms_app_request($systemid, $action, $params = array(), $format = 'json') 
    {
        $systemid = (int) $systemid;

        // 1. Get app_id and readkey from system_meta table
        $result = $this->mysqli->query("SELECT app_id, readkey FROM system_meta WHERE id='$systemid'");
        if (!$row = $result->fetch_object()) {
            return array("success"=>false, "message"=>"System does not exist");
        }

        // 2. Construct request URL
        $request_url = $this->host."/app/$action.json?id=$row->app_id&apikey=$row->readkey";

        // 3. Add additional parameters
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $request_url .= "&$key=$value";
            }
        }

        // 4. Make the request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // 5. Handle the response
        if ($httpCode == 200) {

            if ($format != 'json') {
                return array("success" => true, "data" => $result, "readkey" => $row->readkey);
            }

            $data = json_decode($result);

            if ($data !== null) {

                if (isset($data->success) && $data->success === false) {
                    return array("success" => false, "http_code" => $httpCode, "message" => $data->message);
                }

                return array(
                    "success" => true, 
                    "data" => $data,
                    "readkey" => $row->readkey
                );
            }
        }

        return array("success" => false, "http_code" => $httpCode, "message" => $result);
    }
    
    // --------------------------------------------------------------------------------------------

    // Save day
    public function save_day($systemid, $row) 
    {
        $systemid = (int) $systemid;
        $timestamp = (int) $row['timestamp'];
        $row['id'] = $systemid;

        // Delete existing stats
        $this->mysqli->query("DELETE FROM system_stats_daily WHERE `id`='$systemid' AND `timestamp`='$timestamp'");

        // Insert new
        $this->save_stats_table('system_stats_daily',$row);

        return array("success" => true, "message" => "Saved");
    }

    public function save_stats_table($table_name,$stats) {
        // Generate query from schema
        $fields = array();
        $qmarks = array();
        $codes = array();
        $values = array();
        foreach ($this->schema[$table_name] as $field => $field_schema) {
            if (isset($stats[$field])) {
                $fields[] = $field;
                $qmarks[] = '?';
                $codes[] = $field_schema['code'];
                $values[] = $stats[$field];
            }
        }
        $fields = implode(',',$fields);
        $qmarks = implode(',',$qmarks);
        $codes = implode('',$codes);

        $stmt = $this->mysqli->prepare("INSERT INTO $table_name ($fields) VALUES ($qmarks)");
        $stmt->bind_param($codes, ...$values);
        $stmt->execute();
        $stmt->close();
    }

    public function get_monthly($start,$end,$system_id = false) {
        return $this->get('system_stats_monthly_v2',$start,$end,$system_id);
    }

    public function get_last7($session_userid, $system_id = false, $mode = "public") {
        return $this->get('system_stats_last7_v2',false,false,$system_id,$session_userid,$mode);
    }

    public function get_last30($session_userid, $system_id = false, $mode = "public") {
        return $this->get('system_stats_last30_v2',false,false,$system_id,$session_userid,$mode);
    }

    public function get_last90($session_userid, $system_id = false, $mode = "public") {
        return $this->get('system_stats_last90_v2',false,false,$system_id,$session_userid,$mode);
    }

    public function get_last365($session_userid, $system_id = false, $mode = "public") {
        return $this->get('system_stats_last365_v2',false,false,$system_id,$session_userid,$mode);
    }

    public function get_custom($session_userid, $system_id = false, $mode = "public") {
        return $this->get('system_stats_custom',false,false,$system_id,$session_userid,$mode);
    }
    
    public function get_all($session_userid, $system_id = false, $mode = "public") {
        return $this->get('system_stats_all_v2',false,false,$system_id,$session_userid,$mode);
    }

    /*
    public function get_custom($session_userid, $system_id = false, $mode = "public", $start = false, $end = false) {
        if (!$start) return false;
        if (!$end) return false;
        if ($mode != "public") return false;

        $date = new DateTime();
        $date->setTimezone(new DateTimeZone('Europe/London'));
        $date->setTime(0, 0, 0);
        // start
        $date->modify($start);
        $start = $date->getTimestamp();
        // end
        $date->modify($end);
        $date->modify('+1 month');
        $end = $date->getTimestamp();

        $stats = array();
        $result = $this->mysqli->query("SELECT id FROM system_meta WHERE share=1 AND published=1"); // OR userid='$userid' (removed for now)
        while ($row = $result->fetch_object()) {
            $system_stats_result = $this->process_from_daily($row->id, $start, $end);
            if ($system_stats_result !== false) {
                $stats[] = $system_stats_result;
            }
        }
        return $stats;
    }*/

    // Get system stats
    public function get($table_name, $start=false, $end=false, $system_id = false, $session_userid = false, $mode = "public")
    {
        // Generate cache key for public mode
        if ($mode === "public" && $start === false && $end === false && $system_id === false) {
            // Try to get from cache first
            if ($this->redis && ($cached_result = $this->redis->get($table_name."_public_cache"))) {
                // return json_decode($cached_result, true);
            }
        }


        $where = '';
        if ($start!==false && $end!==false) {
            $date = new DateTime();
            $date->setTimezone(new DateTimeZone('Europe/London'));
            $date->setTime(0, 0, 0);
            // start
            $date->modify($start);
            $start = $date->getTimestamp();
            // end
            $date->modify($end);
            $date->modify('+1 month');
            $end = $date->getTimestamp();

            $where = "WHERE timestamp>=$start AND timestamp<$end";
        }

        if ($system_id!==false) {
            $system_id = (int) $system_id;
            if ($where=='') {
                $where = "WHERE sm.id=$system_id";
            } else {
                $where .= " AND sm.id=$system_id";
            }
        }

        else if ($mode == "public") {
            if ($where=='') {
                $where = "WHERE sm.published=1 AND sm.share=1";
            } else {
                $where .= " AND sm.published=1 AND sm.share=1";
            }
        }

        else if ($mode == "user" && $session_userid!==false) {
            $session_userid = (int) $session_userid;
            if ($where=='') {
                $where = "WHERE sm.userid=$session_userid";
            } else {
                $where .= " AND sm.userid=$session_userid";
            }
        }
        
        $system_rows = array();
        $result = $this->mysqli->query("SELECT s.* FROM $table_name s JOIN system_meta sm ON s.id = sm.id $where");

        while ($row = $result->fetch_object()) {
            $systemid = $row->id;
            if (!isset($system_rows[$systemid])) {
                $system_rows[$systemid] = array();
            }
            $system_rows[$systemid][] = $row;
        }

        // and sub-account systems
        if ($mode == "user" && $session_userid!==false) {
            $session_userid = (int) $session_userid;
            
            // Build additional where clause for sub-accounts
            $sub_where = '';
            if ($start!==false && $end!==false) {
                $sub_where = "WHERE s.timestamp>=$start AND s.timestamp<$end";
            }
            
            // Add any systems from sub-accounts
            $sub_query = "SELECT s.* FROM $table_name s 
                         JOIN system_meta sm ON s.id = sm.id 
                         JOIN users u ON sm.userid = u.id 
                         JOIN accounts a ON u.id = a.linkeduser 
                         WHERE a.adminuser='$session_userid'";
            
            if ($sub_where != '') {
                $sub_query .= " AND " . str_replace("WHERE ", "", $sub_where);
            }
            
            $result = $this->mysqli->query($sub_query);
            while ($row = $result->fetch_object()) {
                $systemid = $row->id;
                if (!isset($system_rows[$systemid])) {
                    $system_rows[$systemid] = array();
                }
                $system_rows[$systemid][] = $row;
            }
        }


        $stats = array();        
        foreach ($system_rows as $systemid => $rows) {
            $stats[$systemid] = $this->process($rows,$systemid,$start);
            
            if ($stats[$systemid]['combined_cop']!==null) {
                //$stats[$systemid]['combined_cop'] = number_format($stats[$systemid]['combined_cop'],1,'.','')*1;
            }
        }

        // Cache the result if mode is public
        if ($this->redis && $mode === "public" && $start === false && $end === false && $system_id === false && $stats !== false) {
            //$this->redis->set($table_name."_public_cache", json_encode($stats));
            //$this->redis->expire($table_name."_public_cache", 3600); // 1 hour = 3600 seconds
        }

        return $stats;
    }

    public function system_get_monthly($systemid, $start = false, $end = false) {

        $systemid = (int) $systemid;

        if ($start === false || $end === false) {
            $date = new DateTime();
            $date->setTimezone(new DateTimeZone('Europe/London'));
            $date->setTime(0, 0, 0);
            $end = $date->getTimestamp();
            $date->modify("-1 year");
            $start = $date->getTimestamp();        
        }

        $monthly = array();
        $result = $this->mysqli->query("SELECT * FROM system_stats_monthly_v2 WHERE timestamp BETWEEN $start AND $end AND id = $systemid ORDER BY timestamp ASC");
        while ($row = $result->fetch_object()) {
            $monthly[] = $row;
        }
        return $monthly;
    }

    public function process_from_daily($systemid, $start = false, $end = false) {

        $systemid = (int) $systemid;
        
        if ($start===false && $end===false) {
            $where = "WHERE id = $systemid";
        } else {
            $start = (int) $start;
            $end = (int) $end;
            $where = "WHERE timestamp >= $start AND timestamp < $end AND id = $systemid";
        }
        
        $rows = array();

        // Get daily data from system_stats_daily table for this system and time period
        $result = $this->mysqli->query("SELECT * FROM system_stats_daily $where");
        while ($row = $result->fetch_object()) {
            $rows[] = $row;
        }

        return $this->process($rows,$systemid,$start);
    }
    
    public function process($rows,$systemid,$start) {

        $categories = array('combined','running','space','water','cooling');

        $weight_by_heat = true;

        // Totals only
        $totals = array();
        $total_fields = array('elec_kwh','heat_kwh','data_length');
        foreach ($categories as $category) {
            foreach ($total_fields as $field) {
                $totals[$category][$field] = 0;
            }
        }

        // sum x data_length
        $sum = array();
        $sum_length = array();
        $sum_heat_kwh = array();
        $sum_fields = array('elec_mean','heat_mean','flowT_mean','returnT_mean','outsideT_mean','roomT_mean','prc_carnot');
        foreach ($categories as $category) {
            foreach ($sum_fields as $field) {
                $sum[$category][$field] = 0;
                $sum_length[$category][$field] = 0;
                $sum_heat_kwh[$category][$field] = 0;
            }
        }

        // Custom fields
        $totals['combined']['cooling_kwh'] = 0;
        $totals['combined']['starts'] = 0;
        $totals['from_energy_feeds'] = array('elec_kwh'=>0,'heat_kwh'=>0);
        $totals['agile_cost'] = 0;
        $totals['cosy_cost'] = 0;
        $totals['go_cost'] = 0;
        $totals['eon_next_pumped_v2_cost'] = 0;
        $totals['error_air'] = 0;
        $totals['error_air_kwh'] = 0;

        // Quality
        $quality_fields = array('elec','heat','flowT','returnT','outsideT','roomT');
        $quality_totals = array();
        foreach ($quality_fields as $field) {
            $quality_totals[$field] = 0;
        }
        
        // Count days
        $days = 0;

        foreach ($rows as $row) {

            foreach ($categories as $category) {
                foreach ($total_fields as $field) {
                    $totals[$category][$field] += $row->{$category."_".$field};
                }
                foreach ($sum_fields as $field) {
                    if ($row->{$category."_".$field} != null) {
                        if ($weight_by_heat) {
                            $sum[$category][$field] += $row->{$category."_".$field} * $row->{$category."_heat_kwh"};
                        } else {
                            $sum[$category][$field] += $row->{$category."_".$field} * $row->{$category."_data_length"};
                        }
                        $sum_length[$category][$field] += $row->{$category."_data_length"};
                        $sum_heat_kwh[$category][$field] += $row->{$category."_heat_kwh"};
                    }
                }
            }

            foreach ($quality_fields as $field) {
                $quality_totals[$field] += $row->{"quality_".$field};
            }

            $totals['combined']['starts'] += $row->combined_starts*1;
            $totals['combined']['cooling_kwh'] += $row->combined_cooling_kwh;
            $totals['from_energy_feeds']['elec_kwh'] += $row->from_energy_feeds_elec_kwh;
            $totals['from_energy_feeds']['heat_kwh'] += $row->from_energy_feeds_heat_kwh;

            $agile_cost = $row->unit_rate_agile * 0.01 * $totals['from_energy_feeds']['elec_kwh'];
            $totals['agile_cost'] += $agile_cost;

            $cosy_cost = $row->unit_rate_cosy * 0.01 * $totals['from_energy_feeds']['elec_kwh'];
            $totals['cosy_cost'] += $cosy_cost;

            $go_cost = $row->unit_rate_go * 0.01 * $totals['from_energy_feeds']['elec_kwh'];
            $totals['go_cost'] += $go_cost;

            $eon_next_pumped_v2_cost = $row->unit_rate_eon_next_pumped_v2 * 0.01 * $totals['from_energy_feeds']['elec_kwh'];
            $totals['eon_next_pumped_v2_cost'] += $eon_next_pumped_v2_cost;

            $totals['error_air'] += $row->error_air;
            $totals['error_air_kwh'] += $row->error_air_kwh;
            
            $days++;
        }

        if ($days == 0) {
            return false;
        }

        // Calculate mean from sum
        $mean = array();
        foreach ($categories as $category) {
            foreach ($sum_fields as $field) {
                $mean[$category][$field] = null;

                if ($weight_by_heat) {
                    if ($sum_heat_kwh[$category][$field] > 0) {
                        $mean[$category][$field] = $sum[$category][$field] / $sum_heat_kwh[$category][$field];
                    }
                } else {
                    if ($sum_length[$category][$field] > 0) {
                        $mean[$category][$field] = $sum[$category][$field] / $sum_length[$category][$field];
                    }
                }
            }
        }

        // Calculate quality
        $quality = array();
        foreach ($quality_fields as $field) {
            $quality[$field] = 0;
            if ($days > 0) {
                $quality[$field] = $quality_totals[$field] / $days;
            }
        }

        $stats = array(
            'id' => $systemid,
            'timestamp' => $start   
        );

        // As above but without number formatting
        foreach ($categories as $category) {
            $stats[$category.'_elec_kwh'] = $totals[$category]['elec_kwh'];
            $stats[$category.'_heat_kwh'] = $totals[$category]['heat_kwh'];
            if ($totals[$category]['elec_kwh'] > 0) {
                $stats[$category.'_cop'] = $totals[$category]['heat_kwh'] / $totals[$category]['elec_kwh'];
            } else {
                $stats[$category.'_cop'] = null;
            }
            $stats[$category.'_data_length'] = $totals[$category]['data_length'];

            $stats[$category.'_elec_mean'] = $mean[$category]['elec_mean'];
            $stats[$category.'_heat_mean'] = $mean[$category]['heat_mean'];
            $stats[$category.'_flowT_mean'] = $mean[$category]['flowT_mean'];
            $stats[$category.'_returnT_mean'] = $mean[$category]['returnT_mean'];
            $stats[$category.'_outsideT_mean'] = $mean[$category]['outsideT_mean'];
            $stats[$category.'_roomT_mean'] = $mean[$category]['roomT_mean'];
            $stats[$category.'_prc_carnot'] = $mean[$category]['prc_carnot'];
        }

        $stats['combined_cooling_kwh'] = $totals['combined']['cooling_kwh'];
        $stats['combined_starts'] = $totals['combined']['starts'];
        if ($totals['combined']['data_length']>0) {
            $stats['combined_starts_per_hour'] = $totals['combined']['starts'] / ($totals['combined']['data_length'] / 3600.0);
        } else {
            $stats['combined_starts_per_hour'] = 0;
        }
        $stats['from_energy_feeds_elec_kwh'] = $totals['from_energy_feeds']['elec_kwh'];
        $stats['from_energy_feeds_heat_kwh'] = $totals['from_energy_feeds']['heat_kwh'];
        $stats['from_energy_feeds_cop'] = 0;
        if ($totals['from_energy_feeds']['elec_kwh'] > 0) {
            $stats['from_energy_feeds_cop'] = $totals['from_energy_feeds']['heat_kwh'] / $totals['from_energy_feeds']['elec_kwh'];
        }

        foreach ($quality_fields as $field) {
            $stats['quality_'.$field] = $quality[$field];
        }

        $stats['error_air'] = $totals['error_air'];
        $stats['error_air_kwh'] = $totals['error_air_kwh'];

        $stats['unit_rate_agile'] = null;
        $stats['unit_rate_cosy'] = null;
        $stats['unit_rate_go'] = null;
        $stats['unit_rate_eon_next_pumped_v2'] = null;

        if ($totals['from_energy_feeds']['elec_kwh'] > 0) {
            $stats['unit_rate_agile'] = round(100*$totals['agile_cost'] / $totals['from_energy_feeds']['elec_kwh'],1);
            $stats['unit_rate_cosy'] = round(100*$totals['cosy_cost'] / $totals['from_energy_feeds']['elec_kwh'],1);
            $stats['unit_rate_go'] = round(100*$totals['go_cost'] / $totals['from_energy_feeds']['elec_kwh'],1);
            $stats['unit_rate_eon_next_pumped_v2'] = round(100*$totals['eon_next_pumped_v2_cost'] / $totals['from_energy_feeds']['elec_kwh'],1);
        }

        if ($stats['unit_rate_agile'] === 0) $stats['unit_rate_agile'] = null;
        if ($stats['unit_rate_cosy'] === 0) $stats['unit_rate_cosy'] = null;
        if ($stats['unit_rate_go'] === 0) $stats['unit_rate_go'] = null;
        if ($stats['unit_rate_eon_next_pumped_v2'] === 0) $stats['unit_rate_eon_next_pumped_v2'] = null;

        $weighted_flowT_sum = 0;
        $weighted_outsideT_sum = 0;
        $weighted_flowT_minus_outsideT_sum = 0;
        $weighted_flowT_minus_returnT_sum = 0;
        $weighted_elec_sum = 0;
        $weighted_heat_sum = 0;
        $total_kwh_elec = 0;
        $total_kwh_heat = 0;
        $total_kwh_heat_running = 0;
        $total_kwh_elec_running = 0;
        $total_kwh_carnot_elec = 0;
        $total_time_on = 0;
        $total_time_total = 0;
        $total_cycle_count = 0;

        foreach ($rows as $row) {
            $kwh_elec = $row->weighted_kwh_elec;
            $kwh_heat = $row->weighted_kwh_heat;

            $weighted_flowT_sum += $row->weighted_flowT * $kwh_heat;
            $weighted_outsideT_sum += $row->weighted_outsideT * $kwh_heat;
            $weighted_flowT_minus_outsideT_sum += ($row->weighted_flowT_minus_outsideT) * $kwh_heat;
            $weighted_flowT_minus_returnT_sum += ($row->weighted_flowT_minus_returnT) * $kwh_heat;
            $weighted_elec_sum += $row->weighted_elec * $kwh_elec;
            $weighted_heat_sum += $row->weighted_heat * $kwh_heat;

            $total_kwh_elec += $kwh_elec;
            $total_kwh_heat += $kwh_heat;

            $total_kwh_heat_running += $row->weighted_kwh_heat_running;
            $total_kwh_elec_running += $row->weighted_kwh_elec_running;
            $total_kwh_carnot_elec += $row->weighted_kwh_carnot_elec;
            $total_time_on += $row->weighted_time_on;
            $total_time_total += $row->weighted_time_total;
            $total_cycle_count += $row->weighted_cycle_count;
        }
        $stats['weighted_flowT'] = null;
        $stats['weighted_outsideT'] = null;
        $stats['weighted_flowT_minus_outsideT'] = null;
        $stats['weighted_flowT_minus_returnT'] = null;
        $stats['weighted_elec'] = null;
        $stats['weighted_heat'] = null;

        if ($total_kwh_heat > 0) {
            $stats['weighted_flowT'] = $weighted_flowT_sum / $total_kwh_heat;
            $stats['weighted_outsideT'] = $weighted_outsideT_sum / $total_kwh_heat;
            $stats['weighted_flowT_minus_outsideT'] = $weighted_flowT_minus_outsideT_sum / $total_kwh_heat;
            $stats['weighted_flowT_minus_returnT'] = $weighted_flowT_minus_returnT_sum / $total_kwh_heat;
            $stats['weighted_heat'] = $weighted_heat_sum / $total_kwh_heat;
        }

        if ($total_kwh_elec > 0) {
            $stats['weighted_elec'] = $weighted_elec_sum / $total_kwh_elec;
        }

        $stats['weighted_prc_carnot'] = 0;
        if ($total_kwh_elec_running > 0 && $total_kwh_carnot_elec > 0) {
            $stats['weighted_prc_carnot'] = 100 * ($total_kwh_heat_running / $total_kwh_elec_running) / ($total_kwh_heat_running / $total_kwh_carnot_elec);
        }

        $stats['weighted_kwh_elec'] = $total_kwh_elec;
        $stats['weighted_kwh_heat'] = $total_kwh_heat;
        $stats['weighted_kwh_heat_running'] = $total_kwh_heat_running;
        $stats['weighted_kwh_elec_running'] = $total_kwh_elec_running;
        $stats['weighted_kwh_carnot_elec'] = $total_kwh_carnot_elec;
        $stats['weighted_time_on'] = $total_time_on;
        $stats['weighted_time_total'] = $total_time_total;
        $stats['weighted_cycle_count'] = $total_cycle_count;

        return $stats;
    }

    public function get_daily($systemid, $start, $end, $fields) {

        $systemid = (int) $systemid;
        $start = (int) $start;
        $end = (int) $end;

        // Print header based on system_stats_daily schema
        $all_fields = array();
        foreach ($this->schema['system_stats_daily'] as $field => $field_schema) {
            $all_fields[] = $field;
        }

        if ($fields) {
            $fields = explode(",",$fields);
            $valid_fields = array();
            foreach ($fields as $field) {
                if (in_array($field,$all_fields)) {
                    $valid_fields[] = $field;
                }
            }
            $field_select = implode(",",$valid_fields);
        } else {
            $valid_fields = $all_fields;
            $field_select = "*";
        }
        
        $out = implode(",",$valid_fields)."\n";

        $date = new DateTime();
        $date->setTimezone(new DateTimeZone('Europe/London'));

        if ($start == false || $end == false) {
            $where = "WHERE id=$systemid";
        } else {
            $where = "WHERE id=$systemid AND timestamp>=$start AND timestamp<$end";
        }
        // Get data
        $result = $this->mysqli->query("SELECT $field_select FROM system_stats_daily $where ORDER BY timestamp ASC");
        while ($row = $result->fetch_object()) {
            $data = array();
            foreach ($valid_fields as $field) {
            
                $value = $row->$field;
                
                if ($field == "timestamp") {
                    // convert DateTime
                    // $date->setTimestamp($value);
                    // $value = $date->format('Y-m-d H:i:s');
                }
            
                $data[] = $value;
            }
            $out .= implode(",",$data)."\n";
        }
        
        return $out;
    }
    
    public function export_daily($systemid) 
    {
        $systemid = (int) $systemid;
                    
        $filename = "daily.csv";
        $where = '';
        if ($systemid>0) {
            $where = "WHERE id=$systemid";
            $filename = "daily-$systemid.csv";
        }

        // There is no need for the browser to cache the output
        header("Cache-Control: no-cache, no-store, must-revalidate");

        // Tell the browser to handle output as a csv file to be downloaded
        header('Content-Description: File Transfer');
        header("Content-type: application/octet-stream");
        header("Content-Disposition: attachment; filename=$filename");

        header("Expires: 0");
        header("Pragma: no-cache");

        // Write to output stream
        $fh = @fopen( 'php://output', 'w' );
        
        // Print header based on system_stats_daily schema
        $header = array();
        foreach ($this->schema['system_stats_daily'] as $field => $field_schema) {
            $header[] = $field;
        }
        fputcsv($fh, $header);

        // Get data
        $result = $this->mysqli->query("SELECT * FROM system_stats_daily $where");
        while ($row = $result->fetch_object()) {
            $data = array();
            foreach ($this->schema['system_stats_daily'] as $field => $field_schema) {
                $data[] = $row->$field;
            }
            fputcsv($fh, $data);
        }
        
        fclose($fh);
        exit;
    }

    /**
     * Calculate average winter statistics across all public systems
     * Winter defined as November through February (months 11, 12, 1, 2)
     * 
     * @return array Statistics including:
     *   - avg_winter_heat_output: Average winter heat output (kWh)
     *   - avg_winter_elec_input: Average winter electrical input (kWh)
     *   - avg_winter_indoor_temp: Average indoor temperature during winter
     *   - avg_elec_coldest_day: Average electrical input on coldest day (kWh)
     */
    public function get_winter_summary() {
        // Query winter months (Nov, Dec, Jan, Feb) from monthly stats
        // MONTH() returns 1-12, so winter = 11, 12, 1, 2
        $query = "
            SELECT 
                AVG(combined_heat_kwh) as avg_winter_heat_output,
                AVG(combined_elec_kwh) as avg_winter_elec_input,
                AVG(combined_roomT_mean) as avg_winter_indoor_temp
            FROM system_stats_monthly_v2 sm
            JOIN system_meta meta ON sm.id = meta.id
            WHERE meta.share = 1 
                AND meta.published = 1
                AND (MONTH(FROM_UNIXTIME(sm.timestamp)) IN (11, 12, 1, 2))
                AND combined_heat_kwh IS NOT NULL
                AND combined_elec_kwh IS NOT NULL
                AND combined_roomT_mean IS NOT NULL
        ";
        
        $result = $this->mysqli->query($query);
        $stats = $result->fetch_object();
        
        // Get average electrical input on coldest day
        // This uses the measured data from system_meta which is populated by coldest_day.php script
        $coldest_query = "
            SELECT 
                AVG(daily.combined_elec_kwh) as avg_elec_coldest_day
            FROM system_meta meta
            JOIN system_stats_daily daily ON meta.id = daily.id
            WHERE meta.share = 1 
                AND meta.published = 1
                AND meta.measured_outside_temp_coldest_day IS NOT NULL
                AND daily.weighted_outsideT = meta.measured_outside_temp_coldest_day
                AND daily.combined_elec_kwh IS NOT NULL
        ";
        
        $coldest_result = $this->mysqli->query($coldest_query);
        $coldest_stats = $coldest_result->fetch_object();
        
        return array(
            'avg_winter_heat_output' => $stats->avg_winter_heat_output ? round($stats->avg_winter_heat_output, 1) : 0,
            'avg_winter_elec_input' => $stats->avg_winter_elec_input ? round($stats->avg_winter_elec_input, 1) : 0,
            'avg_winter_indoor_temp' => $stats->avg_winter_indoor_temp ? round($stats->avg_winter_indoor_temp, 1) : 0,
            'avg_elec_coldest_day' => $coldest_stats->avg_elec_coldest_day ? round($coldest_stats->avg_elec_coldest_day, 1) : 0
        );
    }

    /**
     * Get winter summary statistics for a specific system
     * Returns winter performance metrics for a single system
     * 
     * @param int $system_id The system ID to get winter stats for
     * @return array Array containing:
     *   - avg_winter_heat_output: Average winter heat output (kWh)
     *   - avg_winter_elec_input: Average winter electrical input (kWh)
     *   - avg_winter_indoor_temp: Average indoor temperature during winter
     *   - avg_elec_coldest_day: Average electrical input on coldest day (kWh)
     */
    public function get_winter_summary_for_system($system_id) {
        // Query winter months (Nov, Dec, Jan, Feb) from monthly stats for specific system
        // MONTH() returns 1-12, so winter = 11, 12, 1, 2
        $stmt = $this->mysqli->prepare("
            SELECT 
                AVG(combined_heat_kwh) as avg_winter_heat_output,
                AVG(combined_elec_kwh) as avg_winter_elec_input,
                AVG(combined_roomT_mean) as avg_winter_indoor_temp
            FROM system_stats_monthly_v2 sm
            JOIN system_meta meta ON sm.id = meta.id
            WHERE sm.id = ?
                AND (MONTH(FROM_UNIXTIME(sm.timestamp)) IN (11, 12, 1, 2))
                AND combined_heat_kwh IS NOT NULL
                AND combined_elec_kwh IS NOT NULL
                AND combined_roomT_mean IS NOT NULL
        ");
        
        $stmt->bind_param("i", $system_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = $result->fetch_object();
        
        // Get average electrical input on coldest day for this specific system
        $coldest_stmt = $this->mysqli->prepare("
            SELECT 
                daily.combined_elec_kwh as avg_elec_coldest_day
            FROM system_meta meta
            JOIN system_stats_daily daily ON meta.id = daily.id
            WHERE meta.id = ?
                AND meta.measured_outside_temp_coldest_day IS NOT NULL
                AND daily.weighted_outsideT = meta.measured_outside_temp_coldest_day
                AND daily.combined_elec_kwh IS NOT NULL
        ");
        
        $coldest_stmt->bind_param("i", $system_id);
        $coldest_stmt->execute();
        $coldest_result = $coldest_stmt->get_result();
        $coldest_stats = $coldest_result->fetch_object();
        
        return array(
            'avg_winter_heat_output' => $stats->avg_winter_heat_output ? round($stats->avg_winter_heat_output, 1) : null,
            'avg_winter_elec_input' => $stats->avg_winter_elec_input ? round($stats->avg_winter_elec_input, 1) : null,
            'avg_winter_indoor_temp' => $stats->avg_winter_indoor_temp ? round($stats->avg_winter_indoor_temp, 1) : null,
            'avg_elec_coldest_day' => $coldest_stats->avg_elec_coldest_day ? round($coldest_stats->avg_elec_coldest_day, 1) : null
        );
    }
}
