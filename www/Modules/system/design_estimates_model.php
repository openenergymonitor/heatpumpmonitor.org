<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

// Design-stage performance estimates for comparison with measured performance.
//
// MCS 031 a) issue 5.0 (MIS 3005) and MCS 031 a): 2025 issue 2.0 (MCS:2025),
// both 05/12/2025, share the same method: a generic SPF looked up from the
// design flow temperature (Appendix Table 2, the same in every heat loss band),
// hot water divided by a fixed factor, plus an immersion term when the
// cylinder is pasteurised by immersion. Instead of the EPC demand used on a
// quote, the estimate is applied to the system's measured space and water
// heat, so it compares efficiency only.
//
// The manufacturer SCOP estimate reads the SCOP declared to MCS for the
// system's heat pump model (heatpump_model.mcs_scop) at the design flow
// temperature for space heating and at the hot water target + 5 K, and applies
// it to the same measured heat.
//
// See analysis/performance_prediction/docs/mcs_and_scop_methods.md
class DesignEstimates
{
    const MCS031_VERSION = "MCS 031 a) issue 5.0 / MCS 031 a): 2025 issue 2.0";

    // Table 2 column 5 (ASHP) and column 4 (GSHP), keyed by the top of each flow temperature band
    private static $mcs031_space = array(
        'Air Source'    => array(35 => 4.0, 40 => 3.8, 45 => 3.4, 50 => 3.1, 55 => 2.8, 60 => 2.5, 65 => 2.5),
        'Ground Source' => array(35 => 4.3, 40 => 4.1, 45 => 3.7, 50 => 3.4, 55 => 3.1, 60 => 2.8, 65 => 2.8)
    );
    private static $mcs031_water = array('Air Source' => 1.9, 'Ground Source' => 2.2);

    // Existing radiators default to 60 °C unless a room-by-room heat loss justifies lower
    const MCS031_EXISTING_RADIATOR_FLOW = 60;

    private static $legionella_per_year = array('Daily' => 365, 'Weekly' => 52, 'Fornightly' => 26, 'Monthly' => 12);

    // Annual estimates are only compared with a year of data: the same
    // threshold the system page uses to label the figure SPF rather than COP,
    // and the analysis threshold for the fleet summary
    const MIN_DAYS_SYSTEM = 290;
    const MIN_DAYS_FLEET = 330;

    // Hot water is read off the SCOP table at the target + 5 K. The target was
    // off the form from Dec 2025 to Oct 2026, so it's assumed where blank (as in the analysis)
    const SCOP_DHW_LIFT = 5;
    const SCOP_DEFAULT_DHW_TARGET = 50;

    private $system_stats;
    private $redis;
    private $mysqli;

    public function __construct($system_stats, $redis, $mysqli = null)
    {
        $this->system_stats = $system_stats;
        $this->redis = $redis;
        $this->mysqli = $mysqli;
    }

    // Write the MCS 031 and manufacturer SCOP fields to system_meta for one system, or every system
    // when $systemid is false. Called on save, after the nightly stats update,
    // and from computed_fields.php to backfill. NULL where the method doesn't apply.
    public function update_meta($systemid = false)
    {
        // Fails until update_database.php has added the columns
        $stmt = $this->mysqli->prepare("UPDATE system_meta SET mcs031_space_spf=?, mcs031_spf=?, mcs031_spf_ratio=? WHERE id=?");
        if (!$stmt) return 0;
        // Separate so MCS 031 keeps updating before the SCOP columns exist
        $scop_stmt = $this->try_sql("prepare", "UPDATE system_meta SET scop_space=?, scop_spf=?, scop_spf_ratio=? WHERE id=?");
        $scop_tables = $scop_stmt ? $this->scop_tables($systemid) : array();

        $where = $systemid ? "WHERE id='" . (int) $systemid . "'" : "";
        $result = $this->mysqli->query("SELECT * FROM system_meta $where");

        $count = 0;
        while ($meta = $result->fetch_object()) {
            $stats = $this->system_stats->get_last365(false, $meta->id);
            $stats = isset($stats[$meta->id]) ? (object) $stats[$meta->id] : null;

            $space_spf = null;
            $spf = null;
            $ratio = null;
            $e = $this->mcs031($meta, $stats);
            if ($e !== null) {
                $space_spf = $e['space_spf'];
                if (isset($e['combined_spf'])) {
                    $spf = round($e['combined_spf'], 3);
                    if ($stats->combined_cop > 0) $ratio = round($stats->combined_cop / $e['combined_spf'], 3);
                }
            }

            $id = (int) $meta->id;
            $stmt->bind_param("dddi", $space_spf, $spf, $ratio, $id);
            $stmt->execute();

            if ($scop_stmt) {
                $space_scop = null;
                $spf = null;
                $ratio = null;
                $e = isset($scop_tables[$id]) ? $this->scop($meta, $stats, $scop_tables[$id]) : null;
                if ($e !== null) {
                    $space_scop = round($e['space_scop'], 3);
                    if (isset($e['combined_spf'])) {
                        $spf = round($e['combined_spf'], 3);
                        if ($stats->combined_cop > 0) $ratio = round($stats->combined_cop / $e['combined_spf'], 3);
                    }
                }
                $scop_stmt->bind_param("dddi", $space_scop, $spf, $ratio, $id);
                $scop_stmt->execute();
            }
            $count++;
        }
        return $count;
    }

