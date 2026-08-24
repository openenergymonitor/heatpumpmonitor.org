<?php
defined('EMONCMS_EXEC') or die('Restricted access');
?>
<script src="<?php echo $path; ?>theme/vendor/vue-2.7.16/vue.min.js" integrity="sha384-YVYXhPGIH/Gmcr0W5Rin4PcpcsG1a4pcdUUod1CnbDEJut7XiUaJtSlNKeRLJBPk"></script>
<script src="<?php echo $path; ?>theme/vendor/axios-1.4.0/axios.min.js" integrity="sha384-I4Qw/vWb/sK/7VwepTtkaq636YLYClbEgEwKp3ueUCvjiLFrcoKUFAY5mOl40Fj3"></script>
<script src="<?php echo $path; ?>theme/vendor/chart.js-4.4.1/chart.umd.js" integrity="sha384-dug+JxfBvklEQdJ4AYuBBAIScUz0bVN73xpy273gcAwHjb3qI0fXmuYNaNfdyYJG"></script>

<div id="app">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container-fluid">
            <h3>Monthly</h3>
        </div>
    </div>

    <div class="container-fluid" style="margin-top:20px">
        <div class="row">
            <div class="col-md-5">
                <div class="input-group mb-3"> 
                    <span class="input-group-text">Chart mode</span>
                    <select class="form-control" v-model="chart_yaxis" @change="change_chart_mode">
                        <optgroup v-for="(group, group_name) in system_stats_monthly_by_group" :label="group_name">
                            <option v-for="(row,key) in group" :value="key">{{ row.name }}</option>
                        </optgroup>
                    </select>
                </div>
                <br>

                <p>Select systems...</p>
                <!-- Add your table here -->
                <table class="table">
                    <tr>
                        <th>Color</th>
                        <th>System</th>
                        <th></th>
                    </tr>
                    <tr v-for="system,idx in selected_systems">
                        <td><input class="form-control" type="color" v-model="system.color" @change="change_color"></td>
                        <td>
                            <select class="form-control" v-model="system.id" @change="change_system(idx)">
                                <option v-for="s,i in system_list" :value="s.id">{{ s.location }}, {{ s.hp_manufacturer }} {{ s.hp_model }}, {{ s.hp_output }} kW</option>
                            </select>
                        </td>
                        <td><button class="btn btn-danger" @click="remove_system">Delete</button></td>
                    </tr>
                </table>
                <button class="btn btn-primary" @click="add_system">+ Add system</button>

            </div>
            <div class="col-md-7">
                <!-- Add your flot chart here -->
                <div style="position:relative; height:600px"><canvas id="chart"></canvas></div>

            </div>
        </div>
    </div>
</div>

<script>
    var id = <?php echo $systemid; ?>;
    
    var colours = ["#fec601","#ea7317","#73bfb8","#3da5d9","#2364aa"];

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

    var app = new Vue({
        el: '#app',
        data: {
            system_stats_monthly_by_group: system_stats_monthly_by_group,
            system_list: [],
            selected_systems: [
                {id: id, color: colours[0], monthly: []}
            ],
            chart_yaxis: 'combined_cop',
        },
        methods: {
            change_chart_mode: function() {
                draw_chart();
            },
            add_system: function () {
                if (this.selected_systems.length == 0) {
                    // add empty system
                    this.selected_systems.push({id: 1, color: colours[0], monthly: []});
                    load_system_data(0);
                    draw_chart();
                } else {
                    // add copy of last system
                    this.selected_systems.push(JSON.parse(JSON.stringify(this.selected_systems[this.selected_systems.length-1])));
                    this.selected_systems[this.selected_systems.length-1].color = colours[this.selected_systems.length-1];
                    draw_chart();
                }
                
            },
            change_color: function() {
                draw_chart();
            },
            change_system: function(idx) {
                load_system_data(idx);
                draw_chart();
            },
            remove_system: function(idx) {
                this.selected_systems.splice(idx, 1);
                draw_chart();
            }
        }
    });

    var chart = null;
    var chart_options = {
        type: 'bar',
        data: { labels: [], datasets: [] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: { display: true, position: 'top' },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            var v = ctx.parsed.y;
                            return ctx.dataset.label + ': ' + (v == null ? '' : +v.toFixed(3));
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { maxRotation: 45, autoSkip: true }
                },
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'COP' }
                }
            }
        }
    };

    axios.get(path+"system/list/public.json")
        .then(function (response) {
            app.system_list = response.data;

            // Load system data
            for (var i = 0; i < app.selected_systems.length; i++) {
                load_system_data(i);
            }
        })
        .catch(function (error) {
            console.log(error);
        });



    chart = new Chart(document.getElementById("chart"), chart_options);

    function load_system_data(idx) {
        var system = app.selected_systems[idx];

        axios.get(path + 'system/stats/monthly?id=' + system.id)
            .then(function(response) {
                app.selected_systems[idx].monthly = response.data;             
                draw_chart();
            })
            .catch(function(error) {
                console.log(error);
            });
    }

    function draw_chart() {
        chart_options.options.scales.y.title.text = system_stats_monthly[app.chart_yaxis].name;

        var datasets = [];
        var labels = [];

        for (var i in app.selected_systems) {
            let system = app.selected_systems[i];
            let x = [];
            let y = [];

            for (var j = 0; j < system.monthly.length; j++) {
                x.push(month_label(system.monthly[j]['timestamp']));
                y.push(system.monthly[j][app.chart_yaxis]);
            }
            var idx = get_system_index(system.id);
            if (idx == undefined) continue;

            labels = x;
            datasets.push({
                label: app.system_list[idx].location+" "+app.system_list[idx].hp_model+" "+app.system_list[idx].hp_output+" kW",
                data: y,
                backgroundColor: system.color,
                borderColor: system.color,
                borderWidth: 0
            });
        }
        chart_options.data.labels = labels;
        chart_options.data.datasets = datasets;
        chart.update();
    }

    function month_label(timestamp) {
        return new Date(timestamp * 1000).toLocaleDateString('en-GB', {month: 'short', year: 'numeric'});
    }

    function get_system_index(system_id) {
        // find system_id in app.system_list 
        for (var i = 0; i < app.system_list.length; i++) {
            if (app.system_list[i].id == system_id) {
                return i;
            }
        }
    }

</script>
