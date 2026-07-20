<?php
defined('EMONCMS_EXEC') or die('Restricted access');
global $session, $path;
$admin = 0;
if (isset($session['admin']) && $session['admin']) {
    $admin = 1;
}
?>
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>

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
                        <option v-for="s,i in system_list" :value="s.id">{{ s.location }}, {{ s.hp_manufacturer }} {{ s.hp_model }}, {{ s.hp_output }} kW</option>
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
            <div id="chart_wrap" style="width:100%;height:600px; margin-bottom:20px; position:relative">
                <canvas id="placeholder"></canvas>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-lg-4 col-md-6">
                <div class="input-group">
                    <span class="input-group-text">Colour points by</span>
                    <select class="form-control" v-model="colour_by" @change="update_fit">
                        <option value="">None</option>
                        <option value="running_flowT_mean">Flow temperature</option>
                        <option value="running_returnT_mean">Return temperature</option>
                        <option value="outsideT">Outside temperature</option>
                        <option value="roomT">Room temperature</option>
                    </select>
                </div>
            </div>
            <div class="col-lg-8 col-md-6" v-show="colour_by">
                <div style="display:flex; align-items:center; gap:8px; height:100%">
                    <span style="white-space:nowrap; color:#666">{{ colour_label }}</span>
                    <span style="white-space:nowrap">{{ colour_min | toFixed(1) }}°C</span>
                    <div id="colour_scale_bar" style="flex:1; height:16px; border:1px solid #ccc; border-radius:3px"></div>
                    <span style="white-space:nowrap">{{ colour_max | toFixed(1) }}°C</span>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- alert danger -->
            <div class="alert alert-danger" role="alert" v-if="room_temp_alert.length>0">
                {{ room_temp_alert }}
            </div>
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
                    <span class="input-group-text" title="Automated 80% prediction interval, evaluated at design DT">&plusmn; 80% PI</span>
                    <input type="text" class="form-control" :value="measured_heatloss_range | toFixed(2)" disabled>
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
                    <span class="input-group-text"><input type="checkbox" v-model="fixed_room_tmp_enable" @change="load" /></span>
                    <input type="text" class="form-control" v-model.number="fixed_room_tmp" @change="load">
                    <span class="input-group-text">°C</span>
                </div>
            </div>
        </div>

        <div class="row">


            <p>Each datapoint shows the average heat output over a 24 hour period. Hover over data point for more information, click on a data point to open the dashboard view for that day.</p>

            <p><b>Note:</b> The measured heat output shown here is the combined heat output of space heating and hot water.
                While it doesnt compare directly to the heat loss of the building, it is a good indicator of the heat demand from the heat pump.
                Technically the space heating demand from a heat pump should be the calculated heat loss of the building minus gains. These gains
                are not taken into account in the sizing of heat pumps when following the BS EN 12831:2003 simplified calculation method that most
                heat loss tools use but are at least in a typical 100m2 house not that different from the average heat demand for hot water and
                so could be viewed as cancelling out.
            </p>
        </div>
        <div style="text-align:center; padding:20px 0">
          <a href="<?php echo $path; ?>system/view?id=<?php echo $systemid; ?>" class="btn btn-primary">Back to System</a>
        </div>
    </div>
</div>

