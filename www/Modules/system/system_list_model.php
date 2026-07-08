<?php

defined('EMONCMS_EXEC') or die('Restricted access');

/**
 * Server-side filtering, sorting and paging for the system list UI.
 */
class SystemListModel
{
    /** @var mysqli */
    private $mysqli;

    /** @var mysqli */
    private $emoncms_mysqli;

    /** @var System */
    private $system;

    /** @var string */
    private $hpmon_path;

    /** @var string emoncms DB name (cross-database JOIN for username sort) */
    private $emoncms_db;

    public function __construct($mysqli, $system)
    {
        $this->mysqli = $mysqli;
        $this->system = $system;

        global $emoncms_mysqli;
        $this->emoncms_mysqli = $emoncms_mysqli;

        global $settings;
        $this->hpmon_path = isset($settings['path']) ? $settings['path'] : '/opt/openenergymonitor/heatpumpmonitor';
        $this->emoncms_db = isset($settings['emoncms_credentials']['database'])
            ? preg_replace('/[^a-zA-Z0-9_]/', '', $settings['emoncms_credentials']['database'])
            : 'emoncms';
    }

    /**
     * Paginated rows for the table (stats joined, tariff columns computed).
     *
     * @param string $mode public|user|admin
     * @param int|false $session_userid
     * @param array $params query params from GET
     * @return array
     */
    public function query($mode, $session_userid, array $params)
    {
        $ctx = $this->build_query_context($mode, $session_userid, $params);
        $limit = $ctx['limit'];
        $offset = $ctx['offset'];

        $order_sql = $this->build_order_sql($ctx['sort'], $ctx['dir'], $mode, $ctx['tariff']);

        // Fetch limit+1 rows to derive has_more without a separate COUNT(*) over the heavy join.
        $fetch_limit = $limit + 1;
        $page_sql = "SELECT {$ctx['select_sql']} FROM {$ctx['from_sql']} {$ctx['where_sql']} {$order_sql} LIMIT " . (int) $fetch_limit . " OFFSET " . (int) $offset;

        $result = $this->mysqli->query($page_sql);
        if (!$result) {
            return array('success' => false, 'message' => $this->mysqli->error);
        }

        $rows = array();
        while ($row = $result->fetch_object()) {
            $rows[] = $row;
        }

        $has_more = (count($rows) > $limit);
        if ($has_more) {
            array_pop($rows);
        }

        $userids = array();
        foreach ($rows as $row) {
            $userids[] = (int) $row->userid;
        }

        $usernames = $this->fetch_usernames(array_unique($userids));

        $remove_private = ($mode === 'public');
        $last_by_app = array();
        if (!$remove_private) {
            $app_ids = array();
            foreach ($rows as $r) {
                if (isset($r->app_id) && (int) $r->app_id > 0) {
                    $app_ids[(int) $r->app_id] = true;
                }
            }
            $last_by_app = $this->system->get_last_updated_batch(array_keys($app_ids));
        }

        $out_rows = array();
        foreach ($rows as $row) {
            $userid = (int) $row->userid;
            if (isset($usernames[$userid])) {
                $row->username = $usernames[$userid];
                $row->name = $usernames[$userid];
            } elseif ($mode !== 'public') {
                $row->username = 'DELETED: ' . $userid;
                $row->name = $row->username;
            }

            $row = $this->finalize_row($row, $remove_private, $last_by_app);
            $out_rows[] = $row;
        }

        return array(
            'success' => true,
            'rows' => $out_rows,
            'total' => null,
            'has_more' => $has_more,
            'offset' => $offset,
            'limit' => $limit,
            'query_signature' => md5($ctx['where_sql'] . '|' . $order_sql . '|' . $ctx['tariff'] . '|' . $ctx['period_resolved']),
        );
    }

    /**
     * Aggregates: sidebar counts, totals card, chart points, CSV payload.
     *
     * @param string $mode
     * @param int|false $session_userid
     * @param array $params
     * @return array
     */
    public function summary($mode, $session_userid, array $params)
    {
        $ctx = $this->build_query_context($mode, $session_userid, $params);

        // --- Full filtered set (metering filters applied): counts + totals averages
        $where_meter = $ctx['where_sql'];

        $avg_sql = "
            SELECT
                COUNT(*) AS listed_system_count,
                SUM(CASE WHEN {$ctx['cop_avg_condition']} THEN 1 ELSE 0 END) AS cop_avg_count,
                SUM(CASE WHEN {$ctx['cop_avg_condition']} THEN {$ctx['combined_cop_ref']} ELSE 0 END) AS cop_sum,
                SUM(CASE WHEN {$ctx['cop_avg_condition']} THEN COALESCE({$ctx['combined_elec_ref']}, 0) ELSE 0 END) AS sum_elec_kwh,
                SUM(CASE WHEN {$ctx['cop_avg_condition']} THEN COALESCE({$ctx['combined_heat_ref']}, 0) ELSE 0 END) AS sum_heat_kwh
            FROM {$ctx['from_sql']}
            {$where_meter}
        ";

        $avg_result = $this->mysqli->query($avg_sql);
        if (!$avg_result) {
            return array('success' => false, 'message' => $this->mysqli->error);
        }
        $avg_row = $avg_result->fetch_object();
        $cop_avg_count = (int) $avg_row->cop_avg_count;
        $average_cop = $cop_avg_count > 0 ? (float) $avg_row->cop_sum / $cop_avg_count : 0;
        $sum_elec = (float) $avg_row->sum_elec_kwh;
        $sum_heat = (float) $avg_row->sum_heat_kwh;
        $average_cop_kwh = ($sum_elec > 0) ? $sum_heat / $sum_elec : 0;

        // --- Pre-metering counts (filterNodes + filterDays + admin restricted + public visibility)
        $where_pre_meter = $ctx['where_pre_metering_sql'];
        $count_sql = "
            SELECT
                SUM(CASE WHEN {$ctx['effective_flag_expr']} >= 1 THEN 1 ELSE 0 END) AS num_flagged,
                SUM(CASE WHEN {$ctx['effective_flag_expr']} < 1 AND sm.mid_metering = 1 THEN 1 ELSE 0 END) AS num_mid,
                SUM(CASE WHEN {$ctx['effective_flag_expr']} < 1 AND COALESCE(sm.mid_metering,0) = 0 AND COALESCE(sm.heat_meter,'') <> 'Heat pump integration' THEN 1 ELSE 0 END) AS num_other,
                SUM(CASE WHEN {$ctx['effective_flag_expr']} < 1 AND COALESCE(sm.mid_metering,0) = 0 AND sm.heat_meter = 'Heat pump integration' THEN 1 ELSE 0 END) AS num_hpint
            FROM {$ctx['from_sql']}
            {$where_pre_meter}
        ";
        $cr = $this->mysqli->query($count_sql);
        if (!$cr) {
            return array('success' => false, 'message' => $this->mysqli->error);
        }
        $crow = $cr->fetch_object();

        $include_csv = true;
        if (isset($params['include_csv']) && (string) $params['include_csv'] === '0') {
            $include_csv = false;
        }
        $include_points = true;
        if (isset($params['include_points']) && (string) $params['include_points'] === '0') {
            $include_points = false;
        }

        $out = array(
            'success' => true,
            'counts' => array(
                'listed_system_count' => (int) $avg_row->listed_system_count,
                'num_flagged' => (int) $crow->num_flagged,
                'num_mid' => (int) $crow->num_mid,
                'num_other' => (int) $crow->num_other,
                'num_hpint' => (int) $crow->num_hpint,
            ),
            'totals' => array(
                'average_cop' => $average_cop,
                'average_cop_kwh' => $average_cop_kwh,
            ),
            'query_signature' => md5($where_meter . '|' . $ctx['tariff'] . '|' . $ctx['period_resolved']),
        );
        if ($include_points) {
            $out['points'] = $this->fetch_chart_points($ctx, $params);
        }
        if ($include_csv) {
            $out['csv'] = $this->fetch_csv_payload($ctx, $params, $ctx['tariff']);
        }

        return $out;
    }

