// -------------------------------------------------------------------------------
// HEAT DEMAND
// Daily mean heat output against inside - outside temperature difference
// -------------------------------------------------------------------------------

var heatdemand_colors = ["#4682b4", "#e8a33c", "#5bb35b", "#c0504d", "#8064a2", "#4bacc6"];
var heatdemand_solar = { start: 0, end: 0, data: null };
var heatdemand_plot = null;

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

// Daily solar kWh, keyed by timestamp. Fetched once per bargraph window.
// heatpumpmonitor.org: no solar feed is available, so the Solar split is hidden.
function heatdemand_solar_map() {
    if (!feeds["solar_elec_kwh"] || typeof feed == "undefined") return null;
    if (heatdemand_solar.start != bargraph_start || heatdemand_solar.end != bargraph_end) {
        heatdemand_solar = {
            start: bargraph_start,
            end: bargraph_end,
            data: new Map(feed.getdata(feeds["solar_elec_kwh"].id, bargraph_start, bargraph_end, "daily", 0, 1))
        };
    }
    return heatdemand_solar.data;
}

function heatdemand_points() {
    var heat = daily_data[bargraph_mode + "_heat_kwh"];
    var outsideT = daily_data["combined_outsideT_mean"];
    if (!heat || !outsideT) return [];

    var roomT = new Map(daily_data["combined_roomT_mean"] || []);
    var quality = new Map(daily_data["quality_heat"] || []);
    var outside = new Map(outsideT);
    var solar = heatdemand_solar_map();

    var fixed = $("#heatdemand_fixed_roomT").is(":checked") || !feeds["heatpump_roomT"];
    var fixed_roomT = parseFloat($("#heatdemand_roomT").val());
    var min_dT = parseFloat($("#heatdemand_min_dT").val()) || 0;
    var min_quality = parseFloat($("#heatdemand_min_quality").val()) || 0;

    var points = [];
    for (var z in heat) {
        var time = heat[z][0];
        var inside = fixed ? fixed_roomT : roomT.get(time);
        var out = outside.get(time);
        var q = quality.has(time) ? quality.get(time) : 100;
        if (heat[z][1] == null || inside == null || out == null || q < min_quality) continue;

        var dT = inside - out;
        var kw = heat[z][1] / 24;
        if (kw <= 0 || dT < min_dT) continue;

        points.push({ time: time, inside: inside, dT: dT, kw: kw, solar: solar ? solar.get(time) : null });
    }
    return points;
}

