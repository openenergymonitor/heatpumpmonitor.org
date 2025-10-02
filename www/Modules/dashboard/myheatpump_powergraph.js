var powergraph_series = [];

var inst_cop_min = 2;
var inst_cop_max = 6;
var inst_cop_mv_av_dp = 0;

var kw_at_50 = 0;
var kw_at_50_for_volume = 0;

var standby_dhw_hl_enable = false; // DHW standby loss

function powergraph_load() {
    var skipmissing = 0;
    var limitinterval = 0;

    view.calc_interval(1200);

    powergraph_series = {};


    // Change labels & target axis depending on DHW type
    const unit_dhw = config.app.heatpump_dhwT_unit.value || '°C';
    const dhw_type = (unit_dhw == '°C') ? "temp" : "charge"; 
    dhw_label = (dhw_type == 'temp') ? "DHW T" : "DHW Charge";
    dhw_target_label = (dhw_type == 'temp') ? "DHW TargetT" : "DHW Target Charge";
    dhw_axis = (dhw_type == 'temp') ? 2 : 7;

    // Index order is important here!
    
    var feeds_to_load = {
        "heatpump_dhw": { label: "DHW", yaxis: 4, color: "#88F", lines: { lineWidth: 0, show: true, fill: 0.15 } },
        "heatpump_ch": { label: "CH", yaxis: 4, color: "#FB6", lines: { lineWidth: 0, show: true, fill: 0.15 } },
        "heatpump_cooling": { label: "Cooling", yaxis: 4, color: "#66b0ff", lines: { lineWidth: 0, show: true, fill: 0.15 } },
        "heatpump_error": { label: "Error", yaxis: 4, color: "#F00", lines: { lineWidth: 0, show: true, fill: 0.15 } },
        "heatpump_targetT": { label: "TargetT", yaxis: 2, color: "#ccc" },
        "heatpump_flowT": { label: "FlowT", yaxis: 2, color: 2 },
        "heatpump_returnT": { label: "ReturnT", yaxis: 2, color: 3 },
        "heatpump_outsideT": { label: "OutsideT", yaxis: 2, color: "#c880ff" },
        "heatpump_roomT": { label: "RoomT", yaxis: 2, color: "#000" },
        "heatpump_flowrate": { label: "Flow rate", yaxis: 3, color: 6 },
        "heatpump_heat": { label: "Heat", yaxis: 1, color: 0, lines: { show: true, fill: 0.2, lineWidth: 0.5 } },
        "heatpump_elec": { label: "Electric", yaxis: 1, color: 1, lines: { show: true, fill: 0.3, lineWidth: 0.5 } },
        "immersion_elec": { label: "Immersion", yaxis: 1, color: 4, lines: { show: true, fill: 0.3, lineWidth: 0.5 } },
        "heatpump_dhwT": { label: dhw_label, yaxis: dhw_axis, color: "#0080ff" },
        "heatpump_dhwTargetT": { label: dhw_target_label, yaxis: dhw_axis, color:"#99cbfc" },
    }

    // limit to feeds that are available
    for (var key in feeds_to_load) {
        if (feeds[key] == undefined) {
            delete feeds_to_load[key];
        }
    }

    // If heatpump_cooling present 
    if (feeds["heatpump_cooling"] != undefined) {
        show_cooling = true;
        $(".show_stats_category[key='cooling']").show();
    }

    var average = 1;
    if (view.interval < 15) average = 0;

    var keys = Object.keys(feeds_to_load);
    var keys_string = keys.join(",");

    $.ajax({
        url: path + "timeseries/data.json",
        data: {
            id: config.id,
            feeds: keys_string,
            start: view.start,
            end: view.end,
            interval: view.interval,
            average: average,
            delta: 0,
            skipmissing: skipmissing,
            limitinterval: limitinterval,
            timeformat: "notime"
        },
        async: true,
        dataType: "json",
        success: function (all_data) {
            // Transfer from data to all_data by key
            for (var key in feeds_to_load) {
                all_data[key] = populate_timestamps(all_data[key], view.start, view.interval);
                // Data object used for calculations
                data[key] = remove_null_values(all_data[key], view.interval);

                // Load to powergraph_series (used for drawing the graph)
                let series = feeds_to_load[key];
                series.data = data[key];
                powergraph_series[key] = series;
            }

        if (feeds["heatpump_outsideT"] != undefined) {
            $("#fixed_outside_temperature_bound").hide();
        } else {
            $("#fixed_outside_temperature_bound").show();
        }

        // Process heatpump_targetT data
        // replace null values with the last known value
        var targetT = null;
        for (var z in data["heatpump_targetT"]) {
            if (data["heatpump_targetT"][z][1] != null) {
                targetT = data["heatpump_targetT"][z][1];
            } else {
                data["heatpump_targetT"][z][1] = targetT;
            }
        } 
        
        // Process heatpump_dhwTargetT data
        // replace null values with the last known value
        var targetT = null;
        for (var z in data["heatpump_dhwTargetT"]) {
            if (data["heatpump_dhwTargetT"][z][1] != null) {
                targetT = data["heatpump_dhwTargetT"][z][1];
            } else {
                data["heatpump_dhwTargetT"][z][1] = targetT;
            }
        }

        // Process axioma heat meter error data
        process_error_data();

        if (feeds["heatpump_cooling"] == undefined && config.app.auto_detect_cooling.value) {
            auto_detect_cooling();
        }

        powergraph_process();
    }});
}