    private static function mcs031_space_spf($source, $flow)
    {
        foreach (self::$mcs031_space[$source] as $upper => $spf) {
            if (ceil($flow) <= $upper) return $spf;
        }
        return null;
    }

    // MCS 031 estimate for one system. $meta is the system_meta row and
    // $stats a last 365 day stats row. Returns null where the method doesn't
    // apply (not air or ground source, hybrid, no design flow temperature,
    // or above the table's 65 °C).
    public function mcs031($meta, $stats = null)
    {
        $source = isset($meta->hp_type) ? $meta->hp_type : '';
        if (!isset(self::$mcs031_space[$source])) return null;
        if (!empty($meta->hp_hybrid)) return null;

        $flow = isset($meta->flow_temp) ? (float) $meta->flow_temp : 0;
        if ($flow <= 0) return null;
        $space_spf = self::mcs031_space_spf($source, $flow);
        if ($space_spf === null) return null;

        $existing_radiators = !empty($meta->old_radiators) && empty($meta->new_radiators) && empty($meta->UFH);

        $estimate = array(
            'method' => self::MCS031_VERSION,
            'source' => $source,
            'design_flow' => $flow,
            'space_spf' => $space_spf,
            'water_spf' => self::$mcs031_water[$source],
            'existing_radiators' => $existing_radiators,
            'space_spf_default_flow' => $existing_radiators ? self::mcs031_space_spf($source, self::MCS031_EXISTING_RADIATOR_FLOW) : null
        );

        // Pasteurisation by immersion: raise the cylinder 10 K, 50 to 60 °C
        $immersion = 0;
        $frequency = isset($meta->legionella_frequency) ? $meta->legionella_frequency : '';
        if (!empty($meta->legionella_immersion) && !empty($meta->cylinder_volume) && isset(self::$legionella_per_year[$frequency])) {
            $immersion = self::$legionella_per_year[$frequency] * $meta->cylinder_volume * 10 * 4200 / 3600000;
        }
        $estimate['immersion_kwh'] = $immersion;

        // Apply to measured heat, only with a year of data and metered hot water
        if ($stats === null || empty($stats->combined_data_length) || $stats->combined_data_length < self::MIN_DAYS_SYSTEM * 86400) {
            return $estimate;
        }
        $space = (float) $stats->space_heat_kwh;
        $water = (float) $stats->water_heat_kwh;
        if ($space <= 0 || $water <= 0) return $estimate;

        $elec = $space / $space_spf + $water / $estimate['water_spf'] + $immersion;
        $estimate['combined_spf'] = ($space + $water) / $elec;
        return $estimate;
    }

