//feed.apikey = apikey;
//feed.public_userid = public_userid;
//feed.public_username = public_username;
// ----------------------------------------------------------------------
// Display
// ----------------------------------------------------------------------

$(window).ready(function () {

});

if (!session_write) $(".config-open").hide();

// ----------------------------------------------------------------------
// Configuration
// ----------------------------------------------------------------------
config.app = {
    "app_name": { "type": "value", "name": "App title", "default": "MY HEATPUMP", "optional": true, "description": "Enter custom title for app" },
    // Electric
    "heatpump_elec": { "type": "feed", "autoname": "heatpump_elec", "description": "Electric use in watts" },
    "heatpump_elec_kwh": { "type": "feed", "autoname": "heatpump_elec_kwh", "description": "Cumulative electric use kWh" },
    // Heat
    "heatpump_heat": { "type": "feed", "autoname": "heatpump_heat", "optional": true, "description": "Heat output in watts" },
    "heatpump_heat_kwh": { "type": "feed", "autoname": "heatpump_heat_kwh", "optional": true, "description": "Cumulative heat output in kWh" },
    // Sensors
    "heatpump_flowT": { "type": "feed", "autoname": "heatpump_flowT", "optional": true, "description": "Flow temperature" },
    "heatpump_returnT": { "type": "feed", "autoname": "heatpump_returnT", "optional": true, "description": "Return temperature" },
    "heatpump_outsideT": { "type": "feed", "autoname": "heatpump_outsideT", "optional": true, "description": "Outside temperature" },
    "heatpump_roomT": { "type": "feed", "autoname": "heatpump_roomT", "optional": true, "description": "Room temperature" },
    "heatpump_targetT": { "type": "feed", "autoname": "heatpump_targetT", "optional": true, "description": "Target (Room or Flow) Temperature" },
    "heatpump_flowrate": { "type": "feed", "autoname": "heatpump_flowrate", "optional": true, "description": "Flow rate" },
    // State
    "heatpump_dhw": { "type": "feed", "autoname": "heatpump_dhw", "optional": true, "description": "Status of Hot Water circuit (non-zero when running)" },
    "heatpump_ch": { "type": "feed", "autoname": "heatpump_ch", "optional": true, "description": "Status of Central Heating circuit (non-zero when running)" },
    "heatpump_cooling": { "type": "feed", "autoname": "heatpump_cooling", "optional": true, "description": "Cooling status (0: not cooling, 1: cooling)" },
    "heatpump_error": { "type": "feed", "autoname": "heatpump_error", "optional": true, "description": "Axioma heat meter error state" },
    // Additional
    "immersion_elec": { "type": "feed", "autoname": "immersion_elec", "optional": true, "description": "Immersion electric use in watts" },
    // "immersion_elec_kwh": { "type": "feed", "autoname": "immersion_elec_kwh", "optional": true, "description": "Immersion electric use kWh" },

    // DHW
    "heatpump_dhwT": { "type": "feed", "autoname": "heatpump_dhwT", "optional": true, "description": "Domestic Hot Water temperature" },
    "heatpump_dhwTargetT": { "type": "feed", "autoname": "heatpump_dhwTargetT", "optional": true, "description": "Target DHW Temperature" },
    "heatpump_dhwT_unit": {"type": "select", "name": "DHW Temperature Unit", "options": {"%": "%", "°C": "°C"}, "default": "°C", "optional": true, "description": "Select the unit for the DHW temperature feed."},
    // Other
    "starting_power": { "type": "value", "default": 150, "name": "Starting power", "description": "Starting power of heatpump in watts" },
    "auto_detect_cooling":{"type":"checkbox", "default":false, "name": "Auto detect cooling", "description":"Auto detect summer cooling if cooling status feed is not present"},
    "enable_process_daily":{"type":"checkbox", "default":false, "name": "Enable daily pre-processor", "description":"Enable split between water and space heating in daily view"},
    "start_date": { "type": "value", "default": 0, "name": "Start date", "description": "Start date for all time values (unix timestamp)" },
};
// config.feeds = feed.list();

// This is to aid with finding the userid of the app owner
//if (config.feeds.length > 0) {
//    console.log("userid: "+config.feeds[0].userid);
//}

config.initapp = function () { init() };
config.showapp = function () { show() };
config.hideapp = function () { clear() };

// ----------------------------------------------------------------------
// APPLICATION
// ----------------------------------------------------------------------
var meta = {};
var data = {};

