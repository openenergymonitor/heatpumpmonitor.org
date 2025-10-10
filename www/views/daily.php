<?php
defined('EMONCMS_EXEC') or die('Restricted access');
?>
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.3/jquery.flot.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.3/jquery.flot.time.min.js"></script>
<script src="Lib/jquery.flot.axislabels.js"></script>

<div id="app">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container-fluid">
            <h3>Daily</h3>
        </div>
    </div>

    <div class="container-fluid" style="margin-top:20px; max-width:1320px">
        <!-- row and 4 columns -->
        <div class="row">
            <div class="col-lg-6">
                <div class="input-group mb-3">
                    <span class="input-group-text">Select system</span>

                    <select class="form-control" v-model="systemid" @change="change_system">
                        <option v-for="s,i in system_list" :value="s.id">{{ s.location }}, {{ s.hp_manufacturer }} {{ s.hp_model }}, {{ s.hp_output }} kW</option>
                    </select>

                    <button class="btn btn-primary" @click="next_system(-1)"><</button>
                            <button class="btn btn-primary" @click="next_system(1)">></button>
                </div>
            </div>
        </div>

        <!-- row with x and y axis selection -->
        <div class="row">

            <!-- Y-axis grouped by group -->
            <div class="col-lg-6">
                <div class="input-group mb-3">
                    <span class="input-group-text">Y-axis</span>
                    <select class="form-control" v-model="selected_yaxis" @change="load">
                        <optgroup v-for="(group, group_name) in stats_schema_grouped" :label="group_name">
                            <option v-for="(row,key) in group" :value="key" v-if="row.name">{{ row.name }}</option>
                        </optgroup>
                    </select>
                </div>
            </div>

            <!-- X-axis grouped by group -->
            <div class="col-lg-6">
                <div class="input-group mb-3">
                    <span class="input-group-text">X-axis</span>
                    <select class="form-control" v-model="selected_xaxis" @change="load">
                        <optgroup v-for="(group, group_name) in stats_schema_grouped" :label="group_name">
                            <option v-for="(row,key) in group" :value="key" v-if="row.name">{{ row.name }}</option>
                        </optgroup>
                    </select>
                </div>
            </div>
            
        </div>

        <div class="row" style="margin-right:-5px">
            <div id="placeholder" style="width:100%;height:600px; margin-bottom:20px"></div>
        </div>

        <!-- min max y axis -->
        <div class="row">
            <div class="col-lg-3">
                <div class="input-group mb-3">
                    <span class="input-group-text">Min Y-axis</span>
                    <input type="text" class="form-control" v-model="min_yaxis" @change="draw">
                </div>
            </div>
            <div class="col-lg-3">
                <div class="input-group mb-3">
                    <span class="input-group-text">Max Y-axis</span>
                    <input type="text" class="form-control" v-model="max_yaxis" @change="draw">
                </div>
            </div>
            <div class="col-lg-3">
                <div class="input-group mb-3">
                    <span class="input-group-text">Min X-axis</span>
                    <input type="text" class="form-control" v-model="min_xaxis" @change="draw">
                </div>
            </div>
            <div class="col-lg-3">
                <div class="input-group mb-3">
                    <span class="input-group-text">Max X-axis</span>
                    <input type="text" class="form-control" v-model="max_xaxis" @change="draw">
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var userid = <?php echo $userid; ?>;
    var systemid = <?php echo $systemid; ?>;
    var stats_schema = <?php echo json_encode($stats_schema); ?>;

    // Group stats schema by group
    var stats_schema_grouped = {};
    for (var key in stats_schema) {
        var group = stats_schema[key].group;
        if (!stats_schema_grouped[group]) {
            stats_schema_grouped[group] = {};
        }
        stats_schema_grouped[group][key] = stats_schema[key];
    }

    var mode = "combined";

    var hp_output = 0;
    var heat_loss = 0;
    var hp_max = 0;
    var data = [];
    var series = [];
    var systemid_map = {};
    var max_heat = 0;

    var selected_xaxis = 'running_outsideT_mean';
    var selected_yaxis = 'running_flowT_mean';
    var min_yaxis = 'auto';
    var max_yaxis = 'auto';
    var min_xaxis = 'auto';
    var max_xaxis = 'auto';

    // Get URL parameters
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('x')) {
        selected_xaxis = urlParams.get('x');
    }
    if (urlParams.has('y')) {
        selected_yaxis = urlParams.get('y');
    }
    if (urlParams.has('min_yaxis')) {
        min_yaxis = urlParams.get('min_yaxis');
    }
    if (urlParams.has('max_yaxis')) {
        max_yaxis = urlParams.get('max_yaxis');
    }
    if (urlParams.has('min_xaxis')) {
        min_xaxis = urlParams.get('min_xaxis');
    }
    if (urlParams.has('max_xaxis')) {
        max_xaxis = urlParams.get('max_xaxis');
    }

    var app = new Vue({
        el: '#app',
        data: {
            enable_save: false,
            systemid: systemid,
            system_list: {},
            stats_schema: stats_schema,
            stats_schema_grouped: stats_schema_grouped,
            // Selection
            selected_xaxis: selected_xaxis,
            selected_yaxis: selected_yaxis,
            // Y-axis
            min_yaxis: min_yaxis,
            max_yaxis: max_yaxis,
            // X-axis
            min_xaxis: min_xaxis,
            max_xaxis: max_xaxis,
        },
        methods: {
            change_system: function() {
                app.fixed_room_tmp_enable = 0;
                load();
            },
            load: function() {
                load();
            },
            next_system: function(direction) {
                app.fixed_room_tmp_enable = 0;
                var z = systemid_map[app.systemid] * 1;
                z += direction;
                if (z >= 0 && z < app.system_list.length) {
                    app.systemid = app.system_list[z].id;

                    load();
                }
            }
        },
        filters: {
            toFixed: function(value, dp) {
                if (!value) value = 0;
                return value.toFixed(dp);
            }
        }
    });

    // Load list of systems
    $.ajax({
        type: "GET",
        url: path + "system/list/public.json",
        success: function(result) {

            // sort by location
            result.sort(function(a, b) {
                // sort by location string
                if (a.location < b.location) return -1;
                if (a.location > b.location) return 1;
                return 0;
            });

            // System list by id
            app.system_list = result;

            for (var z in result) {
                systemid_map[result[z].id] = z;
            }

            load();
            resize();
        }
    });

    function load() {

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

        var z = systemid_map[app.systemid];

        var fields = [
            'timestamp',
            mode + '_heat_mean',
            mode + '_roomT_mean',
            mode + '_outsideT_mean',
            'combined_starts',
            'running_flowT_mean',
            'running_returnT_mean',
            'combined_elec_kwh',
            'combined_heat_kwh',
        ];

        $.ajax({
            // text plain dataType:
            dataType: "text",
            url: path + "system/stats/daily",
            data: {
                'id': app.systemid
                // 'start': 1,
                // 'end': 2,
                // 'fields': fields.join(',')
            },
            async: true,
            success: function(result) {
                // split
                var lines = result.split('\n');

                var fields = lines[0].split(',');
                for (var z in fields) {
                    fields[z] = fields[z].trim();
                }
                
                // create data
                for (var z in fields) {
                     let key = fields[z];
                     data[key] = [];
                }

                for (var i = 1; i < lines.length; i++) {
                    var parts = lines[i].split(',');
                    if (parts.length != fields.length) {
                        continue;
                    }

                    var timestamp = parts[1] * 1000;

                    for (var j = 1; j < parts.length; j++) {
                        let value = parts[j] * 1;
                        // add to data
                        data[fields[j]].push([timestamp, value]);
                    }
                }

                data['series'] = [];
                for (var i = 0; i < data[app.selected_xaxis].length; i++) {
                    var x = data[app.selected_xaxis][i][1];
                    var y = data[app.selected_yaxis][i][1];
                    if (x===0 && y===0) continue;
                    data['series'].push([x, y, i]);
                }
                draw();
            }
        });
    }

    function draw() {

        // Save selected_xaxis and selected_yaxis to URL

        var newurl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?id=' + app.systemid;

        if (app.selected_xaxis != 'running_outsideT_mean') {
            newurl += '&x=' + app.selected_xaxis;
        }

        if (app.selected_yaxis != 'running_flowT_mean') {
            newurl += '&y=' + app.selected_yaxis;
        }

        if (app.min_yaxis != 'auto') {
            newurl += '&min_yaxis=' + app.min_yaxis;
        }
        if (app.max_yaxis != 'auto') {
            newurl += '&max_yaxis=' + app.max_yaxis;
        }
        if (app.min_xaxis != 'auto') {
            newurl += '&min_xaxis=' + app.min_xaxis;
        }
        if (app.max_xaxis != 'auto') {
            newurl += '&max_xaxis=' + app.max_xaxis;
        }
        window.history.pushState({ path: newurl }, '', newurl);

        var xaxis_label = stats_schema[app.selected_xaxis].group.replace("Stats: ","") + ": " + stats_schema[app.selected_xaxis].name;
        if (stats_schema[app.selected_xaxis].unit != "") xaxis_label += " (" + stats_schema[app.selected_xaxis].unit + ")";

        var yaxis_label = stats_schema[app.selected_yaxis].group.replace("Stats: ","") + ": " + stats_schema[app.selected_yaxis].name;
        if (stats_schema[app.selected_yaxis].unit != "") yaxis_label += " (" + stats_schema[app.selected_yaxis].unit + ")";

        // Flot options
        var options = {
            series: {},
            xaxis: {
                axisLabel: xaxis_label,
                // max: app.design_DT
            },
            yaxis: {
                // min: 0,
                // max: max_heat * 1.1,
                axisLabel: yaxis_label
            },
            grid: {
                hoverable: true,
                clickable: true
            },
            axisLabels: {
                show: true
            }
        };

        if (app.min_yaxis != 'auto') {
            options.yaxis.min = app.min_yaxis * 1;
        }
        if (app.max_yaxis != 'auto') {
            options.yaxis.max = app.max_yaxis * 1;
        }
        if (app.min_xaxis != 'auto') {
            options.xaxis.min = app.min_xaxis * 1;
        }
        if (app.max_xaxis != 'auto') {
            options.xaxis.max = app.max_xaxis * 1;
        }


        var series = [{
            data: data['series'],

            color: 'blue',
            lines: {
                show: false,
                fill: false
            },
            points: {
                show: true,
                radius: 2
            }
        }];

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
                str += "X: " + x.toFixed(1) + "<br>";
                str += "Y: " + y.toFixed(1) + "<br>";

                var original_index = data['series'][item.dataIndex][2];

                str += "Room: " + data[mode + '_roomT_mean'][original_index][1].toFixed(1) + " °C<br>";
                str += "Outside: " + data[mode + '_outsideT_mean'][original_index][1].toFixed(1) + " °C<br>";
                str += "FlowT: " + data['running_flowT_mean'][original_index][1].toFixed(1) + " °C<br>";
                str += "ReturnT: " + data['running_returnT_mean'][original_index][1].toFixed(1) + " °C<br>";

                var d = new Date(data[mode + '_heat_mean'][original_index][0]);
                str += d.getDate() + " " + d.toLocaleString('default', {
                    month: 'short'
                }) + " " + d.getFullYear() + "<br>";

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