    // -------------------------------------------------------------------------

    private function build_query_context($mode, $session_userid, array $params)
    {
        $offset = isset($params['offset']) ? max(0, (int) $params['offset']) : 0;
        $limit = isset($params['limit']) ? max(1, min(200, (int) $params['limit'])) : 50;

        $period = isset($params['period']) ? $params['period'] : 'last365';
        $month_start = isset($params['month_start']) ? $params['month_start'] : '';
        $month_end = isset($params['month_end']) ? $params['month_end'] : '';

        $tariff = isset($params['tariff']) ? $params['tariff'] : 'flat';
        $allowed_tariff = array('flat', 'agile', 'cosy', 'go', 'ovohp', 'eon_next_pumped_v2');
        if (!in_array($tariff, $allowed_tariff, true)) {
            $tariff = 'flat';
        }

        $sort = isset($params['sort']) ? $params['sort'] : 'combined_cop';
        $dir = isset($params['dir']) ? strtolower($params['dir']) : 'desc';
        if ($dir !== 'asc' && $dir !== 'desc') {
            $dir = 'desc';
        }

        $min_days = isset($params['min_days']) ? max(0, (int) $params['min_days']) : 0;
        $thresh_days = max(0, $min_days - 1);

        $show_mid = !empty($params['show_mid']);
        $show_other = !empty($params['show_other']);
        $show_hpint = !empty($params['show_hpint']);
        $show_errors = !empty($params['show_errors']);

        $admin_restricted = ($mode === 'admin') && !empty($params['admin_restricted']);

        $filter = isset($params['filter']) ? $params['filter'] : '';

        $period_resolved = $this->resolve_period_key($period, $month_start, $month_end);

        if ($period_resolved === 'monthly_range' && ($month_start === '' || $month_end === '')) {
            $period_resolved = 'last365';
        }

        $stats_fragments = $this->build_stats_fragments($period_resolved, $month_start, $month_end, $tariff);

        $user_join_sql = $this->user_sort_join_sql($sort, $mode);
        $base_from = $this->base_from_sql($stats_fragments['join_sql'], $user_join_sql);

        $mode_where = $this->mode_visibility_sql($mode, $session_userid);

        $filter_sql = $this->build_filter_sql($filter, $stats_fragments['combined_cop_expr']);

        $public_data_where = ($mode === 'public')
            ? ' AND (COALESCE(s.combined_data_length,0) <> 0) '
            : '';

        $min_days_sql = ' AND (COALESCE(s.combined_data_length,0) / (24 * 3600)) >= ' . (float) $thresh_days . ' ';

        $admin_restrict_sql = '';
        if ($admin_restricted) {
            $admin_restrict_sql = ' AND ((sm.share = 1 AND sm.published = 0) OR (' . $stats_fragments['effective_flag_expr'] . ' >= 1)) ';
        }

        $metering_sql = $this->metering_sql($show_mid, $show_other, $show_hpint, $show_errors, $stats_fragments['effective_flag_expr']);

        $where_core = ' WHERE 1=1 ' . $mode_where . $filter_sql . $public_data_where . $min_days_sql . $admin_restrict_sql;

        $where_pre_metering = ' WHERE 1=1 ' . $mode_where . $filter_sql . $public_data_where . $min_days_sql . $admin_restrict_sql;

        $where_full = $where_core . $metering_sql;

        $select_sql = $stats_fragments['select_outer'];

        return array(
            'limit' => $limit,
            'offset' => $offset,
            'sort' => $sort,
            'dir' => $dir,
            'tariff' => $tariff,
            'period_resolved' => $period_resolved,
            'from_sql' => $base_from,
            'where_sql' => $where_full,
            'where_pre_metering_sql' => $where_pre_metering,
            'select_sql' => $select_sql,
            'combined_cop_ref' => $stats_fragments['combined_cop_expr'],
            'combined_elec_ref' => 'COALESCE(s.combined_elec_kwh, 0)',
            'combined_heat_ref' => 'COALESCE(s.combined_heat_kwh, 0)',
            'cop_avg_condition' => '(COALESCE(s.combined_elec_kwh, 0) > 0 AND COALESCE(s.combined_heat_kwh, 0) > 0 AND COALESCE(s.combined_heat_kwh, 0) > COALESCE(s.combined_elec_kwh, 0))',
            'effective_flag_expr' => $stats_fragments['effective_flag_expr'],
            'tariff' => $tariff,
        );
    }

