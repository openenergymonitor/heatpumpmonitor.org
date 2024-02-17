<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
    .sticky {
        position: sticky;
        top: 20px;
    }
</style>

<div id="app" class="bg-light">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container-fluid">

            <div style="float:right; margin-right:30px">
                <div class="input-group">
                    <span class="input-group-text">Stats time period</span>

                    <select class="form-control" v-model="stats_time_start" @change="stats_time_start_change" style="width:130px">
                        <option value="last30">Last 30 days</option>
                        <option value="last365">Last 365 days</option>
                        <option v-for="month in available_months_start">{{ month }}</option>
                    </select>
                    
                    <span class="input-group-text" v-if="stats_time_end!='only'">to</span>

                    <select class="form-control" v-model="stats_time_end" v-if="stats_time_start!='last30' && stats_time_start!='last365'" @change="stats_time_end_change" style="width:120px">
                        <option value="only">Only</option>
                        <option v-for="month in available_months_end">{{ month }}</option>
                    </select>
                </div>
            </div>

            <!--
            <div class="input-group" style="width:300px; float:right; margin-right:20px">
                <div class="input-group-text">Colour</div>
                <select class="form-control" v-model="colour_property" @change="axis_change">
                    <option value="UFH">Under Floor Heating</option>
                    <option value="new_radiators">New Radiators</option>
                    <option value="old_radiators">Old Radiators</option>
                </select>
            </div>
            -->
            
            <div class="input-group" style="width:300px; float:right; margin-right:20px">
                <div class="input-group-text">Y-axis</div>
                <select class="form-control" v-model="yaxis_property" @change="axis_change">
                    <optgroup v-for="(group, group_name) in column_groups" :label="group_name" v-if="group_name=='Stats: Combined' || group_name=='Stats: When Running' || group_name=='Stats: Space heating' || group_name=='Stats: Water heating'">
                        <option v-for="(row,key) in group" :value="row.key">{{ row.name }}</option>
                    </optgroup>
                </select>
            </div>

            <!--
            <div class="input-group" style="width:300px; float:right; margin-right:20px">
                <div class="input-group-text">X-axis</div>
                <select class="form-control" v-model="xaxis_property" @change="axis_change">
                    <option value="location">Location</option>
                    <option value="elec_kwh">Electricity (kWh)</option>
                </select>
            </div>
            -->

            <h3>Graph</h3>
        </div>
    </div>

    <div class="container-fluid" style="background-color:#fff; border-bottom:1px solid #ccc" v-show="chart_enable">
        <div class="row">
            <div id="chart"></div>
        </div>
    </div>

</div>

