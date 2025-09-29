<?php

function process_error_data($data, $interval, $starting_power) {
    $total_error_time = 0;
    $min_error_time = 120;
    $total_error_elec_kwh = 0;

    $power_to_kwh = 1.0 * $interval / 3600000.0;

    if ($data["heatpump_error"]!=false) {
        // Axioma heat meter error codes:
        // 1024: No flow
        // 67108864: < 3C delta T (ignore, this is often the case)
        // translate 1024  = 1, everything else = 0
        foreach ($data["heatpump_error"] as $z => $error) {
            if ($error == 1024) {
                $data["heatpump_error"][$z] = 1;
                $total_error_time += $interval;
                $total_error_elec_kwh += $data["heatpump_elec"][$z] * $power_to_kwh;

            } else {
                $data["heatpump_error"][$z] = 0;
            }
        }
    } else {
        $data["heatpump_error"] = [];
        
        // Heat meter error auto detection
        if (isset($data["heatpump_elec"]) && isset($data["heatpump_heat"]) && isset($data["heatpump_flowT"]) && isset($data["heatpump_returnT"])) {
        
            if ($data["heatpump_heat"]==false || $data["heatpump_flowT"]==false || $data["heatpump_returnT"]==false) {
                return array("air" => 0, "air_kwh" => 0);
            }

            $error_state = 0;
            $error_time = 0;
            $error_kwh = 0;

            

            foreach ($data["heatpump_elec"] as $z => $elec) {
                $heat = $data["heatpump_heat"][$z];
                $flowT = $data["heatpump_flowT"][$z];
                $returnT = $data["heatpump_returnT"][$z];

                $DT = $flowT - $returnT;

                if ($elec > $starting_power && $heat == 0 && $DT > 1.5 && $flowT > 30) {
                    $error_state = 1;
                    
                    $error_time += $interval;
                    $total_error_time += $interval;

                    $kwh_inc = $elec * $power_to_kwh;
                    $error_kwh += $kwh_inc;
                    $total_error_elec_kwh += $kwh_inc;
                } else {
                    if ($error_state == 1 && $error_time <= 60) {
                        $total_error_time -= $error_time;
                        $total_error_elec_kwh -= $error_kwh;
                    }
                    $error_state = 0;
                    $error_time = 0;
                    $error_kwh = 0;
                }
            }
        }
    }

    if ($total_error_time < $min_error_time) {
        $total_error_time = 0;
        $total_error_elec_kwh = 0;
    }

    if ($total_error_time == 0) {
        $total_error_time = null;
        $total_error_elec_kwh = null;
    }

    return array("air" => $total_error_time, "air_kwh" => $total_error_elec_kwh);
}

// if the heatpump_cooling flag doesn't exist, we can try to auto detect it
function auto_detect_cooling($data, $interval) {

    if (!isset($data["heatpump_heat"])) {
        return false;
    }

    // Enable cooling only if cooling kWh > heating kWh
    $heat_kwh = 0;
    $cool_kwh = 0;
    foreach ($data["heatpump_heat"] as $z => $heat) {
        if ($heat !== null && $heat >= 0) {
            $heat_kwh += $heat * $interval / 3600;
        } else {
            $cool_kwh += -1 * $heat * $interval / 3600;
        }
    }
    // exit if heat is greater than cooling
    if ($heat_kwh > $cool_kwh*4) {
        return false;
    }

    $miniumum_cooling_time = 300;
    // $miniumum_cooling_outsideT = 10;
    $data_heatpump_cooling = [];

    $cool_state = false;
    $cool_start_index = 0;
    $cool_time = 0;
    // $total_cool_time = 0;

    foreach ($data["heatpump_heat"] as $z => $heat) {
        
        // if ($data["heatpump_outsideT"][$z][1] !== null) {
        //     $outsideT = $data["heatpump_outsideT"][$z][1];
        // }

        if ($heat !== null && $heat < 0) { //  && $outsideT > $miniumum_cooling_outsideT
            if ($cool_time == 0) $cool_start_index = $z;
            $cool_state = true;
            $cool_time += $interval;
            // $total_cool_time += $interval;
        } else {
            if ($cool_state && $cool_time <= $miniumum_cooling_time) {
                // Clear if too short
                for ($y = $cool_start_index; $y < $z; $y++) {
                    $data_heatpump_cooling[$y] = 0;
                }
            }
            $cool_time = 0;
            $cool_state = false;
        }
        
        $data_heatpump_cooling[] = $cool_state;
    }

    // returns the data object with the cooling flag added
    return $data_heatpump_cooling;
}

