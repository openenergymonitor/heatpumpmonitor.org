<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.3/jquery.flot.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.3/jquery.flot.time.min.js"></script>
<script src="Lib/jquery.flot.axislabels.js"></script>

<div id="app">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container-fluid">
            <h3>Heat demand explorer</h3>
        </div>
    </div>

    <div class="container-fluid" style="margin-top:20px; max-width:1320px">
        <!-- row and 4 columns -->
        <div class="row">
            <div class="col-lg-6">
                <div class="input-group mb-3">
                    <span class="input-group-text">Select system</span>

                    <select class="form-control" v-model="systemid" @change="change_system">
                        <option v-for="s,i in system_list" :value="s.id">{{ s.location }}, {{ s.hp_model }}, {{ s.hp_output }} kW</option>
                    </select>

                    <button class="btn btn-primary" @click="next_system(-1)"><</button>
                            <button class="btn btn-primary" @click="next_system(1)">></button>
                </div>
            </div>
            <div class="col-lg-2 col-sm-4 col-6">
                <div class="input-group mb-3">
                    <span class="input-group-text">Elec</span>
                    <input type="text" class="form-control" :value="total_elec_kwh | toFixed(0)" disabled>
                    <span class="input-group-text">kWh</span>
                </div>
            </div>
            <div class="col-lg-2 col-sm-4 col-6">
                <div class="input-group mb-3">
                    <span class="input-group-text">Heat</span>
                    <input type="text" class="form-control" :value="total_heat_kwh | toFixed(0)" disabled>
                    <span class="input-group-text">kWh</span>
                </div>
            </div>
            <div class="col-lg-2 col-sm-4 col-12">
                <div class="input-group mb-3">
                    <span class="input-group-text">COP</span>
                    <input type="text" class="form-control" :value="total_cop | toFixed(2)" disabled>
                </div>
            </div>
        </div>

        <div class="row" style="margin-right:-5px">
            <div id="placeholder" style="width:100%;height:600px; margin-bottom:20px"></div>
        </div>

        <div class="row mb-3">
            <div class="col-lg-3 col-md-6">
                <div class="input-group mb-3">
                    <span class="input-group-text">Base DT</span>
                    <input type="text" class="form-control" v-model.number="base_DT" @change="update_fit">
                    <span class="input-group-text">°K</span>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="input-group mb-3">
                    <span class="input-group-text">Design DT</span>
                    <input type="text" class="form-control" v-model.number="design_DT" @change="update_fit">
                    <span class="input-group-text">°K</span>
                </div>
            </div>


            <div class="col-lg-3 col-md-6">
                <div class="input-group mb-3">
                    <span class="input-group-text">Heat demand</span>
                    <input type="text" class="form-control" v-model.number="measured_heatloss" @change="update_fit">
                    <span class="input-group-text">kW</span>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="input-group">
                    <span class="input-group-text">&plusmn;</span>
                    <input type="text" class="form-control" v-model.number="measured_heatloss_range" @change="update_fit">
                    <span class="input-group-text">kW</span>
                    <button class="btn btn-primary" @click="save_heat_loss" v-if="enable_save">Save</button>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col">
                <div class="input-group">
                    <span class="input-group-text">Filter out below</span>
                    <input type="text" class="form-control" v-model.number="auto_min_DT" @change="update_fit">
                    <span class="input-group-text">°K DT</span>
                    <button class="btn btn-primary" @click="auto_fit">Auto fit</button>
                </div>  
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-lg-4 col-md-6">
                <div class="input-group mb-3">
                    <span class="input-group-text">Calculated heat loss</span>
                    <input type="text" class="form-control" v-model.number="calculated_heatloss" :disabled="!enable_save" @change="update_fit">
                    <span class="input-group-text">kW</span>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="input-group mb-3">
                    <span class="input-group-text">Heat pump datasheet capacity</span>
                    <input type="text" class="form-control" v-model.number="datasheet_hp_max" :disabled="!enable_save" @change="update_fit">
                    <span class="input-group-text">kW</span>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="input-group mb-3">
                    <span class="input-group-text">Max capacity test result</span>
                    <input type="text" class="form-control" v-model.number="measured_hp_max" :disabled="!enable_save" @change="update_fit">
                    <span class="input-group-text">kW</span>
                    <button class="btn btn-primary" @click="save_capacity_figures" v-if="enable_save">Save</button>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-lg-4 col-md-6">
                <div class="input-group mb-3">
                    <span class="input-group-text">Fixed room temperature</span>
                    <span class="input-group-text"><input type="checkbox" v-model="fixed_room_tmp_enable" @change="change_system" /></span>
                    <input type="text" class="form-control" v-model.number="fixed_room_tmp" @change="change_system">
                    <span class="input-group-text">°C</span>
                </div>
            </div>
        </div>

        <div class="row">


            <p>Each datapoint shows the average heat output over a 24 hour period. Hover over data point for more information.</p>

            <p><b>Note:</b> The measured heat output shown here is the combined heat output of space heating and hot water.
                While it doesnt compare directly to the heat loss of the building, it is a good indicator of the heat demand from the heat pump.
                Technically the space heating demand from a heat pump should be the calculated heat loss of the building minus gains. These gains
                are not taken into account in the sizing of heat pumps when following the BS EN 12831:2003 simplified calculation method that most
                heat loss tools use but are at least in a typical 100m2 house not that different from the average heat demand for hot water and
                so could be viewed as cancelling out.
            </p>
        </div>
    </div>