var previousPoint = false;
var viewmode = "bargraph";
var panning = false;
var flot_font_size = 12;
var updaterinst = false;
var elec_enabled = false;
var heat_enabled = false;
var immersion_enabled = false;
var feeds = {};
var progtime = 0;
var firstrun = true;
var heatpump_elec_start = 0;
var heatpump_heat_start = 0;
var start_time = 0;
var end_time = 0;
var show_immersion = false;
var show_flow_rate = false;
var show_instant_cop = false;

var show_daily_cop_series = true;
var show_defrost_and_loss = false;
var show_cooling = false;
var emitter_spec_enable = false;

var show_dhw_temp = false

var bargraph_start = 0;
var bargraph_end = 0;
var last_bargraph_start = 0;
var last_bargraph_end = 0;
var bargraph_mode = "combined";

var realtime_cop_div_mode = "30min";

var months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

// duration contants (milliseonds)
const MINUTE = 60 * 1000;
const HOUR = 60 * MINUTE;
const DAY = 24 * HOUR;

$.ajax({
    url: path + "timeseries/available",
    data: { id: config.id },
    async: true,
    dataType: "json",
    success: function (result) {
        if (result == null || result.feeds == undefined) {
            console.log("No timeseries available");
            return;
        }

        feeds = result.feeds;

        show();
    }
});

// config.init();

function init() {
    // Quick translation of feed ids
    feeds = {};
    for (var key in config.app) {
        if (config.app[key].value) feeds[key] = config.feedsbyid[config.app[key].value];
    }
}

