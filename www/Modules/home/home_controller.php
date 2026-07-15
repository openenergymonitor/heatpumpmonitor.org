<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

// Histogram controller
function home_controller() {

    global $route, $session, $system_stats;



    // HTML view
    if ($route->action == "") {
        $route->format = "html";

        // Load home_page_stats.json from www
        $data = json_decode(file_get_contents("home_page_stats.json"), true);
        
        return view("Modules/home/home_view.php", array(
            "userid"=>$session['userid'],
            "stats"=>$data
        ));
    }

    // Find homes like this one
    // Returns all eligible systems with the home-description meta fields;
    // filtering to the user's home is done client-side on the home page.
    if ($route->action == "find_homes_like_this") {
        $route->format = "json";

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
                'measured_mean_flow_temp_coldest_day'
            ),
            'stats_fields' => array(
                'combined_elec_kwh',
                'combined_heat_kwh',
                'combined_cop',
                'combined_data_length',
                'error_air_kwh',
                'weighted_flowT',
                'weighted_flowT_minus_outsideT',
                'cooling_heat_kwh',
                'unit_rate_agile',
                'unit_rate_cosy',
                'unit_rate_go'
            ),
            "sort" => array(
                "field" => "combined_cop",
                "order" => "desc"
            )
        );

        $systems = $system_stats->combined_meta_stats_query($query);

        $output_systems = array();
        foreach ($systems as $system) {

            // Only include systems with a valid COP figure
            if ($system->combined_cop === null) continue;

            // combined_data_length in seconds converted to days
            $days = $system->combined_data_length / 86400;
            if ($days <= 330) continue;

            auto_flag_air_error($system);

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
                'measured_flow_temp' => $system->measured_mean_flow_temp_coldest_day !== null ? (float) $system->measured_mean_flow_temp_coldest_day : null,
                'weighted_flowT' => $system->weighted_flowT !== null ? round($system->weighted_flowT, 1) : null,
                'weighted_flowT_minus_outsideT' => $system->weighted_flowT_minus_outsideT !== null ? round($system->weighted_flowT_minus_outsideT, 1) : null,
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

    return false;
}

function auto_flag_air_error($system) {
    $combined_elec_kwh = isset($system->combined_elec_kwh) ? $system->combined_elec_kwh : (isset($system->stats['combined_elec_kwh']) ? $system->stats['combined_elec_kwh'] : null);
    $combined_heat_kwh = isset($system->combined_heat_kwh) ? $system->combined_heat_kwh : (isset($system->stats['combined_heat_kwh']) ? $system->stats['combined_heat_kwh'] : null);
    $combined_cop = isset($system->combined_cop) ? $system->combined_cop : (isset($system->stats['combined_cop']) ? $system->stats['combined_cop'] : null);
    $error_air_kwh = isset($system->error_air_kwh) ? $system->error_air_kwh : (isset($system->stats['error_air_kwh']) ? $system->stats['error_air_kwh'] : null);

    // Auto flag air errors if the air error changes the COP by more than 0.2
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