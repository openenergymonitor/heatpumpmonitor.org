// Emitter capacity estimator.
//
// Expects these globals to be defined inline by the view before this file
// loads (they carry server-side values):
//     path      base URL path for API/asset requests
//     admin     1 if the current user is an admin, else 0
//     systemid  system id to open on load (0 = default to first system)
//
// For each steady-state signature episode with heat, return temp and room temp
// data, the emitter rated output at the rated ΔT is inferred by inverting the
// standard radiator equation:
//
//     heat = rated × (ΔT / ratedΔT)^n
//     ΔT   = (flowT + returnT) / 2 − roomT      (mean water temp minus room)
//
// Each estimate is weighted by the heat energy delivered over the episode
// (kWh), building a histogram of rated capacity vs energy delivered. The
// distribution's weighted mean/median and its peaks are reported: a household
// running weather compensation sweeps through a range of ΔT, and every episode
// should point back at the same installed capacity, so the histogram should
// pile up around the true value.

var user_mode = false;

// Chart canvas colours (canvas can't use CSS classes).
var INK = "#6b6a64";              // axis titles, ticks
var GRID = "rgba(0,0,0,.08)";    // gridlines
var BAR = "rgba(0,114,178,0.55)";   // histogram bars
var CURVE = "#eb6834";              // smoothed density curve and peak markers

var episodes = [];   // raw usable episodes for the current system
var chart;           // Chart.js instance (created lazily)

// ---- Parameters --------------------------------------------------------
// Read the parameter inputs, falling back to defaults on bad input.
function params() {
    var num = function (id, dflt) {
        var v = parseFloat(document.getElementById(id).value);
        return isNaN(v) ? dflt : v;
    };
    return {
        ratedDT: num("rdt", 50),
        n:       num("exp", 1.3),
        minDT:   num("mindt", 5),
        binW:    Math.max(0.05, num("binw", 0.5)),
        bw:      num("bw", 0)          // KDE bandwidth in kW; 0 = automatic
    };
}

// ---- Episode transform -------------------------------------------------
// Keep only episodes with the feeds the radiator equation needs, and carry the
// raw quantities; the rated-output estimate itself is recomputed in refresh()
// so parameter changes don't need a reload.
function transform(rows) {
    var out = [];
    for (var i = 0; i < rows.length; i++) {
        var r = rows[i];
        var heat = (r.heat === null || r.heat === undefined) ? NaN : +r.heat;
        var flowT = +r.flowT;
        var returnT = (r.returnT === null || r.returnT === undefined) ? NaN : +r.returnT;
        var roomT = (r.roomT === null || r.roomT === undefined) ? NaN : +r.roomT;
        var dur = +r.duration;
        if (isNaN(heat) || heat <= 0) continue;
        if (isNaN(flowT) || isNaN(returnT) || isNaN(roomT)) continue;
        out.push({
            start: +r.start_time,
            end: +r.end_time,
            dur: Math.round(dur / 60),                   // minutes
            heat: heat,                                  // W
            flowT: flowT,                                // °C
            returnT: returnT,                            // °C
            mwt: (flowT + returnT) / 2,                  // °C
            roomT: roomT,                                // °C
            kwh: heat * dur / 3.6e6                      // heat energy delivered
        });
    }
    return out;
}

// Rated output (kW at the rated ΔT) for one episode under parameters p, or NaN
// if the episode's radiator ΔT is below the minimum.
function ratedKw(e, p) {
    var dt = e.mwt - e.roomT;
    if (dt < p.minDT) return NaN;
    return (e.heat / Math.pow(dt / p.ratedDT, p.n)) / 1000;
}

// ---- Weighted statistics -----------------------------------------------
// pts: array of { kw, kwh }.
function weightedMean(pts) {
    var sw = 0, swx = 0;
    pts.forEach(function (d) { sw += d.kwh; swx += d.kwh * d.kw; });
    return sw > 0 ? swx / sw : NaN;
}

function weightedMedian(pts) {
    if (!pts.length) return NaN;
    var s = pts.slice().sort(function (a, b) { return a.kw - b.kw; });
    var tot = s.reduce(function (sum, d) { return sum + d.kwh; }, 0);
    var acc = 0;
    for (var i = 0; i < s.length; i++) {
        acc += s[i].kwh;
        if (acc >= tot / 2) return s[i].kw;
    }
    return s[s.length - 1].kw;
}