    // Measured against estimated SPF across public MID-metered, boundary H4
    // systems with a year of data, metered hot water and a declared design flow
    // temperature, by heat pump type. Recomputed from the current stats and
    // cached in redis until 7am, shortly after the nightly stats update.
    public function mcs031_fleet_summary()
    {
        $cache_key = "hpmon:design_estimates:mcs031_fleet";

        if ($this->redis) {
            $cached = $this->redis->get($cache_key);
            if ($cached) {
                $decoded = json_decode($cached, true);
                if ($decoded !== null) return $decoded;
            }
        }

        $systems = $this->system_stats->combined_meta_stats_query(array(
            'meta_filters' => array(
                'mid_metering' => 1,
                'metering_boundary_code' => 4
            ),
            'meta_fields' => array(
                'hp_type', 'hp_hybrid', 'flow_temp', 'old_radiators', 'new_radiators', 'UFH',
                'legionella_frequency', 'legionella_immersion', 'cylinder_volume'
            ),
            'stats_fields' => array(
                'combined_cop', 'combined_data_length', 'space_heat_kwh', 'water_heat_kwh',
                'combined_elec_kwh', 'combined_heat_kwh', 'immersion_kwh', 'cooling_heat_kwh'
            ),
            'stats_table' => "system_stats_last365_v2"
        ));

        $groups = array();
        foreach ($systems as $s) {
            if ($s->combined_cop === null || $s->combined_data_length < self::MIN_DAYS_FLEET * 86400) continue;
            if (abs((float) $s->cooling_heat_kwh) >= 1) continue;
            $e = $this->mcs031($s, $s);
            if ($e === null || !isset($e['combined_spf'])) continue;
            $groups[$e['source']][] = array((float) $s->combined_cop, $e['combined_spf']);
        }

        $summary = array('method' => self::MCS031_VERSION, 'updated' => time(), 'by_source' => array());
        foreach ($groups as $source => $rows) {
            $n = count($rows);
            $measured = array_sum(array_column($rows, 0)) / $n;
            $estimated = array_sum(array_column($rows, 1)) / $n;
            $summary['by_source'][$source] = array(
                'n' => $n,
                'mean_measured' => $measured,
                'mean_estimated' => $estimated
            );
        }

        if ($this->redis) {
            $timezone = new DateTimeZone('Europe/London');
            $now = new DateTime('now', $timezone);
            $expires = new DateTime('today 07:00', $timezone);
            if ($expires <= $now) $expires->modify('+1 day');
            $this->redis->setex($cache_key, $expires->getTimestamp() - $now->getTimestamp(), json_encode($summary));
        }

        return $summary;
    }

