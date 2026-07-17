// Heat pump signature explorer.
//
// Expects these globals to be defined inline by the view before this file
// loads (they carry server-side values):
//     path      base URL path for API/asset requests
//     admin     1 if the current user is an admin, else 0
//     systemid  system id to open on load (0 = default to first system)

var user_mode = false;
var systemid_map = {};   // system id -> index into app.system_list

// Chart canvas colours. The canvas can't use CSS classes, so the few colours
// it needs are defined here rather than read back out of the stylesheet.
var INK = "#6b6a64";              // axis titles, ticks
var GRID = "rgba(0,0,0,.08)";    // gridlines
var TREND = "#eb6834";           // median-COP trend line

// ---- Field definitions -------------------------------------------------
// F:     variable key -> long axis label
// SHORT: variable key -> short label used in tooltips and correlation chips
var F = {
    ot:   "Outside temp (°C)",
    ft:   "Flow temp (°C)",
    lift: "Lift, flow − outside (°C)",
    el:   "Electrical power (W)",
    dt:   "ΔT (°C)",
    cop:  "COP",
    dur:  "Duration (min)"
};
var SHORT = { ot: "outT", ft: "flowT", lift: "lift", el: "elec", dt: "ΔT", cop: "COP", dur: "dur" };
var KEYS = Object.keys(F);

// Map a signature_episodes row (from signature/list) to a chart point.
// Optional feeds (outsideT, dT, cop) arrive as null and become NaN so the
// chart simply skips them rather than plotting a misleading zero.
function transform(rows) {
    return rows.map(function (r) {
        var start = +r.start_time, end = +r.end_time;
        var ft = +r.flowT;
        var ot = (r.outsideT === null || r.outsideT === undefined) ? NaN : +r.outsideT;
        return {
            t:     fmtDate(start),
            start: start,
            end:   end,
            dur:   Math.round((+r.duration) / 60),                 // seconds -> minutes
            ft:    ft,
            dt:    (r.dT === null || r.dT === undefined) ? NaN : +r.dT,
            el:    Math.round(+r.elec),
            ot:    ot,
            cop:   (r.cop === null || r.cop === undefined) ? NaN : +r.cop,
            lift:  Math.round((ft - ot) * 10) / 10,                // flow minus outside temperature
            u:     path + "dashboard?id=" + app.systemid + "&mode=power&start=" + start + "&end=" + end
        };
    });
}

// Format a unix timestamp as "YYYY-MM-DD HH:MM" in local time.
function fmtDate(ts) {
    var d = new Date(ts * 1000);
    var p = function (n) { return String(n).padStart(2, "0"); };
    return d.getFullYear() + "-" + p(d.getMonth() + 1) + "-" + p(d.getDate()) +
        " " + p(d.getHours()) + ":" + p(d.getMinutes());
}

// ---- Colour ramp -------------------------------------------------------
// Viridis: multi-hue but monotone in lightness and colourblind-safe, so
// magnitude reads across a dynamic purple → blue → teal → green → yellow range.
var ST = [[68, 1, 84], [59, 82, 139], [33, 145, 140], [94, 201, 98], [253, 231, 37]];

// Map t in [0,1] to an rgb() string by linearly interpolating between the
// ramp control points.
function ramp(t) {
    t = Math.max(0, Math.min(1, t));
    var seg = ST.length - 1;
    var x = t * seg;
    var i = Math.min(Math.floor(x), seg - 1);
    var f = x - i;
    var a = ST[i], b = ST[i + 1];
    return "rgb(" +
        Math.round(a[0] + (b[0] - a[0]) * f) + "," +
        Math.round(a[1] + (b[1] - a[1]) * f) + "," +
        Math.round(a[2] + (b[2] - a[2]) * f) + ")";
}

// ---- Small helpers -----------------------------------------------------
// Display formatting: power and duration as integers, COP/ΔT to 2dp, else 1dp.
function fmt(k, v) {
    if (k === "el" || k === "dur") return Math.round(v);
    return v.toFixed(k === "cop" || k === "dt" ? 2 : 1);
}

// Median of a numeric array.
function median(a) {
    var s = a.slice().sort(function (x, y) { return x - y; });
    return s[Math.floor(s.length / 2)];
}