// Weighted percentile (0..1) of kw, by kwh weight.
function weightedPercentile(pts, q) {
    if (!pts.length) return NaN;
    var s = pts.slice().sort(function (a, b) { return a.kw - b.kw; });
    var tot = s.reduce(function (sum, d) { return sum + d.kwh; }, 0);
    var acc = 0;
    for (var i = 0; i < s.length; i++) {
        acc += s[i].kwh;
        if (acc >= tot * q) return s[i].kw;
    }
    return s[s.length - 1].kw;
}

// ---- Histogram ---------------------------------------------------------
// Bin points into fixed-width bins from 0 to a kWh-weighted 99th percentile
// cap (extreme low-ΔT episodes produce huge rated estimates that would
// otherwise stretch the axis). Returns bins plus the clipped tail's totals.
function makeHistogram(pts, binW) {
    var cap = weightedPercentile(pts, 0.99);
    var nbins = Math.max(1, Math.ceil(cap / binW));
    var kwh = new Array(nbins).fill(0);
    var count = new Array(nbins).fill(0);
    var clippedKwh = 0, clippedN = 0;
    pts.forEach(function (d) {
        var i = Math.floor(d.kw / binW);
        if (i >= nbins) { clippedKwh += d.kwh; clippedN++; return; }
        kwh[i] += d.kwh;
        count[i]++;
    });
    return { kwh: kwh, count: count, binW: binW, nbins: nbins, clippedKwh: clippedKwh, clippedN: clippedN };
}

// ---- Peak detection ----------------------------------------------------
// Peaks are found on a kWh-weighted gaussian kernel density estimate of the
// rated-output samples, not on the raw histogram bars: the KDE is independent
// of bin edges, so binning noise disappears. Candidate peaks are then ranked
// by PROMINENCE — how far a peak rises above the highest saddle connecting it
// to taller terrain — which is what separates a genuinely distinct mode from
// a noisy shoulder on the flank of a bigger peak.

// Weighted gaussian KDE of pts (by kwh weight), evaluated at m evenly spaced
// grid points spanning [0, xmax]. Returns { x: grid, d: density } with the
// density normalised so it integrates to 1 over all points.
function kdeCurve(pts, bw, xmax, m) {
    var xs = new Array(m);
    var d = new Array(m).fill(0);
    for (var i = 0; i < m; i++) xs[i] = (i + 0.5) * xmax / m;
    var totW = 0;
    pts.forEach(function (p) { totW += p.kwh; });
    if (totW <= 0 || bw <= 0) return { x: xs, d: d };
    var inv = 1 / (bw * Math.sqrt(2 * Math.PI));
    pts.forEach(function (p) {
        // gaussians decay fast: only evaluate within ±4 bandwidths
        var lo = Math.max(0, Math.floor((p.kw - 4 * bw) / xmax * m));
        var hi = Math.min(m - 1, Math.ceil((p.kw + 4 * bw) / xmax * m));
        for (var i = lo; i <= hi; i++) {
            var z = (xs[i] - p.kw) / bw;
            d[i] += p.kwh * inv * Math.exp(-0.5 * z * z);
        }
    });
    for (var i = 0; i < m; i++) d[i] /= totW;
    return { x: xs, d: d };
}

// Automatic KDE bandwidth: Silverman's rule with the effective sample size of
// the kWh weights, halved because Silverman's rule assumes a single gaussian
// and over-smooths multimodal data. Never narrower than half a display bin.
function autoBandwidth(pts, binW) {
    var sw = 0, swx = 0, swx2 = 0, sw2 = 0;
    pts.forEach(function (p) {
        sw += p.kwh; swx += p.kwh * p.kw; swx2 += p.kwh * p.kw * p.kw; sw2 += p.kwh * p.kwh;
    });
    if (sw <= 0) return binW;
    var mean = swx / sw;
    var v = swx2 / sw - mean * mean;
    var sigma = v > 0 ? Math.sqrt(v) : 0;
    var neff = sw * sw / sw2;
    var h = 0.53 * sigma * Math.pow(neff, -0.2);
    return Math.max(binW / 2, h || binW);
}