<script>
    var userid = <?php echo $userid; ?>;
    var systemid = <?php echo $systemid; ?>;
    var admin = <?php echo $admin; ?>;

    var mode = "combined";

    var hp_output = 0;
    var heat_loss = 0;
    var hp_max = 0;
    var data = [];
    var series = [];
    var systemid_map = {};
    var max_heat = 0;
    var user_mode = false;

    var app = new Vue({
        el: '#app',
        data: {
            enable_save: false,
            systemid: systemid,
            system_list: {},
            total_elec_kwh: 0,
            total_heat_kwh: 0,
            total_cool_kwh: 0,
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
            fixed_room_tmp: 20,
            room_temp_alert: "",
            colour_by: "",
            colour_min: 0,
            colour_max: 0

        },
        computed: {
            colour_label: function() {
                var labels = {
                    'running_flowT_mean': 'Flow temperature (°C)',
                    'running_returnT_mean': 'Return temperature (°C)',
                    'outsideT': 'Outside temperature (°C)',
                    'roomT': 'Room temperature (°C)'
                };
                return labels[this.colour_by] || '';
            }
        },
        methods: {
            change_system: function() {
                app.fixed_room_tmp_enable = 0;
                load();
            },
            load: function() {
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
                app.fixed_room_tmp_enable = 0;
                var z = systemid_map[app.systemid] * 1;
                z += direction;
                if (z >= 0 && z < app.system_list.length) {
                    app.systemid = app.system_list[z].id;

                    // Update ?id= in URL
                    var url = new URL(window.location.href);
                    url.searchParams.set('id', app.systemid);
                    window.history.pushState({}, '', url);


                    load();
                }
            },
            auto_fit: function() {
                var fit = calculateRobustFit(data['heat_vs_dt'], app.auto_min_DT, 2.5, 3);

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



    load_system_list();

    // Load list of systems
    function load_system_list() {

        let system_list_url = path + "system/list/public.json";

        if (user_mode) {
            system_list_url = path + "system/list/user.json";
        }

        if (admin) {
            system_list_url = path + "system/list/admin.json";
        }

        $.ajax({
            type: "GET",
            url: system_list_url,
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
    }

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
        if (z === undefined) {
            // switch to user mode
            user_mode = true;
            load_system_list();
            return;
        }

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
            'combined_data_length',
            'cooling_heat_kwh'
        ];

        $.ajax({
            // text plain dataType:
            dataType: "text",
            url: path + "system/stats/daily",
            data: {
                'id': app.systemid,
                //'start': 1,
                //'end': 2,
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

                // Detect if we have valid room temperature data
                var valid_room_temp = 0;
                for (var i = 0; i < data[mode + '_roomT_mean'].length; i++) {
                    if (data[mode + '_roomT_mean'][i][1] > 0) {
                        valid_room_temp = 1;
                        break;
                    }
                }
                // auto enable fixed room temp if no room temp data
                if (valid_room_temp == 0) {
                    app.fixed_room_tmp_enable = 1;
                    app.room_temp_alert = "No room temperature data found, fixed room temperature enabled\nSet fixed room temperature in the box below (default 20°C)";
                } else {
                    app.room_temp_alert = "";
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
                var total_cool_kwh = 0;

                data['heat_vs_dt'] = [];
                data['cool_vs_dt'] = [];
                for (var i = 0; i < data[mode + '_heat_mean'].length; i++) {
                    if (data[mode + '_roomT_mean'][i][1] > 0 && data[mode + '_data_length'][i][1] > 64800) {

                        // Filter out invalid points where the heat pump wasn't running:
                        // flow and return temperature both zero (heat output is spurious ~0)
                        if (data['running_flowT_mean'][i][1] == 0 && data['running_returnT_mean'][i][1] == 0) {
                            continue;
                        }

                        var x = data[mode + '_roomT_mean'][i][1] - data[mode + '_outsideT_mean'][i][1];
                        //if (x > 0) {
                            // Convert heat from W to kW
                            var y = data[mode + '_heat_mean'][i][1]*0.001;

                            // Check if cooling is present and subtract from heat output
                            var cool = null;
                            if (data['cooling_heat_kwh'][i][1] > 0) {
                                cool = data['cooling_heat_kwh'][i][1] / 24.0;
                            }
                            // Subtract cooling from heat output if cooling is present
                            if (cool !== null) y -= cool;

                            // Add to series
                            data['heat_vs_dt'].push([x, y, i]);
                            data['cool_vs_dt'].push([x, cool, i]);

                            if (y > max_heat) max_heat = y;
                        //}

                        total_elec_kwh += data['combined_elec_kwh'][i][1];
                        total_heat_kwh += data['combined_heat_kwh'][i][1];
                        total_cool_kwh += data['cooling_heat_kwh'][i][1];
                    }
                }

                app.total_elec_kwh = total_elec_kwh;
                app.total_heat_kwh = total_heat_kwh;
                app.total_cool_kwh = total_cool_kwh;
                app.total_cop = total_heat_kwh / total_elec_kwh;

                draw();
            }
        });
    }

    var chart = null;

    // Map a colour-by token to the underlying data field name
    function colour_field_name(token) {
        if (token === 'outsideT') return mode + '_outsideT_mean';
        if (token === 'roomT') return mode + '_roomT_mean';
        return token; // running_flowT_mean, running_returnT_mean
    }

    // Map a normalised value t in [0,1] to a colour (blue = low, red = high)
    function colour_scale(t) {
        if (t < 0) t = 0;
        if (t > 1) t = 1;
        var hue = 240 * (1 - t); // 240 = blue, 0 = red
        return 'hsl(' + hue + ', 70%, 50%)';
    }

    // CSS gradient string matching colour_scale, for the legend bar
    function colour_scale_css() {
        var stops = [];
        for (var s = 0; s <= 10; s++) {
            stops.push(colour_scale(s / 10) + ' ' + (s * 10) + '%');
        }
        return 'linear-gradient(to right, ' + stops.join(', ') + ')';
    }

    // Reference-line labels, drawn by the custom plugin below
    var ref_labels = [];

    // Custom plugin: draw text labels next to the reference lines
    var refLabelPlugin = {
        id: 'reflabels',
        afterDatasetsDraw: function(c) {
            var ctx = c.ctx;
            var xs = c.scales.x, ys = c.scales.y;
            ctx.save();
            ctx.fillStyle = '#666';
            ctx.font = '13px sans-serif';
            ctx.textBaseline = 'bottom';
            for (var k = 0; k < ref_labels.length; k++) {
                var L = ref_labels[k];
                ctx.fillText(L.text, xs.getPixelForValue(L.x) + 4, ys.getPixelForValue(L.y) + (L.dy || 0));
            }
            ctx.restore();
        }
    };

    // Build a horizontal reference-line dataset
    function ref_line(min_dt, y, color, width) {
        return {
            data: [{ x: min_dt, y: y }, { x: app.design_DT, y: y }],
            borderColor: color,
            borderWidth: width || 1,
            pointRadius: 0,
            pointHitRadius: 0,
            showLine: true,
            fill: false,
            series_type: 'line'
        };
    }

    // Soft drop shadow under the reference/fit lines (matches the Flot look)
    var lineShadowPlugin = {
        id: 'lineshadow',
        beforeDatasetDraw: function(c, args) {
            var ds = c.data.datasets[args.index];
            if (ds && ds.series_type === 'line') {
                c.ctx.save();
                c.ctx.shadowColor = 'rgba(0,0,0,0.25)';
                c.ctx.shadowBlur = 2;
                c.ctx.shadowOffsetX = 0;
                c.ctx.shadowOffsetY = 2;
            }
        },
        afterDatasetDraw: function(c, args) {
            var ds = c.data.datasets[args.index];
            if (ds && ds.series_type === 'line') {
                c.ctx.restore();
            }
        }
    };

    // Full bounding box around the plot area (matches the Flot look)
    var boxPlugin = {
        id: 'chartbox',
        afterDraw: function(c) {
            var a = c.chartArea, ctx = c.ctx;
            ctx.save();
            ctx.strokeStyle = '#000';
            ctx.lineWidth = 1.5;
            ctx.strokeRect(a.left, a.top, a.right - a.left, a.bottom - a.top);
            ctx.restore();
        }
    };

    function draw() {

        // Left hand edge of chart: minimum DT in the data (can be negative when cooling), capped at 0
        var min_dt = 0;
        for (var i = 0; i < data['heat_vs_dt'].length; i++) {
            if (data['heat_vs_dt'][i][0] < min_dt) min_dt = data['heat_vs_dt'][i][0];
        }

        // Heat scatter points ({x, y, i} where i is the original data index)
        var heat_points = [];
        for (var i = 0; i < data['heat_vs_dt'].length; i++) {
            var p = data['heat_vs_dt'][i];
            heat_points.push({ x: p[0], y: p[1], i: p[2] });
        }

        // Optionally colour the heat points by a selected field
        var heat_colour = 'blue';
        if (app.colour_by) {
            var cf = colour_field_name(app.colour_by);

            var vmin = Infinity, vmax = -Infinity;
            for (var i = 0; i < heat_points.length; i++) {
                var v = (data[cf] && data[cf][heat_points[i].i]) ? data[cf][heat_points[i].i][1] : NaN;
                if (!isNaN(v)) {
                    if (v < vmin) vmin = v;
                    if (v > vmax) vmax = v;
                }
            }
            if (vmin === Infinity) { vmin = 0; vmax = 1; }
            if (vmax === vmin) vmax = vmin + 1;
            app.colour_min = vmin;
            app.colour_max = vmax;

            heat_colour = [];
            for (var i = 0; i < heat_points.length; i++) {
                var v = (data[cf] && data[cf][heat_points[i].i]) ? data[cf][heat_points[i].i][1] : NaN;
                heat_colour.push(isNaN(v) ? '#cccccc' : colour_scale((v - vmin) / (vmax - vmin)));
            }

            $('#colour_scale_bar').css('background', colour_scale_css());
        }

        // Cooling scatter points
        var cool_points = [];
        for (var i = 0; i < data['cool_vs_dt'].length; i++) {
            var p = data['cool_vs_dt'][i];
            if (p[1] === null) continue;
            cool_points.push({ x: p[0], y: p[1], i: p[2] });
        }

        // Point styling: open rings (Flot look) when uncoloured, filled circles when colour-scaled
        var heat_bg = app.colour_by ? heat_colour : 'white';
        var heat_border = app.colour_by ? heat_colour : 'blue';
        var heat_bw = app.colour_by ? 0.5 : 2;
        var heat_radius = app.colour_by ? 4 : 2;

        var datasets = [{
            data: heat_points,
            pointBackgroundColor: heat_bg,
            pointBorderColor: heat_border,
            pointBorderWidth: heat_bw,
            pointRadius: heat_radius,
            pointHoverRadius: heat_radius + 1.5,
            showLine: false,
            series_type: 'heat'
        }, {
            data: cool_points,
            pointBackgroundColor: 'white',
            pointBorderColor: 'purple',
            pointBorderWidth: 2,
            pointRadius: 2,
            pointHoverRadius: 4,
            showLine: false,
            series_type: 'cooling'
        }];

        // Reference lines and their labels
        ref_labels = [];

        datasets.push(ref_line(min_dt, app.calculated_heatloss, '#808080', 2));
        if (app.calculated_heatloss > 0) ref_labels.push({ x: min_dt, y: app.calculated_heatloss, text: 'Heat loss value on form', dy: -4 });

        datasets.push(ref_line(min_dt, hp_output, '#000000', 2));
        if (hp_output > 0) ref_labels.push({ x: min_dt, y: hp_output, text: 'Heatpump badge capacity', dy: -4 });

        if (app.datasheet_hp_max > 0) {
            datasets.push(ref_line(min_dt, app.datasheet_hp_max, '#aa0000', 2));
            var dy = ((hp_output - app.datasheet_hp_max) < 0.5) ? 14 : -4;
            ref_labels.push({ x: min_dt, y: app.datasheet_hp_max, text: 'Heatpump datasheet capacity', dy: dy });
        }

        if (app.measured_hp_max > 0) {
            datasets.push(ref_line(min_dt, app.measured_hp_max, '#cc8888', 2));
            ref_labels.push({ x: min_dt, y: app.measured_hp_max, text: 'Max capacity test result', dy: -4 });
        }

        // Measured heat loss sloped line with automated 80% prediction interval band
        if (app.measured_heatloss > 0) {

            datasets.push({
                data: [{ x: app.base_DT, y: 0 }, { x: app.design_DT, y: app.measured_heatloss }],
                borderColor: '#888', borderWidth: 2, pointRadius: 0, pointHitRadius: 0,
                showLine: true, fill: false, series_type: 'line'
            });

            // 80% prediction interval about the measured heat loss line
            if (app.design_DT > app.base_DT) {
                var pi_m = app.measured_heatloss / (app.design_DT - app.base_DT);
                var pi_b = -pi_m * app.base_DT;
                var pi = calculatePIStats(data['heat_vs_dt'], pi_m, pi_b, app.auto_min_DT);

                if (pi) {
                    var TCRIT = 1.2816; // z for an 80% two-sided interval (normal approx.)

                    // Report the PI half-width at design DT as the ± range figure
                    app.measured_heatloss_range = piHalfWidth(pi, app.design_DT, TCRIT).toFixed(2) * 1;

                    // The interval widens away from the mean DT, so sample it as a curve
                    var pi_upper = [], pi_lower = [];
                    var PI_STEPS = 40;
                    for (var s2 = 0; s2 <= PI_STEPS; s2++) {
                        var xx = app.base_DT + (app.design_DT - app.base_DT) * (s2 / PI_STEPS);
                        var yy = pi_m * xx + pi_b;
                        var hw = piHalfWidth(pi, xx, TCRIT);
                        pi_upper.push({ x: xx, y: yy + hw });
                        pi_lower.push({ x: xx, y: yy - hw });
                    }
                    datasets.push({
                        data: pi_upper, borderColor: '#c4c4c4', borderWidth: 1.5,
                        pointRadius: 0, pointHitRadius: 0, showLine: true, fill: false, tension: 0, series_type: 'line'
                    });
                    datasets.push({
                        data: pi_lower, borderColor: '#c4c4c4', borderWidth: 1.5,
                        pointRadius: 0, pointHitRadius: 0, showLine: true, fill: false, tension: 0, series_type: 'line'
                    });
                }
            }
        }

        var options = {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            parsing: false,
            scales: {
                x: {
                    type: 'linear',
                    min: min_dt,
                    max: app.design_DT,
                    title: { display: true, text: 'Room - Outside Temperature', color: '#333', font: { size: 14 } },
                    border: { display: false },
                    grid: { color: '#e6e6e6', tickColor: '#e6e6e6' },
                    ticks: {
                        stepSize: 2.5,
                        color: '#333',
                        font: { size: 13 },
                        // Hide the forced min/max endpoint labels, keep the nice 2.5 steps
                        callback: function(v) { var r = v / 2.5; return Math.abs(r - Math.round(r)) < 1e-6 ? v : ''; }
                    }
                },
                y: {
                    min: 0,
                    max: max_heat * 1.1,
                    title: { display: true, text: 'Heatpump heat output (kW)', color: '#333', font: { size: 14 } },
                    border: { display: false },
                    grid: { color: '#e6e6e6', tickColor: '#e6e6e6' },
                    ticks: {
                        stepSize: 1,
                        color: '#333',
                        font: { size: 13 },
                        // Hide the fractional max endpoint label
                        callback: function(v) { return Math.abs(v - Math.round(v)) < 1e-6 ? v : ''; }
                    }
                }
            },
            interaction: { mode: 'nearest', intersect: true },
            plugins: {
                legend: { display: false },
                tooltip: {
                    filter: function(item) {
                        return item.dataset.series_type === 'heat' || item.dataset.series_type === 'cooling';
                    },
                    callbacks: {
                        title: function() { return ''; },
                        label: function(ctx) {
                            var oi = ctx.raw.i;
                            var heat_label = ctx.dataset.series_type === 'cooling' ? 'Cooling' : 'Heat';
                            var lines = [];
                            lines.push(heat_label + ': ' + ctx.raw.y.toFixed(3) + ' kW');
                            lines.push('DT: ' + ctx.raw.x.toFixed(1) + ' °K');
                            lines.push('Room: ' + data[mode + '_roomT_mean'][oi][1].toFixed(1) + ' °C');
                            lines.push('Outside: ' + data[mode + '_outsideT_mean'][oi][1].toFixed(1) + ' °C');
                            lines.push('FlowT: ' + data['running_flowT_mean'][oi][1].toFixed(1) + ' °C');
                            lines.push('ReturnT: ' + data['running_returnT_mean'][oi][1].toFixed(1) + ' °C');
                            var d = new Date(data[mode + '_heat_mean'][oi][0]);
                            lines.push(d.getDate() + ' ' + d.toLocaleString('default', { month: 'short' }) + ' ' + d.getFullYear());
                            return lines;
                        }
                    }
                }
            },
            onClick: function(e, elements, c) {
                var hit = c.getElementsAtEventForMode(e, 'nearest', { intersect: true }, false);
                for (var k = 0; k < hit.length; k++) {
                    var ds = c.data.datasets[hit[k].datasetIndex];
                    if (ds.series_type === 'heat' || ds.series_type === 'cooling') {
                        var oi = ds.data[hit[k].index].i;
                        var start = data[mode + '_heat_mean'][oi][0] / 1000;
                        var end = start + 86400;
                        window.open(path + "dashboard?id=" + app.systemid + "&mode=power&start=" + start + "&end=" + end);
                        return;
                    }
                }
            },
            onHover: function(e, elements, c) {
                var hit = elements.filter(function(el) {
                    var ds = c.data.datasets[el.datasetIndex];
                    return ds.series_type === 'heat' || ds.series_type === 'cooling';
                });
                c.canvas.style.cursor = hit.length ? 'pointer' : 'default';
            }
        };

        if (chart) chart.destroy();
        chart = new Chart(document.getElementById('placeholder'), {
            type: 'scatter',
            data: { datasets: datasets },
            options: options,
            plugins: [lineShadowPlugin, refLabelPlugin, boxPlugin]
        });
    }

    // Window resize
    $(window).resize(function() {
        resize();
    });

    function resize() {
        var width = $("#chart_wrap").width();
        var height = width * 1.2;
        if (height > 600) height = 600;
        $("#chart_wrap").height(height);
        if (chart) chart.resize();
    }

    function calculateLineOfBestFit(dataPoints, min_x) {
        let xSum = 0,
            ySum = 0,
            xySum = 0,
            xxSum = 0,
            n = 0;

        // Calculate sums over the included points only (x >= min_x)
        for (const [x, y] of dataPoints) {
            if (x >= min_x) {
                xSum += x;
                ySum += y;
                xxSum += x * x;
                xySum += x * y;
                n++;
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

    // Least-squares fit with iterative outlier trimming: fit, drop points whose
    // residual exceeds k standard errors, refit; repeat until stable (or maxIter).
    function calculateRobustFit(dataPoints, min_x, k, maxIter) {
        var pts = dataPoints.filter(function(p) { return p[0] >= min_x; });
        if (pts.length < 3) return calculateLineOfBestFit(dataPoints, min_x);

        var fit = calculateLineOfBestFit(pts, -Infinity);

        for (var iter = 0; iter < maxIter; iter++) {
            // Residual standard error about the current line
            var sse = 0;
            for (var i = 0; i < pts.length; i++) {
                var r = pts[i][1] - (fit.m * pts[i][0] + fit.b);
                sse += r * r;
            }
            var s = Math.sqrt(sse / Math.max(1, pts.length - 2));
            if (s <= 0) break;

            // Keep only inliers (within k standard errors)
            var thresh = k * s;
            var kept = pts.filter(function(p) {
                return Math.abs(p[1] - (fit.m * p[0] + fit.b)) <= thresh;
            });

            // Stop if nothing was trimmed, or too few points remain to fit
            if (kept.length === pts.length || kept.length < 3) break;

            pts = kept;
            fit = calculateLineOfBestFit(pts, -Infinity);
        }

        return fit;
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

    // Statistics for a prediction interval about the line y = m*x + b,
    // using heating points with x >= minx. Returns null if too few points.
    function calculatePIStats(dataPoints, m, b, minx) {
        var n = 0, xSum = 0;
        for (var k = 0; k < dataPoints.length; k++) {
            if (dataPoints[k][0] < minx) continue;
            n++;
            xSum += dataPoints[k][0];
        }
        if (n < 3) return null;

        var xbar = xSum / n;
        var Sxx = 0, sse = 0;
        for (var k = 0; k < dataPoints.length; k++) {
            var x = dataPoints[k][0];
            if (x < minx) continue;
            var resid = dataPoints[k][1] - (m * x + b);
            Sxx += (x - xbar) * (x - xbar);
            sse += resid * resid;
        }
        if (Sxx <= 0) return null;

        return { n: n, xbar: xbar, Sxx: Sxx, s: Math.sqrt(sse / (n - 2)) };
    }

    // Prediction-interval half-width at x0 (tcrit = critical value for the desired level)
    function piHalfWidth(stats, x0, tcrit) {
        return tcrit * stats.s * Math.sqrt(1 + 1 / stats.n + (x0 - stats.xbar) * (x0 - stats.xbar) / stats.Sxx);
    }
</script>
