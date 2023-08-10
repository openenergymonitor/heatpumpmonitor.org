<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');
?>
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>


<div id="app" class="bg-light">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container" style="max-width:800px;">
            <h3>{{ system.location }}</h3>
        </div>
    </div>

    <div class="container mt-3" style="max-width:800px">
        <div class="row">
            <p>Last 30  days</p>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Elec: {{ last30.elec_kwh }} kWh</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Heat: {{ last30.heat_kwh }} kWh</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">SCOP: {{ last30.cop }}</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <p>Last 365  days</p>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Elec: {{ last365.elec_kwh }} kWh</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Heat: {{ last365.heat_kwh }} kWh</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">SCOP: {{ last365.cop }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="container mt-3" style="max-width:800px">
        <div class="row">
            <div id="chart"></div>
        </div>
    </div>

    <div class="container mt-3" style="max-width:800px">
        <div class="row">
            <table class="table">
                <tbody v-for="group,group_name in schema_groups">
                    <tr>
                        <th style="background-color:#f0f0f0;">{{ group_name }}</th>
                        <td style="background-color:#f0f0f0;"></td>
                        <td style="background-color:#f0f0f0;"></td>
                    </tr>
                    <tr v-for="(field,key) in group" v-if="field.editable && key!='share'">
                        <td>
                            <span>{{ field.name }}</span>
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
                                <span v-if="mode=='view'">{{ system[key] }}</span>
                            </span>
                        </td>
                    </tr>
                </tbody>

            </table>

        </div>
    </div>
    <div style=" background-color:#eee; padding-top:20px; padding-bottom:10px" v-if="mode=='edit'">
        <div class="container" style="max-width:800px;">
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
    var schema = <?php echo json_encode($schema); ?>;
    // arrange by group
    var schema_groups = {};
    for (var key in schema) {
        if (schema[key].group) {
            if (!schema_groups[schema[key].group]) {
                schema_groups[schema[key].group] = {};
            }
            schema_groups[schema[key].group][key] = schema[key];
        }
    }

    var app = new Vue({
        el: '#app',
        data: {
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
                window.location.href = path + 'system';
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
            type: 'datetime'
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
            y.push(app.monthly[i]['cop']);
        }

        chart_options.xaxis.categories = x;
        chart_options.series = [{
            name: 'COP',
            data: y
        }];

        chart.updateOptions(chart_options);
    }
</script>