// Pearson correlation between two equal-length arrays, or null when there are
// too few points or no variance to compute it.
function pearson(a, b) {
    var n = a.length;
    if (n < 3) return null;
    var ma = a.reduce(function (s, x) { return s + x; }, 0) / n;
    var mb = b.reduce(function (s, x) { return s + x; }, 0) / n;
    var cov = 0, va = 0, vb = 0;
    for (var i = 0; i < n; i++) {
        cov += (a[i] - ma) * (b[i] - mb);
        va  += (a[i] - ma) * (a[i] - ma);
        vb  += (b[i] - mb) * (b[i] - mb);
    }
    if (va === 0 || vb === 0) return null;
    return cov / Math.sqrt(va * vb);
}

// ---- Axis / colour selectors -------------------------------------------
// Populate a <select> with one option per variable, marking val as selected.
function opt(sel, val) {
    sel.innerHTML = "";
    for (var i = 0; i < KEYS.length; i++) {
        var k = KEYS[i];
        var o = document.createElement("option");
        o.value = k;
        o.textContent = F[k];
        if (k === val) o.selected = true;
        sel.appendChild(o);
    }
}
opt(document.getElementById("xs"), "ot");
opt(document.getElementById("ys"), "cop");
opt(document.getElementById("cs"), "el");

// ---- State -------------------------------------------------------------
var DATA = [];       // all episodes for the selected system (as chart points)
var VIEW = [];       // DATA after the active constraints are applied
var chart;           // Chart.js instance (created lazily)
var filters = [];    // active constraints: { prop, center, tol }

// ---- Constraints (hold a property within ± tolerance of a centre) ------
// Build the HTML for one constraint row (Bootstrap grid).
function frowHTML(f, i) {
    var opts = KEYS.map(function (k) {
        return '<option value="' + k + '"' + (k === f.prop ? " selected" : "") + ">" + F[k] + "</option>";
    }).join("");
    return '<div class="row g-2 align-items-center mb-2" data-i="' + i + '">' +
        '<div class="col"><select class="form-select form-select-sm fp">' + opts + '</select></div>' +
        '<div class="col-auto text-muted small">within</div>' +
        '<div class="col"><input type="number" class="form-control form-control-sm ft" step="0.1" value="' + f.tol + '"></div>' +
        '<div class="col-auto text-muted small">of</div>' +
        '<div class="col"><input type="number" class="form-control form-control-sm fc" step="0.1" value="' + f.center + '"></div>' +
        '<div class="col-auto"><button class="btn btn-outline-secondary btn-sm xbtn" title="remove">&times;</button></div>' +
        '</div>';
}

// Render all constraint rows and wire up their inputs.
function renderFilters() {
    var box = document.getElementById("frows");
    box.innerHTML = filters.map(frowHTML).join("");
    box.querySelectorAll("[data-i]").forEach(function (row) {
        var i = +row.dataset.i;
        row.querySelector(".fp").addEventListener("change", function (e) { filters[i].prop = e.target.value; refresh(); });
        row.querySelector(".fc").addEventListener("input", function (e) { filters[i].center = parseFloat(e.target.value); refresh(); });
        row.querySelector(".ft").addEventListener("input", function (e) { filters[i].tol = parseFloat(e.target.value); refresh(); });
        row.querySelector(".xbtn").addEventListener("click", function () { filters.splice(i, 1); renderFilters(); refresh(); });
    });
}

// Add a new constraint, defaulting to flow temp centred on the median.
function addFilter() {
    var prop = "ft";
    var c = DATA.length ? median(DATA.map(function (d) { return d[prop]; })) : 34;
    filters.push({ prop: prop, center: Math.round(c * 10) / 10, tol: 0.1 });
    renderFilters();
    refresh();
}

// Keep only episodes that satisfy every constraint.
function applyFilters(rows) {
    return rows.filter(function (r) {
        return filters.every(function (f) {
            if (isNaN(f.center) || isNaN(f.tol)) return true;
            return Math.abs(r[f.prop] - f.center) <= f.tol;
        });
    });
}