    /**
     * JOIN emoncms.users for ORDER BY username (heatpump DB + emoncms DB are separate).
     */
    private function user_sort_join_sql($sort, $mode)
    {
        if (($sort !== 'name' && $sort !== 'username') || $mode === 'public') {
            return '';
        }
        $db = $this->emoncms_db;
        return " LEFT JOIN `{$db}`.users u_sort ON u_sort.id = sm.userid ";
    }

    /**
     * Key used for stats table / monthly aggregation.
     */
    private function resolve_period_key($period, $month_start, $month_end)
    {
        $presets = array('last7', 'last30', 'last90', 'last365', 'all', 'custom');
        if (in_array($period, $presets, true)) {
            return $period;
        }
        return 'monthly_range';
    }

    private function base_from_sql($stats_join_sql, $user_join_sql = '')
    {
        return "system_meta sm
                {$user_join_sql}
                LEFT JOIN (
                    SELECT system_id, COUNT(*) AS photo_count
                    FROM system_images
                    GROUP BY system_id
                ) pc ON sm.id = pc.system_id
                LEFT JOIN manufacturers m ON sm.hp_manufacturer = m.name
                LEFT JOIN heatpump_model hm ON m.id = hm.manufacturer_id
                    AND sm.hp_model = hm.name
                    AND CAST(hm.capacity AS DECIMAL(10,2)) = sm.hp_output
                    AND (hm.refrigerant = sm.refrigerant OR sm.refrigerant IS NULL OR hm.refrigerant IS NULL)
                {$stats_join_sql}";
    }

    private function mode_visibility_sql($mode, $session_userid)
    {
        if ($mode === 'public') {
            return ' AND sm.share = 1 AND sm.published = 1 ';
        }
        if ($mode === 'user' && $session_userid) {
            $ids = $this->system->get_user_accounts((int) $session_userid);
            $ids = array_map('intval', $ids);
            return ' AND sm.userid IN (' . implode(',', $ids) . ') ';
        }
        return '';
    }

    private function metering_sql($show_mid, $show_other, $show_hpint, $show_errors, $effective_flag_expr)
    {
        // effective_flag_expr references sm / s — must match FROM alias sm and stats s
        $parts = array();
        if ($show_mid) {
            $parts[] = '(sm.mid_metering = 1)';
        }
        if ($show_other) {
            $parts[] = '(COALESCE(sm.mid_metering,0) = 0 AND COALESCE(sm.heat_meter,\'\') <> \'Heat pump integration\')';
        }
        if ($show_hpint) {
            $parts[] = '(COALESCE(sm.mid_metering,0) = 0 AND sm.heat_meter = \'Heat pump integration\')';
        }
        $meter_or = count($parts) ? '(' . implode(' OR ', $parts) . ')' : '0';

        if ($show_errors) {
            return ' AND ( (' . $effective_flag_expr . ' >= 1) OR (' . $meter_or . ') ) ';
        }
        return ' AND (' . $effective_flag_expr . ' < 1) AND (' . $meter_or . ') ';
    }

    /**
     * Stats join + outer SELECT list + expressions referencing sm,s.
     */
    private function build_stats_fragments($period_key, $month_start, $month_end, $tariff)
    {
        $table_map = array(
            'last7' => 'system_stats_last7_v2',
            'last30' => 'system_stats_last30_v2',
            'last90' => 'system_stats_last90_v2',
            'last365' => 'system_stats_last365_v2',
            'all' => 'system_stats_all_v2',
            'custom' => 'system_stats_custom',
        );

        $monthly_agg = ($period_key === 'monthly_range');
        if ($monthly_agg) {
            $bounds = $this->monthly_timestamp_bounds($month_start, $month_end);
            $agg = $this->monthly_aggregate_subquery_sql($bounds[0], $bounds[1]);
            $join_sql = "LEFT JOIN ( {$agg} ) s ON sm.id = s.id ";
            $combined_cop_expr = 'CASE WHEN COALESCE(s.combined_elec_kwh,0) > 0 THEN (s.combined_heat_kwh / s.combined_elec_kwh) ELSE NULL END';
        } else {
            $tbl = isset($table_map[$period_key]) ? $table_map[$period_key] : 'system_stats_last365_v2';
            $join_sql = "LEFT JOIN {$tbl} s ON sm.id = s.id ";
            $combined_cop_expr = 'CASE WHEN COALESCE(s.combined_elec_kwh,0) > 0 THEN (s.combined_heat_kwh / s.combined_elec_kwh) ELSE NULL END';
        }

        $dyn_flag = $this->dynamic_air_error_flag_sql();
        $effective_flag_expr = 'CASE WHEN COALESCE(sm.data_flag,0) >= 1 THEN 1 WHEN (' . $dyn_flag . ') THEN 1 ELSE 0 END';

        $rate_sql = $this->selected_unit_rate_sql($tariff);

        $categories = array('combined', 'running', 'space', 'water');
        $cost_cols = '';
        foreach ($categories as $cat) {
            $cost_cols .= ", (COALESCE(s.{$cat}_elec_kwh,0) * ({$rate_sql}) * 0.01) AS {$cat}_cost ";
            $cost_cols .= ", CASE WHEN COALESCE(s.{$cat}_cop,0) > 0 THEN ({$rate_sql}) / s.{$cat}_cop ELSE NULL END AS {$cat}_heat_unit_cost ";
        }

        $per_m2 = '';
        foreach ($categories as $cat) {
            $per_m2 .= ", CASE WHEN sm.floor_area IS NOT NULL AND sm.floor_area > 0 THEN (COALESCE(s.{$cat}_elec_kwh,0) / sm.floor_area) ELSE NULL END AS {$cat}_elec_kwh_per_m2 ";
            $per_m2 .= ", CASE WHEN sm.floor_area IS NOT NULL AND sm.floor_area > 0 THEN (COALESCE(s.{$cat}_heat_kwh,0) / sm.floor_area) ELSE NULL END AS {$cat}_heat_kwh_per_m2 ";
        }

        $prc_dhw = 'CASE WHEN COALESCE(s.water_heat_kwh,0) > 0 AND COALESCE(s.space_heat_kwh,0) > 0 THEN (s.water_heat_kwh / (s.water_heat_kwh + s.space_heat_kwh) * 100) ELSE NULL END';

        $hp_make_model = 'TRIM(CONCAT(COALESCE(sm.hp_manufacturer,\'\'),\' \',COALESCE(sm.hp_model,\'\')))';
        $training = '((CASE WHEN sm.heatgeek = 1 THEN 1 ELSE 0 END) + (CASE WHEN sm.ultimaterenewables = 1 THEN 1 ELSE 0 END) + (CASE WHEN sm.heatingacademy = 1 THEN 1 ELSE 0 END))';

        $oversize = 'CASE WHEN COALESCE(sm.measured_heat_loss,0) > 0 THEN ' .
            '(COALESCE(NULLIF(sm.hp_max_output_test,0), NULLIF(sm.hp_max_output,0)) / sm.measured_heat_loss) ELSE NULL END';

        $select_outer = "sm.*,
            COALESCE(pc.photo_count, 0) AS photo_count,
            m.id AS manufacturer_id,
            hm.id AS heatpump_model_id,
            s.*,
            {$combined_cop_expr} AS combined_cop_calc,
            {$rate_sql} AS selected_unit_rate,
            {$hp_make_model} AS hp_make_model,
            {$training} AS training,
            {$oversize} AS oversizing_factor,
            {$prc_dhw} AS prc_demand_hot_water
            {$cost_cols}
            {$per_m2}";

        return array(
            'join_sql' => $join_sql,
            'select_outer' => $select_outer,
            'combined_cop_expr' => $combined_cop_expr,
            'effective_flag_expr' => $effective_flag_expr,
            'dynamic_air_sql' => $dyn_flag,
        );
    }