function get_heatpump_stats($feed,$app,$start,$end,$starting_power,$timezone = 'Europe/London') {

    // --------------------------------------------------------------------------------------------------------------    
    // Validate params
    // --------------------------------------------------------------------------------------------------------------
    if ($end===null || $start===null) {
        $date = new DateTime();
        $date->setTimezone(new DateTimeZone($timezone));
        $date->modify("midnight");
        $end = $date->getTimestamp();
        $date->modify("-30 day");
        $start = $date->getTimestamp();
    } else {
        $start = convert_time($start,$timezone);
        $end = convert_time($end,$timezone);
    }
    
    if ($end<=$start) return array('success'=>false, 'message'=>"Request end time before start time");

    $period = $end-$start;
    if ($period<=3600*24*7) {
        $interval = 10;
    } else if ($period<=3600*24*14) {
        $interval = 20;
    } else if ($period<=3600*24*21) {
        $interval = 30;
    } else if ($period<=3600*24*42) {
        $interval = 60;
    } else if ($period<=3600*24*90) {
        $interval = 120;
    } else {
        return array('success'=>false, 'message'=>"period to large");
    }
    
    if (!isset($app->config->heatpump_elec) || $app->config->heatpump_elec<1) return array('success'=>false, 'message'=>"Missing electricity consumption feed");
        
    // --------------------------------------------------------------------------------------------------------------    
    // Load data
    // --------------------------------------------------------------------------------------------------------------    
    $data = array();
    
    $feeds = array("heatpump_elec","heatpump_flowT","heatpump_returnT","heatpump_outsideT","heatpump_roomT","heatpump_heat","heatpump_dhw","heatpump_error","heatpump_cooling","immersion_elec");
    
    foreach ($feeds as $key) {
        $data[$key] = false;
        if (isset($app->config->$key) && $app->config->$key>0) {   
            $data[$key] = $feed->get_data($app->config->$key,$start,$end-$interval,$interval,1,$timezone,"notime");
            $data[$key] = remove_null_values($data[$key],$interval);
        }
    }

    $errors = process_error_data($data, $interval, $starting_power);

    if ($data["heatpump_cooling"]==false && isset($app->config->auto_detect_cooling) && $app->config->auto_detect_cooling) {
        $data["heatpump_cooling"] = auto_detect_cooling($data, $interval);
    }

    $immersion_kwh = process_aux($data, $interval);
    
    $cop_stats = calculate_window_cops($data, $interval, $starting_power);
    
    $stats = process_stats($data, $interval, $starting_power);
    
    foreach ($stats as $category => $val) {
        $cop_stats[$category]["elec_mean"] = $stats[$category]["elec"]["mean"];    
        $cop_stats[$category]["heat_mean"] = $stats[$category]["heat"]["mean"];    
        $cop_stats[$category]["flowT_mean"] = $stats[$category]["flowT"]["mean"];
        $cop_stats[$category]["returnT_mean"] = $stats[$category]["returnT"]["mean"];
        $cop_stats[$category]["outsideT_mean"] = $stats[$category]["outsideT"]["mean"];
        $cop_stats[$category]["roomT_mean"] = $stats[$category]["roomT"]["mean"]; 
    }
    
    $ideal_carnot_heat_mean = carnot_simulator($data, $starting_power);
    
    foreach ($stats as $category => $val) {
        $cop_stats[$category]["prc_carnot"] = null;
        if ($ideal_carnot_heat_mean!==false) {
            if ($ideal_carnot_heat_mean[$category]!==null && $ideal_carnot_heat_mean[$category]>0) {
                $cop_stats[$category]["prc_carnot"] = number_format(100 * $stats[$category]["heat"]["mean"] / $ideal_carnot_heat_mean[$category],3,'.','')*1;
            }
        }
    }
    
    $cop_stats["combined"]["cooling_kwh"] = process_defrosts($data,$interval);

    $cop_stats["weighted"] = process_weighted_average($data, $interval);

    $starts_result = compressor_starts($data, $interval, $starting_power);
    
    $cop_stats["combined"]["starts"] = $starts_result["starts"];
    $cop_stats["combined"]["starts_per_hour"] = number_format($starts_result["starts_per_hour"],2,'.','')*1;
    
    $elec_kwh = 0;
    if (isset($app->config->heatpump_elec_kwh)) {
        $elec_kwh = get_cumulative_kwh($feed,$app->config->heatpump_elec_kwh,$start,$end);
    }
    $heat_kwh = 0;
    if (isset($app->config->heatpump_heat_kwh)) {
        $heat_kwh = get_cumulative_kwh($feed,$app->config->heatpump_heat_kwh,$start,$end);
    }
    
    $cop = null;
    if ($elec_kwh>0) {
        $cop = $heat_kwh / $elec_kwh;
    }
    if ($elec_kwh!==null) $elec_kwh = number_format($elec_kwh,4,'.','')*1;
    if ($heat_kwh!==null) $heat_kwh = number_format($heat_kwh,4,'.','')*1;
    if ($cop!==null) $cop = number_format($cop,3,'.','')*1;
    
    $cop_stats["from_energy_feeds"] = array(
        "elec_kwh" => $elec_kwh,
        "heat_kwh" => $heat_kwh,
        "cop" => $cop
    );
    
    $result = [
      "start"=>(int)$start,
      "end"=>(int)$end,
      "interval"=>(int)$interval,
      "stats"=>$cop_stats,
      "quality"=>[
        "elec"=>get_quality($data["heatpump_elec"]),
        "heat"=>get_quality($data["heatpump_heat"]),
        "flowT"=>get_quality($data["heatpump_flowT"]),
        "returnT"=>get_quality($data["heatpump_returnT"]),
        "outsideT"=>get_quality($data["heatpump_outsideT"]),
        "roomT"=>get_quality($data["heatpump_roomT"])
      ],
      "errors" => $errors,
      "immersion_kwh" => $immersion_kwh
    ];
    
    return $result;
}

