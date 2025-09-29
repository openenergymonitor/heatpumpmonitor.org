<?php

function process_weighted_averages($feed, $app, $start, $end) {

    $interval = 120;
    
    $flowT_data = $feed->get_data($app->config->heatpump_flowT,$start,$end,$interval,1,"UTC","notime");
    $returnT_data = $feed->get_data($app->config->heatpump_returnT,$start,$end,$interval,1,"UTC","notime");
    $outsideT_data = $feed->get_data($app->config->heatpump_outsideT,$start,$end,$interval,1,"UTC","notime");
    $heat_data = $feed->get_data($app->config->heatpump_heat,$start,$end,$interval,1,"UTC","notime");
    $elec_data = $feed->get_data($app->config->heatpump_elec,$start,$end,$interval,1,"UTC","notime");
    $power_to_kwh = $interval / 3600000;

    $npoints = count($flowT_data);

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

    if ($npoints<100) {
        return array(
            "success" => false,
            "message" => "Not enough data points"
        );
    }

    $flowT_data = remove_null_values($flowT_data, $interval);
    $returnT_data = remove_null_values($returnT_data, $interval);
    $outsideT_data = remove_null_values($outsideT_data, $interval);
    $heat_data = remove_null_values($heat_data, $interval);
    $elec_data = remove_null_values($elec_data, $interval);

    // Max carnot threshold detection
    $max_carnot_state = 0;
    $max_carnot_time = 0;
    $max_carnot_kwh = 0;
    $max_carnot_total_time = 0;
    $max_carnot_total_kwh = 0;

    // DHW error detection
    $dhw_error_state = 0;
    $dhw_error_time = 0;
    $dhw_error_kwh = 0;
    $dhw_total_error_time = 0;
    $dhw_total_error_kwh = 0;

    // COP below 1 detection
    $low_cop_error_state = 0;
    $low_cop_error_time = 0;
    $low_cop_error_kwh = 0;
    $low_cop_total_error_time = 0;
    $low_cop_total_error_kwh = 0;

    // COP above 10 detection
    $high_cop_error_state = 0;
    $high_cop_error_time = 0;
    $high_cop_error_kwh = 0;
    $high_cop_total_error_time = 0;
    $high_cop_total_error_kwh = 0;

    $negative_elec_kwh = 0;
    $negative_elec_count = 0;

    $cycle_state = 0;

    // print "- Start: $start, End: $end\n";
    $days = ($end - $start) / (24*3600);
    // print "- Days: $days\n";

    $date = new DateTime();
    $date->setTimezone(new DateTimeZone('Europe/London'));
    $date->setTimestamp($start);
    $hour = $date->format('H');

    $day_count = 0;
    $flowT_weighted_sum_daily = 0;
    $kwh_elec_daily = 0;
    $kwh_heat_daily = 0;
    $kwh_carnot_elec_daily = 0;
    $kwh_elec_running_daily = 0;
    $kwh_heat_running_daily = 0;


    $waft_daily = array();

    $daily_kwh_heat = array();
    $daily_kwh_elec = array();
    $daily_kwh_carnot_elec = array();
    $daily_kwh_elec_running = array();
    $daily_kwh_heat_running = array();

    $starting_power = 200;
    for ($i=0; $i<$npoints; $i++) {


        $time = $start + $i*$interval;
        $date->setTimestamp($time);

        $last_hour = $hour;
        $hour = $date->format('H');

        // is this a new day
        if ($hour<$last_hour) {
            if ($kwh_heat_daily>0) {
                $waft_daily[] = round($flowT_weighted_sum_daily/$kwh_heat_daily,2);

                $daily_kwh_heat[] = $kwh_heat_daily;
                $daily_kwh_elec[] = $kwh_elec_daily;
                $daily_kwh_carnot_elec[] = $kwh_carnot_elec_daily;
                $daily_kwh_elec_running[] = $kwh_elec_running_daily;
                $daily_kwh_heat_running[] = $kwh_heat_running_daily;
            }

            $flowT_weighted_sum_daily = 0;
            $kwh_elec_daily = 0;
            $kwh_heat_daily = 0;
            $kwh_carnot_elec_daily = 0;
            $kwh_elec_running_daily = 0;
            $kwh_heat_running_daily = 0;


            $day_count++;
        }
        
        // Get data
        $heat = $heat_data[$i];
        $elec = $elec_data[$i];
        $flowT = $flowT_data[$i];
        $returnT = $returnT_data[$i];
        $outsideT = $outsideT_data[$i];

        // continue if any are null
        if ($heat===null || $elec===null || $flowT===null || $returnT===null || $outsideT===null) continue;
        
        if ($elec<0) {
            $negative_elec_kwh += $elec * $power_to_kwh;
            $negative_elec_count++;
            $elec = 0;
        }

        if ($heat<0) $heat = 0;


        $dt = $flowT - $returnT;

        // Calculate ideal carnot efficiency
        $condensor = $flowT + 2 + 273.15;
        $evaporator = $outsideT - 6 + 273.15;
        $carnot_dt = $condensor - $evaporator;
        $ideal_carnot = 0;
        if ($carnot_dt>0) {
            $ideal_carnot = $condensor / $carnot_dt;
        }
    
        // Calculate COP
        $cop = 0;
        if ($elec>0) {
            $cop = $heat / $elec;
        }

        // Calculate % of carnot efficiency
        $prc_carnot = 0;
        if ($ideal_carnot>0) {
            $prc_carnot = $cop / $ideal_carnot;
        }

        // Measure kWh of heat above 70% carnot, with a run time of at least 120*8 = 16 minutes
        // Needs a sufficient minimum run time to avoid short fluctuations
        if ($prc_carnot>0.7) {
            $max_carnot_state = 1;
            $max_carnot_time += $interval;
            $max_carnot_kwh += $heat * $power_to_kwh;
            $max_carnot_total_time += $interval;
            $max_carnot_total_kwh += $heat * $power_to_kwh;
        } else {
            if ($max_carnot_state == 1 && $max_carnot_time <= 120*8) {
                $max_carnot_total_time -= $max_carnot_time;
                $max_carnot_total_kwh -= $max_carnot_kwh;
            }
            $max_carnot_state = 0;
            $max_carnot_time = 0;
            $max_carnot_kwh = 0;
        }

        // Measure kWh of elec when COP is below 1
        if ($heat>0 && $cop<1) {
            $low_cop_error_state = 1;
            $low_cop_error_time += $interval;
            $low_cop_error_kwh += $elec * $power_to_kwh;
            $low_cop_total_error_time += $interval;
            $low_cop_total_error_kwh += $elec * $power_to_kwh;
        } else {
            if ($low_cop_error_state == 1 && $low_cop_error_time <= 120*5) {
                $low_cop_total_error_time -= $low_cop_error_time;
                $low_cop_total_error_kwh -= $low_cop_error_kwh;
            }
            $low_cop_error_state = 0;
            $low_cop_error_time = 0;
            $low_cop_error_kwh = 0;
        }

        // Measure kWh of elec when COP is above 10
        if ($heat>0 && $cop>10) {
            $high_cop_error_state = 1;
            $high_cop_error_time += $interval;
            $high_cop_error_kwh += $elec * $power_to_kwh;
            $high_cop_total_error_time += $interval;
            $high_cop_total_error_kwh += $elec * $power_to_kwh;
        } else {
            if ($high_cop_error_state == 1 && $high_cop_error_time <= 120*5) {
                $high_cop_total_error_time -= $high_cop_error_time;
                $high_cop_total_error_kwh -= $high_cop_error_kwh;
            }
            $high_cop_error_state = 0;
            $high_cop_error_time = 0;
            $high_cop_error_kwh = 0;
        }

        // DHW error detection
        if ($elec > $starting_power && $heat == 0 && $dt > 1.5 && $flowT > 30) {
            $dhw_error_state = 1;            
            $dhw_error_time += $interval;
            $dhw_error_kwh += $elec * $power_to_kwh;
            $dhw_total_error_time += $interval;
            $dhw_total_error_kwh += $elec * $power_to_kwh;
        } else {
            if ($dhw_error_state == 1 && $dhw_error_time <= 120) {
                $dhw_total_error_time -= $dhw_error_time;
                $dhw_total_error_kwh -= $dhw_error_kwh;
            }
            $dhw_error_state = 0;
            $dhw_error_time = 0;
            $dhw_error_kwh = 0;
        } 

        // Calculate daily bins
        $flowT_weighted_sum_daily += $heat * $flowT * $power_to_kwh;

        // Calculate weighted averages
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
            $kwh_carnot_elec_daily += ($heat / $ideal_carnot) * $power_to_kwh;
            // Calculate actual elec consumption
            $kwh_elec_running += $elec * $power_to_kwh;
            $kwh_heat_running += $heat * $power_to_kwh;

            $kwh_elec_running_daily += $elec * $power_to_kwh;
            $kwh_heat_running_daily += $heat * $power_to_kwh;

            $time_on += $interval;
        } else {
            $cycle_state = 0;
        }

        // Calculate total kwh
        $kwh_heat += $heat * $power_to_kwh;
        $kwh_elec += $elec * $power_to_kwh;

        $kwh_heat_daily += $heat * $power_to_kwh;
        $kwh_elec_daily += $elec * $power_to_kwh;

        $time_total += $interval;
    }

    // Calculate weighted averages
    $waft = round($flowT_weighted_sum/$kwh_heat,2);
    $waot = round($outsideT_weighted_sum/$kwh_heat,2);
    $wafmo = round($flowT_minus_outsideT_weighted_sum/$kwh_heat,2);
    $wadt = round($dt_weighted_sum/$kwh_heat,2);
    $waelec = round($elec_weighted_sum/$kwh_elec,2);
    $waheat = round($heat_weighted_sum/$kwh_heat,2);
    $cop = $kwh_heat/$kwh_elec;

    // calculate % of time off
    $time_off = $time_total - $time_on;
    $time_off_prc = round(100 * $time_off / $time_total,2);

    $wa_prc_carnot = 0;
    if ($kwh_elec_running>0 && $kwh_carnot_elec>0) {
        $wa_prc_carnot = round(100 * ($kwh_heat_running / $kwh_elec_running) / ($kwh_heat_running / $kwh_carnot_elec),2);
    }
    
    $max_carnot_prc_of_heat = round(100 * $max_carnot_total_kwh / $kwh_heat);
    $low_cop_prc_of_elec = round(100 * $low_cop_total_error_kwh / $kwh_elec);
    $high_cop_prc_of_elec = round(100 * $high_cop_total_error_kwh / $kwh_elec);
    $dhw_error_prc_of_elec = round(100 * $dhw_total_error_kwh / $kwh_elec);

    // Calculate averages from daily data
    $waft_daily_avg = 0; //round(array_sum($waft_daily) / count($waft_daily),2);
    for ($i=0; $i<count($waft_daily); $i++) {
        $waft_daily_avg += $waft_daily[$i] * $daily_kwh_heat[$i];
    }
    $waft_daily_avg = round($waft_daily_avg / array_sum($daily_kwh_heat),2);

    $wa_prc_carnot_via_daily = 0;

    $kwh_elec_running2 = array_sum($daily_kwh_elec_running);
    $kwh_heat_running2 = array_sum($daily_kwh_heat_running);
    $kwh_carnot_elec2 = array_sum($daily_kwh_carnot_elec);

    if ($kwh_elec_running2>0 && $kwh_carnot_elec2>0) {
        $wa_prc_carnot_via_daily = round(100 * ($kwh_heat_running2 / $kwh_elec_running2) / ($kwh_heat_running2 / $kwh_carnot_elec2),2);
    }

    /*
    echo "- day count: $day_count\n";
    echo "- negative elec: $negative_elec_kwh, count: $negative_elec_count\n";
    echo "\n";

    echo "- WAFT: $waft ($waft_daily_avg)\n";
    echo "- WAOT: $waot\n";
    echo "- WAFMO: $wafmo\n";
    echo "- WADT: $wadt\n";
    echo "- WA elec: $waelec\n";
    echo "- WA heat: $waheat\n";
    echo "- kwh_heat: $kwh_heat\n";
    echo "- kwh_elec: $kwh_elec\n";
    echo "- cop: $cop\n";
    echo "- % carnot:  $wa_prc_carnot ($wa_prc_carnot_via_daily)\n";

    echo "- Time on: $time_on, Time off: $time_off, % off: $time_off_prc\n";
    echo "- Cycle count: $cycle_count\n";

    echo "- Max carnot time: ".$max_carnot_total_time."s, kwh: $max_carnot_total_kwh, % of heat: ".$max_carnot_prc_of_heat."%\n";
    echo "- Low cop time: ".$low_cop_total_error_time."s, kwh: $low_cop_total_error_kwh, % of elec: ".$low_cop_prc_of_elec."%\n";
    echo "- High cop time: ".$high_cop_total_error_time."s, kwh: $high_cop_total_error_kwh, % of elec: ".$high_cop_prc_of_elec."%\n";
    echo "- DHW error time: ".$dhw_total_error_time."s, kwh: $dhw_total_error_kwh, % of elec: ".$dhw_error_prc_of_elec."%\n";
    */

    return array(
        "start" => $start,
        "end" => $end,
        "days" => $days,
        "waft" => $waft,
        "waot" => $waot,
        "wafmo" => $wafmo,
        "wadt" => $wadt,
        "waelec" => $waelec,
        "waheat" => $waheat,
        "wa_prc_carnot" => $wa_prc_carnot,
        "max_carnot_prc_heat" => $max_carnot_prc_of_heat,
        "low_cop_prc_elec" => $low_cop_prc_of_elec,
        "high_cop_prc_elec" => $high_cop_prc_of_elec,
        "dhw_error_prc_elec" => $dhw_error_prc_of_elec,
        "prc_time_off" => $time_off_prc,
        "cycle_count" => $cycle_count
    );
}

// Remove null values from feed data
function remove_null_values2($data, $interval) {
    $last_valid_pos = 0;
    for ($pos = 0; $pos < count($data); $pos++) {
        if ($data[$pos] !== null) {
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