    private function dynamic_air_error_flag_sql()
    {
        $cop_listed = 'CASE WHEN COALESCE(s.combined_elec_kwh,0) > 0 THEN (s.combined_heat_kwh / s.combined_elec_kwh) ELSE NULL END';
        $cop_air = 'CASE WHEN (s.combined_elec_kwh - s.error_air_kwh) > 0 THEN (s.combined_heat_kwh / (s.combined_elec_kwh - s.error_air_kwh)) ELSE NULL END';
        return '( COALESCE(s.error_air_kwh,0) > 0 AND COALESCE(s.combined_elec_kwh,0) > 0 AND ABS( COALESCE(' . $cop_listed . ',0) - COALESCE(' . $cop_air . ',0) ) > 0.2 )';
    }

    /**
     * SQL expression for elec p/kWh used in costs (whitelist only).
     */
    private function selected_unit_rate_sql($tariff)
    {
        $map = array(
            'flat' => '24.86',
            'agile' => 'COALESCE(s.unit_rate_agile, 24.86)',
            'cosy' => 'COALESCE(s.unit_rate_cosy, 24.86)',
            'go' => 'COALESCE(s.unit_rate_go, 24.86)',
            'ovohp' => '15.0',
            'eon_next_pumped_v2' => 'COALESCE(s.unit_rate_eon_next_pumped_v2, 24.86)',
        );
        return isset($map[$tariff]) ? $map[$tariff] : $map['flat'];
    }

    private function monthly_timestamp_bounds($start_str, $end_str)
    {
        $date = new DateTime('now', new DateTimeZone('Europe/London'));
        $date->setTime(0, 0, 0);
        $date->modify($start_str);
        $start_ts = $date->getTimestamp();

        $date = new DateTime('now', new DateTimeZone('Europe/London'));
        $date->setTime(0, 0, 0);
        $date->modify($start_str);
        $date->modify($end_str);
        $date->modify('+1 month');
        $end_ts = $date->getTimestamp();

        return array($start_ts, $end_ts);
    }

