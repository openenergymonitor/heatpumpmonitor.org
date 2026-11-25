// Measurements card: design parameters worked out from the monitoring data,
// set against the system form, then the flow temperature map, primary
// pipework and system volume, the hot water cylinder and data checks.
// Concept. Measurements built so far come from system_measurements (nightly,
// scripts/measurements/system_measurements.php). The rest show example
// values marked Concept until they are built: see MEASUREMENTS_PREVIEW and
// analysis/performance_prediction/docs/system_page_insights.md.

// Example values for measurements not built yet, by the name they are
// expected to have in system_measurements. A real measurement of the same
// name replaces the example and its Preview mark.
var MEASUREMENTS_PREVIEW = {
    // Emitter capacity at ΔT50 from steady running episodes (emitter.js
    // method), heat-weighted, with the main peaks of the distribution
    emitter_kw50: { value: 9.4, lower: 8.7, upper: 10.3, samples: 412, unit: "kW",
        details: { exponent: 1.3, room: "feed", peaks: [{ kw: 6.6, share: 0.18, sd: 0.45 }, { kw: 9.4, share: 0.82, sd: 0.55 }] } },
    // Machine replay (scripts/feed_scan/replay.php, machine_replay.md): field
    // COP / datasheet COP in steady space heating at 30-70 rps over the last
    // year, the peer level against same-size systems in the same conditions,
    // and the share of steady running at minimum speed and its COP against the level
    replay_level: { value: 0.94, samples: 1580, unit: "", details: { peer_level: 0.97, min_share: 0.46, min_ratio: 0.82, steady_flowT: 33.2 } },
    // Primary loop: return to flow transport delay at hot water switch-overs
    // (comparison/fast.py loop_volume, manual_loop_lag.md)
    loop_delay: { value: 88, lower: 85, upper: 91, samples: 14, unit: "s", details: { flowrate: 14.4 } },
    loop_volume: { value: 21.1, lower: 20.4, upper: 21.8, samples: 14, unit: "L",
        details: { spike_volume: 21.4, events: [20.2, 20.4, 20.6, 20.9, 21.0, 21.0, 21.1, 21.1, 21.2, 21.4, 21.5, 21.6, 21.9, 22.3] } },
    // Water inside the heat pump and meter, from the heat pump model table
    unit_water_content: { value: 2.0, unit: "L", details: { source: "datasheet" } },
    // Whole heating circuit from the mean water temperature rise after starts
    // (today the Options panel's manual emitter spec and system volume tool)
    system_volume: { value: 118, lower: 96, upper: 141, samples: 37, unit: "L" },
    // Cylinder standby heat loss coefficient from overnight temperature decay
    dhw_standby_U: { value: 1.8, lower: 1.6, upper: 2.1, samples: 31, unit: "W/K", details: { env_temp: 18, fleet: [1.2, 2.9] } },
    // Outside design temperatures, when there is no complete year of data yet
    design_outsideT_996: { value: -2.1, samples: 8760, details: { blocks: [], coldest_hour: null } },
    design_outsideT_990: { value: -0.8, samples: 8760 },
    // Metering and data checks (replay.php, pumponly.php)
    data_checks: { value: 1, details: {
        dhw_flag: { status: "good", value: "OK", note: "Set on 412 reheats; no unflagged reheats found" },
        sensor_offset: { value: 0.02, fleet: [-0.06, 0.04], scale: [-0.15, 0.15] },
        meter_k: { value: 69.3, fleet: [68.9, 70.0], scale: [68, 71] },
        room_feed: true, flowrate_feed: true,
        standby_power: { value: 14, fleet: [8, 21], scale: [0, 40] },
        pump_only_power: { value: 26, fleet: [17, 37], scale: [0, 60] },
        coverage: 0.987
    } }
};

// Fleet figures for comparison. % Carnot: last 365 days of every system with a
// value between 20 and 80% (system/stats/last365, November 2026), 10th, 50th
// and 90th percentile. Replay: 132 validated Arotherm+ systems
// (twins/replay_fleet.csv). To come from a nightly fleet summary
var MEASUREMENTS_FLEET = {
    prc_carnot: {
        space: { p: [43.3, 48.8, 54.5], n: 585 },
        water: { p: [34.9, 41.8, 48.6], n: 518 },
        running: { p: [41.3, 47.5, 53.1], n: 826 }
    },
    level: { p: [0.89, 0.964, 1.024], n: 132 },
    min_share: { p: [0.06, 0.39, 0.71] },
    min_ratio: { p: [0.73, 0.84, 0.90] }
};

// Page state for the card: pipe size for the length
var hp_ms_state = { pipe: 28 };

