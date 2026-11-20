// System card: the system form (system_meta) shown below the totals.
// Concept. Reads the global system_meta set by myheatpump.php; any field
// may be missing or empty, and a group with nothing to show is left out.

// system_card_render() draws it again, keeping it open or minimised, after the
// heat demand tool saves a measured heat demand into system_meta.
function system_card_render() {
    var m = window.system_meta;
    var el = document.getElementById("system-block");
    if (!m || !el) return;
    var prev_body = document.getElementById("system-body");
    var start_open = prev_body ? !prev_body.hidden : false;

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
        });
    }
    function has(v) { return v !== null && v !== undefined && v !== "" && !(typeof v === "number" && isNaN(v)); }
    function num(v) { return has(v) && isFinite(v) && v * 1 !== 0 ? v * 1 : null; }
    function fmt(v, dp) { return (Math.round(v * Math.pow(10, dp)) / Math.pow(10, dp)).toString(); }
    function safe_url(u) { return has(u) && /^https?:\/\//i.test(u) ? u : null; }
    function link(u, text) {
        u = safe_url(u);
        return u ? '<a href="' + esc(u) + '" target="_blank" rel="noopener">' + text + '</a>' : text;
    }

    // Label and value pair; nothing when the value is empty
    function fact(label, value, unit) {
        if (!has(value)) return "";
        return '<div class="hp-sys-fact"><div class="hp-sys-fact-label">' + esc(label) + '</div>' +
            '<div class="hp-sys-fact-value">' + esc(value) +
            (unit ? '<span class="hp-sys-unit">' + esc(unit) + '</span>' : '') + '</div></div>';
    }
    // On or off chip, for the tick boxes of the form
    function chip(label, on, icon) {
        return '<span class="hp-sys-chip' + (on ? ' is-on' : '') + '">' +
            '<i class="bi bi-' + (icon || (on ? 'check-lg' : 'dash')) + '"></i>' + esc(label) + '</span>';
    }
    function group(icon, title, body, extra_class) {
        if (!body) return "";
        return '<div class="hp-sys-group' + (extra_class ? ' ' + extra_class : '') + '">' +
            '<div class="hp-sys-group-head">' + icon + '<span>' + esc(title) + '</span></div>' + body + '</div>';
    }

    // Measured design parameters (system_measurements), by name. Null when not measured
    var ms = window.system_measurements || {};
    function mv(name) { return ms[name] && ms[name].value !== null ? ms[name] : null; }

    // Link that opens the power view for a period, unix s. data: measured values
    // to highlight in the stats, as data- attributes ({ flowt, outsidet } or { heatkw })
    function period(start, end, text, title, data) {
        if (!start || !end) return text;
        var attrs = "";
        for (var k in (data || {})) if (has(data[k])) attrs += ' data-' + k + '="' + esc(data[k]) + '"';
        return '<a href="#" class="hp-sys-period" data-start="' + (start * 1000) + '" data-end="' + (end * 1000) + '"' + attrs +
            (title ? ' title="' + esc(title) + '"' : '') + '>' + text + '</a>';
    }
    function chip_date(t) { return esc(date(t).replace(/ \d{4}$/, "")); }
    function date(t, with_time) {
        var opts = { day: "numeric", month: "short", year: "numeric" };
        if (with_time) { opts.hour = "2-digit"; opts.minute = "2-digit"; }
        return new Date(t * 1000).toLocaleString("en-GB", opts);
    }
    // Label and value pair where the value is already HTML
    function fact_html(label, html) {
        return '<div class="hp-sys-fact"><div class="hp-sys-fact-label">' + esc(label) + '</div>' +
            '<div class="hp-sys-fact-value">' + html + '</div></div>';
    }

    // Marker on a scale bar: position in % of the scale
    function pct(v, lo, hi) { return Math.max(0, Math.min(100, 100 * (v - lo) / (hi - lo))); }

    // -- Sizing: heat pump output against design and measured heat loss -------
    function sizing() {
        var hp = num(m.hp_output), max_out = num(m.hp_max_output), design = num(m.heat_loss);
        // Estimated heat demand at the form's design temperature, from the nightly heat loss fit
        var auto = mv("heat_loss_design");
        var meas = auto ? auto.value : null, meas_r = auto && auto.upper !== null ? auto.upper - auto.value : 0;
        // Observed on the coldest days of the last year (the flow temperature card's days)
        var cold = mv("heat_coldest_days"), peak_4h = mv("heat_peak_4h");
        if (!hp || (!design && !meas)) return "";

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

        return '<div class="hp-sys-feature">' +
            '<div class="hp-sys-feature-head"><span class="app-live-label">Sizing</span>' +
            '<span class="hp-sys-feature-value text-hp-heat">' + fmt(hp, 1) + '<span class="hp-sys-unit">kW heat pump</span></span></div>' +
            bar + '<div class="hp-sys-feature-note">' + note + '</div></div>';
    }

    // -- Flow temperature: design against measured on the coldest days --------
    function flow() {
        // Space heating flow temperature on the coldest days of the last year, worked out nightly
        var cold = mv("flowT_coldest_days"), peak = mv("flowT_peak_4h");
        var design = num(m.flow_temp);
        var meas = cold ? cold.value : null;
        if (!design && !meas) return "";
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
        var head = meas || design;
        return '<div class="hp-sys-feature">' +
            '<div class="hp-sys-feature-head"><span class="app-live-label">Flow temperature</span>' +
            '<span class="hp-sys-feature-value text-hp-flow">' + fmt(head, 1) + '<span class="hp-sys-unit">°C ' + (meas ? 'measured' : 'design') + '</span></span></div>' +
            bar + '<div class="hp-sys-feature-note">' + note + '</div></div>';
    }

    // -- Groups ---------------------------------------------------------------
    var icon_house = '<span class="hp-sys-icon"><i class="bi bi-house"></i></span>';
    var icon_rad = '<span class="hp-sys-icon"><span class="svg-icon-radiator"></span></span>';
    var icon_dhw = '<span class="hp-sys-icon"><span class="svg-icon-shower"></span></span>';
    var icon_meter = '<span class="hp-sys-icon"><i class="bi bi-speedometer2"></i></span>';
    var icon_installer = '<span class="hp-sys-icon"><i class="bi bi-tools"></i></span>';
    var icon_hp = '<span class="hp-sys-icon"><span class="svg-icon-hpmon"></span></span>';

    // Measured design parameters, with links to the periods they come from
    function measured_group() {
        var b = "";
        var dot = mv("design_outsideT_996"), dot990 = mv("design_outsideT_990");
        if (dot) {
            var form = has(m.design_temp) ? " (form " + fmt(m.design_temp, 1) + "°C)" : "";
            b += fact_html("Design outside 99.6%", esc(fmt(dot.value, 1)) + '<span class="hp-sys-unit">°C' + esc(form) + '</span>');
            if (dot990) b += fact_html("Design outside 99.0%", esc(fmt(dot990.value, 1)) + '<span class="hp-sys-unit">°C</span>');
            var ch = dot.details && dot.details.coldest_hour;
            if (ch) b += fact_html("Coldest hour", period(ch.start, ch.end, fmt(ch.outsideT, 1), date(ch.start, true)) + '<span class="hp-sys-unit">°C</span>');
        }

        var hd = mv("heat_loss_design"), hdm = mv("heat_loss_design_measured"), htc = mv("heat_loss_htc"), base = mv("heat_loss_base_dT");
        if (hd) {
            var at = hd.details && hd.details.design_outsideT !== undefined ? " at " + fmt(hd.details.design_outsideT, 1) + "°C" : "";
            var range = hd.upper !== null ? " ±" + fmt(hd.upper - hd.value, 2) : "";
            b += fact_html("Heat demand" + at, esc(fmt(hd.value, 2)) + '<span class="hp-sys-unit">kW' + range + '</span>');
        }
        if (hdm && (!hd || hd.details.design_outsideT_source != "measured")) {
            b += fact_html("At measured 99.6%", esc(fmt(hdm.value, 2)) + '<span class="hp-sys-unit">kW</span>');
        }
        if (htc) b += fact_html("Heat transfer coefficient", esc(fmt(htc.value, 0)) + '<span class="hp-sys-unit">W/K</span>');
        if (base) b += fact_html("Balance point", esc(fmt(base.value, 1)) + '<span class="hp-sys-unit">K</span>');

        if (!b) return "";

        var updated = 0;
        for (var k in ms) if (ms[k].updated > updated) updated = ms[k].updated;
        if (updated) b += '<div class="hp-sys-fact-label">Worked out from the monitoring data, ' + esc(date(updated)) + '</div>';
        return group('<span class="hp-sys-icon"><i class="bi bi-graph-up"></i></span>', "Measured", b);
    }

    function heatpump_group() {
        var name = [m.hp_manufacturer, m.hp_model].filter(has).join(" ");
        var b = "";
        b += fact("Make & model", name);
        b += fact("Type", m.hp_type);
        b += fact("Refrigerant", m.refrigerant);
        if (num(m.hp_max_output)) b += fact("Datasheet max", fmt(m.hp_max_output, 1), "kW");
        if (num(m.hp_max_output_test)) b += fact("Tested max", fmt(m.hp_max_output_test, 1), "kW");
        var chips = "";
        if (m.hp_hybrid) chips += chip("Hybrid", true, "fire");
        if (m.uses_backup_heater) chips += chip("Backup heater", true, "lightning-charge");
        if (chips) b += '<div class="hp-sys-chips">' + chips + '</div>';
        return group(icon_hp, "Heat pump", b);
    }

    function property_group() {
        var b = "";
        b += fact("Property", m.property);
        b += fact("Built", m.age);
        if (num(m.floor_area)) b += fact("Floor area", fmt(m.floor_area, 0), "m²");
        if (num(m.heat_loss) && num(m.floor_area)) b += fact("Design heat loss", fmt(1000 * m.heat_loss / m.floor_area, 0), "W/m²");
        b += fact("Insulation", m.insulation);
        return group(icon_house, "Property", b);
    }

    function heating_group() {
        var b = "";
        var emitters = [["New radiators", m.new_radiators], ["Existing radiators", m.old_radiators], ["Underfloor", m.UFH], ["Fan coils", m.fan_coil_radiators]];
        if (emitters.some(function (e) { return e[1]; })) {
            b += '<div class="hp-sys-chips">' + emitters.map(function (e) { return chip(e[0], e[1]); }).join("") + '</div>';
        }
        if (num(m.zone_number)) b += fact("Zones", m.zone_number);
        b += fact("Hydraulic separation", m.hydraulic_separation);
        if (m.volumiser) b += fact("Volumiser", "Yes");
        if (m.freeze && m.freeze !== "Not applicable") b += fact("Freeze protection", m.freeze);
        return group(icon_rad, "Space heating", b);
    }

    function water_group() {
        if (!has(m.dhw_method) || m.dhw_method === "None") return group(icon_dhw, "Hot water", has(m.dhw_method) ? fact("Storage", "None") : "");
        var b = "";
        b += fact("Storage", m.dhw_method);
        b += fact("Cylinder", m.dhw_make_model);
        if (num(m.cylinder_volume)) b += fact("Volume", fmt(m.cylinder_volume, 0), "L");
        if (num(m.dhw_target_temperature)) b += fact("Target", fmt(m.dhw_target_temperature, 1), "°C");
        if (has(m.legionella_frequency) && m.legionella_frequency !== "No cylinder") {
            b += fact("Legionella cycle", m.legionella_frequency + (m.legionella_immersion ? " (immersion)" : ""));
        }
        return group(icon_dhw, "Hot water", b);
    }

    function metering_group() {
        var b = "";
        var badges = "";
        if (m.mid_metering) badges += '<span class="hp-sys-badge is-good"><i class="bi bi-patch-check-fill"></i>MID metered</span>';
        if (has(m.metering_boundary_code) && m.metering_boundary_code !== 0) {
            badges += '<span class="hp-sys-badge" title="SEPEMO system boundary">Boundary H' + esc(String(m.metering_boundary_code).replace(/^H/i, "")) + '</span>';
        }
        if (badges) b += '<div class="hp-sys-chips">' + badges + '</div>';
        b += fact("Electricity", m.electric_meter);
        b += fact("Heat", m.heat_meter);
        var inc = [
            ["Primary pump", m.metering_inc_central_heating_pumps],
            ["Secondary pumps", m.metering_inc_secondary_heating_pumps],
            ["Controls", m.metering_inc_controls],
            ["Backup heater", m.metering_inc_boost],
            ["Immersion", m.metering_inc_immersion]
        ];
        if (m.hp_type === "Ground Source") inc.push(["Brine pumps", m.metering_inc_brine_pumps]);
        if (inc.some(function (e) { return has(e[1]); })) {
            b += '<div class="hp-sys-fact-label">Electricity meter includes</div>';
            b += '<div class="hp-sys-chips">' + inc.map(function (e) { return chip(e[0], e[1]); }).join("") + '</div>';
        }
        if (m.data_flag) {
            b += '<div class="hp-sys-flag"><i class="bi bi-exclamation-triangle-fill"></i>' + (has(m.data_flag_note) ? esc(m.data_flag_note) : "Data issue flagged") + '</div>';
        }
        return group(icon_meter, "Metering", b);
    }

    function installer_group() {
        var b = "";
        if (has(m.installer_name)) {
            var logo = has(m.installer_logo) ? '<img class="hp-sys-logo" src="' + esc(path + "theme/img/installers/" + m.installer_logo) + '" alt="">' : "";
            b += '<div class="hp-sys-installer">' + logo + '<div class="hp-sys-installer-name">' + link(m.installer_url, esc(m.installer_name)) + '</div></div>';
        }
        var training = [["Heat Geek", m.heatgeek], ["Ultimate Renewables", m.ultimaterenewables], ["Heating Academy", m.heatingacademy], ["Guild", m.guild]]
            .filter(function (t) { return t[1]; });
        if (training.length) {
            b += '<div class="hp-sys-chips">' + training.map(function (t) { return '<span class="hp-sys-badge"><i class="bi bi-mortarboard"></i>' + esc(t[0]) + '</span>'; }).join("") + '</div>';
        }
        if (num(m.installation_Cost)) b += fact("Installation cost", "£" + Math.round(m.installation_Cost).toLocaleString("en-GB"), "before grant");
        var learn = "";
        if (safe_url(m.youtube)) learn += link(m.youtube, '<i class="bi bi-youtube"></i>YouTube');
        if (safe_url(m.betateach)) learn += link(m.betateach, '<i class="bi bi-mic"></i>BetaTalk');
        if (learn) b += '<div class="hp-sys-links">' + learn + '</div>';
        return group(icon_installer, "Installer", b);
    }

    function notes() {
        if (!has(m.notes)) return "";
        // Escaped first, then web addresses made into links
        var text = esc(m.notes).replace(/https?:\/\/[^\s<]+/g, function (u) {
            return '<a href="' + u + '" target="_blank" rel="noopener">' + u + '</a>';
        });
        return '<blockquote class="hp-sys-notes"><i class="bi bi-quote"></i>' + text + '</blockquote>';
    }

    var features = sizing() + flow();
    var groups = heatpump_group() + property_group() + heating_group() + measured_group() + water_group() + metering_group() + installer_group();
    if (!features && !groups && !has(m.notes)) return;

    // Caption with a Hide/Show button; set_open below sets the starting state
    var html = '<div class="app-card-caption hp-sys-caption"><span class="app-section-label">System</span>' +
        (has(m.location) ? '<span class="app-caption-note"><i class="bi bi-geo-alt"></i> ' + esc(m.location) + '</span>' : '') +
        '<div class="btn-group app-segmented ms-auto"><button class="btn" id="system-toggle" aria-expanded="true" aria-controls="system-body" title="Hide the system details">' +
        '<i class="bi bi-chevron-up"></i> <span>Hide</span></button></div></div>';
    html += '<div id="system-body">';
    if (features) html += '<div class="hp-sys-features">' + features + '</div>';
    if (groups) html += '<div class="hp-sys-groups">' + groups + '</div>';
    html += notes();
    html += '</div>';

    el.innerHTML = html;
    el.style.display = "";

    // A missing installer logo is removed rather than shown broken
    var logo_img = el.querySelector(".hp-sys-logo");
    if (logo_img) logo_img.addEventListener("error", function () { logo_img.remove(); });

    var body = document.getElementById("system-body");
    var btn = document.getElementById("system-toggle");
    function set_open(open) {
        body.hidden = !open;
        el.classList.toggle("is-collapsed", !open);
        btn.setAttribute("aria-expanded", open ? "true" : "false");
        btn.title = (open ? "Hide" : "Show") + " the system details";
        btn.querySelector("i").className = "bi bi-chevron-" + (open ? "up" : "down");
        btn.querySelector("span").textContent = open ? "Hide" : "Show";
    }
    btn.addEventListener("click", function () { set_open(body.hidden); });
    // Minimised on load: the card opens on Show
    set_open(start_open);
}
system_card_render();

// Measured periods open the power view for that day or window. One delegated
// handler, since the card's content is redrawn.
(function () {
    var el = document.getElementById("system-block");
    if (!el) return;
    el.addEventListener("click", function (e) {
        var a = e.target.closest(".hp-sys-period");
        if (!a || typeof hp_open_power != "function") return;
        e.preventDefault();
        var start = parseInt(a.getAttribute("data-start")), end = parseInt(a.getAttribute("data-end"));
        // Days behind the measured flow temperature open with it highlighted in the stats
        if (a.hasAttribute("data-flowt") && typeof hp_open_measured_flowT == "function") hp_open_measured_flowT(start, end, parseFloat(a.getAttribute("data-flowt")),
            a.hasAttribute("data-outsidet") ? parseFloat(a.getAttribute("data-outsidet")) : null);
        else if (a.hasAttribute("data-heatkw") && typeof hp_open_measured_heat == "function") hp_open_measured_heat(start, end, parseFloat(a.getAttribute("data-heatkw")));
        else hp_open_power(start, end);
        window.scrollTo(0, 0);
    });
})();