function populate_timestamps(values, start, interval) {
    var intervalms = interval*1000;
    var time = Math.floor(start/intervalms)*intervalms;
    var with_time = [];
    for (var z in values) {
        with_time.push([time,values[z]]);
        time += intervalms;
    }
    return with_time;
} 

// Called from powergraph_load and when changing settings
// This function processes the data and loads it into powergraph_series
function powergraph_process() {
    // process_stats: calculates min, max, mean, total, etc
    process_stats();
    // process immersion
    process_aux();
    // Different approach for cop calculations
    calculate_window_cops();
    // carnor_simulator: calculates carnot heat output
    carnot_simulator();
    // process_inst_cop: calculates instantaneous COP
    process_inst_cop();
    // process_defrosts: calculates defrost energy
    process_defrosts();
    // calculates emitter and volume
    emitter_and_volume_calculator();
    // calculate starts
    compressor_starts();
    // calculate DHW standby heatloss
    calculate_standby_heat_loss();

    // Load powergraph_series into flot
    powergraph_draw();
}

function process_inst_cop() {

    var inst_cop_min = parseFloat($("#inst_cop_min").val());
    var inst_cop_max = parseFloat($("#inst_cop_max").val());

    powergraph_series['inst_cop'] = [];
    data["inst_COP"] = [];

    if (show_instant_cop) {
        if (data["heatpump_elec"] != undefined && data["heatpump_heat"] != undefined) {

            // foreach elec_without_null & heat_without_null find the COP 3 point average

            var np = inst_cop_mv_av_dp;

            for (var z = np; z < data["heatpump_elec"].length - np; z++) {
                var time = data["heatpump_elec"][z][0];

                // Extract values only once
                var elec_values = data["heatpump_elec"].slice(z - np, z + np + 1).map(entry => entry[1]);
                var heat_values = data["heatpump_heat"].slice(z - np, z + np + 1).map(entry => entry[1]);

                // Check for null values
                if (!elec_values.includes(null) && !heat_values.includes(null)) {
                    // Calculate sum directly
                    var elec_sum_inst = elec_values.reduce((sum, value) => sum + value, 0);
                    var heat_sum_inst = heat_values.reduce((sum, value) => sum + value, 0);

                    // Avoid division by zero
                    var cop = elec_sum_inst !== 0 ? heat_sum_inst / elec_sum_inst : null;
                    data["inst_COP"][z] = [time, cop];
                }
            }

            // filter out inst_COP values outside of range
            for (var z in data["inst_COP"]) {
                let inst_COP = data["inst_COP"][z][1];
                if (inst_COP > inst_cop_max) inst_COP = null;
                else if (inst_COP < inst_cop_min) inst_COP = null;
                data["inst_COP"][z][1] = inst_COP;
            }

            powergraph_series['inst_cop'] = { label: "Inst COP", data: data["inst_COP"], yaxis: 3, color: "#44b3e2", lines: { show: true, lineWidth: 2 } };
        }
    }
}

