<?php
defined('EMONCMS_EXEC') or die('Restricted access');
?>

<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.3/jquery.flot.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.3/jquery.flot.time.min.js"></script>
<script src="Lib/jquery.flot.axislabels.js"></script>

<!-- moment.js for date formatting -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>

<div id="app">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container-fluid" style="max-width:1320px">

            <div class="input-group mb-3" style="width:600px; float:right">
                <span class="input-group-text">Select system</span>

                <select class="form-control" v-model="systemid" @change="change_system">
                    <option v-for="s,i in filtered_system_list" :value="s.id">{{ s.location }}, {{ s.hp_manufacturer }} {{ s.hp_model }}, {{ s.hp_output }} kW</option>
                </select>

                <button class="btn btn-primary" @click="next_system(-1)"><</button>
                <button class="btn btn-primary" @click="next_system(1)">></button>
            </div>

            <h3>Daily</h3>


            
        </div>
    </div>

    <div class="container-fluid" style="margin-top:20px; max-width:1320px">
        <!-- row and 4 columns -->


        <!-- Table of the 5 coldest days -->
        <div class="row">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Mean Outside Temp</th>
                        <th>Mean Flow Temp when running</th>
                        <th>RoomT Running</th>
                        <th>Elec</th>
                        <th>Heat</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="day in coldest_days" >
                        <td>{{ day.timestamp | toDate }}</td>
                        <td>{{ day.combined_outsideT_mean.toFixed(1) }}&deg;C</td>
                        <td>{{ day.running_flowT_mean.toFixed(2) }}&deg;C</td>
                        <td>{{ day.running_roomT_mean.toFixed(1) }}&deg;C</td>
                        <td>{{ day.combined_elec_kwh.toFixed(1) }} kWh</td>
                        <td>{{ day.combined_heat_kwh.toFixed(1) }} kWh</td>

                        <td><input type="radio" v-model="selected_day" :value="day.timestamp" @change="change_selected_day"></td>
                    </tr>
                </tbody>
            </table>

        </div>

        <div class="row" style="margin-right:-5px">
            <!-- 2/3 width -->
            <div class="col-lg-8">
            <div id="placeholder" style="width:100%;height:500px; margin-bottom:20px"></div>
            </div>

            <!-- 1/3 width -->
            <div class="col-lg-4">

                <div class="input-group mb-3">
                    <span class="input-group-text">Mean FlowT (When running)</span>
                    <input type="text" class="form-control" v-model="mean_flowT" disabled>
                </div>

                <div class="input-group mb-3">
                    <span class="input-group-text">Mean OutsideT</span>
                    <input type="text" class="form-control" v-model="mean_outsideT" disabled>
                </div>

                <div class="input-group mb-3">
                    <span class="input-group-text">Max FlowT</span>
                    <input type="text" class="form-control" v-model="max_flowT" disabled>
                </div>

                <button class="btn btn-primary" @click="save_coldest" :disabled="!enable_save">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
    var userid = <?php echo $userid; ?>;
    var systemid = <?php echo $systemid; ?>;
    var data = [];
    var timeseries = {};
    var systemid_map = {};


    var app = new Vue({
        el: '#app',
        data: {
            enable_save: false,
            systemid: systemid,
            system_list: {},
            filtered_system_list: [],
            coldest_days: [],
            max_flowT: '',
            mean_flowT: '',
            mean_outsideT: '',
            selected_day: '',
            flowT_mean_window: ''
        },
        methods: {
            change_system: function() {
                load();
            },
            load: function() {
                load();
            },
            next_system: function(direction) {
               // find current system
                var index = this.find_system(app.systemid);
                if (index == -1) {
                    return;
                }
                index += direction*1;
               
                if (app.filtered_system_list[index] == undefined) {
                    return;
                }

                app.systemid = app.filtered_system_list[index].id;
                load();
            },

            find_system: function(id) {
                for (var i in app.filtered_system_list) {
                    if (app.filtered_system_list[i].id == id) {
                        return i*1;
                    }
                }
                return -1;
            },

            filter_system_list: function() {
                // Only systems that have 290 days of data in the last year
                // combined_data_length is in seconds

                var filtered = app.system_list;
            
                // filter out systems with less than 290 days of data
                filtered = filtered.filter(function(s) {
                    return s.combined_data_length > 290 * 24 * 3600;
                });

                // sort by combined_cop desc
                filtered.sort(function(a, b) {
                    return b.combined_cop - a.combined_cop;
                });

                app.filtered_system_list = filtered;
            },

            change_selected_day: function() {
                console.log(app.selected_day);
                load_timeseries(app.selected_day);

                // apply mean_flowT & mean_outsideT from selected day
                var index = app.coldest_days.findIndex(x => x.timestamp == app.selected_day);
                app.mean_flowT = app.coldest_days[index].running_flowT_mean.toFixed(2);
                app.mean_outsideT = app.coldest_days[index].combined_outsideT_mean.toFixed(2);
            },

            save_coldest: function() {

                var data_to_save = {
                    'id': app.systemid,
                    'data': {
                        'measured_max_flow_temp_coldest_day': app.max_flowT
                    }
                };

                $.ajax({
                    type: "POST",
                    url: path + "system/save",
                    contentType: "application/json",
                    data: JSON.stringify(data_to_save),
                    success: function(result) {
                        console.log(result);
                        alert(result.message);

                        // update system_list
                        var index = app.find_system(app.systemid);
                        app.filtered_system_list[index].measured_max_flow_temp_coldest_day = app.max_flowT;
                        app.filtered_system_list[index].measured_mean_flow_temp_coldest_day = app.mean_flowT;
                        app.filtered_system_list[index].measured_outside_temp_coldest_day = app.mean_outsideT;
                    }
                });
            }
        },
        filters: {
            toFixed: function(value, dp) {
                if (!value) value = 0;
                return value.toFixed(dp);
            },
            toDate: function(value) {
                // 15 Dec 2022
                // timezone is London
                return moment(value*1000).format('DD MMM YYYY');
            }
        }
    });

    // Load list of systems
    $.ajax({
        type: "GET",
        url: path + "system/list/public.json",
        success: function(result) {

            // System list by id
            app.system_list = result;

            // Get last365 stats
            $.ajax({
                type: "GET",
                url: path + "system/stats/last365",
                
                success: function(result) {

                    // apply stats to each system in system_list
                    for (var z in app.system_list) {
                        var id = app.system_list[z].id;
                        var stats = result[id];
                        if (stats !== undefined) {
                            for (var key in stats) {
                                app.system_list[z][key] = stats[key];
                            }
                        }
                    }

                    app.filter_system_list();

                    load();
                    resize();
                }
            });

        }
    });

    function load() {

        // Set app.max_flowT
        var index = app.find_system(app.systemid);

        app.max_flowT = app.filtered_system_list[index].measured_max_flow_temp_coldest_day;
        app.mean_flowT = app.filtered_system_list[index].measured_mean_flow_temp_coldest_day;
        app.mean_outsideT = app.filtered_system_list[index].measured_outside_temp_coldest_day;

        // does this user has write access
        $.ajax({
            type: "GET",
            url: path + "system/hasaccess",
            data: {
                'id': app.systemid
            },
            success: function(result) {
                app.enable_save = 1 * result;
            }
        });

        $.ajax({
            dataType: "text",
            url: path + "system/stats/daily",
            data: {
                'id': app.systemid
            },
            async: true,
            success: function(result) {
                var lines = result.split('\n');

                var fields = lines[0].split(',');
                for (var z in fields) {
                    fields[z] = fields[z].trim();
                }
                
                data = [];
                for (var i = 1; i < lines.length; i++) {
                    var parts = lines[i].split(',');
                    if (parts.length != fields.length) {
                        continue;
                    }

                    var day = {};

                    for (var j = 1; j < parts.length; j++) {
                        let value = parts[j] * 1;
                        day[fields[j]] = value;
                    }

                    // filter out days with 0 heat
                    if (day.combined_heat_kwh == 0) {
                        continue;
                    }

                    data.push(day);
                }

                // Find the 5 coldest days, based on combined_outsideT_mean
                var coldest_days = [];
                // Sort by combined_outsideT_mean
                data.sort(function(a, b) {
                    return a.combined_outsideT_mean - b.combined_outsideT_mean;
                });
                
                for (var i = 0; i < 6; i++) {
                    coldest_days.push(data[i]);
                }

                app.coldest_days = coldest_days;

                // get timestamp of coldest day
                var timestamp = coldest_days[0].timestamp*1;
                app.selected_day = timestamp;
                load_timeseries(timestamp);
            }
        });
    }

    function load_timeseries(timestamp) {
        // Load timeseries data
        $.ajax({
            type: "GET",
            url: path + "timeseries/data",
            data: {
                'id': app.systemid,
                'feeds': 'heatpump_flowT,heatpump_returnT,heatpump_dhw',
                'start': timestamp,
                'end': timestamp + 24 * 3600,
                'interval': 60,
                'average': 1,
                'timeformat': 'notime'
            },
            success: function(result) {
                var flowT_values = result['heatpump_flowT'];
                var returnT_values = result['heatpump_returnT'];
                var dhw_values = false;
                if (result['heatpump_dhw'] != undefined) {
                    dhw_values = result['heatpump_dhw'];
                }

                timeseries['flowT'] = [];
                timeseries['returnT'] = [];
                timeseries['dhw'] = [];

                var sum = 0;
                var count = 0;
                for (var i in flowT_values) {
                    let time = timestamp + i * 60;

                    if (flowT_values[i] < -10) {
                        flowT_values[i] = null;
                    }

                    if (returnT_values[i] < -10) {
                        returnT_values[i] = null;
                    }

                    timeseries['flowT'].push([time*1000, flowT_values[i]]);
                    timeseries['returnT'].push([time*1000, returnT_values[i]]);
                    if (dhw_values) {
                        timeseries['dhw'].push([time*1000, dhw_values[i]]);
                    }

                    if (flowT_values[i] != null) {
                        sum += flowT_values[i];
                        count++;
                    }
                }

                // Generate a moving 2 hour average of flowT
                // interval is 60 seconds
                // so 60 samples either side

                timeseries['flowT_mean'] = [];
                for (var i = 0; i < timeseries['flowT'].length; i++) {
                    let sum = 0;
                    let count = 0;
                    for (var j = i - 60; j <= i + 60; j++) {
                        if (j >= 0 && j < timeseries['flowT'].length) {
                            sum += timeseries['flowT'][j][1];
                            count++;
                        }
                    }
                    timeseries['flowT_mean'].push([timeseries['flowT'][i][0], sum / count]);
                }

                app.flowT_mean_window = (sum / count).toFixed(2);

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
                mode: "time", timezone: "browser",
                timeformat: "%H:%M",
            },
            yaxes: [{
                    position: "left",
                    axisLabel: "Temperature (°C)",
                },
                {
                    min: 0,
                    max: 1,
                    // hide
                    show: false
                }
            ],
            grid: {
                hoverable: true,
                clickable: true
            },
            axisLabels: {
                show: true
            },
        };

        var series = [];

        if (timeseries['flowT'] != undefined) {
            series.push({ data: timeseries['flowT'], label: "FlowT", color: 2, lines: { show: true, fill: false } });
        }

        if (timeseries['returnT'] != undefined) {
            series.push({ data: timeseries['returnT'], label: "ReturnT", color: 3, lines: { show: true, fill: false } });
        }

        if (timeseries['dhw'] != undefined) {
            series.push({ data: timeseries['dhw'], label: "DHW", yaxis: 2, color: "#88F", lines: { lineWidth: 0, show: true, fill: 0.15 } });
        }

        if (timeseries['flowT_mean'] != undefined) {
            series.push({ data: timeseries['flowT_mean'], label: "FlowT Mean", color: 4, lines: { show: true, fill: false } });
        }

        var chart = $.plot("#placeholder", series, options);
    }

    // Flot tooltip
    var previousPoint = null;
    $("#placeholder").bind("plothover", function(event, pos, item) {
        if (item) {
            if (previousPoint != item.datapoint) {
                previousPoint = item.datapoint;

                $("#tooltip").remove();
                var x = item.datapoint[0];
                var y = item.datapoint[1];

                var str = "";
                

                str += item.series.label +": " + y.toFixed(1) + "&deg;C";



                tooltip(item.pageX, item.pageY, str, "#fff", "#000");
            }
        } else {
            $("#tooltip").remove();
            previousPoint = null;
        }
    });

    // Flot click
    $("#placeholder").bind("plotclick", function(event, pos, item) {
        if (item) {
            var x = item.datapoint[0];
            var y = item.datapoint[1];

            app.max_flowT = y.toFixed(1);


        }
    });

    // Window resize
    $(window).resize(function() {
        resize();
    });
    
    function resize() {
        var width = $("#placeholder").width();
        var height = width*1.2;
        if (height>450) height = 450;
        $("#placeholder").height(height);
        draw();   
    }

    // Creates a tooltip for use with flot graphs
    function tooltip(x, y, contents, bgColour, borderColour = "rgb(255, 221, 221)") {
        var offset = 10; // use higher values for a little spacing between `x,y` and tooltip
        var elem = $('<div id="tooltip">' + contents + '</div>').css({
            position: 'absolute',
            color: "#000",
            display: 'none',
            'font-weight': 'bold',
            border: '1px solid ' + borderColour,
            padding: '2px',
            'background-color': bgColour,
            opacity: '0.8',
            'text-align': 'left'
        }).appendTo("body").fadeIn(200);

        var elemY = y - elem.height() - offset;
        var elemX = x - elem.width() - offset;
        if (elemY < 0) {
            elemY = 0;
        }
        if (elemX < 0) {
            elemX = 0;
        }
        elem.css({
            top: elemY,
            left: elemX
        });
    }


</script>
