// -------------------------------------------------------------------------------
// HEAT DEMAND
// Daily mean heat output against inside - outside temperature difference, drawn
// in the Sizing tile of the Measurements card (myheatpump_measurements.js).
// Simple view: every day of every year with the nightly fit (heat_loss_htc,
// heat_loss_base_dT and heat_loss_design from
// scripts/measurements/system_measurements.php), so it matches the Estimated
// value on the sizing bar. Explore: the tile spans the card and adds the period,
// heat, design and filter controls, fitted here with the same method.
// -------------------------------------------------------------------------------

var heatdemand_colors = ["#4682b4", "#e8a33c", "#5bb35b", "#c0504d", "#8064a2", "#4bacc6"];
var heatdemand_plot = null;
// Numeric fields from the system form (system_meta): design heat loss (kW at the
// design temperature), design outside temperature, badge output (kW) and the heat
// pump database model for max output tests. Null when empty.
var system_form = (function () {
    function num(key) {
        var v = system_meta[key];
        return v === null || v === undefined || v === "" || !isFinite(v) ? null : v * 1;
    }
    return { heat_loss: num("heat_loss"), design_temp: num("design_temp"), hp_output: num("hp_output"), heatpump_model_id: num("heatpump_model_id") };
})();

// Approved max output tests for this heat pump model, heat in kW. Null until loaded or when there are none
var heatdemand_maxcap = null;

// Daily stats for the tool, all years. Null until the card first opens, then
// { loading: true }, and { rows, room } or { message }
var heatdemand_data = null;

// Days with less data than this (s) are left out, as the measurement script
var HEATDEMAND_MIN_LENGTH = 64800;
// Room temperature with no room temperature feed, as the measurement script's ROOM_T
var HEATDEMAND_ROOM_T = 20;

// Explore controls, kept while the card is drawn again. outsideT starts at the
// form's design temperature, else the measured 99.6% one
var heatdemand_state = { open: false, info: false, period: "all", mode: "combined", outsideT: null,
    roomT: HEATDEMAND_ROOM_T, fixed_room: false, min_dT: 0, min_quality: 0, split: "none" };
// Set by a ?heatdemand=1 link before the card is drawn: open it with the tool expanded
var heatdemand_autoopen = false;

// Ordinary least squares with intercept. X is an array of rows [x1, x2, ...]
function ols(X, y) {
    var n = y.length, k = X[0].length + 1;
    if (n <= k) return null;

    // Normal equations A = XᵀX, b = Xᵀy
    var A = [], b = [];
    for (var i = 0; i < k; i++) { A.push(new Array(k).fill(0)); b.push(0); }
    for (var r = 0; r < n; r++) {
        var row = [1].concat(X[r]);
        for (var i = 0; i < k; i++) {
            b[i] += row[i] * y[r];
            for (var j = 0; j < k; j++) A[i][j] += row[i] * row[j];
        }
    }

    // Invert A by Gauss-Jordan elimination
    var inv = A.map(function (row, i) { return row.map(function (_, j) { return i == j ? 1 : 0; }); });
    for (var c = 0; c < k; c++) {
        var p = c;
        for (var r = c + 1; r < k; r++) if (Math.abs(A[r][c]) > Math.abs(A[p][c])) p = r;
        if (Math.abs(A[p][c]) < 1e-12) return null;
        [A[c], A[p]] = [A[p], A[c]];
        [inv[c], inv[p]] = [inv[p], inv[c]];
        var d = A[c][c];
        for (var j = 0; j < k; j++) { A[c][j] /= d; inv[c][j] /= d; }
        for (var r = 0; r < k; r++) {
            if (r == c) continue;
            var f = A[r][c];
            for (var j = 0; j < k; j++) { A[r][j] -= f * A[c][j]; inv[r][j] -= f * inv[c][j]; }
        }
    }

    var beta = inv.map(function (row) { return row.reduce(function (s, v, j) { return s + v * b[j]; }, 0); });

    var mean = y.reduce(function (s, v) { return s + v; }, 0) / n;
    var sse = 0, sst = 0;
    for (var r = 0; r < n; r++) {
        var fit = beta[0];
        for (var j = 1; j < k; j++) fit += beta[j] * X[r][j - 1];
        sse += (y[r] - fit) ** 2;
        sst += (y[r] - mean) ** 2;
    }
    // Standard error from diagonal of s²(XᵀX)⁻¹. 95% interval uses the normal approximation (n > 30)
    var s2 = sse / (n - k);
    var se = beta.map(function (_, i) { return Math.sqrt(s2 * inv[i][i]); });

    return { beta: beta, se: se, r2: sst > 0 ? 1 - sse / sst : 1, n: n, s2: s2, inv: inv };
}