// ---- Summary cards -----------------------------------------------------
function cards() {
    var box = document.getElementById("cards");
    if (!VIEW.length) { box.innerHTML = ""; return; }
    var n = VIEW.length;
    var tot = VIEW.reduce(function (s, d) { return s + d.dur; }, 0);                 // shown duration (min)
    var wcop = VIEW.reduce(function (s, d) { return s + d.cop * d.dur; }, 0) / tot;  // duration-weighted COP
    var lm = VIEW.filter(function (d) { return d.el < 540; }).length;               // episodes near min modulation
    var c = [
        ["Episodes shown", n],
        ["Steady runtime", (tot / 60).toFixed(0) + " h"],
        ["Weighted COP", wcop.toFixed(2)],
        ["Near min-mod", Math.round(100 * lm / n) + "%"]
    ];
    box.innerHTML = c.map(function (x) {
        return '<div class="col-6 col-md-3">' +
            '<div class="card h-100"><div class="card-body py-2 px-3">' +
            '<div class="text-muted small">' + x[0] + '</div>' +
            '<div class="fs-4">' + x[1] + '</div>' +
            '</div></div></div>';
    }).join("");
}

// Median COP per 2°C outside-temperature band, used for the trend line.
function trend() {
    var b = {};
    VIEW.forEach(function (d) {
        var k = Math.floor(d.ot / 2) * 2;
        (b[k] = b[k] || []).push(d.cop);
    });
    return Object.keys(b).map(Number).sort(function (a, c) { return a - c; }).map(function (k) {
        var v = b[k].sort(function (x, y) { return x - y; });
        return { x: k + 1, y: v[Math.floor(v.length / 2)] };    // band centre, median COP
    });
}

// ---- Correlation chips -------------------------------------------------
// Rank how each property correlates with the current Y variable over the shown
// episodes, and render one chip per property (bar width scales with |r|).
function correlations() {
    var yk = document.getElementById("ys").value;
    var xk = document.getElementById("xs").value;
    var head = document.getElementById("corrhead");
    var chips = document.getElementById("corrchips");
    if (VIEW.length < 3) {
        head.textContent = "Not enough points (" + VIEW.length + ") to compute correlations — loosen the constraints.";
        chips.innerHTML = "";
        return;
    }
    var yv = VIEW.map(function (d) { return d[yk]; });
    var list = KEYS.filter(function (k) { return k !== yk; })
        .map(function (k) { return { k: k, r: pearson(VIEW.map(function (d) { return d[k]; }), yv) }; })
        .filter(function (o) { return o.r !== null; })
        .sort(function (a, b) { return Math.abs(b.r) - Math.abs(a.r); });
    head.textContent = "How " + SHORT[yk] + " correlates with each property, over " + VIEW.length + " shown episodes (Pearson r):";
    chips.innerHTML = list.map(function (o) {
        var sign = o.r >= 0 ? "+" : "−";
        var col = o.r >= 0 ? "var(--bs-success)" : "var(--bs-danger)";
        var w = Math.round(Math.abs(o.r) * 46) + 2;
        var cur = o.k === xk ? " border-primary" : "";
        return '<span class="d-inline-flex align-items-center gap-1 px-2 py-1 rounded-pill bg-light border small' + cur + '">' +
            '<span style="display:inline-block;height:6px;border-radius:3px;min-width:2px;background:' + col + ';width:' + w + 'px"></span>' +
            SHORT[o.k] + " <b>" + sign + Math.abs(o.r).toFixed(2) + "</b></span>";
    }).join("");
}