function show() {

    config.app.enable_process_daily.value = 1;

    $("#app_name").html(config.app['app_name'].value);
    // Apply starting_power
    config.app.starting_power.value = 150;
    $("#starting_power").val(config.app.starting_power.value);

    if (!config.app.enable_process_daily.value) {
        $(".bargraph_mode").hide();
        $("#clear-daily-data").hide();

        bargraph_mode = "combined";
    } else {
        $(".bargraph_mode").show();
        $("#clear-daily-data").show();
    }

    $("body").css('background-color', 'WhiteSmoke');
    // -------------------------------------------------------------------------------
    // Configurations
    // -------------------------------------------------------------------------------
    if (feeds["heatpump_elec_kwh"] != undefined) elec_enabled = true;
    if (feeds["heatpump_heat"] != undefined && feeds["heatpump_heat_kwh"] != undefined) heat_enabled = true;
    if (feeds["immersion_elec"] != undefined) immersion_enabled = true;

    if (feeds["heatpump_flowrate"] != undefined) {
        $("#show_flow_rate_bound").show();
    }

    if (feeds["heatpump_dhwT"] != undefined) {
        $("#show_dhw_temp_bound").show();
    }

    if (feeds["immersion_elec"] != undefined) {
        show_immersion = true;
        $("#show_immersion")[0].checked = true;
        $("#show_immersion_bound").show();
    }

    if (feeds["heatpump_dhw"] == undefined) {
        $(".show_stats_category[key='water_heating']").hide();
        $(".show_stats_category[key='space_heating']").hide();
    } else {
        $(".show_stats_category[key='water_heating']").show();
        $(".show_stats_category[key='space_heating']").show();
    }

    // -------------------------------------------------------------------------------

    if (elec_enabled) {
        //meta["heatpump_elec_kwh"] = feed.getmeta(feeds["heatpump_elec_kwh"].id);
        //if (feeds["heatpump_elec"] != undefined) meta["heatpump_elec"] = feed.getmeta(feeds["heatpump_elec"].id);
        if (feeds["heatpump_elec_kwh"].start_time > start_time) start_time = feeds["heatpump_elec_kwh"].start_time;
        if (feeds["heatpump_elec_kwh"].end_time > end_time) end_time = feeds["heatpump_elec_kwh"].end_time;
    }

    if (heat_enabled) {
        //meta["heatpump_heat_kwh"] = feed.getmeta(feeds["heatpump_heat_kwh"].id);
        //meta["heatpump_heat"] = feed.getmeta(feeds["heatpump_heat"].id);
        if (feeds["heatpump_heat_kwh"].start_time > start_time) start_time = feeds["heatpump_heat_kwh"].start_time;
        if (feeds["heatpump_heat_kwh"].end_time > end_time) end_time = feeds["heatpump_heat_kwh"].end_time;

        $("#show_negative_heat_bound").show();
    }

    var alltime_start_time = start_time;
    var config_start_date = config.app.start_date.value * 1;
    if (config_start_date > alltime_start_time) {
        alltime_start_time = config_start_date;
        var d = new Date(alltime_start_time * 1000);
        $("#all_time_history_title").html("TOTAL SINCE: " + d.getDate() + " " + months[d.getMonth()] + " " + d.getFullYear());
    } else {
        var d = new Date(start_time * 1000);
        $("#all_time_history_title").html("TOTAL SINCE: " + d.getDate() + " " + months[d.getMonth()] + " " + d.getFullYear());
    }

    // Load elec start here after start_time may have been modified by heat start time
    if (elec_enabled) {
        //heatpump_elec_start = feed.getvalue(feeds["heatpump_elec_kwh"].id, alltime_start_time);
    }

    if (heat_enabled) {
        //heatpump_heat_start = feed.getvalue(feeds["heatpump_heat_kwh"].id, alltime_start_time);
    }

    resize();

    var date = new Date();
    var now = date.getTime();

    end = end_time * 1000;

    if (now - end > HOUR) {
        $("#last_updated").show();
        $("#live_table").hide();
        date.setTime(end);
        let h = date.getHours();
        let m = date.getMinutes();
        if (h < 10) h = "0" + h;
        if (m < 10) m = "0" + m;
        $("#last_updated").html("Last updated: " + date.toDateString() + " " + h + ":" + m)
    } else {
        $("#last_updated").hide();
        $("#live_table").show();
    }

    if (urlParams.mode != undefined) {
        if (urlParams.mode == "power") {
            viewmode = "powergraph"
            $("#advanced-block").show();
            $("#advanced-toggle").html("HIDE DETAIL");
        }
        if (urlParams.cop) {
            $("#show_instant_cop").click();
            show_instant_cop = true;
            $("#inst_cop_options").show();
        }
        if (urlParams.flow) {
            $("#show_flow_rate").click();
            show_flow_rate = true;
        }
        if (urlParams.carnot) {
            $("#carnot_enable")[0].click();
            $("#heatpump_factor").val(urlParams.carnot);
        }
    }

    // If this is a new dashboard there will be less than a days data 
    // show power graph directly in this case
    var timeWindow = (end - start_time * 1000);
    if (timeWindow < 3 * DAY || viewmode == "powergraph") {
        if (timeWindow > 3 * DAY) timeWindow = DAY;
        var start = end - timeWindow;

        if (urlParams.start != undefined) start = urlParams.start * 1000;
        if (urlParams.end != undefined) end = urlParams.end * 1000;
        if (urlParams.hours != undefined) start = end - urlParams.hours * HOUR;

        view.start = start;
        view.end = end;
        viewmode = "powergraph";
        $(".bargraph-navigation").hide();
        powergraph_load();
        $(".powergraph-navigation").show();
        $("#advanced-toggle").show();
    } else {
        var timeWindow = 30 * DAY;
        var start = end - timeWindow;
        if (start < (start_time * 1000)) start = start_time * 1000;

        if (urlParams.start != undefined) start = urlParams.start * 1000;
        if (urlParams.end != undefined) end = urlParams.end * 1000;
        
        bargraph_load(start, end);
        bargraph_draw();

        // check if we need to process any historic data here
        //if (config.app.enable_process_daily.value) {
            // process_daily_data();
        //} else {
            $("#overlay_text").html("");
            $("#overlay").hide();    
        //}

        $("#advanced-toggle").hide();
    }

    // LOOP
    progtime = now;
    updater();
    updaterinst = setInterval(updater, 10000);

    // Load totals from pre-processed daily data
    if (config.app.enable_process_daily.value) {
        $.ajax({
            url: path + "system/stats/all",
            data: { id: config.id, apikey: apikey },
            async: true,
            dataType: "json",
            success: function (result) {
                result = result[config.id];
                if (result.combined_elec_kwh != undefined) {
                    $("#total_elec").html(Math.round(result.combined_elec_kwh));
                    $("#total_heat").html(Math.round(result.combined_heat_kwh));
                    $("#total_cop").html(result.combined_cop.toFixed(2));
                }
            }
        });
    }
    

    $(".ajax-loader").hide();
}

function clear() {
    clearInterval(updaterinst);
}

