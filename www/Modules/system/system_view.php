<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');
global $settings, $session;
?>
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
    .quality-bound {
        background-color: #ddd;
        padding:5px;
    }
    .quality {
        width: 100%;
    }
    .quality td {
        padding: 5px;
        text-align: center;
        border: 1px solid #fff;
        color: #fff;
        font-size: 14px;
    }
</style>

<div id="app" class="bg-light">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container" style="max-width:800px;">
            <div style="float:right" v-if="admin && system.id"><a :href="path+'system/log?id='+system.id" class="btn btn-light">Change log</a></div>
            <div style="float:right; margin-right:10px;" v-if="admin && system.id">
                <a :href="'https://mail.google.com/mail/?view=cm&fs=1&to=<?php echo $email; ?>'" target="_blank" class="btn btn-dark">
                    <i class="fa fa-envelope" style="color: #ffffff;"></i> Email
                </a>
            </div>

            <div v-if="system.hp_model!=''">
                <h3>{{ system.hp_output }} kW, {{ system.hp_model }}</h3>
                <p>{{ system.location }}, <span v-if="system.installer_name"><a :href="system.installer_url">{{ system.installer_name }}</a></span></p>
            </div>
            <a v-if="system.id" :href="system.url"><button class="btn btn-primary mb-3"><span class="d-none d-lg-inline-block">Emoncms</span> Dashboard</button></a>
            <a v-if="system.id" :href="path+'heatloss?id='+system.id"><button class="btn btn-secondary mb-3">Heat demand <span class="d-none d-lg-inline-block">tool</span></button></a>
            <a v-if="system.id" :href="path+'monthly?id='+system.id"><button class="btn btn-secondary mb-3">Monthly</button></a>
            <a v-if="system.id" :href="path+'daily?id='+system.id"><button class="btn btn-secondary mb-3">Daily</button></a>
            <a v-if="system.id" :href="path+'compare?id='+system.id"><button class="btn btn-secondary mb-3">Compare</button></a>              
            <a v-if="system.id" :href="path+'histogram?id='+system.id"><button class="btn btn-secondary mb-3">Histogram</button></a>
            <button class="btn btn-warning mb-3" style="margin-left:10px" v-if="admin && mode=='view'" @click="mode='edit'">Edit</button>

            <h3 v-if="!system.id">Add New System</h3>
        </div>
    </div>
    <br>

    <div class="container mt-3" style="max-width:800px" v-if="system.url!='' && all!=undefined">


        <div class="card mt-3" v-if="all.combined_data_length">
            <h5 class="card-header">All data</h5>
            <div class="card-body">
                <div class="row" style="text-align:center">
                    <div class="col">
                        <h5>Electric</h5>
                        <h4>{{ all.combined_elec_kwh | toFixed(0) }} kWh</h4>
                    </div>
                    
                    <div class="col">
                        <h5>Heat Output</h5>
                        <h4>{{ all.combined_heat_kwh | toFixed(0) }} kWh</h4>
                    </div>
                    
                    <div class="col">
                        <h5 title="Seasonal performance factor">COP</h5>
                        <h4>{{ all.combined_cop | toFixed(1) }}</h4>            
                    </div>

                    <div class="col">
                        <h5 title="Data coverage">Data</h5>
                        <h4>{{ all.combined_data_length | formatDays }} days</h4>
                    </div>
                </div>      
            </div>
        </div>

        <div class="card mt-3">
            <h5 class="card-header">Data Coverage</h5>
            <div class="card-body">
                <p>100% is full data coverage, 0% is no data</p>
                <div class="quality-bound">
                <table class="quality">
                    <tr>
                        <td></td>
                        <td v-for="month in monthly">
                        {{ month.timestamp | monthName }}
                        </td>
                    </tr>
                    <tr>
                        <td>Elec</td>
                        <td v-for="month in monthly" :style="{ backgroundColor: qualityColor(month.quality_elec) }">
                        {{ month.quality_elec | toFixed(0) }}
                        </td>
                    </tr>
                    <tr>
                        <td>Heat</td>
                        <td v-for="month in monthly" :style="{ backgroundColor: qualityColor(month.quality_heat) }">
                            {{ month.quality_heat | toFixed(0) }}
                        </td>
                    </tr>
                    <tr>
                        <td>Flow</td>
                        <td v-for="month in monthly" :style="{ backgroundColor: qualityColor(month.quality_flowT) }">
                            {{ month.quality_flowT | toFixed(0) }}
                        </td>
                    </tr>
                    <tr>
                        <td>Return</td>
                        <td v-for="month in monthly" :style="{ backgroundColor: qualityColor(month.quality_returnT) }">
                            {{ month.quality_returnT | toFixed(0) }}
                        </td>
                    </tr>
                    <tr>
                        <td>Outside</td>
                        <td v-for="month in monthly" :style="{ backgroundColor: qualityColor(month.quality_outsideT) }">
                            {{ month.quality_outsideT | toFixed(0) }}
                        </td>
                    </tr>
                </tr>
                </table>
            </div>
            </div>
        </div>
    </div>

    <div class="container mt-3" style="max-width:800px">
        <div class="card mt-3" v-if="mode=='edit' && system.id">
            <h5 class="card-header">Reload system data</h5>
            <div class="card-body">
                <p>Manually reload system data from Emoncms dashboard</p>
                <button type="button" class="btn btn-primary" @click="loadstats" :disabled="disable_loadstats">Reload</button>

                <pre id="reload_log" style="display:none; background-color:#300a24; color:#fff; padding:10px; margin-top:20px; border-radius: 5px;"></pre>
            </div>
        </div>
    </div>

    <div class="container mt-3" style="max-width:800px" v-if="mode=='edit' && (session_userid==system.userid || !system.userid)">
        <div class="card mt-3">
            <h5 class="card-header">Select Emoncms.org dashboard</h5>
            <div class="card-body">

                <select class="form-select"  style="width:100%" v-model="new_app_selection" @change="load_app">
                    <option value="">PLEASE SELECT</option>
                    <option v-for="(app,index) in available_apps" :value="app.id" :disabled="app.in_use==1">{{ app.username }}: {{ app.name }} {{ app.in_use_msg }}</option>
                </select>

            </div>
        </div>
    </div>

    <div class="container mt-3" style="max-width:800px">
        <div class="row" v-if="system.url!=''">
            <p v-if="mode=='view'">Information about this system.</p>
            <div v-if="mode=='edit'">
                <p>Please complete this form to provide valuable context about the system, which will help others interpret the monitored data effectively and enable richer data analysis.<br><br>We encourage you to fill out the entire form, as it offers the most comprehensive understanding for others. However, if time is limited or you lack some information, you may opt for the 'Basic & Required' option to keep it simple.</p>
                <hr>
                <div class="row">
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" v-model="form_type" value="basic" @change="filter_schema_groups">
                            <label>Keep it simple (basic and required fields)</label>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" v-model="form_type" value="full" @change="filter_schema_groups">
                            <label>I've got time (full form)</label>
                        </div>
                    </div>
                </div>
                
                <hr class="mt-3">
            </div>

            <div class="input-group mt-3" v-if="admin">
                <span class="input-group-text">URL Admin only</span>
                <input type="text" class="form-control" v-model="system.url">
            </div>

            <table class="table mt-3">
                <tbody v-for="group,group_name in filtered_schema_groups">
                    <tr>
                        <th style="background-color:#f0f0f0;">{{ group_name }}</th>
                        <td style="background-color:#f0f0f0;"></td>
                        <td style="background-color:#f0f0f0;"></td>
                    </tr>
                    <tr v-for="(field,key) in group">
                        <td>
                            <span>{{ field.name }}</span> <span v-if="!field.optional && mode=='edit'" style="color:#aa0000">*</span>
                        </td>
                        <td>
                            <span v-if="field.helper" data-bs-toggle="tooltip" data-bs-placement="top" :title="field.helper">
                                <i class="fas fa-question-circle"></i>
                            </span>
                        </td>
                        <td>
                            <span v-if="field.type=='tinyint(1)'">
                                <input type="checkbox" v-model="system[key]" :disabled="mode=='view' || field.disabled" @change="filter_schema_groups">
                            </span>
                            <span v-if="field.type!='tinyint(1)'">
                                <!-- Edit mode text input -->
                                <div class="input-group" v-if="mode=='edit' && !field.options">
                                    <input class="form-control" type="text" v-model="system[key]" @change="filter_schema_groups">
                                    <span class="input-group-text" v-if="field.unit">{{ field.unit }}</span>
                                </div>
                                <!-- View mode select input -->
                                <select class="form-control" v-if="mode=='edit' && field.options" v-model="system[key]" @change="filter_schema_groups">
                                    <option v-for="option in field.options">{{ option }}</option>
                                </select>
                                <!-- View mode text -->
                                <span v-if="mode=='view'">{{ system[key] }}</span> <span v-if="mode=='view'" style="color:#666; font-size:14px">{{ field.unit }}</span>
                            </span>
                        </td>
                    </tr>
                </tbody>

            </table>

        </div>
    </div>
    <div style=" background-color:#eee; padding-top:20px; padding-bottom:10px" v-if="mode=='edit' && system.url!=''">
        <div class="container" style="max-width:800px;">
            <?php if ($settings['public_mode_enabled']) { ?>
            <div class="row">
                <div class="col">
                    <p><b>Agree to share this information publicly</b></p>
                </div>
                <div class="col">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" v-model="system.share">
                        <label>Yes</label>
                    </div>
                </div>
            </div>
            <div class="row" v-if="admin">
                <div class="col">
                    <p><b>Publish system (Admin only)</b></p>
                </div>
                <div class="col">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" v-model="system.published">
                        <label>Yes</label>
                    </div>
                </div>
            </div>
            <?php } ?>

            <button type="button" class="btn btn-primary" @click="save">Save</button>
            <button type="button" class="btn btn-light" @click="cancel" style="margin-left:10px">Cancel</button>
            <br><br>

            <div class="alert alert-danger" role="alert" v-if="show_error" v-html="message"></div>
            <div class="alert alert-success" role="alert" v-if="show_success">
                <span v-html="message"></span>
                <button type="button" class="btn btn-light" @click="cancel" v-if="show_success">Back to system list</button>
            </div>
        </div>
    </div>