    // mysqli query or prepare that returns false rather than throwing when a
    // column is missing, e.g. before update_database.php has run
    private function try_sql($method, $sql)
    {
        try {
            return $this->mysqli->$method($sql);
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }

    // SCOP tables of the heat pump models systems link to, keyed by system id,
    // for one system or all. Empty before update_database.php has added the
    // heatpump_model columns. Requires the System class for the model join.
    public function scop_tables($systemid = false)
    {
        $where = "hm.mcs_scop IS NOT NULL";
        if ($systemid) $where .= " AND sm.id='" . (int) $systemid . "'";
        $result = $this->try_sql("query", "SELECT sm.id, hm.id AS model_id, hm.mcs_model_number, hm.mcs_certification_number, hm.mcs_scop
            FROM system_meta sm " . System::HEATPUMP_MODEL_JOIN . " WHERE $where");
        if (!$result) return array();

        $tables = array();
        while ($row = $result->fetch_object()) {
            if (isset($tables[$row->id])) continue;
            $scop = array();
            foreach ((array) json_decode($row->mcs_scop, true) as $flow => $value) {
                if ($value > 0) $scop[(int) $flow] = (float) $value;
            }
            if (!count($scop)) continue;
            ksort($scop);
            $tables[$row->id] = array(
                'model_id' => (int) $row->model_id,
                'model_number' => $row->mcs_model_number,
                'certification_number' => $row->mcs_certification_number,
                'scop' => $scop
            );
        }
        return $tables;
    }

    // SCOP at a flow temperature, interpolated between declared values. Below
    // the lowest declared temperature (35 °C) the 35 °C value is used; above the
    // highest there is no value rather than an extrapolation.
    public static function scop_at($scop, $flow)
    {
        $temps = array_keys($scop);
        if ($flow > end($temps)) return null;
        if ($flow <= $temps[0]) return $scop[$temps[0]];
        for ($i = 1; $i < count($temps); $i++) {
            $t1 = $temps[$i];
            if ($flow <= $t1) {
                $t0 = $temps[$i - 1];
                return $scop[$t0] + ($scop[$t1] - $scop[$t0]) * ($flow - $t0) / ($t1 - $t0);
            }
        }
        return null;
    }

    // Manufacturer SCOP estimate for one system. $meta is the system_meta row,
    // $stats a last 365 day stats row and $table the system's entry from
    // scop_tables(), looked up when not given. Returns null where it doesn't
    // apply (no SCOP table for the model, hybrid, no design flow temperature,
    // or a design flow above the declared temperatures). Hot water and the
    // combined figure follow the same data rules as mcs031().
    public function scop($meta, $stats = null, $table = null)
    {
        if ($table === null) {
            $tables = $this->scop_tables($meta->id);
            if (!isset($tables[$meta->id])) return null;
            $table = $tables[$meta->id];
        }
        if (!empty($meta->hp_hybrid)) return null;

        $flow = isset($meta->flow_temp) ? (float) $meta->flow_temp : 0;
        if ($flow <= 0) return null;
        $space_scop = self::scop_at($table['scop'], $flow);
        if ($space_scop === null) return null;

        $dhw_target = isset($meta->dhw_target_temperature) ? (float) $meta->dhw_target_temperature : 0;
        $dhw_target_assumed = $dhw_target <= 0;
        if ($dhw_target_assumed) $dhw_target = self::SCOP_DEFAULT_DHW_TARGET;
        $water_flow = $dhw_target + self::SCOP_DHW_LIFT;

        $temps = array_keys($table['scop']);
        $estimate = array(
            'model_id' => $table['model_id'],
            'model_number' => $table['model_number'],
            'certification_number' => $table['certification_number'],
            'scop_min_flow' => $temps[0],
            'scop_max_flow' => end($temps),
            'design_flow' => $flow,
            'space_scop' => $space_scop,
            'dhw_target' => $dhw_target,
            'dhw_target_assumed' => $dhw_target_assumed,
            'water_flow' => $water_flow,
            'water_scop' => self::scop_at($table['scop'], $water_flow)
        );

        if ($estimate['water_scop'] === null) return $estimate;
        if ($stats === null || empty($stats->combined_data_length) || $stats->combined_data_length < self::MIN_DAYS_SYSTEM * 86400) {
            return $estimate;
        }
        $space = (float) $stats->space_heat_kwh;
        $water = (float) $stats->water_heat_kwh;
        if ($space <= 0 || $water <= 0) return $estimate;

        $estimate['combined_spf'] = ($space + $water) / ($space / $space_scop + $water / $estimate['water_scop']);
        return $estimate;
    }

    // Measured against manufacturer SCOP estimate across all models with a
    // SCOP table, for the same systems as mcs031_fleet_summary(). Cached in
    // redis until 7am.
    public function scop_fleet_summary()
    {
        $cache_key = "hpmon:design_estimates:scop_fleet";

        if ($this->redis) {
            $cached = $this->redis->get($cache_key);
            if ($cached) {
                $decoded = json_decode($cached, true);
                if ($decoded !== null) return $decoded;
            }
        }

        $tables = $this->scop_tables();

        $systems = $this->system_stats->combined_meta_stats_query(array(
            'meta_filters' => array(
                'mid_metering' => 1,
                'metering_boundary_code' => 4
            ),
            'meta_fields' => array('hp_hybrid', 'flow_temp', 'dhw_target_temperature'),
            'stats_fields' => array(
                'combined_cop', 'combined_data_length', 'space_heat_kwh', 'water_heat_kwh', 'cooling_heat_kwh'
            ),
            'stats_table' => "system_stats_last365_v2"
        ));

        $rows = array();
        foreach ($systems as $s) {
            if (!isset($tables[$s->id])) continue;
            if ($s->combined_cop === null || $s->combined_data_length < self::MIN_DAYS_FLEET * 86400) continue;
            if (abs((float) $s->cooling_heat_kwh) >= 1) continue;
            $e = $this->scop($s, $s, $tables[$s->id]);
            if ($e === null || !isset($e['combined_spf'])) continue;
            $rows[] = array((float) $s->combined_cop, $e['combined_spf']);
        }

        $summary = array('updated' => time(), 'n' => count($rows));
        if (count($rows)) {
            $summary['mean_measured'] = array_sum(array_column($rows, 0)) / count($rows);
            $summary['mean_estimated'] = array_sum(array_column($rows, 1)) / count($rows);
        }

        // Not cached while empty, e.g. part way through mapping models to MCS products
        if ($this->redis && count($rows)) {
            $timezone = new DateTimeZone('Europe/London');
            $now = new DateTime('now', $timezone);
            $expires = new DateTime('today 07:00', $timezone);
            if ($expires <= $now) $expires->modify('+1 day');
            $this->redis->setex($cache_key, $expires->getTimestamp() - $now->getTimestamp(), json_encode($summary));
        }

        return $summary;
    }
}