<script>

    var columns = <?php echo json_encode($columns); ?>;
    var stats_columns = <?php echo json_encode($stats_columns); ?>;
    // remove stats_columns id & timestmap
    delete stats_columns.id;
    delete stats_columns.timestamp;

    // add stats_columns to columns
    for (var key in stats_columns) {
        columns[key] = stats_columns[key];
    }

    // convert to column groups
    var column_groups = {};
    for (var key in columns) {
        var column = columns[key];
        if (column_groups[column.group] == undefined) column_groups[column.group] = [];
        column_groups[column.group].push({key: key, name: column.name});
    }

    // Available months
    // Aug 2023, Jul 2023, Jun 2023 etc for 12 months
    var months = [];
    var d = new Date();
    for (var i = 0; i < 12; i++) {
        months.push(d.toLocaleString('default', { month: 'short' }) + ' ' + d.getFullYear());
        d.setMonth(d.getMonth() - 1);
    }

    var mode = "<?php echo $mode; ?>";

    var app = new Vue({
        el: '#app',
        data: {
            systems: <?php echo json_encode($systems); ?>,
            mode: "<?php echo $mode; ?>",
            chart_enable: true,
            columns: columns,
            column_groups: column_groups,
            currentSortColumn: 'combined_cop',
            currentSortDir: 'desc',
            stats_columns: stats_columns,
            // stats time selection
            stats_time_start: "last365",
            stats_time_end: "only",
            stats_time_range: false,
            available_months_start: months,
            available_months_end: months,

            colour_property: 'UFH',
            yaxis_property: 'combined_cop',
            xaxis_property: 'location'
        },
        methods: {
            axis_change: function() {
                draw_chart();
            },
            sort: function(column, starting_order) {

                if (this.currentSortColumn != column) {
                    this.currentSortDir = starting_order;
                    this.currentSortColumn = column;
                } else {
                    if (this.currentSortDir == 'desc') {
                        this.currentSortDir = 'asc';
                    } else {
                        this.currentSortDir = 'desc';
                    }
                }
                this.sort_only(column);
            },
            sort_only: function(column) {
                this.systems.sort((a, b) => {
                    let modifier = 1;
                    if (this.currentSortDir == 'desc') modifier = -1;
                    if (a[column] < b[column]) return -1 * modifier;
                    if (a[column] > b[column]) return 1 * modifier;
                    return 0;
                });
            },
            stats_time_start_change: function () {
                // change available_months_end to only show months after start
                if (this.stats_time_start=='last30' || this.stats_time_start=='last365') {
                    this.stats_time_end = 'only';
                } else {
                    let start_index = this.available_months_start.indexOf(this.stats_time_start);
                    this.available_months_end = this.available_months_start.slice(0,start_index); 

                    if (this.stats_time_end!='only') {
                        this.stats_time_end = this.available_months_end[0]; 
                    }
                }
                this.load_system_stats();
            },
            stats_time_end_change: function () {
                this.load_system_stats();
            },
            load_system_stats: function () {
                
                // Start
                let start = this.stats_time_start;
                if (start!='last30' && start!='last365') {
                    // Convert e.g Mar 2023 to 2023-03-01
                    let months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    let month = start.split(' ')[0];
                    let year = start.split(' ')[1];
                    start = year + '-' + (months.indexOf(month)+1) + '-01';
                }

                // End
                let end = this.stats_time_end;
                if (end!='only') {
                    // Convert e.g Mar 2023 to 2023-03-01
                    let months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    let month = end.split(' ')[0];
                    let year = end.split(' ')[1];
                    end = year + '-' + (months.indexOf(month)+1) + '-01';
                } else {
                    end = start;
                }

                var url = path+'system/stats';
                var params = {
                    start: start,
                    end: end
                };

                if (start == 'last30' || start == 'last365') {
                    url = path+'system/stats/'+start;
                    params = {};
                }
                // Load system/stats data
                axios.get(url, {
                        params: params
                    })
                    .then(response => {
                        var stats = response.data;
                        for (var i = 0; i < app.systems.length; i++) {
                            let id = app.systems[i].id;
                            if (stats[id]) {
                                // copy stats data to system
                                for (var key in stats[id]) {
                                    app.systems[i][key] = stats[id][key];
                                }
                            } else {
                                for (var col in stats_columns) {
                                    app.systems[i][stats_columns[col]] = 0;
                                }
                            }
                        }
                        // sort
                        app.sort_only(app.currentSortColumn);
                        if (app.chart_enable) draw_chart();
                        
                    })
                    .catch(error => {
                        alert("Error loading data: " + error);
                    });
            }
        }
    });

    init_chart();
    
    app.load_system_stats();
    

    function init_chart() {
        chart_options = {
            colors_style_guidlines: ['#29ABE2'],
            colors: ['#29AAE3'],
            chart: {
                type: 'bar',
                height: 600,
                toolbar: {
                    show: false
                }
            },
            plotOptions: {
                bar: {
                    distributed: true
                },
            },
            dataLabels: {
                enabled: false
            },
            series: [ ],
            xaxis: { 
                categories: [],
                title: {
                    text: 'Location'
                }
            },
            legend: {
                show: false
            },
        };

        last_type = 'bar';

        // y-axis label

        chart = new ApexCharts(document.querySelector("#chart"), chart_options);
        chart.render();
    }

    function draw_chart() {
        app.sort_only(app.yaxis_property);

        var x = [];
        var y = [];
        var colors = [];
        var xy = [];
        
        var time = (new Date()).getTime()*0.001;

        for (var i = 0; i < app.systems.length; i++) {
            let yvalue = app.systems[i][app.yaxis_property];
            let xvalue = app.systems[i][app.xaxis_property];


            if (app.yaxis_property == 'combined_cop' || app.yaxis_property == 'when_running_cop' || app.yaxis_property == 'when_running_carnot_prc' || app.yaxis_property == 'elec_kwh' ) {
                if (yvalue<0) yvalue = null;
            }
            
            if (app.yaxis_property == 'data_start') {
                if (yvalue!=0) {
                    yvalue = Math.round((time - yvalue) / (24*3600));
                } else {
                    yvalue = 0;
                }
            }

            if (yvalue) {

                /*
                if (app.systems[i][app.colour_property] == 1) {
                    colors.push('#E2AB29');
                } else {
                    colors.push('#29ABE2');
                }*/
                colors.push('#29ABE2');
                x.push(xvalue);
                y.push(yvalue);
                xy.push([xvalue, yvalue]);
            }
        }

        //chart_options.xaxis.categories = x;
        chart_options.series = [{
            name: app.columns[app.yaxis_property].name  
        }];

        if (app.xaxis_property!='location') {
            chart_options.xaxis.categories = [];
            chart_options.series[0].data = xy;
            chart_options.chart.type = 'scatter';
        } else {
            chart_options.xaxis.categories = x;
            chart_options.series[0].data = y;
            chart_options.chart.type = 'bar';
        }

        var reset = false;
        if (chart_options.chart.type != last_type) {
            reset = true;
        }

        last_type = chart_options.chart.type;

        // change the colour of the bar based on the y-axis property

        chart_options.yaxis = {
            title: {
                text: app.columns[app.yaxis_property].name
            }
        }
        chart_options.colors = colors;

        if (reset) {
            chart.destroy();
            chart = new ApexCharts(document.querySelector("#chart"), chart_options);
            chart.render();
        } else {
            chart.updateOptions(chart_options);
        }
    }

</script>