// ---- Chart -------------------------------------------------------------
// Build (or update) the bubble chart for the current axis/colour selection.
function build() {
    if (!VIEW.length) {
        if (chart) { chart.data = { datasets: [] }; chart.update(); }
        return;
    }
    var xk = document.getElementById("xs").value;
    var yk = document.getElementById("ys").value;
    var ck = document.getElementById("cs").value;

    // Colour is normalised across the shown range; bubble radius scales with
    // the sqrt of duration so that area (not radius) tracks time.
    var cv = VIEW.map(function (d) { return d[ck]; });
    var cmin = Math.min.apply(null, cv), cmax = Math.max.apply(null, cv);
    var dmax = Math.max.apply(null, VIEW.map(function (d) { return d.dur; }));
    var pts = VIEW.map(function (d) {
        return {
            x: d[xk],
            y: d[yk],
            r: 3 + Math.sqrt(d.dur / dmax) * 8,
            _d: d,
            _c: ramp((d[ck] - cmin) / (cmax - cmin || 1))
        };
    });

    var ds = [{
        type: "bubble",
        data: pts,
        backgroundColor: pts.map(function (p) { return p._c; }),
        borderWidth: 0,
        order: 2
    }];
    // Overlay the median-COP trend line only on the outside-temp vs COP view.
    if (xk === "ot" && yk === "cop") {
        ds.push({ type: "line", data: trend(), borderColor: TREND, borderWidth: 2, pointRadius: 0, tension: 0.3, order: 1 });
    }

    var cfg = {
        data: { datasets: ds },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: 6 },
            // Click a bubble to open its dashboard view; only the bubble
            // dataset (index 0) carries an episode URL.
            onClick: function (e, el) {
                if (el.length && el[0].datasetIndex === 0) {
                    var u = pts[el[0].index]._d.u;
                    if (u) window.open(u, "_blank");
                }
            },
            onHover: function (e, el) {
                var over = el.length && el[0].datasetIndex === 0 && pts[el[0].index]._d.u;
                e.native.target.style.cursor = over ? "pointer" : "default";
            },
            scales: {
                x: { title: { display: true, text: F[xk], color: INK }, grid: { color: GRID }, ticks: { color: INK } },
                y: { title: { display: true, text: F[yk], color: INK }, grid: { color: GRID }, ticks: { color: INK } }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    filter: function (i) { return i.datasetIndex === 0; },
                    callbacks: {
                        title: function (i) { return i[0].raw._d.t || ""; },
                        label: function (i) {
                            var d = i.raw._d;
                            var a = [
                                "COP " + d.cop.toFixed(2) + "   " + d.el + " W   " + d.dur + " min",
                                "flow " + d.ft + "°C   out " + d.ot + "°C   lift " + d.lift + "°C   ΔT " + d.dt + "°C"
                            ];
                            if (d.u) a.push("click to open episode ↗");
                            return a;
                        }
                    }
                }
            }
        }
    };

    if (chart) {
        chart.data = cfg.data;
        chart.options = cfg.options;
        chart.update();
    } else {
        chart = new Chart(document.getElementById("sc"), cfg);
    }

    // Colour legend bar spanning the shown range of the colour variable.
    document.getElementById("cbar").innerHTML =
        "<span>" + F[ck] + "</span>" +
        '<span style="flex:1;max-width:200px;height:12px;border-radius:6px;background:linear-gradient(90deg,' +
        ramp(0) + "," + ramp(0.25) + "," + ramp(0.5) + "," + ramp(0.75) + "," + ramp(1) + ')"></span>' +
        "<span>" + fmt(ck, cmin) + " → " + fmt(ck, cmax) + "</span>";
}

// Recompute the view from the constraints and refresh every panel.
function refresh() {
    VIEW = applyFilters(DATA);
    var n = DATA.length, m = VIEW.length;
    document.getElementById("count").textContent = filters.length ? (m + " of " + n + " episodes shown") : (n + " episodes");
    cards();
    build();
    correlations();
    updateUrl();
}

// Redraw when the axis/colour selectors change.
["xs", "ys", "cs"].forEach(function (id) {
    document.getElementById(id).addEventListener("change", function () { build(); correlations(); updateUrl(); });
});
document.getElementById("addf").addEventListener("click", addFilter);
document.getElementById("clearf").addEventListener("click", function () { filters = []; renderFilters(); refresh(); });

// ---- Load episodes for the selected system -----------------------------
function loadSystem() {
    document.getElementById("err").textContent = "";
    document.getElementById("count").textContent = "Loading…";

    // Point the "Back to System" link at the current system (it sits outside
    // the Vue root, so its href is set here rather than via a binding).
    var back = document.getElementById("backbtn");
    if (back) back.href = path + "system/view?id=" + app.systemid;
    $.ajax({
        type: "GET",
        url: path + "signature/list",
        data: { id: app.systemid },
        dataType: "json",
        success: function (rows) {
            if (!rows || rows.error) {
                document.getElementById("err").textContent = (rows && rows.error) ? rows.error : "No data";
                DATA = [];
                refresh();
                return;
            }
            DATA = transform(rows);
            refresh();
        },
        error: function () {
            document.getElementById("err").textContent = "Failed to load episodes";
            DATA = [];
            refresh();
        }
    });
}

