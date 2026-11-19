// System card: the system form (system_meta) shown below the totals.
// Concept. Reads the global system_meta set by myheatpump.php; any field
// may be missing or empty, and a group with nothing to show is left out.

(function () {
    var m = window.system_meta;
    var el = document.getElementById("system-block");
    if (!m || !el) return;

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

    // Marker on a scale bar: position in % of the scale
    function pct(v, lo, hi) { return Math.max(0, Math.min(100, 100 * (v - lo) / (hi - lo))); }

    // -- Sizing: heat pump output against design and measured heat loss -------
    function sizing() {
        var hp = num(m.hp_output), max_out = num(m.hp_max_output), design = num(m.heat_loss);
        var meas = num(m.measured_heat_loss), meas_r = num(m.measured_heat_loss_range) || 0;
        if (!hp || (!design && !meas)) return "";

        var hi = Math.max(hp, max_out || 0, design || 0, meas ? meas + meas_r : 0) * 1.12;
        var dot = has(m.design_temp) ? fmt(m.design_temp, 1) + "°C" : "design temperature";

        var bar = '<div class="hp-sys-scale">';
        bar += '<div class="hp-sys-scale-fill is-heat" style="width:' + pct(hp, 0, hi) + '%"></div>';
        if (max_out && max_out > hp) {
            bar += '<div class="hp-sys-scale-fill is-heat-max" style="left:' + pct(hp, 0, hi) + '%;width:' + (pct(max_out, 0, hi) - pct(hp, 0, hi)) + '%"></div>';
        }
        if (meas) {
            bar += '<div class="hp-sys-scale-band" style="left:' + pct(meas - meas_r, 0, hi) + '%;width:' + (pct(meas + meas_r, 0, hi) - pct(meas - meas_r, 0, hi)) + '%"></div>';
            bar += '<div class="hp-sys-scale-dot" style="left:' + pct(meas, 0, hi) + '%"></div>';
            bar += '<div class="hp-sys-scale-tag is-below" style="left:' + pct(meas, 0, hi) + '%">Measured ' + fmt(meas, 1) + ' kW</div>';
        }
        if (design) {
            bar += '<div class="hp-sys-scale-tick" style="left:' + pct(design, 0, hi) + '%"></div>';
            bar += '<div class="hp-sys-scale-tag is-above" style="left:' + pct(design, 0, hi) + '%">Design ' + fmt(design, 1) + ' kW</div>';
        }
        bar += '</div>';

        var ratio = design ? hp / design : hp / meas;
        var note = '<b>' + fmt(ratio, 2) + '×</b> the ' + (design ? 'design' : 'measured') + ' heat loss at ' + esc(dot);
        if (design && meas) note += ' · <b>' + fmt(hp / meas, 2) + '×</b> measured';

        return '<div class="hp-sys-feature">' +
            '<div class="hp-sys-feature-head"><span class="app-live-label">Sizing</span>' +
            '<span class="hp-sys-feature-value text-hp-heat">' + fmt(hp, 1) + '<span class="hp-sys-unit">kW heat pump</span></span></div>' +
            bar + '<div class="hp-sys-feature-note">' + note + '</div></div>';
    }

    // -- Flow temperature: design against measured at the design temperature --
    function flow() {
        var design = num(m.flow_temp), meas = num(m.measured_design_flowT), meas_r = num(m.measured_design_flowT_range) || 0;
        if (!design && !meas) return "";

        var vals = [design, meas ? meas - meas_r : null, meas ? meas + meas_r : null].filter(function (v) { return v; });
        var lo = Math.floor(Math.min(25, Math.min.apply(null, vals) - 5) / 5) * 5;
        var hi = Math.ceil(Math.max(55, Math.max.apply(null, vals) + 5) / 5) * 5;
        var dot = has(m.design_temp) ? fmt(m.design_temp, 1) + "°C" : "design";

        var bar = '<div class="hp-sys-scale is-flow">';
        if (meas) {
            bar += '<div class="hp-sys-scale-band is-flow" style="left:' + pct(meas - meas_r, lo, hi) + '%;width:' + (pct(meas + meas_r, lo, hi) - pct(meas - meas_r, lo, hi)) + '%"></div>';
            bar += '<div class="hp-sys-scale-dot is-flow" style="left:' + pct(meas, lo, hi) + '%"></div>';
            bar += '<div class="hp-sys-scale-tag is-below" style="left:' + pct(meas, lo, hi) + '%">Measured ' + fmt(meas, 1) + '°C</div>';
        }
        if (design) {
            bar += '<div class="hp-sys-scale-tick" style="left:' + pct(design, lo, hi) + '%"></div>';
            bar += '<div class="hp-sys-scale-tag is-above" style="left:' + pct(design, lo, hi) + '%">Design ' + fmt(design, 1) + '°C</div>';
        }
        bar += '<div class="hp-sys-scale-end is-lo">' + lo + '°</div><div class="hp-sys-scale-end is-hi">' + hi + '°</div>';
        bar += '</div>';

        var note = "Flow temperature at " + esc(dot) + " outside";
        if (design && meas) {
            var d = meas - design;
            note = 'Running <b>' + fmt(Math.abs(d), 1) + '°C ' + (d < 0 ? 'cooler' : 'warmer') + '</b> than designed at ' + esc(dot);
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
    var groups = heatpump_group() + property_group() + heating_group() + water_group() + metering_group() + installer_group();
    if (!features && !groups && !has(m.notes)) return;

    // Caption with a Hide/Show button; the card opens expanded
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
    btn.addEventListener("click", function () {
        var open = body.hidden;
        body.hidden = !open;
        el.classList.toggle("is-collapsed", !open);
        btn.setAttribute("aria-expanded", open ? "true" : "false");
        btn.title = (open ? "Hide" : "Show") + " the system details";
        btn.querySelector("i").className = "bi bi-chevron-" + (open ? "up" : "down");
        btn.querySelector("span").textContent = open ? "Hide" : "Show";
    });
})();