function measurements_card_render() {
    var m = window.system_meta;
    var el = document.getElementById("measurements-block");
    if (!m || !el) return;
    var H = hp_sys, esc = H.esc, has = H.has, num = H.num, fmt = H.fmt, period = H.period, date = H.date,
        chip_date = H.chip_date, pct = H.pct, mv = H.mv, fact = H.fact, fact_html = H.fact_html;
    var ms = window.system_measurements || {};

    // Measured value, or the example value marked as a preview
    function pv(name) {
        var v = mv(name);
        if (v) return v;
        var p = MEASUREMENTS_PREVIEW[name];
        if (!p) return null;
        var c = JSON.parse(JSON.stringify(p));
        c.preview = true;
        if (c.lower === undefined) c.lower = null;
        if (c.upper === undefined) c.upper = null;
        c.details = c.details || {};
        return c;
    }
    function preview_tag(on) {
        return on ? '<span class="hp-ms-preview" title="Example values: this measurement is not built yet">Concept</span>' : '';
    }
    function unit(u) { return '<span class="hp-sys-unit">' + u + '</span>'; }
    function signed(v, dp) { return (v > 0 ? "+" : v < 0 ? "−" : "") + Math.abs(v).toFixed(dp); }
    // A tile: label, headline value and the body below
    function tile(label, value_html, value_class, body, is_preview, extra_class) {
        return '<div class="hp-sys-feature' + (extra_class ? ' ' + extra_class : '') + (is_preview ? ' is-preview' : '') + '">' +
            '<div class="hp-sys-feature-head"><span class="app-live-label">' + esc(label) + preview_tag(is_preview) + '</span>' +
            '<span class="hp-sys-feature-value ' + (value_class || '') + '">' + value_html + '</span></div>' + body + '</div>';
    }
    function section(title, sub, body, is_preview) {
        if (!body) return "";
        return '<div class="hp-ms-section' + (is_preview ? ' is-preview' : '') + '"><div class="hp-ms-section-head"><span>' + esc(title) + '</span>' + preview_tag(is_preview) +
            (sub ? '<span class="hp-ms-section-sub">' + sub + '</span>' : '') + '</div>' + body + '</div>';
    }
    // Small range bar: the fleet's 10th to 90th percentile shaded, this system as a dot
    function range_bar(v, fleet, scale, unit_text) {
        var lo = scale[0], hi = scale[1];
        return '<div class="hp-ms-range" title="This system ' + esc(fmt(v, 2) + unit_text) + '; most systems ' + esc(fmt(fleet[0], 2) + ' to ' + fmt(fleet[1], 2) + unit_text) + '">' +
            '<div class="hp-ms-range-fleet" style="left:' + pct(fleet[0], lo, hi) + '%;width:' + (pct(fleet[1], lo, hi) - pct(fleet[0], lo, hi)) + '%"></div>' +
            (fleet[2] !== undefined ? '<div class="hp-ms-range-mid" style="left:' + pct(fleet[2], lo, hi) + '%"></div>' : '') +
            '<div class="hp-ms-range-dot" style="left:' + pct(v, lo, hi) + '%"></div></div>';
    }

    var ROOM = 20;          // assumed room temperature where there is no feed
    var EMITTER_DT = 5;     // assumed flow - return at design
    var CP = 4.13;          // kJ/L/K, water at 40-50 °C
    var CP_FLOW = 4.15;     // kJ/kg/K, for flow rates, as the measurement script
    var DESIGN_DT = 5;      // assumed design flow - return ΔT (the form has no field)

    // Space heating flow rate and ΔT. The coldest days' measurement
    // (dT_coldest_days, flowrate_coldest_days, from the daily stats), else the
    // last 365 days' space heating means. Flow rate is implied: mean heat /
    // (cp × mean ΔT), so it doesn't depend on the flow meter's units
    function flow_dT() {
        var fr = mv("flowrate_coldest_days"), dt = mv("dT_coldest_days");
        if (fr && dt) return { fr: fr, dt: dt, source: "coldest" };
        var t = last365();
        if (!t) return null;
        var fT = num(t.space_flowT_mean), rT = num(t.space_returnT_mean), q = num(t.space_heat_mean);
        if (fT === null || rT === null || !q || fT - rT < 0.5) return null;
        var d = fT - rT;
        return {
            fr: { value: q / (CP_FLOW * 1000 * d) * 60, lower: null, upper: null, details: { heat_kw: q / 1000, days: [] } },
            dt: { value: d, lower: null, upper: null, details: {} },
            source: "year"
        };
    }

    // -- Sizing: heat pump output against design and measured heat loss, -----
    // then the heat demand tool (myheatpump_heatdemand.js). Explore spans the card
    function sizing() {
        var hp = num(m.hp_output), max_out = num(m.hp_max_output), design = num(m.heat_loss);
        // Estimated heat demand at the form's design temperature, from the nightly heat loss fit
        var auto = mv("heat_loss_design");
        var meas = auto ? auto.value : null, meas_r = auto && auto.upper !== null ? auto.upper - auto.value : 0;
        // Observed on the coldest days of the last year (the flow temperature card's days)
        var cold = mv("heat_coldest_days"), peak_4h = mv("heat_peak_4h");
        var hd = heatdemand_data, hd_none = hd && (hd.message || (hd.rows && hd.rows.length < 3));
        if (!hp || (!design && !meas)) {
            if (hd_none) return "";
            return '<div class="hp-sys-feature has-hd' + (heatdemand_state.open ? ' is-wide' : '') + '"><div class="hp-sys-feature-head"><span class="app-live-label">Sizing</span>' +
                (hp ? '<span class="hp-sys-feature-value text-hp-heat">' + fmt(hp, 1) + '<span class="hp-sys-unit">kW heat pump</span></span>' : '') +
                '</div>' + heatdemand_html() + '</div>';
        }

        var hi = Math.max(hp, max_out || 0, design || 0, meas ? meas + meas_r : 0,
            peak_4h ? peak_4h.value : 0, cold ? cold.value : 0) * 1.12;

        var bar = '<div class="hp-sys-scale">';
        bar += '<div class="hp-sys-scale-fill is-heat" style="width:' + pct(hp, 0, hi) + '%"></div>';
        if (max_out && max_out > hp) {
            bar += '<div class="hp-sys-scale-fill is-heat-max" style="left:' + pct(hp, 0, hi) + '%;width:' + (pct(max_out, 0, hi) - pct(hp, 0, hi)) + '%"></div>';
        }
        if (meas) {
            bar += '<div class="hp-sys-scale-band" style="left:' + pct(meas - meas_r, 0, hi) + '%;width:' + (pct(meas + meas_r, 0, hi) - pct(meas - meas_r, 0, hi)) + '%"></div>';
            bar += '<div class="hp-sys-scale-dot" style="left:' + pct(meas, 0, hi) + '%"></div>';
            var a = auto.details || {};
            var how = "Line fitted to " + (a.days || auto.samples) + " days of daily heat against inside - outside temperature difference" +
                (has(a.design_dT) ? ", read off at ΔT " + fmt(a.design_dT, 1) + " K" : "") +
                (a.extrapolated ? ", beyond the coldest day" + (has(a.max_dT) ? " (ΔT " + fmt(a.max_dT, 1) + " K)" : "") : "") +
                (auto.upper !== null ? ". 80% range " + fmt(auto.lower, 1) + " to " + fmt(auto.upper, 1) + " kW" : "");
            bar += '<div class="hp-sys-scale-tag is-below" style="left:' + pct(meas, 0, hi) + '%" title="' + esc(how) + '">Estimated ' + fmt(meas, 1) + ' kW' + (a.extrapolated ? ' (extrapolated)' : '') + '</div>';
        }
        if (design) {
            bar += '<div class="hp-sys-scale-tick" style="left:' + pct(design, 0, hi) + '%"></div>';
            bar += '<div class="hp-sys-scale-tag is-above" style="left:' + pct(design, 0, hi) + '%">Design ' + fmt(design, 1) + ' kW</div>';
        }
        // Highest of the coldest days (kWh / 24) and highest 4 hours on them
        [[cold, "Highest coldest day"], [peak_4h, "Peak 4 h"]].forEach(function (p) {
            if (!p[0]) return;
            bar += '<div class="hp-sys-scale-peak" style="left:' + pct(p[0].value, 0, hi) + '%" title="' + p[1] + ' ' + fmt(p[0].value, 2) + ' kW"></div>';
        });
        bar += '</div>';

        var note = '';
        if (cold) {
            // The highest of the coldest days, with its outside temperature, opening that day
            var c = cold.details || {};
            note += 'Highest: <b>' + period(cold.start_time, cold.end_time, fmt(cold.value, 1) + ' kW',
                date(cold.start_time) + ', mean heat output over the day in all modes (' + c.heat_kwh + ' kWh / 24); open with it highlighted', { heatkw: cold.value }) + ' 24 h mean</b>' +
                (has(c.outsideT) ? ' at ' + fmt(c.outsideT, 1) + '°C outside' : '');
            if (c.days && c.days.length) {
                note += '<div class="hp-sys-chips">';
                c.days.forEach(function (d) {
                    note += period(d.start, d.end, '<span class="hp-sys-badge">' + chip_date(d.start) + ' · ' + fmt(d.heat_kw, 1) + ' kW' +
                        ' <span class="hp-sys-badge-sub">' + fmt(d.outsideT, 1) + '° out</span></span>',
                        date(d.start) + ", " + d.heat_kwh + " kWh (" + d.space_kw + " kW space heating), " + d.outsideT + " °C outside (weighted by space heating output; day mean " + d.outsideT_day + " °C)" +
                        (d.heat_4h !== null ? ", highest 4 h " + d.heat_4h + " kW" : ""), { heatkw: d.heat_kw });
                });
                note += '</div>';
            }
        }
        if (peak_4h) note += (note && !(cold && cold.details && cold.details.days && cold.details.days.length) ? '<br>' : '') + 'Peak: <b>' + period(peak_4h.start_time, peak_4h.end_time, fmt(peak_4h.value, 1) + ' kW',
            date(peak_4h.start_time, true) + (peak_4h.details && peak_4h.details.outsideT !== null ? ", " + peak_4h.details.outsideT + " °C outside" : "")) +
            ' 4 h mean</b> on ' + esc(date(peak_4h.start_time, true));

        return '<div class="hp-sys-feature has-hd' + (heatdemand_state.open ? ' is-wide' : '') + '">' +
            '<div class="hp-sys-feature-head"><span class="app-live-label">Sizing</span>' +
            '<span class="hp-sys-feature-value text-hp-heat">' + fmt(hp, 1) + '<span class="hp-sys-unit">kW heat pump</span></span></div>' +
            bar + '<div class="hp-sys-feature-note">' + note + '</div>' + (hd_none ? '' : heatdemand_html()) + '</div>';
    }

    // -- Flow temperature: design against measured on the coldest days --------
    function flow() {
        // Space heating flow temperature on the coldest days of the last year, worked out nightly
        var cold = mv("flowT_coldest_days"), peak = mv("flowT_peak_4h");
        var design = num(m.flow_temp);
        var meas = cold ? cold.value : null;
        var map = flowmap();
        if (!design && !meas) return map ? tile("Flow temperature", "", "text-hp-flow", map) : "";
        var c = cold ? cold.details || {} : {};

        var vals = [design, cold ? cold.lower : null, cold ? cold.upper : null, peak ? peak.value : null].filter(function (v) { return v; });
        var lo = Math.floor(Math.min(25, Math.min.apply(null, vals) - 5) / 5) * 5;
        var hi = Math.ceil(Math.max(55, Math.max.apply(null, vals) + 5) / 5) * 5;
        var dot = has(m.design_temp) ? fmt(m.design_temp, 1) + "°C" : "design";

        var bar = '<div class="hp-sys-scale is-flow">';
        if (meas) {
            // Band from the lowest to the highest of the coldest days, dot at the highest
            if (cold.lower !== null && cold.upper !== null)
                bar += '<div class="hp-sys-scale-band is-flow" style="left:' + pct(cold.lower, lo, hi) + '%;width:' + (pct(cold.upper, lo, hi) - pct(cold.lower, lo, hi)) + '%" title="Coldest days ' + fmt(cold.lower, 1) + ' to ' + fmt(cold.upper, 1) + '°C"></div>';
            bar += '<div class="hp-sys-scale-dot is-flow" style="left:' + pct(meas, lo, hi) + '%"></div>';
            bar += '<div class="hp-sys-scale-tag is-below" style="left:' + pct(meas, lo, hi) + '%">Measured ' + fmt(meas, 1) + '°C</div>';
        }
        if (design) {
            bar += '<div class="hp-sys-scale-tick" style="left:' + pct(design, lo, hi) + '%"></div>';
            bar += '<div class="hp-sys-scale-tag is-above" style="left:' + pct(design, lo, hi) + '%">Design ' + fmt(design, 1) + '°C</div>';
        }
        if (peak) {
            bar += '<div class="hp-sys-scale-peak is-flow" style="left:' + pct(peak.value, lo, hi) + '%" title="Peak 4 h ' + fmt(peak.value, 1) + '°C"></div>';
        }
        bar += '<div class="hp-sys-scale-end is-lo">' + lo + '°</div><div class="hp-sys-scale-end is-hi">' + hi + '°</div>';
        bar += '</div>';

        var note = "Design flow temperature at " + esc(dot) + " outside";
        if (meas) {
            // The highest day, with its own outside temperature, opening that day
            note = 'Highest: ' + period(cold.start_time, cold.end_time, fmt(meas, 1) + '°C', date(cold.start_time) + ', open with the weighted flow temperature highlighted', { flowt: meas, outsidet: c.outsideT }) +
                (has(c.outsideT) ? ' at ' + fmt(c.outsideT, 1) + '°C outside' : '');
            if (design) {
                var d = meas - design;
                note += ', <b>' + fmt(Math.abs(d), 1) + '°C ' + (d < 0 ? 'cooler' : 'warmer') + '</b> than designed at ' + esc(dot);
            }
            // The coldest days, coldest first, each opening its day
            if (c.days && c.days.length) {
                note += '<div class="hp-sys-chips">';
                c.days.forEach(function (d) {
                    note += period(d.start, d.end, '<span class="hp-sys-badge">' + chip_date(d.start) + ' · ' + fmt(d.flowT, 1) + '°' +
                        ' <span class="hp-sys-badge-sub">' + fmt(d.outsideT, 1) + '° out</span></span>',
                        date(d.start) + ", " + d.outsideT + " °C outside (weighted by space heating output; day mean " + d.outsideT_day + " °C), " + d.space_kwh + " kWh space heating" +
                        (d.flowT_4h !== null ? ", highest 4 h " + d.flowT_4h + " °C" : ""), { flowt: d.flowT, outsidet: d.outsideT });
                });
                note += '</div>';
            }
            if (peak) note += 'Space heating peak 4 h ' + period(peak.start_time, peak.end_time, '<b>' + fmt(peak.value, 1) + '°C</b>',
                date(peak.start_time, true) + (peak.details && peak.details.outsideT !== null ? ", " + peak.details.outsideT + " °C outside" : "")) +
                ' on ' + esc(date(peak.start_time, true));
            if (!c.dhw_split) note += '<br>No hot water flag, so these may include cylinder reheats';
        }
        // Inferred from the flow map: the flow temperature it runs at, moved to the design outside temperature
        var inf = mv("flowT_design_inferred");
        if (inf) {
            var idt = inf.details || {}, bands = idt.bands || [];
            // With more than one band run in the cold, the value is the highest: listed on hover
            var bands_title = bands.length > 1 ? ' title="Highest of ' + bands.map(function (b) { return fmt(b.flowT, 1) + '°'; }).join(', ') + ' run in the cold"' : '';
            note += '<br>Inferred from the map: <b' + bands_title + '>' + fmt(inf.value, 1) + '°C</b> at ' + fmt(idt.design_outsideT, 1) + '°C outside';
        }
        var head = meas || design;
        return '<div class="hp-sys-feature">' +
            '<div class="hp-sys-feature-head"><span class="app-live-label">Flow temperature</span>' +
            '<span class="hp-sys-feature-value text-hp-flow">' + fmt(head, 1) + '<span class="hp-sys-unit">°C ' + (meas ? 'measured' : 'design') + '</span></span></div>' +
            bar + '<div class="hp-sys-feature-note">' + note + '</div>' + map + '</div>';
    }

    // -- Outside design temperature: measured 99.6% and 99.0% against the form -
    function outside() {
        var d996 = pv("design_outsideT_996"), d990 = pv("design_outsideT_990");
        var design = has(m.design_temp) ? m.design_temp * 1 : null;
        if (!d996) return "";
        var t996 = d996.value, t990 = d990 ? d990.value : null;
        var det = d996.details || {}, ch = det.coldest_hour;

        var vals = [t996, t990, design, ch ? ch.outsideT : null].filter(function (v) { return v !== null && v !== undefined; });
        var lo = Math.floor(Math.min.apply(null, vals) - 2), hi = Math.ceil(Math.max.apply(null, vals) + 2);
        if (hi - lo < 8) hi = lo + 8;

        var bar = '<div class="hp-sys-scale is-outside">';
        if (t990 !== null) bar += '<div class="hp-sys-scale-band" style="left:' + pct(t996, lo, hi) + '%;width:' + (pct(t990, lo, hi) - pct(t996, lo, hi)) + '%" title="99.6% to 99.0%"></div>';
        if (t990 !== null) bar += '<div class="hp-sys-scale-peak is-outside" style="left:' + pct(t990, lo, hi) + '%" title="99.0%: ' + fmt(t990, 1) + '°C"></div>';
        bar += '<div class="hp-sys-scale-dot is-outside" style="left:' + pct(t996, lo, hi) + '%"></div>';
        bar += '<div class="hp-sys-scale-tag is-below" style="left:' + pct(t996, lo, hi) + '%">99.6% ' + fmt(t996, 1) + '°C</div>';
        if (ch) bar += '<div class="hp-sys-scale-peak is-cold" style="left:' + pct(ch.outsideT, lo, hi) + '%" title="Coldest hour ' + fmt(ch.outsideT, 1) + '°C"></div>';
        if (design !== null) {
            bar += '<div class="hp-sys-scale-tick" style="left:' + pct(design, lo, hi) + '%"></div>';
            bar += '<div class="hp-sys-scale-tag is-above" style="left:' + pct(design, lo, hi) + '%">Design ' + fmt(design, 1) + '°C</div>';
        }
        bar += '<div class="hp-sys-scale-end is-lo">' + lo + '°</div><div class="hp-sys-scale-end is-hi">' + hi + '°</div></div>';

        var years = det.blocks && det.blocks.length ? det.blocks.length : null;
        var note = 'Hourly outside temperature is below this for 35 hours a year (0.4%)' +
            (years ? ', over ' + years + ' complete year' + (years > 1 ? 's' : '') : '') + '.';
        if (t990 !== null) note += ' 99.0%: <b>' + fmt(t990, 1) + '°C</b>, 88 hours a year.';
        if (design !== null) {
            var d = t996 - design;
            note += '<br>Design temperature on the form is <b>' + fmt(Math.abs(d), 1) + ' K ' + (d > 0 ? 'colder' : 'milder') + '</b> than the measured 99.6%';
        }
        if (ch) note += '<br>Coldest hour: ' + period(ch.start, ch.end, '<b>' + fmt(ch.outsideT, 1) + '°C</b>', date(ch.start, true)) + ' on ' + esc(date(ch.start, true));
        // Each complete year, newest first
        if (years) {
            note += '<div class="hp-sys-chips">';
            det.blocks.forEach(function (b) {
                var y = new Date(b.start * 1000).getFullYear() + "–" + String(new Date(b.end * 1000).getFullYear()).slice(2);
                note += '<span class="hp-sys-badge" title="' + esc(b.hours + " hours, " + fmt(100 * b.coverage, 1) + "% coverage, coldest hour " + b.min + " °C") + '">' + y +
                    ' · ' + fmt(b.t996, 1) + '° <span class="hp-sys-badge-sub">' + fmt(b.t990, 1) + '° at 99%</span></span>';
            });
            note += '</div>';
        }
        note += '<span class="hp-ms-fine">From the weather service feed, not a sensor at the house</span>';

        return tile("Outside design temperature", fmt(t996, 1) + unit("°C 99.6%"), "hp-ms-cold", bar + '<div class="hp-sys-feature-note">' + note + '</div>', d996.preview);
    }

    // -- Emitter capacity: measured at ΔT50 against what the form implies ------
    function emitters() {
        // From the steady running episodes (signature/list), loaded when the card
        // opens and worked out in the browser; the example until then, or when
        // the system has no usable episodes
        var em = window.hp_ms_emitter, e = em && em.e ? em.e : pv("emitter_kw50");
        if (!e) return "";
        // Example scaled to this system: the output on the highest of the
        // coldest days at its flow temperature, so the preview hangs together
        var hc = mv("heat_coldest_days"), fc = mv("flowT_coldest_days"), fd = flow_dT(), need_dT = fd ? fd.dt.value : EMITTER_DT;
        if (e.preview && hc && fc) {
            var rt = hc.details && has(hc.details.roomT) ? hc.details.roomT : ROOM;
            var c50 = hc.value / Math.pow((fc.value - need_dT / 2 - rt) / 50, 1.3);
            if (isFinite(c50) && c50 > 1 && c50 < 60) {
                var k = c50 / e.value;
                e.value *= k; e.lower *= k; e.upper *= k;
                e.details.peaks.forEach(function (p) { p.kw *= k; p.sd *= k; });
            }
        }
        var det = e.details || {}, n = det.exponent || 1.3;
        // Implied by the form: its heat loss at its design flow temperature,
        // with the assumed flow - return and room temperatures
        var implied = null, mwt = num(m.flow_temp) ? m.flow_temp - EMITTER_DT / 2 : null;
        if (num(m.heat_loss) && mwt && mwt > ROOM + 2) implied = m.heat_loss / Math.pow((mwt - ROOM) / 50, n);
        // Flow temperature these emitters need for the estimated heat loss at design
        var hl = mv("heat_loss_design"), need = null;
        if (hl) need = ROOM + 50 * Math.pow(hl.value / e.value, 1 / n) + need_dT / 2;

        // Distribution of capacity over the episodes, weighted by heat: example
        // from the peaks; real data will carry the curve itself
        var peaks = det.peaks || [{ kw: e.value, share: 1, sd: 0.6 }];
        var x_hi = Math.ceil(Math.max(det.x_max || (e.upper || e.value) * 1.25, (implied || 0) * 1.1)), x_lo = 0;
        var pts = [], ymax = 0;
        if (det.curve) {
            // The episodes' KDE, on its own grid from 0
            det.curve.x.forEach(function (x, i) { if (x <= x_hi) { pts.push([x, det.curve.d[i]]); if (det.curve.d[i] > ymax) ymax = det.curve.d[i]; } });
        } else {
            for (var i = 0; i <= 120; i++) {
                var x = x_lo + (x_hi - x_lo) * i / 120, y = 0;
                peaks.forEach(function (p) { y += p.share / p.sd * Math.exp(-0.5 * Math.pow((x - p.kw) / p.sd, 2)); });
                pts.push([x, y]); if (y > ymax) ymax = y;
            }
        }
        var dist_d = "M0,60 " + pts.map(function (p) { return "L" + fmt(300 * (p[0] - x_lo) / (x_hi - x_lo), 1) + "," + fmt(60 - 54 * p[1] / ymax, 1); }).join(" ") + " L300,60 Z";
        var dist = '<div class="hp-ms-dist"><svg viewBox="0 0 300 60" preserveAspectRatio="none" aria-hidden="true"><path d="' + dist_d + '"/></svg>';
        if (e.lower !== null && e.upper !== null) dist += '<div class="hp-ms-dist-band" style="left:' + pct(e.lower, x_lo, x_hi) + '%;width:' + (pct(e.upper, x_lo, x_hi) - pct(e.lower, x_lo, x_hi)) + '%" title="80% of heat: ' + fmt(e.lower, 1) + ' to ' + fmt(e.upper, 1) + ' kW"></div>';
        dist += '<div class="hp-ms-dist-line" style="left:' + pct(e.value, x_lo, x_hi) + '%"></div>';
        dist += '<div class="hp-sys-scale-tag is-below" style="left:' + pct(e.value, x_lo, x_hi) + '%">Measured ' + fmt(e.value, 1) + ' kW</div>';
        if (implied) {
            dist += '<div class="hp-sys-scale-tick" style="left:' + pct(implied, x_lo, x_hi) + '%"></div>';
            dist += '<div class="hp-sys-scale-tag is-above" style="left:' + pct(implied, x_lo, x_hi) + '%">Form ' + fmt(implied, 1) + ' kW</div>';
        }
        dist += '<div class="hp-sys-scale-end is-lo">0</div><div class="hp-sys-scale-end is-hi">' + x_hi + ' kW</div></div>';

        var note = 'Radiator output at a 50 K mean water to room difference, from <b>' + e.samples + '</b> steady running periods' +
            (det.kwh ? ' (' + fmt(det.kwh, 0) + ' kWh)' : '') + ' weighted by heat' +
            (e.lower !== null ? '. 80% of heat: ' + fmt(e.lower, 1) + '–' + fmt(e.upper, 1) + ' kW' : '') + '.';
        var minor = peaks.filter(function (p) { return Math.abs(p.kw - e.value) > 1; });
        if (minor.length) note += ' A second peak at ' + minor.map(function (p) { return '<b>' + fmt(p.kw, 1) + ' kW</b> (' + Math.round(100 * p.share) + '% of heat)'; }).join(", ") + ': rooms or a zone switched off.';
        if (implied) note += '<br>Form: ' + fmt(m.heat_loss, 1) + ' kW at ' + fmt(m.flow_temp, 0) + '°C flow implies <b>' + fmt(implied, 1) + ' kW</b> (' + EMITTER_DT + ' K flow − return, ' + ROOM + '°C room)';
        if (need) {
            var cold = mv("flowT_coldest_days");
            note += '<br>For the estimated ' + fmt(hl.value, 1) + ' kW at design, at the measured ' + fmt(need_dT, 1) + ' K ΔT, these need about <b>' + fmt(need, 1) + '°C</b> flow' +
                (cold ? ' (coldest days measured ' + fmt(cold.value, 1) + '°C)' : '');
        }
        if (!e.preview) note += '<br>Worked out in the browser from the steady running episodes. <a href="' + path + 'emitter?id=' + config.id + '" target="_blank" rel="noopener">Open in the emitter explorer</a>';
        else if (em && em.message) note += '<br><b>' + esc(em.message) + '</b>';
        else if (!em) note += '<br>Loading the steady running episodes…';
        note += '<span class="hp-ms-fine">Exponent ' + n + ' assumed (radiators); underfloor heating is nearer 1.1. Episodes with mean water under 5 K above room are left out</span>';
        return tile("Emitter capacity", fmt(e.value, 1) + unit("kW at ΔT50"), "text-hp-heat",
            dist + '<div class="hp-sys-feature-note">' + note + '</div>', e.preview);
    }

    // -- Flow rate and flow - return ΔT: measured on the coldest days against design
    function flowrate() {
        var f = flow_dT();
        if (!f) return "";
        var fr = f.fr, dt = f.dt;
        var rmin = num(m.hp_min_flowrate), rmax = num(m.hp_max_flowrate);
        // Heat loss to carry at design: the estimate from the data, else the form
        var hl = mv("heat_loss_design"), q_design = hl ? hl.value : num(m.heat_loss);
        var need = q_design ? q_design / (CP_FLOW * DESIGN_DT) * 60 : null;

        // Flow rate bar: rated range shaded, measured dot and day range, flow rate needed at design ΔT
        var hi = Math.ceil(Math.max(fr.upper || fr.value, rmax || 0, need || 0) * 1.15 / 5) * 5;
        var bar = '<div class="hp-sys-scale is-flowrate">';
        if (rmin && rmax) bar += '<div class="hp-ms-rated" style="left:' + pct(rmin, 0, hi) + '%;width:' + (pct(rmax, 0, hi) - pct(rmin, 0, hi)) + '%" title="Rated ' + rmin + ' to ' + rmax + ' l/min (heat pump database)"></div>';
        if (fr.lower !== null && fr.upper !== null && fr.upper > fr.lower)
            bar += '<div class="hp-sys-scale-band" style="left:' + pct(fr.lower, 0, hi) + '%;width:' + (pct(fr.upper, 0, hi) - pct(fr.lower, 0, hi)) + '%"></div>';
        bar += '<div class="hp-sys-scale-dot is-flowrate" style="left:' + pct(fr.value, 0, hi) + '%"></div>';
        bar += '<div class="hp-sys-scale-tag is-below" style="left:' + pct(fr.value, 0, hi) + '%">Measured ' + fmt(fr.value, 1) + ' l/min</div>';
        if (need) {
            bar += '<div class="hp-sys-scale-tick" style="left:' + pct(need, 0, hi) + '%"></div>';
            bar += '<div class="hp-sys-scale-tag is-above" style="left:' + pct(need, 0, hi) + '%">Design ' + fmt(need, 1) + ' l/min at ' + DESIGN_DT + ' K</div>';
        }
        bar += '<div class="hp-sys-scale-end is-lo">0</div><div class="hp-sys-scale-end is-hi">' + hi + ' l/min</div></div>';

        // ΔT bar, 0 to 10 K: measured dot and day range, assumed design ΔT
        var dhi = Math.max(10, Math.ceil((dt.upper || dt.value) + 1));
        var dbar = '<div class="hp-sys-scale is-dT">';
        if (dt.lower !== null && dt.upper !== null && dt.upper > dt.lower)
            dbar += '<div class="hp-sys-scale-band" style="left:' + pct(dt.lower, 0, dhi) + '%;width:' + (pct(dt.upper, 0, dhi) - pct(dt.lower, 0, dhi)) + '%"></div>';
        dbar += '<div class="hp-sys-scale-dot is-flowrate" style="left:' + pct(dt.value, 0, dhi) + '%"></div>';
        dbar += '<div class="hp-sys-scale-tag is-below" style="left:' + pct(dt.value, 0, dhi) + '%">Measured ΔT ' + fmt(dt.value, 1) + ' K</div>';
        dbar += '<div class="hp-sys-scale-tick" style="left:' + pct(DESIGN_DT, 0, dhi) + '%"></div>';
        dbar += '<div class="hp-sys-scale-tag is-above" style="left:' + pct(DESIGN_DT, 0, dhi) + '%" title="The form has no design ΔT">Design ' + DESIGN_DT + ' K</div>';
        dbar += '<div class="hp-sys-scale-end is-lo">0</div><div class="hp-sys-scale-end is-hi">' + dhi + ' K</div></div>';

        var note = (f.source == "coldest" ? 'Space heating on the 3 coldest days, weighted by heat' : 'Space heating over the last 365 days') +
            ': <b>' + fmt(fr.details.heat_kw, 1) + ' kW</b> mean while running, ' + fmt(dt.value, 1) + ' K flow − return, so about <b>' + fmt(fr.value, 1) + ' l/min</b>.';
        if (need) {
            var carry = fr.value / 60 * CP_FLOW * DESIGN_DT;
            note += '<br>At ' + DESIGN_DT + ' K this flow rate carries <b>' + fmt(carry, 1) + ' kW</b>; the ' + (hl ? 'estimated ' : 'design ') + fmt(q_design, 1) + ' kW at design needs <b>' + fmt(need, 1) + ' l/min</b>.';
        }
        // A wider ΔT than designed lowers the mean water temperature for the same flow
        if (dt.value > DESIGN_DT + 0.5) note += '<br>A ' + fmt(dt.value, 1) + ' K ΔT puts the mean water temperature ' + fmt((dt.value - DESIGN_DT) / 2, 1) + ' K lower than at ' + DESIGN_DT + ' K for the same flow temperature.';
        if (rmin && rmax) {
            var where = fr.value < rmin ? 'below the rated range' : fr.value > rmax * 0.97 ? 'at the top of the rated range' : 'within the rated range';
            note += '<br>' + (fr.value > rmax * 0.97 || fr.value < rmin ? '<b>' : '') + where.charAt(0).toUpperCase() + where.slice(1) + (fr.value > rmax * 0.97 || fr.value < rmin ? '</b>' : '') +
                ' of ' + rmin + '–' + rmax + ' l/min for this model.';
        }
        if (fr.details.days && fr.details.days.length) {
            note += '<div class="hp-sys-chips">';
            fr.details.days.forEach(function (d) {
                note += period(d.start, d.end, '<span class="hp-sys-badge">' + chip_date(d.start) + ' · ' + fmt(d.flowrate, 1) + ' l/min · ' + fmt(d.dT, 1) + ' K' +
                    ' <span class="hp-sys-badge-sub">' + fmt(d.heat_kw, 1) + ' kW</span></span>', date(d.start) + ', ' + d.outsideT + ' °C outside' + (d.running_h ? ', ' + d.running_h + ' h space heating' : ''));
            });
            note += '</div>';
        }
        note += '<span class="hp-ms-fine">Flow rate worked out as heat ÷ (cp × ΔT) from the daily stats, not read from the flow meter' +
            (has(m.hydraulic_separation) && !/^(none|no)/i.test(m.hydraulic_separation) ? '. With hydraulic separation this is the heat pump side; the emitter side can differ' : '') + '</span>';
        return tile("Flow rate and ΔT", fmt(fr.value, 1) + unit("l/min, " + fmt(dt.value, 1) + " K"), "text-hp-flow",
            bar + dbar + '<div class="hp-sys-feature-note">' + note + '</div>');
    }

    // -- Efficiency ------------------------------------------------------------
    // Last 365 day stats, loaded by myheatpump.js for the totals card; the card
    // is drawn again when they arrive
    function last365() {
        if (window.totals && window.totals.last365) return window.totals.last365;
        if (window.hp_ms_last365) return window.hp_ms_last365;
        last365_load();
        return null;
    }

    // % of Carnot by mode, against the fleet
    function carnot() {
        var t = last365();
        if (!t && window.hp_last365_loaded) return "";
        if (!t) return tile("% of Carnot", '<span class="hp-sys-unit">loading</span>', "", '<div class="hp-sys-feature-note">Loading the last 365 days</div>');
        var modes = [["space", "Space heating"], ["water", "Hot water"], ["running", "When running"]];
        var rows = "", head = null;
        modes.forEach(function (md) {
            var prc = num(t[md[0] + "_prc_carnot"]), cop = num(t[md[0] + "_cop"]);
            if (!prc || !cop) return;
            if (head === null) head = [prc, md[1]];
            var f = MEASUREMENTS_FLEET.prc_carnot[md[0]].p;
            var ideal = cop * 100 / prc, flowT = num(t[md[0] + "_flowT_mean"]), outT = num(t[md[0] + "_outsideT_mean"]);
            rows += '<div class="hp-ms-eff-row"><div><div class="hp-ms-check-label">' + md[1] + '</div>' +
                '<div class="hp-ms-check-note">COP ' + cop.toFixed(2) + ' of an ideal ' + ideal.toFixed(1) +
                (flowT !== null && outT !== null ? ' at ' + fmt(flowT, 1) + '°C flow, ' + fmt(outT, 1) + '°C outside' : '') + '</div></div>' +
                '<div class="hp-ms-check-value"><span>' + prc.toFixed(1) + unit("%") + '</span>' +
                range_bar(prc, [f[0], f[2], f[1]], [25, 65], "%") + '</div></div>';
        });
        if (!rows) return "";
        var note = '<div class="hp-sys-feature-note">Measured COP as a share of the ideal (Carnot) COP at the same flow and outside temperatures, over the last 365 days. ' +
            'Takes out the effect of flow and outside temperature, so it shows how well the heat pump turns lift into heat. ' +
            'Ideal COP uses flow + 2 K and outside − 6 K. Shaded: the middle 80% of systems; line: median.</div>';
        return tile("% of Carnot", head[0].toFixed(1) + unit("% " + head[1].toLowerCase()), "text-hp-cop", rows + note);
    }

    // Level against the datasheet: from the steady running episodes (loaded
    // with the emitter tile) for Arotherm+ systems; for other makes the
    // Arotherm+ fit is only a stand-in, so it stays a concept. The example
    // until the episodes load or when there are too few
    function level() {
        var L = window.hp_ms_level, F = MEASUREMENTS_FLEET;
        var arotherm = /arotherm/i.test((m.hp_manufacturer || "") + " " + (m.hp_model || ""));
        var make = [m.hp_manufacturer, m.hp_model].filter(has).join(" ") || "this heat pump";
        var from_eps = L && L.value;
        var lv = from_eps ? { value: L.value, samples: L.hours, details: {} } : pv("replay_level");
        if (!lv) return "";
        var concept = !from_eps || !arotherm;
        var d = lv.details || {};
        var lo = 0.7, hi = 1.1, fl = F.level.p;
        var v = Math.min(hi, Math.max(lo, lv.value));
        var bar = '<div class="hp-sys-scale is-level">' +
            '<div class="hp-sys-scale-band" style="left:' + pct(fl[0], lo, hi) + '%;width:' + (pct(fl[2], lo, hi) - pct(fl[0], lo, hi)) + '%" title="Middle 80% of ' + F.level.n + ' Arotherm+ systems in the machine replay"></div>' +
            '<div class="hp-sys-scale-tick" style="left:' + pct(1, lo, hi) + '%"></div>' +
            '<div class="hp-sys-scale-tag is-above" style="left:' + pct(1, lo, hi) + '%">Datasheet 1.00</div>' +
            '<div class="hp-sys-scale-peak is-mid" style="left:' + pct(fl[1], lo, hi) + '%" title="Median ' + fl[1] + '"></div>' +
            '<div class="hp-sys-scale-dot is-cop" style="left:' + pct(v, lo, hi) + '%"></div>' +
            '<div class="hp-sys-scale-tag is-below" style="left:' + pct(v, lo, hi) + '%">This system ' + lv.value.toFixed(2) + '</div>' +
            '<div class="hp-sys-scale-end is-lo">' + lo + '</div><div class="hp-sys-scale-end is-hi">' + hi + '</div></div>';
        var b = bar;
        if (from_eps) {
            b += fact_html("Steady running used", fmt(L.hours, 0) + unit("h, " + L.samples + " of " + L.of + " episodes"));
            b += '<div class="hp-sys-fact hp-ms-fact-concept"><div class="hp-sys-fact-label">Peer level' + preview_tag(true) + '</div>' +
                '<div class="hp-sys-fact-value"><span class="hp-sys-unit">needs the fleet: same-size systems, same conditions</span></div></div>';
        } else {
            if (has(d.peer_level)) b += fact_html("Peer level", d.peer_level.toFixed(2) + unit("same-size systems, same conditions"));
            if (has(d.min_share)) b += fact_html("Steady running at minimum speed", fmt(100 * d.min_share, 0) + unit("% (most " + Math.round(100 * F.min_share.p[0]) + "–" + Math.round(100 * F.min_share.p[2]) + "%)"));
        }
        var t = last365(), spf = t ? num(t.combined_cop) : null;
        if (spf) b += fact_html("SPF, last 365 days", fmt(spf, 2) + unit("about " + fmt(spf / lv.value, 2) + " at level 1.00"));
        b += '<div class="hp-sys-feature-note">Field COP against the Arotherm+ datasheet COP at the same flow and outside temperatures and output, in steady space heating at 30–70 rps. ' +
            lv.value.toFixed(2) + ' means about ' + Math.abs(Math.round(100 * (1 / lv.value - 1))) + '% ' + (lv.value < 1 ? 'more' : 'less') + ' electricity than the datasheet for the same heat. ' +
            'It describes the gap, not its cause: the machine, the heat meter flow reading or something else.' +
            (from_eps ? '<br>Worked out in the browser from the steady running episodes (flow 25–45°C, at least 15 K lift). Reads about 0.01 higher than the machine replay on most systems.' : '') +
            (from_eps && !arotherm ? '<br><b>' + esc(make) + ':</b> the Arotherm+ fit is only a stand-in for other makes, so this is a concept.' : '') +
            (!from_eps && L && L.message ? '<br><b>' + esc(L.message) + '</b>' : '') +
            (!from_eps && !L ? '<br>Loading the steady running episodes…' : '') + '</div>';
        return tile("Level against the datasheet", lv.value.toFixed(2), "text-hp-cop", b, concept);
    }

    // -- Heat loss figures behind the Sizing tile ------------------------------
    function heatloss_stats() {
        var hd = mv("heat_loss_design"), hdm = mv("heat_loss_design_measured"), htc = mv("heat_loss_htc"), base = mv("heat_loss_base_dT");
        var s = "";
        function stat(label, value, u, title) {
            return '<div class="hp-ms-stat"' + (title ? ' title="' + esc(title) + '"' : '') + '><div class="hp-sys-fact-label">' + esc(label) + '</div><div class="hp-ms-stat-value">' + value + unit(u) + '</div></div>';
        }
        if (htc) s += stat("Heat transfer coefficient", fmt(htc.value, 0), "W/K", "Slope of daily heat against inside − outside temperature, " + htc.samples + " days");
        if (base) s += stat("Balance point", fmt(base.value, 1), "K", "Temperature difference where the fit reaches zero heat: internal and solar gains");
        if (hd) {
            var at = hd.details && has(hd.details.design_outsideT) ? " at " + fmt(hd.details.design_outsideT, 1) + "°C" : "";
            var ci = hd.details && hd.details.ci80 ? " ±" + fmt(hd.details.ci80, 2) : "";
            s += stat("Heat demand" + at, fmt(hd.value, 2), "kW" + ci, "± is the 80% confidence interval of the fit at design; single days spread " + fmt(hd.lower, 1) + " to " + fmt(hd.upper, 1) + " kW");
        }
        if (hdm && (!hd || hd.details.design_outsideT_source != "measured")) s += stat("At measured 99.6%", fmt(hdm.value, 2), "kW");
        if (hd && num(m.floor_area)) s += stat("Per floor area", fmt(1000 * hd.value / m.floor_area, 0), "W/m²", "Measured heat demand / " + m.floor_area + " m²" +
            (num(m.heat_loss) ? ". Form: " + fmt(1000 * m.heat_loss / m.floor_area, 0) + " W/m²" : ""));
        return s ? '<div class="hp-ms-stats">' + s + '</div>' : '';
    }

    // -- Flow temperature against outside temperature, weighted by heat --------
    // The map, shown in the Flow temperature tile under the coldest days
    function flowmap() {
        var map = flowmap_build(mv);
        if (!map) return "";
        var det = map.fit, res = "";
        if (det) {
            // Kind of control from the slope, then the fitted line at each end
            var kind = det.b <= -0.3 ? ["is-good", "bi-thermometer-half", "Weather compensation", ""] :
                det.b <= -0.1 ? ["", "bi-thermometer-half", "Gentle slope", "A shallow weather compensation curve, or a fixed flow temperature with room temperature control"] :
                ["", "bi-dash-lg", "Fixed flow temperature", "A fixed flow temperature or room thermostat control"];
            res = '<span class="hp-sys-badge ' + kind[0] + '"' + (kind[3] ? ' title="' + esc(kind[3]) + '"' : '') + '><i class="bi ' + kind[1] + '"></i>' + kind[2] + '</span>';
            if (det.b <= -0.1) res += ' <b>' + fmt(det.a + det.b * det.x_lo, 1) + '°</b> at ' + fmt(det.x_lo, 0) + '°C to <b>' +
                fmt(det.a + det.b * det.x_hi, 1) + '°</b> at ' + fmt(det.x_hi, 0) + '°C, ' + Math.round(100 * det.within) + '% of heat within ±2 K';
        }
        return '<div class="hp-ms-map-block"><div class="hp-ms-map-top">' +
            '<div class="hp-ms-map-legend" title="Colour is scaled within each outside temperature; the bars above show each one\'s heat"><span>Less</span><span class="hp-ms-map-ramp"></span><span>More heat at each outside temperature</span></div></div>' +
            '<div id="hp-ms-map" class="hp-ms-map"><canvas role="img" aria-label="Space heating delivered at each flow and outside temperature"></canvas><div class="hp-ms-tip" hidden></div></div>' +
            (res ? '<div class="hp-ms-map-result">' + res + '</div>' : '') +
            '<span class="hp-ms-fine">Space heating below ' + FLOWMAP_MAX_OUTSIDE + '°C outside from 10 s data, ' + map.n + ' days in the last year</span></div>';
    }

    // -- Primary pipework and system volume ------------------------------------
    function pipework() {
        var vol = pv("loop_volume"), delay = pv("loop_delay"), unit_w = pv("unit_water_content"), sys_vol = pv("system_volume");
        if (!vol) return "";
        var pipes = { 22: 0.32, 28: 0.54, 35: 0.83 };
        var uw = unit_w ? unit_w.value : 0;
        var each = Math.max(0, (vol.value - uw) / pipes[hp_ms_state.pipe] / 2);

        var ev = vol.details.events || [];
        var lo = Math.floor(Math.min.apply(null, ev.concat([vol.value])) - 1), hi = Math.ceil(Math.max.apply(null, ev.concat([vol.value])) + 1);
        var strip = '<div class="hp-sys-scale is-events" title="Volume from each hot water switch-over">';
        ev.forEach(function (v) { strip += '<div class="hp-ms-event" style="left:' + pct(v, lo, hi) + '%"></div>'; });
        if (vol.details.spike_volume) strip += '<div class="hp-sys-scale-tick" style="left:' + pct(vol.details.spike_volume, lo, hi) + '%" title="Spike energy method ' + vol.details.spike_volume + ' L"></div>';
        strip += '<div class="hp-sys-scale-end is-lo">' + lo + ' L</div><div class="hp-sys-scale-end is-hi">' + hi + ' L</div></div>';

        var b = strip;
        if (delay) b += fact_html("Loop delay", fmt(delay.value, 0) + unit("s" + (delay.lower !== null ? ", " + delay.lower + "–" + delay.upper : "")));
        if (delay && delay.details.flowrate) b += fact_html("Flow rate", fmt(delay.details.flowrate, 1) + unit("l/min"));
        if (vol.details.spike_volume) b += fact_html("Spike energy check", fmt(vol.details.spike_volume, 1) + unit("L"));
        if (unit_w) b += fact_html("Heat pump and meter water", fmt(uw, 1) + unit("L, " + (unit_w.details.source || "")));
        b += '<div class="hp-ms-pipe-row"><span class="hp-sys-fact-label">Primaries, each way</span>' +
            '<div class="btn-group app-segmented">' + Object.keys(pipes).map(function (p) {
                return '<button class="btn hp-ms-pipe' + (hp_ms_state.pipe == p ? ' active' : '') + '" data-pipe="' + p + '" title="' + pipes[p] + ' L per metre">' + p + ' mm</button>';
            }).join("") + '</div><b class="hp-ms-pipe-len">' + fmt(each, 1) + unit("m") + '</b></div>';
        if (has(m.hydraulic_separation) && !/^(none|no)/i.test(m.hydraulic_separation)) {
            b += '<div class="hp-sys-flag"><i class="bi bi-info-circle"></i>Hydraulic separation on the form (' + esc(m.hydraulic_separation) + '): if it is between the meter and the heat pump, the volume includes it</div>';
        }
        b += '<div class="hp-sys-feature-note">From ' + vol.samples + ' hot water switch-overs: the time a step in return temperature takes to reach the flow sensor, times the flow rate. ' +
            'Includes the water in the heat pump, so the length depends on its water content and the pipe size.</div>';

        if (sys_vol) {
            b += '<div class="hp-ms-subhead">Heating system volume' + preview_tag(sys_vol.preview) + '</div>';
            b += fact_html("Whole system", fmt(sys_vol.value, 0) + unit("L" + (sys_vol.lower !== null ? ", " + sys_vol.lower + "–" + sys_vol.upper : "")));
            if (num(m.hp_output)) b += fact_html("Per kW of heat pump", fmt(sys_vol.value / m.hp_output, 0) + unit("L/kW"));
            b += fact_html("Primary loop share", fmt(100 * vol.value / sys_vol.value, 0) + unit("%"));
            if (m.volumiser) b += fact_html("Volumiser", "Yes");
            b += '<div class="hp-sys-feature-note">From the rise in mean water temperature after ' + sys_vol.samples + ' starts, with the emitter capacity above. ' +
                'Today the Options panel works this out for one window at a time.</div>';
        }
        return tile("Primary loop", fmt(vol.value, 1) + unit("L"), "text-hp-flow", b, vol.preview);
    }

    // -- Hot water cylinder standby loss ---------------------------------------
    function cylinder() {
        if (has(m.dhw_method) && /^none/i.test(m.dhw_method)) return "";
        var U = pv("dhw_standby_U");
        if (!U) return "";
        var V = num(m.cylinder_volume), V_used = V || 200;
        var target = num(m.dhw_target_temperature) || 50, env = U.details.env_temp || 18;
        var loss_w = U.value * (target - env);
        var t_half = Math.LN2 * V_used * CP * 1000 / U.value / 86400;

        var b = '';
        if (U.details.fleet) b += '<div class="hp-ms-range-row"><span class="hp-sys-fact-label">Against other cylinders</span>' + range_bar(U.value, U.details.fleet, [0, 4], " W/K") + '</div>';
        b += fact_html("Standby loss at " + fmt(target, 0) + "°C", fmt(loss_w, 0) + unit("W, " + fmt(loss_w * 24 / 1000, 1) + " kWh/day"));
        b += fact_html("Half-life of the charge", fmt(t_half, 1) + unit("days"));
        b += fact_html("Cylinder", fmt(V_used, 0) + unit("L" + (V ? "" : " assumed")));
        if (U.lower !== null) b += fact_html("80% range", fmt(U.lower, 1) + "–" + fmt(U.upper, 1) + unit("W/K"));
        b += '<div class="hp-sys-feature-note">From ' + U.samples + ' periods of cylinder temperature falling with no reheat or draw-off, assuming the cylinder is in a ' + env + '°C space. ' +
            'Needs a cylinder temperature in °C, not % charge. Today the Options panel works this out for one window at a time.</div>';
        return tile("Hot water cylinder", fmt(U.value, 1) + unit("W/K"), "text-hp-flow", b, U.preview);
    }

    // -- Metering and data checks ----------------------------------------------
    // Rows from the last 365 days' stats, the feed list and the form; rows that
    // need a feed scan show example values, greyed
    function checks() {
        var t = last365(), c = pv("data_checks"), d = c ? c.details : {};
        var rows = [];
        var icon = { good: "check-circle-fill", warn: "exclamation-triangle-fill", info: "info-circle-fill" };
        var word = { good: "OK", warn: "Check", info: "Note" };
        function row(status, label, value, note, extra, concept) {
            rows.push('<div class="hp-ms-check is-' + status + (concept ? ' is-preview' : '') + '"><i class="bi bi-' + icon[status] + '" title="' + word[status] + '" aria-label="' + word[status] + '"></i>' +
                '<div class="hp-ms-check-text"><div class="hp-ms-check-label">' + esc(label) + preview_tag(concept) + '</div>' + (note ? '<div class="hp-ms-check-note">' + note + '</div>' : '') + '</div>' +
                '<div class="hp-ms-check-value"><span>' + value + '</span>' + (extra || '') + '</div></div>');
        }
        function in_fleet(r) { return r.value >= r.fleet[0] && r.value <= r.fleet[1]; }

        if (t) {
            var days = (t.combined_data_length || 0) / 86400;
            // Coverage: heat meter data, with any other feed below 95% named
            var q = { "Heat": t.quality_heat, "Electricity": t.quality_elec, "Flow temperature": t.quality_flowT, "Return temperature": t.quality_returnT, "Outside temperature": t.quality_outsideT };
            var low = [];
            for (var k in q) if (has(q[k]) && q[k] < 95) low.push(k.toLowerCase() + " " + fmt(q[k], 1) + "%");
            if (has(t.quality_heat)) row(t.quality_heat >= 95 && !low.length ? "good" : "warn", "Data coverage", fmt(t.quality_heat, 1) + unit("%"),
                "Share of the last 365 days with heat meter data" + (low.length ? ": " + esc(low.join(", ")) : ""));

            // Hot water flag: set at all in the last year
            var cylinder = has(m.dhw_method) && !/^none/i.test(m.dhw_method);
            if (t.water_data_length > 0) row("good", "Hot water flag", "Set", fmt(t.water_heat_kwh, 0) + " kWh of hot water heat in the last 365 days");
            else if (cylinder) row("warn", "Hot water flag", "Never set", "The form lists hot water, but no hot water heat was recorded: reheats count as space heating");
            else if (has(m.dhw_method)) row("info", "Hot water flag", "No hot water", "No hot water on the form");

            // Room temperature feed
            var rq = num(t.quality_roomT);
            row(rq ? "good" : "warn", "Room temperature feed", rq ? "Present" : "Missing",
                rq ? "Used for heat loss and emitter capacity (" + fmt(rq, 1) + "% of the year)" : "20°C assumed for heat loss and emitter capacity");
        }
        // Flow rate feed, from the feed list
        if (typeof feeds == "object" && feeds && Object.keys(feeds).length) {
            var has_fr = feeds["heatpump_flowrate"] !== undefined;
            row(has_fr ? "good" : "info", "Flow rate feed", has_fr ? "Present" : "Missing", has_fr ? "" : "Flow rate is worked out from heat and ΔT instead");
        }
        if (t) {
            // Other heat sources: metered immersion and boiler heat, and the form
            var other = [];
            if (t.immersion_kwh > 0) other.push("immersion " + fmt(t.immersion_kwh, 0) + " kWh");
            if (t.boiler_kwh > 0) other.push("boiler " + fmt(t.boiler_kwh, 0) + " kWh");
            if (m.hp_hybrid) other.push("hybrid boiler (form)");
            if (m.uses_backup_heater) other.push("backup heater (form)");
            row(other.length ? (t.boiler_kwh > 0 || m.hp_hybrid ? "warn" : "info") : "good", "Other heat sources", other.length ? "Yes" : "None recorded",
                other.length ? esc(other.join(", ")) + ". Heat from other sources lowers the measured heat loss" : "Heat from other sources would lower the measured heat loss");

            // Heat meter air errors
            if (has(t.error_air_kwh)) row(t.error_air_kwh > 0 ? "warn" : "good", "Heat meter air errors", t.error_air_kwh > 0 ? fmt(t.error_air_kwh, 1) + unit("kWh") : "None",
                t.error_air_kwh > 0 ? "Electricity used while the heat meter reported air in the system" : "No periods with the heat meter reporting air");

            // Standby: electricity while not running (below the starting power), off and pump overrun
            var off_s = (t.combined_data_length || 0) - (t.running_data_length || 0);
            if (off_s > 86400 && has(t.combined_elec_kwh) && has(t.running_elec_kwh)) {
                var sb = (t.combined_elec_kwh - t.running_elec_kwh) * 1000 / (off_s / 3600);
                var r = { value: sb, fleet: [8, 21], scale: [0, 40] };
                row(in_fleet(r) ? "good" : "info", "Standby power", fmt(sb, 0) + unit("W"), "Mean while not running, including pump overrun",
                    range_bar(sb, r.fleet, r.scale, " W"));
            }
            // Starts per day
            if (has(t.combined_starts) && days > 1) row("info", "Starts per day", fmt(t.combined_starts / days, 1), "Compressor starts, last 365 days");
        }
        if (m.data_flag) row("warn", "Flagged", "Yes", esc(has(m.data_flag_note) ? m.data_flag_note : "Data issue flagged"));

        // Still concept: these need the replay and pump-only feed scans
        if (d.sensor_offset) row(in_fleet(d.sensor_offset) ? "good" : "warn", "Heat meter sensor offset", signed(d.sensor_offset.value, 2) + unit("K"),
            "Flow − return with the pump running and the compressor off", range_bar(d.sensor_offset.value, d.sensor_offset.fleet, d.sensor_offset.scale, " K"), c.preview);
        if (d.meter_k) row(in_fleet(d.meter_k) ? "good" : "warn", "Heat meter consistency", fmt(d.meter_k.value, 1),
            "Heat ÷ (flow rate × ΔT) in steady running, about 69.5 expected", range_bar(d.meter_k.value, d.meter_k.fleet, d.meter_k.scale, ""), c.preview);
        if (d.pump_only_power) row(in_fleet(d.pump_only_power) ? "good" : "info", "Pump-only power", fmt(d.pump_only_power.value, 0) + unit("W"), "Pump running, compressor off",
            range_bar(d.pump_only_power.value, d.pump_only_power.fleet, d.pump_only_power.scale, " W"), c.preview);
        if (d.dhw_flag) row(d.dhw_flag.status, "Reheats the flag misses", "None", "Fast flow temperature rises followed by hot water, with no flag", null, c.preview);
        if (!rows.length) return "";
        return section("Metering and data checks", "The figures above depend on these. Shaded: the middle 80% of systems", '<div class="hp-ms-checks">' + rows.join("") + '</div>');
    }

    // -- Card ------------------------------------------------------------------
    var design = '<div class="hp-ms-grid">' + sizing() + flow() + outside() + flowrate() + emitters() + '</div>' + heatloss_stats();
    var body = section("Design: measured against the form", "Each tile compares what the monitoring data shows with the system form", design) +
        section("Efficiency", "How close the heat pump runs to the ideal and to its datasheet",
            '<div class="hp-ms-grid">' + carnot() + level() + '</div>') +
        '<div class="hp-ms-grid">' + pipework() + cylinder() + '</div>' +
        checks();

    var updated = 0;
    for (var k in ms) if (ms[k].updated > updated) updated = ms[k].updated;
    var note = '<span class="app-caption-note">' + (updated ? 'From the monitoring data, ' + esc(date(updated)) : 'From the monitoring data') + '</span>';
    H.card(el, "measurements", "Measurements", note, body, measurements_on_open);
    if (heatdemand_autoopen) heatdemand_show();
}

