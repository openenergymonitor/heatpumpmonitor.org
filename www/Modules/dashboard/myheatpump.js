// heatpumpmonitor.org version of the emoncms MyHeatpump app
// (emoncms/app apps/OpenEnergyMonitor/myheatpump). Feed data comes from the
// timeseries/* and system/stats/* endpoints in place of the emoncms feed API.
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
    "app_name": { "type": "value", "name": "App title", "default": "My Heatpump", "optional": true, "description": "Enter custom title for app" },
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

    // Hybrid Boiler
    'boiler_heat': { "type": "feed", "autoname": "boiler_heat", "optional": true, "description": "Boiler heat output in watts" },

    // Solar
    "solar_elec_kwh": { "type": "feed", "autoname": "solar_elec_kwh", "optional": true, "description": "Cumulative solar generation kWh, used by heat demand" },

    // Other
    "starting_power": { "type": "value", "default": 150, "name": "Starting power", "description": "Starting power of heatpump in watts" },
    "auto_detect_cooling":{"type":"checkbox", "default":false, "name": "Auto detect cooling", "description":"Auto detect summer cooling if cooling status feed is not present"},
    "enable_process_daily":{"type":"checkbox", "default":true, "name": "Enable daily pre-processor", "description":"Enable split between water and space heating in daily view"},
    "start_date": { "type": "value", "default": 0, "name": "Start date", "description": "Start date for all time values (unix timestamp)" },
};

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
var chart_max_height = 480;   // chart height follows the window up to this
var updaterinst = false;
var elec_enabled = false;
var heat_enabled = false;
var immersion_enabled = false;
var boiler_enabled = false;
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
var bargraph_period = false; // days or "all", false after zoom or pan

var realtime_cop_div_mode = "30min";

var months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

// duration contants (milliseonds)
const MINUTE = 60 * 1000;
const HOUR = 60 * MINUTE;
const DAY = 24 * HOUR;

// Feeds available for this system, keyed by app feed name, with start and end times
$.ajax({
    url: path + "timeseries/available",
    data: { id: config.id, readkey: apikey },
    async: true,
    dataType: "json",
    success: function (result) {
        if (result == null || result.feeds == undefined) {
            console.log("No timeseries available");
            return;
        }

        feeds = result.feeds;

        if (result.starting_power != undefined) {
            starting_power = result.starting_power;
        }
        if (result.start_time != undefined) {
            start_time = result.start_time;
        }
        if (result.auto_detect_cooling != undefined) {
            config.app.auto_detect_cooling.value = result.auto_detect_cooling;
        }

        show();
    }
});

function init() {
}

