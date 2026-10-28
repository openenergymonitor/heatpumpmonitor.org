<?php
defined('EMONCMS_EXEC') or die('Restricted access');
?>
<script src="<?php echo $path; ?>theme/vendor/vue-2.7.16/vue.min.js" integrity="sha384-YVYXhPGIH/Gmcr0W5Rin4PcpcsG1a4pcdUUod1CnbDEJut7XiUaJtSlNKeRLJBPk"></script>
<script src="<?php echo $path; ?>theme/vendor/jquery-3.6.3/jquery.min.js" integrity="sha384-Ft/vb48LwsAEtgltj7o+6vtS2esTU9PCpDqcXs4OCVQFZu5BqprHtUCZ4kjK+bpE"></script>
<script src="<?php echo $path; ?>theme/vendor/flot-0.8.3/jquery.flot.min.js" integrity="sha384-RwhSkUmEGeOAldfwpJck6gcmrGxI7Q8shFWFF5lqnOihJnV556qpqUVpYNP7wSew"></script>
<script src="<?php echo $path; ?>theme/vendor/flot-0.8.3/jquery.flot.time.min.js" integrity="sha384-qSVSrwJ8D6S0H6+MOkF7MBxx9C21JN6c8O8uZlZzGPMpLhsV0N015e/P4bdc4Whj"></script>
<script src="<?php echo $path; ?>theme/vendor/flot-0.8.3/jquery.flot.selection.min.js" integrity="sha384-CwXlgbYx235nUO+kK1VMvvpMQvCQBPTxSvnARPZ10KOjsCVYSalFvknxEzuQXwxi"></script>
<script src="Lib/jquery.flot.axislabels.js"></script>
<script src="Modules/dashboard/vis.helper.js"></script>