// Flow temperature map data: space heating (kWh) in bins of outside (x) and flow (y)
// temperature, below FLOWMAP_MAX_OUTSIDE outside, and the fit (peaks and line), from the
// nightly scan's flowT_map. Grids are [i][j], i from x0 in steps of xs, j from
// y0 in steps of ys. Null until the PNG has decoded, or with no flowT_map
var FLOWMAP_MAX_OUTSIDE = 15;   // °C, as the measurement script's FLOWMAP_MAX_OUTSIDE
var FLOWMAP_RIDGE_K = 5;        // K: the map shows flow this far either side of the lowest and highest peak dots
var FLOWMAP_GAMMA = 2;          // colour = (heat / column max) ^ this: above 1 fades the weaker cells and leaves the peaks
var FLOWMAP_FLOOR = 0.03;       // opacity of the red for no heat, so the plot area reads as continuous
function flowmap_build(mv) {
    var fm = mv("flowT_map");
    return fm && fm.details && fm.details.png ? flowmap_from_png(fm) : null;
}

// From the nightly flowT_map: an 8 bit greyscale PNG of space heating rows,
// highest flow first (older maps have hot water rows below: not read); a pixel v > 0 is
// exp((v - 1) / 254 × ln(max_wh)) Wh. Decoded once (asynchronously: returns
// null until then, and draws the card again when done)
function flowmap_from_png(fm) {
    var c = window.hp_ms_png;
    if (c && c.updated == fm.updated) return c.data;
    if (c && c.loading == fm.updated) return null;
    window.hp_ms_png = { loading: fm.updated };
    var dt = fm.details, img = new Image();
    img.onload = function () {
        var cv = document.createElement("canvas");
        cv.width = dt.nx; cv.height = 2 * dt.ny;
        var cx = cv.getContext("2d");
        cx.drawImage(img, 0, 0);
        var px = cx.getImageData(0, 0, dt.nx, 2 * dt.ny).data;
        // Columns below FLOWMAP_MAX_OUTSIDE (older maps go higher)
        var nx = Math.max(1, Math.min(dt.nx, Math.round((FLOWMAP_MAX_OUTSIDE - dt.x0) / dt.x_step)));
        function grid(half, max_wh) {
            var g = [], ln = Math.log(Math.max(max_wh, 1.001));
            for (var i = 0; i < nx; i++) {
                var col = [];
                for (var j = 0; j < dt.ny; j++) {
                    var v = px[4 * ((half * dt.ny + dt.ny - 1 - j) * dt.nx + i)];
                    col.push(v > 0 ? Math.exp((v - 1) / 254 * ln) / 1000 : 0);
                }
                g.push(col);
            }
            return g;
        }
        // Peak flow temperature in each 1 K of outside temperature and the line through them, from the nightly fit
        var f = dt.fit, fit = f && f.peaks && f.peaks.length ? { a: f.intercept, b: f.slope, within: f.within_2K,
            peaks: f.peaks.map(function (p) { return { x: p[0], y: p[1] }; }),
            x_lo: f.peaks[0][0] - 0.5, x_hi: f.peaks[f.peaks.length - 1][0] + 0.5 } : null;
        var data = { x0: dt.x0, xs: dt.x_step, nx: nx, y0: dt.y0, ys: dt.y_step, ny: dt.ny,
            space: grid(0, dt.max_wh.space), n: fm.samples, fit: fit };
        window.hp_ms_png = { updated: fm.updated, data: data };
        measurements_card_render();
    };
    img.onerror = function () { window.hp_ms_png = { updated: fm.updated, data: null }; };
    img.src = "data:image/png;base64," + dt.png;
    return null;
}

