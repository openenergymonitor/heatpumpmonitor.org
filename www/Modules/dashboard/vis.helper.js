// view class
// used by emoncms visualisations
// handles zooming and panning of time based data
var view =
{
  start:0,
  end:0,
  first_data:0,
  pan_speed:0.2,
  limit_x:true,
  
  // Used by multigraph
  'ymin':null,
  'ymax':null,
  'y2min':null,
  'y2max':null,
  'datetimepicker_previous':null,
  // ---

  'fullscreen':function ()
  {
    var ctrl = $("#graph-fullscreen");
    if (document.fullscreenElement) {
      ctrl.html("<i class='icon-resize-full'></i>");
      ctrl.attr("title", "Restore").removeClass("graph-min").addClass("graph-exp");
      document.exitFullscreen();
    } else {
      ctrl.html("<i class='icon-resize-small'></i>");
      ctrl.attr("title", "Expand").removeClass("graph-exp").addClass("graph-min");
      $('body').get(0).requestFullscreen().catch(err => {
        alert(`Error attempting to enable full-screen mode: ${err.message} (${err.name})`);
      });
    }
  },

  'zoomout':function ()
  {
    var time_window = this.end - this.start;
    var middle = this.start + time_window / 2;
    time_window = time_window * 2;
    
    this.start = middle - (time_window/2);
    this.end = middle + (time_window/2);
    
    if (this.limit_x && this.start<this.first_data) {
        this.start = this.first_data;
    }
    
    if (this.limit_x && this.end>this.now()) {
        this.end = this.now();
    }
  },

  'zoomin':function ()
  {
    var time_window = this.end - this.start;
    var middle = this.start + time_window / 2;
    time_window = time_window * 0.5;
    this.start = middle - (time_window/2);
    this.end = middle + (time_window/2);
  },

  'panright':function ()
  {
    var time_window = this.end - this.start;
    var shiftsize = time_window * view.pan_speed;
    var now = this.now();
    // Stop at now. A window already past now, such as daily bars to the end of today, stays put.
    if (this.end + shiftsize > now && this.limit_x) {
      shiftsize = Math.max(0, now - this.end);
    }
    this.start += shiftsize;
    this.end += shiftsize;
  },

  'panleft':function ()
  {
    var time_window = this.end - this.start;
    var shiftsize = time_window * view.pan_speed;
    if (this.start - shiftsize < this.first_data && this.limit_x) {
      shiftsize = this.start - this.first_data;
    }
    this.start -= shiftsize;
    this.end -= shiftsize;
  },

  'timewindow':function(time)
  {
    this.start = ((new Date()).getTime())-(3600000*24*time);    //Get start time
    this.end = (new Date()).getTime();    //Get end time
  },

  'calc_interval':function(npoints=600, min_interval=5)
  {
    var interval = Math.round(((this.end - this.start)*0.001)/npoints);
    var outinterval = this.round_interval(interval);
    
    if (outinterval<min_interval) outinterval = min_interval;
    if (!this.fixinterval) this.interval = outinterval;
    
    var intervalms = this.interval*1000;
    this.start = Math.floor(this.start / intervalms) * intervalms;
    this.end = Math.ceil(this.end / intervalms) * intervalms;
  },
  
  'round_interval':function(interval)
  {
      var outinterval = 5;
      if (interval>5) outinterval = 5;
      if (interval>10) outinterval = 10;
      if (interval>15) outinterval = 15;
      if (interval>20) outinterval = 20;
      if (interval>30) outinterval = 30;
      if (interval>60) outinterval = 60;
      if (interval>120) outinterval = 120;
      if (interval>180) outinterval = 180;
      if (interval>300) outinterval = 300;
      if (interval>600) outinterval = 600;
      if (interval>900) outinterval = 900;
      if (interval>1200) outinterval = 1200;
      if (interval>1800) outinterval = 1800;
      if (interval>3600*1) outinterval = 3600*1;
      if (interval>3600*2) outinterval = 3600*2;
      if (interval>3600*3) outinterval = 3600*3;
      if (interval>3600*4) outinterval = 3600*4;
      if (interval>3600*5) outinterval = 3600*5;
      if (interval>3600*6) outinterval = 3600*6;
      if (interval>3600*12) outinterval = 3600*12;
      if (interval>3600*24) outinterval = 3600*24;
      if (interval>3600*36) outinterval = 3600*36;
      if (interval>3600*48) outinterval = 3600*48;
      if (interval>3600*72) outinterval = 3600*72;

      return outinterval;
  },

  'now':function()
  {
    var date = new Date();
    return date.getTime();
  }
}