// Prominence of the local maximum at grid index i: height above the higher of
// the two saddles found by walking out on each side until terrain taller than
// the peak is met (or the edge of the grid).
function peakProminence(d, i) {
    var h = d[i];
    var minL = h, minR = h;
    var j;
    for (j = i - 1; j >= 0 && d[j] <= h; j--) if (d[j] < minL) minL = d[j];
    for (j = i + 1; j < d.length && d[j] <= h; j++) if (d[j] < minR) minR = d[j];
    return h - Math.max(minL, minR);
}

// Find up to 4 peaks of the KDE with prominence of at least 10% of the tallest
// density. Each peak's kWh is the energy of the episodes falling between the
// density minima that separate it from its neighbouring peaks, so the shares
// of the reported peaks sum to the total. Returns { peaks, curve }.
function findPeaks(pts, bw, xmax) {
    var m = 512;
    var k = kdeCurve(pts, bw, xmax, m);
    var d = k.d;
    var dmax = Math.max.apply(null, d);
    if (!(dmax > 0)) return { peaks: [], curve: k };

    var cand = [];
    for (var i = 1; i < m - 1; i++) {
        if (d[i] > d[i - 1] && d[i] >= d[i + 1]) {
            var prom = peakProminence(d, i);
            if (prom >= 0.1 * dmax) cand.push({ idx: i, kw: k.x[i], height: d[i], prom: prom });
        }
    }
    cand.sort(function (a, b) { return b.prom - a.prom; });
    var kept = cand.slice(0, 4).sort(function (a, b) { return a.kw - b.kw; });

    // attribution boundaries: the density minimum between adjacent kept peaks
    var bounds = [0];
    for (var i = 0; i < kept.length - 1; i++) {
        var lo = kept[i].idx, hi = kept[i + 1].idx, minj = lo;
        for (var j = lo; j <= hi; j++) if (d[j] < d[minj]) minj = j;
        bounds.push(k.x[minj]);
    }
    bounds.push(Infinity);
    kept.forEach(function (p, i) {
        p.kwh = 0;
        pts.forEach(function (q) {
            if (q.kw >= bounds[i] && q.kw < bounds[i + 1]) p.kwh += q.kwh;
        });
    });
    return { peaks: kept, curve: k };
}

// ---- Summary cards -----------------------------------------------------
function cards(pts, totKwh) {
    var box = document.getElementById("cards");
    if (!pts.length) { box.innerHTML = ""; return; }
    var c = [
        ["Episodes used", pts.length],
        ["Heat delivered", totKwh.toFixed(0) + " kWh"],
        ["Weighted mean", weightedMean(pts).toFixed(1) + " kW"],
        ["Weighted median", weightedMedian(pts).toFixed(1) + " kW"]
    ];
    box.innerHTML = c.map(function (x) {
        return '<div class="col-6 col-md-3">' +
            '<div class="card h-100"><div class="card-body py-2 px-3">' +
            '<div class="text-muted small">' + x[0] + '</div>' +
            '<div class="fs-4">' + x[1] + '</div>' +
            '</div></div></div>';
    }).join("");
}

// ---- Peak panel --------------------------------------------------------
function peaksPanel(peaks, totKwh, bw) {
    var head = document.getElementById("peakhead");
    var chips = document.getElementById("peakchips");
    if (!peaks.length) {
        head.textContent = "No peaks detected.";
        chips.innerHTML = "";
        return;
    }
    var smoothed = " (kernel-smoothed, σ = " + bw.toFixed(1) + " kW)";
    head.textContent = (peaks.length === 1)
        ? "The distribution has a single dominant peak" + smoothed + " — a consistent estimate of installed emitter capacity:"
        : "The distribution has " + peaks.length + " distinct peaks" + smoothed + ". Multiple peaks can indicate zoning, " +
          "part of the emitter circuit closing off (TRVs), or a mix of emitter types:";
    chips.innerHTML = peaks.map(function (p) {
        var share = totKwh > 0 ? Math.round(100 * p.kwh / totKwh) : 0;
        return '<span class="d-inline-flex align-items-center gap-1 px-2 py-1 rounded-pill bg-light border small">' +
            "<b>" + p.kw.toFixed(1) + " kW</b> &middot; " + share + "% of heat</span>";
    }).join("");
}

