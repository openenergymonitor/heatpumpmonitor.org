<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

// Measurements worked out from each system's monitoring data by
// scripts/measurements/system_measurements.php, read back for the system list
// and other pages.
class Measurement
{
    private $mysqli;
    private $system_stats;

    // Measurements shown as system list columns, keyed by measurement name.
    // The column key is "m_" + name, so it can't clash with system_meta or stats fields.
    const COLUMNS = array(
        'heat_loss_design' => array('name' => 'Heat demand at design', 'heading' => 'Measured<br>heat demand', 'group' => 'Measured: Heat loss', 'unit' => 'kW', 'dp' => 1,
            'helper' => 'Daily heat against inside - outside temperature difference, read off at the design temperature on the system form (or the measured one if the form has none) and 20 °C inside'),
        'heat_loss_design_measured' => array('name' => 'Heat demand at measured design temp', 'heading' => 'Heat demand<br>at measured DOT', 'group' => 'Measured: Heat loss', 'unit' => 'kW', 'dp' => 1,
            'helper' => 'As heat demand at design, at the measured 99.6% design outside temperature'),
        'heat_loss_htc' => array('name' => 'Heat transfer coefficient', 'heading' => 'HTC', 'group' => 'Measured: Heat loss', 'unit' => 'W/K', 'dp' => 0,
            'helper' => 'Slope of daily heat against inside - outside temperature difference'),
        'heat_loss_base_dT' => array('name' => 'Balance point', 'heading' => 'Balance<br>point', 'group' => 'Measured: Heat loss', 'unit' => 'K', 'dp' => 1,
            'helper' => 'Inside - outside temperature difference where the fit reaches zero heat'),

        'heat_peak_day' => array('name' => 'Peak day heat output', 'heading' => 'Peak day', 'group' => 'Measured: Peak heat', 'unit' => 'kW', 'dp' => 1,
            'helper' => 'Highest day of heat output divided by 24, all modes'),
        'heat_peak_4h' => array('name' => 'Peak 4 hour heat output', 'heading' => 'Peak 4h', 'group' => 'Measured: Peak heat', 'unit' => 'kW', 'dp' => 1,
            'helper' => 'Highest 4 hour mean heat output, all modes'),

        'design_outsideT_996' => array('name' => 'Design outside temp 99.6%', 'heading' => 'DOT<br>99.6%', 'group' => 'Measured: Design temperature', 'unit' => '°C', 'dp' => 1,
            'helper' => 'Hourly outside temperature equalled or exceeded for 99.6% of hours, complete years only'),
        'design_outsideT_990' => array('name' => 'Design outside temp 99.0%', 'heading' => 'DOT<br>99.0%', 'group' => 'Measured: Design temperature', 'unit' => '°C', 'dp' => 1,
            'helper' => 'Hourly outside temperature equalled or exceeded for 99.0% of hours, complete years only'),

        'flowT_typical_design' => array('name' => 'Typical flow temp at design', 'heading' => 'Flow temp<br>at design', 'group' => 'Measured: Flow temperature', 'unit' => '°C', 'dp' => 1,
            'helper' => 'Daily space heating flow temperature weighted by heat output, against inside - outside temperature difference over the latest year, read off at design'),
        'flowT_sustained_design' => array('name' => 'Sustained flow temp at design', 'heading' => 'Sustained<br>flow temp', 'group' => 'Measured: Flow temperature', 'unit' => '°C', 'dp' => 1,
            'helper' => 'As typical flow temp at design, using the highest 4 hour space heating flow temperature each day'),
        'flowT_peak_4h' => array('name' => 'Peak 4 hour flow temp', 'heading' => 'Peak 4h<br>flow temp', 'group' => 'Measured: Flow temperature', 'unit' => '°C', 'dp' => 1,
            'helper' => 'Highest 4 hour space heating flow temperature in the latest year, weighted by heat output')
    );

    public function __construct($mysqli, $system_stats)
    {
        $this->mysqli = $mysqli;
        $this->system_stats = $system_stats;
    }

    // Column definitions for the system list, keyed by column key
    public function columns()
    {
        $columns = array();
        foreach (self::COLUMNS as $name => $c) $columns["m_$name"] = $c;
        return $columns;
    }

    // Measurement values for the system list: { system id: { m_name: value } }.
    // Same visibility as the system list stats: public systems in public mode,
    // the user's own (and linked accounts') systems in user mode, all in admin mode.
    public function get_list($session_userid, $mode = "public")
    {
        $where = "sm.share=1 AND sm.published=1";
        if ($mode == "admin") {
            $where = "1";
        } else if ($mode == "user") {
            if (!$session_userid) return array();
            $ids = implode(",", array_map('intval', $this->system_stats->get_user_accounts((int) $session_userid)));
            $where = "sm.userid IN ($ids)";
        }

        $names = "'".implode("','", array_keys(self::COLUMNS))."'";
        $result = $this->mysqli->query("SELECT m.system_id, m.name, m.value FROM measurement m JOIN system_meta sm ON m.system_id = sm.id WHERE $where AND m.name IN ($names)");
        $list = array();
        if (!$result) return $list;
        while ($row = $result->fetch_object()) {
            if ($row->value === null) continue;
            $dp = self::COLUMNS[$row->name]['dp'] + 1;
            $list[(int) $row->system_id]["m_".$row->name] = round((float) $row->value, $dp);
        }
        return $list;
    }

    // All measurements for one system, with intervals, periods and details
    public function get_system($systemid)
    {
        $systemid = (int) $systemid;
        $result = $this->mysqli->query("SELECT * FROM measurement WHERE system_id = $systemid ORDER BY name");
        $out = array();
        while ($row = $result->fetch_object()) {
            foreach (array('value', 'lower', 'upper') as $k) $row->$k = $row->$k === null ? null : (float) $row->$k;
            foreach (array('samples', 'start_time', 'end_time', 'version', 'updated') as $k) $row->$k = $row->$k === null ? null : (int) $row->$k;
            $row->details = $row->details ? json_decode($row->details) : null;
            unset($row->system_id);
            $out[$row->name] = $row;
        }
        return $out;
    }
}