<div id="app">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container-fluid">
            <h3>Heat pump grid demand aggregation</h3>
        </div>
    </div>

    <div class="container-fluid" style="margin-top:20px; max-width:1320px">

        <div class="row">
            <div class="col-auto mb-2">
                <div role="group" class="btn-group" aria-label="Systems included">
                    <button type="button" class="btn" :class="group=='mid' ? 'btn-primary' : 'btn-outline-primary'" @click="set_group('mid')" title="Systems with MID metering only">MID metered</button>
                    <button type="button" class="btn" :class="group=='live' ? 'btn-primary' : 'btn-outline-primary'" @click="set_group('live')">All systems</button>
                </div>
            </div>
            <div class="col text-end">

                <div role="group" class="btn-group" style="width: 250px; margin-right:20px">
                    <button type="button" class="btn btn-outline-secondary" @click="time_window(7)">W</button>
                    <button type="button" class="btn btn-outline-secondary" @click="time_window(30)">M</button>
                    <button type="button" class="btn btn-outline-secondary" @click="time_window(90)">3M</button>
                    <button type="button" class="btn btn-outline-secondary" @click="time_window(365)">Y</button>
                    <button type="button" class="btn btn-outline-secondary" @click="max">MAX</button>
                </div>

                <div role="group" class="btn-group" style="width: 250px; margin-right:20px">
                    <button type="button" class="btn btn-outline-secondary" @click="zoomin">+</button>
                    <button type="button" class="btn btn-outline-secondary" @click="zoomout">-</button>
                    <button type="button" class="btn btn-outline-secondary" @click="panleft">&lt;</button>
                    <button type="button" class="btn btn-outline-secondary" @click="panright">&gt;</button>
                </div>
            </div>
        </div>

        <div class="row" style="margin-right:-5px">
            <div id="placeholder" style="width:100%;height:600px; margin-bottom:20px"></div>
        </div>

        <!-- Configuration controls -->
        <div class="row">
            <div class="col-lg-7 mb-3">
                <div class="input-group">
                    <span class="input-group-text">Number of households</span>
                    <input class="form-control" v-model.number="number_of_households" @change="load" placeholder="">
                    <button type="button" class="btn" :class="number_of_households==1 ? 'btn-primary' : 'btn-outline-primary'" @click="set_households(1)">1 household</button>
                    <button type="button" class="btn" :class="number_of_households==300000 ? 'btn-primary' : 'btn-outline-primary'" @click="set_households(300000)" title="Rough number of UK households with heat pumps today">300k today</button>
                    <button type="button" class="btn" :class="number_of_households==30000000 ? 'btn-primary' : 'btn-outline-primary'" @click="set_households(30000000)" title="UK households if heating were fully electrified">30M fully electrified</button>
                </div>
                <div class="form-text">Try increasing to 300,000 households (roughly the number of UK homes with heat pumps today) or 30 million (all homes, fully electrified), or enter your own value.</div>
            </div>
            <div class="col-lg-5 mb-3">
                <div class="input-group">
                    <span class="input-group-text">Household heat demand</span>
                    <input class="form-control" v-model.number="household_heat_demand_kwh" @change="load" placeholder="">
                    <span class="input-group-text">kWh/y</span>
                </div>
                <div class="form-text" v-if="average_heat_kwh!==null">
                    The average {{ group=='mid' ? 'MID metered' : '' }} HeatpumpMonitor system supplies around <b>{{ average_heat_kwh.toLocaleString() }} kWh/y</b> of heat
                    (<a href="#" @click.prevent="set_heat_demand(average_heat_kwh)">use this</a>).
                    This is higher than the heat demand of a typical UK household.
                </div>
            </div>
        </div>

        <!-- Display toggles and chart settings -->
        <div class="card mb-3" style="background-color: #f8f9fa;">
            <div class="card-body d-flex flex-wrap align-items-center" style="gap:12px 24px">
                <span class="fw-semibold">Show on chart:</span>
                <div class="form-check form-check-inline m-0">
                    <input class="form-check-input" type="checkbox" id="show_heat" v-model="show_heat" @change="load">
                    <label class="form-check-label" for="show_heat">Heat output</label>
                </div>
                <div class="form-check form-check-inline m-0">
                    <input class="form-check-input" type="checkbox" id="show_wind" v-model="show_wind" @change="load">
                    <label class="form-check-label" for="show_wind">Wind generation</label>
                </div>
                <div class="form-check form-check-inline m-0">
                    <input class="form-check-input" type="checkbox" id="show_cop" v-model="show_cop" @change="load">
                    <label class="form-check-label" for="show_cop">COP</label>
                </div>
                <div class="form-check form-check-inline m-0">
                    <input class="form-check-input" type="checkbox" id="show_outside_temp" v-model="show_outside_temp" @change="load">
                    <label class="form-check-label" for="show_outside_temp">Outside temperature</label>
                </div>
                <!--
                <div class="form-check form-check-inline m-0">
                    <input class="form-check-input" type="checkbox" id="show_systems" v-model="show_systems" @change="load">
                    <label class="form-check-label" for="show_systems">Number of systems</label>
                </div>
                -->
                <div class="d-flex flex-wrap ms-auto" style="gap:12px">
                    <div class="input-group" style="width:auto" v-if="show_wind">
                        <span class="input-group-text">UK wind proportion</span>
                        <input class="form-control" style="max-width:90px" v-model="wind_proportion" @change="load" placeholder="">
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="input-group" style="width:auto">
                        <span class="input-group-text">Minimum interval</span>
                        <select class="form-select" v-model.number="min_interval" @change="load">
                            <option :value="60">Auto</option>
                            <option :value="1800">Half hourly</option>
                            <option :value="3600">Hourly</option>
                            <option :value="86400">Daily</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="row justify-content-center" style="margin-top:20px">
            <div class="col-lg-10 col-xl-9 text-center">


                <div v-if="average_systems!==null" style="font-size:14px; color:#666; margin-bottom:20px">
                    Average number of {{ group=='mid' ? 'MID metered systems' : 'systems' }} in window: <b>{{ average_systems }}</b>
                </div>

                <p>Can you match supply and demand with heat pumps on the grid?</p><p>Explore our simple grid model that uses the above HeatpumpMonitor dataset.</p>
                <a href="https://openenergymonitor.org/tools/ukgridsim.html" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px; margin-right:8px" aria-hidden="true">
                        <!-- Pylon legs and top -->
                        <path d="M8.5 21 L11.2 3 h1.6 L15.5 21"/>
                        <!-- Crossarms -->
                        <path d="M4.5 7 h15"/>
                        <path d="M6 11 h12"/>
                        <!-- Insulators -->
                        <path d="M4.5 7 v2 M19.5 7 v2"/>
                        <!-- Lattice cross-brace -->
                        <path d="M9.9 12 L14.8 16.5 M14.1 12 L9.2 16.5"/>
                    </svg>UK Grid Sim</a>
                    <br><br>
                <p style="font-size:14px; color:#666">Note: There are a small number of HeatpumpMonitor systems that also provide active cooling in summer, real active cooling demand is aggregated as positive heat output (winter defrost heat loss is still subtracted as it should be).</p>
            </div>
        </div>

        <br>

    </div>
