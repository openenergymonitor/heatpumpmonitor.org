// Monte Carlo sweep over system designs through the dynamic heat pump simulator.
// Fixed: carnot_variable COP model at 47% carnot, single PI control (AUTO_ADAPT),
// full-year Llanberis 2024 weather. Varied: heat loss, capacity ratio, emitter
// sizing, gains, DHW demand, water volume, flow rate, setpoints, standby/pumps.
//
// Usage: node run_scenarios.js [N] [workers]   (defaults: 400, cpus-2)

const os = require("os");
const fs = require("fs");
const path = require("path");
const { Worker, isMainThread, parentPort, workerData } = require("worker_threads");

const N = parseInt(process.argv[2] || "400");
const OUT = path.join(__dirname, "..", "sim_results.csv");

// Deterministic RNG (Date.now/Math.random-free scenarios are reproducible)
function mulberry32(a) {
    return function () {
        a |= 0; a = (a + 0x6D2B79F5) | 0;
        let t = Math.imul(a ^ (a >>> 15), 1 | a);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

function makeScenarios(n) {
    const rng = mulberry32(20260715);
    const U = (a, b) => a + (b - a) * rng();
    const scenarios = [];
    for (let i = 0; i < n; i++) {
        const heat_loss = U(2000, 9000);                     // W at ~23K inside-outside
        const capacity = Math.min(18000, Math.max(3500, heat_loss * U(1.0, 2.2)));
        const radiator_ratio = U(1.2, 3.2);                  // emitter rated (DT50) / heat loss
        const system_DT = U(4, 8);
        const setpointT = U(18.5, 21.5);
        const setback = rng() < 0.5;
        const schedule = setback
            ? [{ start: "00:00", set_point: setpointT - 2, price: 24.67 },
               { start: "06:00", set_point: setpointT, price: 24.67 },
               { start: "22:00", set_point: setpointT - 2, price: 24.67 }]
            : [{ start: "00:00", set_point: setpointT, price: 24.67 }];
        scenarios.push({
            i,
            params: {
                heat_loss, capacity, radiator_ratio, system_DT, setpointT,
                setback: setback ? 1 : 0,
            },
            cfg: {
                building: {
                    heat_loss,
                    solar_gains_scale: U(0, 8),
                    lac_gains: U(100, 350),
                    metabolic_gains: U(50, 150),
                },
                heatpump: {
                    cop_model: "carnot_variable",
                    prc_carnot: 47,
                    capacity,
                    radiatorRatedOutput: heat_loss * radiator_ratio,
                    radiatorRatedDT: 50,
                    system_water_volume: U(60, 250),
                    flow_rate: (capacity * 60) / (4187 * system_DT),   // sized for system_DT at full output
                    system_DT,
                    minimum_modulation: U(20, 45),
                    standby: U(5, 20),
                    pumps: U(10, 30),
                    ramp_rate: 1,
                },
                control: { mode: 0 },                                   // single PI (AUTO_ADAPT)
                dhw: {
                    daily_volume: U(60, 350),
                    cylinder_volume: U(120, 300),
                },
                dhw_schedule: [
                    { start: "04:00", set_point: U(42, 50), duration: 10800 },
                    { start: "13:00", set_point: U(42, 50), duration: 7200 },
                ],
                schedule,
            },
        });
    }
    return scenarios;
}

if (isMainThread) {
    const scenarios = makeScenarios(N);
    const nWorkers = parseInt(process.argv[3] || String(Math.max(1, os.cpus().length - 2)));
    console.log(`running ${N} scenarios on ${nWorkers} workers`);

    const rows = new Array(N);
    let next = 0, done = 0;
    const t0 = Date.now();

    const workers = [];
    for (let w = 0; w < nWorkers; w++) {
        const worker = new Worker(__filename, { workerData: true, argv: process.argv.slice(2) });
        workers.push(worker);
        const feed = () => {
            if (next < N) worker.postMessage(scenarios[next++]);
            else worker.terminate();
        };
        worker.on("message", (msg) => {
            rows[msg.i] = msg.row;
            done++;
            if (done % 25 === 0 || done === N) {
                const rate = done / ((Date.now() - t0) / 1000);
                console.log(`  ${done}/${N}  (${rate.toFixed(1)}/s, eta ${((N - done) / rate / 60).toFixed(1)} min)`);
            }
            feed();
        });
        worker.on("error", (e) => { console.error("worker error:", e); process.exit(1); });
        feed();
    }

    process.on("exit", () => {
        const cols = Object.keys(rows.find(Boolean));
        const lines = [cols.join(",")];
        for (const r of rows) if (r) lines.push(cols.map((c) => r[c]).join(","));
        fs.writeFileSync(OUT, lines.join("\n") + "\n");
        console.log(`wrote ${OUT} (${rows.filter(Boolean).length} rows)`);
    });
} else {
    const { makeContext, runScenario } = require("./engine.js");
    const ctx = makeContext();
    parentPort.on("message", (s) => {
        const out = runScenario(ctx, s.cfg);
        const row = { ...s.params, ...out };
        delete row.sim_time_ms;
        parentPort.postMessage({ i: s.i, row });
    });
}