// Last 365 day stats for this card, when the totals card hasn't loaded them
// (myheatpump.js loads them only once the app config has loaded)
function last365_load() {
    if (window.hp_ms_last365_loading || typeof config == "undefined") return;
    window.hp_ms_last365_loading = true;
    $.ajax({
        url: path + "system/stats/last365",
        data: { id: config.id, readkey: apikey },
        dataType: "json",
        success: function (result) {
            window.hp_ms_last365 = result && result[config.id] ? result[config.id] : null;
            window.hp_last365_loaded = true;
            measurements_card_render();
        },
        error: function () { window.hp_last365_loaded = true; measurements_card_render(); }
    });
}

// Vaillant Arotherm+ generic v2 datasheet COP fit, ported from
// scripts/feed_scan/arotherm_model.php (the machine replay's model). Pre-defrost
var AROTHERM_FIT = {
    // Heat output per rps as a share of nominal capacity, to infer speed
    volcap: function (f, o) { return Math.max((1.070e-2 + 2.889e-4 * o + 6.495e-7 * o * o) * (1 - 9.330e-3 * (f - 50)), 1e-4); },
    speed: function (q, f, o, kw) { return kw / (q * AROTHERM_FIT.volcap(f, o)); },
    cop: function (q, f, o, kw) {
        var p = [0.24930, 0.74153, -0.48742, 0.66656, -1.05159, 0.32990, -0.03477, -0.00303];
        var qh = kw / q, s = Math.min(Math.max(AROTHERM_FIT.speed(q, f, o, kw), 30), 120) / 100;
        var Tc = f + 273.15 + 4 * qh, Te = o + 273.15 - 7 * qh, L = Math.max(Tc - Te, 5), z = (L - 45) / 45, w = (f - 50) / 15;
        var eta = (p[0] + p[1] * s + p[2] * s * s + p[3] * s * z) * (1 + p[4] * z + p[5] * z * z + p[6] * z * z * z) * (1 + p[7] * w);
        return eta * Tc / L;
    }
};

