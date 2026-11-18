<?php
defined('EMONCMS_EXEC') or die('Restricted access');
?>
<script src="<?php echo $path; ?>theme/vendor/vue-2.7.16/vue.min.js" integrity="sha384-YVYXhPGIH/Gmcr0W5Rin4PcpcsG1a4pcdUUod1CnbDEJut7XiUaJtSlNKeRLJBPk"></script>
<script src="<?php echo $path; ?>theme/vendor/chart.js-4.4.1/chart.umd.js" integrity="sha384-dug+JxfBvklEQdJ4AYuBBAIScUz0bVN73xpy273gcAwHjb3qI0fXmuYNaNfdyYJG"></script>

<style>
    .ot-card { background:#f7f7f7; border-radius:6px; padding:10px 12px; margin-bottom:12px; height:calc(100% - 12px); }
    .ot-card .lbl { font-size:12px; color:#666; }
    .ot-card .val { font-size:22px; font-weight:600; }
    .ot-card .sub { font-size:12px; color:#888; }
    .ot-chart { position:relative; height:380px; margin-bottom:8px; }
    .ot-note { font-size:13px; color:#666; margin-bottom:28px; }
</style>

<div id="app">
    <div style="background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container-fluid" style="max-width:1320px">
            <h3>Heat by outside temperature <small class="text-muted" style="font-size:14px">prototype</small></h3>
            <p class="text-muted" style="margin-bottom:0">How a system's heat and electricity are spread across outside temperature, and a simple physical model fitted to a window of days and run over a full reference year.</p>
        </div>
    </div>

    <div class="container-fluid" style="margin-top:20px; max-width:1320px">
        <div class="row">
            <div class="col-lg-3">
                <div class="input-group mb-3">
                    <span class="input-group-text">Search</span>
                    <input type="text" class="form-control" v-model="query" placeholder="e.g. vaillant 7kw" title="Space-separated terms matched against id, location, manufacturer and model. A term like 7kw matches badge capacity exactly.">
                </div>
            </div>
            <div class="col-lg-5">
                <div class="input-group mb-3">
                    <span class="input-group-text">System <span class="text-muted ms-1" v-if="query">({{ filtered.length }})</span></span>
                    <select class="form-control" v-model="systemid" @change="load">
                        <option v-for="s in filtered" :value="s.id">{{ s.id }}: {{ s.location }}, {{ s.hp_manufacturer }} {{ s.hp_model }}, {{ s.hp_output }} kW</option>
                    </select>
                    <button class="btn btn-primary" @click="next_system(-1)">&lt;</button>
                    <button class="btn btn-primary" @click="next_system(1)">&gt;</button>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="input-group mb-3">
                    <span class="input-group-text">Reference year</span>
                    <select class="form-control" v-model.number="ref_offset" @change="change_ref">
                        <option v-for="o in ref_options" :value="o.offset">{{ o.label }}</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="row" v-if="ndays">
            <div class="col-lg-3">
                <div class="input-group mb-3">
                    <span class="input-group-text">Fit window</span>
                    <select class="form-control" v-model.number="win_len" @change="update(false)">
                        <option :value="0">Reference year</option>
                        <option v-for="n in [7,14,30,60,90,120,180,240]" :value="n">{{ n }} days</option>
                    </select>
                </div>
            </div>
            <div class="col-lg-6" v-if="win_len">
                <div class="input-group mb-3">
                    <span class="input-group-text">Starting {{ win_start_label }}</span>
                    <input type="range" class="form-range form-control" style="padding:12px" min="0" :max="win_start_max" v-model.number="win_start" @input="update(false)">
                </div>
            </div>
            <div class="col-lg-3">
                <div class="input-group mb-3">
                    <span class="input-group-text">Bin width</span>
                    <select class="form-control" v-model.number="bin_width" @change="update(false)">
                        <option :value="1">1 K</option>
                        <option :value="2">2 K</option>
                    </select>
                </div>
            </div>
        </div>

        <div v-if="status" class="alert alert-info">{{ status }}</div>
        <div v-if="coverage && !status" class="alert alert-light border" style="padding:8px 12px; font-size:14px">{{ coverage }}</div>
        <div v-for="w in warnings" class="alert alert-warning" style="padding:8px 12px">{{ w }}</div>

        <div v-show="ndays && !status">
            <!-- Summary -->
            <div class="row" v-if="s">
                <div class="col-6 col-lg-2"><div class="ot-card">
                    <div class="lbl">SPF, fit window</div>
                    <div class="val">{{ fmt(s.win_spf, 2) }}</div>
                    <div class="sub">{{ s.win_days }} days, {{ fmt(s.win_heat, 0) }} kWh heat</div>
                </div></div>
                <div class="col-6 col-lg-2"><div class="ot-card" style="background:#eef4ea">
                    <div class="lbl">Projected SPF, reference year</div>
                    <div class="val">{{ fmt(s.proj_spf, 2) }}</div>
                    <div class="sub">{{ fmt(s.proj_heat, 0) }} kWh heat modelled over {{ s.proj_days }} days</div>
                </div></div>
                <div class="col-6 col-lg-2"><div class="ot-card">
                    <div class="lbl">Measured SPF, reference year</div>
                    <div class="val">{{ fmt(s.ref_spf, 2) }}</div>
                    <div class="sub">{{ s.ref_days }} days, {{ fmt(s.ref_heat, 0) }} kWh heat<span v-if="s.proj_err !== null">; model on the same days {{ s.proj_err >= 0 ? '+' : '' }}{{ fmt(s.proj_err, 1) }}%</span></div>
                </div></div>
                <div class="col-6 col-lg-2"><div class="ot-card">
                    <div class="lbl">Heat-median outside temp</div>
                    <div class="val">{{ fmt(s.ref_tmed, 1) }}&deg;C</div>
                    <div class="sub"><span v-if="s.tmed_modelled">modelled year; </span>measured {{ fmt(s.meas_tmed, 1) }}&deg;C, fit window {{ fmt(s.win_tmed, 1) }}&deg;C</div>
                </div></div>
                <div class="col-6 col-lg-2"><div class="ot-card">
                    <div class="lbl">Flow &minus; outside at heat-median</div>
                    <div class="val">{{ fmt(s.dt_med, 1) }} K</div>
                    <div class="sub" v-if="s.flow_med !== null">flow {{ fmt(s.flow_med, 1) }}&deg;C from the fitted line</div>
                </div></div>
                <div class="col-6 col-lg-2"><div class="ot-card">
                    <div class="lbl">{{ split ? 'Space heating' : 'Heat' }} line</div>
                    <div class="val">{{ fmt(s.k_wk, 0) }} W/K</div>
                    <div class="sub">balance {{ fmt(s.tb, 1) }}&deg;C<span v-if="s.tb_fixed"> (assumed)</span><span v-if="s.eta">, &eta; {{ fmt(s.eta * 100, 0) }}% of Carnot</span><span v-else>, linear COP fit</span></div>
                </div></div>
            </div>

            <!-- Histogram -->
            <div class="row">
                <div class="col-lg-8"><h5 style="margin-top:10px">{{ hist_title }}</h5></div>
                <div class="col-lg-4">
                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text">Show</span>
                        <select class="form-control" v-model="hist_mode" @change="draw_hist">
                            <option value="heat">Heat delivered</option>
                            <option value="elec">Electricity used</option>
                            <option value="days">Days</option>
                            <option value="cop">COP per bin</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="ot-chart"><canvas id="hist"></canvas></div>
            <div class="ot-note">
                Bars are the measured days of the reference year, binned by daily mean outside temperature. Solid colour marks days inside the fit window, pale colour days outside it. The black line is the model fitted to the window and run over all 365 days of the reference year; days the system didn't measure take their outside temperature from nearby systems (see "Days"). The dashed vertical line is the heat-median outside temperature of the modelled year: half the year's heat is delivered on colder days.
            </div>

            <div class="row">
                <div class="col-lg-6">
                    <h5>Daily heat vs outside temperature</h5>
                    <div class="ot-chart"><canvas id="heat_scatter"></canvas></div>
                    <div class="ot-note">
                        {{ split ? 'Space heating' : 'Heat' }} per day is fitted as k&nbsp;&times;&nbsp;(balance&nbsp;&minus;&nbsp;outside), zero above the balance temperature. If the window has fewer than 7 days above 12&deg;C the balance is fixed at 15.5&deg;C. <span v-if="split">Hot water is a line in outside temperature if the window spans 10&nbsp;K or more, otherwise a constant.</span>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="row">
                        <div class="col-6"><h5>Daily {{ scatter2_title }}</h5></div>
                        <div class="col-6">
                            <div class="input-group input-group-sm mb-2">
                                <select class="form-control" v-model="scatter2_mode" @change="draw_scatter2">
                                    <option value="cop">COP</option>
                                    <option value="flow">Flow temperature</option>
                                    <option value="dt">Flow &minus; outside</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="ot-chart"><canvas id="scatter2"></canvas></div>
                    <div class="ot-note">
                        COP is modelled from Carnot: a heat-weighted line through daily flow temperature (condensing at flow&nbsp;+&nbsp;2, evaporating at outside&nbsp;&minus;&nbsp;6) and a single practical efficiency &eta; that matches the window's electricity. Without flow temperatures it falls back to a heat-weighted line through daily COP.
                        <span v-if="s">Fitted &eta;: {{ split ? 'space heating ' : '' }}<b>{{ s.eta !== null ? fmt(s.eta * 100, 1) + '%' : 'linear fit' }}</b><span v-if="split">, hot water <b>{{ s.eta_w !== null ? fmt(s.eta_w * 100, 1) + '%' : (s.cop_w_linear ? 'linear fit' : '–') }}</b></span> of Carnot.</span>
                        Standby electricity is a constant per day ({{ s ? fmt(s.idle, 2) : '' }} kWh).
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Outside temperature model — shared between node test and the view
var OTModel = (function () {
    var COND = 2.0, EVAP = -6.0;

    function num(v) { return (v === '' || v === undefined || v === null) ? null : v * 1; }

    // Parse the system/stats/daily CSV into day objects
    function parse(csv) {
        var lines = csv.trim().split('\n');
        var f = lines[0].split(',').map(function (s) { return s.trim(); });
        var rows = [];
        for (var i = 1; i < lines.length; i++) {
            var p = lines[i].split(',');
            if (p.length != f.length) continue;
            var r = {};
            for (var j = 0; j < f.length; j++) r[f[j]] = num(p[j]);
            rows.push(r);
        }
        var split = rows.some(function (r) { return r.water_heat_kwh > 0; });
        var days = [];
        rows.forEach(function (r) {
            var H = r.combined_heat_kwh || 0, E = r.combined_elec_kwh || 0;
            var Hw = split ? (r.water_heat_kwh || 0) : 0;
            var Ew = split ? (r.water_elec_kwh || 0) : 0;
            var Hs = split ? (r.space_heat_kwh !== null ? r.space_heat_kwh : Math.max(0, H - Hw)) : H;
            var Es = split ? (r.space_elec_kwh || 0) : E;
            if (Hs < 0.3) Es = 0; // days without meaningful space heat: elec counts as idle
            var idle = Math.max(0, E - Es - Ew);
            days.push({
                ts: r.timestamp, t: r.combined_outsideT_mean,
                ok: r.combined_data_length >= 0.9 * 86400 && r.combined_outsideT_mean !== null,
                H: H, E: E, Hs: Hs, Es: Es, Hw: Hw, Ew: Ew, idle: idle,
                flowS: split ? r.space_flowT_mean : (r.space_flowT_mean || r.running_flowT_mean),
                flowW: r.water_flowT_mean
            });
        });
        return { days: days, split: split };
    }

    // Weighted least squares y = a + b x
    function wls(xs, ys, ws) {
        var sw = 0, sx = 0, sy = 0, sxx = 0, sxy = 0;
        for (var i = 0; i < xs.length; i++) {
            var w = ws ? ws[i] : 1;
            sw += w; sx += w * xs[i]; sy += w * ys[i]; sxx += w * xs[i] * xs[i]; sxy += w * xs[i] * ys[i];
        }
        if (sw <= 0) return null;
        var d = sw * sxx - sx * sx;
        if (Math.abs(d) < 1e-9 * sw * sw) return { a: sy / sw, b: 0 };
        var b = (sw * sxy - sx * sy) / d;
        return { a: (sy - b * sx) / sw, b: b };
    }

    function carnot(flow, t) {
        var tc = flow + COND, te = t + EVAP, lift = Math.max(tc - te, 5);
        return (tc + 273.15) / lift;
    }

    // Space heat: hinge H = k * max(0, Tb - T), grid search on Tb
    // Balance temperature is only fitted if the window has mild days to pin
    // it down; otherwise it is fixed at a typical value (TB_PRIOR).
    var TB_PRIOR = 15.5;
    function fitHinge(days) {
        var mild = days.filter(function (d) { return d.t > 12; }).length;
        var lo = 12, hi = 20;
        if (mild < 7) lo = hi = TB_PRIOR;
        var best = null;
        for (var tb = lo; tb <= hi + 0.001; tb += 0.1) {
            var sxy = 0, sxx = 0;
            days.forEach(function (d) { var x = Math.max(0, tb - d.t); sxy += x * d.Hs; sxx += x * x; });
            if (sxx <= 0) continue;
            var k = sxy / sxx, sse = 0;
            days.forEach(function (d) { var e = d.Hs - k * Math.max(0, tb - d.t); sse += e * e; });
            if (!best || sse < best.sse) best = { tb: tb, k: k, sse: sse };
        }
        if (best) best.fixed = (lo == hi);
        return best || { tb: TB_PRIOR, k: 0, fixed: true };
    }

    // COP model for one mode: Carnot from a flow temperature line and a fitted
    // practical efficiency, or a heat-weighted linear COP fit if no flow temps.
    function fitCOP(days, Hk, Ek, Fk) {
        var use = days.filter(function (d) { return d[Hk] > 0.5 && d[Ek] > 0; });
        if (!use.length) return null;
        var withFlow = use.filter(function (d) { return d[Fk] > 0; });
        if (withFlow.length >= 3 && withFlow.length >= 0.5 * use.length) {
            var line = wls(withFlow.map(function (d) { return d.t; }), withFlow.map(function (d) { return d[Fk]; }), withFlow.map(function (d) { return d[Hk]; }));
            var a = 0, e = 0;
            withFlow.forEach(function (d) { a += d[Hk] / carnot(d[Fk], d.t); e += d[Ek]; });
            var eta = a / e;
            return { type: 'carnot', f0: line.a, f1: line.b, eta: eta,
                     cop: function (t) { return eta * carnot(line.a + line.b * t, t); },
                     flow: function (t) { return line.a + line.b * t; } };
        }
        var lin = wls(use.map(function (d) { return d.t; }), use.map(function (d) { return d[Hk] / d[Ek]; }), use.map(function (d) { return d[Hk]; }));
        return { type: 'linear', a: lin.a, b: lin.b, cop: function (t) { return Math.max(1, lin.a + lin.b * t); }, flow: null };
    }

    function fit(days) {
        days = days.filter(function (d) { return d.ok; });
        if (days.length < 3) return null;
        var hinge = fitHinge(days);
        // Hot water: a line in outside temperature if the window spans a
        // wide enough range, otherwise a constant daily mean.
        var ts = days.map(function (d) { return d.t; });
        var range = Math.max.apply(null, ts) - Math.min.apply(null, ts);
        var water = range >= 10 ? wls(ts, days.map(function (d) { return d.Hw; }))
                                : { a: sum(days, function (d) { return d.Hw; }) / days.length, b: 0 };
        var idle = days.reduce(function (s, d) { return s + d.idle; }, 0) / days.length;
        var m = {
            n: days.length, hinge: hinge, water: water, idle: idle,
            copS: fitCOP(days, 'Hs', 'Es', 'flowS'),
            copW: fitCOP(days, 'Hw', 'Ew', 'flowW')
        };
        m.heatS = function (t) { return hinge.k * Math.max(0, hinge.tb - t); };
        m.heatW = function (t) { return Math.max(0, water.a + water.b * t); };
        m.day = function (t) {
            var hs = m.heatS(t), hw = m.heatW(t);
            var es = hs > 0 ? (m.copS ? hs / m.copS.cop(t) : 0) : 0;
            var ew = hw > 0 ? (m.copW ? hw / m.copW.cop(t) : 0) : 0;
            return { Hs: hs, Hw: hw, Es: es, Ew: ew, idle: idle };
        };
        return m;
    }

    function sum(days, f) { return days.reduce(function (s, d) { return s + f(d); }, 0); }

    // Heat-weighted median outside temperature
    function heatMedianT(days) {
        var ds = days.filter(function (d) { return d.ok && d.H > 0; }).sort(function (a, b) { return a.t - b.t; });
        var tot = sum(ds, function (d) { return d.H; }), acc = 0;
        for (var i = 0; i < ds.length; i++) { acc += ds[i].H; if (acc >= tot / 2) return ds[i].t; }
        return null;
    }

    return { parse: parse, fit: fit, wls: wls, carnot: carnot, heatMedianT: heatMedianT, sum: sum };
})();


var COL = {
    space: 'rgba(232,116,59,1)', space_lt: 'rgba(232,116,59,0.28)',
    water: 'rgba(52,120,200,1)', water_lt: 'rgba(52,120,200,0.28)',
    idle: 'rgba(120,120,120,1)', idle_lt: 'rgba(120,120,120,0.28)',
    model: '#222'
};
var DAY = 86400;
var charts = {};
var DAYS = []; // the system's own days, kept outside Vue so they aren't made reactive
var REF = [];  // the 365 calendar days of the reference year: own days, or filled from nearby systems
var NB_CACHE = {};
var urlParams = new URLSearchParams(window.location.search);

// Vertical marker line at options.plugins.vline.x (in data units of the x scale,
// or a fractional category index for bar charts via options.plugins.vline.index)
var vlinePlugin = {
    id: 'vline',
    afterDatasetsDraw: function (chart, args, opts) {
        if (!opts || (opts.x === null && opts.index === null) || (opts.x === undefined && opts.index === undefined)) return;
        var xs = chart.scales.x, px = opts.index !== undefined && opts.index !== null ? xs.getPixelForValue(opts.index) : xs.getPixelForValue(opts.x);
        if (!isFinite(px)) return;
        var ctx = chart.ctx, top = chart.chartArea.top, bottom = chart.chartArea.bottom;
        ctx.save();
        ctx.strokeStyle = '#555'; ctx.setLineDash([5, 4]); ctx.lineWidth = 1;
        ctx.beginPath(); ctx.moveTo(px, top); ctx.lineTo(px, bottom); ctx.stroke();
        ctx.setLineDash([]); ctx.fillStyle = '#555'; ctx.font = '11px sans-serif';
        ctx.fillText(opts.label || '', px + 4, top + 12);
        ctx.restore();
    }
};
Chart.register(vlinePlugin);

var app = new Vue({
    el: '#app',
    data: {
        systemid: urlParams.has('id') ? parseInt(urlParams.get('id')) : <?php echo (int) $systemid; ?>,
        system_list: [],
        ndays: 0,
        split: false,
        status: 'Loading...',
        warnings: [],
        coverage: '',
        ref_offset: urlParams.has('ref') ? parseInt(urlParams.get('ref')) : 0,
        ref_options: [],
        win_len: urlParams.has('len') ? parseInt(urlParams.get('len')) : 0,
        win_start: 0,
        win_start_max: 0,
        bin_width: 1,
        hist_mode: 'heat',
        scatter2_mode: 'cop',
        s: null,
        query: ''
    },
    computed: {
        // Systems matching all space-separated search terms (as on the signature
        // page): "<number>kw" is an exact badge capacity match, anything else a
        // substring of id, location, manufacturer, model and kW.
        filtered: function () {
            var q = (this.query || '').trim().toLowerCase();
            if (!q) return this.system_list;
            var terms = q.split(/\s+/);
            return this.system_list.filter(function (s) {
                var hay = (s.id + ' ' + s.location + ' ' + s.hp_manufacturer + ' ' + s.hp_model + ' ' + s.hp_output + ' kw').toLowerCase();
                var cap = parseFloat(s.hp_output);
                return terms.every(function (t) {
                    var m = t.match(/^(\d+(?:\.\d+)?)kw$/);
                    if (m) return cap === parseFloat(m[1]);
                    return hay.indexOf(t) !== -1;
                });
            });
        },
        win_start_label: function () {
            if (!this.ndays || !REF.length) return '';
            var d = REF[Math.min(REF.length - 1, this.win_start)];
            return d ? fmt_date(d.ts) : '';
        },
        hist_title: function () {
            return { heat: 'Heat delivered by outside temperature', elec: 'Electricity used by outside temperature',
                     days: 'Days by outside temperature', cop: 'COP by outside temperature' }[this.hist_mode];
        },
        scatter2_title: function () {
            return { cop: 'COP', flow: 'flow temperature', dt: 'flow − outside' }[this.scatter2_mode] + ' vs outside temperature';
        }
    },
    methods: {
        fmt: function (v, dp) { return (v === null || v === undefined || !isFinite(v)) ? '–' : v.toFixed(dp); },
        load: load,
        update: function (reset_start) { update(reset_start); },
        change_ref: function () { build_reference().then(function () { app.status = ''; update(true); }); },
        draw_hist: function () { draw_hist(); },
        draw_scatter2: function () { draw_scatter2(); },
        // Step through the search matches
        next_system: function (dir) {
            var list = this.filtered;
            if (!list.length) return;
            var i = list.findIndex(function (s) { return s.id == app.systemid; });
            i = i < 0 ? 0 : i + dir;
            if (i >= 0 && i < list.length) { this.systemid = list[i].id; load(); }
        }
    },
    watch: {
        // If the current system drops out of the matches, load the first match
        // once typing pauses
        query: function () {
            clearTimeout(query_timer);
            query_timer = setTimeout(function () {
                var list = app.filtered;
                if (list.length && !list.some(function (s) { return s.id == app.systemid; })) {
                    app.systemid = list[0].id;
                    load();
                }
            }, 400);
        }
    }
});
var query_timer = null;

var initial_start = urlParams.has('start') ? urlParams.get('start') : null;

fetch(path + 'system/list/public.json').then(function (r) { return r.json(); }).then(function (list) {
    list.sort(function (a, b) { return (a.location || '').localeCompare(b.location || ''); });
    app.system_list = list;
    load();
});

var model = null;

function load() {
    app.status = 'Loading daily data...';
    app.coverage = '';
    var sysid = app.systemid;
    var fields = ['timestamp', 'combined_outsideT_mean', 'combined_data_length',
        'combined_heat_kwh', 'combined_elec_kwh', 'space_heat_kwh', 'space_elec_kwh',
        'water_heat_kwh', 'water_elec_kwh', 'space_flowT_mean', 'water_flowT_mean', 'running_flowT_mean'];
    fetch(path + 'system/stats/daily?id=' + sysid + '&fields=' + fields.join(','))
        .then(function (r) { return r.text(); })
        .then(function (csv) {
            if (sysid != app.systemid) return;
            var p = OTModel.parse(csv);
            DAYS = p.days.filter(function (d) { return d.ts; });
            REF = [];
            app.split = p.split;
            if (!DAYS.some(function (d) { return d.ok; })) {
                app.ndays = 0;
                app.status = 'No complete days with an outside temperature for this system yet.';
                return;
            }
            // Reference years: 365 day periods back from the last day
            var last = DAYS[DAYS.length - 1].ts, first = DAYS[0].ts;
            var opts = [];
            for (var o = 0; o < 6; o++) {
                var end = last - o * 365 * DAY, start = end - 365 * DAY;
                if (o > 0 && start < first - 30 * DAY) break;
                opts.push({ offset: o, label: fmt_date(start + DAY) + ' – ' + fmt_date(end) });
            }
            app.ref_options = opts;
            if (app.ref_offset >= opts.length) app.ref_offset = 0;
            return build_reference().then(function () {
                if (sysid != app.systemid) return;
                app.ndays = DAYS.length;
                app.status = '';
                update(true);
            });
        });
}

function ref_period() {
    var end = DAYS[DAYS.length - 1].ts - app.ref_offset * 365 * DAY;
    return { start: end - 365 * DAY, end: end };
}

// Calendar date of a daily timestamp (midnight local time; +12 h keeps it clear of DST shifts)
function day_key(ts) { return new Date((ts + 43200) * 1000).toISOString().slice(0, 10); }

// Daily outside temperature {date: T} of one system over a period, complete days only
function fetch_outside(id, P) {
    var key = id + ':' + P.end;
    if (NB_CACHE[key]) return NB_CACHE[key];
    NB_CACHE[key] = fetch(path + 'system/stats/daily?id=' + id + '&start=' + (P.start + 1) + '&end=' + (P.end + 1) +
                          '&fields=timestamp,combined_outsideT_mean,combined_data_length')
        .then(function (r) { return r.text(); })
        .then(function (csv) {
            var out = {}, n = 0;
            csv.trim().split('\n').slice(1).forEach(function (line) {
                var p = line.split(',');
                if (p.length != 3 || p[1] === '' || !(p[2] * 1 >= 0.9 * 86400)) return;
                out[day_key(p[0] * 1)] = p[1] * 1; n++;
            });
            return { temps: out, n: n };
        })
        .catch(function () { return { temps: {}, n: 0 }; });
    return NB_CACHE[key];
}

// Mean daily outside temperature of the 5 nearest systems with at least 300
// complete days in the period (nearest 12 are tried).
function load_neighbours(P) {
    var me = app.system_list.find(function (s) { return s.id == app.systemid; }) || {};
    var lat = parseFloat(me.latitude), lon = parseFloat(me.longitude), located = isFinite(lat) && isFinite(lon);
    if (!located) { lat = 52.5; lon = -1.5; }
    var cands = app.system_list.filter(function (s) {
        return s.id != app.systemid && isFinite(parseFloat(s.latitude)) && isFinite(parseFloat(s.longitude));
    }).map(function (s) {
        var dy = (parseFloat(s.latitude) - lat) * 111.2;
        var dx = (parseFloat(s.longitude) - lon) * 111.2 * Math.cos(lat * Math.PI / 180);
        return { id: s.id, location: s.location, dist: Math.sqrt(dx * dx + dy * dy) };
    }).sort(function (a, b) { return a.dist - b.dist; }).slice(0, 12);

    return Promise.all(cands.map(function (c) { return fetch_outside(c.id, P); })).then(function (res) {
        var use = [];
        cands.forEach(function (c, i) { if (res[i].n >= 300 && use.length < 5) { c.temps = res[i].temps; use.push(c); } });
        var sum = {}, cnt = {};
        use.forEach(function (c) {
            for (var k in c.temps) { sum[k] = (sum[k] || 0) + c.temps[k]; cnt[k] = (cnt[k] || 0) + 1; }
        });
        var temps = {};
        for (var k in sum) temps[k] = sum[k] / cnt[k];
        return { systems: use, temps: temps, located: located };
    });
}

// Build REF: 365 calendar days. Days the system measured completely are its
// own; others get an outside temperature (own partial-day mean if there is
// one, else the nearby systems' mean plus this system's sensor offset) and no
// energy data.
function build_reference() {
    var P = ref_period();
    app.status = 'Loading outside temperatures from nearby systems...';
    return load_neighbours(P).then(function (nb) {
        var own = {};
        DAYS.forEach(function (d) { if (d.ts > P.start && d.ts <= P.end) own[day_key(d.ts)] = d; });
        // Sensor offset against the neighbours, shrunk towards 0 when overlap is short
        var diffs = [];
        for (var k in own) if (own[k].ok && nb.temps[k] !== undefined) diffs.push(own[k].t - nb.temps[k]);
        var off = diffs.length ? diffs.reduce(function (a, b) { return a + b; }, 0) / diffs.length * diffs.length / (diffs.length + 14) : 0;

        REF = [];
        var n_own = 0, n_nb = 0, n_part = 0;
        for (var ts = P.start + DAY; ts <= P.end + 3600; ts += DAY) {
            var key = day_key(ts), d = own[key];
            if (d && d.ok) { REF.push(d); n_own++; continue; }
            var t = null;
            if (d && d.t !== null) { t = d.t; n_part++; }
            else if (nb.temps[key] !== undefined) { t = nb.temps[key] + off; n_nb++; }
            if (t === null) continue;
            REF.push({ ts: d ? d.ts : ts, t: t, ok: false, filled: true, H: 0, E: 0, Hs: 0, Es: 0, Hw: 0, Ew: 0, idle: 0 });
        }

        var c = n_own + ' of the reference year\'s days are measured';
        if (n_part) c += ', ' + n_part + ' more have outside temperature only';
        if (n_nb) {
            c += '. Outside temperature for the other ' + n_nb + ' days is the mean of ' + nb.systems.length + ' nearby systems (' +
                nb.systems.map(function (x) { return x.location + ' ' + Math.round(x.dist) + ' km'; }).join(', ') + ')' +
                (nb.located ? '' : ', chosen around central England as this system has no location') +
                ', shifted ' + (off >= 0 ? '+' : '') + off.toFixed(1) + ' K to match this system\'s sensor (' + diffs.length + ' overlapping days).';
        } else c += '.';
        if (365 - REF.length > 0) c += ' ' + (365 - REF.length) + ' days have no outside temperature at all and are left out.';
        app.coverage = n_own >= 360 ? '' : c;
    });
}

function update(reset_start) {
    if (!REF.length) return;
    var n = REF.length;
    var len = app.win_len || n;
    app.win_start_max = Math.max(0, n - len);
    if (reset_start) {
        app.win_start = 0;
        if (initial_start) {
            var ts = Date.parse(initial_start) / 1000;
            var i = REF.findIndex(function (d) { return d.ts >= ts; });
            if (i >= 0) app.win_start = i;
            initial_start = null;
        } else if (app.win_len) {
            // default: start the window on 1 December within the reference year
            for (var j = 0; j < n; j++) {
                var dt = new Date(REF[j].ts * 1000);
                if (dt.getMonth() == 11 && dt.getDate() == 1) { app.win_start = j; break; }
            }
        }
    }
    if (app.win_start > app.win_start_max) app.win_start = app.win_start_max;
    if (!app.win_len) app.win_start = 0;

    var ref = REF;
    var w0 = app.win_start, w1 = Math.min(n, w0 + len);
    REF.forEach(function (d, i) { d.inwin = i >= w0 && i < w1; });
    var win = REF.slice(w0, w1);

    model = OTModel.fit(win);

    // Warnings
    var w = [];
    var heating_days = win.filter(function (d) { return d.ok && d.Hs > 0.5; }).length;
    if (heating_days < 5) w.push('Fewer than 5 days of ' + (app.split ? 'space heating' : 'heating') + ' in the fit window, so the projection has nothing to scale the heating season from.');
    if (!app.split) w.push('This system has no space heating / hot water split, so everything is treated as one heat demand line.');
    var ok_ref = ref.filter(function (d) { return d.ok; });
    app.warnings = w;

    // Summary
    var sum = OTModel.sum;
    var okw = win.filter(function (d) { return d.ok; });
    var s = {};
    s.win_days = okw.length;
    s.win_heat = sum(okw, function (d) { return d.H; });
    s.win_spf = s.win_heat / sum(okw, function (d) { return d.E; });
    s.ref_days = ok_ref.length;
    s.ref_heat = sum(ok_ref, function (d) { return d.H; });
    s.ref_spf = s.ref_heat / sum(ok_ref, function (d) { return d.E; });
    s.meas_tmed = OTModel.heatMedianT(ref);
    s.win_tmed = OTModel.heatMedianT(win);
    s.ref_tmed = s.meas_tmed; s.tmed_modelled = false; s.proj_err = null; s.proj_days = ref.length;
    s.proj_spf = null; s.proj_heat = null; s.dt_med = null; s.flow_med = null; s.eta = null; s.eta_w = null; s.cop_w_linear = false;
    if (model && heating_days >= 5) {
        // Projection over every day of the reference year
        var H = 0, E = 0, mod = [];
        ref.forEach(function (d) {
            var p = model.day(d.t); H += p.Hs + p.Hw; E += p.Es + p.Ew + p.idle;
            mod.push({ ok: true, t: d.t, H: p.Hs + p.Hw });
        });
        s.proj_heat = H; s.proj_spf = H / E;
        s.ref_tmed = OTModel.heatMedianT(mod); s.tmed_modelled = true;
        // Model against measurement on the measured days only
        var Hm = 0, Em = 0;
        ok_ref.forEach(function (d) { var p = model.day(d.t); Hm += p.Hs + p.Hw; Em += p.Es + p.Ew + p.idle; });
        if (ok_ref.length) s.proj_err = (Hm / Em / s.ref_spf - 1) * 100;
    }
    if (model) {
        s.k_wk = model.hinge.k / 24 * 1000;
        s.tb = model.hinge.tb; s.tb_fixed = model.hinge.fixed;
        s.idle = model.idle;
        if (model.copS && model.copS.type == 'carnot') {
            s.eta = model.copS.eta;
            if (s.ref_tmed !== null) { s.flow_med = model.copS.flow(s.ref_tmed); s.dt_med = s.flow_med - s.ref_tmed; }
        }
        if (model.copW) {
            if (model.copW.type == 'carnot') s.eta_w = model.copW.eta;
            else s.cop_w_linear = true;
        }
    }
    app.s = s;

    save_url();
    Vue.nextTick(function () { draw_hist(); draw_heat_scatter(); draw_scatter2(); });
}

function save_url() {
    var q = '?id=' + app.systemid;
    if (app.ref_offset) q += '&ref=' + app.ref_offset;
    if (app.win_len) {
        var d = REF[app.win_start];
        q += '&len=' + app.win_len + '&start=' + new Date(d.ts * 1000).toISOString().slice(0, 10);
    }
    window.history.replaceState({}, '', window.location.pathname + q);
}

function fmt_date(ts) {
    return new Date(ts * 1000).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

function make_chart(id, config) {
    if (charts[id]) charts[id].destroy();
    charts[id] = new Chart(document.getElementById(id), config);
}

// ---------------------------------------------------------------------------
// Histogram by outside temperature bin
// ---------------------------------------------------------------------------
function draw_hist() {
    if (!REF.length) return;
    var bw = app.bin_width;
    var ref = REF;
    if (!ref.length) return;
    var tmin = Math.floor(Math.min.apply(null, ref.map(function (d) { return d.t; })) / bw) * bw;
    var tmax = Math.floor(Math.max.apply(null, ref.map(function (d) { return d.t; })) / bw) * bw;
    var nb = Math.round((tmax - tmin) / bw) + 1;
    var labels = [], B = [];
    for (var i = 0; i < nb; i++) {
        var lo = tmin + i * bw;
        labels.push(bw == 1 ? lo + '' : lo + ' to ' + (lo + bw));
        B.push({ Hs_in: 0, Hs_out: 0, Hw_in: 0, Hw_out: 0, Es_in: 0, Es_out: 0, Ew_in: 0, Ew_out: 0, I_in: 0, I_out: 0,
                 n_in: 0, n_out: 0, n_fill: 0, mH: 0, mE: 0, mHs: 0, mHw: 0 });
    }
    ref.forEach(function (d) {
        var b = B[Math.min(nb - 1, Math.floor((d.t - tmin) / bw))];
        var k = d.inwin ? '_in' : '_out';
        if (d.filled) b.n_fill++;
        else b['n' + k]++;
        b['Hs' + k] += d.Hs; b['Hw' + k] += d.Hw;
        b['Es' + k] += d.Es; b['Ew' + k] += d.Ew; b['I' + k] += d.idle;
        if (model) {
            var p = model.day(d.t);
            b.mHs += p.Hs; b.mHw += p.Hw; b.mH += p.Hs + p.Hw; b.mE += p.Es + p.Ew + p.idle;
        }
    });

    var sp = app.split ? 'Space heating' : 'Heat';
    var mode = app.hist_mode, ds = [];
    function bar(label, key, col, stack) {
        return { type: 'bar', label: label, data: B.map(function (b) { return b[key]; }), backgroundColor: col, stack: stack || 's', order: 2 };
    }
    var show_model = model && app.s && app.s.proj_spf !== null;
    if (mode == 'heat') {
        ds.push(bar(sp + ' (window)', 'Hs_in', COL.space), bar(sp, 'Hs_out', COL.space_lt));
        if (app.split) ds.push(bar('Hot water (window)', 'Hw_in', COL.water), bar('Hot water', 'Hw_out', COL.water_lt));
        if (show_model) ds.push(line('Model, total heat', B.map(function (b) { return b.mH; })));
        if (show_model && app.split) {
            var lw = line('Model, hot water', B.map(function (b) { return b.mHw; }));
            lw.borderDash = [6, 4]; lw.borderWidth = 1.5; lw.stack = 'model_w';
            ds.push(lw);
        }
    } else if (mode == 'elec') {
        ds.push(bar(sp + ' (window)', 'Es_in', COL.space), bar(sp, 'Es_out', COL.space_lt));
        if (app.split) ds.push(bar('Hot water (window)', 'Ew_in', COL.water), bar('Hot water', 'Ew_out', COL.water_lt));
        ds.push(bar('Standby (window)', 'I_in', COL.idle), bar('Standby', 'I_out', COL.idle_lt));
        if (show_model) ds.push(line('Model, total electricity', B.map(function (b) { return b.mE; })));
    } else if (mode == 'days') {
        ds.push(bar('Measured (window)', 'n_in', COL.idle), bar('Measured', 'n_out', COL.idle_lt));
        var fb = bar('Outside temperature only', 'n_fill', 'rgba(255,255,255,0)');
        fb.borderColor = '#999'; fb.borderWidth = 1;
        ds.push(fb);
    } else if (mode == 'cop') {
        var ratio = function (h, e) { return function (b) { var E = e(b); return E > 0.5 ? h(b) / E : null; }; };
        ds.push(line('Measured, combined', B.map(ratio(
            function (b) { return b.Hs_in + b.Hs_out + b.Hw_in + b.Hw_out; },
            function (b) { return b.Es_in + b.Es_out + b.Ew_in + b.Ew_out + b.I_in + b.I_out; })), '#666', false));
        if (app.split) {
            ds.push(line('Measured, space heating', B.map(ratio(function (b) { return b.Hs_in + b.Hs_out; }, function (b) { return b.Es_in + b.Es_out; })), COL.space, false));
            ds.push(line('Measured, hot water', B.map(ratio(function (b) { return b.Hw_in + b.Hw_out; }, function (b) { return b.Ew_in + b.Ew_out; })), COL.water, false));
        }
        if (show_model) ds.push(line('Model, combined', B.map(function (b) { return b.mE > 0.5 ? b.mH / b.mE : null; })));
    }

    var unit = { heat: 'kWh', elec: 'kWh', days: 'days', cop: 'COP' }[mode];
    var tmed = app.s ? app.s.ref_tmed : null;
    make_chart('hist', {
        data: { labels: labels, datasets: ds },
        options: {
            maintainAspectRatio: false, animation: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { stacked: true, title: { display: true, text: 'Daily mean outside temperature (°C)' }, grid: { display: false } },
                y: { stacked: mode != 'cop', min: mode != 'cop' ? 0 : undefined, title: { display: true, text: unit } }
            },
            plugins: {
                vline: { index: tmed !== null ? (tmed - tmin) / bw - 0.5 : null, label: tmed !== null ? 'heat-median ' + tmed.toFixed(1) + '°C' : '' },
                tooltip: { callbacks: {
                    label: function (c) { var v = c.parsed.y; return v === null ? null : c.dataset.label + ': ' + v.toFixed(mode == 'cop' ? 2 : (mode == 'days' ? 0 : 1)); }
                } }
            }
        }
    });

    function line(label, data, color, stepped) {
        return { type: 'line', label: label, data: data, borderColor: color || COL.model, backgroundColor: color || COL.model,
                 borderWidth: 2, pointRadius: stepped === false ? 3 : 0, stepped: stepped === false ? false : 'middle',
                 stack: 'model', order: 1, spanGaps: false };
    }
}

// ---------------------------------------------------------------------------
// Daily scatter charts
// ---------------------------------------------------------------------------
function scatter_ds(label, days, yf, col) {
    return { type: 'scatter', label: label, pointRadius: 2.5, borderWidth: 0, backgroundColor: col,
             data: days.map(function (d) { var y = yf(d); return y === null ? null : { x: d.t, y: y, ts: d.ts }; })
                       .filter(function (p) { return p !== null; }) };
}
function curve_ds(label, f, tmin, tmax, col, dash) {
    var pts = [];
    for (var t = tmin; t <= tmax + 0.001; t += 0.25) { var y = f(t); if (y !== null && isFinite(y)) pts.push({ x: t, y: y }); }
    return { type: 'line', label: label, data: pts, borderColor: col, borderWidth: 2, pointRadius: 0, borderDash: dash || [], parsing: true };
}
function t_range() {
    var ref = REF;
    return [Math.floor(Math.min.apply(null, ref.map(function (d) { return d.t; }))),
            Math.ceil(Math.max.apply(null, ref.map(function (d) { return d.t; })))];
}
function scatter_options(ytitle, beginAtZero) {
    var tmed = app.s ? app.s.ref_tmed : null;
    return {
        maintainAspectRatio: false, animation: false,
        scales: {
            x: { type: 'linear', title: { display: true, text: 'Daily mean outside temperature (°C)' } },
            y: { min: beginAtZero ? 0 : undefined, title: { display: true, text: ytitle } }
        },
        plugins: {
            vline: { x: tmed, label: tmed !== null ? 'heat-median' : '' },
            legend: { labels: { boxWidth: 12 } },
            tooltip: { callbacks: { label: function (c) {
                var p = c.raw, s = c.dataset.label + ': ' + c.parsed.y.toFixed(2) + ' at ' + c.parsed.x.toFixed(1) + '°C';
                if (p && p.ts) s += ' (' + fmt_date(p.ts) + ')';
                return s;
            } } }
        }
    };
}

function draw_heat_scatter() {
    if (!REF.length) return;
    var ref = REF.filter(function (d) { return d.ok; });
    var tr = t_range(), sp = app.split ? 'Space heating' : 'Heat';
    var inw = ref.filter(function (d) { return d.inwin; }), outw = ref.filter(function (d) { return !d.inwin; });
    var ds = [];
    ds.push(scatter_ds(sp + ' (window)', inw, function (d) { return d.Hs; }, COL.space));
    ds.push(scatter_ds(sp, outw, function (d) { return d.Hs; }, COL.space_lt));
    if (app.split) {
        ds.push(scatter_ds('Hot water (window)', inw, function (d) { return d.Hw; }, COL.water));
        ds.push(scatter_ds('Hot water', outw, function (d) { return d.Hw; }, COL.water_lt));
    }
    if (model) {
        ds.push(curve_ds('Model, ' + sp.toLowerCase(), model.heatS, tr[0], tr[1], COL.model));
        if (app.split) ds.push(curve_ds('Model, hot water', model.heatW, tr[0], tr[1], COL.model, [6, 4]));
    }
    make_chart('heat_scatter', { data: { datasets: ds }, options: scatter_options('kWh per day', true) });
}

function draw_scatter2() {
    if (!REF.length) return;
    var ref = REF.filter(function (d) { return d.ok; });
    var tr = t_range(), mode = app.scatter2_mode, sp = app.split ? 'Space heating' : 'Heat';
    var inw = ref.filter(function (d) { return d.inwin; }), outw = ref.filter(function (d) { return !d.inwin; });
    var ds = [], ytitle = '';
    function add(lbl, f, cs, cw) {
        ds.push(scatter_ds(lbl + ' (window)', inw, f, cs));
        ds.push(scatter_ds(lbl, outw, f, cw));
    }
    var cS = model ? model.copS : null, cW = model ? model.copW : null;
    var tS = model ? Math.min(tr[1], Math.ceil(model.hinge.tb)) : tr[1]; // space curves stop at the balance temperature
    if (mode == 'cop') {
        ytitle = 'COP';
        add(sp, function (d) { return d.Hs > 2 && d.Es > 0 ? d.Hs / d.Es : null; }, COL.space, COL.space_lt);
        if (app.split) add('Hot water', function (d) { return d.Hw > 1 && d.Ew > 0 ? d.Hw / d.Ew : null; }, COL.water, COL.water_lt);
        if (cS) ds.push(curve_ds('Model, ' + sp.toLowerCase(), cS.cop, tr[0], tS, COL.model));
        if (cW && app.split) ds.push(curve_ds('Model, hot water', cW.cop, tr[0], tr[1], COL.model, [6, 4]));
    } else {
        var dt = mode == 'dt';
        ytitle = dt ? 'Flow − outside (K)' : 'Flow temperature (°C)';
        add(sp, function (d) { return d.Hs > 2 && d.flowS > 0 ? d.flowS - (dt ? d.t : 0) : null; }, COL.space, COL.space_lt);
        if (app.split) add('Hot water', function (d) { return d.Hw > 1 && d.flowW > 0 ? d.flowW - (dt ? d.t : 0) : null; }, COL.water, COL.water_lt);
        if (cS && cS.flow) ds.push(curve_ds('Model, ' + sp.toLowerCase(), function (t) { return cS.flow(t) - (dt ? t : 0); }, tr[0], tS, COL.model));
        if (cW && cW.flow && app.split) ds.push(curve_ds('Model, hot water', function (t) { return cW.flow(t) - (dt ? t : 0); }, tr[0], tr[1], COL.model, [6, 4]));
    }
    make_chart('scatter2', { data: { datasets: ds }, options: scatter_options(ytitle, false) });
}
</script>