function emitter_and_volume_calculator() {
    $("#system_volume").html("?");
    $("#kW_at_50").html("?");

    if (stats['combined']["heatpump_heat"] == undefined) return false;
    if (stats['combined']["heatpump_heat"].mean == null) return false;

    $("#emitter-spec-volume").hide();


    if (!emitter_spec_enable) return false;

    var fixed_emitter_spec = $("#fix_kW_at_50")[0].checked;
    if (fixed_emitter_spec) {
        fixed_emitter_spec = parseFloat($("#kW_at_50").val());
    }

    var starting_power = parseFloat($("#starting_power").val());

    // if stats roomT mean
    var roomT_enable = false;
    var manual_roomT = 20;
    if (data["heatpump_roomT"] != undefined) {
        roomT_enable = true;
        if (stats["space_heating"] != undefined && stats["space_heating"]["heatpump_roomT"] != undefined && stats["space_heating"]["heatpump_roomT"].mean != undefined) {
            if (stats["space_heating"]["heatpump_roomT"].mean != null) {
                manual_roomT = stats["space_heating"]["heatpump_roomT"].mean.toFixed(1);
            }
        } else {
            if (stats['when_running']["heatpump_roomT"].mean != null) {
                manual_roomT = stats['when_running']["heatpump_roomT"].mean.toFixed(1);
            }
        }
    } else {
        $("#manual_roomT_enable")[0].checked = true;
    }

    var manual_roomT_enable = $("#manual_roomT_enable")[0].checked;
    if (!manual_roomT_enable) {
        $("#room_temperature").val(manual_roomT);
    } else {
        manual_roomT = parseFloat($("#room_temperature").val());
    }

    var dhw_enable = false;
    if (data["heatpump_dhw"] != undefined) dhw_enable = true;

    // Plot instantaneous emitter spec
    data["emitter_spec"] = [];

    // holds value & frequency
    let emitter_spec_histogram = {};
    
    var roomT = null;

    let kw_at_50_sum = 0;
    let kw_at_50_count = 0;

    for (var z in data["heatpump_flowT"]) {

        let dhw = false;
        if (dhw_enable) dhw = data["heatpump_dhw"][z][1];
        
        if (!manual_roomT_enable) {
            if (data["heatpump_roomT"][z][1]!=null) {
                roomT = data["heatpump_roomT"][z][1];
            }
        } else {
            roomT = manual_roomT;
        }

        let kw_at_50 = null;
        if (!dhw) {
            let flowT = data["heatpump_flowT"][z][1];
            let returnT = data["heatpump_returnT"][z][1];
            let DT = flowT - returnT;

            if (DT > 1.0) {
                let MWT = (flowT + returnT) * 0.5;
                let MWT_minus_room = MWT - roomT;
                let heat = data["heatpump_heat"][z][1];
                kw_at_50 = 0.001 * heat / Math.pow(MWT_minus_room / 50, 1.3);

                // Add to histogram
                if (kw_at_50 > 0 && kw_at_50 !=null) {
                    let rounded = kw_at_50.toFixed(1);
                    if (emitter_spec_histogram[rounded] == undefined) emitter_spec_histogram[rounded] = 0;
                    emitter_spec_histogram[rounded]++;

                    kw_at_50_sum += kw_at_50;
                    kw_at_50_count++;
                }
            }
        }
        if (kw_at_50 <= 0) kw_at_50 = null;

        let time = data["heatpump_flowT"][z][0];
        data["emitter_spec"].push([time, kw_at_50]);
    }

    let kw_at_50_mean = null;
    if (kw_at_50_count > 0) kw_at_50_mean = kw_at_50_sum / kw_at_50_count;

    // find the most common value
    let max = 0;
    let max_key = 0;
    for (var key in emitter_spec_histogram) {
        if (emitter_spec_histogram[key] > max) {
            max = emitter_spec_histogram[key];
            max_key = key;
        }
    }
    let radiator_spec = parseFloat(max_key);

    if (fixed_emitter_spec) radiator_spec = fixed_emitter_spec;

    // Create plot of heat output from radiators based on most common emitter spec
    data["emitter_spec_heat"] = [];
    data["system_volume"] = [];

    data["MWT"] = [];



    let kwh_to_volume = 0;
    let last_MWT = null;
    let last_DT = null;
    let MWT_increase = 0;
    let system_volume = null;
    roomT = null;

    let volumes = [];

    for (var z in data["heatpump_flowT"]) {

        let power = data["heatpump_elec"][z][1];
        let heat_from_heatpump = data["heatpump_heat"][z][1];

        let dhw = false;
        if (dhw_enable) dhw = data["heatpump_dhw"][z][1];
        
        let heat_from_rads = null;
        system_volume = null;

        if (heat_from_heatpump <= 0) {

            if (kwh_to_volume != 0) {
                volumes.push({
                    kwh: kwh_to_volume,
                    MWT_inc: MWT_increase,
                    volume: (kwh_to_volume * 3600000) / (4150 * MWT_increase)
                });
            }

            kwh_to_volume = 0;
            MWT_increase = 0;
            last_MWT = null;
        }
        
        if (power != null && power >= starting_power && !dhw) {

            let flowT = data["heatpump_flowT"][z][1];
            let returnT = data["heatpump_returnT"][z][1];
            let DT = flowT - returnT;
            let MWT = (flowT + returnT) * 0.5;
            
            if (!manual_roomT_enable) {
                if (data["heatpump_roomT"][z][1]!=null) {
                    roomT = data["heatpump_roomT"][z][1];
                }
            } else {
                roomT = manual_roomT;
            }

            let MWT_minus_room = MWT - roomT;
            heat_from_rads = 1000 * Math.pow(MWT_minus_room / 50, 1.3) * radiator_spec;

            if (heat_from_rads != null) {
                // Calculate volume
                let heat_to_volume =  heat_from_heatpump - heat_from_rads

                if (last_MWT != null) {
                    let MWT_change = MWT - last_MWT;
                        kwh_to_volume += heat_to_volume * (view.interval / 3600000);
                        MWT_increase += MWT_change
                        if (MWT_increase < 0) {
                            MWT_increase = 0;
                            kwh_to_volume = 0;
                        }
                }

                if (kwh_to_volume>0.1) {
                    system_volume = (kwh_to_volume * 3600000) / (4150 * MWT_increase);
                }
            }
            last_MWT = MWT;
        }
        let time = data["heatpump_flowT"][z][0];
        data["emitter_spec_heat"].push([time, heat_from_rads]);
        data["system_volume"].push([time, system_volume]);

        // filter out emitter_spec values outside of 20% of radiator spec
        if (data["emitter_spec"][z][1] != null) {
            if (data["emitter_spec"][z][1] > radiator_spec * 1.2) data["emitter_spec"][z][1] = null;
            if (data["emitter_spec"][z][1] < radiator_spec * 0.8) data["emitter_spec"][z][1] = null;
        }
    }

    if (kwh_to_volume != 0) {
        volumes.push({
            kwh: kwh_to_volume,
            MWT_inc: MWT_increase,
            volume: (kwh_to_volume * 3600000) / (4150 * MWT_increase)
        });
    }


    let total_kwh_to_volume = 0;
    let total_MWT_increase = 0;

    console.log("Volumes:");
    for (var z in volumes) {
        total_kwh_to_volume += volumes[z].kwh;
        total_MWT_increase += volumes[z].MWT_inc;
        console.log("kwh: " + volumes[z].kwh.toFixed(2) + " MWT: " + volumes[z].MWT_inc.toFixed(2) + " volume: " + volumes[z].volume.toFixed(0));
    }

    // Calculate system volume
    system_volume = (total_kwh_to_volume * 3600000) / (4150 * total_MWT_increase);

    console.log("Most common emitter spec: " + radiator_spec + " kW");
    // if kw_at_50_mean numeric convert to 1 decimal place
    if (kw_at_50_mean != null) kw_at_50_mean = kw_at_50_mean.toFixed(1);
    console.log("Mean emitter spec: " + kw_at_50_mean + " kW");

    console.log("MWT increase: " + total_MWT_increase.toFixed(1) + "K");
    console.log("Heat to system volume: " + total_kwh_to_volume.toFixed(1) + " kWh");
    console.log("System volume: " + system_volume.toFixed(0) + " litres");

    $("#kW_at_50").val(radiator_spec);
    
    if (system_volume>0) {
        $("#system_volume").val(system_volume.toFixed(0));
    } else {
        $("#system_volume").val("?");
    }

    $("#emitter-spec-volume").show();
    $("#emitter-spec-volume").html("("+radiator_spec + " kW, "+system_volume.toFixed(0) + " L)");


    // Enable for development
    powergraph_series['emitter_spec_heat'] = {
        label: "Emitter spec heat",
        data: data["emitter_spec_heat"],
        yaxis: 1,
        //color orange
        color: "#ff7f0e",
        lines: { show: true, fill: 0.2, lineWidth: 0.5 }
    };

    powergraph_series['emitter_spec'] = { 
        label: "Emitter spec", 
        data: data["emitter_spec"], 
        yaxis: 6, 
        color: "#888", 
        lines: { show: true, lineWidth: 2 } 
    };

    powergraph_series['system_volume'] = {
        label: "System volume",
        data: data["system_volume"],
        yaxis: 7,
        // dark blue
        color: "#1f77b4",
        lines: { show: true, lineWidth: 2 }
    };
/*
    powergraph_series['MWT'] = {
        label: "MWT",
        data: data["MWT"],
        yaxis: 2,
        // dark blue
        color: "#1f77b4",
        lines: { show: true, lineWidth: 2 }
    };
  */  
}

