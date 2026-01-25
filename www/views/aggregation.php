<?php
defined('EMONCMS_EXEC') or die('Restricted access');
?>
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.3/jquery.flot.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.3/jquery.flot.time.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.3/jquery.flot.selection.min.js"></script>
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
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-9">
                <div class="row">
                    <div :class="show_wind ? 'col-lg-4' : 'col-lg-6'">
                        <div class="input-group mb-3">
                            <span class="input-group-text">Household heat demand</span>
                            <input class="form-control" v-model="household_heat_demand_kwh" @change="load" placeholder="">
                            <span class="input-group-text">kWh/y</span>
                        </div>
                    </div>
                    <div :class="show_wind ? 'col-lg-4' : 'col-lg-6'">
                        <div class="input-group mb-3">
                            <span class="input-group-text">Number of households</span>
                            <input class="form-control" v-model="number_of_households" @change="load" placeholder="">
                        </div>
                    </div>
                    <div class="col-lg-4" v-if="show_wind">
                        <div class="input-group mb-3">
                            <span class="input-group-text">UK wind proportion</span>
                            <input class="form-control" v-model="wind_proportion" @change="load" placeholder="">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Display toggles -->
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-9">
                <div class="card" style="background-color: #f8f9fa;">
                    <div class="card-body">
                        <h6 class="card-title mb-3">Show on chart:</h6>
                        <div class="row">
                            <div class="col-lg-3 col-md-3 col-sm-6 col-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="show_heat" v-model="show_heat" @change="load">
                                    <label class="form-check-label" for="show_heat">Heat output</label>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-3 col-sm-6 col-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="show_wind" v-model="show_wind" @change="load">
                                    <label class="form-check-label" for="show_wind">Wind generation</label>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-3 col-sm-6 col-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="show_cop" v-model="show_cop" @change="load">
                                    <label class="form-check-label" for="show_cop">COP</label>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-3 col-sm-6 col-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="show_outside_temp" v-model="show_outside_temp" @change="load">
                                    <label class="form-check-label" for="show_outside_temp">Outside temperature</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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
        "cop": { label: "Heat pump COP", data: false, yaxis: 3, color: 'orange', lines: { show: true, fill: 0.0 }}
    };

    var scale_unit = "kW";
    var scale = 1;
    var first_load = true;

    // Used to scale to average UK household heat demand ~9100 kWh/year
    var heatpumpmonitor_elec_demand = 3552;
    var heatpumpmonitor_heat_output = 13500;
    var heatpumpmonitor_cop = 3.8;

    var app = new Vue({
        el: '#app',
        data: {
            number_of_households: 300000,
            household_heat_demand_kwh: 9100,
            show_wind: false,
            show_heat: false,
            wind_proportion: 120,
            show_cop: false,
            show_outside_temp: false,
            SPF: 3.8
        },
        methods: {
            load: function() {
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



    function load() {

        view.calc_interval(1200,60);

        $.ajax({
            // text plain dataType:
            dataType: "json",
            url: path + "remote-feed",
            data: {
                'ids': [476422,536709,97699,536695].join(","),
                'start': view.start,
                'end': view.end,
                'interval': view.interval,
                'average': 1
            },
            async: true,
            success: function(result) {

                // -----------------------------------------------------------------------------------------------------------------------------
                // 1. Calculate scaling only on first load
                // -----------------------------------------------------------------------------------------------------------------------------

                if (first_load) {
                    first_load = false;
                    let heatpumpmonitor_elec_demand_sum = 0;
                    let heatpumpmonitor_heat_output_sum = 0;
                    let count = 0;

                    for (var z in result[0]['data']) {
                        let heatpump_elec = result[0]['data'][z][1];
                        if (heatpump_elec<0) heatpump_elec = 0;

                        let heatpump_heat = result[1]['data'][z][1];
                        if (heatpump_heat<0) heatpump_heat = null;

                        if (heatpump_elec>0 && heatpump_heat!==null) {
                            heatpumpmonitor_elec_demand_sum += heatpump_elec;
                            heatpumpmonitor_heat_output_sum += heatpump_heat;
                            count += 1;
                        }
                    }

                    let time_period_hours = Math.round((count * view.interval) / 3600);
                    if (time_period_hours>8760*0.9 && time_period_hours<8760*1.1) {
                        console.log("Time period is approximately 1 year - calculating scaling values, hours: " + time_period_hours);
                        heatpumpmonitor_elec_demand = (heatpumpmonitor_elec_demand_sum / count) * 24 * 365 * 0.001;
                        heatpumpmonitor_heat_output = (heatpumpmonitor_heat_output_sum / count) * 24 * 365 * 0.001;
                        heatpumpmonitor_cop = heatpumpmonitor_heat_output / heatpumpmonitor_elec_demand;

                        console.log("Time period hours: " + time_period_hours);
                        console.log("Average heatpumpmonitor electric demand (unscaled): " + heatpumpmonitor_elec_demand.toFixed(3) + " kWh");
                        console.log("Average heatpumpmonitor heat output (unscaled): " + heatpumpmonitor_heat_output.toFixed(3) + " kWh");
                        console.log("Average heatpumpmonitor COP: " + heatpumpmonitor_cop.toFixed(3));
                    } else {
                        console.log("Time period is not approximately 1 year (" + time_period_hours + " hours) using default scaling values");
                    }
                }

                // -----------------------------------------------------------------------------------------------------------------------------


                // auto scale kW, MW, GW
                // max value is ~2000W unscaled
                let max_value = 2000 * app.number_of_households;
                if (max_value>1e9) {
                    scale = 1e-9;
                    scale_unit = "GW";
                } else if (max_value>1e6) {
                    scale = 1e-6;
                    scale_unit = "MW";
                } else if (max_value>1e3) {
                    scale = 1e-3;
                    scale_unit = "kW";
                } else {
                    scale = 1;
                    scale_unit = "W";
                }

                var heatpump_elec_data = result[0]['data'];
                var heatpump_heat_data = result[1]['data'];
                var wind_generation_data = result[2]['data'];
                var outside_temp_data = result[3]['data'];

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
                draw();
            }
        });
    }

    function draw() {

        // Flot options
        var options = {
            xaxis: {
                // axisLabel: xaxis_label,
                // max: app.design_DT
                mode: "time",
                timeformat: "%b %Y",
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
                str += date.toLocaleString();

                tooltip(item.pageX, item.pageY, str, "#fff", "#000");
            }
        } else {
            $("#tooltip").remove();
            previousPoint = null;
        }
    });

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