// Process feed data, returns stats:
// returns minval, maxval, diff, mean, stdev, time_elapsed, kwh, npoints, npointsnull
// used by graph module and myheatpump app
function stats(data)
{
    var sum = 0;
    var i=0;
    var minval = 0;
    var maxval = 0;
    var npoints = 0;
    var npointsnull = 0;
    
    var start_time = -1;
    var end_time = 0;
    
    var val = null;
    for (var z in data)
    {
        var val = data[z][1];                        // 1) only calculated based on present values
        // if (data[z][1]!=null) val = data[z][1];   // 2) if value is missing use last value
        
        if (val!=null) 
        {
            if (i==0) {
                maxval = val;
                minval = val;
            }
            if (val>maxval) maxval = val;
            if (val<minval) minval = val;
            sum += val;
            i++;
            
            if (start_time==-1) start_time = data[z][0]
            end_time = data[z][0]
        }
        if (data[z][1]==null) npointsnull++;

        npoints ++;
    }
    var mean = sum / i;
    sum = 0, i=0;
    for (z in data)
    {
        sum += (data[z][1] - mean) * (data[z][1] - mean);
        i++;
    }
    var stdev = Math.sqrt(sum / i);
    
    var time_elapsed = end_time - start_time;
    
    var kwh = (mean*time_elapsed*0.001)/3600000.0
    
    return {
        "minval":minval,
        "maxval":maxval,
        "diff":maxval-minval,
        "mean":mean,
        "stdev":stdev,
        "time_elapsed":time_elapsed,
        "kwh":kwh,
        "npointsnull":npointsnull,
        "npoints":npoints    
    }
}

// http://stackoverflow.com/questions/901115/how-can-i-get-query-string-values/901144#901144
var urlParams;
(window.onpopstate = function () {
    var match,
        pl = /\+/g, // Regex for replacing addition symbol with a space
        search = /([^&=]+)=?([^&]*)/g,
        decode = function (s) { return decodeURIComponent(s.replace(pl, " ")); },
        query = window.location.search.substring(1);

    urlParams = {};
    while (match = search.exec(query))
       urlParams[decode(match[1])] = decode(match[2]);
})();

// Creates a tooltip for use with flot graphs
function tooltip(x, y, contents, bgColour, borderColour="rgb(255, 221, 221)")
{
    var offset = 10; // use higher values for a little spacing between `x,y` and tooltip
    var elem = $('<div id="tooltip">' + contents + '</div>').css({
        position: 'absolute',
        color: "#000",
        display: 'none',
        'font-weight':'bold',
        border: '1px solid '+borderColour,
        padding: '2px',
        'background-color': bgColour,
        opacity: '0.8',
        'text-align': 'left'
    }).appendTo("body").fadeIn(200);

    var windowWidth = $(window).width();
    var elemWidth = elem.width();
    var elemHeight = elem.height();
    
    // Determine horizontal position based on cursor position
    var elemX;
    if (x < windowWidth / 2) {
        // Near left edge: position tooltip to the right of cursor
        elemX = x + offset;
    } else {
        // Near right edge: position tooltip to the left of cursor
        elemX = x - elemWidth - offset;
    }
    
    // Ensure tooltip doesn't go off screen
    if (elemX < 0) { elemX = 0; }
    if (elemX + elemWidth > windowWidth) { elemX = windowWidth - elemWidth; }
    
    // Position tooltip above cursor
    var elemY = y - elemHeight - offset;
    if (elemY < 0) { elemY = 0; }
    
    elem.css({
        top: elemY,
        left: elemX
    });
}