/**
 * Calculates the standby heat loss coefficient (U) for the DHW cylinder.
 */
function calculate_standby_heat_loss() {
    const heatlossDisplay = $("#standby_dhw_hl_result");
    const halflifeDisplay = $("#standby_dhw_t_half_result");
    heatlossDisplay.html("---"); // Reset result
    halflifeDisplay.html("---"); // Reset result

    // select whether we have temperature or charge type data
    const unit_dhw = config.app.heatpump_dhwT_unit.value || '°C';
    const dhw_type = (unit_dhw == '°C') ? "temp" : "charge"; 

    // Ensure any previous fit line is removed if calculation is disabled or fails
    delete powergraph_series['dhwT_fitted'];
    data["dhwT_fitted"] = [];

    if (!standby_dhw_hl_enable) {
        return; // Calculation not enabled
    }

    if (data["heatpump_dhwT"] == undefined || data["heatpump_dhwT"].length < 2) {
        heatlossDisplay.html("<span style='color:orange;'>Requires DHW Temp Feed Data</span>");
        return;
    }

    const V_cyl_str = $("#cylinder_volume").val();
    const T_env_str = $("#env_temperature").val();

    const V_cyl = parseFloat(V_cyl_str);
    // set environmental temperature to 0 for charge type,
    // otherwise parse the input string
    // If using cylinder charge, we just estimate the decay to 0%, not the decay to
    // environmental temperature
    const T_env = (dhw_type =='temp') ? parseFloat(T_env_str) : 0.0;

    if (isNaN(V_cyl) || V_cyl <= 0 || isNaN(T_env)) {
        heatlossDisplay.html("<span style='color:red;'>Invalid Inputs</span>");
        return;
    }

    heatlossDisplay.html("Calculating...");

    const rho_water = 1.0; // kg/L
    const cp_water = 4186; // J/(kg*K)

    let times_s = []; // Relative time in seconds
    let ln_deltaT_norm = []; // ln(deltaT / deltaT_0)
    let deltaT_0 = null;
    let start_time_ms = null;

    // Prepare data for regression
    for (let i = 0; i < data["heatpump_dhwT"].length; i++) {
        const time_ms = data["heatpump_dhwT"][i][0];
        const T_cyl = data["heatpump_dhwT"][i][1];

        if (T_cyl === null) continue; // Skip null temperature values

        const deltaT = T_cyl - T_env;

        if (deltaT <= 0) continue; // Skip non-positive temperature differences

        // Establish the baseline deltaT and start time for the log normalization
        if (deltaT_0 === null) {
            deltaT_0 = deltaT;
            start_time_ms = time_ms;
        }

        // Only add points after the start time is established
        if (start_time_ms !== null) {
            const relative_time_s = (time_ms - start_time_ms) / 1000.0;
            const ln_dt_norm_val = Math.log(deltaT / deltaT_0);

            // Avoid issues with log(0) or log(negative) if deltaT somehow becomes <=0 after check
             if (!isNaN(ln_dt_norm_val) && isFinite(ln_dt_norm_val)) {
                times_s.push(relative_time_s);
                ln_deltaT_norm.push(ln_dt_norm_val);
            }
        }
    }

    if (times_s.length < 5) { // Require a minimum number of points for a meaningful fit
        heatlossDisplay.html("<span style='color:orange;'>Insufficient Data Points in Window</span>");
        return;
    }

    // Perform linear regression: ln(deltaT/deltaT0) = -k * t
    const regressionResult = linearRegression(times_s, ln_deltaT_norm);

    if (!regressionResult) {
        heatlossDisplay.html("<span style='color:red;'>Regression Failed</span>");
        return;
    }

    const slope = regressionResult.slope; // This is -k
    const decay_constant_k = -slope; // k should be positive for decay

    if (isNaN(decay_constant_k) || decay_constant_k <= 0) {
         heatlossDisplay.html("<span style='color:orange;'>Non-decaying Fit</span>");
         return;
    }

    // Generate fitted data and add series ---
    // Check if we have valid parameters to generate the fitted curve
    if (deltaT_0 !== null && start_time_ms !== null && !isNaN(T_env)) {
        data["dhwT_fitted"] = []; // Initialize array

        // Use the original data timestamps for the fitted curve x-axis
        for (let i = 0; i < data["heatpump_dhwT"].length; i++) {
            const time_ms = data["heatpump_dhwT"][i][0];
            let fitted_temp = null;

            // Only calculate fitted points from the start of the regression data onwards
            if (time_ms >= start_time_ms) {
                const relative_time_s = (time_ms - start_time_ms) / 1000.0;
                // Calculate predicted temperature using the model: T(t) = T_env + (T(0) - T_env) * exp(-k*t)
                // Using deltaT_0 which is T(0) - T_env
                const deltaT_predicted = deltaT_0 * Math.exp(-decay_constant_k * relative_time_s);
                fitted_temp = T_env + deltaT_predicted;
            }
            data["dhwT_fitted"].push([time_ms, fitted_temp]);
        }

        // Add the generated data to powergraph_series for plotting
        powergraph_series['dhwT_fitted'] = {
            label: (dhw_type == "temp") ? "DHW T (Fitted)":"DHW Charge (Fitted)", // Label for the legend
            data: data["dhwT_fitted"],
            yaxis: (dhw_type == "temp") ? 2:7, // Plot on the temperature or charge axis (2 or 7)
            color: "#ff9900", // A distinct color (e.g., orange)
            lines: { show: true, lineWidth: 1 } // Style: thinner line than actual data
            // Optional: use dashes for clearer distinction:
            // lines: { show: true, lineWidth: 1, dashes: [5, 5] }
        };
    }

    // Calculate Heat Loss Coefficient U = V_cyl * rho * cp * k
    const U_WK = V_cyl * rho_water * cp_water * decay_constant_k;

    // calculate half life
    const half_life_s = Math.log(2) / decay_constant_k;
    const half_life_h = half_life_s / 3600;
    const half_life_d = half_life_h / 24;

    // --- Detailed Logging ---
    console.groupCollapsed("Standby Heat Loss Calculation Details"); // Use groupCollapsed to keep console cleaner initially
    try { // Use a try...finally to ensure groupEnd is always called
        console.log(`Timestamp: ${new Date().toISOString()}`);
        console.log(`Window: ${new Date(view.start).toLocaleString()} - ${new Date(view.end).toLocaleString()}`);
        console.log(`Inputs: V_cyl=${V_cyl} L, T_env=${T_env} °C`);
        console.log(`Data Points Used for Fit: ${times_s.length}`);
        if (times_s.length > 0) {
                console.log(`Fit Duration: ${times_s[times_s.length - 1].toFixed(1)} s (${ (times_s[times_s.length - 1]/3600).toFixed(2) } hours)`);
                console.log(`Initial Temp: ${ (deltaT_0 + T_env).toFixed(2) } °C (DeltaT₀ = ${deltaT_0.toFixed(2)} K)`);
                // Find the last temperature used in the fit
                const last_ln_deltaT_norm = ln_deltaT_norm[ln_deltaT_norm.length - 1];
                const last_deltaT = deltaT_0 * Math.exp(last_ln_deltaT_norm);
                const last_temp = last_deltaT + T_env;
                console.log(`Final Temp Used: ${last_temp.toFixed(2)} °C (DeltaT = ${last_deltaT.toFixed(2)} K)`);
        } else {
                console.log("Fit Duration: N/A (No points)");
                console.log("Initial Temp: N/A");
                console.log("Final Temp Used: N/A");
        }

        console.log("--- Linear Regression Fit ---");
        console.log("Model: ln(ΔT(t)/ΔT₀) = slope * t + intercept");
        console.log(`Raw Slope (m): ${regressionResult.slope.toExponential(5)} (1/s)`);
        console.log(`Raw Intercept (b): ${regressionResult.intercept.toFixed(5)}`);
        // Include R-squared if you calculated it in linearRegression function
        console.log(`R² (Goodness of Fit): ${regressionResult.r2 ? regressionResult.r2.toFixed(4) : 'N/A'}`);

        console.log("--- Derived Values ---");
        console.log(`Decay Constant (k = -slope): ${decay_constant_k.toExponential(5)} (1/s)`);

        // Calculate and log half-life
        console.log(`Half-life (t_1/2 = ln(2)/k): ${half_life_s.toFixed(1)} s ≈ ${half_life_h.toFixed(2)} hours ≈ ${half_life_d.toFixed(2)} days`);

        console.log("--- Final Result ---");
        console.log(`Heat Loss Coefficient (U = V_cyl * ρ * cp * k): ${U_WK.toFixed(3)} W/K`);

    } finally {
        console.groupEnd();
    }
    // --- End Detailed Logging ---


    if (dhw_type == 'temp') {
        heatlossDisplay.html(U_WK.toFixed(2) + " W/K");
    } else {
        heatlossDisplay.html("---");
    }
    halflifeDisplay.html(half_life_d.toFixed(2));
   }