// Line fit with outlier trimming: drop points more than 2.5 standard errors from the line and refit
function robust_fit(pts) {
    var fit = null;
    for (var iter = 0; iter < 4; iter++) {
        fit = ols(pts.map(function (p) { return [p[0]]; }), pts.map(function (p) { return p[1]; }));
        if (!fit) return null;
        var limit = 2.5 * Math.sqrt(fit.s2);
        var kept = pts.filter(function (p) { return Math.abs(p[1] - fit.beta[0] - fit.beta[1] * p[0]) <= limit; });
        if (kept.length == pts.length || kept.length < 3) break;
        pts = kept;
    }
    return fit;
}

// Heat demand fit. Days below the balance point (zero heat ΔT) are mostly water heating and standby,
// which flatten the line. Fit, exclude days below the balance point, refit until it settles.
// Returns { m, b, base, pts } where pts are the days at or above the balance point.
function heatdemand_fit(pts) {
    var fit = robust_fit(pts);
    if (!fit || fit.beta[1] <= 0) return null;
    var base = -fit.beta[0] / fit.beta[1];
    var used = pts;

    for (var it = 0; it < 6; it++) {
        var above = pts.filter(function (p) { return p[0] >= base; });
        if (above.length == used.length) break;
        var f = robust_fit(above);
        if (!f || f.beta[1] <= 0) break;
        fit = f;
        used = above;
        var nb = -fit.beta[0] / fit.beta[1];
        if (Math.abs(nb - base) < 0.1) break;
        base = nb;
    }
    return { m: fit.beta[1], b: fit.beta[0], base: -fit.beta[0] / fit.beta[1], r2: fit.r2, pts: used };
}

// 80% prediction interval half width at x for a single day, about the fitted line.
// Uses the days at or above the balance point. Normal approximation (n > 30)
function heatdemand_pi(fit, x) {
    var n = fit.pts.length;
    var xbar = fit.pts.reduce(function (s, p) { return s + p[0]; }, 0) / n;
    var sxx = 0, sse = 0;
    fit.pts.forEach(function (p) {
        sxx += (p[0] - xbar) ** 2;
        sse += (p[1] - fit.m * p[0] - fit.b) ** 2;
    });
    return 1.282 * Math.sqrt(sse / (n - 2)) * Math.sqrt(1 + 1 / n + (x - xbar) ** 2 / sxx);
}


// Daily stats for every year, the fields the tool needs. Loaded once, when the
// Measurements card first opens, then the card is drawn again
var HEATDEMAND_FIELDS = "timestamp,combined_heat_mean,space_heat_kwh,cooling_heat_kwh,combined_outsideT_mean," +
    "combined_roomT_mean,combined_data_length,weighted_flowT,running_returnT_mean,quality_heat";
