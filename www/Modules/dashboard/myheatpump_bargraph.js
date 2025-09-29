
// -------------------------------------------------------------------------------
// BAR GRAPH
// -------------------------------------------------------------------------------

var daily_data = {};
var bargraph_series = [];

var process_daily_timeout = 1; // start at 1s

var show_daily_cooling = false;
var show_daily_immersion = false;

function process_daily_data() {

    $("#overlay").show();
    $.ajax({
        url: path + "app/processdaily",
        data: { id: config.id, apikey: apikey, timeout: process_daily_timeout },
        async: true,
        success: function (result) {
            if (result.days_left != undefined) {
                if (result.days_left > 0) {
                    $("#overlay_text").html("Processing daily data... " + result.days_left + " days left");
                    // run again in 10 seconds
                    process_daily_timeout = 5;
                    setTimeout(process_daily_data, 1000);
                    
                } else {
                    $("#overlay_text").html("");
                    $("#overlay").hide();
                    // reload bargraph
                    bargraph_load(bargraph_start, bargraph_end);
                    bargraph_draw();    
                }
            }

            if (result.success != undefined) {
                // if false
                if (!result.success) {
                    $("#overlay").show();
                    $("#overlay_text").html(result.message);
                    setTimeout(process_daily_data, 1000);
                }
            }
        }
    });
}

function bargraph_load(start, end) {

    $("#data-error").hide();

    var intervalms = DAY;
    end = Math.ceil(end / intervalms) * intervalms;
    start = Math.floor(start / intervalms) * intervalms;

    bargraph_start = start;
    bargraph_end = end;

    daily_data = {};

    if (config.app.enable_process_daily.value) {
        // Fetch daily data e.g http://localhost/emoncms/app/getdailydata?name=MyHeatpump&apikey=APIKEY
        // Ajax jquery syncronous request
        // format is csv
        $.ajax({
            url: path + "system/stats/daily",
            data: { id: config.id, start: start*0.001, end: end*0.001, apikey: apikey },
            async: false,
            success: function (data) {
                var rows = data.split("\n");
                var fields = rows[0].split(",");

                
                for (var z = 1; z < rows.length; z++) {
                    var cols = rows[z].split(",");
                    var timestamp = cols[1] * 1000;

                    if (cols.length == fields.length) {
                        for (var i=2; i<fields.length; i++) {
                            if (daily_data[fields[i]] == undefined) daily_data[fields[i]] = [];

                            if (cols[i] != "") {
                                cols[i] = parseFloat(cols[i]);
                            } else {
                                cols[i] = null;
                            }

                            daily_data[fields[i]].push([timestamp, cols[i]]);
                        }
                    }
                }

                // Is there dhw data?
                show_daily_dhw = false;
                for (var z in daily_data["water_heat_kwh"]) {
                    if (daily_data["water_heat_kwh"][z][1] > 0) {
                        show_daily_dhw = true;
                        break;
                    }
                }

                // Is there cooling data?
                show_daily_cooling = false;
                for (var z in daily_data["cooling_heat_kwh"]) {
                    if (daily_data["cooling_heat_kwh"][z][1] > 0) {
                        show_daily_cooling = true;
                        break;
                    }
                }

                // Is there immersion heater data?
                show_daily_immersion = false;
                for (var z in daily_data["immersion_kwh"]) {
                    if (daily_data["immersion_kwh"][z][1] > 0) {
                        show_daily_immersion = true;
                        break;
                    }
                }

                if (show_daily_dhw) {
                    $(".bargraph_mode[mode='water']").show();
                    $(".bargraph_mode[mode='space']").show();
                } else {
                    $(".bargraph_mode[mode='water']").hide();
                    $(".bargraph_mode[mode='space']").hide();
                }

                if (show_daily_cooling) {
                    $(".bargraph_mode[mode='cooling']").show();
                } else {
                    $(".bargraph_mode[mode='cooling']").hide();
                }

                /*
                var weighted_flowT_sum = 0;
                var weighted_outsideT_sum = 0;
                var total_kwh_heat = 0;

                var total_kwh_elec_running = 0;
                var total_kwh_heat_running = 0;
                var total_kwh_carnot_elec = 0;

                for (var z in daily_data["weighted_flowT"]) {
                    let kwh_heat = daily_data["weighted_kwh_heat"][z][1];
                    let flowT = daily_data["weighted_flowT"][z][1];
                    let outsideT = daily_data["weighted_outsideT"][z][1];

                    let kwh_elec_running = daily_data["weighted_kwh_elec_running"][z][1];
                    let kwh_heat_running = daily_data["weighted_kwh_heat_running"][z][1];
                    let kwh_carnot_elec = daily_data["weighted_kwh_carnot_elec"][z][1];

                    weighted_flowT_sum += flowT * kwh_heat;
                    weighted_outsideT_sum += outsideT * kwh_heat;
                    total_kwh_heat += kwh_heat;

                    total_kwh_elec_running += kwh_elec_running;
                    total_kwh_heat_running += kwh_heat_running;
                    total_kwh_carnot_elec += kwh_carnot_elec;

                }

                var wa_prc_carnot = 0;
                if (total_kwh_elec_running > 0 && total_kwh_carnot_elec > 0) {
                    wa_prc_carnot = 100 * (total_kwh_heat_running / total_kwh_elec_running) / (total_kwh_heat_running / total_kwh_carnot_elec);
                }

                var weighted_flowT_mean = weighted_flowT_sum / total_kwh_heat;
                var weighted_outsideT_mean = weighted_outsideT_sum / total_kwh_heat;
                console.log("weighted_flowT_mean", weighted_flowT_mean);
                console.log("weighted_outsideT_mean", weighted_outsideT_mean);
                console.log("wa_prc_carnot", wa_prc_carnot);
                console.log("total_kwh_heat", total_kwh_heat);
                */
            }
        });
    } else {

        // Option: Use standard feed data instead of pre-processed daily data

        if (heat_enabled) {
            daily_data["combined_heat_kwh"] = feed.getdata(feeds["heatpump_heat_kwh"].id, start, end, "daily", 0, 1);
        }
        if (elec_enabled) {
            daily_data["combined_elec_kwh"] = feed.getdata(feeds["heatpump_elec_kwh"].id, start, end, "daily", 0, 1);
        }
        if (feeds["heatpump_outsideT"] != undefined) {
            if ((end - start) < 120 * DAY) {
                daily_data["combined_outsideT_mean"] = feed.getdata(feeds["heatpump_outsideT"].id, start, end, "daily", 1, 0);
            }
        }

        // add series that shows COP points for each day
        if (heat_enabled) {
            if ((end - start) < 120 * DAY) {
                daily_data["combined_cop"] = [];
                for (var z in daily_data["combined_elec_kwh"]) {
                    time = daily_data["combined_elec_kwh"][z][0];
                    elec = daily_data["combined_elec_kwh"][z][1];
                    heat = daily_data["combined_heat_kwh"][z][1];
                    if (elec && heat) {
                        daily_data["combined_cop"][z] = [time, heat / elec];
                    }
                }
            }
        }
    }

    set_url_view_params('daily', start, end);
}

