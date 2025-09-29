var immersion_kwh = 0;

function process_error_data() {

    var starting_power = parseFloat($("#starting_power").val());


    var total_error_time = 0;
    var min_error_time = 120;
    var total_error_elec_kwh = 0;

    var power_to_kwh = 1.0 * view.interval / 3600000.0;

    if (feeds["heatpump_error"] != undefined) {
        // Axioma heat meter error codes:
        // 1024: No flow
        // 67108864: < 3C delta T (ignore, this is often the case)
        // translate 1024  = 1, everything else = 0
        for (var z in data["heatpump_error"]) {
            let time = data["heatpump_error"][z][0];
            let error = data["heatpump_error"][z][1];
            if (error == 1024) {
                data["heatpump_error"][z] = [time, 1];
                total_error_time += view.interval;
                total_error_elec_kwh += data["heatpump_elec"][z][1] * power_to_kwh;
            }
            else data["heatpump_error"][z] = [time, 0];
        }

    } else {

        data["heatpump_error"] = [];
        // Heat meter error auto detection
        if (data["heatpump_elec"] != undefined && data["heatpump_heat"] != undefined && data["heatpump_flowT"] != undefined && data["heatpump_returnT"] != undefined) {

            console.log("auto detect heat meter errors");

            var error_state = 0;
            var error_time = 0;
            var error_kwh = 0;
            var error_start_index = 0;

            for (var z in data["heatpump_elec"]) {
                var time = data["heatpump_elec"][z][0];

                var elec = data["heatpump_elec"][z][1];
                var heat = data["heatpump_heat"][z][1];
                var flowT = data["heatpump_flowT"][z][1];
                var returnT = data["heatpump_returnT"][z][1];

                var DT = flowT - returnT;

                if (elec > starting_power && heat == 0 && DT > 1.5 && flowT > 30) {
                    if (error_time == 0) error_start_index = z;

                    error_state = 1;
                    error_time += view.interval;
                    total_error_time += view.interval;
                    
                    let kwh_inc = elec * power_to_kwh;
                    error_kwh += kwh_inc;
                    total_error_elec_kwh += kwh_inc;
                } else {
                    if (error_state == 1 && error_time <= 60) {
                        for (var y = error_start_index; y < z; y++) {
                            data["heatpump_error"][y][1] = 0;
                        }
                        total_error_time -= error_time;
                        total_error_elec_kwh -= error_kwh;
                    }
                    error_time = 0;
                    error_kwh = 0;
                    error_state = 0;
                }
                data["heatpump_error"].push([time, error_state]);
            }

            if (total_error_time > min_error_time) {
                powergraph_series['heatpump_error'] = { 
                    data: data["heatpump_error"],
                    label: "Error", 
                    yaxis: 4, 
                    color: "#F00", 
                    lines: { lineWidth: 0, show: true, fill: 0.15 } 
                };
            }
        }
    }

    var error_div = $("#data-error");
    if (total_error_time > min_error_time) {
        error_div.show();
        error_div.attr("title", "Heat meter air issue detected for " + (total_error_time / 60).toFixed(0) + " minutes ("+total_error_elec_kwh.toFixed(3)+" kWh)");
        $("#error-message").html("Heat meter air issue detected for " + (total_error_time / 60).toFixed(0) + " minutes, see: <a href='https://docs.openenergymonitor.org/heatpumps/removing_air.html'>Removing Air from Heating Systems</a>");
        $("#error-message").show();
    } else {
        error_div.hide();
        $("#error-message").hide();
    }
}