</div>

<script>

    var date = new Date();

    var interval = 3600;
    // end to the closest interval
    view.end = Math.floor((date.getTime()/1000)/interval)*interval*1000;
    // start time 30 days ago
    view.start = view.end - 365*24*3600*1000;

    var series = {
        "wind": { label: "Wind generation", data: false, color: 'green', lines: { show: true, fill: 0.2 }},
        "heat": { label: "Heat output", data: false, color: 0, lines: { show: true, fill: 0.5 }},
        "elec": { label: "Electric demand", data: false, color: 1, lines: { show: true, fill: 0.5 }},
        // Outside temperature on axis 2
        "outsideT": { label: "Outside temperature", data: false, yaxis: 2, color: 'blue', lines: { show: true, fill: 0.0 }},
        // COP data on axis 3
        "cop": { label: "Heat pump COP", data: false, yaxis: 3, color: 'orange', lines: { show: true, fill: 0.0 }},
        // Number of systems on axis 4
        "systems": { label: "Number of systems", data: false, yaxis: 4, color: 'purple', lines: { show: true, fill: 0.0 }}
    };

    var scale_unit = "kW";
    var scale = 1;

    // Aggregate feeds on emoncms.org for each group of systems, written by
    // scripts/aggregate/aggregate_fast.php (feed tag = group). The totals are
    // divided by the number of systems here to give the average per system.
    var feed_groups = {
        mid:  { elec: 548548, heat: 548550, outsideT: 548553, numsys: 548554 },
        live: { elec: 476418, heat: 536693, outsideT: 536695, numsys: 476417 }
    };
    var wind_feedid = 97699;   // UK wind generation

    // Average annual electric demand and heat output per system (kWh), used to
    // scale to the household heat demand. Calculated from the last 12 months of
    // each group when first shown, these defaults are used if that fails.
    var default_scaling = { elec: 3552, heat: 13500 };
    var scaling = {};
    var load_id = 0;
    var data_interval = null;   // interval (s) of the data currently plotted

    // Human readable interval e.g. 30 min, 1 hour, 2 days
    function format_interval(seconds) {
        if (seconds % 86400 == 0) return (seconds/86400) + (seconds==86400 ? " day" : " days");
        if (seconds % 3600 == 0) return (seconds/3600) + (seconds==3600 ? " hour" : " hours");
        if (seconds % 60 == 0) return (seconds/60) + " min";
        return seconds + " s";
    }

    var app = new Vue({
        el: '#app',
        data: {
            number_of_households: 1,
            household_heat_demand_kwh: 10000,
            show_wind: false,
            show_heat: false,
            wind_proportion: 120,
            show_cop: false,
            show_outside_temp: false,
            show_systems: false,
            average_systems: null,
            average_heat_kwh: null,
            group: 'mid',
            min_interval: 60,
            SPF: 3.8
        },
        methods: {
            load: function() {
                load();
            },
            set_heat_demand: function(kwh) {
                this.household_heat_demand_kwh = kwh;
                load();
            },
            set_households: function(n) {
                this.number_of_households = n;
                load();
            },
            set_group: function(group) {
                this.group = group;
                load();
            },
            zoomin: function() {
                view.zoomin();
                load();
            },
            zoomout: function() {
                view.zoomout();
                load();
            },
            panleft: function() {
                view.panleft();
                load();
            },
            panright: function() {
                view.panright();
                load();
            },
            max: function() {
                var date = new Date();
                view.end = Math.floor(date.getTime()/1000/interval)*interval*1000;

                // 13th of March 2022
                var start_date = new Date("2022-03-13T00:00:00Z");
                view.start = start_date.getTime();
                load();
            },
            time_window: function(days) {
                var date = new Date();
                view.end = Math.floor(date.getTime()/1000/interval)*interval*1000;
                view.start = view.end - days*24*3600*1000;
                load();
            }
        },
        filters: {
            toFixed: function(value, dp) {
                if (!value) value = 0;
                return value.toFixed(dp);
            }
        }
    });
    
    app.load();



    // Divide a total series by the number of systems in place, as the emoncms.org
    // virtual feeds did (source total, divide by source number of systems)
    function per_system(data, numsys_data) {
        for (var z in data) {
            let v = data[z][1];
            let n = numsys_data[z] ? numsys_data[z][1] : null;
            data[z][1] = (v===null || n===null || n==0) ? null : v / n;
        }
    }

    function load_scaling(group, done) {
        var feeds = feed_groups[group];
        var end = Math.floor(Date.now()/1000/3600)*3600*1000;
        $.ajax({
            dataType: "json",
            url: path + "remote-feed",
            data: {
                'ids': [feeds.elec, feeds.heat, feeds.numsys].join(","),
                'start': end - 365*24*3600*1000,
                'end': end,
                'interval': 3600,
                'average': 1
            },
            success: function(result) {
                var elec_data = result[0]['data'];
                var heat_data = result[1]['data'];
                per_system(elec_data, result[2]['data']);
                per_system(heat_data, result[2]['data']);

                let elec_sum = 0;
                let heat_sum = 0;
                let count = 0;
                for (var z in elec_data) {
                    let heatpump_elec = elec_data[z][1];
                    if (heatpump_elec<0) heatpump_elec = 0;
                    let heatpump_heat = heat_data[z][1];
                    if (heatpump_heat<0) heatpump_heat = null;
                    if (heatpump_elec>0 && heatpump_heat!==null) {
                        elec_sum += heatpump_elec;
                        heat_sum += heatpump_heat;
                        count += 1;
                    }
                }

                // hourly data, so count is the number of hours with data
                if (count>8760*0.9 && count<8760*1.1) {
                    scaling[group] = {
                        elec: (elec_sum / count) * 24 * 365 * 0.001,
                        heat: (heat_sum / count) * 24 * 365 * 0.001
                    };
                    console.log(group + ": average per system " + scaling[group].elec.toFixed(0) + " kWh electric, "
                        + scaling[group].heat.toFixed(0) + " kWh heat, COP " + (scaling[group].heat / scaling[group].elec).toFixed(2));
                } else {
                    console.log(group + ": " + count + " hours of data in the last year, using default scaling");
                    scaling[group] = default_scaling;
                }
                done();
            },
            error: function() {
                scaling[group] = default_scaling;
                done();
            }
        });
    }

    function load() {

        if (!scaling[app.group]) {
            load_scaling(app.group, load);
            return;
        }

        view.calc_interval(1200, app.min_interval);

        var group = app.group;
        var this_interval = view.interval;
        var feeds = feed_groups[group];
        var this_load = ++load_id;

        $.ajax({
            dataType: "json",
            url: path + "remote-feed",
            data: {
                'ids': [feeds.elec, feeds.heat, wind_feedid, feeds.outsideT, feeds.numsys].join(","),
                'start': view.start,
                'end': view.end,
                'interval': view.interval,
                'average': 1
            },
            async: true,
            success: function(result) {

                // a later load (e.g. after switching group) has been started
                if (this_load != load_id) return;

                var heatpumpmonitor_heat_output = scaling[group].heat;
                app.average_heat_kwh = Math.round(heatpumpmonitor_heat_output / 100) * 100;

                // auto scale kW, MW, GW
                // max value is ~2000W unscaled
                let max_value = 2000 * app.number_of_households;
                if (max_value>1e9) {
                    scale = 1e-9;
                    scale_unit = "GW";
                } else if (max_value>1e6) {
                    scale = 1e-6;
                    scale_unit = "MW";
                } else {
                    // kW minimum, tooltip shows 0.1 kW resolution
                    scale = 1e-3;
                    scale_unit = "kW";
                }

                var heatpump_elec_data = result[0]['data'];
                var heatpump_heat_data = result[1]['data'];
                var wind_generation_data = result[2]['data'];
                var outside_temp_data = result[3]['data'];
                var number_of_systems_data = result[4]['data'];

                per_system(heatpump_elec_data, number_of_systems_data);
                per_system(heatpump_heat_data, number_of_systems_data);

                // Average number of systems in window
                var sum_systems = 0;
                var count_systems = 0;
                for (var z in number_of_systems_data) {
                    let number_of_systems = number_of_systems_data[z][1];
                    if (number_of_systems!==null && number_of_systems>0) {
                        sum_systems += number_of_systems;
                        count_systems += 1;
                    }
                }
                app.average_systems = count_systems>0 ? Math.round(sum_systems/count_systems) : null;

                var sum_heatpump_elec = 0;
                var sum_heatpump_heat = 0;
                var sum_wind = 0;

                var cop = 0;
                var cop_data = [];

                for (var z in heatpump_elec_data) {
                    let heatpump_elec = heatpump_elec_data[z][1];
                    if (heatpump_elec<0) heatpump_elec = 0;

                    let heatpump_heat = heatpump_heat_data[z][1];
                    if (heatpump_heat<0) heatpump_heat = null;

                    let wind_generation = wind_generation_data[z][1];
                    if (wind_generation<0) wind_generation = 0;

                    let outside_temp = outside_temp_data[z][1];

                    heatpump_elec_data[z][1] = heatpump_elec * scale * app.number_of_households * (app.household_heat_demand_kwh / heatpumpmonitor_heat_output);
                    heatpump_heat_data[z][1] = heatpump_heat * scale * app.number_of_households * (app.household_heat_demand_kwh / heatpumpmonitor_heat_output);

                    sum_heatpump_elec += heatpump_elec_data[z][1];
                    sum_heatpump_heat += heatpump_heat_data[z][1];

                    wind_generation_data[z][1] = wind_generation * 0.0525 * app.wind_proportion * 0.01 * scale * app.number_of_households * (app.household_heat_demand_kwh / heatpumpmonitor_heat_output);

                    sum_wind += wind_generation_data[z][1];

                    // calculate COP
                    if (heatpump_elec>0 && heatpump_heat!==null) {
                        cop = heatpump_heat / heatpump_elec;
                    } else {
                        cop = null;
                    }
                    if (cop>7) {
                        cop = null;
                    }

                    cop_data.push([heatpump_elec_data[z][0], cop]);
                }

                console.log("Average heatpump electric demand: " + (sum_heatpump_elec/heatpump_elec_data.length).toFixed(3) + " " + scale_unit);
                console.log("Average heatpump heat output: " + (sum_heatpump_heat/heatpump_heat_data.length).toFixed(3) + " " + scale_unit);
                console.log("Average wind generation: " + (sum_wind/wind_generation_data.length).toFixed(3) + " " + scale_unit);

                series["heat"].data = heatpump_heat_data;
                series["elec"].data = heatpump_elec_data;
                series["wind"].data = wind_generation_data;
                series["outsideT"].data = outside_temp_data;
                series["cop"].data = cop_data;
                series["systems"].data = number_of_systems_data;
                data_interval = this_interval;
                draw();
            }
        });
    }

    function draw() {

        // Adapt x-axis date/time format to the visible range
        var range = view.end - view.start;
        var day = 24 * 3600 * 1000;
        var timeformat = "%b %Y";                               // months and beyond
        if (range <= 2 * day) timeformat = "%H:%M";             // hours
        else if (range <= 14 * day) timeformat = "%d %b %H:%M"; // days
        else if (range <= 180 * day) timeformat = "%d %b";      // weeks to months

        // Flot options
        var options = {
            xaxis: {
                // axisLabel: xaxis_label,
                // max: app.design_DT
                mode: "time",
                timeformat: timeformat,
                min: view.start,
                max: view.end,
            },
            yaxis: {
                // min: 0,
                // max: max_heat * 1.1,
                axisLabel: "Heatpump electric demand (" + scale_unit + ")",
            },
            yaxes: [
                {
                    // y1axis - electric demand and heat output
                    axisLabel: "Heatpump electric demand (" + scale_unit + ")",
                },
                {
                    // y2axis - outside temperature
                    axisLabel: "Outside temperature (°C)",
                    position: "right",
                    show: app.show_outside_temp
                },
                {
                    // y3axis - COP
                    axisLabel: "Heat pump COP",
                    position: "right",
                    min: 0,
                    show: app.show_cop
                },
                {
                    // y4axis - number of systems
                    axisLabel: "Number of systems",
                    position: "right",
                    min: 0,
                    show: app.show_systems
                }
            ],
            grid: {
                hoverable: true,
                clickable: true
            },
            axisLabels: {
                show: true
            },
            legend: {
                // hide
                show: false,
                position: "nw"
            },
            selection: {
                mode: "x"
            }
        };

        var plot_series = [];

        // Hide wind generation if not selected
        if (app.show_wind) {
            plot_series.push(series["wind"]);
        }

        // Hide heat output if not selected
        if (app.show_heat) {
            plot_series.push(series["heat"]);
        }

        // Show electric demand always
        plot_series.push(series["elec"]);

        // Hide outside temperature if not selected
        if (app.show_outside_temp) {
            plot_series.push(series["outsideT"]);
        }

        // Hide COP if not selected
        if (app.show_cop) {
            plot_series.push(series["cop"]);
        }

        // Hide number of systems if not selected
        if (app.show_systems) {
            plot_series.push(series["systems"]);
        }

        var chart = $.plot("#placeholder", plot_series, options);
    }

    // Flot selection
    $("#placeholder").bind("plotselected", function(event, ranges) {
        view.start = ranges.xaxis.from;
        view.end = ranges.xaxis.to;
        load();
    });

    // Flot tooltip
    var previousPoint = null;
    $("#placeholder").bind("plothover", function(event, pos, item) {
        if (item) {
            if (previousPoint != item.datapoint) {
                previousPoint = item.datapoint;

                $("#tooltip").remove();
                var x = item.datapoint[0];
                var y = item.datapoint[1];

                let seriesIndex = item.seriesIndex;

                var date = new Date(x);
                var str = "";
                str += series["elec"].label + ": " + series["elec"].data[item.dataIndex][1].toFixed(1) + " " + scale_unit + "<br>";

                if (app.show_heat) {
                    str += series["heat"].label + ": " + series["heat"].data[item.dataIndex][1].toFixed(1) + " " + scale_unit + "<br>";
                }
                if (app.show_wind) {
                    str += series["wind"].label + ": " + series["wind"].data[item.dataIndex][1].toFixed(1) + " " + scale_unit + "<br>";
                }
                if (app.show_outside_temp) {
                    str += series["outsideT"].label + ": " + series["outsideT"].data[item.dataIndex][1].toFixed(1) + " °C" + "<br>";
                }
                if (app.show_cop) {
                    str += series["cop"].label + ": " + series["cop"].data[item.dataIndex][1].toFixed(1) + "<br>";
                }
                if (app.show_systems) {
                    str += series["systems"].label + ": " + series["systems"].data[item.dataIndex][1].toFixed(0) + "<br>";
                }
                str += format_date(date, data_interval);
                if (data_interval) {
                    str += "<br>Interval: " + format_interval(data_interval) + " average";
                }

                chart_tooltip(item.pageX, item.pageY, str);
            }
        } else {
            $("#tooltip").remove();
            previousPoint = null;
        }
    });

    // e.g. 6th September 2026, with the time added for sub-daily intervals
    function format_date(date, interval) {
        var months = ["January", "February", "March", "April", "May", "June", "July",
                      "August", "September", "October", "November", "December"];
        var d = date.getDate();
        var suffix = "th";
        if (d % 10 == 1 && d != 11) suffix = "st";
        else if (d % 10 == 2 && d != 12) suffix = "nd";
        else if (d % 10 == 3 && d != 13) suffix = "rd";

        var str = d + suffix + " " + months[date.getMonth()] + " " + date.getFullYear();
        if (!interval || interval < 86400) {
            str += ", " + String(date.getHours()).padStart(2, "0") + ":" + String(date.getMinutes()).padStart(2, "0");
        }
        return str;
    }

    // Tooltip kept within the chart area: above-right of the point by default,
    // flipped below near the top and to the left near the right hand edge
    function chart_tooltip(x, y, html) {
        $("#tooltip").remove();
        var tip = $("<div id='tooltip'>").html(html).css({
            position: "absolute",
            "background-color": "#fff",
            border: "1px solid #ccc",
            "border-radius": "4px",
            "box-shadow": "0 2px 6px rgba(0,0,0,0.15)",
            padding: "6px 10px",
            "font-size": "14px",
            "pointer-events": "none",
            "z-index": 1000
        }).appendTo("body");

        var w = tip.outerWidth();
        var h = tip.outerHeight();
        var chart = $("#placeholder");
        var bounds = chart.offset();
        var gap = 15;

        var left = x + gap;
        if (left + w > bounds.left + chart.width()) left = x - w - gap;
        if (left < bounds.left) left = bounds.left;

        var top = y - h - gap;
        if (top < bounds.top) top = y + gap;

        tip.css({ left: left, top: top });
    }

    // Window resize
    $(window).resize(function() {
        resize();
    });
    
    function resize() {
        var width = $("#placeholder").width();
        var height = width*1.2;
        if (height>600) height = 600;
        $("#placeholder").height(height);
        draw();   
    }

</script>