// Group key for a point: year, heating season (July to June) or solar tercile
function heatdemand_group(p, split, solar_bands) {
    var d = new Date(p.time);
    var y = d.getFullYear();
    if (split == "year") return "" + y;
    if (split == "season") return d.getMonth() >= 6 ? y + "/" + (y + 1) : (y - 1) + "/" + y;
    if (split == "solar") {
        if (p.solar == null) return "Solar n/a";
        if (p.solar < solar_bands[0]) return "Solar 1: < " + solar_bands[0].toFixed(1) + " kWh";
        if (p.solar < solar_bands[1]) return "Solar 2: " + solar_bands[0].toFixed(1) + " - " + solar_bands[1].toFixed(1) + " kWh";
        return "Solar 3: > " + solar_bands[1].toFixed(1) + " kWh";
    }
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

function heatdemand_draw() {
    if (!$("#heatdemand-block").is(":visible")) return;

    var points = heatdemand_points();
    if (points.length < 3) {
        $("#heatdemand-result").html("Not enough days with heat, outside temperature and room temperature data. Try a longer window or tick Use as room T.");
        $("#heatdemand").empty();
        return;
    }

    var split = $("#heatdemand_split").val();
    var solar_values = points.filter(function (p) { return p.solar != null; }).map(function (p) { return p.solar; }).sort(function (a, b) { return a - b; });
    var solar_bands = [solar_values[Math.floor(solar_values.length / 3)] || 0, solar_values[Math.floor(2 * solar_values.length / 3)] || 0];

    var groups = {};
    points.forEach(function (p) {
        var key = heatdemand_group(p, split, solar_bands);
        if (!groups[key]) groups[key] = [];
        groups[key].push(p);
    });

    var design_outsideT = parseFloat($("#heatdemand_design_outsideT").val());
    var design_insideT = parseFloat($("#heatdemand_roomT").val());
    var design_dT = design_insideT - design_outsideT;

    var max_dT = Math.ceil(Math.max(design_dT, Math.max.apply(null, points.map(function (p) { return p.dT; }))) + 2);
    var max_kw = Math.ceil(Math.max.apply(null, points.map(function (p) { return p.kw; })) * 1.1);
    var series = [], lines = [], result = [];

    Object.keys(groups).sort().forEach(function (key, i) {
        var color = heatdemand_colors[i % heatdemand_colors.length];
        var g = groups[key];
        series.push({
            label: key, color: color, days: g,
            data: g.map(function (p) { return [p.dT, p.kw]; }),
            points: { show: true, radius: 3, fill: true, fillColor: color }
        });
        if (split != "none") {
            var l = heatdemand_fit_group(g, color, max_dT);
            if (l) {
                lines.push(l.series);
                result.push(key + ": " + (l.fit.m * 1000).toFixed(0) + " W/K, balance point " + l.fit.base.toFixed(1) + " K");
            }
        }
    });

    var all = heatdemand_fit_group(points, "#333", max_dT);
    if (all) {
        var f = all.fit;

        // 80% prediction interval band, sampled as it widens away from the mean ΔT
        var upper = [], lower = [];
        for (var i = 0; i <= 40; i++) {
            var x = f.base + (max_dT - f.base) * i / 40;
            var hw = heatdemand_pi(f, x);
            upper.push([x, f.m * x + f.b + hw]);
            lower.push([x, Math.max(0, f.m * x + f.b - hw)]);
        }
        lines.push(heatdemand_line(upper, "#bbb", 1), heatdemand_line(lower, "#bbb", 1), all.series);
        result.unshift("<b>All days: " + (f.m * 1000).toFixed(0) + " W/K</b>, balance point " + f.base.toFixed(1) + " K, R² " + f.r2.toFixed(2) + ", " + f.pts.length + " of " + points.length + " days");

        if (!isNaN(design_dT)) {
            var y = f.m * design_dT + f.b, pi = heatdemand_pi(f, design_dT);
            max_kw = Math.max(max_kw, Math.ceil(y + pi + 0.5));
            lines.push({ label: "Projected", data: [[design_dT, y]], color: "#c0504d", points: { show: true, radius: 5, fill: true, fillColor: "#c0504d" } });
            $("#heatdemand-design").html("<b>" + y.toFixed(2) + " kW</b> ± " + pi.toFixed(2) + " kW (80% PI) at " + design_outsideT + " °C outside, " + design_insideT + " °C inside, ΔT " + design_dT.toFixed(1) + " K");
        }
    } else {
        $("#heatdemand-design").html("");
    }

    // Heat = b0 + b1·ΔT + b2·solar, when solar is available
    var with_solar = points.filter(function (p) { return p.solar != null; });
    if (with_solar.length > 10) {
        var m = ols(with_solar.map(function (p) { return [p.dT, p.solar]; }), with_solar.map(function (p) { return p.kw; }));
        if (m) {
            var ci = function (i) { return (m.beta[i] * 1000).toFixed(0) + " ± " + (1.96 * m.se[i] * 1000).toFixed(0); };
            result.push("<b>With solar:</b> " + ci(1) + " W/K, " + ci(2) + " W per kWh/day of solar, intercept " + ci(0) + " W, R² " + m.r2.toFixed(2) + " (95% interval)");
        }
    }

    // Design heat loss from the system form, a horizontal line at the entered kW
    if (system_design.heat_loss > 0) {
        max_kw = Math.max(max_kw, Math.ceil(system_design.heat_loss + 0.5));
        lines.push({ label: "Design heat loss", data: [[0, system_design.heat_loss], [max_dT, system_design.heat_loss]], color: "#7b5ea7", lines: { show: true, lineWidth: 2, dashes: [6, 4] }, points: { show: false } });
        var at = system_design.design_temp != null ? " at " + system_design.design_temp + " °C outside" : "";
        result.push("<b>Design heat loss:</b> " + system_design.heat_loss + " kW" + at + " (system form)");
    }

    $("#heatdemand-result").html(result.join("<br>"));

    // Scope of the estimate, shown when a split or solar fit is on the chart
    var scope = [];
    if (split != "none") scope.push("Estimate uses the all days fit, not the " + $("#heatdemand_split option:selected").text().toLowerCase() + " fits.");
    if (with_solar.length > 10) scope.push("Estimate does not include solar. The with solar fit shows its effect.");
    $("#heatdemand-scope-note").html(scope.join(" ")).toggle(scope.length > 0);

    var font = { size: flot_font_size, color: "#666", fill: "#666" };
    var options = {
        xaxis: { min: 0, max: max_dT, autoScale: "none", font: font, axisLabel: "Inside - outside temperature (K)" },
        yaxes: [{ min: 0, max: max_kw, autoScale: "none", font: font, axisLabel: "Mean heat output (kW)" }],
        grid: { show: true, color: "#aaa", borderWidth: 0, hoverable: true, clickable: true },
        legend: { show: true, position: "nw" }
    };
    heatdemand_plot = Flot.plot(document.getElementById("heatdemand"), series.concat(lines), options);
}

function heatdemand_tooltip(item) {
    var p = item.series.days[item.dataIndex];
    var solar = p.solar != null ? "<br>Solar: " + p.solar.toFixed(1) + " kWh" : "";
    tooltip(item.pageX, item.pageY, new Date(p.time).toLocaleDateString() + "<br>ΔT: " + p.dT.toFixed(1) + " K<br>Heat: " + p.kw.toFixed(2) + " kW" + solar, "#fff", "#000");
}

$("#heatdemand-toggle").click(function () {
    $("#heatdemand-block").toggle();
    var open = $("#heatdemand-block").is(":visible");
    $(this).html(open ? "HIDE HEAT DEMAND" : "SHOW HEAT DEMAND");
    // A week to 3 month preset has too few days for a useful fit, so opening
    // the tool widens the daily bars to the last year. Year, All and a zoomed
    // or panned window are kept.
    if (open && ["7", "30", "90"].indexOf(bargraph_period) != -1) {
        $(".hp-period[days='365']").click();
    } else {
        heatdemand_draw();
    }
});

// Design outside temperature starts at the value entered on the system form
if (system_design.design_temp != null) $("#heatdemand_design_outsideT").val(system_design.design_temp);

$("#heatdemand-block").on("change", "input, select", heatdemand_draw);

$("#heatdemand_info_toggle").click(function () {
    var open = !$("#heatdemand-info").is(":visible");
    $("#heatdemand-info").toggle(open);
    $(this).toggleClass("active", open).attr("aria-expanded", open);
});

document.getElementById("heatdemand").addEventListener("plotclick", function (event) {
    var item = event.detail[1];
    if (!item || !item.series.days) return;
    $("#tooltip").remove();
    hp_open_day(item.series.days[item.dataIndex].time);
    $("html, body").scrollTop(0);
});

document.getElementById("heatdemand").addEventListener("plothover", function (event) {
    var item = event.detail[1];
    $("#tooltip").remove();
    if (item && item.series.days) heatdemand_tooltip(item);
});