function show() {
    // Daily data always comes from the heatpumpmonitor daily stats.
    // App title is set from the system details in myheatpump.php.
    config.app.enable_process_daily.value = 1;
    config.app.starting_power.value = 150;

    // Apply starting_power
    $("#starting_power").val(config.app.starting_power.value);

    if (!config.app.enable_process_daily.value) {
        $(".bargraph_mode").hide();

        bargraph_mode = "combined";
    } else {
        $(".bargraph_mode").show();
    }

    // -------------------------------------------------------------------------------
    // Configurations
    // -------------------------------------------------------------------------------
    if (feeds["heatpump_elec_kwh"] != undefined) elec_enabled = true;
    if (feeds["heatpump_heat"] != undefined && feeds["heatpump_heat_kwh"] != undefined) heat_enabled = true;
    if (feeds["immersion_elec"] != undefined) immersion_enabled = true;
    if (feeds["boiler_heat"] != undefined) boiler_enabled = true;

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
        if (feeds["heatpump_elec_kwh"].start_time > start_time) start_time = feeds["heatpump_elec_kwh"].start_time;
        if (feeds["heatpump_elec_kwh"].end_time > end_time) end_time = feeds["heatpump_elec_kwh"].end_time;
    }

    if (heat_enabled) {
        if (feeds["heatpump_heat_kwh"].start_time > start_time) start_time = feeds["heatpump_heat_kwh"].start_time;
        if (feeds["heatpump_heat_kwh"].end_time > end_time) end_time = feeds["heatpump_heat_kwh"].end_time;

        $("#show_negative_heat_bound").show();
    }

    var alltime_start_time = start_time;
    var config_start_date = config.app.start_date.value * 1;
    if (config_start_date > alltime_start_time) {
        alltime_start_time = config_start_date;
        var d = new Date(alltime_start_time * 1000);
    } else {
        var d = new Date(start_time * 1000);
    }
    totals_since_title = "Total since " + d.getDate() + " " + months[d.getMonth()] + " " + d.getFullYear();
    $("#all_time_history_title").html(totals_since_title);

    var date = new Date();
    var now = date.getTime();

    end = end_time * 1000;

    // Live values only when the data is recent. The header status gives its age.
    live_status_update(end_time);
    $("#live_table").toggle(now - end <= HOUR);
    $(".hp-return-part").toggle(feeds["heatpump_returnT"] != undefined);

    resize();

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
        if (urlParams.dhw) {
            $("#show_dhw_temp").click();
            show_dhw_temp = true;
        }
        if (urlParams.carnot) {
            $("#carnot_enable")[0].click();
            $("#heatpump_factor").val(urlParams.carnot);
        }
        if (urlParams.cool) {
            $("#show_defrost_and_loss").click();
            show_defrost_and_loss = true;
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
        if (urlParams.start == undefined && urlParams.end == undefined) bargraph_period = "30";

        bargraph_load(start, end);
        bargraph_draw();

        // Daily data is processed on the server
        $("#overlay_text").html("");
        $("#overlay").hide();

        $("#advanced-toggle").hide();
    }

    // LOOP
    progtime = now;
    updater();
    updaterinst = setInterval(updater, 10000);

    // Load all time and last 365 day totals from pre-processed daily data
    if (config.app.enable_process_daily.value) {
        ["all", "last365"].forEach(function (period) {
            $.ajax({
                url: path + "system/stats/" + period,
                data: { id: config.id, readkey: apikey },
                async: true,
                dataType: "json",
                success: function (result) {
                    result = result[config.id];
                    if (result == undefined) return;
                    totals[period] = result;
                    alltime_totals_draw();
                }
            });
        });
    }

    $(".ajax-loader").hide();
}

// Totals card: all time or last 365 days, for the mode selected above the daily
// chart. system/stats/all and system/stats/last365 give totals per mode with the
// same prefixes as bargraph_mode.
var totals = { all: null, last365: null };
var totals_period = "all";
var totals_since_title = "All time history";
var alltime_mode_names = { running: "When running", space: "Space heating", water: "Water heating", cooling: "Cooling" };

function alltime_totals_draw() {
    var t = totals[totals_period];
    $("#all_time_history_title").html(totals_period == "last365" ? "Last 365 days" : totals_since_title);
    $(".total-period").each(function () {
        $(this).toggleClass("active", $(this).attr("period") == totals_period);
    });
    if (!t) return;

    var mode = t[bargraph_mode + "_elec_kwh"] != undefined ? bargraph_mode : "combined";
    var elec = t[mode + "_elec_kwh"];
    var heat = t[mode + "_heat_kwh"];
    var cop = t[mode + "_cop"];

    $("#total_elec").html(elec != null ? Math.round(elec) : "-");
    $("#total_heat").html(heat != null ? Math.round(heat) : "-");
    $("#total_cop").html(cop != null ? cop.toFixed(2) : "-");
    $("#total_mode").html(alltime_mode_names[mode] ? "&middot; " + alltime_mode_names[mode] : "");

    // Boiler total is for the whole system, shown with All
    var show_boiler = boiler_enabled && mode == "combined" && t.boiler_kwh != undefined;
    if (show_boiler) $("#total_boiler").html(Math.round(t.boiler_kwh));
    $("#boiler_total_bound").toggle(show_boiler);
}

$(".total-period").click(function () {
    totals_period = $(this).attr("period");
    alltime_totals_draw();
});

function clear() {
    clearInterval(updaterinst);
}