function bargraph_draw() {

    bargraph_series = [];

    var elec_kwh_in_window = 0;
    var heat_kwh_in_window = 0;
    var immersion_kwh_in_window = 0;
    var days_elec = 0;
    var days_heat = 0;

    // If we have heating data
    // - add heating data to bargraph
    // - add cooling data to bargraph if in combined mode and cooling data is present
    if (daily_data[bargraph_mode+"_heat_kwh"] != undefined) {

        data["heatpump_heat_kwhd"] = daily_data[bargraph_mode+"_heat_kwh"];

        let color = 0;
        if (bargraph_mode == "cooling") {
            color = "#66b0ff";
        }
        
        bargraph_series.push({
            data: data["heatpump_heat_kwhd"], color: color,
            bars: { show: true, align: "center", barWidth: 0.75 * DAY, fill: 1.0, lineWidth: 0 },
            stack: true
        });
        

        for (var z in data["heatpump_heat_kwhd"]) {
            heat_kwh_in_window += data["heatpump_heat_kwhd"][z][1];
            days_heat++;
        }

        // If we are in combined mode and there is cooling data
        // overlay cooling data on top of heating data
        if (bargraph_mode=="combined" && show_daily_cooling) {
            data["cooling_heat_kwhd"] = daily_data["cooling_heat_kwh"];
            bargraph_series.push({
                data: data["cooling_heat_kwhd"], color: "#66b0ff",
                bars: { show: true, align: "center", barWidth: 0.75 * DAY, fill: 1.0, lineWidth: 0 }
            });
        }
    }

    // If we have electric data add to bargraph
    if (daily_data[bargraph_mode+"_elec_kwh"] != undefined) {
        data["heatpump_elec_kwhd"] = daily_data[bargraph_mode+"_elec_kwh"];

        bargraph_series.push({
            data: data["heatpump_elec_kwhd"], color: 1,
            bars: { show: true, align: "center", barWidth: 0.75 * DAY, fill: 1.0, lineWidth: 0 },
            stack: false
        });

        for (var z in data["heatpump_elec_kwhd"]) {
            elec_kwh_in_window += data["heatpump_elec_kwhd"][z][1];
            days_elec++;
        }
    }

    // If we have outside temperature data add to bargraph
    if (feeds["heatpump_outsideT"] != undefined) {
        data["heatpump_outsideT_daily"] = daily_data["combined_outsideT_mean"];

        bargraph_series.push({
            data: data["heatpump_outsideT_daily"], color: "#c880ff", yaxis: 2,
            lines: { show: true, align: "center", fill: false }, points: { show: false }
        });
    }

    // If we have % carnot data add to bargraph
    if (daily_data["running_prc_carnot"] != undefined && $("#carnot_enable")[0].checked) {
        data["running_prc_carnot"] = daily_data["running_prc_carnot"];

        bargraph_series.push({
            data: data["running_prc_carnot"], color: "#ff9e80", yaxis: 2,
            points: { show: true }
        });
    }

    // If we have error data add to bargraph
    if (daily_data["error_air"] != undefined) {
        data["error_air"] = daily_data["error_air"];

        let total_error_air = 0;
        for (var z in data["error_air"]) {
            total_error_air += data["error_air"][z][1];
        }

        if (daily_data["error_air_kwh"] != undefined) {
            data["error_air_kwh"] = daily_data["error_air_kwh"];
        }

        let total_error_air_elec_kwh = 0;
        if (data["error_air_kwh"] != undefined) {
            for (var z in data["error_air_kwh"]) {
                total_error_air_elec_kwh += data["error_air_kwh"][z][1];
            }
        }

        let prc_error_air_kwh = 0;
        if (elec_kwh_in_window > 0) {
            prc_error_air_kwh = (total_error_air_elec_kwh / elec_kwh_in_window) * 100;
        }

        if (prc_error_air_kwh >= 1) {
            var error_div = $("#data-error");
            error_div.show();
            error_div.attr("title", "Heat meter air issue detected for " + (total_error_air / 60).toFixed(0) + " minutes (" + (total_error_air_elec_kwh).toFixed(1) + " kWh)");
            
            bargraph_series.push({
                data: data["error_air"], color: "#ff0000", yaxis: 4,
                points: { show: true }
            });
        }
    }

    // If we have COP data add to bargraph
    if (daily_data[bargraph_mode+"_cop"] != undefined) {
        cop_data = daily_data[bargraph_mode+"_cop"];

        bargraph_series.push({
            data: cop_data, color: "#44b3e2", yaxis: 3,
            points: { show: true }
        });
    }

    // If we are in combined mode and there is immersion data
    // overlay immersion data on top of heating data
    if (show_daily_immersion && (bargraph_mode=="combined" || bargraph_mode=="water")) {
        data["immersion_kwhd"] = daily_data["immersion_kwh"];
        bargraph_series.push({
            data: data["immersion_kwhd"], color: 4,
            bars: { show: true, align: "center", barWidth: 0.75 * DAY, fill: 0.8, lineWidth: 0 },
            stack: true
        });

        // Calculate total immersion energy
        for (var z in data["immersion_kwhd"]) {
            immersion_kwh_in_window += data["immersion_kwhd"][z][1];
        }
    }

    var cop_in_window = 0; 
    if (elec_kwh_in_window>0) {
        cop_in_window = heat_kwh_in_window / elec_kwh_in_window;
    }
    if (cop_in_window < 0) cop_in_window = 0;
    $("#window-cop").html((cop_in_window).toFixed(2));

    var prefix = "";
    if (show_daily_immersion && (bargraph_mode=="combined" || bargraph_mode=="water")) {
        var elec_inc_aux_in_window = elec_kwh_in_window + immersion_kwh_in_window;
        var heat_inc_aux_in_window = heat_kwh_in_window + immersion_kwh_in_window;
        var cop_inc_aux_in_window = 0;
        if (elec_inc_aux_in_window > 0) {
            cop_inc_aux_in_window = heat_inc_aux_in_window / elec_inc_aux_in_window;
        }
        if (cop_inc_aux_in_window < 0) cop_inc_aux_in_window = 0;
        $("#window-cop").html((cop_in_window).toFixed(2) + " (" + (cop_inc_aux_in_window).toFixed(2) + ")");
        prefix = "HP ";
    }

    var tooltip_text = "";
    tooltip_text += prefix+"Electric: " + elec_kwh_in_window.toFixed(0) + " kWh (" + (elec_kwh_in_window / days_elec).toFixed(1) + " kWh/d)\n";
    tooltip_text += prefix+"Heat: " + heat_kwh_in_window.toFixed(0) + " kWh (" + (heat_kwh_in_window / days_heat).toFixed(1) + " kWh/d)\n";

    if (show_daily_immersion && (bargraph_mode=="combined" || bargraph_mode=="water")) {
        tooltip_text += "Immersion: " + immersion_kwh_in_window.toFixed(0) + " kWh (" + (immersion_kwh_in_window / days_heat).toFixed(1) + " kWh/d)\n";
    }
    tooltip_text += "Days: " + days_elec;
    $("#window-cop").attr("title", tooltip_text);

    $("#window-carnot-cop").html("");

    var options = {
        xaxis: {
            mode: "time",
            timezone: "browser",
            font: { size: flot_font_size, color: "#666" },
            // labelHeight:-5
            reserveSpace: false,
            min: bargraph_start, 
            max: bargraph_end
        },
        yaxes: [{
            font: { size: flot_font_size, color: "#666" },
            // labelWidth:-5
            reserveSpace: false,
            min: 0
        }, {
            font: { size: flot_font_size, color: "#c880ff" },
            // labelWidth:-5
            reserveSpace: false,
            // max:40
        }, {
            font: { size: flot_font_size, color: "#44b3e2" },
            reserveSpace: false,
            min: 1,
            max: 8
        }, {
            show: false
        }],
        selection: { mode: "x" },
        grid: {
            show: true,
            color: "#aaa",
            borderWidth: 0,
            hoverable: true,
            clickable: true
        }
    }
    
    if ($('#placeholder').width()) {
        var plot = $.plot($('#placeholder'), bargraph_series, options);
        $('#placeholder').append("<div id='bargraph-label' style='position:absolute;left:50px;top:30px;color:#666;font-size:12px'></div>");
    }
}