    /**
     * Aggregate monthly rows to one row per system (totals + weighted fields).
     */
    private function monthly_aggregate_subquery_sql($start_ts, $end_ts)
    {
        $start_ts = (int) $start_ts;
        $end_ts = (int) $end_ts;

        $cats = array('combined', 'running', 'space', 'water');
        $sum_parts = array();
        foreach ($cats as $c) {
            foreach (array('elec_kwh', 'heat_kwh', 'data_length') as $f) {
                $sum_parts[] = "SUM({$c}_{$f}) AS {$c}_{$f}";
            }
        }

        $extra = array(
            'SUM(combined_starts) AS combined_starts',
            'SUM(combined_cooling_kwh) AS combined_cooling_kwh',
            'SUM(from_energy_feeds_elec_kwh) AS from_energy_feeds_elec_kwh',
            'SUM(from_energy_feeds_heat_kwh) AS from_energy_feeds_heat_kwh',
            'SUM(error_air) AS error_air',
            'SUM(error_air_kwh) AS error_air_kwh',
            'SUM(immersion_kwh) AS immersion_kwh',
            'SUM(boiler_kwh) AS boiler_kwh',
            'SUM(space_heat_kwh) AS space_heat_kwh',
            'SUM(water_heat_kwh) AS water_heat_kwh',
            'SUM(weighted_kwh_elec) AS weighted_kwh_elec',
            'SUM(weighted_kwh_heat) AS weighted_kwh_heat',
            'SUM(weighted_kwh_heat_running) AS weighted_kwh_heat_running',
            'SUM(weighted_kwh_elec_running) AS weighted_kwh_elec_running',
            'SUM(weighted_kwh_carnot_elec) AS weighted_kwh_carnot_elec',
            'SUM(weighted_flowT * weighted_kwh_heat) AS w_flowT_num',
            'SUM(weighted_outsideT * weighted_kwh_heat) AS w_outsideT_num',
            'SUM(weighted_flowT_minus_outsideT * weighted_kwh_heat) AS w_ftmo_num',
            'SUM(weighted_flowT_minus_returnT * weighted_kwh_heat) AS w_ftmr_num',
            'SUM(weighted_elec * weighted_kwh_elec) AS w_elec_num',
            'SUM(weighted_heat * weighted_kwh_heat) AS w_heat_num',
            'SUM(weighted_time_on) AS weighted_time_on',
            'SUM(weighted_time_total) AS weighted_time_total',
            'SUM(weighted_cycle_count) AS weighted_cycle_count',
        );

        $sums_sql = implode(",\n                ", array_merge($sum_parts, $extra));

        return "SELECT id,
                {$sums_sql},
                CASE WHEN SUM(combined_elec_kwh) > 0 THEN SUM(combined_heat_kwh) / SUM(combined_elec_kwh) ELSE NULL END AS combined_cop,
                CASE WHEN SUM(running_elec_kwh) > 0 THEN SUM(running_heat_kwh) / SUM(running_elec_kwh) ELSE NULL END AS running_cop,
                CASE WHEN SUM(space_elec_kwh) > 0 THEN SUM(space_heat_kwh) / SUM(space_elec_kwh) ELSE NULL END AS space_cop,
                CASE WHEN SUM(water_elec_kwh) > 0 THEN SUM(water_heat_kwh) / SUM(water_elec_kwh) ELSE NULL END AS water_cop,
                CASE WHEN SUM(weighted_kwh_heat) > 0 THEN SUM(weighted_flowT * weighted_kwh_heat) / SUM(weighted_kwh_heat) ELSE NULL END AS weighted_flowT,
                CASE WHEN SUM(weighted_kwh_heat) > 0 THEN SUM(weighted_outsideT * weighted_kwh_heat) / SUM(weighted_kwh_heat) ELSE NULL END AS weighted_outsideT,
                CASE WHEN SUM(weighted_kwh_heat) > 0 THEN SUM(weighted_flowT_minus_outsideT * weighted_kwh_heat) / SUM(weighted_kwh_heat) ELSE NULL END AS weighted_flowT_minus_outsideT,
                CASE WHEN SUM(weighted_kwh_heat) > 0 THEN SUM(weighted_flowT_minus_returnT * weighted_kwh_heat) / SUM(weighted_kwh_heat) ELSE NULL END AS weighted_flowT_minus_returnT,
                CASE WHEN SUM(weighted_kwh_elec) > 0 THEN SUM(weighted_elec * weighted_kwh_elec) / SUM(weighted_kwh_elec) ELSE NULL END AS weighted_elec,
                CASE WHEN SUM(weighted_kwh_heat) > 0 THEN SUM(weighted_heat * weighted_kwh_heat) / SUM(weighted_kwh_heat) ELSE NULL END AS weighted_heat,
                CASE WHEN SUM(weighted_kwh_elec_running) > 0 AND SUM(weighted_kwh_carnot_elec) > 0 THEN
                    100 * (SUM(weighted_kwh_heat_running) / SUM(weighted_kwh_elec_running)) / (SUM(weighted_kwh_heat_running) / SUM(weighted_kwh_carnot_elec))
                ELSE 0 END AS weighted_prc_carnot,
                CASE WHEN SUM(from_energy_feeds_elec_kwh) > 0 THEN ROUND(100 * SUM(unit_rate_agile * from_energy_feeds_elec_kwh) / SUM(from_energy_feeds_elec_kwh), 1) ELSE NULL END AS unit_rate_agile,
                CASE WHEN SUM(from_energy_feeds_elec_kwh) > 0 THEN ROUND(100 * SUM(unit_rate_cosy * from_energy_feeds_elec_kwh) / SUM(from_energy_feeds_elec_kwh), 1) ELSE NULL END AS unit_rate_cosy,
                CASE WHEN SUM(from_energy_feeds_elec_kwh) > 0 THEN ROUND(100 * SUM(unit_rate_go * from_energy_feeds_elec_kwh) / SUM(from_energy_feeds_elec_kwh), 1) ELSE NULL END AS unit_rate_go,
                CASE WHEN SUM(from_energy_feeds_elec_kwh) > 0 THEN ROUND(100 * SUM(unit_rate_eon_next_pumped_v2 * from_energy_feeds_elec_kwh) / SUM(from_energy_feeds_elec_kwh), 1) ELSE NULL END AS unit_rate_eon_next_pumped_v2
            FROM system_stats_monthly_v2
            WHERE timestamp >= {$start_ts} AND timestamp < {$end_ts}
            GROUP BY id";
    }

    private function build_filter_sql($filter, $combined_cop_sql)
    {
        $filter = trim($filter);
        if ($filter === '') {
            return '';
        }

        if ($filter === 'MID') {
            return ' AND sm.mid_metering = 1 ';
        }
        if ($filter === 'HG' || $filter === 'HeatGeek') {
            return ' AND sm.heatgeek = 1 ';
        }
        if ($filter === 'NHG') {
            return ' AND COALESCE(sm.heatgeek,0) = 0 ';
        }
        if ($filter === 'UR') {
            return ' AND sm.ultimaterenewables = 1 ';
        }
        if ($filter === 'HA') {
            return ' AND sm.heatingacademy = 1 ';
        }
        if ($filter === 'HG4') {
            return ' AND sm.heatgeek = 1 AND (' . $combined_cop_sql . ') > 4 ';
        }

        if (strpos($filter, 'query:') === 0) {
            return $this->build_query_filter_sql(substr($filter, 6));
        }

        $like = $this->mysqli->real_escape_string(strtolower($filter));
        $cols = array(
            'sm.location', 'sm.hp_manufacturer', 'sm.hp_model', 'sm.installer_name',
            'sm.hp_type', 'sm.property', 'sm.refrigerant'
        );
        $ors = array();
        foreach ($cols as $c) {
            $ors[] = "LOWER({$c}) LIKE '%{$like}%'";
        }
        return ' AND (' . implode(' OR ', $ors) . ') ';
    }