// Level against the datasheet from the steady running episodes: datasheet
// electricity for the same heat / measured electricity, over episodes at
// 30-70 rps (the replay's level band). Space heating only (flow 25-45 °C) and
// at least 15 K of lift: below that the fit, whose data starts at 35 °C flow,
// gives far too much credit. Checked against the replay on 13 Arotherm+
// systems: r 0.98, a median 0.008 higher, at most 0.046 apart
var LEVEL_MIN_HOURS = 20;
function level_from_episodes(rows, qnom) {
    var model_e = 0, e = 0, hours = 0, n = 0, all = 0;
    rows.forEach(function (r) {
        var h = +r.heat, el = +r.elec, f = +r.flowT, o = +r.outsideT, d = +r.duration;
        if (!(h > 0 && el > 0 && d > 0) || isNaN(f) || isNaN(o)) return;
        all++;
        if (f < 25 || f > 45 || f - o < 15) return;
        var kw = h / 1000, rps = AROTHERM_FIT.speed(qnom, f, o, kw);
        if (rps < 30 || rps > 70) return;
        model_e += h / AROTHERM_FIT.cop(qnom, f, o, kw) * d; e += el * d; hours += d / 3600; n++;
    });
    if (hours < LEVEL_MIN_HOURS) return { message: "Too few steady hours at 30–70 rps in the datasheet's range (" + Math.round(hours) + " h, " + LEVEL_MIN_HOURS + " needed)" };
    return { value: model_e / e, hours: hours, samples: n, of: all };
}

