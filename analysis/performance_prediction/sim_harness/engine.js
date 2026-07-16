// Node harness around the dynamic heat pump simulator (browser tool).
// Loads the unmodified tool source in a vm sandbox with Vue/jQuery/DOM stubs,
// injects the annual weather dataset directly, and exposes runScenario().

const fs = require("fs");
const path = require("path");
const vm = require("vm");

const DIR = path.join(__dirname, "..", "dynamic_heatpump");

function makeContext() {
    // Chainable jQuery stub: $(x).width().height(), $(x).bind(), $.plot() ...
    const jq = () => new Proxy(function () {}, {
        get: (t, p) => (p === Symbol.toPrimitive ? () => 0 : jq()),
        apply: () => jq(),
    });
    const $ = jq();

    // Minimal Vue stub: data + bound methods on a plain object
    function Vue(opts) {
        const o = Object.assign({}, opts.data);
        for (const k in opts.methods) o[k] = opts.methods[k].bind(o);
        return o;
    }

    const sandbox = {
        Vue,
        $,
        jQuery: $,
        window: {},
        document: {},
        navigator: {},
        alert: () => {},
        prompt: () => null,
        fetch: () => new Promise(() => {}),           // never resolves; not used
        setTimeout: (fn) => fn(),                     // run app.simulate() synchronously
        console: { log: () => {}, error: () => {}, warn: () => {} },
        performance: { now: () => Date.now() },
        Date, Math, JSON, Object, Array, parseFloat, parseInt, isFinite, isNaN, NaN, Infinity,
        view_calc_interval: function () { sandbox.view && (sandbox.view.interval = 3600); },
    };
    const ctx = vm.createContext(sandbox);

    for (const f of ["vaillant.js", "ecodan.js", "timeseries.js", "dynamic_heatpump.js"]) {
        vm.runInContext(fs.readFileSync(path.join(DIR, f), "utf8"), ctx, { filename: f });
    }

    // Neutralise plotting for scenario runs (it needs real flot + view state)
    vm.runInContext("plot = function(){}; show_spinner = function(){}; hide_spinner = function(){};", ctx);

    // Inject annual weather dataset (bypasses fetch-based load_csv_data)
    const csv = fs.readFileSync(path.join(DIR, "llanberis2024.csv"), "utf8");
    const outsideT = [], solar = [], agile = [];
    for (const line of csv.split("\n")) {
        const c = line.trim().split(",");
        if (c.length >= 5) {
            outsideT.push(parseFloat(c[1]));
            solar.push(parseFloat(c[3]));
            agile.push(parseFloat(c[4]));
        }
    }
    vm.runInContext(
        `annual_dataset_outsideT = ${JSON.stringify(outsideT)};
         annual_dataset_solar = ${JSON.stringify(solar)};
         annual_dataset_agile = ${JSON.stringify(agile)};
         annual_dataset_loaded = true;`, ctx);

    return ctx;
}

function deepAssign(target, src) {
    for (const k in src) {
        if (src[k] !== null && typeof src[k] === "object" && !Array.isArray(src[k])
            && target[k] && typeof target[k] === "object") {
            deepAssign(target[k], src[k]);
        } else {
            target[k] = src[k];
        }
    }
}

// cfg: { building: {...}, heatpump: {...}, control: {...}, dhw: {...},
//        schedule: [...], dhw_schedule: [...] }
function runScenario(ctx, cfg) {
    const app = vm.runInContext("app", ctx);

    // Reset persistent simulation state so scenarios are independent
    vm.runInContext(`
        ITerm = 0; error = 0; ITerm_outer = 0; error_outer = 0; degree_minutes = 0;
        cyl_T = []; dhw_primaryT = 35;
        app.building.fabric[0].T = 16; app.building.fabric[1].T = 17; app.building.fabric[2].T = 18;
        update_fabric_starting_temperatures();
        MWT = room; flow_temperature = room; return_temperature = room;
        view.start = 1; view.end = 2;
    `, ctx);

    for (const section of ["building", "heatpump", "control", "dhw", "external"]) {
        if (cfg[section]) deepAssign(app[section], cfg[section]);
    }
    if (cfg.schedule) app.schedule = cfg.schedule;
    if (cfg.dhw_schedule) app.dhw_schedule = cfg.dhw_schedule;

    app.days = 365;
    app.days_pre_sim = cfg.days_pre_sim !== undefined ? cfg.days_pre_sim : 5;
    app.external.use_csv = true;

    app.simulate();   // synchronous via setTimeout stub

    // Candidate annualised metrics computed from the sim's 30s timeseries.
    // Deliberately electricity-free (usable as honest COP predictors from
    // real monitoring data: flowT, outsideT, heat power + rated capacity).
    const metrics = vm.runInContext(`(function () {
        const cap = app.heatpump.capacity;
        const dt_h = 30 / 3600;
        let sh = 0, shc = 0, shc_var = 0, sdt = 0, sdt2 = 0, sflow = 0;
        let below40 = 0, on_steps = 0, starts = 0, prev_on = false;
        const n = heat_data.length;
        for (let i = 0; i < n; i++) {
            const h = heat_data[i];
            const on = h > 0;
            if (on && !prev_on) starts++;
            prev_on = on;
            if (!on) continue;
            on_steps++;
            const fT = flowT_data[i], oT = outsideT_data[i];
            const dT = fT - oT;
            sh += h; sdt += h * dT; sdt2 += h * dT * dT; sflow += h * fT;
            // fixed-offset ideal carnot (heatpumpmonitor convention)
            const den = (fT + 2) - (oT - 6);
            if (den > 0) shc += h / ((fT + 2 + 273.15) / den);
            // variable-offset carnot (needs rated capacity: metadata)
            const ratio = h / cap;
            const denv = (fT + 3 * ratio) - (oT - 8 * ratio);
            if (denv > 0) shc_var += h / ((fT + 3 * ratio + 273.15) / denv);
            if (h < 0.4 * cap) below40 += h;
        }
        const dT_w = sdt / sh;
        return {
            H_carnot: sh / shc,
            H_carnot_var: sh / shc_var,
            dT_w: dT_w,
            dT_sd_w: Math.sqrt(Math.max(0, sdt2 / sh - dT_w * dT_w)),
            flowT_w: sflow / sh,
            runtime_frac: on_steps / n,
            starts_per_day: starts / 365,
            heat_frac_below_40pct: below40 / sh,
            mean_load_factor: sh / on_steps / cap,
        };
    })()`, ctx);

    const r = app.results, s = app.stats;
    return {
        elec_kwh: r.elec_kwh,
        heat_kwh: r.heat_kwh,
        spf: r.heat_kwh / r.elec_kwh,
        dhw_heat_kwh: r.dhw_heat_kwh,
        dhw_elec_kwh: r.dhw_elec_kwh,
        mean_room_temp: r.mean_room_temp,
        weighted_flowT: s.flowT_weighted,
        weighted_outsideT: s.outsideT_weighted,
        weighted_flowT_minus_outsideT: s.flowT_minus_outsideT_weighted,
        wa_prc_carnot: s.wa_prc_carnot,
        degree_hours_below_setpoint: s.degree_hours_below_setpoint,
        degree_hours_above_setpoint: s.degree_hours_above_setpoint,
        ...metrics,
        sim_time_ms: r.sim_time_ms,
    };
}

module.exports = { makeContext, runScenario };