function heatdemand_load() {
    if (heatdemand_data || typeof config == "undefined" || !config.id) return;
    heatdemand_data = { loading: true };
    function done(d) { heatdemand_data = d; measurements_card_render(); }
    $.ajax({
        url: path + "system/stats/daily",
        data: { id: config.id, fields: HEATDEMAND_FIELDS, readkey: apikey },
        dataType: "text",
        success: function (csv) {
            var lines = String(csv || "").trim().split("\n"), head = lines.shift().split(","), col = {};
            head.forEach(function (h, i) { col[h] = i; });
            if (col.timestamp === undefined) return done({ message: "No daily data for this system" });
            function f(p, k) { var v = col[k] === undefined ? "" : p[col[k]]; return v === "" || v === undefined ? null : parseFloat(v); }
            var rows = [];
            lines.forEach(function (line) {
                var p = line.split(",");
                if (p.length != head.length) return;
                rows.push({ time: f(p, "timestamp") * 1000, heat_mean: f(p, "combined_heat_mean"), space_kwh: f(p, "space_heat_kwh"),
                    cooling_kwh: f(p, "cooling_heat_kwh"), outsideT: f(p, "combined_outsideT_mean"), roomT: f(p, "combined_roomT_mean"),
                    length: f(p, "combined_data_length"), flowT: f(p, "weighted_flowT"), returnT: f(p, "running_returnT_mean"),
                    quality: f(p, "quality_heat") });
            });
            // As the measurement script: the room temperature feed when there is
            // one, else HEATDEMAND_ROOM_T for every day
            var room = rows.some(function (r) { return r.roomT > 0; });
            if (!room) heatdemand_state.fixed_room = true;
            done({ rows: rows, room: room });
        },
        error: function () { done({ message: "Could not load the daily data" }); }
    });
}

function heatdemand_default_outsideT() {
    if (system_form.design_temp !== null) return system_form.design_temp;
    var t = (window.system_measurements || {}).design_outsideT_996;
    return t && t.value !== null ? Math.round(t.value * 10) / 10 : -3;
}

// Settings in use: the Explore controls, or the nightly fit's rules in the simple view
function heatdemand_settings() {
    var s = heatdemand_state, d = heatdemand_data || {};
    if (s.outsideT === null) s.outsideT = heatdemand_default_outsideT();
    if (s.open) return s;
    return { period: "all", mode: "combined", outsideT: s.outsideT, roomT: HEATDEMAND_ROOM_T, fixed_room: !d.room,
        min_dT: -Infinity, min_quality: 0, split: "none" };
}

// The nightly fit: { m (kW/K), b (kW), base (K), n, design (heat_loss_design) }, or null
function heatdemand_nightly() {
    var ms = window.system_measurements || {}, htc = ms.heat_loss_htc, base = ms.heat_loss_base_dT;
    if (!htc || htc.value === null || !base || base.value === null) return null;
    var m = htc.value / 1000, des = ms.heat_loss_design;
    return { m: m, b: -m * base.value, base: base.value, n: htc.samples,
        design: des && des.value !== null && des.details && des.details.design_dT !== undefined ? des : null };
}

// Days for the fit. Always as the measurement script: at least 18 h of data,
// a flow or return temperature and an outside temperature. Then the settings
function heatdemand_points(s) {
    var rows = heatdemand_data && heatdemand_data.rows ? heatdemand_data.rows : [];
    if (!rows.length) return [];
    var from = s.period == "year" ? rows[rows.length - 1].time - 364 * 86400000 : -Infinity;
    var points = [];
    rows.forEach(function (r) {
        if (r.time < from || !(r.length > HEATDEMAND_MIN_LENGTH) || r.outsideT === null) return;
        if (!r.flowT && !r.returnT) return;
        if (r.quality !== null && r.quality < s.min_quality) return;
        var inside = s.fixed_room ? s.roomT : r.roomT;
        if (!(inside > 0)) return;
        // Mean heat over the hours with data. All is every mode less cooling
        var kw = s.mode == "space" ? (r.space_kwh === null ? null : r.space_kwh / (r.length / 3600)) :
            (r.heat_mean === null ? null : r.heat_mean / 1000 - (r.cooling_kwh || 0) / 24);
        if (kw === null) return;
        var dT = inside - r.outsideT;
        if (dT < s.min_dT) return;
        points.push({ time: r.time, inside: inside, outside: r.outsideT, dT: dT, kw: kw });
    });
    return points;
}

// Group key for a point: year or heating season (July to June)
function heatdemand_group(p, split) {
    var d = new Date(p.time), y = d.getFullYear();
    if (split == "year") return "" + y;
    if (split == "season") return d.getMonth() >= 6 ? y + "/" + (y + 1) : (y - 1) + "/" + y;
    return "Days";
}

