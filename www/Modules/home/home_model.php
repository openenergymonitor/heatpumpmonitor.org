<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class Home
{
    private $system_stats;
    private $redis;
    private $mysqli;

    public function __construct($system_stats, $redis, $mysqli = null)
    {
        $this->system_stats = $system_stats;
        $this->redis = $redis;
        $this->mysqli = $mysqli;
    }

    // Pre-rendered SVG map of system locations for the home page hero:
    // a simplified UK & Ireland coastline (see scripts/build_hero_map_geometry.py)
    // with one dot per public MID-metered system, coloured by installer as
    // on the full map page. Rendering server-side keeps the hero instant — no tiles,
    // no map library, no data fetch — and the result is cached in redis
    // until 7am the next day, shortly after the nightly stats update.
    // Returns array(svg, count, plotted).
    public function hero_map()
    {
        $cache_key = "hpmon:home:hero_map";

        if ($this->redis) {
            $cached = $this->redis->get($cache_key);
            if ($cached) {
                $decoded = json_decode($cached, true);
                if ($decoded !== null) return $decoded;
            }
        }

        $geometry = json_decode(file_get_contents(__DIR__."/hero_map_geometry.json"));
        if ($geometry === null || !$this->mysqli) {
            return array("svg" => "", "count" => 0, "plotted" => 0);
        }

        // Installer name → brand colour, as used on the full map page
        $colors = array();
        if ($result = $this->mysqli->query("SELECT name, color FROM installer")) {
            while ($row = $result->fetch_object()) {
                if ($row->name && $row->color && preg_match('/^#[0-9a-fA-F]{3,8}$/', $row->color)) {
                    $colors[trim($row->name)] = strtolower($row->color);
                }
            }
        }

        $default_color = "#cccccc";
        $count = 0;
        $plotted = 0;
        $dots_by_color = array();

        // Same query as mid_metered_count (which the hero copy quotes), plus
        // the location fields — so the dot count always matches the text
        $systems = $this->system_stats->combined_meta_stats_query(array(
            'meta_filters' => array('mid_metering' => 1),
            'meta_fields' => array('id', 'latitude', 'longitude', 'installer_name'),
            'stats_fields' => array(),
            'stats_table' => "system_stats_last365_v2"
        ));
        foreach ($systems as $row) {
            $count++;
            $lat = (float) $row->latitude;
            $lon = (float) $row->longitude;
            if (!$lat || !$lon) continue;

            // Same projection the geometry file was built with
            // (see scripts/build_hero_map_geometry.py)
            $lam = deg2rad($lon - $geometry->lon0);
            $phi = deg2rad($lat);
            if ($geometry->proj === "tm") {
                $B = cos($phi) * sin($lam);
                $px = 0.5 * log((1 + $B) / (1 - $B));
                $py = atan2(tan($phi), cos($lam));
            } else {
                $px = $lam;
                $py = log(tan(M_PI / 4 + $phi / 2));
            }
            $x = ($px - $geometry->xmin) * $geometry->scale;
            $y = ($geometry->ymax - $py) * $geometry->scale;

            // Systems beyond the map viewport are counted but not drawn
            if ($x < 0 || $x > $geometry->width) continue;
            if ($y < 0 || $y > $geometry->height) continue;

            $color = $default_color;
            $installer = $row->installer_name !== null ? trim($row->installer_name) : "";
            if ($installer !== "" && isset($colors[$installer])) $color = $colors[$installer];

            $dots_by_color[$color][] = array(round($x, 1), round($y, 1));
            $plotted++;
        }

        // Uncoloured systems first so installer-coloured dots draw on top
        uksort($dots_by_color, function($a, $b) use ($default_color) {
            if ($a === $default_color) return -1;
            if ($b === $default_color) return 1;
            return strcmp($a, $b);
        });

        // xMax pins the map to the card's right edge: any letterbox space in
        // a wide card becomes open Atlantic on the left, under the overlays
        $svg = '<svg class="hpm-hero-map" viewBox="0 0 '.$geometry->width.' '.$geometry->height.'" preserveAspectRatio="xMaxYMid meet" role="img" aria-label="Map of monitored heat pump systems across the UK and Ireland">';
        $svg .= '<g class="hpm-hero-map-land"><path d="'.implode('', $geometry->paths).'"/></g>';
        $svg .= '<g class="hpm-hero-map-dots">';
        foreach ($dots_by_color as $color => $dots) {
            $svg .= '<g fill="'.$color.'">';
            foreach ($dots as $dot) {
                $svg .= '<circle cx="'.$dot[0].'" cy="'.$dot[1].'" r="5.2"/>';
            }
            $svg .= '</g>';
        }
        $svg .= '</g></svg>';

        $output = array("svg" => $svg, "count" => $count, "plotted" => $plotted);

        if ($this->redis) {
            $timezone = new DateTimeZone('Europe/London');
            $now = new DateTime('now', $timezone);
            $expires = new DateTime('today 07:00', $timezone);
            if ($expires <= $now) $expires->modify('+1 day');
            $this->redis->setex($cache_key, $expires->getTimestamp() - $now->getTimestamp(), json_encode($output));
        }

        return $output;
    }

    // Returns all eligible systems (MID metered, metering boundary code 4,
    // valid COP, >330 days of data, not data-flagged) with the
    // home-description meta fields and last-365-day stats. Any filtering,
    // e.g. to homes like the user's, is done client-side on the home page.
    public function eligible_systems()
    {
        $query = array(
            'meta_filters' => array(
                'mid_metering' => 1,
                'metering_boundary_code' => 4
            ),
            'meta_fields' => array(
                'id',
                'location',
                'hp_type',
                'hp_manufacturer',
                'hp_model',
                'hp_output',
                'floor_area',
                'property',
                'age',
                'insulation',
                'data_flag',
                'flow_temp',
                'design_temp',
                'measured_mean_flow_temp_coldest_day',
                'measured_outside_temp_coldest_day'
            ),
            'stats_fields' => array(
                'combined_elec_kwh',
                'combined_heat_kwh',
                'immersion_kwh',
                'combined_cop',
                'combined_data_length',
                'error_air_kwh',
                'weighted_flowT',
                'weighted_flowT_minus_outsideT',
                'weighted_prc_carnot',
                'cooling_heat_kwh',
                'unit_rate_agile',
                'unit_rate_cosy',
                'unit_rate_go'
            ),
            "sort" => array(
                "field" => "combined_cop",
                "order" => "desc"
            ),
            "stats_table" => "system_stats_last365_v2"
        );

        $systems = $this->system_stats->combined_meta_stats_query($query);

        $output_systems = array();
        foreach ($systems as $system) {

            // Only include systems with a valid COP figure
            if ($system->combined_cop === null) continue;

            // combined_data_length in seconds converted to days
            $days = $system->combined_data_length / 86400;
            if ($days <= 330) continue;

            $this->auto_flag_air_error($system);

            if ($system->data_flag == 1) continue;

            // Trim the payload to what the home page uses
            $output_systems[] = array(
                'id' => (int) $system->id,
                'location' => $system->location,
                'hp_type' => $system->hp_type,
                'hp_manufacturer' => $system->hp_manufacturer,
                'hp_model' => $system->hp_model,
                'hp_output' => $system->hp_output !== null ? (float) $system->hp_output : null,
                'floor_area' => $system->floor_area !== null ? (float) $system->floor_area : null,
                'property' => $system->property,
                'age' => $system->age,
                'insulation' => $system->insulation,
                'flow_temp' => $system->flow_temp !== null ? (float) $system->flow_temp : null,
                'design_temp' => $system->design_temp !== null ? (float) $system->design_temp : null,
                'measured_flow_temp' => $system->measured_mean_flow_temp_coldest_day !== null ? (float) $system->measured_mean_flow_temp_coldest_day : null,
                'measured_outside_temp_coldest_day' => $system->measured_outside_temp_coldest_day !== null ? (float) $system->measured_outside_temp_coldest_day : null,
                'weighted_flowT' => $system->weighted_flowT !== null ? round($system->weighted_flowT, 1) : null,
                'weighted_flowT_minus_outsideT' => $system->weighted_flowT_minus_outsideT !== null ? round($system->weighted_flowT_minus_outsideT, 1) : null,
                // 0 means the stat could not be computed — treat as missing
                'weighted_prc_carnot' => $system->weighted_prc_carnot !== null && $system->weighted_prc_carnot > 0 ? round($system->weighted_prc_carnot, 1) : null,
                'cooling_heat_kwh' => $system->cooling_heat_kwh !== null ? round($system->cooling_heat_kwh, 1) : null,
                'unit_rate_agile' => $system->unit_rate_agile !== null ? round($system->unit_rate_agile, 2) : null,
                'unit_rate_cosy' => $system->unit_rate_cosy !== null ? round($system->unit_rate_cosy, 2) : null,
                'unit_rate_go' => $system->unit_rate_go !== null ? round($system->unit_rate_go, 2) : null,
                'combined_cop' => round($system->combined_cop, 2),
                'combined_elec_kwh' => round($system->combined_elec_kwh),
                'combined_heat_kwh' => round($system->combined_heat_kwh)
            );
        }
        return $output_systems;
    }

    // Count of all MID metered systems, any metering boundary and any data
    // length. Cached in redis until 7am the next day, shortly after the
    // nightly stats update.
    public function mid_metered_count()
    {
        $cache_key = "hpmon:home:mid_metered_count";

        if ($this->redis) {
            $cached = $this->redis->get($cache_key);
            if ($cached !== false && $cached !== null) return (int) $cached;
        }

        $query = array(
            'meta_filters' => array(
                'mid_metering' => 1
            ),
            'meta_fields' => array('id'),
            'stats_fields' => array(),
            'stats_table' => "system_stats_last365_v2"
        );

        $count = count($this->system_stats->combined_meta_stats_query($query));

        if ($this->redis) {
            $timezone = new DateTimeZone('Europe/London');
            $now = new DateTime('now', $timezone);
            $expires = new DateTime('today 07:00', $timezone);
            if ($expires <= $now) $expires->modify('+1 day');
            $this->redis->setex($cache_key, $expires->getTimestamp() - $now->getTimestamp(), $count);
        }

        return $count;
    }

    // Systems that have provided active cooling over the last 30 days
    public function cooling_systems()
    {
        $query = array(
            'meta_filters' => array(
                'mid_metering' => 1,
                'metering_boundary_code' => 4
            ),
            'meta_fields' => array(
                'id',
                'location',
                'hp_type',
                'hp_manufacturer',
                'hp_model',
                'hp_output',
                'floor_area',
                'data_flag'
            ),
            'stats_fields' => array(
                'cooling_elec_kwh',
                'cooling_heat_kwh',
                'cooling_cop',
                'combined_data_length',
                'error_air_kwh'
            ),
            "sort" => array(
                "field" => "cooling_heat_kwh",
                "order" => "desc"
            ),
            "stats_table" => "system_stats_last30_v2"
        );

        $systems = $this->system_stats->combined_meta_stats_query($query);

        $output_systems = array();
        foreach ($systems as $system) {

            // Only include systems with a valid COP figure
            if ($system->cooling_cop === null) continue;
            // filter out systems with less than 1 kWh of cooling heat delivered
            if ($system->cooling_heat_kwh < 1) continue;

            // combined_data_length in seconds converted to days
            $days = $system->combined_data_length / 86400;
            if ($days <= 20) continue;

            // $this->auto_flag_air_error($system);
            // if ($system->data_flag == 1) continue;

            // Trim the payload to what the home page uses
            $output_systems[] = array(
                'id' => (int) $system->id,
                'location' => $system->location,
                'hp_type' => $system->hp_type,
                'hp_manufacturer' => $system->hp_manufacturer,
                'hp_model' => $system->hp_model,
                'hp_output' => $system->hp_output !== null ? (float) $system->hp_output : null,
                'floor_area' => $system->floor_area !== null ? (float) $system->floor_area : null,
                'cooling_cop' => round($system->cooling_cop, 2),
                'cooling_elec_kwh' => round($system->cooling_elec_kwh),
                'cooling_heat_kwh' => round($system->cooling_heat_kwh)
            );
        }
        return $output_systems;
    }

    // Systems with an installation cost recorded in system_meta
    // (installation_Cost is entered before subtracting the grant), with the
    // stats the economics explorer needs: measured SPF and annual heat
    // demand. Any metering boundary is included — the boundary code is
    // returned so the UI can badge non-H4 systems.
    public function economics_systems()
    {
        $query = array(
            'meta_filters' => array(
                'mid_metering' => 1
            ),
            'meta_fields' => array(
                'id',
                'location',
                'hp_type',
                'hp_manufacturer',
                'hp_model',
                'hp_output',
                'floor_area',
                'property',
                'installer_name',
                'installer_url',
                'installer_logo',
                'metering_boundary_code',
                'installation_Cost',
                'data_flag'
            ),
            'stats_fields' => array(
                'combined_elec_kwh',
                'combined_heat_kwh',
                'combined_cop',
                'combined_data_length',
                'error_air_kwh'
            ),
            "sort" => array(
                "field" => "combined_cop",
                "order" => "desc"
            ),
            "stats_table" => "system_stats_last365_v2"
        );

        $systems = $this->system_stats->combined_meta_stats_query($query);

        $output_systems = array();
        foreach ($systems as $system) {

            // Only systems that have shared their installation cost
            if ($system->installation_Cost === null || $system->installation_Cost <= 0) continue;

            // Only include systems with a valid COP figure
            if ($system->combined_cop === null) continue;

            // combined_data_length in seconds converted to days
            $days = $system->combined_data_length / 86400;
            if ($days <= 330) continue;

            $this->auto_flag_air_error($system);

            if ($system->data_flag == 1) continue;

            $output_systems[] = array(
                'id' => (int) $system->id,
                'location' => $system->location,
                'hp_type' => $system->hp_type,
                'hp_manufacturer' => $system->hp_manufacturer,
                'hp_model' => $system->hp_model,
                'hp_output' => $system->hp_output !== null ? (float) $system->hp_output : null,
                'floor_area' => $system->floor_area !== null ? (float) $system->floor_area : null,
                'property' => $system->property,
                'installer' => $system->installer_name,
                'installer_url' => $system->installer_url,
                'installer_logo' => $system->installer_logo,
                'boundary' => $system->metering_boundary_code !== null ? (int) $system->metering_boundary_code : null,
                'cost' => round($system->installation_Cost),
                'cop' => round($system->combined_cop, 2),
                'elec' => round($system->combined_elec_kwh),
                'heat' => round($system->combined_heat_kwh)
            );
        }
        return $output_systems;
    }

    // Latest topics in the forum's heat pump category, proxied server-side
    // (the Discourse API sends no CORS headers) and cached for 10 minutes.
    public function forum_topics()
    {
        $cache_file = sys_get_temp_dir()."/hpmon_home_forum_topics.json";
        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 600) {
            $cached = json_decode(file_get_contents($cache_file));
            if ($cached !== null) return $cached;
        }

        $context = stream_context_create(array("http" => array(
            "timeout" => 5,
            "user_agent" => "HeatpumpMonitor.org home page"
        )));
        
        $response = @file_get_contents("https://community.openenergymonitor.org/c/hardware/heatpump/47/l/latest.json?v=".time(), false, $context);
        $data = $response !== false ? json_decode($response) : null;

        if ($data === null || !isset($data->topic_list->topics)) {
            // Forum unreachable: serve the stale cache if there is one
            if (file_exists($cache_file)) {
                $cached = json_decode(file_get_contents($cache_file));
                if ($cached !== null) return $cached;
            }
            return array();
        }

        // The topic list references posters by user id; avatars live in a
        // separate users array
        $users = array();
        foreach ($data->users as $user) {
            $avatar = str_replace("{size}", "96", $user->avatar_template);
            if (substr($avatar, 0, 1) == "/") $avatar = "https://community.openenergymonitor.org".$avatar;
            $users[$user->id] = array("username" => $user->username, "avatar" => $avatar);
        }

        $topics = array();
        foreach ($data->topic_list->topics as $topic) {
            // Skip the pinned "About this category" style topics
            if (!empty($topic->pinned)) continue;

            // Only show established discussions
            // if ($topic->posts_count <= 10) continue;

            $posters = array();
            foreach ($topic->posters as $poster) {
                if (isset($users[$poster->user_id])) $posters[] = $users[$poster->user_id];
            }

            $tags = array();
            if (isset($topic->tags)) {
                foreach ($topic->tags as $tag) $tags[] = is_object($tag) ? $tag->name : $tag;
            }

            $topics[] = array(
                "id" => (int) $topic->id,
                "title" => $topic->title,
                "url" => "https://community.openenergymonitor.org/t/".$topic->slug."/".$topic->id,
                "replies" => max(0, (int) $topic->posts_count - 1),
                "views" => (int) $topic->views,
                "likes" => (int) $topic->like_count,
                "bumped_at" => $topic->bumped_at,
                "solved" => !empty($topic->has_accepted_answer),
                "tags" => $tags,
                "posters" => $posters
            );
            if (count($topics) >= 5) break;
        }

        @file_put_contents($cache_file, json_encode($topics));
        return $topics;
    }

    // Auto flag air errors if the air error changes the COP by more than 0.2
    private function auto_flag_air_error($system)
    {
        $combined_elec_kwh = isset($system->combined_elec_kwh) ? $system->combined_elec_kwh : (isset($system->stats['combined_elec_kwh']) ? $system->stats['combined_elec_kwh'] : null);
        $combined_heat_kwh = isset($system->combined_heat_kwh) ? $system->combined_heat_kwh : (isset($system->stats['combined_heat_kwh']) ? $system->stats['combined_heat_kwh'] : null);
        $combined_cop = isset($system->combined_cop) ? $system->combined_cop : (isset($system->stats['combined_cop']) ? $system->stats['combined_cop'] : null);
        $error_air_kwh = isset($system->error_air_kwh) ? $system->error_air_kwh : (isset($system->stats['error_air_kwh']) ? $system->stats['error_air_kwh'] : null);

        if ($error_air_kwh !== null && $error_air_kwh > 0) {
            if ($combined_elec_kwh !== null && $combined_elec_kwh > 0) {

                $electric_not_including_air_error = $combined_elec_kwh - $error_air_kwh;
                $cop_not_including_air_error = 0;
                if ($electric_not_including_air_error > 0) {
                    $cop_not_including_air_error = $combined_heat_kwh / $electric_not_including_air_error;
                }

                $difference = $combined_cop - $cop_not_including_air_error;
                if (abs($difference) > 0.2) {
                    $system->data_flag = 1;
                }
            }
        }
    }
}