// -------------------------------------------------------------------------------
// POWER GRAPH
// -------------------------------------------------------------------------------
function powergraph_draw() {
    $("#overlay_text").html("");
    $("#overlay").hide();  
    
    set_url_view_params("power", view.start, view.end);

    var style = { size: flot_font_size, color: "#666" }
    var options = {
        lines: { fill: false },
        xaxis: {
            mode: "time", timezone: "browser",
            min: view.start, max: view.end,
            font: style,
            reserveSpace: false
        },
        yaxes: [
            { min: 0, font: style, reserveSpace: false },
            { font: style, reserveSpace: false },
            { min: 0, font: { size: flot_font_size, color: "#44b3e2" }, reserveSpace: false },
            { min: 0, max: 1, show: false, reserveSpace: false }
        ],
        grid: {
            show: true,
            color: "#aaa",
            borderWidth: 0,
            hoverable: true,
            clickable: true,
            // labelMargin:0,
            // axisMargin:0
            margin: { top: 30 }
        },
        selection: { mode: "x" },
        legend: { position: "NW", noColumns: 13 }
    }

    if (show_defrost_and_loss || show_cooling) {
        options.yaxes[0].min = undefined;
    }

    if ($('#placeholder').width()) {
        // Remove keys
        var powergraph_series_without_key = [];
        for (var key in powergraph_series) {
            let show = true;
            if (key == 'heatpump_flowrate' && !show_flow_rate) show = false;
            if (key == 'immersion_elec' && !show_immersion) show = false;
            if (key == 'heatpump_dhwT' && !show_dhw_temp) show = false;
            if (key == 'heatpump_dhwTargetT' && !show_dhw_temp) show = false;
            if (show) powergraph_series_without_key.push(powergraph_series[key]);
        }
        $.plot($('#placeholder'), powergraph_series_without_key, options);
    }

    // show symbol when live scrolling is active
    var now = new Date().getTime();
    if (view.end > now - 5 * MINUTE && view.end <= now + 5 * MINUTE && view.end - view.start <= 2 * DAY) {
        $('#right').hide();
        $('#live').show();
    }
    else {
        $('#live').hide();
        $('#right').show();
    }
}


