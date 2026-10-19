// Simulated twins of the fleet's Arotherm+ systems.
//
// For each system in targets.json (twin_targets.py), runs the dynamic heat
// pump simulator with the Vaillant Arotherm+ datasheet fit (generic_v2) and
// the system's own badge, room temperature, DHW target, cylinder, flow rate
// and climate (Llanberis weather shifted to its mean outside temperature),
// and calibrates three unknowns until the twin matches the system's year:
//
//   heat loss          -> annual space heat
//   DHW daily volume   -> annual water heat
//   design flow temp   -> heat-weighted flow temperature
//
// Then records the twin's annual stats and metrics (H_fixed, H*, P = H*/H_fixed
// with load ratios against the badge, as the fleet scan computed them).
//
// Usage:
//   node twins/twins.js --tools <tools repo>/www/tools/dynamic_heatpump \
//       [--targets twins/targets.json] [--out twins/twins.csv] [--workers N]
//       [--dhw-modulation 100]

const os = require('os');
const fs = require('fs');
const path = require('path');
const { Worker, isMainThread, parentPort, workerData } = require('worker_threads');

const args = process.argv.slice(2);
function arg_value(name, fallback) {
    const i = args.indexOf(name);
    return (i >= 0 && i + 1 < args.length) ? args[i + 1] : fallback;
}

const MAX_ITERATIONS = 10;
const TOL = { space: 0.02, water: 0.04, flowT: 0.2 };   // relative, relative, K
// DHW reheat output limit, % of capacity (the app default is 50, an eco mode)
const DHW_MODULATION = parseFloat(arg_value('--dhw-modulation', '100'));