// Emitter capacity from the steady running episodes (signature_episodes), with
// the emitter explorer's method (emitter_stats.js): rated output at ΔT50 with
// exponent 1.3, episodes with mean water at least 5 K above room, weighted by
// heat. Loaded once, when the card first opens: a system can have 1 MB or more
// of episodes. Sets window.hp_ms_emitter to { e } or { message }
var EMITTER_PARAMS = { ratedDT: 50, n: 1.3, minDT: 5 };
function emitter_load() {
    if (window.hp_ms_emitter || window.hp_ms_emitter_loading || typeof config == "undefined" || typeof transform != "function") return;
    window.hp_ms_emitter_loading = true;
    function done(r, lv) {
        window.hp_ms_emitter = r;
        window.hp_ms_level = lv || { message: r.message };
        measurements_card_render();
    }
    $.ajax({
        url: path + "signature/list",
        data: { id: config.id },
        dataType: "json",
        success: function (rows) {
            if (!rows || rows.error || !rows.length) return done({ message: rows && rows.error ? rows.error : "No steady running episodes for this system yet" });
            // Level against the datasheet, from the same episodes
            var qnom = window.system_meta ? parseFloat(window.system_meta.hp_output) : NaN;
            var lv = qnom > 0 ? level_from_episodes(rows, qnom) : { message: "Needs the heat pump's output on the form" };
            var eps = transform(rows), pts = [], below = 0;
            eps.forEach(function (ep) {
                var kw = ratedKw(ep, EMITTER_PARAMS);
                if (isNaN(kw)) { below++; return; }
                pts.push({ kw: kw, kwh: ep.kwh });
            });
            if (pts.length < 20) return done({ message: eps.length ? "Too few usable episodes (" + pts.length + ")" : "The episodes need heat, return and room temperature" }, lv);
            var kwh = pts.reduce(function (a, q) { return a + q.kwh; }, 0);
            var x_max = weightedPercentile(pts, 0.99) * 1.1;
            var bw = autoBandwidth(pts, 0.5);
            var pk = findPeaks(pts, bw, x_max);
            var curve = kdeCurve(pts, bw, x_max, 160);
            done({ e: {
                value: weightedMedian(pts), lower: weightedPercentile(pts, 0.1), upper: weightedPercentile(pts, 0.9),
                samples: pts.length, unit: "kW",
                details: { exponent: EMITTER_PARAMS.n, kwh: kwh, below_dT: below, x_max: x_max, curve: curve, bw: bw,
                    peaks: pk.peaks.map(function (p) { return { kw: p.kw, share: p.kwh / kwh, sd: bw }; }) }
            } }, lv);
        },
        error: function () { done({ message: "Could not load the steady running episodes" }); }
    });
}