function draw_histogram(histogram) {

    var keys = [];
    for (k in histogram) {
        if (histogram.hasOwnProperty(k)) {
            keys.push(k * 1);
        }
    }
    keys.sort();

    var sorted_histogram = []
    for (var z in keys) {
        sorted_histogram.push([keys[z], histogram[keys[z]]])
    }

    var options = {
        // lines: { fill: true },
        bars: { show: true, align: "center", barWidth: (1 / 200) * 0.8, fill: 1.0, lineWidth: 0 },
        xaxis: {
            // mode: "time", timezone: "browser", 
            min: 0.2, max: 0.8,
            font: { size: flot_font_size, color: "#666" },
            reserveSpace: false
        },
        yaxes: [
            //{ min: 0,font: {size:flot_font_size, color:"#666"},reserveSpace:false},
            { font: { size: flot_font_size, color: "#666" }, reserveSpace: false }
        ],
        grid: {
            show: true,
            color: "#aaa",
            borderWidth: 0,
            hoverable: true,
            clickable: true,
            // labelMargin:0,
            // axisMargin:0
            margin: { top: 30 }
        },
        //selection: { mode: "x" },
        legend: { position: "NW", noColumns: 6 }
    }
    if ($('#histogram').width() > 0) {
        $.plot($('#histogram'), [{ data: sorted_histogram }], options);
    }
}

function powergraph_tooltip(item) {

    var itemTime = item.datapoint[0];
    var itemValue = item.datapoint[1];
    var z = item.dataIndex;

    var d = new Date(itemTime);
    var days = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    var date = days[d.getDay()] + ", " + months[d.getMonth()] + " " + d.getDate();

    var h = d.getHours();
    if (h < 10) h = "0" + h;
    var m = d.getMinutes();
    if (m < 10) m = "0" + m;
    var time = h + ":" + m;

    var name = "";
    var unit = "";
    var dp = 0;

    // extract unit for DHW (% or °C)
    var unit_dhw = config.app.heatpump_dhwT_unit.value || '°C';
    var name_dhw = (unit_dhw == '°C') ? "DHW T" : "DHW Charge"; 
    var name_dhw_target = (unit_dhw == '°C') ? "DHW Target T" : "DHW Target Charge"; 
    var name_dhw_fitted = (unit_dhw == '°C') ? "DHW T (Fitted)" : "DHW Charge (Fitted)"; 


    if (item.series.label == "FlowT") { name = "FlowT"; unit = "°C"; dp = 1; }
    else if (item.series.label == "ReturnT") { name = "ReturnT"; unit = "°C"; dp = 1; }
    else if (item.series.label == "OutsideT") { name = "Outside"; unit = "°C"; dp = 1; }
    else if (item.series.label == "RoomT") { name = "Room"; unit = "°C"; dp = 1; }
    else if (item.series.label == "TargetT") { name = "Target"; unit = "°C"; dp = 1; }
    else if (item.series.label == "DHW") { name = "Hot Water"; unit = ""; dp = 0; }
    else if (item.series.label == "CH") { name = "Central Heating"; unit = ""; dp = 0; }
    else if (item.series.label == "Cooling") { name = "Cooling"; unit = ""; dp = 0; }
    else if (item.series.label == "Error") { name = "Error"; unit = ""; dp = 0; }
    else if (item.series.label == "Electric") { name = "Elec"; unit = "W"; }
    else if (item.series.label == "Heat") { name = "Heat"; unit = "W"; }
    else if (item.series.label == "Carnot Heat") { name = "Carnot Heat"; unit = "W"; }
    else if (item.series.label == "Simulated flow rate") { name = "Simulated flow rate"; unit = ""; dp = 3; }
    else if (item.series.label == "Inst COP") { name = "Inst COP"; unit = ""; dp = 1; }
    else if (item.series.label == "Emitter spec") { name = "Emitter spec"; unit = "kW"; dp = 1; }
    else if (item.series.label == "Emitter spec heat") { name = "Radiator heat output"; unit = "W"; dp = 0; }
    else if (item.series.label == "System volume") { name = "System volume"; unit = "L"; dp = 0; }
    else if (item.series.label == "Flow rate") {
        name = "Flow rate";
        unit = " " + feeds["heatpump_flowrate"].unit;
        dp = 3;
    }
    else if (item.series.label == "Immersion") { name = "Immersion"; unit = "W"; }
    else if (item.series.label == "DHW T") { name = "DHW T"; unit = "°C"; dp = 1; }
    else if (item.series.label == "DHW TargetT") { name = "DHW Target T"; unit = "°C"; dp = 1; }
    else if (item.series.label == "DHW T (Fitted)") { name = "DHW T (Fitted)"; unit = "°C"; dp = 1; }
    else if (item.series.label == "DHW Charge") { name = "DHW Charge"; unit = "%"; dp = 1; }
    else if (item.series.label == "DHW Target Charge") { name = "DHW Target Charge"; unit = "%"; dp = 1; }
    else if (item.series.label == "DHW Charge (Fitted)") { name = "DHW Charge (Fitted)"; unit = "%"; dp = 1; }

    tooltip(item.pageX, item.pageY, name + " " + itemValue.toFixed(dp) + unit + "<br>" + date + ", " + time, "#fff", "#000");
}

