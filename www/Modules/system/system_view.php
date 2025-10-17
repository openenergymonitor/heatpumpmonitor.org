<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');
global $settings, $session, $path;
?>
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>

<link rel="stylesheet" href="<?php echo $path; ?>Lib/autocomplete.css?v=4">
<script src="<?php echo $path; ?>Lib/autocomplete.js?v=10"></script>

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
                <h3>{{ system.hp_output }} kW, {{ system.hp_manufacturer }} {{ system.hp_model }}</h3>
                <p>{{ system.location }}, <span v-if="system.installer_name"><a :href="system.installer_url">{{ system.installer_name }}</a></span></p>
            </div>
            <a v-if="system.id" :href="path+'dashboard?id='+system.id"><button class="btn btn-primary mb-3"><span class="d-none d-lg-inline-block">Emoncms</span> Dashboard</button></a>
            <a v-if="system.id" :href="path+'heatloss?id='+system.id"><button class="btn btn-secondary mb-3">Heat demand <span class="d-none d-lg-inline-block">tool</span></button></a>
            <a v-if="system.id" :href="path+'monthly?id='+system.id"><button class="btn btn-secondary mb-3">Monthly</button></a>
            <a v-if="system.id" :href="path+'daily?id='+system.id"><button class="btn btn-secondary mb-3">Daily</button></a>
            <a v-if="system.id" :href="path+'compare?id='+system.id"><button class="btn btn-secondary mb-3">Compare</button></a>              
            <a v-if="system.id" :href="path+'histogram?id='+system.id"><button class="btn btn-secondary mb-3">Histogram</button></a>
            <button class="btn btn-warning mb-3" style="margin-left:10px" v-if="admin && mode=='view'" @click="switch_mode('edit')">Edit</button>

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
                        <td v-for="month in monthly" :style="{ backgroundColor: qualityColor(month.quality_elec), color: qualityColorText(month.quality_elec) }">
                        {{ month.quality_elec | toFixed(0) }}
                        </td>
                    </tr>
                    <tr>
                        <td>Heat</td>
                        <td v-for="month in monthly" :style="{ backgroundColor: qualityColor(month.quality_heat), color: qualityColorText(month.quality_heat) }">
                            {{ month.quality_heat | toFixed(0) }}
                        </td>
                    </tr>
                    <tr>
                        <td>Flow</td>
                        <td v-for="month in monthly" :style="{ backgroundColor: qualityColor(month.quality_flowT), color: qualityColorText(month.quality_flowT) }">
                            {{ month.quality_flowT | toFixed(0) }}
                        </td>
                    </tr>
                    <tr>
                        <td>Return</td>
                        <td v-for="month in monthly" :style="{ backgroundColor: qualityColor(month.quality_returnT), color: qualityColorText(month.quality_returnT) }">
                            {{ month.quality_returnT | toFixed(0) }}
                        </td>
                    </tr>
                    <tr>
                        <td>Outside</td>
                        <td v-for="month in monthly" :style="{ backgroundColor: qualityColor(month.quality_outsideT), color: qualityColorText(month.quality_outsideT) }">
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
                <div v-if="loading_available_apps" class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading available dashboards...</p>
                </div>
                <select v-else class="form-select"  style="width:100%" v-model="new_app_selection" @change="load_app">
                    <option value="">PLEASE SELECT</option>
                    <option v-for="(app,index) in available_apps" :value="app.id" :disabled="app.in_use==1">{{ app.username }}: {{ app.name }} {{ app.in_use_msg }}</option>
                </select>
            </div>
        </div>
    </div>

    <div class="container mt-3" style="max-width:800px" v-if="mode=='edit'">

        <div class="row">
            <div class="col-4">
                <div class="input-group mt-3">
                    <span class="input-group-text">App ID</span>
                    <input type="text" class="form-control" v-model="system.app_id" placeholder="App ID" disabled>
                </div>
            </div>
            <div class="col">
                <div class="input-group mt-3">
                    <span class="input-group-text">Read API Key</span>
                    <input type="text" class="form-control" v-model="system.readkey" placeholder="Read API Key" disabled>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <div class="input-group mt-3">
                    <span class="input-group-text">URL</span>
                    <input type="text" class="form-control" v-model="system.url" placeholder="Full app URL" disabled>
                </div>
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

            <table class="table mt-3">
                <tbody>
                    <tr>
                        <th style="background-color:#f0f0f0;">Heat pump</th>
                        <td style="background-color:#f0f0f0;"></td>
                        <td style="background-color:#f0f0f0;"></td>
                    </tr>
                    <tr>
                        <td>
                            <span>Manufacturer</span> <span v-if="mode=='edit'" style="color:#aa0000">*</span>
                        </td>
                        <td></td>
                        <td>
                            <div class="input-group" v-if="mode=='edit'">
                                <input id="heatpumpManufacturer" v-model="system.hp_manufacturer" class="form-control" type="text" placeholder="Manufacturer name" required>
                            </div>
                            <span v-if="mode=='view'">{{ system.hp_manufacturer }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span>Model</span> <span v-if="mode=='edit'" style="color:#aa0000">*</span>
                        </td>
                        <td></td>
                        <td>
                            <div class="input-group" v-if="mode=='edit'">
                                <input id="heatpumpModel" v-model="system.hp_model" class="form-control" type="text" placeholder="Model name" required>
                            </div>
                            <span v-if="mode=='view'">{{ system.hp_model }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span>Refrigerant</span>
                        </td>
                        <td></td>
                        <td>
                            <select class="form-control" v-if="mode=='edit'" v-model="system.refrigerant">
                                <option value="" v-if="refrigerants.length>1">Select refrigerant...</option>
                                <option v-for="refrigerant in refrigerants" :value="refrigerant">
                                    {{ refrigerant }}
                                </option>
                            </select>
                            <span v-if="mode=='view'">{{ system.refrigerant }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span>Type</span>
                        </td>
                        <td></td>
                        <td>
                            <select class="form-select" v-if="mode=='edit'" v-model="system.hp_type">
                                <option value="" v-if="types.length>1">Select type...</option>
                                <option v-for="type in types">{{ type }}</option>
                            </select>
                            <span v-if="mode=='view'">{{ system.hp_type }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span>Badge Capacity (kW)</span> <span v-if="mode=='edit'" style="color:#aa0000">*</span>
                        </td>
                        <td></td>
                        <td>
                            <div class="input-group" v-if="mode=='edit'">
                                <input v-model="system.hp_output" class="form-control" type="number" step="0.1" placeholder="e.g. 5.0" required>
                                <span class="input-group-text">kW</span>
                            </div>
                            <span v-if="mode=='view'">{{ system.hp_output }}</span> <span v-if="mode=='view'" style="color:#666; font-size:14px">kW</span>
                        </td>
                    </tr>
                    <tr>
                        <td><span>Heat pump has backup heater installed and in use</span> <!----></td> 
                        <td><span data-bs-toggle="tooltip" data-bs-placement="top" title="This is an inline electric element that can top up the heat pump output mostly for space heating"><i class="fas fa-question-circle"></i></span></td>
                        <td><span><input type="checkbox" v-model="system.uses_backup_heater"></span> <!----></td>
                    </tr>
                </tbody>

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

    // Reset to these values if model is not set
    var all_refrigerants = ["R290", "R32", "CO2", "R410A", "R210A", "R134A", "R407C", "R454C", "R452B"];
    var all_types = ["Air Source", "Ground Source", "Water Source", "Air-to-Air", "Other"];

    var app = new Vue({
        el: '#app',
        data: {
            session_userid: <?php echo $session['userid']; ?>,
            form_type: form_type,
            new_app_selection: '',
            available_apps: [],
            loading_available_apps: false,
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
            disable_loadstats: false,

            manufacturers: [],
            heatpump_models: [],
            hp_refrigerant: '',
            hp_type: '',
            hp_capacity: '',
            refrigerants: all_refrigerants,
            types: all_types

        },
        computed: {
            qualityColor() {
                return function(score) {
                    const hue = (score / 100) * 120; // Map score to hue value (0-120)
                    // if score = 0 grey
                    if (score == 0) return '#ccc';
                    return `hsl(${hue}, 100%, 50%)`; // Convert hue value to HSL color
                }
            },
            qualityColorText() {
                return function(score) {
                    if (score == 0) return '#000'; // Black text for grey background (#ccc has ~80% lightness)
                    
                    // Calculate perceived brightness of the HSL color
                    const hue = (score / 100) * 120; // Same calculation as qualityColor
                    const saturation = 100;
                    const lightness = 50;
                    
                    // Convert HSL to RGB for luminance calculation
                    const c = (1 - Math.abs(2 * lightness/100 - 1)) * saturation/100;
                    const x = c * (1 - Math.abs((hue / 60) % 2 - 1));
                    const m = lightness/100 - c/2;
                    
                    let r, g, b;
                    if (hue >= 0 && hue < 60) {
                        r = c; g = x; b = 0;
                    } else if (hue >= 60 && hue < 120) {
                        r = x; g = c; b = 0;
                    } else if (hue >= 120 && hue < 180) {
                        r = 0; g = c; b = x;
                    } else if (hue >= 180 && hue < 240) {
                        r = 0; g = x; b = c;
                    } else if (hue >= 240 && hue < 300) {
                        r = x; g = 0; b = c;
                    } else {
                        r = c; g = 0; b = x;
                    }
                    
                    r = (r + m) * 255;
                    g = (g + m) * 255;
                    b = (b + m) * 255;
                    
                    // Calculate relative luminance using WCAG formula
                    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                    
                    // Use white text for dark backgrounds (luminance < 0.5), black for light
                    return luminance < 0.5 ? '#fff' : '#000';
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
            switch_mode: function(mode) {
                this.mode = mode;
                if (mode == 'edit') {
                    this.$nextTick(() => {
                        this.init_autocomplete();
                    });
                }
            },
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
                console.log('Loading app', app_id);

                var selected_app = null;
                for (var appx in app.available_apps) {
                    if (app.available_apps[appx].id == app_id) {
                        selected_app = app.available_apps[appx];
                        app.system.url = selected_app.url;
                        app.system.app_id = selected_app.id;
                        app.system.readkey = selected_app.readkey;
                    }
                }
                
                this.$nextTick(() => {
                    this.init_autocomplete();
                });

            },
            load_data: function() {
                alert(system.url);
            },

            init_autocomplete: function() {

                // Initialize autocomplete for heatpump manufacturer
                autocomplete(
                    document.getElementById("heatpumpManufacturer"), 
                    this.manufacturers.map(m => m.name),
                    function(manufacturer) {
                        app.system.hp_manufacturer = manufacturer;
                        app.filter_by_manufacturer();
                    }
                );

                let uniqueHeatpumpNames = [...new Set(this.heatpump_models.map(h => h.name))];

                // Initialize autocomplete for heatpump model
                autocomplete(
                    document.getElementById("heatpumpModel"), 
                    uniqueHeatpumpNames, 
                    function(model) {
                        app.system.hp_model = model;
                        app.filter_refrigerant_and_type();
                    }
                );
            },

            filter_by_manufacturer: function() {
                let uniqueHeatpumpNames = [];
                for (let i = 0; i < this.heatpump_models.length; i++) {
                    if (this.heatpump_models[i].manufacturer_name == this.system.hp_manufacturer) {
                        uniqueHeatpumpNames.push(this.heatpump_models[i].name);
                    }
                }
                uniqueHeatpumpNames = [...new Set(uniqueHeatpumpNames)]; // Remove duplicates
                autocomplete(
                    document.getElementById("heatpumpModel"), 
                    uniqueHeatpumpNames,
                    function(model) {
                        app.system.hp_model = model;
                        app.filter_refrigerant_and_type();
                    }
                );
            },

            filter_refrigerant_and_type: function() {

                // If no model selected, reset refrigerants and type
                if (this.system.hp_model == '') {
                    this.refrigerants = all_refrigerants;
                    this.types = all_types;
                    this.system.refrigerant = '';
                    this.system.hp_type = '';
                    return;
                }

                // Filter refrigerants based on model
                let refrigerants = [];
                let types = [];
                for (let i = 0; i < this.heatpump_models.length; i++) {
                    if (this.heatpump_models[i].name == this.system.hp_model) {
                        refrigerants.push(this.heatpump_models[i].refrigerant);
                        types.push(this.heatpump_models[i].type);
                    }
                }
                refrigerants = [...new Set(refrigerants)];  // Remove duplicates
                types = [...new Set(types)];                // Remove duplicates
                this.refrigerants = refrigerants;
                this.types = types;

                // Set refrigerant and source based on single option or empty
                this.system.refrigerant = refrigerants.length == 1 ? refrigerants[0] : '';
                this.system.hp_type = types.length == 1 ? types[0] : '';
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
                    app.system.freeze = 'Not applicable';
                }
                // If hp_type is Ground Source or Water Source, hide freeze
                else if (app.system.hp_type == 'Ground Source' || app.system.hp_type == 'Water Source') {
                    this.schema_groups['Misc']['freeze'].show = false;
                    app.system.freeze = 'Not applicable';
                }
                // If hp_type is Air-to-Water
                else {
                    this.schema_groups['Metering']['metering_inc_central_heating_pumps'].show = true;
                    this.schema_groups['Misc']['freeze'].show = true;
                }
                

                // IF admin
                if (app.admin) {
                    this.schema_groups['Measurements']['measured_max_flow_temp_coldest_day'].show = true;
                    this.schema_groups['Measurements']['measured_mean_flow_temp_coldest_day'].show = true;
                    this.schema_groups['Measurements']['measured_outside_temp_coldest_day'].show = true;
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
            },

            load_manufacturers: function() {
                $.get(this.path+'manufacturer/list')
                    .done(response => {
                        // Order manufacturers by name alphabetically
                        response.sort((a, b) => a.name.localeCompare(b.name));
                        this.manufacturers = response;

                        app.load_heatpumps();
                    });
            },

            load_heatpumps: function() {
                $.get(this.path+'heatpump/list')
                    .done(response => {
                        app.heatpump_models = response;

                        if (app.mode == 'edit') {
                            app.init_autocomplete();
                        }
                    });
            },


        },
    });

    app.filter_schema_groups();
    app.load_manufacturers();

    axios.get(path + 'system/stats/monthly?id=' + app.system.id)
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
        app.loading_available_apps = true;
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
                app.loading_available_apps = false;
                console.log(app.available_apps);
            })
            .catch(function(error) {
                console.log(error);
                app.loading_available_apps = false;
            });
    }

</script>