    /**
     * Parse query:field:value:op:enabled,...
     */
    private function build_query_filter_sql($query_body)
    {
        $parts = explode(',', $query_body);
        $conds = array();
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $bits = explode(':', $part);
            $field = isset($bits[0]) ? preg_replace('/[^a-zA-Z0-9_]/', '', $bits[0]) : '';
            $value = isset($bits[1]) ? $bits[1] : '';
            $operator = isset($bits[2]) ? $bits[2] : 'eq';
            $enabled = isset($bits[3]) ? $bits[3] : 't';
            if ($field === '' || $enabled === 'f') {
                continue;
            }
            if (!$this->is_allowed_filter_field($field)) {
                continue;
            }

            $col = $this->filter_field_sql_expr($field);
            $numeric = is_numeric($value);
            $esc = $this->mysqli->real_escape_string($value);

            if ($numeric) {
                $v = $value + 0;
                switch ($operator) {
                    case 'gt':
                        $conds[] = "({$col} > {$v})";
                        break;
                    case 'lt':
                        $conds[] = "({$col} < {$v})";
                        break;
                    case 'gte':
                        $conds[] = "({$col} >= {$v})";
                        break;
                    case 'lte':
                        $conds[] = "({$col} <= {$v})";
                        break;
                    case 'ne':
                        $conds[] = "({$col} <> {$v})";
                        break;
                    default:
                        $conds[] = "({$col} = {$v})";
                }
            } else {
                switch ($operator) {
                    case 'ne':
                        $conds[] = "(LOWER({$col}) NOT LIKE '%" . $this->mysqli->real_escape_string(strtolower($esc)) . "%')";
                        break;
                    default:
                        $conds[] = "(LOWER({$col}) LIKE '%" . $this->mysqli->real_escape_string(strtolower($esc)) . "%')";
                }
            }
        }
        if (!count($conds)) {
            return '';
        }
        return ' AND (' . implode(' AND ', $conds) . ') ';
    }

    private function is_allowed_filter_field($field)
    {
        static $meta = null;
        if ($meta === null) {
            $meta = array_keys($this->system->schema_meta);
        }
        static $stats = null;
        if ($stats === null) {
            $schema = array();
            require 'Modules/system/system_schema.php';
            $stats = array_keys($schema['system_stats_daily']);
        }
        return in_array($field, $meta, true) || in_array($field, $stats, true)
            || in_array($field, array('hp_make_model', 'training', 'oversizing_factor', 'combined_cop', 'selected_unit_rate'), true);
    }

    private function is_stats_field($field)
    {
        static $stats = null;
        if ($stats === null) {
            $schema = array();
            require 'Modules/system/system_schema.php';
            $stats = array_flip(array_keys($schema['system_stats_daily']));
        }
        return isset($stats[$field]);
    }

    /**
     * SQL expression for structured filters (query:field:…).
     */
    private function filter_field_sql_expr($field)
    {
        if ($field === 'id') {
            return 'sm.id';
        }
        if ($field === 'combined_cop') {
            return '(CASE WHEN COALESCE(s.combined_elec_kwh,0) > 0 THEN (s.combined_heat_kwh / s.combined_elec_kwh) ELSE NULL END)';
        }
        if ($field === 'hp_make_model') {
            return 'TRIM(CONCAT(COALESCE(sm.hp_manufacturer,\'\'),\' \',COALESCE(sm.hp_model,\'\')))';
        }
        if ($field === 'training') {
            return '((CASE WHEN sm.heatgeek = 1 THEN 1 ELSE 0 END)+(CASE WHEN sm.ultimaterenewables = 1 THEN 1 ELSE 0 END)+(CASE WHEN sm.heatingacademy = 1 THEN 1 ELSE 0 END))';
        }
        if ($field === 'oversizing_factor') {
            return '(CASE WHEN COALESCE(sm.measured_heat_loss,0) > 0 THEN (COALESCE(NULLIF(sm.hp_max_output_test,0), NULLIF(sm.hp_max_output,0)) / sm.measured_heat_loss) ELSE NULL END)';
        }
        if ($field === 'selected_unit_rate') {
            return '(' . $this->selected_unit_rate_sql('flat') . ')';
        }
        if ($this->is_stats_field($field)) {
            return 's.' . $field;
        }
        return 'sm.' . $field;
    }

    private function build_order_sql($sort, $dir, $mode, $tariff)
    {
        $diru = strtoupper($dir === 'asc' ? 'ASC' : 'DESC');

        if ($sort === 'id') {
            return ' ORDER BY sm.id ' . $diru . ' ';
        }
        if ($sort === 'share' || $sort === 'published') {
            return ' ORDER BY sm.`' . $sort . '` ' . $diru . ' , sm.id ASC ';
        }

        if ($sort === 'combined_cop') {
            return ' ORDER BY (CASE WHEN COALESCE(s.combined_elec_kwh,0) > 0 THEN (s.combined_heat_kwh / s.combined_elec_kwh) ELSE NULL END) ' . $diru . ' , sm.id ASC ';
        }
        if ($sort === 'hp_make_model') {
            return ' ORDER BY TRIM(CONCAT(COALESCE(sm.hp_manufacturer,\'\'),\' \',COALESCE(sm.hp_model,\'\'))) ' . $diru . ' , sm.id ASC ';
        }
        if ($sort === 'training') {
            return ' ORDER BY ((CASE WHEN sm.heatgeek = 1 THEN 1 ELSE 0 END)+(CASE WHEN sm.ultimaterenewables = 1 THEN 1 ELSE 0 END)+(CASE WHEN sm.heatingacademy = 1 THEN 1 ELSE 0 END)) ' . $diru . ' , sm.id ASC ';
        }
        if ($sort === 'oversizing_factor') {
            return ' ORDER BY (CASE WHEN COALESCE(sm.measured_heat_loss,0) > 0 THEN (COALESCE(NULLIF(sm.hp_max_output_test,0), NULLIF(sm.hp_max_output,0)) / sm.measured_heat_loss) ELSE NULL END) ' . $diru . ' , sm.id ASC ';
        }
        if ($sort === 'combined_elec_kwh_per_m2') {
            return ' ORDER BY (CASE WHEN sm.floor_area IS NOT NULL AND sm.floor_area > 0 THEN (COALESCE(s.combined_elec_kwh,0)/sm.floor_area) ELSE NULL END) ' . $diru . ' , sm.id ASC ';
        }
        if ($sort === 'combined_heat_unit_cost') {
            $rate = $this->selected_unit_rate_sql($tariff);
            return ' ORDER BY (CASE WHEN COALESCE(s.combined_cop,0) > 0 THEN ((' . $rate . ') / s.combined_cop) ELSE NULL END) ' . $diru . ' , sm.id ASC ';
        }
        if ($sort === 'combined_cost') {
            $rate = $this->selected_unit_rate_sql($tariff);
            return ' ORDER BY (COALESCE(s.combined_elec_kwh,0) * (' . $rate . ') * 0.01) ' . $diru . ' , sm.id ASC ';
        }
        if (($sort === 'name' || $sort === 'username') && $mode !== 'public') {
            return ' ORDER BY u_sort.username ' . $diru . ' , sm.id ASC ';
        }

        if ($this->is_stats_field($sort)) {
            return ' ORDER BY s.`' . $sort . '` ' . $diru . ' , sm.id ASC ';
        }
        if (isset($this->system->schema_meta[$sort])) {
            return ' ORDER BY sm.`' . $sort . '` ' . $diru . ' , sm.id ASC ';
        }

        return ' ORDER BY (CASE WHEN COALESCE(s.combined_elec_kwh,0) > 0 THEN (s.combined_heat_kwh / s.combined_elec_kwh) ELSE NULL END) DESC , sm.id ASC ';
    }

    private function fetch_chart_points($ctx, $params)
    {
        $x = isset($params['xaxis']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $params['xaxis']) : 'weighted_flowT_minus_outsideT';
        $y = isset($params['yaxis']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $params['yaxis']) : 'combined_cop';
        $c = isset($params['color']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $params['color']) : 'weighted_prc_carnot';

        if (!$this->is_allowed_filter_field($x) || !$this->is_allowed_filter_field($y) || !$this->is_allowed_filter_field($c)) {
            return array();
        }

        $xexpr = $this->chart_field_sql($x);
        $yexpr = $this->chart_field_sql($y);
        $cexpr = $this->chart_field_sql($c);

        $sql = "SELECT pt.id, pt.location, pt.hp_output, pt.hp_model, pt.xv, pt.yv, pt.cv FROM (
            SELECT sm.id, sm.location, sm.hp_output, sm.hp_model,
                {$xexpr} AS xv, {$yexpr} AS yv, {$cexpr} AS cv
            FROM {$ctx['from_sql']}
            {$ctx['where_sql']}
        ) pt WHERE pt.xv IS NOT NULL AND pt.yv IS NOT NULL AND pt.xv <> 0 AND pt.yv <> 0";

        $res = $this->mysqli->query($sql);
        if (!$res) {
            return array();
        }
        $pts = array();
        while ($row = $res->fetch_object()) {
            $cv = $row->cv;
            if ($cv !== null && is_numeric($cv)) {
                $cv = strpos((string) $cv, '.') !== false ? (float) $cv : (int) $cv;
            }
            $pts[] = array(
                'id' => (int) $row->id,
                'location' => $row->location,
                'hp_output' => $row->hp_output,
                'hp_model' => $row->hp_model,
                'x' => $row->xv !== null ? (float) $row->xv : null,
                'y' => $row->yv !== null ? (float) $row->yv : null,
                'color' => $cv,
            );
        }
        return $pts;
    }

    private function chart_field_sql($field)
    {
        if ($field === 'combined_cop') {
            return '(CASE WHEN COALESCE(s.combined_elec_kwh,0) > 0 THEN (s.combined_heat_kwh / s.combined_elec_kwh) ELSE NULL END)';
        }
        if ($field === 'hp_make_model') {
            return 'TRIM(CONCAT(COALESCE(sm.hp_manufacturer,\'\'),\' \',COALESCE(sm.hp_model,\'\')))';
        }
        if ($field === 'training') {
            return '((CASE WHEN sm.heatgeek = 1 THEN 1 ELSE 0 END)+(CASE WHEN sm.ultimaterenewables = 1 THEN 1 ELSE 0 END)+(CASE WHEN sm.heatingacademy = 1 THEN 1 ELSE 0 END))';
        }
        if ($field === 'oversizing_factor') {
            return '(CASE WHEN COALESCE(sm.measured_heat_loss,0) > 0 THEN (COALESCE(NULLIF(sm.hp_max_output_test,0), NULLIF(sm.hp_max_output,0)) / sm.measured_heat_loss) ELSE NULL END)';
        }
        if ($this->is_stats_field($field)) {
            return 's.' . $field;
        }
        return 'sm.' . $field;
    }

    private function fetch_csv_payload($ctx, $params, $tariff)
    {
        $cols = isset($params['csv_columns']) ? explode(',', $params['csv_columns']) : array('location', 'combined_cop');
        $safe = array();
        foreach ($cols as $c) {
            $c = preg_replace('/[^a-zA-Z0-9_]/', '', $c);
            if ($c !== '' && $this->is_allowed_filter_field($c) && !in_array($c, array('installer_logo', 'training', 'learnmore'), true)) {
                $safe[] = $c;
            }
        }
        if (!count($safe)) {
            $safe = array('location', 'combined_cop');
        }

        $select_bits = array('sm.id AS id');
        foreach ($safe as $col) {
            if ($col === 'id') {
                continue;
            }
            $select_bits[] = $this->csv_select_expr($col, $tariff) . ' AS `' . $col . '`';
        }

        $sql = 'SELECT ' . implode(', ', $select_bits) . ' FROM ' . $ctx['from_sql'] . ' ' . $ctx['where_sql'] . ' ORDER BY sm.id ASC';
        $res = $this->mysqli->query($sql);
        if (!$res) {
            return array('columns' => $safe, 'rows' => array());
        }
        $rows = array();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        return array('columns' => $safe, 'rows' => $rows);
    }

    private function csv_select_expr($col, $tariff)
    {
        if ($col === 'combined_cop') {
            return '(CASE WHEN COALESCE(s.combined_elec_kwh,0) > 0 THEN (s.combined_heat_kwh / s.combined_elec_kwh) ELSE NULL END)';
        }
        if ($col === 'hp_make_model') {
            return 'TRIM(CONCAT(COALESCE(sm.hp_manufacturer,\'\'),\' \',COALESCE(sm.hp_model,\'\')))';
        }
        if ($col === 'training') {
            return '((CASE WHEN sm.heatgeek = 1 THEN 1 ELSE 0 END)+(CASE WHEN sm.ultimaterenewables = 1 THEN 1 ELSE 0 END)+(CASE WHEN sm.heatingacademy = 1 THEN 1 ELSE 0 END))';
        }
        if ($col === 'oversizing_factor') {
            return '(CASE WHEN COALESCE(sm.measured_heat_loss,0) > 0 THEN (COALESCE(NULLIF(sm.hp_max_output_test,0), NULLIF(sm.hp_max_output,0)) / sm.measured_heat_loss) ELSE NULL END)';
        }
        if ($col === 'selected_unit_rate') {
            return '(' . $this->selected_unit_rate_sql($tariff) . ')';
        }
        if ($this->is_stats_field($col)) {
            return 's.' . $col;
        }
        return 'sm.' . $col;
    }

    private function fetch_usernames(array $userids)
    {
        if (!count($userids)) {
            return array();
        }
        $userids = array_map('intval', array_unique($userids));
        $ids_string = implode(',', $userids);
        $usernames = array();
        $result = $this->emoncms_mysqli->query("SELECT id, username FROM users WHERE id IN ($ids_string)");
        if ($result) {
            while ($row = $result->fetch_object()) {
                $usernames[(int) $row->id] = $row->username;
            }
        }
        return $usernames;
    }

    private function finalize_row($row, $remove_private_fields, $last_updated_by_app = null)
    {
        global $path;

        $row = $this->system->typecast($row);

        if (isset($row->heatpump_model_id) && $row->heatpump_model_id) {
            $row->heatpump_url = $path . 'heatpump/view?id=' . (int) $row->heatpump_model_id;
        }

        $row->heatpump_elec_feedid = false;
        $row->heatpump_heat_feedid = false;
        $row->heatpump_elec_ago = 876000;
        $row->heatpump_heat_ago = 876000;
        $row->heatpump_max_age = 876000;

        if (!$remove_private_fields && isset($row->app_id) && $row->app_id) {
            $aid = (int) $row->app_id;
            $last_updated = false;
            if (is_array($last_updated_by_app)) {
                if (isset($last_updated_by_app[$aid])) {
                    $last_updated = $last_updated_by_app[$aid];
                }
            } else {
                $last_updated = $this->system->get_last_updated($row->app_id);
            }
            if ($last_updated) {
                $row->heatpump_elec_feedid = $last_updated['elec_feedid'];
                $row->heatpump_heat_feedid = $last_updated['heat_feedid'];
                $row->heatpump_elec_ago = $last_updated['elec_ago'];
                $row->heatpump_heat_ago = $last_updated['heat_ago'];
                $row->heatpump_max_age = $last_updated['max_age'];
            }
        }

        // Prefer SQL-combined COP name alignment with UI — overwrite s.combined_cop if calc present
        if (isset($row->combined_cop_calc)) {
            $row->combined_cop = $row->combined_cop_calc;
            unset($row->combined_cop_calc);
        }

        // Dynamic air-error overlay on data_flag (matches prior client behaviour)
        $dyn = $this->eval_dynamic_air_error((array) $row);
        if ($dyn['active']) {
            if (!isset($row->dynamic_data_flag) || !$row->dynamic_data_flag) {
                $row->data_flag_static = isset($row->data_flag) ? $row->data_flag : 0;
                $row->data_flag_note_static = isset($row->data_flag_note) ? $row->data_flag_note : '';
            }
            $row->dynamic_data_flag = 1;
            $row->data_flag = 1;
            $row->data_flag_note = $dyn['note'];
        } else {
            if (isset($row->dynamic_data_flag) && $row->dynamic_data_flag == 1) {
                $row->dynamic_data_flag = 0;
                if (isset($row->data_flag_static)) {
                    $row->data_flag = $row->data_flag_static;
                    $row->data_flag_note = $row->data_flag_note_static;
                }
            }
        }

        if ($remove_private_fields) {
            unset($row->url);
            unset($row->userid);
            unset($row->app_id);
            unset($row->readkey);
        }

        return $row;
    }

    private function eval_dynamic_air_error(array $row)
    {
        $eak = isset($row['error_air_kwh']) ? (float) $row['error_air_kwh'] : 0;
        $ce = isset($row['combined_elec_kwh']) ? (float) $row['combined_elec_kwh'] : 0;
        $ch = isset($row['combined_heat_kwh']) ? (float) $row['combined_heat_kwh'] : 0;
        $cop = ($ce > 0) ? $ch / $ce : 0;
        $ea = isset($row['error_air']) ? (float) $row['error_air'] : 0;

        if ($eak <= 0 || $ce <= 0) {
            return array('active' => false);
        }
        $elec_ex = $ce - $eak;
        if ($elec_ex <= 0) {
            return array('active' => false);
        }
        $cop_air = $ch / $elec_ex;
        if (abs($cop - $cop_air) <= 0.2) {
            return array('active' => false);
        }

        $prc = ($eak / $ce * 100);
        $note = 'Heat meter air error\n';
        $note .= round($ea / 3600) . ' hours, ';
        $note .= round($eak) . " kWh electric\n";
        $note .= '% of electric consumption: ' . round($prc, 1) . "%\n";
        $note .= 'COP listed: ' . round($cop, 2) . "\n";
        $note .= 'COP not including air error: ' . round($cop_air, 2) . "\n";
        $note .= 'Difference: ' . round($cop - $cop_air, 2);

        return array('active' => true, 'note' => $note);
    }
}