/**
 * Performs linear regression on paired data.
 * y = mx + b
 * @param {number[]} x - Array of x values (independent variable, e.g., time in seconds).
 * @param {number[]} y - Array of y values (dependent variable, e.g., ln(deltaT/deltaT0)).
 * @returns {object|null} Object with 'slope' (m) and 'intercept' (b), or null if regression is not possible.
 */
function linearRegression(x, y) {
    const n = x.length;
    if (n < 2 || n !== y.length) {
        console.error("Linear regression requires at least 2 points and equal length arrays.");
        return null; // Not enough data or mismatched arrays
    }

    let sum_x = 0;
    let sum_y = 0;
    let sum_xy = 0;
    let sum_xx = 0;
    let sum_yy = 0; // Needed for R-squared, not strictly required for slope/intercept

    for (let i = 0; i < n; i++) {
        sum_x += x[i];
        sum_y += y[i];
        sum_xy += x[i] * y[i];
        sum_xx += x[i] * x[i];
        sum_yy += y[i] * y[i];
    }

    const denominator = (n * sum_xx - sum_x * sum_x);
    if (Math.abs(denominator) < 1e-10) { // Avoid division by zero if all x are the same
         console.error("Linear regression failed: Denominator is zero (all x values are likely the same).");
         return null;
    }

    const slope = (n * sum_xy - sum_x * sum_y) / denominator;
    const intercept = (sum_y - slope * sum_x) / n;

    // Optional: Calculate R-squared (coefficient of determination)
    let ssr = 0;
    for (let i = 0; i < n; i++) {
        const fit = slope * x[i] + intercept;
        ssr += (fit - sum_y / n) ** 2;
    }
    const sst = sum_yy - (sum_y * sum_y) / n;
    const r2 = (sst === 0) ? 1 : ssr / sst; // Handle case where all y are the same

    return {
        slope: slope,
        intercept: intercept,
        r2: r2 // Uncomment if you want R-squared
    };
}

// Powergraph events (advanced section)

// Power graph navigation
$("#zoomout").click(function () { view.zoomout(); powergraph_load(); });
$("#zoomin").click(function () { view.zoomin(); powergraph_load(); });
$('#right').click(function () { view.panright(); powergraph_load(); });
$('#left').click(function () { view.panleft(); powergraph_load(); });

$('.time').click(function () {
    view.timewindow($(this).attr("time") / 24.0);
    powergraph_load();
});

// Detail section events

$(".show_stats_category").click(function () {
    var key = $(this).attr("key");
    var color = $(this).css("color");
    $(".stats_category").hide();
    $(".stats_category[key='" + key + "']").show();
    $(".show_stats_category").css("border-bottom", "none");
    $(this).css("border-bottom", "1px solid " + color);
});


$("#carnot_enable").click(function () {

    if ($("#carnot_enable_prc")[0].checked && !$("#carnot_enable")[0].checked) {
        $("#carnot_enable_prc")[0].checked = 0;
    }

    if ($("#carnot_enable")[0].checked) {
        $("#carnot_sim_options").show();
    } else {
        $("#carnot_sim_options").hide();
        $("#carnot_prc_options").hide();
    }

    powergraph_process();
});

$("#carnot_enable_prc").click(function () {

    if ($("#carnot_enable_prc")[0].checked) {
        $("#carnot_enable")[0].checked = 1;
        $("#heatpump_factor")[0].disabled = 1;
        $("#carnot_prc_options").show();
        $("#carnot_sim_options").show();
    } else {
        $("#heatpump_factor")[0].disabled = 0;
        $("#carnot_prc_options").hide();
    }

    powergraph_process();
});

$("#condensing_offset").change(function () {
    powergraph_process();
});

$("#evaporator_offset").change(function () {
    powergraph_process();
});

$("#heatpump_factor").change(function () {
    powergraph_process();
});

$("#starting_power").change(function () {
    powergraph_process();
});

$("#fixed_outside_temperature").change(function () {
    powergraph_process();
});

$("#show_flow_rate").click(function () {
    if ($("#show_flow_rate")[0].checked) {
        show_flow_rate = true;
    } else {
        show_flow_rate = false;
    }
    powergraph_draw();
});