// Take a time string in the format "dd/mm/yyyy hh:mm:ss" and return a unix timestamp
function parse_timepicker_time(timestr){
    // Check for date and time parts
    var tmp = timestr.split(" ");
    if (tmp.length!=2) return false;
    // Split date
    var date = tmp[0].split("/");
    if (date.length!=3) return false;
    // Split time
    var time = tmp[1].split(":");
    if (time.length!=3) return false;
    // Convert to unix timestamp
    return new Date(date[2],date[1]-1,date[0],time[0],time[1],time[2],0).getTime() / 1000;
}


// USED BY THE GRAPH MODULE 
// Provided for compatibility
function parseTimepickerTime(timestr){
  return parse_timepicker_time(timestr);
}

// Convert a unix timestamp into a date string of format: Mon Jan 01, 00:00
// Used for tooltips
function tooltip_date(time_ms){
    var months = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
    var days = ["Sun","Mon","Tue","Wed","Thu","Fri","Sat"];

    var d = new Date(time_ms);
    var date = days[d.getDay()]+", "+months[d.getMonth()]+" "+d.getDate();
    
    var h = d.getHours();
    if (h<10) h = "0"+h;
    var m = d.getMinutes();
    if (m<10) m = "0"+m;
    var time = h+":"+m;

    return date+", "+time;
}

// Time of day as HH:MM from a Date, for tooltips
function tooltip_time(date){
    var h = date.getHours();
    if (h<10) h = "0"+h;
    var m = date.getMinutes();
    if (m<10) m = "0"+m;
    return h+":"+m;
}

// Chart tooltip at page position x, y. Rows as [label, value, units, swatch colour].
// Shown to the left of the cursor when it would overflow the right edge of #placeholder.
// Apps that define their own show_tooltip after this file replace it.
function show_tooltip(x, y, values){
    var tooltip = $('#tooltip');
    if (!tooltip[0]) {
        tooltip = $('<div id="tooltip"></div>')
            .css({
                position: "absolute",
                display: "none",
                border: "1px solid #545454",
                padding: "8px",
                "background-color": "#333",
            })
            .appendTo("body");
    }

    tooltip.html('');
    var table = $('<table/>').appendTo(tooltip);

    values.forEach(function(value){
        var row = $('<tr class="tooltip-item"/>').appendTo(table);
        var swatch = value[3] ? '<span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:'+value[3]+';margin-right:6px"></span>' : '';
        $('<td style="padding-right: 8px">'+swatch+'<span class="tooltip-title">'+value[0]+'</span></td>').appendTo(row);
        $('<td><span class="tooltip-value">'+value[1]+'</span> <span class="tooltip-units">'+value[2]+'</span></td>').appendTo(row);
    });

    tooltip.css({ left: x, top: y }).show();

    var placeholder = $('#placeholder');
    var chart_right = placeholder.offset().left + placeholder.outerWidth();
    if (x + tooltip.outerWidth() > chart_right) {
        tooltip.css({ left: x - tooltip.outerWidth() - 20 });
    }
}

function hide_tooltip(){
    $('#tooltip').hide();
}

// Energy colour from the theme tokens, e.g. energy_color("solar") for --ec-energy-solar.
// Flot draws on a canvas, so the value is read here rather than set as a CSS variable.
function energy_color(name){
    return getComputedStyle(document.documentElement).getPropertyValue("--ec-energy-" + name).trim();
}

// True on a device with touch and no mouse like pointer. Used to gate the
// flot 5 touch navigation options so a mouse keeps drag selection.
function is_touch_primary(){
    var touch = ('ontouchstart' in window) || navigator.maxTouchPoints > 0 ||
        (window.matchMedia && window.matchMedia('(any-pointer: coarse)').matches);
    var mouse = window.matchMedia && (window.matchMedia('(any-hover: hover)').matches ||
        window.matchMedia('(any-pointer: fine)').matches);
    return !!touch && !mouse;
}

// Flot 5 plot events are dom listeners. An app that binds them again on a
// change of view keeps them here, so plot_unbind can remove the old ones.
var plot_handlers = {};
function plot_unbind(id){
    var el = document.getElementById(id);
    for (var e in plot_handlers) el.removeEventListener(e, plot_handlers[e]);
    plot_handlers = {};
}

