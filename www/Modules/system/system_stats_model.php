<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class SystemStats
{
    private $mysqli;
    private $system;
    private $redis;
    
    public $schema = array();

    public function __construct($mysqli,$system)
    {
        $this->mysqli = $mysqli;
        $this->system = $system;
        
        global $redis;
        $this->redis = $redis;

        $schema = array();
        require "Modules/system/system_schema.php";

        $this->schema['system_stats_monthly_v2'] = $this->system->populate_codes($schema['system_stats_monthly_v2']);
        $this->schema['system_stats_last7_v2'] = $this->system->populate_codes($schema['system_stats_last7_v2']);
        $this->schema['system_stats_last30_v2'] = $this->system->populate_codes($schema['system_stats_last30_v2']);
        $this->schema['system_stats_last90_v2'] = $this->system->populate_codes($schema['system_stats_last90_v2']);
        $this->schema['system_stats_last365_v2'] = $this->system->populate_codes($schema['system_stats_last365_v2']);
        $this->schema['system_stats_all_v2'] = $this->system->populate_codes($schema['system_stats_all_v2']);
        $this->schema['system_stats_daily'] = $this->system->populate_codes($schema['system_stats_daily']);
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
        if ($row->share == 1 && $row->published == 1) {
            return true;
        }

        // 2. The user owns the system
        if ($userid === $row->userid) {
            return true;
        }

        // 3. The user is an admin
        if ($this->is_admin($userid)) {
            return true;
        }

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


    public function get_system_config($userid, $systemid)
    {
        // get config if owned by user or public
        $userid = (int) $userid;
        $systemid = (int) $systemid;

        if (!$this->has_read_access($userid, $systemid)) {
            return array(
                "success" => false,
                "message" => "Invalid access"
            );
        }

        $result = $this->mysqli->query("SELECT app_id, readkey FROM system_meta WHERE id='$systemid'");
        if (!$row = $result->fetch_object()) {
            return array("success"=>false, "message"=>"System does not exist");   
        }

        try {
            $result = file_get_contents("https://emoncms.org/app/getconfig.json?id=$row->app_id&apikey=$row->readkey");
        } catch (Exception $e) {
            return array(
                "success"=>false, 
                "message"=>"Error fetching config meta"
            );
        }
    
        $config = json_decode($result);  
        
        if (!$config) {
            return array(
                "success"=>false, 
                "message"=>"Empty response from detailed data server"
            );
        }

        $output = new stdClass();
        $output->elec = (int) $config->config->heatpump_elec;
        $output->heat = (int) $config->config->heatpump_heat;
        $output->flowT = (int) $config->config->heatpump_flowT;
        $output->returnT = (int) $config->config->heatpump_returnT;

        if (isset($config->config->heatpump_outsideT)) {
            $output->outsideT = (int) $config->config->heatpump_outsideT;
        }
        $output->server = "https://emoncms.org";
        $output->apikey = $row->readkey;

        return $output;
    }
    
    public function get_system_config_with_meta($userid, $systemid)
    {
        $userid = (int) $userid;
        $systemid = (int) $systemid;

        if (!$this->has_read_access($userid, $systemid)) {
            return array(
                "success" => false,
                "message" => "Invalid access"
            );
        }

        if ($result = $this->redis->get("appconfig:$systemid")) {
            $config = json_decode($result);
            $config->config_cache = true;
            return $config;
        }

        $result = $this->mysqli->query("SELECT app_id, readkey FROM system_meta WHERE id='$systemid'");
        if (!$row = $result->fetch_object()) {
            return array("success"=>false, "message"=>"System does not exist");   
        }

        try {
            $result = file_get_contents("https://emoncms.org/app/getconfigmeta.json?id=$row->app_id&apikey=$row->readkey");
        } catch (Exception $e) {
            return array(
                "success"=>false, 
                "message"=>"Error fetching config meta"
            );
        }
    
        $config = json_decode($result);  
        
        if (!$config) {
            return array(
                "success"=>false, 
                "message"=>"Empty response from detailed data server"
            );
        }

        $config->server = "https://emoncms.org";
        $config->apikey = $row->readkey;
        if ($config->apikey=="") $config->apikey = false;

        $this->redis->set("appconfig:$systemid",json_encode($config));
        $this->redis->expire("appconfig:$systemid",10);

        return $config;
    }

    public function load_from_emoncms($systemid, $start = false, $end = false, $api = 'getstats')
    {
        $systemid = (int) $systemid;

        $result = $this->mysqli->query("SELECT app_id, readkey FROM system_meta WHERE id='$systemid'");
        if (!$row = $result->fetch_object()) {
            return array("success"=>false, "message"=>"System does not exist");   
        }

        $getstats = "https://emoncms.org/app/$api.json?id=$row->app_id&apikey=$row->readkey";
        if ($start && $end) {
            $getstats .= "&start=$start&end=$end";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $getstats);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $stats_rx = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) { // Check if successful response
            return $stats_rx;
        } else {
            return false; // Or handle error as needed
        }

        if (!$stats = json_decode($stats_rx)) {
            return array("success" => false, "message" => $stats_rx);
        } else {
            return array("success" => true, "stats" => $stats);
        }
    }

    public function process_data($systemid, $timeout = 5) 
    {
        $systemid = (int) $systemid;
        $timeout = (int) $timeout;

        $result = $this->mysqli->query("SELECT app_id, readkey FROM system_meta WHERE id='$systemid'");
        if (!$row = $result->fetch_object()) {
            return array("success"=>false, "message"=>"System does not exist");   
        }
        $getstats = "https://emoncms.org/app/processdaily.json?id=$row->app_id&apikey=$row->readkey&timeout=$timeout";     
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $getstats);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) { // Check if successful response
            return json_decode($result,true);
        } else {
            return array("success" => false, "message" => $httpCode);
        }
    }

    public function clear_daily($systemid) {

        global $settings;
        $clearkey = $settings['clearkey'];

        $systemid = (int) $systemid;

        $result = $this->mysqli->query("SELECT app_id, readkey FROM system_meta WHERE id='$systemid'");
        if (!$row = $result->fetch_object()) {
            return array("success"=>false, "message"=>"System does not exist");   
        }
        $request_url = "https://emoncms.org/app/cleardaily.json?id=$row->app_id&apikey=$row->readkey&clearkey=$clearkey";  
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) { // Check if successful response
            return json_decode($result,true);
        } else {
            return array("success" => false, "message" => $httpCode);
        }
    }

    public function enable_daily_mode($systemid) 
    {
        $systemid = (int) $systemid;

        $result = $this->mysqli->query("SELECT app_id, readkey FROM system_meta WHERE id='$systemid'");
        if (!$row = $result->fetch_object()) {
            return array("success"=>false, "message"=>"System does not exist");   
        }
        $request_url = "https://emoncms.org/app/enabledailymode.json?id=$row->app_id&apikey=$row->readkey";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) { // Check if successful response
            return json_decode($result,true);
        } else {
            return array("success" => false, "message" => $httpCode);
        }
    }

    public function get_data_period($systemid)
    {
        $systemid = (int) $systemid;

        $result = $this->mysqli->query("SELECT app_id, readkey FROM system_meta WHERE id='$systemid'");
        if (!$row = $result->fetch_object()) {
            return array("success"=>false, "message"=>"System does not exist");   
        }
        $request_url = "https://emoncms.org/app/getdailydatarange.json?id=$row->app_id&apikey=$row->readkey";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $stats_rx = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) { // Check if successful response
            if (!$period = json_decode($stats_rx)) {
                return array("success" => false, "message" => $stats_rx);
            } else {
                return array("success" => true, "period" => $period);
            }
        } else {
            return array("success" => false, "message" => $httpCode);
        }
    }


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

    public function get_last7($system_id = false) {
        return $this->get('system_stats_last7_v2',false,false,$system_id);
    }

    public function get_last30($system_id = false) {
        return $this->get('system_stats_last30_v2',false,false,$system_id);
    }
   
    public function get_last90($system_id = false) {
        return $this->get('system_stats_last90_v2',false,false,$system_id);
    }

    public function get_last365($system_id = false) {
        return $this->get('system_stats_last365_v2',false,false,$system_id);
    }
    
    public function get_all($system_id = false) {
        return $this->get('system_stats_all_v2',false,false,$system_id);
    }

    // Get system stats
    public function get($table_name, $start=false, $end=false, $system_id = false)
    {
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
            if ($where=='') {
                $where = "WHERE id=$system_id";
            } else {
                $where .= " AND id=$system_id";
            }
        }
        
        $system_rows = array();
        $result = $this->mysqli->query("SELECT * FROM $table_name $where");
        while ($row = $result->fetch_object()) {
            $systemid = $row->id;
            if (!isset($system_rows[$systemid])) {
                $system_rows[$systemid] = array();
            }
            $system_rows[$systemid][] = $row;
        }

        $stats = array();        
        foreach ($system_rows as $systemid => $rows) {
            $stats[$systemid] = $this->process($rows,$systemid,$start);
            
            if ($stats[$systemid]['combined_cop']!==null) {
                //$stats[$systemid]['combined_cop'] = number_format($stats[$systemid]['combined_cop'],1,'.','')*1;
            }
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

        // Weighted average calculations
        /*
    'weighted_flowT' => array('type' => 'float', 'name'=>'Weighted flowT', 'group'=>'Weighted averages', 'dp'=>1, 'unit'=>'°C'),
    'weighted_outsideT' => array('type' => 'float', 'name'=>'Weighted outsideT', 'group'=>'Weighted averages', 'dp'=>1, 'unit'=>'°C'),
    'weighted_flowT_minus_outsideT' => array('type' => 'float', 'name'=>'Weighted flowT - outsideT', 'group'=>'Weighted averages', 'dp'=>1, 'unit'=>'°C'),
    'weighted_flowT_minus_returnT' => array('type' => 'float', 'name'=>'Weighted flowT - returnT', 'group'=>'Weighted averages', 'dp'=>1, 'unit'=>'°C'),
    'weighted_elec' => array('type' => 'float', 'name'=>'Weighted elec', 'group'=>'Weighted averages', 'dp'=>0, 'unit'=>'W'),
    'weighted_heat' => array('type' => 'float', 'name'=>'Weighted heat', 'group'=>'Weighted averages', 'dp'=>0, 'unit'=>'W'),
    'weighted_prc_carnot' => array('type' => 'float', 'name'=>'Weighted % Carnot', 'group'=>'Weighted averages', 'dp'=>1, 'unit'=>'%'),
    'weighted_kwh_elec' => array('type' => 'float', 'name'=>'Weighted elec kWh', 'group'=>'Weighted averages', 'dp'=>4, 'unit'=>'kWh'),
    'weighted_kwh_heat' => array('type' => 'float', 'name'=>'Weighted heat kWh', 'group'=>'Weighted averages', 'dp'=>4, 'unit'=>'kWh'),
    'weighted_kwh_heat_running' => array('type' => 'float', 'name'=>'Weighted heat running kWh', 'group'=>'Weighted averages', 'dp'=>4, 'unit'=>'kWh'),
    'weighted_kwh_elec_running' => array('type' => 'float', 'name'=>'Weighted elec running kWh', 'group'=>'Weighted averages', 'dp'=>4, 'unit'=>'kWh'),
    'weighted_kwh_carnot_elec' => array('type' => 'float', 'name'=>'Weighted Carnot elec kWh', 'group'=>'Weighted averages', 'dp'=>4, 'unit'=>'kWh'),
    'weighted_time_on' => array('type' => 'float', 'name'=>'Weighted time on', 'group'=>'Weighted averages', 'dp'=>0, 'unit'=>'s'),
    'weighted_time_total' => array('type' => 'float', 'name'=>'Weighted time total', 'group'=>'Weighted averages', 'dp'=>0, 'unit'=>'s'),
    'weighted_cycle_count' => array('type' => 'float', 'name'=>'Weighted cycle count', 'group'=>'Weighted averages', 'dp'=>0, 'unit'=>''),
    */


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
    
    public function export_daily($systemid) {
    
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
}