$("#show_immersion").click(function () {
    if ($("#show_immersion")[0].checked) {
        show_immersion = true;
    } else {
        show_immersion = false;
    }
    powergraph_draw();
});

$("#show_defrost_and_loss").click(function () {
    if ($("#show_defrost_and_loss")[0].checked) {
        show_defrost_and_loss = true;
    } else {
        show_defrost_and_loss = false;
    }
    powergraph_draw();
});

$("#show_instant_cop").click(function () {

    if ($("#show_instant_cop")[0].checked) {
        show_instant_cop = true;
        $("#inst_cop_options").show();
    } else {
        show_instant_cop = false;
        $("#inst_cop_options").hide();
    }

    powergraph_process();
});

$("#inst_cop_min").change(function () {
    inst_cop_min = parseInt($("#inst_cop_min").val());
    powergraph_process();
});

$("#inst_cop_max").change(function () {
    inst_cop_max = parseInt($("#inst_cop_max").val());
    powergraph_process();
});

$("#inst_cop_mv_av_dp").change(function () {
    inst_cop_mv_av_dp = parseInt($("#inst_cop_mv_av_dp").val());
    powergraph_process();
});

$("#realtime_cop_div").click(function () {
    if (realtime_cop_div_mode == "30min") {
        realtime_cop_div_mode = "inst";
        $("#realtime_cop_title").html("COP Now");
        $("#realtime_cop_value").html("---");
    } else {
        realtime_cop_div_mode = "30min";
        $("#realtime_cop_title").html("COP 30mins");
        $("#realtime_cop_value").html("---");
        progtime = 0;
    }
    updater();
});

$("#emitter_spec_enable").click(function () {
    if ($("#emitter_spec_enable")[0].checked) {
        emitter_spec_enable = true;
        $("#emitter_spec_options").show();
    } else {
        emitter_spec_enable = false;
        $("#emitter_spec_options").hide();
    }
    powergraph_process();
});

$("#use_for_volume_calc").click(function () {
    kw_at_50_for_volume = kw_at_50;
});

$("#configure_standby").click(function () {
    if ($("#configure_standby")[0].checked) {
        $("#configure_standby_options").show();
    } else {
        $("#configure_standby_options").hide();
    }
});

$('#histogram').bind("plothover", function (event, pos, item) {
    if (item) {
        var z = item.dataIndex;
        if (previousPoint != item.datapoint) {
            previousPoint = item.datapoint;
            $("#tooltip").remove();
            tooltip(item.pageX, item.pageY, item.datapoint[0] + ": " + (item.datapoint[1]).toFixed(3) + " kWh", "#fff", "#000");

        }
    } else $("#tooltip").remove();
});

// Show advanced section on powergraph
$("#advanced-toggle").click(function () {
    var state = $(this).html();

    if (state == "SHOW DETAIL") {
        $("#advanced-block").show();
        $("#advanced-toggle").html("HIDE DETAIL");

    } else {
        $("#advanced-block").hide();
        $("#advanced-toggle").html("SHOW DETAIL");
    }
});

$("#manual_roomT_enable").click(function () {
    if ($("#manual_roomT_enable")[0].checked) {
        // enable manual roomT
        $("#room_temperature").prop('disabled', false);
    } else {
        // disable manual roomT
        $("#room_temperature").prop('disabled', true);
    }
});

$("#room_temperature").change(function () {
    powergraph_process();
});

$("#fix_kW_at_50").click(function () {
    if ($("#fix_kW_at_50")[0].checked) {
        $("#kW_at_50").prop('disabled', false);
    } else {
        $("#kW_at_50").prop('disabled', true);
    }
});

$("#kW_at_50").change(function () {
    powergraph_process();
});

// Standby Heat Loss Calculation Enable/Disable
$("#standby_dhw_hl_enable").click(function () {
    if ($(this).is(":checked")) {
        standby_dhw_hl_enable = true;
        $("#standby_dhw_hl_options").show();
        calculate_standby_heat_loss();
    } else {
        standby_dhw_hl_enable = false;
        $("#standby_dhw_hl_options").hide();
        $("#standby_dhw_hl_result").html("---"); // Clear result
    }
    powergraph_draw();
});

// Recalculate Standby Heat Loss on input change
$("#cylinder_volume, #env_temperature").on('change input', function() {
    if (standby_dhw_hl_enable) {
        calculate_standby_heat_loss();
    }
});

// if press key 'd', copy defrost info to clipboard
$(document).keypress(function (e) {
    if (e.which == 100) {
        var defrost_info = [];

        defrost_info.push(stats.combined.heatpump_outsideT.mean.toFixed(1));
        defrost_info.push(stats.combined.heatpump_flowT.maxval.toFixed(1));
        defrost_info.push(stats.combined.heatpump_flowT.mean.toFixed(1));
        defrost_info.push(stats.combined.heatpump_flowrate.mean.toFixed(3));
        defrost_info.push(stats.combined.heatpump_elec.maxval.toFixed(0));
        defrost_info.push(stats.combined.heatpump_elec.mean.toFixed(0));
        defrost_info.push(stats.combined.heatpump_heat.maxval.toFixed(0));
        defrost_info.push(stats.combined.heatpump_heat.mean.toFixed(0));

        // get share link
        defrost_info.push('?');
        defrost_info.push('?');
        defrost_info.push('?');
        defrost_info.push('?');

        var share_link = window.location.href;
        defrost_info.push(share_link);

        var defrost_text = defrost_info.join("\t");
        copy_text_to_clipboard(defrost_text, "Defrost copied to clipboard");
    }
});