// Series colour by index, as flot 0.8 assigned it. Flot 5 shifts its palette
// by the number of series that carry a fixed colour, so an index is resolved
// here instead. Indexes past the palette repeat it lighter or darker.
var flot_palette = ["#edc240", "#afd8f8", "#cb4b4b", "#4da74d", "#9440ed"];
function flot_color(index){
    var variation = 0;
    for (var i = flot_palette.length; i <= index; i += flot_palette.length) {
        if (variation >= 0) variation = (variation < 0.5) ? -variation - 0.2 : 0;
        else variation = -variation;
    }
    var hex = flot_palette[index % flot_palette.length];
    var rgb = [1, 3, 5].map(function(p){
        return Math.min(255, parseInt(parseInt(hex.substr(p, 2), 16) * (1 + variation)));
    });
    return "rgb(" + rgb.join(",") + ")";
}

// Axis label colour for Flot font options: muted text colour of the app theme
function flot_font_color(){
    var page = document.querySelector(".app-page") || document.body;
    return getComputedStyle(page).getPropertyValue("--bs-secondary-color").trim() || "#999";
}

// Grid lines: Flot draws them at 0.22 alpha of grid.color. Raised here for
// contrast. An explicit grid.tickColor is kept.
// Flot also draws grid lines for the inner axis on each side, so a right y
// axis adds a second set. Only the first shown axis in each direction keeps
// them, unless an axis sets gridLines: true.
if (window.Flot) Flot.plugins.push({
    name: "app-grid",
    init: function(plot){
        plot.hooks.processOptions.push(function(plot, options){
            var grid = options.grid;
            var base = Flot.color.parse(grid.color);
            if (grid.tickColor === base.clone().scale("a", 0.22).toString()) {
                grid.tickColor = base.scale("a", 0.35).toString();
            }
            // Y labels sized to fit and less space between y axes. Narrow
            // charts also drop the y tick marks.
            var narrow = plot.getPlaceholder().clientWidth < 576;
            grid.axisMargin = narrow ? 2 : 4;
            options.yaxes.forEach(function(axis){
                axis.labelWidth = null;
                if (narrow && axis.showTicks == null) axis.showTicks = false;
            });
        });
        plot.hooks.drawBackground.push(function(plot){
            [plot.getXAxes(), plot.getYAxes()].forEach(function(axes){
                var first = true;
                axes.forEach(function(axis){
                    if (!axis.show || !axis.gridLines) return;
                    if (!first && axis.options.gridLines !== true) axis.gridLines = false;
                    first = false;
                });
            });
        });
    }
});

// Touch pan and pinch zoom on time charts, as the graph module. Drag select
// is off on touch. When a gesture ends the new window is sent as a
// plotselected event, so each app reloads as for a selection. Added first so
// its options are set before the Flot touch plugins read them.
if (window.Flot && is_touch_primary()) Flot.plugins.unshift({
    name: "app-touch",
    init: function(plot){
        plot.hooks.processOptions.push(function(plot, options){
            // A new plot drops a gesture window from the plot before it, e.g.
            // a tap on a bar that also panned it slightly before opening a day
            var placeholder = plot.getPlaceholder();
            clearTimeout(placeholder.app_touch_timer);
            if (!options.pan || options.xaxes[0].mode != "time") return;
            if (options.selection) options.selection.mode = null;
            Object.assign(options.zoom, { interactive: true, enableTouch: true, amount: 1.5 });
            Object.assign(options.pan, { interactive: true, enableTouch: true, touchMode: "smartLock", frameRate: 60 });
            Object.assign(options.recenter, { interactive: true, enableTouch: true });
            options.yaxes.forEach(function(axis){
                Object.assign(axis, { axisPan: false, plotPan: false, axisZoom: false, plotZoom: false });
            });
            // Gestures stay in the chart, no page scroll or browser zoom
            placeholder.style.touchAction = "none";
            placeholder.style.overscrollBehavior = "contain";
            // One handler per placeholder. Flot does not shut down a plot
            // that is drawn over, so the plot is taken from the event.
            if (placeholder.app_touch_bound) return;
            placeholder.app_touch_bound = true;
            var on_window = function(event){
                // Flot sends a zero pan for a tap that moves under its snap
                // distance. Window is unchanged, so no reload.
                var delta = event.detail[1];
                if (event.type == "plotpan" && delta && !delta.x && !delta.left) {
                    clearTimeout(placeholder.app_touch_timer);
                    return;
                }
                var xaxis = event.detail[0].getAxes().xaxis;
                var range = { from: xaxis.min, to: xaxis.max };
                clearTimeout(placeholder.app_touch_timer);
                placeholder.app_touch_timer = setTimeout(function(){
                    placeholder.dispatchEvent(new CustomEvent("plotselected", { detail: [{ xaxis: range }] }));
                }, 250);
            };
            placeholder.addEventListener("plotpan", on_window);
            placeholder.addEventListener("plotzoom", on_window);
        });
    }
});