</div>

<script>
    var userid = <?php echo $userid; ?>;
    var systemid = <?php echo $systemid; ?>;

    var mode = "combined";

    var hp_output = 0;
    var heat_loss = 0;
    var hp_max = 0;
    var data = [];
    var series = [];
    var systemid_map = {};
    var max_heat = 0;

    var app = new Vue({
        el: '#app',
        data: {
            enable_save: false,
            systemid: systemid,
            system_list: {},
            total_elec_kwh: 0,
            total_heat_kwh: 0,
            total_cop: 0,
            base_DT: 4,
            design_DT: 23,
            measured_heatloss: 0,
            measured_heatloss_range: 500,
            auto_min_DT: 0,
            calculated_heatloss: 0,
            datasheet_hp_max: 0,
            measured_hp_max: '',
            fixed_room_tmp_enable: 0,
            fixed_room_tmp: 20

        },
        methods: {
            change_system: function() {
                load();
            },
            update_fit: function() {
                draw();
            },
            save_heat_loss: function() {

                var z = systemid_map[app.systemid];
                app.system_list[z].measured_base_DT = app.base_DT;
                app.system_list[z].measured_design_DT = app.design_DT;
                app.system_list[z].measured_heat_loss = app.measured_heatloss;
                app.system_list[z].measured_heat_loss_range = app.measured_heatloss_range;

                var data_to_save =  {
                    id: app.systemid,
                    data: {
                        measured_base_DT: app.base_DT,
                        measured_design_DT: app.design_DT,
                        measured_heat_loss: app.measured_heatloss,
                        measured_heat_loss_range: app.measured_heatloss_range
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
                    }
                });
            },
            save_capacity_figures: function () {

                var z = systemid_map[app.systemid];
                app.system_list[z].hp_max_output = app.datasheet_hp_max;
                app.system_list[z].heat_loss = app.calculated_heatloss;
                app.system_list[z].hp_max_output_test = app.measured_hp_max;
                
                var data_to_save = {
                    id: app.systemid,
                    data: {
                        hp_max_output: app.datasheet_hp_max,
                        heat_loss: app.calculated_heatloss,
                        hp_max_output_test: app.measured_hp_max
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
                    }
                });
            },
            next_system: function(direction) {
                var z = systemid_map[app.systemid] * 1;
                z += direction;
                if (z >= 0 && z < app.system_list.length) {
                    app.systemid = app.system_list[z].id;
                    load();
                }
            },
            auto_fit: function() {
                var fit = calculateLineOfBestFit(data['heat_vs_dt'],app.auto_min_DT);

                app.design_DT = 23;
                app.measured_heatloss = (fit.m * app.design_DT) + fit.b;
                app.measured_heatloss = app.measured_heatloss.toFixed(2)*1;

                app.base_DT = (0 - fit.b) / fit.m
                app.base_DT = app.base_DT.toFixed(1) * 1;

                if (app.base_DT < 0) {
                    var fit = calculateSlopeWithZeroIntercept(data['heat_vs_dt']);
                    app.base_DT = 0;
                    app.measured_heatloss = (fit * app.design_DT);
                    app.measured_heatloss = app.measured_heatloss.toFixed(2)*1;

                }

                draw();
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

        hp_output = app.system_list[z].hp_output;
        heat_loss = app.system_list[z].heat_loss;
        hp_max = app.system_list[z].hp_max_output;

        app.measured_heatloss = app.system_list[z].measured_heat_loss;
        app.measured_heatloss_range = app.system_list[z].measured_heat_loss_range;
        app.calculated_heatloss = app.system_list[z].heat_loss;
        app.datasheet_hp_max = app.system_list[z].hp_max_output;
        app.measured_hp_max = app.system_list[z].hp_max_output_test;

        app.base_DT = app.system_list[z].measured_base_DT;
        app.design_DT = app.system_list[z].measured_design_DT;

        if (app.measured_heatloss == 0 && app.base_DT == 0 && app.design_DT == 0) {
            app.measured_heatloss = 0;
            app.base_DT = 4;
            app.design_DT = 23;
            app.measured_heatloss_range = 0.5;
        }

        


        var fields = [
            'timestamp',
            mode + '_heat_mean',
            mode + '_roomT_mean',
            mode + '_outsideT_mean',
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
                'id': app.systemid,
                'start': 1,
                'end': 2,
                'fields': fields.join(',')
            },
            async: true,
            success: function(result) {
                // split
                var lines = result.split('\n');

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

                    var timestamp = parts[0] * 1000;

                    for (var j = 1; j < parts.length; j++) {
                        let value = parts[j] * 1;
                        // add to data
                        data[fields[j]].push([timestamp, value]);
                    }
                }

                // Apply fixed room temperature
                if (app.fixed_room_tmp_enable) {
                    for (var i = 0; i < data[mode + '_roomT_mean'].length; i++) {
                        data[mode + '_roomT_mean'][i][1] = app.fixed_room_tmp;
                    }
                }

                // Create series with room - outside x-axis and heatpump heat output y-axis
                max_heat = app.calculated_heatloss;
                if (max_heat < hp_output) max_heat = hp_output;
                if (max_heat < hp_max) max_heat = hp_max;

                var total_elec_kwh = 0;
                var total_heat_kwh = 0;

                data['heat_vs_dt'] = [];
                for (var i = 0; i < data[mode + '_heat_mean'].length; i++) {
                    if (data[mode + '_roomT_mean'][i][1] > 0) {
                        var x = data[mode + '_roomT_mean'][i][1] - data[mode + '_outsideT_mean'][i][1];
                        if (x > 0) {
                            var y = data[mode + '_heat_mean'][i][1]*0.001;
                            data['heat_vs_dt'].push([x, y, i]);

                            if (y > max_heat) max_heat = y;
                        }

                        total_elec_kwh += data['combined_elec_kwh'][i][1];
                        total_heat_kwh += data['combined_heat_kwh'][i][1];
                    }
                }

                app.total_elec_kwh = total_elec_kwh;
                app.total_heat_kwh = total_heat_kwh;
                app.total_cop = total_heat_kwh / total_elec_kwh;

                draw();
            }
        });
    }

    function draw() {

        console.log("Draw")

        // Flot options
        var options = {
            series: {},
            xaxis: {
                axisLabel: 'Room - Outside Temperature',
                max: app.design_DT
            },
            yaxis: {
                min: 0,
                max: max_heat * 1.1,
                axisLabel: 'Heatpump heat output (kW)'
            },
            grid: {
                hoverable: true,
                clickable: true
            },
            axisLabels: {
                show: true
            }
        };

        var series = [{
            data: data['heat_vs_dt'],

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

        // Add horizontal line for heat loss
        series.push({
            data: [
                [0, app.calculated_heatloss],
                [app.design_DT, app.calculated_heatloss]
            ],
            color: 'grey',
            lines: {
                show: true,
                fill: false
            },
            points: {
                show: false
            }
        });

        // Add horizontal line for heatpump output
        series.push({
            data: [
                [0, hp_output],
                [app.design_DT, hp_output]
            ],
            color: 'black',
            lines: {
                show: true,
                fill: false
            },
            points: {
                show: false
            }
        });

        // Add horizontal line for heatpump output
        if (app.datasheet_hp_max > 0) {
            series.push({
                data: [
                    [0, app.datasheet_hp_max],
                    [app.design_DT, app.datasheet_hp_max]
                ],
                color: '#aa0000',
                lines: {
                    show: true,
                    fill: false
                },
                points: {
                    show: false
                }
            });
        }

        // Add horizontal line for heatpump output
        if (app.measured_hp_max > 0) {
            series.push({
                data: [
                    [0, app.measured_hp_max],
                    [app.design_DT, app.measured_hp_max]
                ],
                color: '#ddaaaa',
                lines: {
                    show: true,
                    fill: false
                },
                points: {
                    show: false
                }
            });
        }

        // Add measured heat loss line
        if (app.measured_heatloss > 0) {
            series.push({
                data: [
                    [app.base_DT, 0],
                    [app.design_DT, app.measured_heatloss]
                ],
                color: '#aaa',
                lines: {
                    show: true,
                    fill: false
                },
                points: {
                    show: false
                }
            });

            app.measured_heatloss_range = app.measured_heatloss_range*1;


            
            series.push({
                data: [
                    [app.base_DT, app.measured_heatloss_range],
                    [app.design_DT, app.measured_heatloss + app.measured_heatloss_range]
                ],
                color: '#ddd',
                lines: {
                    show: true,
                    fill: false
                },
                points: {
                    show: false
                }
            });

            series.push({
                data: [
                    [app.base_DT, -app.measured_heatloss_range],
                    [app.design_DT, app.measured_heatloss - app.measured_heatloss_range]
                ],
                color: '#ddd',
                lines: {
                    show: true,
                    fill: false
                },
                points: {
                    show: false
                }
            });
        }

        var chart = $.plot("#placeholder", series, options);

        var placeholder = $("#placeholder");
        var o = false;

        if (hp_output>0) {
            o = chart.pointOffset({ x: 0, y: hp_output });
            placeholder.append("<div style='position:absolute;left:" + (o.left + 4) + "px;top:" + (o.top - 23) + "px;color:#666;font-size:smaller'>Heatpump badge capacity</div>");
        }
        if (app.datasheet_hp_max > 0) {
            o = chart.pointOffset({ x: 0, y: app.datasheet_hp_max });
            let offset = -23;
            if ((hp_output - app.datasheet_hp_max)<0.5) offset = 5;
            console.log(hp_output - app.datasheet_hp_max);
            placeholder.append("<div style='position:absolute;left:" + (o.left + 4) + "px;top:" + (o.top + offset) + "px;color:#666;font-size:smaller'>Heatpump datasheet capacity</div>");
        }
        if (app.measured_hp_max > 0) {
            o = chart.pointOffset({ x: 0, y: app.measured_hp_max });
            placeholder.append("<div style='position:absolute;left:" + (o.left + 4) + "px;top:" + (o.top - 23) + "px;color:#666;font-size:smaller'>Max capacity test result</div>");
        }
        if (app.calculated_heatloss>0) {
            o = chart.pointOffset({ x: 0, y: app.calculated_heatloss });
            placeholder.append("<div style='position:absolute;left:" + (o.left + 4) + "px;top:" + (o.top - 23) + "px;color:#666;font-size:smaller'>Heat loss value on form</div>");
        }
    }

    // Flot tooltip
    var previousPoint = null;
    $("#placeholder").bind("plothover", function(event, pos, item) {
        if (item) {
            if (previousPoint != item.datapoint) {
                previousPoint = item.datapoint;

                $("#tooltip").remove();
                var DT = item.datapoint[0];
                var HEAT = item.datapoint[1];

                var str = "";
                str += "Heat: " + HEAT.toFixed(3) + " kW<br>";
                str += "DT: " + DT.toFixed(1) + " °K<br>";

                var original_index = data['heat_vs_dt'][item.dataIndex][2];

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

    function calculateLineOfBestFit(dataPoints, min_x) {
        let xSum = 0,
            ySum = 0,
            xySum = 0,
            xxSum = 0;
        const n = dataPoints.length;

        // Calculate sums
        for (const [x, y] of dataPoints) {
            if (x >= min_x) {
                xSum += x;
                ySum += y;
                xxSum += x * x;
                xySum += x * y;
            }
        }

        // Calculate slope (m) and y-intercept (b) for y = mx + b
        const m = (n * xySum - xSum * ySum) / (n * xxSum - xSum * xSum);
        const b = (ySum - m * xSum) / n;

        return {
            m,
            b
        };
    }

    function calculateSlopeWithZeroIntercept(dataPoints) {
        let xySum = 0,
            xxSum = 0;

        // Calculate sums
        for (const [x, y] of dataPoints) {
            xxSum += x * x;
            xySum += x * y;
        }

        // Calculate slope (m) for y = mx
        const m = xySum / xxSum;

        return m; // intercept (b) is implicitly 0
    }
</script>