</div>
<script>

    // set page background color bg-light
    document.body.style.backgroundColor = "#f8f9fa";

    var schema = <?php echo json_encode($schema); ?>;
    // arrange by group
    var schema_groups = {};
    for (var key in schema) {
        if (schema[key].group && schema[key].editable) {
            if (!schema_groups[schema[key].group]) {
                schema_groups[schema[key].group] = {};
            }
            schema_groups[schema[key].group][key] = schema[key];
        }
    }

    let system_stats_monthly = <?php echo json_encode($system_stats_monthly); ?>;
    // covert to by group
    let system_stats_monthly_by_group = {};
    for (var key in system_stats_monthly) {
        let row = system_stats_monthly[key];
        if (row.group) {
            if (system_stats_monthly_by_group[row.group]==undefined) {
                system_stats_monthly_by_group[row.group] = {};
            }
            system_stats_monthly_by_group[row.group][key] = row;
        }
    }

    var reload_interval = null;

    var system = <?php echo json_encode($system_data); ?>;

    var form_type = 'basic';
    if (system.id) {
        form_type = 'full';
    }

    var app = new Vue({
        el: '#app',
        data: {
            session_userid: <?php echo $session['userid']; ?>,
            form_type: form_type,
            new_app_selection: '',
            available_apps: [],
            path: path,
            mode: "<?php echo $mode; ?>", // edit, view
            system: system,
            monthly: [],
            all: [],
            schema_groups: schema_groups,
            filtered_schema_groups: {},

            show_error: false,
            show_success: false,
            message: '',
            admin: <?php echo $admin ? 'true' : 'false'; ?>,

            chart_yaxis: 'combined_cop',
            system_stats_monthly_by_group: system_stats_monthly_by_group,
            disable_loadstats: false
        },
        computed: {
            qualityColor() {
                return function(score) {
                    const hue = (score / 100) * 120; // Map score to hue value (0-120)
                    // if score = 0 grey
                    if (score == 0) return '#ccc';
                    return `hsl(${hue}, 100%, 50%)`; // Convert hue value to HSL color
                }
            }
        },
        filters: {
            monthName: function(timestamp) {
                var date = new Date(timestamp * 1000);
                var month = date.toLocaleString('default', { month: 'short' });
                return month;
            },
            formatDays: function(data_length) {
                // days ago
                var days = data_length / (3600 * 24);
                return Math.round(days);
            },
            toFixed: function(value, decimals) {
                if (value == undefined) return '';
                value = parseFloat(value);
                return value.toFixed(decimals);
            }
        },
        methods: {
            save: function() {
                // Send data to server using axios, check response for success
                axios.post('save', {
                        id: this.$data.system.id,
                        data: this.$data.system
                    })
                    .then(function(response) {
                        if (response.data.success) {
                            app.show_success = true;
                            app.show_error = false;
                            app.message = response.data.message;

                            var list_items = "";

                            if (response.data.change_log != undefined) {
                                let change_log = response.data.change_log;
                                // Loop through change log add as list
                                for (var i = 0; i < change_log.length; i++) {
                                    list_items += "<li><b>" + change_log[i]['key'] + "</b> changed from <b>" + change_log[i]['old'] + "</b> to <b>" + change_log[i]['new'] + "</b></li>";
                                }
                            }

                            if (response.data.warning_log != undefined) {
                                let warning_log = response.data.warning_log;
                                // Loop through change log add as list
                                for (var i = 0; i < warning_log.length; i++) {
                                    list_items += "<li>" + warning_log[i]['message'] + "</li>";
                                }
                            }

                            if (list_items) {
                                app.message = "<br><ul>" + list_items + "</ul>";
                            }

                            if (response.data.new_system != undefined && response.data.new_system) {
                                window.location.href = 'edit?id=' + response.data.new_system;
                            }
                        } else {
                            app.show_error = true;
                            app.show_success = false;
                            app.message = response.data.message;

                            if (response.data.error_log != undefined) {
                                let error_log = response.data.error_log;
                                app.message = 'Could not save form data<br><br><ul>';
                                // Loop through change log add as list
                                for (var i = 0; i < error_log.length; i++) {
                                    app.message += "<li><b>" + error_log[i]['key'] + "</b>: " + error_log[i]['message'] + "</li>";
                                }
                                app.message += '</ul>';
                            }
                        }
                    });
            },
            cancel: function() {
                window.location.href = path + 'system/list/public';
            },
            loadstats: function() {
                app.disable_loadstats = true;
                axios.get(path + 'system/loadstats?id=' + app.system.id)
                    .then(function(response) {
                        alert(response.data.message);
                        app.disable_loadstats = false;

                        // start periodic check for reload log
                        app.reloadlog();
                        reload_interval = setInterval(function() {
                            app.reloadlog();
                        }, 5000);
                    });
            },
            reloadlog: function() {
                axios.get(path + 'system/reloadlog?id=' + app.system.id)
                    .then(function(response) {
                        if (response.data) {
                            document.getElementById('reload_log').innerHTML = response.data;
                            document.getElementById('reload_log').style.display = 'block';

                            // check for string 'processed monthly systems' in log to stop
                            if (response.data.indexOf('processed monthly systems') > -1) {
                                clearInterval(reload_interval);
                            }
                        }
                    });
            },
            load_app: function() {
                var app_id = app.new_app_selection;

                var selected_app = null;
                for (var appx in app.available_apps) {
                    if (app.available_apps[appx].id == app_id) {
                        selected_app = app.available_apps[appx];
                        app.system.url = selected_app.url;
                    }
                }
            },
            load_data: function() {
                alert(system.url);
            },

            rules: function() {

                // Only show brine pumps for ground source and water source
                this.schema_groups['Metering']['metering_inc_brine_pumps'].show = (app.system.hp_type == 'Ground Source' || app.system.hp_type == 'Water Source') ? true : false;

                switch (app.system.dhw_method) {
                    case 'None':
                    case 'Other':
                    case '':
                        this.schema_groups['Hot water']['cylinder_volume'].show = false;
                        this.schema_groups['Hot water']['dhw_coil_hex_area'].show = false;
                        this.schema_groups['Hot water']['dhw_target_temperature'].show = false;
                        this.schema_groups['Hot water']['dhw_control_type'].show = false;
                        this.schema_groups['Hot water']['legionella_frequency'].show = false;
                        this.system.legionella_frequency = '';
                        break;
                    case "Cylinder with coil":
                        this.schema_groups['Hot water']['cylinder_volume'].show = true;
                        this.schema_groups['Hot water']['dhw_coil_hex_area'].show = true;
                        this.schema_groups['Hot water']['dhw_target_temperature'].show = true;
                        this.schema_groups['Hot water']['dhw_control_type'].show = true;
                        this.schema_groups['Hot water']['legionella_frequency'].show = true;
                        break;
                    case "Cylinder with plate heat exchanger":
                        this.schema_groups['Hot water']['cylinder_volume'].show = true;
                        this.schema_groups['Hot water']['dhw_coil_hex_area'].show = true;
                        this.schema_groups['Hot water']['dhw_target_temperature'].show = true;
                        this.schema_groups['Hot water']['dhw_control_type'].show = true;
                        this.schema_groups['Hot water']['legionella_frequency'].show = true;
                        break;
                    case "Thermal store (heat exchanger on output)":
                        this.schema_groups['Hot water']['cylinder_volume'].show = true;
                        this.schema_groups['Hot water']['dhw_coil_hex_area'].show = true;
                        this.schema_groups['Hot water']['dhw_target_temperature'].show = true;
                        this.schema_groups['Hot water']['dhw_control_type'].show = true;
                        this.schema_groups['Hot water']['legionella_frequency'].show = false;
                        this.system.legionella_frequency = '';
                        break;
                    case "Phase change store": 
                        this.schema_groups['Hot water']['cylinder_volume'].show = false;
                        this.schema_groups['Hot water']['dhw_coil_hex_area'].show = false;
                        this.schema_groups['Hot water']['dhw_target_temperature'].show = true;
                        this.schema_groups['Hot water']['dhw_control_type'].show = true;
                        this.schema_groups['Hot water']['legionella_frequency'].show = false;
                        this.system.legionella_frequency = '';
                        break;
                }

                this.schema_groups['Hot water']['legionella_target_temperature'].show = (app.system.legionella_frequency != 'Disabled' && app.system.legionella_frequency !='') ? true : false;
                this.schema_groups['Hot water']['legionella_immersion'].show = (app.system.legionella_frequency != 'Disabled' && app.system.legionella_frequency !='') ? true : false;
                this.schema_groups['Metering']['metering_inc_immersion'].show = (app.system.legionella_immersion) ? true : false;

                // if 'class 1' string in electric meter type
                if (app.system.electric_meter.indexOf('class 1') > -1 && app.system.heat_meter.indexOf('class 2') > -1) {
                    app.system.mid_metering = true;
                } else {
                    app.system.mid_metering = false;
                }

                // If uses_backup_heater, show metering_inc_boost
                this.schema_groups['Metering']['metering_inc_boost'].show = (app.system.uses_backup_heater) ? true : false;

                // If hydraulic seperation, show metering_inc_secondary_heating_pumps
                this.schema_groups['Metering']['metering_inc_secondary_heating_pumps'].show = (app.system.hydraulic_separation!='None' && app.system.hydraulic_separation!='') ? true : false;

                // If heat pump type is Air-to-Air
                if (app.system.hp_type == 'Air-to-Air') {
                    this.schema_groups['Metering']['metering_inc_central_heating_pumps'].show = false;
                    this.schema_groups['Misc']['freeze'].show = false;
                    this.schema_groups['Heat pump']['uses_backup_heater'].show = false;
                }
            },

            filter_schema_groups: function() {

                this.rules();

                var filtered_schema_groups = {};
                for (var group in this.schema_groups) {
                    var group_schema = this.schema_groups[group];
                    var filtered_group_schema = {};
                    var field_count = 0;
                    for (var key in group_schema) {
                        var field = group_schema[key];

                        if (field.show == undefined) field.show = true;

                        if (field.editable && field.show && (this.form_type == 'full' || !field.optional || field.basic)) {
                            filtered_group_schema[key] = field;
                            field_count++;
                        }
                    }
                    if (field_count > 0) {
                        filtered_schema_groups[group] = filtered_group_schema;
                    }
                }
                this.filtered_schema_groups = filtered_schema_groups;
            }
        },
    });

    app.filter_schema_groups();




    axios.get(path + 'system/monthly?id=' + app.system.id)
        .then(function(response) {
            app.monthly = response.data;
            // draw_chart();

        })
        .catch(function(error) {
            console.log(error);
        });

    axios.get(path + 'system/stats/all?id=' + app.system.id)
        .then(function(response) {
            app.all = response.data[app.system.id];
        })
        .catch(function(error) {
            console.log(error);
        });

    // Load available apps
    if (app.mode == 'edit') {
        axios.get(path + 'system/available')
            .then(function(response) {
                // Add in_use_msg
                for (var appx in response.data) {
                    if (app.system.url == response.data[appx].url) {
                        app.new_app_selection = response.data[appx].id;
                        continue;
                    }

                    if (response.data[appx].in_use == 1) {
                        response.data[appx].in_use_msg = ' (in use)';
                    } else {
                        response.data[appx].in_use_msg = '';
                    }
                }

                app.available_apps = response.data;
                console.log(app.available_apps);
            })
            .catch(function(error) {
                console.log(error);
            });
    }

</script>