// ---- Vue: system selector ----------------------------------------------
var app = new Vue({
    el: '#app',
    data: {
        path: path,
        systemid: systemid,
        system_list: [],
        query: ""            // free-text search terms
    },
    computed: {
        // Systems matching all whitespace-separated search terms. A term of the
        // form "<number>kw" (e.g. "7kw", "8.5kw") is an exact badge-capacity
        // (hp_output) filter; every other term is a substring match across system
        // id, location, manufacturer, model and kW. Empty search = full list.
        // Drives both the dropdown options and the prev/next stepping.
        filtered: function () {
            var q = (this.query || "").trim().toLowerCase();
            if (!q) return this.system_list;
            var terms = q.split(/\s+/);
            return this.system_list.filter(function (s) {
                var hay = (s.id + " " + s.location + " " + s.hp_manufacturer + " " + s.hp_model + " " + s.hp_output + " kw").toLowerCase();
                var cap = parseFloat(s.hp_output);
                return terms.every(function (t) {
                    var m = t.match(/^(\d+(?:\.\d+)?)kw$/);
                    if (m) return cap === parseFloat(m[1]);   // exact badge capacity
                    return hay.indexOf(t) !== -1;
                });
            });
        }
    },
    methods: {
        // Display label for a system row.
        label: function (s) {
            return s.location + ", " + s.hp_manufacturer + " " + s.hp_model + ", " + s.hp_output + " kW";
        },
        // Dropdown selection changed (v-model has already set systemid).
        onSelect: function () {
            updateUrl();
            loadSystem();
        },
        // Step to the previous/next system within the filtered list. If the
        // current system isn't in the filtered list (e.g. just after typing a
        // new search), start at the first/last match.
        step: function (direction) {
            var list = this.filtered;
            if (!list.length) return;
            var idx = -1;
            for (var i = 0; i < list.length; i++) {
                if (list[i].id === this.systemid) { idx = i; break; }
            }
            idx = (idx === -1) ? (direction > 0 ? 0 : list.length - 1) : idx + direction;
            if (idx < 0) idx = 0;
            if (idx >= list.length) idx = list.length - 1;
            this.systemid = list[idx].id;
            updateUrl();
            loadSystem();
        }
    }
});

// Serialise the whole view (system, axes/colour, constraints) into the URL so
// the page can be shared or bookmarked. replaceState (not pushState) keeps the
// URL current without flooding history as constraint values are edited.
//   id = system id, x/y/c = axis & colour keys, f = constraints as
//   "prop:center:tol" joined by commas.
function updateUrl() {
    var url = new URL(window.location.href);
    var p = url.searchParams;
    p.set('id', app.systemid);
    p.set('x', document.getElementById('xs').value);
    p.set('y', document.getElementById('ys').value);
    p.set('c', document.getElementById('cs').value);
    if (filters.length) {
        p.set('f', filters.map(function (f) {
            return f.prop + ':' + f.center + ':' + f.tol;
        }).join(','));
    } else {
        p.delete('f');
    }
    window.history.replaceState({}, '', url);
}

// Restore axes/colour and constraints from the URL (system id comes via PHP as
// the initial systemid). Called once before the first load so a shared link
// opens in the same view. Invalid params are ignored.
function readUrlState() {
    var p = new URL(window.location.href).searchParams;
    var setSel = function (id, key) {
        var v = p.get(key);
        if (v && F[v]) document.getElementById(id).value = v;
    };
    setSel('xs', 'x');
    setSel('ys', 'y');
    setSel('cs', 'c');

    var f = p.get('f');
    if (f) {
        filters = f.split(',').map(function (part) {
            var bits = part.split(':');
            return { prop: bits[0], center: parseFloat(bits[1]), tol: parseFloat(bits[2]) };
        }).filter(function (c) {
            return F[c.prop] && !isNaN(c.center) && !isNaN(c.tol);
        });
        renderFilters();
    }
}

// Load the system list (public / user / admin), then the first system's data.
function load_system_list() {
    var system_list_url = path + "system/list/public.json";
    if (user_mode) system_list_url = path + "system/list/user.json";
    if (admin) system_list_url = path + "system/list/admin.json";

    $.ajax({
        type: "GET",
        url: system_list_url,
        dataType: "json",
        success: function (result) {
            result.sort(function (a, b) {
                if (a.location < b.location) return -1;
                if (a.location > b.location) return 1;
                return 0;
            });
            app.system_list = result;
            systemid_map = {};
            for (var z in result) systemid_map[result[z].id] = z;

            // Default to the first system if none selected or not in the list.
            if (!app.systemid || systemid_map[app.systemid] === undefined) {
                if (result.length) {
                    app.systemid = result[0].id;
                    updateUrl();
                }
            }
            loadSystem();
        }
    });
}

readUrlState();
load_system_list();
