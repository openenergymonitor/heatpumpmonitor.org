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

        <!-- row with x and y axis selection -->
        <div class="row">

            <!-- Y-axis grouped by group -->
            <div class="col-lg-6">
                <div class="input-group mb-3">
                    <span class="input-group-text">Scale by number of households</span>
                    <input class="form-control" v-model="number_of_households" @change="load" placeholder="Enter number of households">
                </div>
            </div>
            <!-- Show wind power checkbox and option to set proportion -->
            <div class="col-lg-6">
                <div class="input-group mb-3">
                    <span class="input-group-text">Compare to UK wind (scaled)</span>
                    <div class="input-group-text">
                        <input class="form-check-input mt-0" type="checkbox" v-model="show_wind" @change="load">
                    </div>
                    <input v-if="show_wind" class="form-control" v-model="wind_proportion" @change="load" placeholder="Enter wind power proportion" style="max-width:100px">
                    <label v-if="show_wind" class="input-group-text">%</label>
                </div>
            </div>
        </div>  

    </div>
</div>

<script>

    var date = new Date();

    var interval = 3600;
    // end to the closest interval
    view.end = Math.floor(date.getTime()/1000/interval)*interval*1000;
    // start time 30 days ago
    view.start = view.end - 30*24*3600*1000;

    var series = [
        { label: "Wind generation", data: false, color: 'green', lines: { show: true, fill: 0.2 }},
        { label: "Electric demand", data: false, color: 0, lines: { show: true, fill: 0.5 }}
    ];

    var scale_unit = "kW";
    var scale = 1;

    var app = new Vue({
        el: '#app',
        data: {
            number_of_households: 30000000,
            show_wind: false,
            wind_proportion: 120
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

        view.calc_interval(1200,1800);

        $.ajax({
            // text plain dataType:
            dataType: "json",
            url: path + "remote-feed",
            data: {
                'ids': [476422,97699].join(","),
                'start': view.start,
                'end': view.end,
                'interval': view.interval,
                'average': 1
            },
            async: true,
            success: function(result) {

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
                var wind_generation_data = result[1]['data'];

                var sum_heatpump = 0;
                var sum_wind = 0;

                for (var z in heatpump_elec_data) {
                    let heatpump_elec = heatpump_elec_data[z][1];
                    if (heatpump_elec<0) heatpump_elec = 0;

                    let wind_generation = wind_generation_data[z][1];
                    if (wind_generation<0) wind_generation = 0;

                    heatpump_elec_data[z][1] = heatpump_elec * scale * app.number_of_households;

                    sum_heatpump += heatpump_elec_data[z][1];

                    wind_generation_data[z][1] = wind_generation * 0.0525 * app.wind_proportion * 0.01 * scale * app.number_of_households;

                    sum_wind += wind_generation_data[z][1];
                }

                console.log("Average heatpump electric demand: " + (sum_heatpump/heatpump_elec_data.length).toFixed(3) + " " + scale_unit);
                console.log("Average wind generation: " + (sum_wind/wind_generation_data.length).toFixed(3) + " " + scale_unit);

                series[1].data = heatpump_elec_data;
                series[0].data = wind_generation_data;
                draw();
            }
        });
    }

    function draw() {

        // Flot options
        var options = {
            series: {},
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

        // Hide wind generation if not selected
        if (!app.show_wind) {
            series[0].data = false;
        }

        var chart = $.plot("#placeholder", series, options);
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

                var date = new Date(x);
                var str = item.series.label + "<br>" + date.toLocaleString() + "<br>" + y.toFixed(2) + " " + scale_unit;
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