// ---- Bin episode sample ------------------------------------------------
// Clicking a histogram bar lists up to 10 representative episodes from that
// bin (largest heat delivered first), each linking to the dashboard so the
// underlying data can be inspected — same click-through as the signature tool.
var LAST = null;   // { h, pts } from the last refresh, consumed by selectBin()

// Format a unix timestamp as "YYYY-MM-DD HH:MM" in local time.
function fmtDate(ts) {
    var d = new Date(ts * 1000);
    var p = function (n) { return String(n).padStart(2, "0"); };
    return d.getFullYear() + "-" + p(d.getMonth() + 1) + "-" + p(d.getDate()) +
        " " + p(d.getHours()) + ":" + p(d.getMinutes());
}

function hideBinPanel() {
    document.getElementById("binpanel").style.display = "none";
}

function selectBin(b) {
    if (!LAST || !app.current) return;
    var h = LAST.h;
    var lo = b * h.binW, hi = (b + 1) * h.binW;
    var inBin = LAST.pts.filter(function (d) { return d.kw >= lo && d.kw < hi; });
    if (!inBin.length) { hideBinPanel(); return; }
    inBin.sort(function (a, c) { return c.kwh - a.kwh; });
    var sample = inBin.slice(0, 10);
    var sysid = app.current.id;

    document.getElementById("binhead").innerHTML =
        "Episodes rated <b>" + lo.toFixed(2) + " – " + hi.toFixed(2) + " kW</b>: showing " +
        sample.length + " of " + inBin.length + " (largest heat delivered first). Click a row to open it in the dashboard.";

    var rows = sample.map(function (d) {
        var e = d.e;
        var u = path + "dashboard?id=" + sysid + "&mode=power&start=" + e.start + "&end=" + e.end;
        return '<tr style="cursor:pointer" onclick="window.open(\'' + u + '\', \'_blank\')">' +
            "<td>" + fmtDate(e.start) + "</td>" +
            "<td>" + e.dur + " min</td>" +
            "<td>" + Math.round(e.heat) + " W</td>" +
            "<td>" + e.flowT.toFixed(1) + " / " + e.returnT.toFixed(1) + "°C</td>" +
            "<td>" + e.roomT.toFixed(1) + "°C</td>" +
            "<td>" + (e.mwt - e.roomT).toFixed(1) + " K</td>" +
            "<td>" + d.kw.toFixed(2) + " kW</td>" +
            "<td>" + d.kwh.toFixed(2) + " kWh</td>" +
            '<td class="text-primary">open ↗</td></tr>';
    }).join("");

    document.getElementById("bintable").innerHTML =
        '<table class="table table-sm table-hover small mb-0"><thead><tr class="text-muted">' +
        "<th>Start</th><th>Duration</th><th>Heat</th><th>Flow / return</th><th>Room</th>" +
        "<th>Radiator ΔT</th><th>Rated</th><th>Delivered</th><th></th>" +
        "</tr></thead><tbody>" + rows + "</tbody></table>";
    document.getElementById("binpanel").style.display = "";
}