// if the heatpump_cooling flag doesn't exist, we can try to auto detect it
function auto_detect_cooling() {

    data["heatpump_cooling"] = [];

    // Enable cooling only if cooling kWh > heating kWh
    var heat_kwh = 0;
    var cool_kwh = 0;
    for (var z in data["heatpump_heat"]) {
        let heat = data["heatpump_heat"][z][1];
        if (heat != null && heat >= 0) {
            heat_kwh += heat * view.interval / HOUR;
        } else {
            cool_kwh += -1 * heat * view.interval / HOUR;
        }
    }
    // exit if heat is greater than cooling
    if (heat_kwh > cool_kwh*4) {
        show_cooling = false;
        $(".show_stats_category[key='cooling']").hide();
        return;
    }

    var miniumum_cooling_time = 300;
    // var miniumum_cooling_outsideT = 10;

    var cool_state = false;
    var cool_start_index = 0;
    var cool_time = 0;
    var total_cool_time = 0;
    var outsideT = 0;

    for (var z in data["heatpump_heat"]) {
        let time = data["heatpump_heat"][z][0];
        let heat = data["heatpump_heat"][z][1];
        
        // if (data["heatpump_outsideT"][z][1] != null) {
        //     outsideT = data["heatpump_outsideT"][z][1];
        // }

        if (heat != null && heat < 0) { //  && outsideT > miniumum_cooling_outsideT
            if (cool_time == 0) cool_start_index = z;
            cool_state = true;
            cool_time += view.interval;
            total_cool_time += view.interval;
        } else {
            if (cool_state && cool_time <= miniumum_cooling_time) {
                // Clear if too short
                for (var y = cool_start_index; y < z; y++) {
                    data["heatpump_cooling"][y][1] = 0;
                }
            }
            cool_time = 0;
            cool_state = false;
        }
        
        data["heatpump_cooling"].push([time, cool_state]);
    }

    // Add to powergraph series
    if (total_cool_time > 0) {
        powergraph_series['heatpump_cooling'] = {
            data: data["heatpump_cooling"],
            label: "Cooling",
            yaxis: 4,
            color: "#66b0ff",
            lines: { lineWidth: 0, show: true, fill: 0.15 }
        };

        show_cooling = true;
        $(".show_stats_category[key='cooling']").show();
    } else {
        show_cooling = false;
        $(".show_stats_category[key='cooling']").hide();
    }
}