// Click on a legend entry to hide or show its series. Flot 5 draws the legend
// as static svg, so this is wired up here. Hidden labels are kept across
// redraws, so call this after every Flot.plot on the chart. left, when given,
// moves the legend to that many pixels from the left edge of the placeholder.
var plot_hidden_labels = {};
function plot_legend(plot, left){
    var legend = plot.getPlaceholder().querySelector('.legend');
    if (!legend) return;
    if (left !== undefined) legend.style.left = left + "px";
    legend.style.pointerEvents = "auto";

    // Legend entries are the series with a label, or with none set at all
    var series = plot.getData();
    var entries = series.filter(function(s){ return !("label" in s) || s.label; });
    var groups = legend.querySelectorAll('svg > g');
    for (var i = 0; i < groups.length; i++) {
        (function(group, s){
            if (!s) return;
            var fresh = group.cloneNode(true);
            fresh.style.cursor = "pointer";
            fresh.style.opacity = plot_hidden_labels[s.label] ? 0.4 : 1;
            group.parentNode.replaceChild(fresh, group);
            fresh.addEventListener('click', function(){
                plot_hidden_labels[s.label] = !plot_hidden_labels[s.label];
                plot_apply_hidden(plot);
                plot_legend(plot, left);
            });
        })(groups[i], entries[i]);
    }
    plot_apply_hidden(plot);
}

// Hide the series whose label is in plot_hidden_labels and show the rest,
// keeping what each series set so it can be restored.
function plot_apply_hidden(plot){
    var series = plot.getData();
    var changed = false;
    for (var i = 0; i < series.length; i++) {
        var s = series[i];
        if (!s.shown) s.shown = { lines: s.lines.show, points: s.points.show, bars: s.bars.show };
        var hide = !!plot_hidden_labels[s.label];
        var lines = hide ? false : s.shown.lines;
        if (s.lines.show !== lines) {
            s.lines.show = lines;
            s.points.show = hide ? false : s.shown.points;
            s.bars.show = hide ? false : s.shown.bars;
            changed = true;
        }
    }
    if (changed) {
        plot.setData(series);
        plot.draw();
    }
}

// -- Compact card layout: time bar, status and legend ------------------------
// Markup in Lib/timebar.php and Lib/timebar_manual.php, classes in app-kit.css.

// Hide or show range options. iOS Safari lists hidden options, so they are
// disabled as well.
function timebar_hide(selector, hide){
    $("#time-select").find(selector).prop({ hidden: !!hide, disabled: !!hide });
}

// Sync the time bar after each redraw: range select, window dates and the Now
// button. daily hides the ranges under a week and shows dates without times.
function timebar_update(daily){
    var $select = $("#time-select");
    timebar_hide("option[value=1], option[value=3], option[value=6], option[value=24]", daily);
    // Nearest listed range within 5%, so a month of whole days reads "1 month".
    // Other windows show Custom.
    var hours = Math.round((view.end - view.start) / 3600000);
    var $option = $select.find("option").filter(function(){
        var h = parseFloat(this.value);
        return !this.hidden && h > 0 && Math.abs(h - hours) <= h * 0.05;
    }).first();
    $select.val($option.length ? $option.val() : "");

    // Dates with the year for daily bars and windows over two months
    var dates_only = daily || (view.end - view.start) > 60 * 86400000;
    var opts = dates_only
        ? { day: "numeric", month: "short", year: "numeric" }
        : { day: "numeric", month: "short", hour: "2-digit", minute: "2-digit", hourCycle: "h23" };
    var fmt = function(t){ return new Date(t).toLocaleString(undefined, opts); };
    $("#window-label").text(fmt(view.start) + " – " + fmt(view.end));

    $("#time-now").toggle(!daily && (Date.now() - view.end) > 300000);
}