// ---- Chart -------------------------------------------------------------
function build(h, peaks, lineData) {
    var labels = [];
    for (var i = 0; i < h.nbins; i++) labels.push(((i + 0.5) * h.binW).toFixed(2));
    var peakBins = {};
    peaks.forEach(function (p) {
        peakBins[Math.min(h.nbins - 1, Math.floor(p.kw / h.binW))] = true;
    });

    var cfg = {
        type: "bar",
        data: {
            labels: labels,
            datasets: [{
                // smoothed density (scaled to expected kWh per bin), with a
                // marker at each detected peak
                type: "line",
                data: lineData,
                borderColor: CURVE,
                borderWidth: 2,
                tension: 0.3,
                pointRadius: lineData.map(function (_, i) { return peakBins[i] ? 5 : 0; }),
                pointBackgroundColor: CURVE,
                pointBorderColor: "#fff",
                pointBorderWidth: 1,
                order: 1
            }, {
                data: h.kwh,
                backgroundColor: BAR,
                borderWidth: 0,
                barPercentage: 1.0,
                categoryPercentage: 0.92,
                order: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: 6 },
            // Click a bar to list a sample of that bin's episodes below the chart.
            onClick: function (e, el) {
                if (el.length && el[0].datasetIndex === 1) selectBin(el[0].index);
            },
            onHover: function (e, el) {
                var over = el.length && el[0].datasetIndex === 1;
                e.native.target.style.cursor = over ? "pointer" : "default";
            },
            scales: {
                x: {
                    title: { display: true, text: "Emitter rated output at ΔT" + params().ratedDT + " (kW)", color: INK },
                    grid: { display: false },
                    ticks: {
                        color: INK, maxRotation: 0, autoSkip: true,
                        // label whole-kW positions only, to keep the axis readable
                        callback: function (v) {
                            var x = parseFloat(this.getLabelForValue(v));
                            return Math.abs(x - Math.round(x)) < h.binW / 2 ? Math.round(x) : "";
                        }
                    }
                },
                y: {
                    title: { display: true, text: "Heat delivered (kWh)", color: INK },
                    grid: { color: GRID }, ticks: { color: INK },
                    beginAtZero: true
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    filter: function (i) { return i.datasetIndex === 1; },
                    callbacks: {
                        title: function (i) {
                            var b = i[0].dataIndex;
                            return (b * h.binW).toFixed(2) + " – " + ((b + 1) * h.binW).toFixed(2) + " kW";
                        },
                        label: function (i) {
                            var b = i.dataIndex;
                            return h.kwh[b].toFixed(1) + " kWh over " + h.count[b] + " episodes";
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
        chart = new Chart(document.getElementById("hist"), cfg);
    }
}

// ---- Refresh -----------------------------------------------------------
// Recompute rated-output estimates from the current parameters and redraw.
function refresh() {
    var p = params();

    var pts = [];
    var belowDT = 0;
    episodes.forEach(function (e) {
        var kw = ratedKw(e, p);
        if (isNaN(kw)) { belowDT++; return; }
        pts.push({ kw: kw, kwh: e.kwh, e: e });
    });

    var msg = "";
    if (app.current) {
        msg = pts.length + " usable episodes";
        if (belowDT) msg += " (" + belowDT + " below min ΔT)";
    }
    document.getElementById("count").textContent = msg;

    // any previous bin selection no longer matches the new binning/estimates
    hideBinPanel();

    var totKwh = pts.reduce(function (s, d) { return s + d.kwh; }, 0);
    cards(pts, totKwh);

    if (!pts.length) {
        if (chart) { chart.data = { labels: [], datasets: [] }; chart.update(); }
        document.getElementById("peakhead").textContent = "";
        document.getElementById("peakchips").innerHTML = "";
        if (app.current) {
            document.getElementById("err").textContent =
                "No usable episodes — this system needs heat, return temp and room temp episode data.";
        }
        return;
    }

    var h = makeHistogram(pts, p.binW);
    LAST = { h: h, pts: pts };
    var xmax = h.nbins * h.binW;
    var bw = p.bw > 0 ? p.bw : autoBandwidth(pts, p.binW);
    var pk = findPeaks(pts, bw, xmax);
    // KDE evaluated at the bin centres, scaled from density to expected kWh
    // per bin so the curve overlays the histogram bars directly
    var curveBins = kdeCurve(pts, bw, xmax, h.nbins);
    var lineData = curveBins.d.map(function (dd) { return dd * totKwh * h.binW; });
    build(h, pk.peaks, lineData);
    peaksPanel(pk.peaks, totKwh, bw);

    if (h.clippedN) {
        document.getElementById("count").textContent =
            msg + ", " + h.clippedN + " above chart range (" + h.clippedKwh.toFixed(1) + " kWh)";
    }
}

["rdt", "exp", "mindt", "binw", "bw"].forEach(function (id) {
    document.getElementById(id).addEventListener("input", refresh);
});
document.getElementById("binclose").addEventListener("click", hideBinPanel);

// ---- Load a system's episodes ------------------------------------------
function loadSystemData(id) {
    document.getElementById("err").textContent = "";
    document.getElementById("count").textContent = "Loading…";
    $.ajax({
        type: "GET",
        url: path + "signature/list",
        data: { id: id },
        dataType: "json",
        success: function (rows) {
            if (!rows || rows.error) {
                document.getElementById("err").textContent = (rows && rows.error) ? rows.error : "No data";
                episodes = [];
            } else {
                episodes = transform(rows);
            }
            refresh();
        },
        error: function () {
            document.getElementById("err").textContent = "Failed to load episodes";
            episodes = [];
            refresh();
        }
    });
}

// ---- Vue: system control panel -----------------------------------------
var app = new Vue({
    el: '#app',
    data: {
        path: path,
        candidate: null,     // system id currently chosen in the dropdown
        current: null,       // system row currently loaded
        system_list: [],
        query: ""            // free-text search terms
    },
    computed: {
        // Systems matching all whitespace-separated search terms. A term of the
        // form "<number>kw" (e.g. "7kw", "8.5kw") is an exact badge-capacity
        // (hp_output) filter; every other term is a substring match across system
        // id, location, manufacturer, model and kW. Empty search = full list.
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
        // Open a system by id (ignored if unknown).
        open: function (id) {
            if (id === null || id === undefined) return;
            var sys = null;
            for (var i = 0; i < this.system_list.length; i++) {
                if (this.system_list[i].id === id) { sys = this.system_list[i]; break; }
            }
            if (!sys) return;
            this.candidate = id;
            this.current = sys;
            updateUrl();
            updateButtons();
            loadSystemData(id);
        },
        // Step through the filtered list, loading each system as we go.
        step: function (direction) {
            var list = this.filtered;
            if (!list.length) return;
            var idx = -1;
            for (var i = 0; i < list.length; i++) {
                if (list[i].id === this.candidate) { idx = i; break; }
            }
            idx = (idx === -1) ? (direction > 0 ? 0 : list.length - 1) : idx + direction;
            if (idx < 0) idx = 0;
            if (idx >= list.length) idx = list.length - 1;
            this.open(list[idx].id);
        }
    }
});

// Keep ?id= in the URL so the page can be shared or bookmarked.
function updateUrl() {
    var url = new URL(window.location.href);
    if (app.current) url.searchParams.set('id', app.current.id);
    window.history.replaceState({}, '', url);
}

// Point the footer buttons at the current system.
function updateButtons() {
    var sig = document.getElementById("sigbtn");
    var back = document.getElementById("backbtn");
    if (app.current) {
        sig.style.display = "";
        back.style.display = "";
        sig.href = path + "signature?id=" + app.current.id;
        back.href = path + "system/view?id=" + app.current.id;
    } else {
        sig.style.display = "none";
        back.style.display = "none";
    }
}

// System id to open on load, from ?id= falling back to the PHP default.
function urlId() {
    var p = new URL(window.location.href).searchParams;
    var raw = p.get('id') || (systemid ? String(systemid) : '');
    var n = parseInt(raw, 10);
    return isNaN(n) ? 0 : n;
}

// Load the system list (public / user / admin), keeping only systems that have
// signature episodes (via signature/systems), then open the requested system.
function load_system_list() {
    var system_list_url = path + "system/list/public.json";
    if (user_mode) system_list_url = path + "system/list/user.json";
    if (admin) system_list_url = path + "system/list/admin.json";

    $.ajax({
        type: "GET",
        url: path + "signature/systems.json",
        dataType: "json",
        success: function (systems) {
            var counts = {};
            if (Array.isArray(systems)) {
                systems.forEach(function (s) { counts[s.system_id] = s.count; });
            }
            load_filtered_system_list(system_list_url, counts);
        },
        error: function () {
            load_filtered_system_list(system_list_url, null);
        }
    });
}

function load_filtered_system_list(system_list_url, counts) {
    $.ajax({
        type: "GET",
        url: system_list_url,
        dataType: "json",
        success: function (result) {
            if (counts) {
                result = result.filter(function (s) { return counts[s.id] > 0; });
            }
            result.sort(function (a, b) {
                if (a.location < b.location) return -1;
                if (a.location > b.location) return 1;
                return 0;
            });
            app.system_list = result;

            var id = urlId();
            var known = result.some(function (s) { return s.id === id; });
            if (!known && result.length) id = result[0].id;
            if (result.length) app.open(id);
        }
    });
}

updateButtons();
load_system_list();