// Runs when the card opens: the episodes for the emitter tile, the daily data
// for the heat demand tool, the map and the heat demand chart
function measurements_on_open() {
    emitter_load();
    heatdemand_load();
    flowmap_draw();
    heatdemand_draw();
}

// Draws the map to fit its box; called when the card opens and on resize
function flowmap_draw() {
    var wrap = document.getElementById("hp-ms-map"), d = window.hp_ms_png ? window.hp_ms_png.data : null;
    if (!wrap || !d || wrap.offsetParent === null) return;
    var canvas = wrap.querySelector("canvas"), tip = wrap.querySelector(".hp-ms-tip");
    var W = wrap.clientWidth, Ht = W < 500 ? 285 : 350;
    var dpr = window.devicePixelRatio || 1;
    canvas.width = Math.round(W * dpr); canvas.height = Math.round(Ht * dpr);
    canvas.style.height = Ht + "px";
    var ctx = canvas.getContext("2d");
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, W, Ht);

    var cs = getComputedStyle(wrap);
    var ink_rgb = (cs.getPropertyValue("--bs-body-color-rgb") || "34,34,34").trim();
    var ink = "rgb(" + ink_rgb + ")", faint = "rgba(" + ink_rgb + ",0.09)", muted = "rgba(" + ink_rgb + ",0.65)";
    // Strip above the map: each outside temperature column's heat
    var SH = 22, L = 38, R = 10, T = 10 + SH + 6, B = 34, pw = W - L - R, ph = Ht - T - B;

    // Space heating. Both axes are cropped to the cells that hold the
    // heat: stray cells at either end with 0.5% of the heat between them are
    // left out and 1 K is added each side. Flow is then cropped to
    // FLOWMAP_RIDGE_K either side of the peak dots, leaving out the long tail
    // of warm-up heat below the curve; the inferred design point is kept in
    var grid = d.space, total = 0, colsum = [], colmax = [], rowsum = [];
    for (var j = 0; j < d.ny; j++) rowsum.push(0);
    for (var i = 0; i < d.nx; i++) {
        var sum = 0, cm = 0;
        for (var j = 0; j < d.ny; j++) { var v = grid[i][j]; sum += v; rowsum[j] += v; if (v > cm) cm = v; }
        colsum.push(sum); colmax.push(cm); total += sum;
    }
    // First and last bin once more than `tail` of the heat has been passed from each end
    function span(sums, tail) {
        var a = 0, b = sums.length - 1, acc = 0;
        while (a < b && acc + sums[a] <= tail) acc += sums[a++];
        acc = 0;
        while (b > a && acc + sums[b] <= tail) acc += sums[b--];
        return [a, b + 1];
    }
    // In bins: [i0, i1) outside, [j0, j1) flow
    var tail = 0.005 * total;
    var sx = total > 0 ? span(colsum, tail) : [0, d.nx], sy = total > 0 ? span(rowsum, tail) : [0, d.ny];
    var i0 = sx[0], i1 = sx[1], j0 = sy[0], j1 = sy[1];
    var pi = Math.round(1 / d.xs), pj = Math.round(1 / d.ys), mj = Math.round(6 / d.ys);
    i0 = Math.max(0, i0 - pi); i1 = Math.min(d.nx, i1 + pi);
    j0 = Math.max(0, j0 - pj); j1 = Math.min(d.ny, j1 + pj);
    // Flow bin of a temperature
    function fj(t) { return Math.floor((t - d.y0) / d.ys); }
    if (d.fit && d.fit.peaks.length) {
        var pys = d.fit.peaks.map(function (p) { return p.y; });
        j0 = Math.max(j0, fj(Math.min.apply(null, pys) - FLOWMAP_RIDGE_K));
        j1 = Math.min(j1, fj(Math.max.apply(null, pys) + FLOWMAP_RIDGE_K) + 1);
    }
    var msr = window.system_measurements || {}, inf = msr.flowT_design_inferred && msr.flowT_design_inferred.value !== null ? msr.flowT_design_inferred : null;
    var inf_x = inf && inf.details ? inf.details.design_outsideT : null;
    if (inf_x === null || inf_x === undefined) inf = null;
    if (inf) {
        var ii = Math.floor((inf_x - d.x0) / d.xs), ij = fj(inf.value);
        // It can be colder than any of the data (the design outside temperature):
        // the axes then go beyond the grid, with no heat there
        i0 = Math.min(i0, ii - pi); i1 = Math.max(i1, ii + 1 + pi);
        j0 = Math.min(j0, ij - pj); j1 = Math.max(j1, ij + 1 + pj);
    }
    if (j1 - j0 < mj) { j0 = Math.floor((j0 + j1 - mj) / 2); j1 = j0 + mj; }
    var nx = i1 - i0, ny = j1 - j0;
    var xlo = d.x0 + i0 * d.xs, xhi = d.x0 + i1 * d.xs, lo = d.y0 + j0 * d.ys, hi = d.y0 + j1 * d.ys;

    var colsum_max = Math.max.apply(null, colsum.slice(Math.max(0, i0), Math.max(0, i1)));

    var cw = pw / nx, ch = ph / ny;
    function X(t) { return L + (t - xlo) / (xhi - xlo) * pw; }
    function Y(t) { return T + ph - (t - lo) / (hi - lo) * ph; }

    // Grid and axes
    ctx.font = "11px " + cs.fontFamily;
    ctx.lineWidth = 1;
    ctx.fillStyle = muted;
    ctx.textAlign = "center"; ctx.textBaseline = "top";
    for (var t = Math.ceil(xlo / 5) * 5; t <= xhi; t += 5) {
        ctx.strokeStyle = faint; ctx.beginPath(); ctx.moveTo(Math.round(X(t)) + 0.5, T); ctx.lineTo(Math.round(X(t)) + 0.5, T + ph); ctx.stroke();
        ctx.fillText(t + "°", X(t), T + ph + 5);
    }
    ctx.textAlign = "right"; ctx.textBaseline = "middle";
    for (var t = Math.ceil(lo / 5) * 5; t <= hi; t += 5) {
        ctx.strokeStyle = faint; ctx.beginPath(); ctx.moveTo(L, Math.round(Y(t)) + 0.5); ctx.lineTo(L + pw, Math.round(Y(t)) + 0.5); ctx.stroke();
        ctx.fillText(t + "°", L - 6, Y(t));
    }
    ctx.textAlign = "center"; ctx.textBaseline = "bottom";
    ctx.fillText("Outside temperature", L + pw / 2, Ht - 1);
    ctx.save(); ctx.translate(10, T + ph / 2); ctx.rotate(-Math.PI / 2); ctx.textBaseline = "middle"; ctx.fillText("Flow temperature", 0, 0); ctx.restore();

    // Column heat strip
    ctx.fillStyle = "rgba(201,54,47,0.45)";
    for (var i = i0; i < i0 + nx; i++) {
        if (!(colsum[i] > 0)) continue;
        var bh = Math.max(1, SH * colsum[i] / colsum_max);
        ctx.fillRect(L + (i - i0) * cw, 10 + SH - bh, cw + 0.3, bh);
    }

    // Cells as one image, a pixel per cell scaled up without smoothing. Colour is
    // scaled within each column, so the spread at cold and mild outside
    // temperatures shows as clearly as in the common ones; no heat is the
    // faintest tint, so the area reads as continuous
    var img = ctx.createImageData(nx, ny), px = img.data;
    for (var i = i0; i < i0 + nx; i++) for (var j = j0; j < j0 + ny; j++) {
        var v = grid[i] && grid[i][j] || 0, o = 4 * ((j1 - 1 - j) * nx + (i - i0));
        var a = v > 0 && colmax[i] > 0 ? FLOWMAP_FLOOR + (1 - FLOWMAP_FLOOR) * Math.pow(Math.min(1, v / colmax[i]), FLOWMAP_GAMMA) : FLOWMAP_FLOOR;
        px[o] = 201; px[o + 1] = 54; px[o + 2] = 47; px[o + 3] = Math.round(255 * a);
    }
    var off = document.createElement("canvas");
    off.width = nx; off.height = ny;
    off.getContext("2d").putImageData(img, 0, 0);
    ctx.save(); ctx.imageSmoothingEnabled = false;
    ctx.drawImage(off, L, T, pw, ph);
    ctx.restore();

    // Page colour, for the ring round the inferred and highlighted dots
    var bg = getComputedStyle(document.body).backgroundColor || "rgb(255,255,255)";

    // Peak flow temperature in each 1 K of outside temperature
    if (d.fit) {
        ctx.save(); ctx.beginPath(); ctx.rect(L, T, pw, ph); ctx.clip();
        ctx.fillStyle = ink;
        d.fit.peaks.forEach(function (p) { ctx.beginPath(); ctx.arc(X(p.x), Y(p.y), 2, 0, 2 * Math.PI); ctx.fill(); });
        ctx.restore();
    }
    // Inferred design flow temperature, unlabelled: hover for it
    if (inf) {
        ctx.fillStyle = ink; ctx.strokeStyle = bg; ctx.lineWidth = 2;
        ctx.beginPath(); ctx.arc(X(inf_x), Y(inf.value), 5, 0, 2 * Math.PI); ctx.fill(); ctx.stroke();
    }

    // Hover: the peak dot under the pointer, enlarged, with its 1 K outside band's
    // heat and how much of that is within ±0.5 K of the peak; or the inferred
    // design point, with its value. The drawn map is
    // kept so the highlight can be moved without drawing it all again
    var drawn = ctx.getImageData(0, 0, canvas.width, canvas.height), lit = null;
    function highlight(p) {
        if (p === lit) return;
        lit = p;
        ctx.putImageData(drawn, 0, 0);
        if (!p || p.inferred) return;
        ctx.fillStyle = ink; ctx.strokeStyle = bg; ctx.lineWidth = 2;
        ctx.beginPath(); ctx.arc(X(p.x), Y(p.y), 5, 0, 2 * Math.PI); ctx.fill(); ctx.stroke();
    }
    canvas.onmousemove = function (e) {
        var r = canvas.getBoundingClientRect(), px = e.clientX - r.left, py = e.clientY - r.top, hit = null;
        if (d.fit) d.fit.peaks.forEach(function (p) {
            var dist = Math.hypot(X(p.x) - px, Y(p.y) - py);
            if (dist <= 8 && (!hit || dist < hit.dist)) hit = { p: p, dist: dist };
        });
        if (inf) {
            var dist = Math.hypot(X(inf_x) - px, Y(inf.value) - py);
            if (dist <= 8 && (!hit || dist < hit.dist)) hit = { p: { inferred: true }, dist: dist };
        }
        canvas.style.cursor = hit ? "pointer" : "";
        highlight(hit ? hit.p : null);
        if (!hit) { tip.hidden = true; return; }
        if (hit.p.inferred) tip.innerHTML = 'Inferred design: <b>' + inf.value.toFixed(1) + '°C</b> at ' + inf_x + '°C outside';
        else {
            var p = hit.p, band = 0, near = 0, all = 0;
            for (var i = 0; i < d.nx; i++) for (var j = 0; j < d.ny; j++) {
                var v = d.space[i][j];
                all += v;
                if (Math.abs(d.x0 + (i + 0.5) * d.xs - p.x) >= 0.5) continue;
                band += v;
                if (Math.abs(d.y0 + (j + 0.5) * d.ys - p.y) <= 0.5) near += v;
            }
            tip.innerHTML = (p.x - 0.5) + '–' + (p.x + 0.5) + '°C outside: mostly <b>' + p.y.toFixed(1) + '°C</b> flow<br>' +
                Math.round(100 * near / band) + '% within ±0.5 K, ' + band.toFixed(0) + ' kWh (' + (100 * band / all).toFixed(0) + '% of the year)';
        }
        tip.hidden = false;
        var left = px + 12, tw = tip.offsetWidth;
        if (left + tw > W) left = px - tw - 12;
        tip.style.left = Math.max(0, left) + "px";
        tip.style.top = Math.max(0, py - tip.offsetHeight - 8) + "px";
    };
    canvas.onmouseleave = function () { tip.hidden = true; canvas.style.cursor = ""; highlight(null); };
}

// Pipe size buttons. One delegated handler, since the card's content is redrawn
(function () {
    var el = document.getElementById("measurements-block");
    if (!el) return;
    el.addEventListener("click", function (e) {
        var b = e.target.closest("[data-pipe]");
        if (!b) return;
        hp_ms_state.pipe = parseInt(b.getAttribute("data-pipe"));
        measurements_card_render();
    });
    var timer = null;
    window.addEventListener("resize", function () { clearTimeout(timer); timer = setTimeout(function () { flowmap_draw(); heatdemand_draw(); }, 100); });
})();