function process_stats() {
    stats = {};
    stats.combined = {};
    stats.when_running = {};
    stats.space_heating = {};
    stats.water_heating = {};
    stats.cooling = {};

    var unit_dhw = config.app.heatpump_dhwT_unit.value || '°C';
    var name_dhw = (unit_dhw == '°C') ? "DHW Temperature" : "DHW Charge"; 
    var name_dhw_target = (unit_dhw == '°C') ? "DHW target Temperature" : "DHW Target Charge"; 


    var feed_options = {
        "heatpump_elec": { name: "HP electric consumption", unit: "W", dp: 0 },
        "heatpump_heat": { name: "HP heat output", unit: "W", dp: 0 },
        "immersion_elec": { name: "Immersion/backup", unit: "W", dp: 0 },
        "heatpump_heat_carnot": { name: "Simulated heat output", unit: "W", dp: 0 },
        "heatpump_flowT": { name: "Flow temperature", unit: "°C", dp: 1 },
        "heatpump_returnT": { name: "Return temperature", unit: "°C", dp: 1 },
        "heatpump_outsideT": { name: "Outside temperature", unit: "°C", dp: 1 },
        "heatpump_roomT": { name: "Room temperature", unit: "°C", dp: 1 },
        "heatpump_targetT": { name: "Target temperature", unit: "°C", dp: 1 },
        "heatpump_flowrate": { name: "Flow rate", unit: "", dp: 3 },
        "heatpump_dhwT": { name: name_dhw, unit: unit_dhw, dp: 1 },
        "heatpump_dhwTargetT": { name: name_dhw_target, unit: unit_dhw, dp: 1 }
    }

    var keys = [];
    for (var key in feed_options) {
        if (data[key] != undefined) {
            keys.push(key);
        }
    }

    for (var z in keys) {
        let key = keys[z];

        for (var x in stats) {
            stats[x][key] = {};
            stats[x][key].sum = 0;
            stats[x][key].count = 0;
            stats[x][key].mean = null;
            stats[x][key].kwh = null;
            stats[x][key].minval = null;
            stats[x][key].maxval = null;
        }
    }

    var starting_power = parseFloat($("#starting_power").val());

    var dhw_enable = false;
    if (data["heatpump_dhw"] != undefined) dhw_enable = true;

    var cooling_enable = false;
    if (data["heatpump_cooling"] != undefined) cooling_enable = true;

    for (var z in data["heatpump_elec"]) {
        let power = data["heatpump_elec"][z][1];

        let dhw = false;
        if (dhw_enable) dhw = data["heatpump_dhw"][z][1];

        let cool = false;
        if (cooling_enable && data["heatpump_cooling"][z] != undefined) cool = data["heatpump_cooling"][z][1];


        // let ch = false;
        // if (data["heatpump_ch"]!=undefined) ch = data["heatpump_ch"][z][1];

        for (var i in keys) {
            let key = keys[i];
            if (data[key][z] != undefined) {
                let value = data[key][z][1];
                if (value != null) {

                    if (cool && key == "heatpump_heat") {
                        // cooling is negative heat
                        // invert here so we can sum it with the heat
                        value = -1 * value;
                    }

                    stats.combined[key].sum += value;
                    stats.combined[key].count++;
                    stats_min_max('combined', key, value);

                    if (power != null && power >= starting_power) {
                        stats.when_running[key].sum += value;
                        stats.when_running[key].count++;
                        stats_min_max('when_running', key, value);

                        if (cool) {
                            stats.cooling[key].sum += value;
                            stats.cooling[key].count++;
                            stats_min_max('cooling', key, value);
                        } else {
                            if (dhw_enable) {
                                if (dhw) {
                                    stats.water_heating[key].sum += value;
                                    stats.water_heating[key].count++;
                                    stats_min_max('water_heating', key, value);
                                } else {
                                    stats.space_heating[key].sum += value;
                                    stats.space_heating[key].count++;
                                    stats_min_max('space_heating', key, value);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    for (var x in stats) {
        let out = "";

        for (var z in keys) {
            let key = keys[z];

            stats[x][key].mean = null;
            if (stats[x][key].count > 0) {
                stats[x][key].mean = stats[x][key].sum / stats[x][key].count;
            }

            stats[x][key].diff = null;
            if (stats[x][key].minval != null && stats[x][key].maxval != null) {
                stats[x][key].diff = stats[x][key].maxval - stats[x][key].minval;
            }

            if (stats[x][key].mean != null) {

                let name = feed_options[key].name;
                if (x == "cooling" && key == "heatpump_heat") name = "Cooling";

                out += "<tr>";
                out += "<td style='text-align:left;'>" + name + "</td>";

                var minval_str = "";
                if (stats[x][key].minval != null) minval_str = (1*stats[x][key].minval).toFixed(feed_options[key].dp) + " " + feed_options[key].unit;
                out += "<td style='text-align:center; color:#777'>" + minval_str + "</td>";

                var maxval_str = "";
                if (stats[x][key].maxval != null) maxval_str = (1*stats[x][key].maxval).toFixed(feed_options[key].dp) + " " + feed_options[key].unit;
                out += "<td style='text-align:center; color:#777'>" + maxval_str + "</td>";

                var diff_str = "";
                if (stats[x][key].diff != null) diff_str = (1*stats[x][key].diff).toFixed(feed_options[key].dp) + " " + feed_options[key].unit;
                out += "<td style='text-align:center; color:#777'>" + diff_str + "</td>";

                out += "<td style='text-align:center'>" + (1*stats[x][key].mean).toFixed(feed_options[key].dp) + " " + feed_options[key].unit + "</td>";

                if (feed_options[key].unit == "W") {
                    stats[x][key].kwh = (stats[x][key].mean * stats[x][key].count * view.interval) / 3600000;
                    out += "<td style='text-align:center'>" + (1*stats[x][key].kwh).toFixed(3) + " kWh</td>";
                } else {
                    out += "<td></td>";
                }
                out += "</tr>";
            }
        }

        $(".stats_category[key='" + x + "']").html(out);
    }
    
    // Standby energy
    var standby_kwh = 0;
    if (stats['when_running']['heatpump_elec'] != undefined && stats['when_running']['heatpump_elec'].kwh != null) {
        standby_kwh = stats['combined']['heatpump_elec'].kwh - stats['when_running']['heatpump_elec'].kwh;
    }
    $("#standby_kwh").html(standby_kwh.toFixed(3));

    return stats;
}

function stats_min_max(category, key, value) {

    if (stats[category][key].minval == null) {
        stats[category][key].minval = value;
    }
    if (value < stats[category][key].minval) {
        stats[category][key].minval = value;
    }
    if (stats[category][key].maxval == null) {
        stats[category][key].maxval = value;
    }
    if (value > stats[category][key].maxval) {
        stats[category][key].maxval = value;
    }
}

// Remove null values from feed data
function remove_null_values(data, interval) {
    var last_valid_pos = 0;
    for (var pos = 0; pos < data.length; pos++) {
        if (data[pos][1] != null) {
            let null_time = (pos - last_valid_pos) * interval;
            if (null_time < 900) {
                for (var x = last_valid_pos + 1; x < pos; x++) {
                    data[x][1] = data[last_valid_pos][1];
                }
            }
            last_valid_pos = pos;
        }
    }
    return data;
}

// This takes a slightly different approach to the stats calculation above
// for cop calculation we need to make sure that it's only based on periods
// where electric and heat data are simultaneously available.
// We check here to make sure both elec and heat are not null and then 
// sum the kwh values before finally populating the COP fields.
function calculate_window_cops() {
    cop_stats = {};
    cop_stats.combined = {};
    cop_stats.when_running = {};
    cop_stats.space_heating = {};
    cop_stats.water_heating = {};
    cop_stats.cooling = {};
    
    for (var category in cop_stats) {
        cop_stats[category].elec_kwh = 0;
        cop_stats[category].heat_kwh = 0;
    }
    
    if (data["heatpump_elec"] != undefined && data["heatpump_heat"] != undefined) {
    
        var starting_power = parseFloat($("#starting_power").val());
    
        var dhw_enable = false;
        if (data["heatpump_dhw"] != undefined) dhw_enable = true;

        var cooling_enable = false;
        if (data["heatpump_cooling"] != undefined) cooling_enable = true;
        
        var power_to_kwh = view.interval / 3600000;
    
        for (var z in data["heatpump_elec"]) {
            let elec = data["heatpump_elec"][z][1];
            let heat = data["heatpump_heat"][z][1];

            let dhw = false;
            let cool = false;

            if (dhw_enable) dhw = data["heatpump_dhw"][z][1];
            if (cooling_enable && data["heatpump_cooling"][z] != undefined) cool = data["heatpump_cooling"][z][1];
            
            if (elec != null && heat !=null) {

                if (cool) {
                    // cooling is negative heat
                    // invert here so we can sum it with the heat
                    heat = -1 * heat;
                }
            
                cop_stats.combined.elec_kwh += elec * power_to_kwh;
                cop_stats.combined.heat_kwh += heat * power_to_kwh;
            
                if (elec >= starting_power) {
                    cop_stats.when_running.elec_kwh += elec * power_to_kwh;
                    cop_stats.when_running.heat_kwh += heat * power_to_kwh;

                    if (cool) {
                        cop_stats.cooling.elec_kwh += elec * power_to_kwh;
                        cop_stats.cooling.heat_kwh += heat * power_to_kwh;
                    } else {
                        if (dhw_enable) {
                            if (dhw) {
                                cop_stats.water_heating.elec_kwh += elec * power_to_kwh;
                                cop_stats.water_heating.heat_kwh += heat * power_to_kwh;
                            } else {
                                cop_stats.space_heating.elec_kwh += elec * power_to_kwh;
                                cop_stats.space_heating.heat_kwh += heat * power_to_kwh;
                            }
                        }
                    }
                }
            }
        }
        
        for (var category in cop_stats) {
            if (cop_stats[category].elec_kwh>0) {

                // COP without immersion
                let cop = cop_stats[category].heat_kwh / cop_stats[category].elec_kwh
                $(".cop_"+category).html(cop.toFixed(2));

                let tooltip_text = "";
                let prefix = "";
                if (immersion_enabled) prefix = "HP ";

                tooltip_text = prefix + "Electric: " + cop_stats[category].elec_kwh.toFixed(1) + " kWh\n";
                tooltip_text += prefix + "Heat: " + cop_stats[category].heat_kwh.toFixed(1) + " kWh\n";
                

                if (immersion_enabled) {
                    // COP with immersion
                    if (category == "combined" || category == "water_heating") {
                        let cop_h4 = (cop_stats[category].heat_kwh + immersion_kwh) / (cop_stats[category].elec_kwh + immersion_kwh)
                        $(".cop_"+category).html(cop.toFixed(2) + " (" + cop_h4.toFixed(2) + ")");
                        tooltip_text += "Immersion: " + immersion_kwh.toFixed(1) + " kWh\n";         
                    }
                }
                
                $(".cop_"+category).attr("title", tooltip_text);    
            } else {
                $(".cop_"+category).html("---");
            }
        }
        
        $("#window-cop").html($(".cop_combined").html());
        $("#window-cop").attr("title", $(".cop_combined").attr("title"));
        
    } else {
        $(".cop_combined").html("---");
        $(".cop_when_running").html("---");
        $(".cop_water_heating").html("---");
        $(".cop_space_heating").html("---");
    }
}

function carnot_simulator() {
    var simulate_heat_output = $("#carnot_enable")[0].checked;
    var show_as_prc_of_carnot = $("#carnot_enable_prc")[0].checked;
    
    var starting_power = parseFloat($("#starting_power").val());

    data["heatpump_heat_carnot"] = [];
    data["sim_flow_rate"] = [];
    powergraph_series['carnot'] = [];
    powergraph_series['sim_flow_rate'] = [];

    if (simulate_heat_output && data["heatpump_elec"] != undefined && data["heatpump_flowT"] != undefined) {

        var condensing_offset = parseFloat($("#condensing_offset").val());
        var evaporator_offset = parseFloat($("#evaporator_offset").val());
        var heatpump_factor = parseFloat($("#heatpump_factor").val());
        var fixed_outside_temperature = parseFloat($("#fixed_outside_temperature").val());

        var heatpump_outsideT_available = false;
        if (data["heatpump_outsideT"] != undefined) {
            heatpump_outsideT_available = true;
        }

        // Carnot COP simulator
        var practical_carnot_heat_sum = 0;
        var ideal_carnot_heat_sum = 0;
        var carnot_heat_n = 0;
        var practical_carnot_heat_kwh = 0;
        var ideal_carnot_heat_kwh = 0;

        var ambientT = 0;

        var histogram = {};
        
        var heat_enabled = true;
        if (data["heatpump_heat"] == undefined) heat_enabled = false;

        for (var z in data["heatpump_elec"]) {

            let time = data["heatpump_elec"][z][0];
            let power = data["heatpump_elec"][z][1];

            let heat = null; 
            if (heat_enabled) {
                heat = data["heatpump_heat"][z][1];
            }
            let flowT = data["heatpump_flowT"][z][1];
            let returnT = data["heatpump_returnT"][z][1];

            if (power == null || (heat_enabled && heat == null) || flowT == null || returnT == null) {
                data["heatpump_heat_carnot"][z] = [time, null];
                data["sim_flow_rate"][z] = [time, null];
                continue;
            }

            if (heatpump_outsideT_available) {
                ambientT = data["heatpump_outsideT"][z][1];
            } else {
                ambientT = fixed_outside_temperature;
            }

            let carnot_COP = ((flowT + condensing_offset + 273) / ((flowT + condensing_offset + 273) - (ambientT + evaporator_offset + 273)));

            let practical_carnot_heat = null;
            let ideal_carnot_heat = null;
            let sim_flow_rate = null;

            if (power != null && carnot_COP != null) {
            
                if (power<starting_power) power = 0;
            
                practical_carnot_heat = power * carnot_COP * heatpump_factor;
                ideal_carnot_heat = power * carnot_COP;

                DT = flowT - returnT
                sim_flow_rate = (practical_carnot_heat / (DT * 4150)) * 3.6;
                if (DT < 1.0) {
                    sim_flow_rate = null
                }

                if (DT<-0.2) {
                    practical_carnot_heat *= -1;
                    ideal_carnot_heat *= -1;
                }

                practical_carnot_heat_sum += practical_carnot_heat;
                ideal_carnot_heat_sum += ideal_carnot_heat;
                carnot_heat_n++;

                practical_carnot_heat_kwh += practical_carnot_heat * view.interval / HOUR;
                ideal_carnot_heat_kwh += ideal_carnot_heat * view.interval / HOUR;

                if (heat != 0 && power != 0 && carnot_COP != 0) {
                    let COP = heat / power;
                    let practical_efficiency = COP / carnot_COP;
                    if (practical_efficiency >= 0 && practical_efficiency <= 1) {
                        let bucket = Math.round(1 * practical_efficiency * 200) / 200

                        if (histogram[bucket] == undefined) histogram[bucket] = 0
                        histogram[bucket] += heat * view.interval / HOUR;
                    }
                }
            }



            data["heatpump_heat_carnot"][z] = [time, practical_carnot_heat];
            data["sim_flow_rate"][z] = [time, sim_flow_rate];
        }

        var practical_carnot_heat_mean = practical_carnot_heat_sum / carnot_heat_n;
        var ideal_carnot_heat_mean = ideal_carnot_heat_sum / carnot_heat_n;
        if (simulate_heat_output && !show_as_prc_of_carnot) {
            powergraph_series['carnot'] = { label: "Carnot Heat", data: data["heatpump_heat_carnot"], yaxis: 1, color: 7, lines: { show: true, fill: 0.05, lineWidth: 0.8 } };
            // Uncomment to show simulated flow rate (experimental)
            // powergraph_series['sim_flow_rate'] = { label: "Simulated flow rate", data: data["sim_flow_rate"], yaxis: 3, color: "#000", lines: { show: true, fill: false, lineWidth: 1.0 } };
        }

        if (show_as_prc_of_carnot) {
            $("#histogram_bound").show();
            draw_histogram(histogram);
        } else {
            $("#histogram_bound").hide();
        }

        if (show_as_prc_of_carnot) {
            if (stats['combined']['heatpump_heat']!=undefined) {
                let prc_of_carnot = (100 * stats['combined']['heatpump_heat'].mean / ideal_carnot_heat_mean).toFixed(1);
                $("#window-carnot-cop").html("(<b>" + prc_of_carnot + "%</b> of Carnot)");
                $("#heatpump_factor").val(prc_of_carnot * 0.01);
            }
        } else {
            $("#window-carnot-cop").html("(Simulated: <b>" + (practical_carnot_heat_mean / stats['combined']['heatpump_elec'].mean).toFixed(2) + "</b>)");
        }
        $("#standby_cop_simulated").html(" (Simulated: " + (practical_carnot_heat_kwh / stats['when_running']['heatpump_elec'].kwh).toFixed(2) + ")");

    } else {
        $("#window-carnot-cop").html("");
    }
}

function process_defrosts() {

    if (data["heatpump_heat"] != undefined) {

        var total_positive_heat_kwh = 0;
        var total_cooling_kwh = 0;
        var total_defrost_and_loss_kwh = 0;
        
        var cooling_enable = false;
        if (data["heatpump_cooling"] != undefined) cooling_enable = true;

        for (var z in data["heatpump_heat"]) {
            let heat = data["heatpump_heat"][z][1];

            let cool = false;
            if (cooling_enable && data["heatpump_cooling"][z] != undefined) cool = data["heatpump_cooling"][z][1];

            if (heat != null) {
                if (heat >= 0) {
                    total_positive_heat_kwh += heat * view.interval / HOUR
                } else {
                    if (cool) {
                        total_cooling_kwh += -1 * heat * view.interval / HOUR
                    } else {
                        total_defrost_and_loss_kwh += -1 * heat * view.interval / HOUR
                    }
                }
            }
        }

        $("#total_positive_heat_kwh").html(total_positive_heat_kwh.toFixed(3));
        $("#total_defrost_and_loss_kwh").html(total_defrost_and_loss_kwh.toFixed(3));
        $("#prc_defrost_and_loss").html((100 * total_defrost_and_loss_kwh / (total_positive_heat_kwh+total_cooling_kwh)).toFixed(1));
        $("#total_net_heat_kwh").html((total_positive_heat_kwh - total_defrost_and_loss_kwh).toFixed(3));
    }
}

function compressor_starts() {

    var starting_power = parseFloat($("#starting_power").val());
    
    var state = null;
    var last_state = null;
    var starts = 0;
    var time_elapsed = 0;

    for (var z in data["heatpump_elec"]) {
        let elec = data["heatpump_elec"][z][1];
        
        if (elec !== null) {
        
            last_state = state;
            
            if (elec >= starting_power) {
                state = 1;
            } else {
                state = 0;
            }
            
            if (last_state===0 && state===1) {
                starts++;
            }
            
            time_elapsed += view.interval
        }
    }
        
    var hours = time_elapsed / 3600;
    
    var starts_per_hour = 0;
    if (hours>0) starts_per_hour = starts / hours;
    console.log("Starts: "+starts+", Starts per hour: "+starts_per_hour.toFixed(1)+", Hours: "+hours.toFixed(1));
}

// Calculate kWh immersion 
function process_aux() {
    if (data["immersion_elec"] != undefined) {
        var powert_to_kwh = view.interval / HOUR;
        immersion_kwh = 0;
        for (var z in data["immersion_elec"]) {
            let immersion_elec = data["immersion_elec"][z][1];
            if (immersion_elec != null) {
                immersion_kwh += immersion_elec * powert_to_kwh;
            }
        }
        $("#immersion_kwh").html(immersion_kwh.toFixed(3));
        console.log("Immersion: "+immersion_kwh.toFixed(3));
    }
}