function updater() {
    $.ajax({
        url: path + "timeseries/values",
        data: { id: config.id, feeds: "heatpump_elec,heatpump_heat,heatpump_flowT,heatpump_returnT,heatpump_elec_kwh,heatpump_heat_kwh", readkey: apikey },
        async: true,
        dataType: "json",
        success: function (result) {
        if (result === null || result.success === false) { return; }

        // Latest values, kept apart from the feed list loaded by show()
        let feeds = result;

        if (feeds["heatpump_elec"] != undefined) $("#heatpump_elec").html(Math.round(feeds["heatpump_elec"].value));
        if (feeds["heatpump_heat"] != undefined) $("#heatpump_heat").html(Math.round(feeds["heatpump_heat"].value));
        if (feeds["heatpump_flowT"] != undefined) $("#heatpump_flowT").html((1 * feeds["heatpump_flowT"].value).toFixed(1));
        if (feeds["heatpump_returnT"] != undefined) $("#heatpump_returnT").html((1 * feeds["heatpump_returnT"].value).toFixed(1));

        // Header status from the latest electric or heat value
        var latest = feeds["heatpump_elec"] || feeds["heatpump_heat"] || feeds["heatpump_elec_kwh"];
        if (latest != undefined) live_status_update(latest.time);

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

// Mean COP over the last duration seconds, shown in the live row
function get_average_cop(feeds, duration) {
    if (feeds["heatpump_elec"] == undefined) return;

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
            limitinterval: 0,
            readkey: apikey
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


    var placeholder_bound = $('#placeholder_bound');
    var placeholder = $('#placeholder');

    var width = placeholder_bound.width();

    // Viewport height less content above the chart (measured from the top of the page,
    // independent of scroll position), the legend, the chart foot and the all time history
    // card. Legend height is the tallest seen at this width and the foot a fixed allowance,
    // so the chart keeps its size when the view changes.
    var chart_foot = 48;
    var history_card = $('#all_time_history_title').closest('.app-card').outerHeight(true);
    var offset_top = placeholder_bound.offset().top;
    var height = window.innerHeight - offset_top - chart_legend_height() - chart_foot - history_card - 16;

    // heatpumpmonitor.org: capped so the chart does not stretch on tall windows
    if (height > chart_max_height) height = chart_max_height;
    // min size to avoid flot errors
    if (height < 250) height = 250;

    placeholder.width(width);
    placeholder_bound.height(height);
    placeholder.height(height);


    if (viewmode == "bargraph") {
        bargraph_draw();
    } else {
        powergraph_draw();
    }
}
// Redraw when the window size changes. emoncms fires a debounced "window.resized"
// event from its theme; heatpumpmonitor.org has none, so the window resize event is
// debounced here.
$(function () {
    var last_width = window.innerWidth;
    var last_height = window.innerHeight;
    var resize_timeout = false;

    window.addEventListener('resize', function () {
        clearTimeout(resize_timeout);
        resize_timeout = setTimeout(function () {
            // Touch devices: skip small height-only changes from the browser address bar
            // showing and hiding on scroll, and from the on-screen keyboard
            var touch = window.matchMedia('(pointer: coarse)').matches;
            if (touch && window.innerWidth == last_width && Math.abs(window.innerHeight - last_height) < 150) return;
            if (window.innerWidth == last_width && window.innerHeight == last_height) return;
            last_width = window.innerWidth;
            last_height = window.innerHeight;
            resize();
        }, 100);
    });
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

    if (show_dhw_temp) url.searchParams.set('dhw', 1);
    else url.searchParams.delete('dhw');

    if (show_defrost_and_loss) url.searchParams.set('cool', 1);
    else url.searchParams.delete('cool');

    if ($("#carnot_enable")[0].checked) url.searchParams.set('carnot', parseFloat($("#heatpump_factor").val()));
    else url.searchParams.delete('carnot');

    $('#permalink')[0].href = url.toString();
}

// -------------------------------------------------------------------------------
// TIME BAR
// -------------------------------------------------------------------------------

// Time bar after a redraw. Daily bars use their own window. Period and time
// buttons are marked active when they match the window.
function hp_timebar_sync() {
    var daily = viewmode == "bargraph";
    if (daily) {
        view.start = bargraph_start;
        view.end = bargraph_end;
    }
    timebar_update(daily);
    $(".hp-period").each(function () {
        $(this).toggleClass("active", daily && $(this).attr("days") == bargraph_period);
    });
    var hours = (view.end - view.start) / HOUR;
    $(".hp-time").each(function () {
        var h = $(this).attr("hours");
        $(this).toggleClass("active", !daily && Math.abs(h - hours) <= h * 0.05);
    });
    $(".bargraph-navigation").toggle(daily);
    $(".powergraph-navigation").toggle(!daily);
    $("#heatdemand-toggle").toggle(daily);
    $("#heatdemand-block").toggle(daily && $("#heatdemand-toggle").html() == "HIDE HEAT DEMAND");
}

// Daily view periods. Day opens the power view for the last 24 hours.
$(".hp-period").click(function () {
    var days = $(this).attr("days");
    var end = (new Date()).getTime();
    if (days == "day") {
        hp_open_power(end - DAY, end);
        return;
    }
    var start = days == "all" ? start_time * 1000 : end - days * DAY;
    if (start < start_time * 1000) start = start_time * 1000;
    bargraph_period = days;
    bargraph_load(start, end);
    bargraph_draw();
});

// Power view windows ending now
$(".hp-time").click(function () {
    var end = (new Date()).getTime();
    hp_open_power(end - $(this).attr("hours") * HOUR, end);
});

// Zoom and pan act on the daily bars or the power graph
function hp_navigate(move) {
    if (viewmode == "bargraph") {
        view.start = bargraph_start;
        view.end = bargraph_end;
        move();
        bargraph_period = false;
        bargraph_load(view.start, view.end);
        bargraph_draw();
    } else {
        move();
        powergraph_load();
    }
}
$("#zoomout").click(function () { hp_navigate(function () { view.zoomout(); }); });
$("#zoomin").click(function () { hp_navigate(function () { view.zoomin(); }); });
$("#right").click(function () { hp_navigate(function () { view.panright(); }); });
$("#left").click(function () { hp_navigate(function () { view.panleft(); }); });

// Start and End fields, from Lib/vis.helper.js
// hp_navigate would reset view to the bar window first, so the typed dates are loaded directly
timebar_manual(function () {
    if (viewmode == "bargraph") {
        bargraph_period = false;
        bargraph_load(view.start, view.end);
        bargraph_draw();
    } else {
        powergraph_load();
    }
});

// -------------------------------------------------------------------------------
// EVENTS
// -------------------------------------------------------------------------------

// Switch to bargraph
$(".viewhistory").click(function () {
    if (viewmode == "bargraph") return;
    var timeWindow = 30 * DAY;
    // var end = (new Date()).getTime();
    var end = end_time * 1000;
    var start = end - timeWindow;
    if (start < (start_time * 1000)) start = start_time * 1000;

    if (last_bargraph_start && last_bargraph_end) {
        start = last_bargraph_start;
        end = last_bargraph_end;
    }

    hp_show_daily();
    bargraph_load(start, end);
    bargraph_draw();
});

// Daily view controls, before the bars load
function hp_show_daily() {
    viewmode = "bargraph";
    $(".bargraph-navigation").show();
    $("#advanced-toggle").hide();
    $("#advanced-block").hide();
}

document.getElementById('placeholder').addEventListener("plothover", function (event) {
    var pos = event.detail[0], item = event.detail[1];
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
document.getElementById('placeholder').addEventListener("plotclick", function (event) {
    var pos = event.detail[0], item = event.detail[1];
    if (item && !panning && viewmode == "bargraph") {
        var z = item.dataIndex;
        var itemTime = item.datapoint[0];

        // if timestamp map available, use it to find correct index in daily data arrays
        if (daily_data_timestamp_map != undefined && daily_data_timestamp_map[itemTime] != undefined) {
            z = daily_data_timestamp_map[itemTime];
        }

        hp_open_day(data["heatpump_elec_kwhd"][z][0]);
    }
});

// Open the power view for the day starting at time (ms)
function hp_open_day(time) {
    hp_open_power(time, time + DAY);
}

// Open the power view for a window in ms, from either view
function hp_open_power(start, end) {
    if (viewmode == "bargraph") {
        last_bargraph_start = bargraph_start;
        last_bargraph_end = bargraph_end;
    }

    view.start = start;
    view.end = end;
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

document.getElementById('placeholder').addEventListener("plotselected", function (event) {
    var ranges = event.detail[0];
    var start = ranges.xaxis.from;
    var end = ranges.xaxis.to;
    panning = true;

    if (viewmode == "bargraph") {
        bargraph_period = false;
        bargraph_load(start, end);
        bargraph_draw();
    } else {
        view.start = start; 
        view.end = end;
        powergraph_load();
    }
    setTimeout(function () { panning = false; }, 100);
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
