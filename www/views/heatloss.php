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

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-top border-3 border-primary">
                    <div class="card-body text-center">
                        <div class="text-secondary text-uppercase small mb-2">Heat demand*</div>
                        <div class="fs-2 fw-bold" v-if="heatloss_mode != 'manual'">{{ measured_heatloss | toFixed(2) }} <span class="fs-5 fw-normal text-muted">kW</span></div>
                        <div class="input-group justify-content-center mx-auto" style="max-width:200px" v-else>
                            <input type="text" class="form-control text-center fw-bold" v-model.number="measured_heatloss" @change="update_fit">
                            <span class="input-group-text">kW</span>
                        </div>
                        <div class="input-group input-group-sm justify-content-center mx-auto mt-2" style="max-width:200px" v-if="heatloss_mode == 'manual'">
                            <span class="input-group-text">Base DT</span>
                            <input type="text" class="form-control text-center" v-model.number="base_DT" @change="update_fit">
                            <span class="input-group-text">°K</span>
                        </div>
                        <div class="text-muted small mt-1">&plusmn; {{ measured_heatloss_range | toFixed(2) }} kW<span v-if="heatloss_mode != 'manual'"> (Base DT: {{ base_DT | toFixed(1) }}K)</span></div>
                        <div class="mt-2">
                            <div class="btn-group btn-group-sm" role="group">
                                <button class="btn" :class="heatloss_mode == 'manual' ? 'btn-outline-primary' : 'btn-primary'" @click="set_heatloss_mode('auto')">Auto fit</button>
                                <button class="btn" :class="heatloss_mode == 'manual' ? 'btn-primary' : 'btn-outline-primary'" @click="set_heatloss_mode('manual')">Manual</button>
                            </div>
                            <button class="btn btn-success btn-sm ms-2" v-if="enable_save" @click="save_heat_loss">Save</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-top border-3 border-danger">
                    <div class="card-body text-center">
                        <div class="text-secondary text-uppercase small mb-2">Coldest day flow temperature*</div>
                        <div class="fs-2 fw-bold">{{ design_flowT | toFixed(1) }} <span class="fs-5 fw-normal text-muted">°C</span></div>
                        <div class="text-muted small mt-1">&plusmn; {{ design_flowT_range | toFixed(1) }} °C</div>
                        <button class="btn btn-sm mt-2" :class="show_flowT ? 'btn-danger' : 'btn-outline-danger'" @click="toggle_flowT">
                            {{ show_flowT ? 'Hide on chart' : 'Show on chart' }}
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-top border-3 border-secondary">
                    <div class="card-body text-center">
                        <div class="text-secondary text-uppercase small mb-2">Design &Delta;T</div>
                        <div class="fs-2 fw-bold">{{ design_DT }} <span class="fs-5 fw-normal text-muted">°K</span></div>
                        <div class="text-muted small mt-1">{{ target_roomT }}°C room &minus; {{ design_outsideT }}°C outside</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-lg-4 col-md-6">
                <div class="input-group">
                    <span class="input-group-text">Colour points by</span>
                    <select class="form-control" v-model="colour_by" @change="update_fit">
                        <option value="">None</option>
                        <option value="weighted_flowT">Flow temperature</option>
                        <option value="running_returnT_mean">Return temperature</option>
                        <option value="outsideT">Outside temperature</option>
                        <option value="roomT">Room temperature</option>
                    </select>
                </div>
            </div>
            <div class="col-lg-5 col-md-12 align-items-center gap-2" :style="{display: colour_by ? 'flex' : 'none'}">
                <span class="text-muted text-nowrap">{{ colour_label }}</span>
                <span class="text-nowrap">{{ colour_min | toFixed(1) }}°C</span>
                <div id="colour_scale_bar" class="flex-grow-1 border rounded" style="height:16px"></div>
                <span class="text-nowrap">{{ colour_max | toFixed(1) }}°C</span>
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
                    <span class="input-group-text" title="From the system's Outside design temperature">Design outside T</span>
                    <input type="text" class="form-control" v-model.number="design_outsideT" @change="update_design_DT">
                    <span class="input-group-text">°C</span>
                </div>
            </div>

            <div class="col-lg-5 col-md-6">
                <div class="input-group mb-3">
                    <span class="input-group-text" title="Used to calculate the design &Delta;T">Target room T</span>
                    <input type="text" class="form-control" v-model.number="target_roomT" @change="update_target_roomT">
                    <span class="input-group-text">°C</span>
                    <button class="btn" :class="fixed_room_tmp_enable ? 'btn-primary' : 'btn-outline-primary'"
                        @click="toggle_fixed_room_tmp"
                        title="Use this fixed room temperature in place of monitored room temperature data">
                        {{ fixed_room_tmp_enable ? 'Using as room T' : 'Use as room T' }}
                    </button>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-lg-4 col-md-6">
                <div class="input-group mb-3">
                    <span class="input-group-text">Calculated heat loss</span>
                    <input type="text" class="form-control" v-model.number="calculated_heatloss" :disabled="!enable_save" @change="update_fit">
                    <span class="input-group-text">kW</span>
                    <button class="btn btn-primary" @click="save_calculated_heat_loss" v-if="enable_save">Save</button>
                </div>
            </div>
            <div class="col-lg-5 col-md-6" v-if="hp_max_test_count > 0">
                <div class="input-group mb-3">
                    <span class="input-group-text">Max output test</span>
                    <input type="text" class="form-control" :value="hp_max_test_text" disabled>
                    <a class="btn btn-primary" :href="heatpump_url" v-if="heatpump_url" title="View heat pump database entry"><i class="fas fa-database"></i></a>
                </div>
            </div>
            <div class="col-lg-5 col-md-6" v-else>
                <div class="input-group mb-3">
                    <span class="input-group-text">Badge capacity</span>
                    <input type="text" class="form-control" :value="badge_capacity" disabled>
                    <span class="input-group-text">kW</span>
                    <span class="input-group-text">No max output test available</span>
                    <a class="btn btn-primary" :href="heatpump_url" v-if="heatpump_url" title="View heat pump database entry"><i class="fas fa-database"></i></a>
                </div>
            </div>
        </div>


        <div class="row">


            <p>Each datapoint shows the average heat output over a 24 hour period. Hover over data point for more information, click on a data point to open the dashboard view for that day.</p>

            <p><b>Heat demand:</b> The measured heat output shown here is the combined heat output of space heating and hot water.
                While it doesnt compare directly to the heat loss of the building, it is a good indicator of the heat demand from the heat pump.
                For a typical 100m2 house the extra head demand for hot water is similar to the reduction in heat demand from internal gains, so the measured heat output is often a good approximation of the heat loss of the building.
            </p>

            <p><b>Flow temperature:</b> The flow temperature at design &Delta;T shown above will often be lower than the design flow temperature calculated via a heat loss calculation tool - even with an accurate heat loss. This is because heat loss calculation tools do not subtract internal gains or take into account the way thermal mass can help a building ride out low temperature extremes.

            <p><b>&plusmn; Uncertainty:</b> Linear regression 80% prediction interval. 8 out of 10 data points are expected to fall within this range.</p>
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
            design_outsideT: -3,
            target_roomT: 20,
            measured_heatloss: 0,
            measured_heatloss_range: 500,
            heatloss_mode: 'auto',
            calculated_heatloss: 0,
            badge_capacity: 0,
            heatpump_url: '',
            hp_max_test_count: 0,
            hp_max_test_mean: 0,
            hp_max_test_min: 0,
            hp_max_test_max: 0,
            fixed_room_tmp_enable: 0,
            room_temp_alert: "",
            colour_by: "",
            colour_min: 0,
            colour_max: 0,
            show_flowT: false,
            design_flowT: 0,
            design_flowT_range: 0

        },
        computed: {
            colour_label: function() {
                var labels = {
                    'weighted_flowT': 'Flow temperature (°C)',
                    'running_returnT_mean': 'Return temperature (°C)',
                    'outsideT': 'Outside temperature (°C)',
                    'roomT': 'Room temperature (°C)'
                };
                return labels[this.colour_by] || '';
            },
            hp_max_test_text: function() {
                if (this.hp_max_test_count == 1) {
                    return this.hp_max_test_mean.toFixed(1) + " kW (1 test)";
                }
                return this.hp_max_test_mean.toFixed(1) + " kW mean, " + this.hp_max_test_min.toFixed(1) + "–" + this.hp_max_test_max.toFixed(1) + " kW range (" + this.hp_max_test_count + " tests)";
            }
        },
        methods: {
            change_system: function() {
                app.fixed_room_tmp_enable = 0;
                app.target_roomT = 20;
                load();
            },
            load: function() {
                load();
            },
            update_fit: function() {
                draw();
            },
            toggle_flowT: function() {
                this.show_flowT = !this.show_flowT;
                draw();
            },
            set_heatloss_mode: function(mode) {
                this.heatloss_mode = mode;
                if (mode == 'auto') {
                    this.auto_fit();
                }
            },
            update_design_DT: function() {
                // Design DT is derived from the target room and design outside temperatures
                app.design_DT = Math.round((app.target_roomT - app.design_outsideT) * 10) / 10;
                draw();
            },
            update_target_roomT: function() {
                app.design_DT = Math.round((app.target_roomT - app.design_outsideT) * 10) / 10;
                // When used as a fixed room temperature, reload to re-apply it to the data
                if (app.fixed_room_tmp_enable) {
                    load();
                } else {
                    draw();
                }
            },
            toggle_fixed_room_tmp: function() {
                app.fixed_room_tmp_enable = app.fixed_room_tmp_enable ? 0 : 1;
                load();
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
            save_calculated_heat_loss: function () {

                var z = systemid_map[app.systemid];
                app.system_list[z].heat_loss = app.calculated_heatloss;

                var data_to_save = {
                    id: app.systemid,
                    data: {
                        heat_loss: app.calculated_heatloss
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
                app.target_roomT = 20;
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
                var fit = calculateHeatDemandFit(data['heat_vs_dt'], 0);

                app.base_DT = fit.base_DT.toFixed(1) * 1;
                app.measured_heatloss = ((fit.m * app.design_DT) + fit.b).toFixed(2) * 1;

                // If the balance point comes out below zero, fall back to a fit through the origin
                if (app.base_DT < 0) {
                    var slope0 = calculateSlopeWithZeroIntercept(data['heat_vs_dt']);
                    app.base_DT = 0;
                    app.measured_heatloss = (slope0 * app.design_DT).toFixed(2) * 1;
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
        app.badge_capacity = hp_output;
        app.heatpump_url = app.system_list[z].heatpump_url || '';

        app.measured_heatloss = app.system_list[z].measured_heat_loss;
        app.measured_heatloss_range = app.system_list[z].measured_heat_loss_range;
        app.calculated_heatloss = app.system_list[z].heat_loss;

        // Max output test results from the heat pump database, matched via the
        // system's make & model (heatpump_model_id resolved server side)
        load_max_cap_tests(app.system_list[z].heatpump_model_id);

        app.base_DT = app.system_list[z].measured_base_DT;
        app.design_DT = app.system_list[z].measured_design_DT;

        if (app.measured_heatloss == 0 && app.base_DT == 0 && app.design_DT == 0) {
            app.measured_heatloss = 0;
            app.base_DT = 4;
            app.design_DT = 23;
            app.measured_heatloss_range = 0.5;
        }

        // No saved heat demand fit for this system: fit automatically once the daily data has loaded
        var auto_fit_needed = !(app.measured_heatloss > 0);

        // Design condition: prefer the system's Outside design temperature (from meta) with
        // the target room temperature (reset to 20 on system change), from which the
        // design DT is derived.
        var design_temp = app.system_list[z].design_temp;
        if (design_temp !== null && design_temp !== "" && !isNaN(design_temp)) {
            app.design_outsideT = design_temp * 1;
            app.design_DT = Math.round((app.target_roomT - app.design_outsideT) * 10) / 10;
        } else {
            // No design temp on record: keep the loaded/default design DT, derive outside T for display
            app.design_outsideT = Math.round((app.target_roomT - app.design_DT) * 10) / 10;
        }

        


        var fields = [
            'timestamp',
            mode + '_heat_mean',
            mode + '_roomT_mean',
            mode + '_outsideT_mean',
            'weighted_flowT',
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
                    app.room_temp_alert = "No room temperature data found, using the target room temperature as a fixed room temperature (default 20°C)";
                } else {
                    app.room_temp_alert = "";
                }

                // Apply the target room temperature as a fixed room temperature
                if (app.fixed_room_tmp_enable) {
                    for (var i = 0; i < data[mode + '_roomT_mean'].length; i++) {
                        data[mode + '_roomT_mean'][i][1] = app.target_roomT;
                    }
                }

                // Create series with room - outside x-axis and heatpump heat output y-axis
                // (max_heat tracks the data only; reference lines are folded into the
                // y-axis scale in draw, where async-loaded test results are available)
                max_heat = 0;

                var total_elec_kwh = 0;
                var total_heat_kwh = 0;
                var total_cool_kwh = 0;

                data['heat_vs_dt'] = [];
                data['cool_vs_dt'] = [];
                for (var i = 0; i < data[mode + '_heat_mean'].length; i++) {
                    if (data[mode + '_roomT_mean'][i][1] > 0 && data[mode + '_data_length'][i][1] > 64800) {

                        // Filter out invalid points where the heat pump wasn't running:
                        // flow and return temperature both zero (heat output is spurious ~0)
                        if (data['weighted_flowT'][i][1] == 0 && data['running_returnT_mean'][i][1] == 0) {
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

                if (auto_fit_needed && data['heat_vs_dt'].length >= 3) {
                    app.auto_fit();
                } else {
                    draw();
                }
            }
        });
    }

    // Load approved max output test results for the system's heat pump model from
    // the heat pump database and compute mean, min & max heat output in kW
    function load_max_cap_tests(model_id) {
        app.hp_max_test_count = 0;
        app.hp_max_test_mean = 0;
        app.hp_max_test_min = 0;
        app.hp_max_test_max = 0;

        if (!model_id) {
            draw();
            return;
        }

        $.ajax({
            type: "GET",
            url: path + "heatpump/max_cap_test/list",
            data: { 'id': model_id },
            dataType: "json",
            success: function(tests) {
                var count = 0, sum = 0, min = null, max = null;
                for (var i = 0; i < tests.length; i++) {
                    if (tests[i].review_status != 1) continue;
                    var heat_kw = tests[i].heat * 0.001;
                    sum += heat_kw;
                    if (min === null || heat_kw < min) min = heat_kw;
                    if (max === null || heat_kw > max) max = heat_kw;
                    count++;
                }
                if (count > 0) {
                    app.hp_max_test_count = count;
                    app.hp_max_test_mean = sum / count;
                    app.hp_max_test_min = min;
                    app.hp_max_test_max = max;
                }
                draw();
            }
        });
    }

    var chart = null;

    // Map a colour-by token to the underlying data field name
    function colour_field_name(token) {
        if (token === 'outsideT') return mode + '_outsideT_mean';
        if (token === 'roomT') return mode + '_roomT_mean';
        return token; // weighted_flowT, running_returnT_mean
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

    // Custom plugin: draw text labels next to the reference lines.
    // Labels that would overlap (reference lines close together) are pushed apart
    // vertically, and each label gets a white halo so it stays legible when it
    // crosses a line or the max output test band.
    var refLabelPlugin = {
        id: 'reflabels',
        afterDatasetsDraw: function(c) {
            var ctx = c.ctx;
            var xs = c.scales.x, ys = c.scales.y;
            var lineH = 16; // minimum vertical spacing between labels in px

            // Desired positions (y is the text bottom edge, textBaseline 'bottom')
            var items = [];
            for (var k = 0; k < ref_labels.length; k++) {
                var L = ref_labels[k];
                items.push({
                    text: L.text,
                    x: xs.getPixelForValue(L.x) + 4,
                    y: ys.getPixelForValue(L.y) + (L.dy || 0)
                });
            }

            // Resolve collisions: top-to-bottom, push each label below the one above
            items.sort(function(a, b) { return a.y - b.y; });
            var min_y = c.chartArea.top + lineH;
            for (var k = 0; k < items.length; k++) {
                if (items[k].y < min_y) items[k].y = min_y;
                if (k > 0 && items[k].y - items[k - 1].y < lineH) {
                    items[k].y = items[k - 1].y + lineH;
                }
            }

            ctx.save();
            ctx.font = '13px sans-serif';
            ctx.textBaseline = 'bottom';
            ctx.lineWidth = 3;
            ctx.strokeStyle = 'rgba(255,255,255,0.85)';
            ctx.fillStyle = '#666';
            for (var k = 0; k < items.length; k++) {
                ctx.strokeText(items[k].text, items[k].x, items[k].y);
                ctx.fillText(items[k].text, items[k].x, items[k].y);
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

        // Daily stats and max output tests load asynchronously; wait for the stats
        if (!data['heat_vs_dt']) return;

        // Left hand edge of chart: minimum DT in the data (can be negative when cooling), capped at 0
        var min_dt = 0;
        for (var i = 0; i < data['heat_vs_dt'].length; i++) {
            if (data['heat_vs_dt'][i][0] < min_dt) min_dt = data['heat_vs_dt'][i][0];
        }

        // z for an 80% two-sided prediction interval (normal approx.)
        var PI_Z = 1.2816;

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

        // Hide the badge capacity line once a max output test is available
        if (app.hp_max_test_count == 0) {
            datasets.push(ref_line(min_dt, hp_output, '#000000', 2));
            if (hp_output > 0) ref_labels.push({ x: min_dt, y: hp_output, text: 'Heatpump badge capacity', dy: -4 });
        }

        // Max output test results from the heat pump database: a shaded min-max band
        // showing the range across tests of this heat pump model, labelled at its edges
        if (app.hp_max_test_count > 0) {
            if (app.hp_max_test_count > 1 && app.hp_max_test_max > app.hp_max_test_min) {
                var band_top = ref_line(min_dt, app.hp_max_test_max, 'rgba(0,0,0,0)', 0);
                band_top.series_type = 'band'; // no line shadow
                datasets.push(band_top);
                var band_bottom = ref_line(min_dt, app.hp_max_test_min, 'rgba(0,0,0,0)', 0);
                band_bottom.series_type = 'band';
                band_bottom.fill = '-1';
                band_bottom.backgroundColor = 'rgba(204,136,136,0.2)';
                datasets.push(band_bottom);
                /*ref_labels.push({ x: min_dt, y: app.hp_max_test_max, text: 'Max output test', dy: -4 });*/
                ref_labels.push({ x: min_dt, y: app.hp_max_test_min, text: 'Max output test range', dy: -4 });
            } else {
                ref_labels.push({ x: min_dt, y: app.hp_max_test_mean, text: 'Max output test result', dy: -4 });
            }
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
                var pi = calculatePIStats(data['heat_vs_dt'], pi_m, pi_b, 0);

                if (pi) {
                    // Report the PI half-width at design DT as the ± range figure
                    app.measured_heatloss_range = piHalfWidth(pi, app.design_DT, PI_Z).toFixed(2) * 1;

                    // The interval widens away from the mean DT, so sample it as a curve
                    var pi_upper = [], pi_lower = [];
                    var PI_STEPS = 40;
                    for (var s2 = 0; s2 <= PI_STEPS; s2++) {
                        var xx = app.base_DT + (app.design_DT - app.base_DT) * (s2 / PI_STEPS);
                        var yy = pi_m * xx + pi_b;
                        var hw = piHalfWidth(pi, xx, PI_Z);
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

        // Flow temperature vs DT on a secondary axis, with an 80% PI, predicting the
        // design flow temperature at the design DT.
        //
        // weighted_flowT mixes space heating with DHW. DHW runs at a high, roughly
        // constant flow temp and dominates the running mean in summer (low DT), which
        // inverts the weather-compensation slope. To recover the space-heating line we
        // weight each day by its modelled space-heating demand, w = max(0, DT - base_DT):
        // summer/DHW-only days (below the balance point) drop out, cold days dominate.
        // The fit always runs so the coldest day flow temperature card is always
        // populated; the checkbox only toggles the flow series on the chart.
        var flow_points = [];
        for (var i = 0; i < data['heat_vs_dt'].length; i++) {
            var oi = data['heat_vs_dt'][i][2];
            var ft = data['weighted_flowT'][oi][1];
            if (ft > 0) flow_points.push([data['heat_vs_dt'][i][0], ft, oi]);
        }

        var ffit = calculateWeightedFlowFit(flow_points, 0, app.base_DT, 5);

        // Mark which points the fit actually used (space-heating regime), to shade the rest
        var flow_inlier = {};
        if (ffit) {
            for (var q = 0; q < ffit.inliers.length; q++) flow_inlier[ffit.inliers[q].i] = true;
        }

        if (app.show_flowT) {
            var flow_scatter = [], flow_colour = [];
            for (var i = 0; i < flow_points.length; i++) {
                flow_scatter.push({ x: flow_points[i][0], y: flow_points[i][1], i: flow_points[i][2] });
                // DHW-dominated / excluded points shown faint, space-heating points solid red
                flow_colour.push((ffit && flow_inlier[flow_points[i][2]]) ? '#d9534f' : '#f2c9c7');
            }

            datasets.push({
                data: flow_scatter,
                yAxisID: 'y1',
                pointBackgroundColor: 'white',
                pointBorderColor: flow_colour,
                pointBorderWidth: 1.5,
                pointRadius: 2,
                pointHoverRadius: 3.5,
                showLine: false,
                series_type: 'flow'
            });
        }

        if (ffit && app.design_DT > app.base_DT) {
            app.design_flowT = (ffit.m * app.design_DT + ffit.b).toFixed(1) * 1;

            // Fit line
            if (app.show_flowT) {
                datasets.push({
                    data: [{ x: min_dt, y: ffit.m * min_dt + ffit.b }, { x: app.design_DT, y: ffit.m * app.design_DT + ffit.b }],
                    yAxisID: 'y1', borderColor: '#d9534f', borderWidth: 2,
                    pointRadius: 0, pointHitRadius: 0, showLine: true, fill: false, tension: 0, series_type: 'line'
                });
            }

            // 80% prediction interval about the flow temperature line, from the
            // space-heating points the fit retained
            var flow_inliers = ffit.inliers.map(function(p) { return [p.x, p.y]; });
            var fpi = calculatePIStats(flow_inliers, ffit.m, ffit.b, -Infinity);
            if (fpi) {
                app.design_flowT_range = piHalfWidth(fpi, app.design_DT, PI_Z).toFixed(1) * 1;

                if (app.show_flowT) {
                    var f_upper = [], f_lower = [];
                    var F_STEPS = 40;
                    for (var s3 = 0; s3 <= F_STEPS; s3++) {
                        var fx = min_dt + (app.design_DT - min_dt) * (s3 / F_STEPS);
                        var fy = ffit.m * fx + ffit.b;
                        var fhw = piHalfWidth(fpi, fx, PI_Z);
                        f_upper.push({ x: fx, y: fy + fhw });
                        f_lower.push({ x: fx, y: fy - fhw });
                    }
                    datasets.push({
                        data: f_upper, yAxisID: 'y1', borderColor: '#f0b0ad', borderWidth: 1.5,
                        pointRadius: 0, pointHitRadius: 0, showLine: true, fill: false, tension: 0, series_type: 'line'
                    });
                    datasets.push({
                        data: f_lower, yAxisID: 'y1', borderColor: '#f0b0ad', borderWidth: 1.5,
                        pointRadius: 0, pointHitRadius: 0, showLine: true, fill: false, tension: 0, series_type: 'line'
                    });
                }
            }
        } else {
            app.design_flowT = 0;
            app.design_flowT_range = 0;
        }

        // Y-axis scale: data maximum extended to include the reference lines
        var y_max = max_heat;
        if (app.calculated_heatloss > y_max) y_max = app.calculated_heatloss;
        if (hp_output > y_max) y_max = hp_output;
        if (app.hp_max_test_max > y_max) y_max = app.hp_max_test_max;

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
                    max: y_max * 1.1,
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
                },
                y1: {
                    type: 'linear',
                    position: 'right',
                    display: app.show_flowT,
                    title: { display: true, text: 'Flow temperature (°C)', color: '#d9534f', font: { size: 14 } },
                    border: { display: false },
                    grid: { drawOnChartArea: false },
                    ticks: { color: '#d9534f', font: { size: 13 } }
                }
            },
            interaction: { mode: 'nearest', intersect: true },
            plugins: {
                legend: { display: false },
                tooltip: {
                    filter: function(item) {
                        var t = item.dataset.series_type;
                        return t === 'heat' || t === 'cooling' || t === 'flow';
                    },
                    callbacks: {
                        title: function() { return ''; },
                        label: function(ctx) {
                            var oi = ctx.raw.i;
                            var lines = [];
                            var d = new Date(data[mode + '_heat_mean'][oi][0]);
                            var datestr = d.getDate() + ' ' + d.toLocaleString('default', { month: 'short' }) + ' ' + d.getFullYear();

                            if (ctx.dataset.series_type === 'flow') {
                                lines.push('FlowT: ' + ctx.raw.y.toFixed(1) + ' °C');
                                lines.push('DT: ' + ctx.raw.x.toFixed(1) + ' °K');
                                lines.push(datestr);
                                return lines;
                            }

                            var heat_label = ctx.dataset.series_type === 'cooling' ? 'Cooling' : 'Heat';
                            lines.push(heat_label + ': ' + ctx.raw.y.toFixed(3) + ' kW');
                            lines.push('DT: ' + ctx.raw.x.toFixed(1) + ' °K');
                            lines.push('Room: ' + data[mode + '_roomT_mean'][oi][1].toFixed(1) + ' °C');
                            lines.push('Outside: ' + data[mode + '_outsideT_mean'][oi][1].toFixed(1) + ' °C');
                            lines.push('FlowT: ' + data['weighted_flowT'][oi][1].toFixed(1) + ' °C');
                            lines.push('ReturnT: ' + data['running_returnT_mean'][oi][1].toFixed(1) + ' °C');
                            lines.push(datestr);
                            return lines;
                        }
                    }
                }
            },
            onClick: function(e, elements, c) {
                var hit = c.getElementsAtEventForMode(e, 'nearest', { intersect: true }, false);
                for (var k = 0; k < hit.length; k++) {
                    var ds = c.data.datasets[hit[k].datasetIndex];
                    if (ds.series_type === 'heat' || ds.series_type === 'cooling' || ds.series_type === 'flow') {
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
                    return ds.series_type === 'heat' || ds.series_type === 'cooling' || ds.series_type === 'flow';
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

    // Heat-demand fit with iterated exclusion of the low-DT DHW/standby floor.
    // Combined heat output doesn't fall to zero in summer (a ~constant DHW/standby floor),
    // and those low-DT points sit above the true space-heating line, rotating a naive fit
    // shallower and under-predicting design heat demand. So: robustly fit, take the balance
    // point (x-intercept), exclude points below it, refit, and repeat until it stabilises.
    // Returns { m, b, base_DT }.
    function calculateHeatDemandFit(dataPoints, min_x) {
        var fit = calculateRobustFit(dataPoints, min_x, 2.5, 3);
        var base = fit.m != 0 ? (0 - fit.b) / fit.m : min_x;

        for (var it = 0; it < 6; it++) {
            if (base <= min_x) break; // nothing below the filter to exclude
            var f = calculateRobustFit(dataPoints, base, 2.5, 3);
            var nb = f.m != 0 ? (0 - f.b) / f.m : base;
            fit = f;
            if (Math.abs(nb - base) < 0.1) { base = nb; break; }
            base = nb;
        }

        return { m: fit.m, b: fit.b, base_DT: base };
    }

    // Weighted least-squares fit of y = m*x + b over points [{x, y, w}, ...]
    function weightedOLS(pts) {
        var Sw = 0, Swx = 0, Swy = 0, Swxx = 0, Swxy = 0;
        for (var i = 0; i < pts.length; i++) {
            var w = pts[i].w, x = pts[i].x, y = pts[i].y;
            Sw += w; Swx += w * x; Swy += w * y; Swxx += w * x * x; Swxy += w * x * y;
        }
        var denom = Sw * Swxx - Swx * Swx;
        if (denom === 0) return { m: 0, b: Sw > 0 ? Swy / Sw : 0 };
        var m = (Sw * Swxy - Swx * Swy) / denom;
        var b = (Swy - m * Swx) / Sw;
        return { m: m, b: b };
    }

    // Weighted residual standard deviation of points [{x,y,w}] about a line fit {m,b}
    function weightedResidStd(pts, fit) {
        var sw = 0, swr2 = 0;
        for (var j = 0; j < pts.length; j++) {
            var r = pts[j].y - (fit.m * pts[j].x + fit.b);
            sw += pts[j].w;
            swr2 += pts[j].w * r * r;
        }
        return sw > 0 ? Math.sqrt(swr2 / sw) : 0;
    }

    // Flow-temperature weather-compensation fit that resists summer DHW contamination.
    //
    // Two mechanisms, exploiting the physics of the contamination:
    //  1. Demand weighting w = max(0, x - base_DT): DHW-only summer days (below the
    //     heating balance point) drop out entirely; cold days dominate.
    //  2. Asymmetric trimming: DHW only ever pushes the running flow-temp mean UP, so we
    //     shed points above the line aggressively (kHigh) and keep the dense band below
    //     (kLow), converging onto the space-heating lower envelope. A final symmetric
    //     pass re-admits the band's own scatter so the fit isn't biased low.
    //
    // Returns { m, b, inliers:[{x,y,w,i}] } or null if too few space-heating points.
    function calculateWeightedFlowFit(dataPoints, min_x, base_DT, maxIter) {
        var all = [];
        for (var i = 0; i < dataPoints.length; i++) {
            var x = dataPoints[i][0];
            if (x < min_x) continue;
            var w = x - base_DT;
            if (w <= 0) continue; // below the balance point: no space heating (summer DHW)
            all.push({ x: x, y: dataPoints[i][1], w: w, i: dataPoints[i][2] });
        }
        if (all.length < 3) return null;

        var pts = all.slice();
        var fit = weightedOLS(pts);
        var s = 0;

        // Asymmetric trimming toward the lower (space-heating) envelope.
        // kHigh is deliberately aggressive: DHW contaminates one-sidedly above the line,
        // and a tighter high-side threshold keeps the fitted slope from drooping.
        var kHigh = 1.25, kLow = 3.0;
        for (var iter = 0; iter < maxIter; iter++) {
            s = weightedResidStd(pts, fit);
            if (s <= 0) break;

            var kept = pts.filter(function(p) {
                var r = p.y - (fit.m * p.x + fit.b);
                return r <= kHigh * s && r >= -kLow * s;
            });
            if (kept.length === pts.length || kept.length < 3) break;

            pts = kept;
            fit = weightedOLS(pts);
        }

        // Final symmetric pass: re-admit legitimate band scatter within kBand of the
        // converged line (DHW sits well above this) so the band centre isn't biased low
        if (s > 0) {
            var kBand = 2.0;
            var band = all.filter(function(p) {
                return Math.abs(p.y - (fit.m * p.x + fit.b)) <= kBand * s;
            });
            if (band.length >= 3) {
                pts = band;
                fit = weightedOLS(pts);
            }
        }

        return { m: fit.m, b: fit.b, inliers: pts };
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