function updater() {
    $.ajax({
        url: path + "timeseries/values",
        data: { id: config.id, feeds: "heatpump_elec,heatpump_heat,heatpump_flowT,heatpump_elec_kwh,heatpump_heat_kwh" },
        async: true,
        dataType: "json",
        success: function (result) {


        if (result === null) { return; }
        let feeds = result;

        if (feeds["heatpump_elec"] != undefined) $("#heatpump_elec").html(Math.round(feeds["heatpump_elec"].value));
        if (feeds["heatpump_heat"] != undefined) $("#heatpump_heat").html(Math.round(feeds["heatpump_heat"].value));
        if (feeds["heatpump_flowT"] != undefined) $("#heatpump_flowT").html((1 * feeds["heatpump_flowT"].value).toFixed(1));

        if (realtime_cop_div_mode == "inst" && feeds["heatpump_elec"] != undefined && feeds["heatpump_heat"] != undefined) {
            var COP_inst = 0;
            if (feeds["heatpump_elec"].value > 0) {
                COP_inst = feeds["heatpump_heat"].value / feeds["heatpump_elec"].value;
            }
            $("#realtime_cop_value").html(COP_inst.toFixed(2));
        }

        // Update all-time values
        if (!config.app.enable_process_daily.value) {
            var total_elec = 0;
            var total_heat = 0;
            if (elec_enabled) total_elec = feeds["heatpump_elec_kwh"].value - heatpump_elec_start;
            if (heat_enabled) total_heat = feeds["heatpump_heat_kwh"].value - heatpump_heat_start;

            var total_cop = 0;
            if (total_elec > 0) total_cop = total_heat / total_elec;
            if (total_cop < 0) total_cop = 0;

            if (total_elec < 20) {
                total_elec = total_elec.toFixed(1);
            } else {
                total_elec = total_elec.toFixed(0);
            }

            if (total_heat < 20) {
                total_heat = total_heat.toFixed(1);
            } else {
                total_heat = total_heat.toFixed(0);
            }

            $("#total_elec").html(total_elec);
            $("#total_heat").html(total_heat);
            $("#total_cop").html(total_cop.toFixed(2));
        }

        // Updates every 60 seconds
        var now = new Date().getTime();
        if ((progtime < (now - MINUTE)) || firstrun) {
            firstrun = false;
            
            if (elec_enabled && heat_enabled && realtime_cop_div_mode == "30min") get_average_cop(feeds, 1800);
            
            // if (feeds["heatpump_elec"] == undefined) $("#heatpump_elec").html(Math.round(elec * HOUR / (60 * 30)));
            // if (feeds["heatpump_heat"] == undefined) $("#heatpump_heat").html(Math.round(heat * HOUR / (60 * 30)));

            // update power chart if showing up to last 5 minutes, and less than 48 hours
            if (viewmode == "powergraph") {
                var timeWindow = (view.end - view.start);
                if (view.end > progtime - 5 * MINUTE && timeWindow <= 2 * DAY) {
                    if (view.end < now) {
                        // automatically scroll power chart if at the end
                        view.end = now;
                        view.start = view.end - timeWindow;
                    }
                    
                    powergraph_load();
                }
            }
            progtime = now;
        }
    }});
}

function get_average_cop(feeds, duration) {

    $.ajax({
        url: path + "timeseries/data.json",
        data: {
            id: config.id,
            feeds: "heatpump_elec,heatpump_heat",
            start: feeds["heatpump_elec"].time - duration,
            end: feeds["heatpump_elec"].time,
            interval: 60,
            average: 1,
            delta: 0,
            skipmissing: 0,
            limitinterval: 0
        },
        async: true,
        dataType: "json",
        success: function (dps) {

            let sum_elec = 0;
            let sum_heat = 0;
            var n = 0;
            for (var z in dps["heatpump_elec"]) {
                sum_elec += dps["heatpump_elec"][z][1];
                sum_heat += dps["heatpump_heat"][z][1];
                n++;
            }
            let elec = sum_elec / n;
            let heat = sum_heat / n;
            
            var COP = 0;
            if (elec > 0 && heat > 0) COP = heat / elec;
            $("#realtime_cop_value").html(COP.toFixed(2));
        }
    });
}

// -------------------------------------------------------------------------------
// RESIZE
// -------------------------------------------------------------------------------

