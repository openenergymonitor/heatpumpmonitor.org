<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class SystemStats
{
    private $mysqli;
    private $system;
    public $schema = array();

    public function __construct($mysqli,$system)
    {
        $this->mysqli = $mysqli;
        $this->system = $system;

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

    public function load_from_url($url, $start = false, $end = false, $api = 'getstats2')
    {
        # decode the url to separate out any args
        $url_parts = parse_url($url);
        $server = $url_parts['scheme'] . '://' . $url_parts['host'];

        $time_range_query = '';
        if ($start && $end) {
            $time_range_query = "&start=$start&end=$end";
        }

        # check if url was to /app/view instead of username
        if (preg_match('/^(.*)\/app\/view$/', $url_parts['path'], $matches)) {
            $getstats = "$server$matches[1]/app/" . $api . $time_range_query;
        } else {
            $getstats = $server . $url_parts['path'] . "/app/" . $api . $time_range_query;
        }

        # if url has query string, pull out the readkey
        if (isset($url_parts['query'])) {
            parse_str($url_parts['query'], $url_args);
            if (isset($url_args['readkey'])) {
                // $readkey = $url_args['readkey'];
                $getstats .= '?' . $url_parts['query'];
            }
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

    public function get_data_period($url)
    {
        # decode the url to separate out any args
        $url_parts = parse_url($url);
        if (!isset($url_parts['scheme']) || !isset($url_parts['host']) || !isset($url_parts['path'])) {
            return array("success" => false, "message" => "Invalid URL"); 
        }
        $server = $url_parts['scheme'] . '://' . $url_parts['host'];

        # check if url was to /app/view instead of username
        if (preg_match('/^(.*)\/app\/view$/', $url_parts['path'], $matches)) {
            $getstats = "$server$matches[1]/app/datastart";
        } else {
            $getstats = $server . $url_parts['path'] . "/app/datastart";
        }

        # if url has query string, pull out the readkey
        if (isset($url_parts['query'])) {
            parse_str($url_parts['query'], $url_args);
            if (isset($url_args['readkey'])) {
                // $readkey = $url_args['readkey'];
                $getstats .= '?' . $url_parts['query'];
            }
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $getstats);
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

        $categories = array('combined','running','space','water');

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
        $sum_fields = array('elec_mean','heat_mean','flowT_mean','returnT_mean','outsideT_mean','roomT_mean','prc_carnot');
        foreach ($categories as $category) {
            foreach ($sum_fields as $field) {
                $sum[$category][$field] = 0;
            }
        }

        // Custom fields
        $totals['combined']['cooling_kwh'] = 0;
        $totals['from_energy_feeds'] = array('elec_kwh'=>0,'heat_kwh'=>0);

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
                    $sum[$category][$field] += $row->{$category."_".$field} * $row->{$category."_data_length"};
                }
            }

            foreach ($quality_fields as $field) {
                $quality_totals[$field] += $row->{"quality_".$field};
            }

            $totals['combined']['cooling_kwh'] += $row->combined_cooling_kwh;
            $totals['from_energy_feeds']['elec_kwh'] += $row->from_energy_feeds_elec_kwh;
            $totals['from_energy_feeds']['heat_kwh'] += $row->from_energy_feeds_heat_kwh;
            
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
                if ($totals[$category]['data_length'] > 0) {
                    $mean[$category][$field] = $sum[$category][$field] / $totals[$category]['data_length'];
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
        $stats['from_energy_feeds_elec_kwh'] = $totals['from_energy_feeds']['elec_kwh'];
        $stats['from_energy_feeds_heat_kwh'] = $totals['from_energy_feeds']['heat_kwh'];
        $stats['from_energy_feeds_cop'] = 0;
        if ($totals['from_energy_feeds']['elec_kwh'] > 0) {
            $stats['from_energy_feeds_cop'] = $totals['from_energy_feeds']['heat_kwh'] / $totals['from_energy_feeds']['elec_kwh'];
        }

        foreach ($quality_fields as $field) {
            $stats['quality_'.$field] = $quality[$field];
        }

        return $stats;
    }
}