function process_stats($data, $interval, $starting_power) {
    $stats = [
        'combined' => [],
        'running' => [],
        'space' => [],
        'water' => [],
        'cooling' => []
    ];
    
    $feed_options = [
        "elec" => ["name" => "Electric consumption", "unit" => "W", "dp" => 1],
        "heat" => ["name" => "Heat output", "unit" => "W", "dp" => 1],
        //"heat_carnot" => ["name" => "Simulated heat output", "unit" => "W", "dp" => 0],
        "flowT" => ["name" => "Flow temperature", "unit" => "°C", "dp" => 2],
        "returnT" => ["name" => "Return temperature", "unit" => "°C", "dp" => 2],
        "outsideT" => ["name" => "Outside temperature", "unit" => "°C", "dp" => 2],
        "roomT" => ["name" => "Room temperature", "unit" => "°C", "dp" => 2],
        //"targetT" => ["name" => "Target temperature", "unit" => "°C", "dp" => 1],
        //"flowrate" => ["name" => "Flow rate", "unit" => "", "dp" => 3]
    ];

    foreach ($feed_options as $key => $props) {
        foreach ($stats as $x => $val) {
            $stats[$x][$key] = [
                'sum' => 0,
                'count' => 0,
                'mean' => null,
                'kwh' => null,
                //'minval' => null,
                //'maxval' => null
            ];
        }
    }

    $dhw_enable = false;
    if (isset($data["heatpump_dhw"]) && $data["heatpump_dhw"] != false) {
        $dhw_enable = true;
    }

    $cooling_enable = false;
    if (isset($data["heatpump_cooling"]) && $data["heatpump_cooling"] != false) {
        $cooling_enable = true;
    }

    for ($z = 0; $z < count($data["heatpump_elec"]); $z++) {
        $power = $data["heatpump_elec"][$z];

        $dhw = false;
        if ($dhw_enable) {
            if (isset($data["heatpump_dhw"][$z])) {
                $dhw = $data["heatpump_dhw"][$z];
            }
        }

        $cool = false;
        if ($cooling_enable && isset($data["heatpump_cooling"][$z])) {
            $cool = $data["heatpump_cooling"][$z];
        }

        foreach ($feed_options as $key => $props) {
            if (isset($data["heatpump_".$key][$z])) {
                $value = $data["heatpump_".$key][$z];
                if ($value !== null) {

                    if ($cool && $key == "heat") {
                        // cooling is negative heat
                        // invert here so we can sum it with the heat
                        $value = -1 * $value;
                    }

                    $stats['combined'][$key]['sum'] += $value;
                    $stats['combined'][$key]['count']++;
                    //stats_min_max($stats, 'combined', $key, $value);

                    if ($power !== null && $power >= $starting_power) {
                        $stats['running'][$key]['sum'] += $value;
                        $stats['running'][$key]['count']++;
                        //stats_min_max($stats, 'running', $key, $value);

                        if ($cool) {
                            $stats['cooling'][$key]['sum'] += $value;
                            $stats['cooling'][$key]['count']++;
                            //stats_min_max($stats, 'cooling', $key, $value);
                        } else {
                            if ($dhw_enable) {
                                if ($dhw) {
                                    $stats['water'][$key]['sum'] += $value;
                                    $stats['water'][$key]['count']++;
                                    //stats_min_max($stats, 'water', $key, $value);
                                } else {
                                    $stats['space'][$key]['sum'] += $value;
                                    $stats['space'][$key]['count']++;
                                    //stats_min_max($stats, 'space', $key, $value);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    foreach ($stats as $x => $val) {
        foreach ($feed_options as $key => $props) {

            $stats[$x][$key]["mean"] = null;
            if ($stats[$x][$key]["count"] > 0) {
                $stats[$x][$key]["mean"] = $stats[$x][$key]["sum"] / $stats[$x][$key]["count"];
            }
            /*
            $stats[$x][$key]["diff"] = null;
            if ($stats[$x][$key]["minval"] !== null && $stats[$x][$key]["maxval"] !== null) {
                $stats[$x][$key]["diff"] = $stats[$x][$key]["maxval"] - $stats[$x][$key]["minval"];
            }
            */
        }
    }
    
    foreach ($stats as $x => $val) {
        foreach ($feed_options as $key => $props) {
        
            if ($props["unit"] == "W" && $stats[$x][$key]["mean"] !== null) {
                $stats[$x][$key]["kwh"] = ($stats[$x][$key]["mean"] * $stats[$x][$key]["count"] * $interval) / 3600000;
                $stats[$x][$key]["kwh"] = number_format($stats[$x][$key]["kwh"],4,".","")*1;            
            } else {
                unset($stats[$x][$key]["kwh"]);
            }
        
            //if ($stats[$x][$key]["sum"]!==null) {
            //    $stats[$x][$key]["sum"] = number_format($stats[$x][$key]["sum"],$props["dp"],".","")*1;
            //}
            unset($stats[$x][$key]["sum"]);
            unset($stats[$x][$key]["count"]);
            
            if ($stats[$x][$key]["mean"]!==null) {
                $stats[$x][$key]["mean"] = number_format($stats[$x][$key]["mean"],$props["dp"],".","")*1;
            }
            /*
            if ($stats[$x][$key]["minval"]!==null) {
                $stats[$x][$key]["minval"] = number_format($stats[$x][$key]["minval"],$props["dp"],".","")*1;  
            }
            if ($stats[$x][$key]["maxval"]!==null) {
                $stats[$x][$key]["maxval"] = number_format($stats[$x][$key]["maxval"],$props["dp"],".","")*1;  
            }    
            if ($stats[$x][$key]["diff"]!==null) {
                $stats[$x][$key]["diff"] = number_format($stats[$x][$key]["diff"],$props["dp"],".","")*1;  
            }*/
             
        }
    }

    return $stats;
}

function stats_min_max(&$stats, $category, $key, $value) {
    // This function should update the min and max values in $stats array
    // Initialize min and max if they are null
    if ($stats[$category][$key]['minval'] === null || $value < $stats[$category][$key]['minval']) {
        $stats[$category][$key]['minval'] = $value;
    }
    if ($stats[$category][$key]['maxval'] === null || $value > $stats[$category][$key]['maxval']) {
        $stats[$category][$key]['maxval'] = $value;
    }
}


// Remove null values from feed data
function remove_null_values($data, $interval) {
    $last_valid_pos = 0;
    for ($pos = 0; $pos < count($data); $pos++) {
        if (isset($data[$pos]) && $data[$pos] !== null) {
            $null_time = ($pos - $last_valid_pos) * $interval;
            if ($null_time < 900) {
                for ($x = $last_valid_pos + 1; $x < $pos; $x++) {
                    $data[$x] = $data[$last_valid_pos];
                }
            }
            $last_valid_pos = $pos;
        }
    }
    return $data;
}

function get_quality($data) {
    if (!is_array($data)) return 0;
    $count = count($data);
    if ($count<1) return 0;

    if (!array_key_exists(0, $data)) return 0;
    
    $null_count = 0;
    for ($pos = 0; $pos < $count; $pos++) {
        if (is_null($data[$pos])) {
            $null_count ++;
        }
    }
    $quality = 100*(1-($null_count / $count));
    return number_format($quality,3,'.','')*1;
}

function calculate_window_cops($data, $interval, $starting_power) {
    $cop_stats = array(
        "combined" => array(),
        "running" => array(),
        "space" => array(),
        "water" => array(),
        "cooling" => array()
    );

    foreach ($cop_stats as $category => $value) {
        $cop_stats[$category]["elec_kwh"] = 0;
        $cop_stats[$category]["heat_kwh"] = 0;
        $cop_stats[$category]["data_length"] = 0;
        $cop_stats[$category]["cop"] = null;
    }

    if (!isset($data["heatpump_elec"]) || !isset($data["heatpump_heat"])) {
        return $cop_stats;
    }
    
    if (!is_array($data["heatpump_elec"])) return $cop_stats;
    if (!is_array($data["heatpump_heat"])) return $cop_stats;
    
    $dhw_enable = false;
    if (isset($data["heatpump_dhw"]) && $data["heatpump_dhw"] != false) {
        $dhw_enable = true;
    }

    $cooling_enable = false;
    if (isset($data["heatpump_cooling"]) && $data["heatpump_cooling"] != false) {
        $cooling_enable = true;
    }

    $power_to_kwh = 1.0 * $interval / 3600000.0;

    foreach ($data["heatpump_elec"] as $z => $elec_data) {
        $elec = $data["heatpump_elec"][$z];
        $heat = $data["heatpump_heat"][$z];

        $dhw = false;
        if ($dhw_enable) {
            if (isset($data["heatpump_dhw"][$z])) {
                $dhw = $data["heatpump_dhw"][$z];
            }
        }

        $cool = false;
        if ($cooling_enable && isset($data["heatpump_cooling"][$z])) {
            $cool = $data["heatpump_cooling"][$z];
        }

        if ($elec !== null && $heat !== null && $elec>=0) {

            if ($cool) {
                // cooling is negative heat
                // invert here so we can sum it with the heat
                $heat = -1 * $heat;
            }

            $cop_stats["combined"]["elec_kwh"] += $elec * $power_to_kwh;
            $cop_stats["combined"]["heat_kwh"] += $heat * $power_to_kwh;
            $cop_stats["combined"]["data_length"] += $interval;
            
            if ($elec >= $starting_power) {
                $cop_stats["running"]["elec_kwh"] += $elec * $power_to_kwh;
                $cop_stats["running"]["heat_kwh"] += $heat * $power_to_kwh;
                $cop_stats["running"]["data_length"] += $interval;

                if ($cool) {
                    $cop_stats["cooling"]["elec_kwh"] += $elec * $power_to_kwh;
                    $cop_stats["cooling"]["heat_kwh"] += $heat * $power_to_kwh;
                    $cop_stats["cooling"]["data_length"] += $interval;
                } else {
                    if ($dhw_enable) {
                        if ($dhw) {
                            $cop_stats["water"]["elec_kwh"] += $elec * $power_to_kwh;
                            $cop_stats["water"]["heat_kwh"] += $heat * $power_to_kwh;
                            $cop_stats["water"]["data_length"] += $interval;
                        } else {
                            $cop_stats["space"]["elec_kwh"] += $elec * $power_to_kwh;
                            $cop_stats["space"]["heat_kwh"] += $heat * $power_to_kwh;
                            $cop_stats["space"]["data_length"] += $interval;
                        }
                    }
                }
            }
        }
    }

    foreach ($cop_stats as $category => $stats) {
    
        $cop_stats[$category]["cop"] = 0;
        if ($cop_stats[$category]["elec_kwh"] > 0) {
            $cop_stats[$category]["cop"] = $cop_stats[$category]["heat_kwh"] / $cop_stats[$category]["elec_kwh"];
        }

        $cop_stats[$category]["elec_kwh"] = number_format($cop_stats[$category]["elec_kwh"],4,".","")*1;            
        $cop_stats[$category]["heat_kwh"] = number_format($cop_stats[$category]["heat_kwh"],4,".","")*1;            
        $cop_stats[$category]["cop"] = number_format($cop_stats[$category]["cop"],3,".","")*1;
        
        if ($cop_stats[$category]["data_length"] == 0) {
            $cop_stats[$category]["elec_kwh"] = null;
            $cop_stats[$category]["heat_kwh"] = null;
            $cop_stats[$category]["cop"] = null;
        }
    }
    return $cop_stats;
}

function carnot_simulator($data, $starting_power) {
    if (!isset($data["heatpump_elec"])) return false;
    if (!isset($data["heatpump_heat"])) return false;
    if (!isset($data["heatpump_flowT"])) return false;
    if (!isset($data["heatpump_returnT"])) return false;
    if (!isset($data["heatpump_outsideT"])) return false;

    if ($data["heatpump_elec"]==false) return false;
    if ($data["heatpump_heat"]==false) return false;
    if ($data["heatpump_flowT"]==false) return false;
    if ($data["heatpump_returnT"]==false) return false;
    if ($data["heatpump_outsideT"]==false) return false;
    
    $dhw_enable = false;
    if (isset($data["heatpump_dhw"]) && $data["heatpump_dhw"] != false) {
        $dhw_enable = true;
    }

    $condensing_offset = 2;
    $evaporator_offset = -6;
    
    $combined_ideal_carnot_heat_sum = 0;
    $combined_carnot_heat_n = 0;
    
    $running_ideal_carnot_heat_sum = 0;
    $running_carnot_heat_n = 0;
    
    $space_ideal_carnot_heat_sum = 0;
    $space_carnot_heat_n = 0;
    
    $water_ideal_carnot_heat_sum = 0;
    $water_carnot_heat_n = 0;  

    $ambientT = null;

    foreach ($data["heatpump_elec"] as $z => $value) {
        $elec = $data["heatpump_elec"][$z];
        $heat = $data["heatpump_heat"][$z];
        $flowT = $data["heatpump_flowT"][$z];
        $returnT = $data["heatpump_returnT"][$z];
        
        if (isset($data["heatpump_outsideT"][$z]) && $data["heatpump_outsideT"][$z] !== null) {
            $ambientT = $data["heatpump_outsideT"][$z];
        }

        // if any of the values are null, skip this iteration
        if ($elec === null || $heat === null || $flowT === null || $returnT === null || $ambientT === null) {
            continue;
        }
        
        $dhw = false;
        if ($dhw_enable) {
            if (isset($data["heatpump_dhw"][$z])) {
                $dhw = $data["heatpump_dhw"][$z];
            }
        }

        $a = $flowT + $condensing_offset + 273;
        $b = $a - ($ambientT + $evaporator_offset + 273);
        
        $carnot_COP = 0;
        if ($b!=0) {
            $carnot_COP = $a / $b;
        }
        
        if ($elec !== null && $carnot_COP !== null) {

            if ($elec<$starting_power) $elec = 0;

            $ideal_carnot_heat = $elec * $carnot_COP;

            $DT = $flowT - $returnT;

            if ($DT<-0.2) {
                $ideal_carnot_heat *= -1;
            }
        
            $combined_ideal_carnot_heat_sum += $ideal_carnot_heat;
            $combined_carnot_heat_n++;
        
            if ($elec >= $starting_power) {
                $running_ideal_carnot_heat_sum += $ideal_carnot_heat;
                $running_carnot_heat_n++;
                
                if ($dhw_enable) {
                    if ($dhw) {
                        $water_ideal_carnot_heat_sum += $ideal_carnot_heat;
                        $water_carnot_heat_n++;
                    } else {
                        $space_ideal_carnot_heat_sum += $ideal_carnot_heat;
                        $space_carnot_heat_n++;    
                    }
                }
            }
        }
    }
    
    $combined_ideal_carnot_heat_mean = null;
    if ($combined_carnot_heat_n>0) {
        $combined_ideal_carnot_heat_mean = round($combined_ideal_carnot_heat_sum / $combined_carnot_heat_n);
    }
    
    $running_ideal_carnot_heat_mean = null;
    if ($running_carnot_heat_n>0) {
        $running_ideal_carnot_heat_mean = round($running_ideal_carnot_heat_sum / $running_carnot_heat_n);
    }
    
    $space_ideal_carnot_heat_mean = null;
    if ($space_carnot_heat_n>0) {
        $space_ideal_carnot_heat_mean = round($space_ideal_carnot_heat_sum / $space_carnot_heat_n);
    }
    
    $water_ideal_carnot_heat_mean = null;
    if ($water_carnot_heat_n>0) {
        $water_ideal_carnot_heat_mean = round($water_ideal_carnot_heat_sum / $water_carnot_heat_n);
    }

    return array(
        "combined" => $combined_ideal_carnot_heat_mean,
        "running" => $running_ideal_carnot_heat_mean,
        "space" => $space_ideal_carnot_heat_mean,
        "water" => $water_ideal_carnot_heat_mean,
        "cooling" => null
    );
}

function process_defrosts($data, $interval) {

    $power_to_kwh = 1.0 * $interval / 3600000.0;
    
    $total_negative_heat_kwh = 0;
    if (isset($data["heatpump_heat"]) && is_array($data["heatpump_heat"])) {
        foreach ($data["heatpump_heat"] as $z => $value) {
            $heat = $data["heatpump_heat"][$z];
            if ($heat !== null && $heat < 0) {
                $total_negative_heat_kwh += -1 * $heat * $power_to_kwh;
            }
        }
    }
    return number_format($total_negative_heat_kwh,3,'.','')*1;
}

function compressor_starts($data, $interval, $starting_power) {
    
    $state = null;
    $last_state = null;
    $starts = 0;
    $time_elapsed = 0;
    
    foreach ($data["heatpump_elec"] as $z => $value) {
        $elec = $data["heatpump_elec"][$z];
            
        if ($elec !== null) {
            $last_state = $state;
            
            if ($elec >= $starting_power) {
                $state = 1;
            } else {
                $state = 0;
            }
            
            if ($last_state===0 && $state===1) {
                $starts++;
            }
            
            $time_elapsed += $interval;
        }
    }
        
    $hours = $time_elapsed / 3600;
    $starts_per_hour = 0;
    if ($hours>0) {
        $starts_per_hour = $starts / $hours;
    }
    
    return array(
        "starts" => $starts,
        "starts_per_hour"=>$starts_per_hour
    );
}

function get_cumulative_kwh($feed,$feedid,$start,$end) {
    
    $meta = $feed->get_meta($feedid);
    if ($meta->start_time>$start) {
        $start = $meta->start_time;
    }
    if ($meta->end_time<$end) {
        $end = $meta->end_time;
    }
    
    if ($end<$start) return false;
    
    
    $kwh_start = $feed->get_value($feedid,$start);
    $kwh_end = $feed->get_value($feedid,$end);

    return $kwh_end - $kwh_start;
}

function convert_time($time,$timezone) {
    // Option to specify times as date strings
    if (!is_numeric($time)) {
        $date = new DateTime();
        $date->setTimezone(new DateTimeZone($timezone));
        $date->modify($time);
        $date->modify('midnight');
        $time = $date->getTimestamp();
    }    
    
    // If timestamp is in milliseconds convert to seconds
    if (($time/1000000000)>100) {
        $time *= 0.001;
    }
    return $time;
}

// Calculate kWh immersion 
function process_aux($data, $interval) {
    $immersion_kwh = 0;
    if (isset($data["immersion_elec"]) && is_array($data["immersion_elec"])) {
        $power_to_kwh = 1.0 * $interval / 3600000.0;
        foreach ($data["immersion_elec"] as $elec) {
            // Only include positive values less than 20kW
            if ($elec !== null && $elec >= 0 && $elec < 20000) {
                $immersion_kwh += $elec * $power_to_kwh;
            }
        }
    }
    return number_format($immersion_kwh,3,'.','')*1;
}

// Weighted average calculation
function process_weighted_average($data, $interval) {
    if (!isset($data["heatpump_elec"])) return false;
    if (!isset($data["heatpump_heat"])) return false;
    if (!isset($data["heatpump_flowT"])) return false;
    if (!isset($data["heatpump_returnT"])) return false;
    if (!isset($data["heatpump_outsideT"])) return false;

    if ($data["heatpump_elec"]==false) return false;
    if ($data["heatpump_heat"]==false) return false;
    if ($data["heatpump_flowT"]==false) return false;
    if ($data["heatpump_returnT"]==false) return false;
    if ($data["heatpump_outsideT"]==false) return false;
    
    $flowT_weighted_sum = 0;
    $elec_weighted_sum = 0;
    $heat_weighted_sum = 0;
    $flowT_minus_outsideT_weighted_sum = 0;
    $outsideT_weighted_sum = 0;
    $dt_weighted_sum = 0;
    $kwh_heat = 0;
    $kwh_elec = 0;
    $kwh_carnot_elec = 0;

    $time_on = 0;
    $time_total = 0;
    $cycle_count = 0;

    $kwh_elec_running = 0;
    $kwh_heat_running = 0;
    
    $cycle_state = 0;

    $power_to_kwh = 1.0 * $interval / 3600000.0;

    $outsideT_available = false;
    if (isset($data["heatpump_outsideT"]) && is_array($data["heatpump_outsideT"])) {
        $outsideT_available = true;
    }

    foreach ($data["heatpump_elec"] as $z => $value) {
        $elec = $data["heatpump_elec"][$z];
        $heat = $data["heatpump_heat"][$z];
        $flowT = $data["heatpump_flowT"][$z];
        $returnT = $data["heatpump_returnT"][$z];
        $outsideT = null;

        if ($outsideT_available) {
            if (isset($data["heatpump_outsideT"][$z])) {
                $outsideT = $data["heatpump_outsideT"][$z];
            }
        }

        // if any of the values are null, skip this iteration
        if ($elec === null || $heat === null || $flowT === null || $returnT === null || $outsideT === null) {
            continue;
        }

        $dt = $flowT - $returnT;

        // Calculate ideal carnot efficiency
        $ideal_carnot = 0;
        if ($outsideT !== null) {
            $condensor = $flowT + 2 + 273.15;
            $evaporator = $outsideT - 6 + 273.15;
            $carnot_dt = $condensor - $evaporator;
            if ($carnot_dt>0) {
                $ideal_carnot = $condensor / $carnot_dt;
            }
        }
    
        // Calculate COP
        $cop = 0;
        if ($elec>0 && $heat>0) {
            $cop = $heat / $elec;
        }

        // Calculate % of carnot efficiency
        $prc_carnot = 0;
        if ($ideal_carnot>0) {
            $prc_carnot = $cop / $ideal_carnot;
        }

        $flowT_weighted_sum += $heat * $flowT * $power_to_kwh;
        $elec_weighted_sum += $elec * $elec * $power_to_kwh;
        $heat_weighted_sum += $heat * $heat * $power_to_kwh;
        $dt_weighted_sum += $heat * $dt * $power_to_kwh;
        $outsideT_weighted_sum += $heat * $outsideT * $power_to_kwh;
        $flowT_minus_outsideT_weighted_sum += $heat * ($flowT-$outsideT) * $power_to_kwh;

        if ($dt>1 && $heat>0 && $ideal_carnot>0) {
            if ($cycle_state == 0) {
                $cycle_count++;
            }
            $cycle_state = 1;

            // Calulate predicted elec consumption based on carnot efficiency
            $kwh_carnot_elec += ($heat / $ideal_carnot) * $power_to_kwh;
            // Calculate actual elec consumption
            $kwh_elec_running += $elec * $power_to_kwh;
            $kwh_heat_running += $heat * $power_to_kwh;

            $time_on += $interval;
        } else {
            $cycle_state = 0;
        }

        // Calculate total kwh
        $kwh_heat += $heat * $power_to_kwh;
        $kwh_elec += $elec * $power_to_kwh;

        $time_total += $interval;

    }

    $waft = null;
    $waot = null;
    $wafmo = null;
    $wadt = null;
    $waelec = null;
    $waheat = null;

    // Calculate weighted averages
    if ($kwh_heat>0) {
        $waft = $flowT_weighted_sum/$kwh_heat;
        $waot = $outsideT_weighted_sum/$kwh_heat;
        $wafmo = $flowT_minus_outsideT_weighted_sum/$kwh_heat;
        $wadt = $dt_weighted_sum/$kwh_heat;
        $waheat = $heat_weighted_sum/$kwh_heat;
    }

    $cop = null;
    if ($kwh_elec>0) {
        $waelec = $elec_weighted_sum/$kwh_elec;
        $cop = $kwh_heat/$kwh_elec;
    }


    $wa_prc_carnot = 0;
    if ($kwh_elec_running>0 && $kwh_carnot_elec>0) {
        $wa_prc_carnot = 100 * ($kwh_heat_running / $kwh_elec_running) / ($kwh_heat_running / $kwh_carnot_elec);
    }


    return array(
        "flowT" => $waft,
        "outsideT" => $waot,
        "flowT_minus_outsideT" => $wafmo,
        "flowT_minus_returnT" => $wadt,
        "elec" => $waelec,
        "heat" => $waheat,
        "prc_carnot" => $wa_prc_carnot,
        "kwh_elec" => $kwh_elec,
        "kwh_heat" => $kwh_heat,
        "cop" => $cop,
        "kwh_heat_running" => $kwh_heat_running,
        "kwh_elec_running" => $kwh_elec_running,
        "kwh_carnot_elec" => $kwh_carnot_elec,
        "time_on" => $time_on,
        "time_total" => $time_total,
        "cycle_count" => $cycle_count
    );
}