if (isMainThread) {
    const tools = arg_value('--tools', null);
    if (!tools) {
        console.error('--tools <path to tools repo www/tools/dynamic_heatpump> is required');
        process.exit(1);
    }
    const targets = JSON.parse(fs.readFileSync(arg_value('--targets', path.join(__dirname, 'targets.json')))).targets;
    const out_file = path.resolve(arg_value('--out', path.join(__dirname, 'twins.csv')));
    const n_workers = Math.min(targets.length,
        parseInt(arg_value('--workers', String(Math.max(1, os.cpus().length - 2)))));
    console.log('calibrating ' + targets.length + ' twins on ' + n_workers + ' workers');

    const rows = [];
    let next = 0;
    const t0 = Date.now();
    for (let w = 0; w < n_workers; w++) {
        const worker = new Worker(__filename, { workerData: { tools: path.resolve(tools) } });
        const feed = function () {
            if (next < targets.length) worker.postMessage(targets[next++]);
            else worker.terminate();
        };
        worker.on('message', function (row) {
            rows.push(row);
            if (rows.length % 10 === 0 || rows.length === targets.length) {
                console.log('  ' + rows.length + '/' + targets.length + '  (' +
                    ((Date.now() - t0) / 60000).toFixed(1) + ' min)');
            }
            feed();
        });
        worker.on('error', function (e) { console.error('worker error:', e); process.exit(1); });
        feed();
    }

    process.on('exit', function () {
        if (!rows.length) return;
        rows.sort((a, b) => a.id - b.id);
        const cols = Object.keys(rows[0]);
        const lines = [cols.join(',')].concat(rows.map(r => cols.map(c => r[c]).join(',')));
        fs.writeFileSync(out_file, lines.join('\n') + '\n');
        const conv = rows.filter(r => r.converged).length;
        console.log('wrote ' + out_file + ' (' + rows.length + ' twins, ' + conv + ' converged)');
    });

} else {
    console.log = function () {};
    const harness = require(path.join(workerData.tools, 'harness.js'));

    function config_for(t, p) {
        const nominal = 1000 * t.badge_kw;
        return {
            outsideT_offset: t.outsideT_offset,
            building: { heat_loss: p.heat_loss },
            heatpump: {
                cop_model: 'generic_v2',
                nominal_capacity: nominal,
                eta_scale: 1.0,
                // Arotherm+ modulation range from the fit's capacity model at A7/W35
                capacity: 1000 * outputRange(t.badge_kw, 35, 7).maxKW,
                minimum_modulation: 100 * VAILLANT_MIN_RPS / VAILLANT_MAX_RPS,
                design_flowT: p.design_flowT,
                design_roomT: t.room_temp,
                flow_rate: t.flow_rate
            },
            control: { mode: 0 },
            schedule: [{ start: '00:00', set_point: t.room_temp, price: 24.67 }],
            dhw: { daily_volume: p.daily_volume, cylinder_volume: t.cylinder_volume },
            dhw_schedule: [
                { start: '04:00', set_point: t.dhw_target, duration: 10800, modulation: DHW_MODULATION },
                { start: '13:00', set_point: t.dhw_target, duration: 7200, modulation: DHW_MODULATION }
            ]
        };
    }

    // One secant step per parameter (they're only weakly coupled), with a
    // proportional or fixed-gain first step and clamps
    function step(hist, target, first, lo, hi) {
        const n = hist.length;
        let x;
        if (n >= 2 && hist[n - 1].y !== hist[n - 2].y) {
            const a = hist[n - 2], b = hist[n - 1];
            x = b.x + (target - b.y) * (b.x - a.x) / (b.y - a.y);
        } else {
            x = first(hist[n - 1]);
        }
        return Math.min(hi, Math.max(lo, x));
    }

    async function calibrate(t) {
        let p = {
            heat_loss: Math.max(1000, 3400 * t.space_heat_kwh / 8000),
            daily_volume: Math.max(20, 120 * t.water_heat_kwh / 1500),
            design_flowT: t.weighted_flowT + 8
        };
        const hist = { space: [], water: [], flowT: [] };
        let app, err, i;
        for (i = 1; i <= MAX_ITERATIONS; i++) {
            app = await harness.run_annual(config_for(t, p));
            const h = app.hpm_model;
            hist.space.push({ x: p.heat_loss, y: h.space_heat_kwh });
            hist.water.push({ x: p.daily_volume, y: h.water_heat_kwh });
            hist.flowT.push({ x: p.design_flowT, y: h.weighted_flowT });
            err = {
                space: h.space_heat_kwh / t.space_heat_kwh - 1,
                water: t.water_heat_kwh > 0 ? h.water_heat_kwh / t.water_heat_kwh - 1 : 0,
                flowT: h.weighted_flowT - t.weighted_flowT
            };
            if (Math.abs(err.space) < TOL.space && Math.abs(err.water) < TOL.water &&
                Math.abs(err.flowT) < TOL.flowT) break;
            if (i === MAX_ITERATIONS) break;
            p = {
                heat_loss: step(hist.space, t.space_heat_kwh, b => b.x * t.space_heat_kwh / b.y, 300, 25000),
                daily_volume: step(hist.water, t.water_heat_kwh, b => b.x * t.water_heat_kwh / Math.max(b.y, 1), 5, 800),
                design_flowT: step(hist.flowT, t.weighted_flowT, b => b.x + 1.5 * (t.weighted_flowT - b.y), 20, 70)
            };
        }
        return { app: app, p: p, err: err, iterations: Math.min(i, MAX_ITERATIONS) };
    }

    parentPort.on('message', async function (t) {
        const c = await calibrate(t);
        const app = c.app, h = app.hpm_model;
        const m = harness.annual_metrics(sim_series, 1000 * t.badge_kw, app.days);
        const row = {
            id: +t.id,
            badge_kw: t.badge_kw,
            has_split: t.has_split ? 1 : 0,
            room_temp_measured: t.room_temp_measured ? 1 : 0,
            iterations: c.iterations,
            converged: (Math.abs(c.err.space) < TOL.space && Math.abs(c.err.water) < TOL.water &&
                Math.abs(c.err.flowT) < TOL.flowT) ? 1 : 0,
            err_space: c.err.space,
            err_water: c.err.water,
            err_flowT: c.err.flowT,
            heat_loss: c.p.heat_loss,
            daily_volume: c.p.daily_volume,
            design_flowT: c.p.design_flowT,
            outsideT_offset: t.outsideT_offset,
            room_temp: t.room_temp,
            flow_rate: app.heatpump.flow_rate,
            spf: h.combined_cop,
            space_heat_kwh: h.space_heat_kwh,
            water_heat_kwh: h.water_heat_kwh,
            weighted_flowT: h.weighted_flowT,
            weighted_flowT_minus_outsideT: h.weighted_flowT_minus_outsideT,
            weighted_flowT_minus_returnT: h.weighted_flowT_minus_returnT,
            mean_room_temp: app.results.mean_room_temp,
            degree_hours_below_setpoint: app.stats.degree_hours_below_setpoint,
            H_fixed: m.H_fixed,
            H_star: m.H_star,
            P: m.H_star / m.H_fixed,
            r_bar: m.r_bar,
            heat_frac_below_40pct: m.heat_frac_below_40pct,
            starts_per_day: m.starts_per_day,
            runtime_frac: m.runtime_frac
        };
        for (const k in row) {
            if (typeof row[k] === 'number' && !Number.isInteger(row[k])) row[k] = +row[k].toPrecision(6);
        }
        parentPort.postMessage(row);
    });
}
