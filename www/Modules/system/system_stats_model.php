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

        $this->schema['system_stats_monthly'] = $this->system->populate_codes($schema['system_stats_monthly']);
        $this->schema['system_stats_last30'] = $this->system->populate_codes($schema['system_stats_last30']);
        $this->schema['system_stats_last365'] = $this->system->populate_codes($schema['system_stats_last365']);
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

    public function load_from_url($url, $start = false, $end = false)
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
            $getstats = "$server$matches[1]/app/getstats" . $time_range_query;
        } else {
            $getstats = $server . $url_parts['path'] . "/app/getstats" . $time_range_query;
        }

        # if url has query string, pull out the readkey
        if (isset($url_parts['query'])) {
            parse_str($url_parts['query'], $url_args);
            if (isset($url_args['readkey'])) {
                // $readkey = $url_args['readkey'];
                $getstats .= '?' . $url_parts['query'];
            }
        }

        $stats_rx = file_get_contents($getstats);

        if (!$stats = json_decode($stats_rx)) {
            return array("success" => false, "message" => $stats_rx);
        } else {
            return array("success" => true, "stats" => $stats);
        }
    }

    public function save_last30($systemid, $stats)
    {
        $systemid = (int) $systemid;

        // Translate the stats into the schema
        // consider changing API structure to match schema
        $stats = array(
            'id' => $systemid,
            'elec_kwh' => $stats->full_period->elec_kwh,
            'heat_kwh' => $stats->full_period->heat_kwh,
            'cop' => $stats->full_period->cop,
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
            'standby_threshold' => $stats->standby_threshold,
            'standby_kwh' => $stats->standby_kwh,
            "quality_elec" => $stats->quality_elec,
            "quality_heat" => $stats->quality_heat,
            "quality_flow" => $stats->quality_flow,
            "quality_return" => $stats->quality_return,
            "quality_outside" => $stats->quality_outside,
            'data_start' => $stats->data_start
        );

        $id = $stats['id'];
        $this->mysqli->query("DELETE FROM system_stats_last30 WHERE id=$id");
        $this->save_stats_table('system_stats_last30',$stats);

        return array("success" => true, "message" => "Saved");
    }

    public function save_last365($systemid, $stats)
    {
        $systemid = (int) $systemid;

        // Translate the stats into the schema
        // consider changing API structure to match schema
        $stats = array(
            'id' => $systemid,
            'elec_kwh' => $stats->last365->elec_kwh,
            'heat_kwh' => $stats->last365->heat_kwh,
            'cop' => $stats->last365->cop,
            'since' => $stats->last365->since,
            'data_start' => $stats->data_start
        );

        $id = $stats['id'];
        $this->mysqli->query("DELETE FROM system_stats_last365 WHERE id=$id");
        $this->save_stats_table('system_stats_last365',$stats);

        return array("success" => true, "message" => "Saved");
    }

    public function save_monthly($systemid,$start,$stats) 
    {    
        $systemid = (int) $systemid;
        $start = (int) $start;

        // Translate the stats into the schema
        // consider changing API structure to match schema

        if (!isset($stats->data_start)) {
            return false;
        }

        $stats = array(
            'id' => $systemid,
            'timestamp' => $start,
            'elec_kwh' => $stats->full_period->elec_kwh,
            'heat_kwh' => $stats->full_period->heat_kwh,
            'cop' => $stats->full_period->cop,
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
            'standby_threshold' => $stats->standby_threshold,
            'standby_kwh' => $stats->standby_kwh,
            "quality_elec" => $stats->quality_elec,
            "quality_heat" => $stats->quality_heat,
            "quality_flow" => $stats->quality_flow,
            "quality_return" => $stats->quality_return,
            "quality_outside" => $stats->quality_outside,
            'data_start' => $stats->data_start
        );

        $id = $stats['id'];
        $timestamp = $stats['timestamp'];

        // Save stats to system_stats_monthly table
        $this->mysqli->query("DELETE FROM system_stats_monthly WHERE id=$id AND timestamp=$timestamp");
        $this->save_stats_table('system_stats_monthly',$stats);

        return array("success" => true, "message" => "Saved");
    }

    public function save_stats_table($table_name,$stats) {
        // Generate query from schema
        $fields = array();
        $qmarks = array();
        $codes = array();
        $values = array();
        foreach ($this->schema[$table_name] as $field => $field_schema) {
            $fields[] = $field;
            $qmarks[] = '?';
            $codes[] = $field_schema['code'];
            $values[] = $stats[$field];
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
        return $this->get('system_stats_monthly',$start,$end,$system_id);
    }

    public function get_last30($system_id = false) {
        return $this->get('system_stats_last30',false,false,$system_id);
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

        $fields = array(
            "elec_kwh" => array("average" => false, "dp" => 0),
            "heat_kwh" => array("average" => false, "dp" => 0),
            "when_running_elec_kwh" => array("average" => false, "dp" => 0),
            "when_running_heat_kwh" => array("average" => false, "dp" => 0),

            "when_running_flowT" => array("average" => true, "dp" => 1),
            "when_running_returnT" => array("average" => true, "dp" => 1),
            "when_running_flow_minus_return" => array("average" => true, "dp" => 1),
            "when_running_outsideT" => array("average" => true, "dp" => 1),
            "when_running_flow_minus_outside" => array("average" => true, "dp" => 1),
            "when_running_carnot_prc" => array("average" => true, "dp" => 1),

            "standby_threshold" => array("average" => true, "dp" => 0),
            "standby_kwh" => array("average" => false, "dp" => 0),

            "quality_elec" => array("average" => true, "dp" => 0),
            "quality_heat" => array("average" => true, "dp" => 0),
            "quality_flow" => array("average" => true, "dp" => 0),
            "quality_return" => array("average" => true, "dp" => 0),
            "quality_outside" => array("average" => true, "dp" => 0),

            "data_start" => array("average" => false, "dp" => 0)
        );

        $field_str = implode(",", array_keys($fields));

        $available_stats = array();
        $result = $this->mysqli->query("SELECT id,$field_str FROM $table_name $where");
        while ($row = $result->fetch_object()) {

            // Initialise if not set, zero all fields
            if (!isset($available_stats[$row->id])) {
                $available_stats[$row->id] = array(
                    "count" => 0
                );
                foreach ($fields as $key => $field) {
                    $available_stats[$row->id][$key] = 0;
                }
            }

            // Sum across all months
            foreach ($fields as $key => $field) {
                $available_stats[$row->id][$key] += $row->$key * 1;
            }
            $available_stats[$row->id]['count']++;
        }

        $stats = array();
        foreach ($available_stats as $id=>$system) {

            // Calculate averages
            if ($system['count'] > 0) {
                foreach ($fields as $key => $field) {
                    if ($field['average']) {
                        $system[$key] = $system[$key] / $system['count'];
                    }
                }
            }

            // Calculate COP
            $cop = 0;
            if ($system['elec_kwh'] > 0) {
                $cop = $system['heat_kwh'] / $system['elec_kwh'];
            }
            $when_running_cop = 0;
            if ($system['when_running_elec_kwh'] > 0) {
                $when_running_cop = $system['when_running_heat_kwh'] / $system['when_running_elec_kwh'];
            }

            // Round to required dp
            $stats["" . $id] = array();
            foreach ($fields as $key => $field) {
                $stats["" . $id][$key] = number_format($system[$key], $field['dp'], ".", "") * 1;
            }
            $stats["" . $id]["cop"] = number_format($cop, 1, ".", "") * 1;
            $stats["" . $id]["when_running_cop"] = number_format($when_running_cop, 1, ".", "") * 1;
        }

        return $stats;
    }

    public function get_last365($system_id = false) {

        $where = '';
        if ($system_id!==false) {
            $where = "WHERE id=$system_id";
        }

        $stats = array();
        $result = $this->mysqli->query("SELECT id,elec_kwh,heat_kwh,cop,since,data_start FROM system_stats_last365 $where");
        while ($row = $result->fetch_object()) {
            $stats["" . $row->id] = array(
                "elec_kwh" => number_format($row->elec_kwh, 0, ".", "") * 1,
                "heat_kwh" => number_format($row->heat_kwh, 0, ".", "") * 1,
                "cop" => number_format($row->cop, 1, ".", "") * 1,
                "since" => $row->since*1,
                "data_start" => $row->data_start*1
            );
        }
        return $stats;
    }

    public function system_get_monthly($systemid) {

        $fields = array(
            "timestamp", 
            "elec_kwh",
            "heat_kwh",
            "cop",
            "when_running_elec_kwh",
            "when_running_heat_kwh",
            "when_running_cop",
            "when_running_flowT",
            "when_running_returnT",
            "when_running_flow_minus_return",
            "when_running_outsideT",
            "when_running_flow_minus_outside",
            "when_running_carnot_prc",
            "standby_threshold",
            "standby_kwh",
            "quality_elec",
            "quality_heat",
            "quality_flow",
            "quality_return",
            "quality_outside",
            "data_start"
        );

        $field_str = implode(",", $fields);

        $monthly = array();
        $result = $this->mysqli->query("SELECT $field_str FROM system_stats_monthly WHERE id=$systemid ORDER BY timestamp ASC");
        while ($row = $result->fetch_object()) {
            $monthly[] = $row;
        }
        return $monthly;
    }
}