function resize() {
    var window_width = $(this).width();

    flot_font_size = 12;
    if (window_width < 450) {
        flot_font_size = 10;
    }

    if (window_width < 700) {
        $(".wide").hide();
    } else {
        $(".wide").show();
    }


    var top_offset = 0;
    var placeholder_bound = $('#placeholder_bound');
    var placeholder = $('#placeholder');

    var width = placeholder_bound.width();
    var height = width * 0.6;
    if (height < 250) height = 250;
    if (height > 480) height = 480;
    if (height > width) height = width;

    placeholder.width(width);
    placeholder_bound.height(height);
    placeholder.height(height - top_offset);

    

    if (viewmode == "bargraph") {
        bargraph_draw();
    } else {
        powergraph_draw();
    }
}
// on finish sidebar hide/show
$(function () {
    $(document).on('window.resized hidden.sidebar.collapse shown.sidebar.collapse', resize)
})
// ----------------------------------------------------------------------
// App log
// ----------------------------------------------------------------------
function app_log(level, message) {
    if (level == "ERROR") alert(level + ": " + message);
    console.log(level + ": " + message);
}

function set_url_view_params(mode, start, end) {
    const url = new URL(window.location);
    url.searchParams.set('mode', mode);
    url.searchParams.set('start', Math.round(start * 0.001));
    url.searchParams.set('end', Math.round(end * 0.001));
    url.searchParams.delete('hours');

    if (show_instant_cop) url.searchParams.set('cop', 1);
    else url.searchParams.delete('cop');

    if (show_flow_rate) url.searchParams.set('flow', 1);
    else url.searchParams.delete('flow');

    if (show_defrost_and_loss) url.searchParams.set('cool', 1);
    else url.searchParams.delete('cool');

    if ($("#carnot_enable")[0].checked) url.searchParams.set('carnot', parseFloat($("#heatpump_factor").val()));
    else url.searchParams.delete('carnot');

    $('#permalink')[0].href = url.toString();
}

// -------------------------------------------------------------------------------
// EVENTS
// -------------------------------------------------------------------------------

// Switch to bargraph
$(".viewhistory").click(function () {
    $(".powergraph-navigation").hide();
    var timeWindow = 30 * DAY;
    // var end = (new Date()).getTime();
    var end = end_time * 1000;
    var start = end - timeWindow;
    if (start < (start_time * 1000)) start = start_time * 1000;

    if (last_bargraph_start && last_bargraph_end) {
        start = last_bargraph_start;
        end = last_bargraph_end;
    }

    viewmode = "bargraph";
    bargraph_load(start, end);
    bargraph_draw();
    $(".bargraph-navigation").show();
    $("#advanced-toggle").hide();
    $("#advanced-block").hide();
});

$('#placeholder').bind("plothover", function (event, pos, item) {
    if (item) {
        if (previousPoint != item.datapoint) {
            previousPoint = item.datapoint;

            $("#tooltip").remove();
            if (viewmode == "bargraph") {           
                bargraph_tooltip(item);
            } else if (viewmode == "powergraph") {
                powergraph_tooltip(item);
            }
        }
    } else $("#tooltip").remove();
});

// Auto click through to power graph
$('#placeholder').bind("plotclick", function (event, pos, item) {
    if (item && !panning && viewmode == "bargraph") {

        last_bargraph_start = bargraph_start;
        last_bargraph_end = bargraph_end;

        var z = item.dataIndex;
        view.start = data["heatpump_elec_kwhd"][z][0];
        view.end = view.start + DAY;
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
    }
});

$('#placeholder').bind("plotselected", function (event, ranges) {
    var start = ranges.xaxis.from;
    var end = ranges.xaxis.to;
    panning = true;

    if (viewmode == "bargraph") {
        bargraph_load(start, end);
        bargraph_draw();
    } else {
        view.start = start; 
        view.end = end;
        powergraph_load();
    }
    setTimeout(function () { panning = false; }, 100);
});

$("#clear-daily-data").click(function () {
    $.ajax({
        url: path + "app/cleardaily",
        data: { id: config.id, apikey: apikey },
        async: true,
        dataType: "json",
        success: function (result) {
            if (result.success) {
                alert("Daily data cleared, please refresh the page to reload data");
                app_log("INFO", "Daily data cleared");
            } else {
                alert("Failed to clear daily data");
                app_log("ERROR", "Failed to clear daily data");
            }
        }
    });
});

$("#show_dhw_temp").click(function () {
    if ($("#show_dhw_temp")[0].checked) {
        show_dhw_temp = true;
    } else {
        show_dhw_temp = false;
    }
    if (viewmode == "powergraph") {
        powergraph_draw();
    }
});
