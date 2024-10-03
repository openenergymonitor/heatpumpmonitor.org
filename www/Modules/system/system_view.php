<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');
global $settings;
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
            <button class="btn btn-primary mb-3"  @click="open_emoncms_dashboard" v-if="system.id"><span class="d-none d-lg-inline-block">Emoncms</span> Dashboard</button>
            <button class="btn btn-secondary mb-3"  @click="open_heatloss_tool" v-if="system.id" >Heat demand <span class="d-none d-lg-inline-block">tool</span></button>  
            <button class="btn btn-secondary mb-3"  @click="open_monthly_tool" v-if="system.id" >Monthly</button>
            <button class="btn btn-secondary mb-3"  @click="open_daily_tool" v-if="system.id" >Daily</button>
            <button class="btn btn-secondary mb-3"  @click="open_compare_tool" v-if="system.id" >Compare</button>              
            <button class="btn btn-secondary mb-3"  @click="open_histogram_tool" v-if="system.id" >Histogram</button>  
            <button class="btn btn-warning mb-3" style="margin-left:10px" v-if="admin && mode=='view'" @click="mode='edit'">Edit</button>
            <!--<button class="btn btn-light mb-3" style="margin-left:10px" v-if="admin && mode=='edit'" @click="mode='view'">Cancel</button>-->

            <h3 v-if="!system.id">New System</h3>
        </div>
    </div>
    <br>

    <div class="container mt-3" style="max-width:800px" v-if="system.url!='' && last365!=undefined && last30!=undefined">


        <div class="card mt-3" v-if="last30.combined_data_length!=last365.combined_data_length">
            <h5 class="card-header">Last 365 days</h5>
            <div class="card-body">
                <div class="row" style="text-align:center">
                    <div class="col">
                        <h5>Electric</h5>
                        <h4>{{ last365.combined_elec_kwh | toFixed(0) }} kWh</h4>
                    </div>
                    
                    <div class="col">
                        <h5>Heat Output</h5>
                        <h4>{{ last365.combined_heat_kwh | toFixed(0) }} kWh</h4>
                    </div>
                    
                    <div class="col">
                        <h5 title="Seasonal performance factor">SPF</h5>
                        <h4>{{ last365.combined_cop | toFixed(1) }}</h4>            
                    </div>    
                </div>      
            </div>
        </div>
        <div class="card mt-3">
        <h5 class="card-header">Last 30 days</h5>
            <div class="card-body">
                <div class="row" style="text-align:center">
                    <div class="col">
                        <h5>Electric</h5>
                        <h4>{{ last30.combined_elec_kwh | toFixed(0) }} kWh</h4>
                    </div>
                    
                    <div class="col">
                        <h5>Heat Output</h5>
                        <h4>{{ last30.combined_heat_kwh | toFixed(0) }} kWh</h4>
                    </div>
                    
                    <div class="col">
                        <h5>COP</h5>
                        <h4>{{ last30.combined_cop | toFixed(1) }}</h4>            
                    </div>    
                </div>
                <hr>
                <div class="row" style="text-align:center">
                    <div class="col">
                        Stats when running
                    </div>  
                </div>
                <div class="row mt-2" style="text-align:center">
                    <div class="col">
                        <b>Electric</b><br>
                        {{ last30.running_elec_kwh | toFixed(0) }} kWh
                    </div>  
                    <div class="col">
                        <b>Heat</b><br>
                        {{ last30.running_heat_kwh | toFixed(0) }} kWh
                    </div>
                    <div class="col">
                        <b>COP</b><br>
                        {{ last30.running_cop | toFixed(1) }}
                    </div>  
                    <div class="col">
                        <b>FlowT</b><br>
                        {{ last30.running_flowT_mean | toFixed(1) }} °C
                    </div>
                    <div class="col">
                        <b>OutsideT</b><br>
                        {{ last30.running_outsideT_mean | toFixed(1) }} °C
                    </div>
                    <div class="col">
                        <b>Carnot</b><br>
                        {{ last30.running_prc_carnot }}%
                    </div>
                </div>      
            </div>
        </div>

        <div class="card mt-3">
            <h5 class="card-header">Monthly data</h5>
            <div class="card-body">
                <div class="input-group mb-3"> 
                    <span class="input-group-text">Chart mode</span>
                    <select class="form-control" v-model="chart_yaxis" @change="change_chart_mode">
                        <optgroup v-for="(group, group_name) in system_stats_monthly_by_group" :label="group_name">
                            <option v-for="(row,key) in group" :value="key">{{ row.name }}</option>
                        </optgroup>
                    </select>
                </div>
                <div id="chart"></div>
            </div>
        </div>    

        <div class="card mt-3">
            <h5 class="card-header">Data Quality</h5>
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

    <div class="container mt-3" style="max-width:800px" v-if="mode=='edit'">
        <div class="card mt-3">
            <h5 class="card-header">Select Emoncms.org dashboard</h5>
            <div class="card-body">

                <select class="form-select"  style="width:100%" v-model="new_app_selection" @change="load_app">
                    <option value="">PLEASE SELECT</option>
                    <option v-for="(app,index) in available_apps" :value="app.id" :disabled="app.in_use==1">{{ app.username }}: {{ app.name }} {{ app.in_use_msg }}</option>
                </select>

                <div class="input-group mt-3" v-if="admin">
                    <span class="input-group-text">URL</span>
                    <input type="text" class="form-control" v-model="system.url">
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-3" style="max-width:800px">


        <div class="row" v-if="system.url!=''">
            <h4>Form data</h4>
            <p>Information about this system...</p>
            <table class="table">
                <tbody v-for="group,group_name in schema_groups">
                    <tr>
                        <th style="background-color:#f0f0f0;">{{ group_name }}</th>
                        <td style="background-color:#f0f0f0;"></td>
                        <td style="background-color:#f0f0f0;"></td>
                    </tr>
                    <tr v-for="(field,key) in group" v-if="field.editable && key!='share' && !field.hide_on_form">
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
                                <input type="checkbox" v-model="system[key]" :disabled="mode=='view'">
                            </span>
                            <span v-if="field.type!='tinyint(1)'">
                                <!-- Edit mode text input -->
                                <div class="input-group" v-if="mode=='edit' && !field.options">
                                    <input class="form-control" type="text" v-model="system[key]">
                                    <span class="input-group-text" v-if="field.unit">{{ field.unit }}</span>
                                </div>
                                <!-- View mode select input -->
                                <select class="form-control" v-if="mode=='edit' && field.options" v-model="system[key]">
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
        if (schema[key].group && schema[key].editable && !schema[key].hide_on_form) {
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

    var app = new Vue({
        el: '#app',
        data: {
            new_app_selection: '',
            available_apps: [],
            path: path,
            mode: "<?php echo $mode; ?>", // edit, view
            system: <?php echo json_encode($system_data); ?>,
            monthly: [],
            last30: [],
            last365: [],
            schema_groups: schema_groups,

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
            change_chart_mode: function() {
                draw_chart();
            },
            open_emoncms_dashboard: function() {
                window.open(app.system.url);
            },
            open_heatloss_tool: function() {
                window.location = path+"heatloss?id="+app.system.id;
            },
            open_monthly_tool: function() {
                window.location = path+"monthly?id="+app.system.id;
            },
            open_daily_tool: function() {
                window.location = path+"daily?id="+app.system.id;
            },
            open_histogram_tool: function() {
                window.location = path+"histogram?id="+app.system.id;
            },
            open_compare_tool: function() {
                window.location = path+"compare?id="+app.system.id;
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
            }
        },
    });

    // CHART

    chart_options = {
        colors_style_guidlines: ['#29ABE2'],
        colors: ['#29AAE3'],
        chart: {
            type: 'bar',
            height: 300,
            toolbar: {
                show: false
            }
        },
        dataLabels: {
            enabled: false
        },
        series: [],
        xaxis: {
            categories: [],
            type: 'datetime',
            labels: {
                datetimeUTC: false,
                // format month and year
                formatter: function(value, timestamp, opts) {
                    return new Date(timestamp).toLocaleDateString('default', { month: 'short', year: 'numeric' });
                }
            }
        },
        yaxis: {
            title: {
                text: 'COP'
            }
        }
    };
    chart = new ApexCharts(document.querySelector("#chart"), chart_options);
    chart.render();

    axios.get(path + 'system/monthly?id=' + app.system.id)
        .then(function(response) {
            app.monthly = response.data;
            draw_chart();

        })
        .catch(function(error) {
            console.log(error);
        });

    axios.get(path + 'system/stats/last365?id=' + app.system.id)
        .then(function(response) {
            app.last365 = response.data[app.system.id];
        })
        .catch(function(error) {
            console.log(error);
        });

    axios.get(path + 'system/stats/last30?id=' + app.system.id)
        .then(function(response) {
            app.last30 = response.data[app.system.id];
        })
        .catch(function(error) {
            console.log(error);
        });

    function draw_chart() {

        var x = [];
        var y = [];

        // 12 months of dummy data peak in winter
        for (var i = 0; i < app.monthly.length; i++) {
            x.push(app.monthly[i]['timestamp'] * 1000);
            y.push(app.monthly[i][app.chart_yaxis]);
        }

        chart_options.xaxis.categories = x;
        chart_options.series = [{
            name: system_stats_monthly[app.chart_yaxis].name,
            data: y
        }];

        chart_options.yaxis = {
            title: {
                text: system_stats_monthly[app.chart_yaxis].name
            }
        }

        chart.updateOptions(chart_options);
    }

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