function bargraph_tooltip(item)
{
    var itemTime = item.datapoint[0];
    var z = item.dataIndex;

    var elec_kwh = null;
    var heat_kwh = null;
    if (elec_enabled && data["heatpump_elec_kwhd"].length && data["heatpump_elec_kwhd"][z] != undefined) elec_kwh = data["heatpump_elec_kwhd"][z][1];
    if (heat_enabled && data["heatpump_heat_kwhd"].length && data["heatpump_heat_kwhd"][z] != undefined) heat_kwh = data["heatpump_heat_kwhd"][z][1];

    var outside_temp_str = "";
    if (feeds["heatpump_outsideT"] != undefined) {
        if (data["heatpump_outsideT_daily"] != undefined && data["heatpump_outsideT_daily"].length && data["heatpump_outsideT_daily"][z] != undefined) {
            let outsideT = data["heatpump_outsideT_daily"][z][1];
            if (outsideT != null) {
                outside_temp_str = "Outside: " + outsideT.toFixed(1) + "°C<br>";
            }
        }
    }

    var COP = null;
    if (heat_kwh !== null && elec_kwh !== null) COP = heat_kwh / elec_kwh;

    var d = new Date(itemTime);
    var days = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    var date = days[d.getDay()] + ", " + months[d.getMonth()] + " " + d.getDate();

    if (COP !== null) COP = (COP).toFixed(2); else COP = "---";

    var str_prc_carnot = "";
    if ($("#carnot_enable")[0].checked) {
        if (data["running_prc_carnot"] != undefined && data["running_prc_carnot"].length && data["running_prc_carnot"][z] != undefined) {
            let prc_carnot = data["running_prc_carnot"][z][1];
            if (prc_carnot != null) {
                str_prc_carnot = "<br>Carnot: " + prc_carnot.toFixed(1) + "%<br>";
            }
        }
    }

    var cool_kwh = null;
    var cooling_str = "";
    if (show_daily_cooling) {
        if (data["cooling_heat_kwhd"].length && data["cooling_heat_kwhd"][z] != undefined) cool_kwh = data["cooling_heat_kwhd"][z][1];
        if (cool_kwh !== null) {
            cooling_str = "<br>Cooling: " + cool_kwh.toFixed(1) + " kWh";
            if (heat_kwh !== null) {
                cooling_str += "<br>Combined: " + heat_kwh.toFixed(3) + " kWh";
            }
        }
        heat_kwh -= cool_kwh;
    }

    // immersion heater
    // only if daily mode = water or combined
    var immersion_str = "";
    var hp_prefix = "";

    if (bargraph_mode == "water" || bargraph_mode == "combined") {
        var immersion_kwh = null;
        if (show_daily_immersion) {
            if (data["immersion_kwhd"].length && data["immersion_kwhd"][z] != undefined) immersion_kwh = data["immersion_kwhd"][z][1];
            if (immersion_kwh !== null) {
                immersion_str = "<br>Immersion: " + immersion_kwh.toFixed(1) + " kWh";
                hp_prefix = "HP ";
            }
        }
        // Calculate COP with immersion heater
        var COP_H4 = null;
        if (elec_kwh !== null && heat_kwh !== null && immersion_kwh !== null) {
            COP_H4 = (heat_kwh + immersion_kwh) / (elec_kwh + immersion_kwh);
            COP += " (" + (COP_H4).toFixed(2) + ")";
        }
    }

    var error_str = "";
    if (data["error_air"] != undefined && data["error_air"].length && data["error_air"][z] != undefined) {
        let error_air = data["error_air"][z][1];
        if (error_air > 0) {
            error_str = "<br>Error: " + (error_air / 60).toFixed(0) + " min";
        }
    }

    if (data["error_air_kwh"] != undefined && data["error_air_kwh"].length && data["error_air_kwh"][z] != undefined) {
        let error_air_kwh = data["error_air_kwh"][z][1];
        if (error_air_kwh > 0) {
            error_str += " (" + error_air_kwh.toFixed(1) + " kWh)";
        }
    }

    if (elec_kwh !== null) elec_kwh = (elec_kwh).toFixed(1); else elec_kwh = "---";
    if (heat_kwh !== null) heat_kwh = (heat_kwh).toFixed(1); else heat_kwh = "---";

    tooltip(item.pageX, item.pageY, date + "<br>" + hp_prefix + "Electric: " + elec_kwh + " kWh<br>" + hp_prefix + "Heat: " + heat_kwh + " kWh"+cooling_str+ immersion_str + "<br>" + outside_temp_str + "COP: " + COP + str_prc_carnot + error_str, "#fff", "#000");
}