function heatdemand_line(data, color, width) {
    return { label: "", data: data, color: color, lines: { show: true, lineWidth: width }, points: { show: false } };
}

function heatdemand_fit_group(g, color, max_dT) {
    var fit = heatdemand_fit(g.map(function (p) { return [p.dT, p.kw]; }));
    if (!fit) return null;
    return { fit: fit, series: heatdemand_line([[fit.base, 0], [max_dT, fit.m * max_dT + fit.b]], color, 2) };
}

// The tool's part of the Sizing tile: chart, results and, in Explore, the
// controls. Drawn into by heatdemand_draw once the card is on the page
function heatdemand_html() {
    var esc = hp_sys.esc, d = heatdemand_data, s = heatdemand_state;
    heatdemand_settings();
    var ready = d && d.rows;
    var head = '<div class="hp-ms-subhead hp-hd-head"><span>Heat demand</span>' + (ready ?
        '<div class="btn-group app-segmented"><button type="button" class="btn' + (s.open ? ' active' : '') + '" data-hd-toggle aria-expanded="' + s.open + '" title="' +
        (s.open ? 'Back to the simple view' : 'Change the period, heat and design conditions, and split by year') + '"><i class="bi bi-arrows-angle-' +
        (s.open ? 'contract' : 'expand') + '"></i> <span>' + (s.open ? 'Simple' : 'Explore') + '</span></button></div>' : '') + '</div>';
    if (!d || d.loading) return '<div class="hp-hd">' + head + '<span class="hp-ms-fine">Loading the daily data…</span></div>';
    if (d.message) return '<div class="hp-hd">' + head + '<span class="hp-ms-fine">' + esc(d.message) + '</span></div>';

    var h = '<div class="hp-hd" id="hp-hd">' + head +
        '<div id="hp-hd-plot" class="hp-hd-plot' + (s.open ? ' is-open' : '') + '" role="img" aria-label="Daily heat output against inside - outside temperature difference"></div>' +
        '<div id="hp-hd-design" class="hp-hd-design"></div><div id="hp-hd-result" class="hp-hd-result"></div>';
    if (!s.open) return h + '<span class="hp-ms-fine">Each point is a day, every year. Click one to open it</span></div>';

    function input(key, label, unit, step, title) {
        return '<div class="input-group input-group-sm w-auto" title="' + esc(title) + '"><span class="input-group-text">' + label + '</span>' +
            '<input type="number" class="form-control" data-hd="' + key + '" value="' + esc(s[key]) + '" step="' + step + '">' +
            '<span class="input-group-text">' + unit + '</span></div>';
    }
    function select(key, label, options, title) {
        return '<div class="input-group input-group-sm w-auto" title="' + esc(title) + '"><span class="input-group-text">' + label + '</span>' +
            '<select class="form-select" data-hd="' + key + '">' + options.map(function (o) {
                return '<option value="' + o[0] + '"' + (s[key] == o[0] ? ' selected' : '') + '>' + o[1] + '</option>';
            }).join("") + '</select></div>';
    }
    h += '<div class="hp-hd-controls">' +
        select("period", "Period", [["all", "All years"], ["year", "Last 365 days"]], "Days to plot and fit") +
        select("mode", "Heat", [["combined", "All"], ["space", "Space heating"]], "All: every mode less cooling, as the nightly fit. Space heating leaves out hot water") +
        select("split", "Split by", [["none", "None"], ["year", "Year"], ["season", "Heating season"]], "Plot and fit groups of days separately") +
        input("outsideT", "Design outside", "°C", 0.5, "Outside temperature for the heat demand estimate") +
        '<div class="input-group input-group-sm w-auto" title="Target room temperature for the heat demand estimate">' +
        '<span class="input-group-text">Room</span><input type="number" class="form-control" data-hd="roomT" value="' + esc(s.roomT) + '" step="0.5">' +
        '<span class="input-group-text">°C</span><span class="input-group-text"><input type="checkbox" class="me-1" data-hd="fixed_room"' +
        (s.fixed_room ? ' checked' : '') + (d.room ? '' : ' disabled title="No room temperature data"') + '> Use as room T</span></div>' +
        input("min_dT", "Min ΔT", "K", 1, "Days with a smaller inside to outside temperature difference are excluded") +
        input("min_quality", "Min quality", "%", 1, "Days with less heat meter data coverage than this are excluded") +
        '<button type="button" class="btn btn-sm btn-outline-secondary' + (s.info ? ' active' : '') + '" data-hd-info title="How this works" aria-expanded="' + s.info + '" aria-controls="hp-hd-info"><i class="bi bi-info-circle"></i></button>' +
        '</div>';
    h += '<div id="hp-hd-info" class="hp-hd-info"' + (s.info ? '' : ' hidden') + '>' +
        '<p class="mb-1">Each point is one day: mean heat output against mean room minus outside temperature. Hover over a point for details, click to open the power view for that day. The slope of the fit line is the heat loss in W/K.</p>' +
        '<ul class="mb-0 ps-3">' +
        '<li><b>Simple view:</b> every day of every year with the nightly fit, the same as the Estimated value on the sizing bar. Explore fits the days here with these controls, so the figures can differ a little.</li>' +
        '<li><b>Days:</b> at least 18 hours of data, with a flow or return temperature and an outside temperature, as the nightly fit.</li>' +
        '<li><b>Fit:</b> the balance point is where the fit line reaches zero heat. Days below the balance point are mostly water heating and standby, so the fit excludes them and refits until the balance point settles. Days more than 2.5 standard errors from the line are also excluded.</li>' +
        '<li><b>Heat:</b> All is every mode less cooling. It includes water heating: for a typical 100 m² house that is similar to the reduction from internal gains, so All is often a good approximation of the building heat loss. Space heating gives a lower figure.</li>' +
        '<li><b>Design outside and Room:</b> set the design ΔT for the estimate. Design outside starts at the value on the system form, else the measured 99.6% outside temperature.</li>' +
        '<li><b>Projected:</b> the fit line at the design ΔT.</li>' +
        '<li><b>Design heat loss:</b> the dashed line, the heat loss entered on the system form.</li>' +
        '<li><b>Max output test:</b> the shaded band, the range of approved max output test results for this heat pump model in the heat pump database. A single line when there is one result.</li>' +
        '<li><b>Heat pump capacity:</b> the solid line, the badge capacity from the system form. Shown when the heat pump database has no max output test for this model.</li>' +
        '<li><b>Use as room T:</b> replaces measured room temperature with the Room value. Always on when there is no room temperature data. Useful when the feed is not representative of the whole house.</li>' +
        '<li><b>Min ΔT:</b> optional, excludes mild days before the fit.</li>' +
        '<li><b>Min quality:</b> percentage of the day with heat meter data. Days with gaps under-count heat.</li>' +
        '<li><b>Split by:</b> heating season runs July to June. The estimate uses the all days fit, not the split fits.</li>' +
        '<li>The estimate is mean output over a day at the design temperature, not peak output within the day. The range is an 80% prediction interval: 8 out of 10 days are expected to fall within it. A design temperature colder than any recorded day is an extrapolation.</li>' +
        '</ul></div>';
    return h + '</div>';
}