// Start and End fields in place of the time bar. on_change runs after either
// field sets the window.
function timebar_manual(on_change){
    var set = function(key){
        return function(date){
            if (!date) { alert("Please enter a valid " + key + " date."); return; }
            var t = date.getTime();
            if (key == "start" ? t >= view.end : t <= view.start) { alert("Start date must be before the end date."); return; }
            view[key] = t;
            on_change();
        };
    };
    var start = DateTimePicker.attach(document.getElementById("request-start"), { onChange: set("start") });
    var end = DateTimePicker.attach(document.getElementById("request-end"), { onChange: set("end") });

    $("#time-manual-open").click(function(){
        start.setDate(new Date(view.start));
        end.setDate(new Date(view.end));
        $("#graph-nav").addClass("d-none");
        $("#graph-nav-manual").removeClass("d-none");
    });
    $("#time-manual-close").click(function(){
        $("#graph-nav-manual").addClass("d-none");
        $("#graph-nav").removeClass("d-none");
    });
}

// Now button: move the window to end now, keeping its length. on_change gets
// the length in ms, so the app can decide whether to follow live data.
function timebar_now(on_change){
    $("#time-now").click(function(){
        var length = view.end - view.start;
        view.end = Date.now();
        view.start = view.end - length;
        on_change(length);
    });
}

// Header status from the time of the latest value, in seconds. Live when
// under 5 minutes old.
function live_status_update(time){
    if (!time) return;
    var age = Math.max(0, Date.now() / 1000 - time);
    var live = age < 300;
    var text = "Live";
    if (!live) {
        if (age < 3600) text = Math.round(age / 60) + " min";
        else if (age < 172800) text = Math.round(age / 3600) + " h";
        else text = Math.round(age / 86400) + " days";
        text = "Updated " + text + " ago";
    }
    $("#live-status").toggleClass("is-live", live).attr("title", "Latest value " + new Date(time * 1000).toLocaleString());
    $("#live-status .app-status-text").text(text);
}

// Legend below the chart from the drawn series. Series on the second axis
// are drawn as lines.
// Optional on_grow is called when the legend is taller than chart_legend_height
// last returned, so the app can resize the chart.
function chart_legend(series, on_grow){
    var items = series.filter(function(s){ return s.label; }).map(function(s){
        var mark = s.yaxis == 2 ? "app-legend-line" : "app-legend-swatch";
        return '<span class="app-legend-item"><span class="' + mark + '" style="background:' + s.color + '"></span>' + s.label + '</span>';
    });
    var legend = $("#chart-legend");
    legend.html(items.join(""));

    if (on_grow && legend.width() == chart_legend_fit.width && legend.outerHeight(true) > chart_legend_fit.height) {
        chart_legend_fit.height = legend.outerHeight(true);
        on_grow();
    }
}

// Tallest legend seen at the current width, for the chart height. Kept when the
// legend gets shorter, so the chart keeps its size between views.
var chart_legend_fit = { width: 0, height: 0 };
function chart_legend_height(){
    var legend = $("#chart-legend");
    if (legend.width() != chart_legend_fit.width) chart_legend_fit = { width: legend.width(), height: 0 };
    chart_legend_fit.height = Math.max(chart_legend_fit.height, legend.outerHeight(true));
    return chart_legend_fit.height;
}

// Time bar view switch on phones: clicks the Power or Daily button that is not active
$(document).on("click", ".app-view-switch", function(){
    $(this).closest(".app-navbar").find(".viewmode-toggle > .btn:not(.active)").first().trigger("click");
});