// Bargraph events

$(".bargraph_mode").click(function () {
    var mode = $(this).attr("mode");
    // change color of selected mode
    $(".bargraph_mode").css("color", "#fff");

    var mode_colors = {
        "combined": "#44b3e2",
        "running": "#44b3e2",
        "water": "#44b3e2",
        "space": "#44b3e2",
        "cooling": "#44b3e2"
    };

    $(this).css("color", mode_colors[mode]);

    bargraph_mode = mode;
    bargraph_draw();
});

$('.bargraph-day').click(function () {
    view.timewindow(1.0);
    viewmode = "powergraph";
    powergraph_load();

    $(".bargraph-navigation").hide();
    $(".powergraph-navigation").show();
    $("#advanced-toggle").show();
    if ($("#advanced-toggle").html() == "SHOW DETAIL") {
        $("#advanced-block").hide();
    } else {
        $("#advanced-block").show();
    }
});

$('.bargraph-period').click(function () {
    var days = $(this).attr("days");
    var timeWindow = days * DAY;
    var end = (new Date()).getTime();
    var start = end - timeWindow;
    if (start < (start_time * 1000)) start = start_time * 1000;
    bargraph_load(start, end);
    bargraph_draw();
});

$('.bargraph-alltime').click(function () {
    var start = start_time * 1000;
    var end = (new Date()).getTime();
    bargraph_load(start, end);
    bargraph_draw();
});