function heatdemand_draw() {
    var el = document.getElementById("hp-hd-plot"), d = heatdemand_data;
    if (!el || el.offsetParent === null || !d || !d.rows) return;
    var s = heatdemand_settings(), open = heatdemand_state.open;
    var out_design = document.getElementById("hp-hd-design"), out_result = document.getElementById("hp-hd-result");
    var ink_rgb = (getComputedStyle(el).getPropertyValue("--bs-body-color-rgb") || "34,34,34").trim();
    var ink = "rgb(" + ink_rgb + ")", muted = "rgba(" + ink_rgb + ",0.65)", faint = "rgba(" + ink_rgb + ",0.25)";

    var points = heatdemand_points(s);
    if (points.length < 3) {
        el.innerHTML = "";
        out_design.innerHTML = "";
        out_result.innerHTML = "Not enough days with heat, outside and room temperature" + (open ? ". Try All years, a lower Min quality or Use as room T." : ".");
        return;
    }

    var groups = {};
    points.forEach(function (p) {
        var key = heatdemand_group(p, s.split);
        (groups[key] = groups[key] || []).push(p);
    });

    // Simple view: the nightly fit and its design point. Explore: fitted here
    var nightly = open ? null : heatdemand_nightly();
    var design_dT = nightly && nightly.design ? nightly.design.details.design_dT : s.roomT - s.outsideT;
    var dTs = points.map(function (p) { return p.dT; });
    var min_dT = Math.min(0, Math.floor(Math.min.apply(null, dTs)));
    var max_dT = Math.ceil(Math.max(isNaN(design_dT) ? 0 : design_dT, Math.max.apply(null, dTs)) + 2);
    var max_kw = Math.ceil(Math.max.apply(null, points.map(function (p) { return p.kw; })) * 1.1);
    var series = [], lines = [], result = [];

    var keys = Object.keys(groups).sort();
    keys.forEach(function (key, i) {
        var color = heatdemand_colors[i % heatdemand_colors.length], g = groups[key];
        series.push({
            label: keys.length > 1 ? key : "", color: color, days: g,
            data: g.map(function (p) { return [p.dT, p.kw]; }),
            points: { show: true, radius: open ? 3 : 2, lineWidth: open ? 2 : 1, fill: true, fillColor: open ? color : "rgba(70,130,180,0.45)" }
        });
        if (keys.length > 1) {
            var l = heatdemand_fit_group(g, color, max_dT);
            if (l) {
                lines.push(l.series);
                result.push(key + ": " + (l.fit.m * 1000).toFixed(0) + " W/K, balance point " + l.fit.base.toFixed(1) + " K");
            }
        }
    });

    var f = nightly, fit = null;
    if (!f) {
        fit = heatdemand_fit(points.map(function (p) { return [p.dT, p.kw]; }));
        if (fit) f = { m: fit.m, b: fit.b, base: fit.base, n: fit.pts.length };
    }
    out_design.innerHTML = "";
    if (f) {
        // 80% prediction interval band, sampled as it widens away from the mean ΔT
        if (fit) {
            var upper = [], lower = [];
            for (var i = 0; i <= 40; i++) {
                var x = f.base + (max_dT - f.base) * i / 40, hw = heatdemand_pi(fit, x);
                upper.push([x, f.m * x + f.b + hw]);
                lower.push([x, Math.max(0, f.m * x + f.b - hw)]);
            }
            lines.push(heatdemand_line(upper, faint, 1), heatdemand_line(lower, faint, 1));
        }
        lines.push(heatdemand_line([[f.base, 0], [max_dT, f.m * max_dT + f.b]], ink, 2));
        var first = new Date(points[0].time), last = new Date(points[points.length - 1].time);
        var span = first.toLocaleDateString("en-GB", { month: "short", year: "numeric" }) + " to " + last.toLocaleDateString("en-GB", { month: "short", year: "numeric" });
        result.unshift((keys.length > 1 ? "<b>All days: " : "<b>") + (f.m * 1000).toFixed(0) + " W/K</b>, balance point " + f.base.toFixed(1) + " K" +
            (fit ? ", R² " + fit.r2.toFixed(2) + ", " + f.n + " of " + points.length + " days" : ", " + points.length + " days") + ", " + span +
            (nightly ? " (nightly fit)" : ""));

        // At the design ΔT: the nightly heat_loss_design, or the line here
        var y = null, lo = null, hi = null, outside = s.outsideT, inside = s.roomT, source = "";
        if (nightly && nightly.design) {
            var nd = nightly.design;
            y = nd.value; lo = nd.lower; hi = nd.upper;
            outside = nd.details.design_outsideT; inside = outside + design_dT;
            source = nd.details.design_outsideT_source == "measured" ? " (measured 99.6%)" : " (system form)";
        } else if (fit && !isNaN(design_dT)) {
            var pi = heatdemand_pi(fit, design_dT);
            y = f.m * design_dT + f.b; lo = y - pi; hi = y + pi;
        }
        if (y !== null) {
            if (lo !== null && hi !== null) {
                max_kw = Math.max(max_kw, Math.ceil(hi + 0.5));
                lines.push({ label: "", data: [[design_dT, Math.max(0, lo)], [design_dT, hi]], color: "#c0504d", lines: { show: true, lineWidth: 2 }, points: { show: false } });
            }
            lines.push({ label: open ? "Projected" : "Estimated", data: [[design_dT, y]], color: "#c0504d", points: { show: true, radius: 5, fill: true, fillColor: "#c0504d" } });
            out_design.innerHTML = "<b>" + y.toFixed(2) + " kW</b>" + (hi !== null ? " ± " + ((hi - lo) / 2).toFixed(2) + " kW (80% PI)" : "") +
                " at " + outside + " °C outside" + source + ", " + inside + " °C inside, ΔT " + design_dT.toFixed(1) + " K" +
                (design_dT > Math.max.apply(null, dTs) ? ", beyond the coldest day" : "");
        }
    }

    // Design heat loss from the system form, a horizontal line at the entered kW
    if (system_form.heat_loss > 0) {
        max_kw = Math.max(max_kw, Math.ceil(system_form.heat_loss + 0.5));
        lines.push({ label: "Design heat loss", data: [[min_dT, system_form.heat_loss], [max_dT, system_form.heat_loss]], color: "#7b5ea7", lines: { show: true, lineWidth: 2, dashes: [6, 4] }, points: { show: false } });
        if (open) result.push("<b>Design heat loss:</b> " + system_form.heat_loss + " kW" + (system_form.design_temp != null ? " at " + system_form.design_temp + " °C outside" : "") + " (system form)");
    }

    // Design outside temperature measured from this system's hourly outside temperature
    var mdot = (window.system_measurements || {}).design_outsideT_996;
    if (open && mdot && mdot.value !== null) {
        var mdot_v = Math.round(mdot.value * 10) / 10;
        result.push("<b>Measured design outside:</b> " + mdot_v + " °C, equalled or exceeded for 99.6% of hours" +
            (mdot_v == s.outsideT ? " (in use)" : ' <a href="#" data-hd-use="' + mdot_v + '">use</a>'));
    }

    // Max output tests of this heat pump model: a shaded min to max band, or a line for one
    // test or matching results. Otherwise the badge capacity from the system form.
    var mc = heatdemand_maxcap;
    if (mc) {
        max_kw = Math.max(max_kw, Math.ceil(mc.max + 0.5));
        if (mc.max > mc.min) {
            lines.push({ label: "Max output test range", data: [[min_dT, mc.max, mc.min], [max_dT, mc.max, mc.min]], color: "#cc8888", lines: { show: true, lineWidth: 0, fill: true, fillColor: "rgba(204,136,136,0.25)" }, points: { show: false } });
        } else {
            lines.push({ label: "Max output test", data: [[min_dT, mc.mean], [max_dT, mc.mean]], color: "#cc8888", lines: { show: true, lineWidth: 2 }, points: { show: false } });
        }
        if (open) result.push("<b>Max output test:</b> " + (mc.count == 1 ? mc.mean.toFixed(1) + " kW (1 test)" :
            mc.mean.toFixed(1) + " kW mean, " + mc.min.toFixed(1) + " - " + mc.max.toFixed(1) + " kW range (" + mc.count + " tests)") +
            ' <a href="' + path + "heatpump/view?id=" + system_form.heatpump_model_id + '" target="_blank" rel="noopener">heat pump database</a>');
    } else if (system_form.hp_output > 0) {
        max_kw = Math.max(max_kw, Math.ceil(system_form.hp_output + 0.5));
        lines.push({ label: "Heat pump capacity", data: [[min_dT, system_form.hp_output], [max_dT, system_form.hp_output]], color: ink, lines: { show: true, lineWidth: 2 }, points: { show: false } });
        if (open) result.push("<b>Heat pump capacity:</b> " + system_form.hp_output + " kW (badge, system form)");
    }

    out_result.innerHTML = result.join("<br>");

    var font = { size: typeof flot_font_size != "undefined" ? flot_font_size : 12, color: muted, fill: muted };
    var options = {
        xaxis: { min: min_dT, max: max_dT, autoScale: "none", font: font, axisLabel: "Inside - outside temperature (K)" },
        yaxes: [{ min: 0, max: max_kw, autoScale: "none", font: font, axisLabel: "Mean heat output (kW)" }],
        grid: { show: true, color: faint, borderWidth: 0, hoverable: true, clickable: true },
        legend: { show: true, position: "nw" }
    };
    heatdemand_plot = Flot.plot(el, series.concat(lines), options);

    // The plot element is new each time the card is drawn
    if (!el.hd_bound) {
        el.hd_bound = true;
        el.addEventListener("plotclick", function (event) {
            var item = event.detail[1];
            if (!item || !item.series.days) return;
            $("#tooltip").remove();
            hp_open_day(item.series.days[item.dataIndex].time);
            $("html, body").scrollTop(0);
        });
        el.addEventListener("plothover", function (event) {
            var item = event.detail[1];
            $("#tooltip").remove();
            if (!item || !item.series.days) return;
            var p = item.series.days[item.dataIndex];
            tooltip(item.pageX, item.pageY, new Date(p.time).toLocaleDateString() + "<br>ΔT: " + p.dT.toFixed(1) + " K (" +
                p.inside.toFixed(1) + " in, " + p.outside.toFixed(1) + " out)<br>Heat: " + p.kw.toFixed(2) + " kW", "#fff", "#000");
        });
        el.addEventListener("mouseleave", function () { $("#tooltip").remove(); });
    }
}

// Opens the Measurements card with the tool in Explore, for ?heatdemand=1
// links (from the old /heatloss page and the system form). Waits for the card
// to be drawn when it isn't yet
function heatdemand_show() {
    heatdemand_state.open = true;
    if (!document.getElementById("measurements-body")) { heatdemand_autoopen = true; return; }
    heatdemand_autoopen = false;
    measurements_card_render();
    if (document.getElementById("measurements-body").hidden) document.getElementById("measurements-toggle").click();
    var t = document.getElementById("hp-hd") || document.getElementById("measurements-block");
    if (t) t.scrollIntoView();
}

// Controls. Delegated, since the card's content is drawn again
$(document).on("click", "#hp-hd [data-hd-toggle]", function () {
    heatdemand_state.open = !heatdemand_state.open;
    measurements_card_render();
});
$(document).on("click", "#hp-hd [data-hd-info]", function () {
    heatdemand_state.info = !heatdemand_state.info;
    $("#hp-hd-info").prop("hidden", !heatdemand_state.info);
    $(this).toggleClass("active", heatdemand_state.info).attr("aria-expanded", heatdemand_state.info);
});
$(document).on("change", "#hp-hd [data-hd]", function () {
    var key = this.getAttribute("data-hd");
    if (this.type == "checkbox") heatdemand_state[key] = this.checked;
    else if (this.tagName == "SELECT") heatdemand_state[key] = this.value;
    else if (this.value !== "" && !isNaN(parseFloat(this.value))) heatdemand_state[key] = parseFloat(this.value);
    heatdemand_draw();
});
$(document).on("click", "#hp-hd [data-hd-use]", function (e) {
    e.preventDefault();
    heatdemand_state.outsideT = parseFloat(this.getAttribute("data-hd-use"));
    $('#hp-hd [data-hd="outsideT"]').val(heatdemand_state.outsideT);
    heatdemand_draw();
});

// Max output tests for the matched heat pump model, approved ones only
if (system_form.heatpump_model_id) {
    $.ajax({
        url: path + "heatpump/max_cap_test/list",
        data: { id: system_form.heatpump_model_id },
        dataType: "json",
        success: function (tests) {
            if (!Array.isArray(tests)) return;
            var kw = tests.filter(function (t) { return t.review_status == 1 && t.heat > 0; })
                .map(function (t) { return t.heat * 0.001; });
            if (!kw.length) return;
            heatdemand_maxcap = {
                count: kw.length,
                mean: kw.reduce(function (s, v) { return s + v; }, 0) / kw.length,
                min: Math.min.apply(null, kw),
                max: Math.max.apply(null, kw)
            };
            heatdemand_draw();
        }
    });
}
