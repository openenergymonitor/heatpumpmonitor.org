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

            <div class="row">
                <div class="col-12 col-sm-12 col-md-6 col-lg-6 col-xl-8">

                    <h3 v-if="mode=='user'">My Systems</h3>
                    <h3 v-if="mode=='admin'">Admin Systems</h3>

                    <p v-if="mode=='user'">Add, edit and view systems associated with your account.</p>
                    <p v-if="mode=='admin'">Add, edit and view all systems.</p>
                    
                    <p v-if="mode=='public'">Here you can see a variety of installations monitored with OpenEnergyMonitor, and compare detailed statistics to see how performance can vary.</p>
                    <p v-if="mode=='public'">If you're monitoring a heat pump with <b>emoncms</b> and the My Heat Pump app, <a href="<?php echo $path; ?>/user/login">login</a> to add your details.</p>
                    <p v-if="mode=='public'">To join in with discussion of the results, or for support please use the <a href="https://community.openenergymonitor.org/tag/heatpumpmonitor">OpenEnergyMonitor forums.</a></p> 
                    
                    <button v-if="mode!='public'" class="btn btn-primary" @click="create">Add new system</button>            
                </div>

                        
                <div class="col-12 col-sm-12 col-md-6 col-lg-6 col-xl-auto ms-auto">
                    <div class="input-group">
                        <span class="input-group-text">Stats time period</span>

                        <select class="form-control" v-model="stats_time_start" @change="stats_time_start_change" style="width:130px">
                            <option value="all">All</option>
                            <option value="last30">Last 30 days</option>
                            <option value="last365">Last 365 days</option>
                            <option v-for="month in available_months_start">{{ month }}</option>
                        </select>
                        
                        <span class="input-group-text" v-if="stats_time_end!='only'">to</span>

                        <select class="form-control" v-model="stats_time_end" v-if="stats_time_start!='all' && stats_time_start!='last30' && stats_time_start!='last365'" @change="stats_time_end_change" style="width:120px">
                            <option value="only">Only</option>
                            <option v-for="month in available_months_end">{{ month }}</option>
                        </select>
                        <button class="btn btn-primary" @click="toggle_chart"><i class="fa fa-chart-bar"></i></button>
                    </div>

                    <div class="input-group" style="margin-top: 12px">
                        <div class="input-group-text">Filter</div>
                        <input class="form-control" name="query" v-model="filterKey" style="width:100px">

                        <div class="input-group-text">Min days</div>
                        <input class="form-control" name="query" v-model="minDays" style="width:100px">  
                    </div>
            </div>

        </div>


    </div>

    <div class="container-fluid" style="background-color:#fff; border-bottom:1px solid #ccc" v-show="chart_enable">
        <div class="row">
            <div id="chart"></div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <!-- Side bar with field selection -->
            <div class="col-12 col-sm-12 col-md-12 col-lg-12 col-xl-2">
                <div class="card mt-3 sticky-card">
                    <div class="card-header">
                    <button class="btn btn-primary d-md-none" style="float:right" @click="showContent = !showContent">
                        <i :class="{'fas fa-chevron-up': showContent, 'fas fa-chevron-down': !showContent}"></i>
                    </button>                        
                    <h5>Select Fields</h5>
                    </div>
                    <div class="collapse show" :class="{ 'd-none': !showContent, 'd-md-block': showContent }">
                        <ul class="list-group list-group-flush" style="overflow-x:hidden; height:600px">
                        <template v-for="(group, group_name) in column_groups" v-if="!((stats_time_start=='last365' || stats_time_start=='all') && (group_name=='When Running' || group_name=='Standby'))">
                            <li class="list-group-item">
                                <b>{{ group_name }}</b>
                            </li>
                            <li v-for="column in group" class="list-group-item">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault" @click="select_column(column.key)" :checked="selected_columns.includes(column.key)">
                                <label class="form-check-label" for="flexCheckDefault">
                                {{ column.name }}
                                </label>
                            </div>
                            </li>
                        </template>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-12 col-md-12 col-lg-12 col-xl-10">

                <table id="custom" class="table table-striped table-sm mt-3">
                    <tr>
                        <th v-if="mode=='admin'" @click="sort('id', 'asc')" style="cursor:pointer">ID</th>
                        <th v-if="mode=='admin'" @click="sort('name', 'asc')" style="cursor:pointer">User
                            <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='name'"></i>
                        </th>
                        <th v-if="mode=='admin'">LINK</th>
                        <th v-for="column in selected_columns" @click="sort(column, 'desc')" style="cursor:pointer" :title="columns[column].helper">{{ columns[column].name }}
                            <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn==column"></i>
                        </th>
                        <th v-if="mode!='public' && public_mode_enabled">Status</th>
                        <th v-if="mode!='public'">Actions</th>
                        <th style="width:80px">View</th>
                    </tr>
                    <tr v-for="(system,index) in fSystems" v-if="mode!='public' || (mode=='public' && system.combined_data_length!=0)">
                        <td v-if="mode=='admin'">{{ system.id }}</td>
                        <td v-if="mode=='admin'" :title="system.username+'\n'+system.email"><span v-if="system.name">{{ system.name }}</span><span v-if="!system.name" style="color:#888">{{ system.username }}</span></td>
                        <td v-if="mode=='admin'"><a v-if="system.emoncmsorg_userid" :href="'https://emoncms.org/admin/setuser?id='+system.emoncmsorg_userid" target="_blank">{{ system.emoncmsorg_userid }}</a></td>
                        <td v-for="column in selected_columns" v-html="column_format(system,column)" v-bind:class="sinceClass(system,column)" style=""></td>
                        <td v-if="mode!='public' && public_mode_enabled">
                            <span v-if="system.share" class="badge bg-success">Shared</span>
                            <span v-if="!system.share" class="badge bg-danger">Private</span>
                            <span v-if="system.published" class="badge bg-success">Published</span>
                            <span v-if="!system.published" class="badge bg-secondary">Waiting for review</span>
                        </td>
                        <td v-if="mode!='public'">
                            <button class="btn btn-warning btn-sm" @click="edit(index)" title="Edit"><i class="fa fa-edit" style="color: #ffffff;"></i></button>
                            <button class="btn btn-danger btn-sm" @click="remove(index)" title="Delete"><i class="fa fa-trash" style="color: #ffffff;"></i></button>
                        </td>
                        <td>
                            <a :href="'<?php echo $path;?>system/view?id='+system.id">
                                <button class="btn btn-primary btn-sm" title="Summary"><i class="fa fa-list-alt" style="color: #ffffff;"></i></button>
                            </a>
                            <a :href="system.url">
                                <button class="btn btn-secondary btn-sm" title="Dashboard"><i class="fa fa-chart-bar" style="color: #ffffff;"></i></button>
                            </a> 
                        </td>
                    </tr>
                </table>
                
                <div class="card">
                  <h5 class="card-header">Totals</h5>
                  <div class="card-body">
                    <p class="card-text">Number of systems in selection: <b>{{ totals.count }}</b></p>
                    <p class="card-text">Average of individual system COP values: <b>{{ totals.average_cop | toFixed(1) }}</b></p>
                    <p class="card-text">Average COP based on total sum of heat and electric values: <b>{{ totals.average_cop_kwh | toFixed(1) }}</p>
                  </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>

    var columns = <?php echo json_encode($columns); ?>;
    var stats_columns = <?php echo json_encode($stats_columns); ?>;

    columns['hp_type'].name = "Source";
    columns['hp_model'].name = "Make & Model";
    columns['hp_output'].name = "Rating";
                    
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
        column_groups[column.group].push({key: key, name: column.name, helper: column.helper});
    }

    // create list of Stats, When Running, Standby columns
    // var stats_columns = [];
    // for (var key in columns) {
    //     var column = columns[key];
    //     if (column.group == 'Stats') stats_columns.push(key);
    //     if (column.group == 'When Running') stats_columns.push(key);
    //     if (column.group == 'Standby') stats_columns.push(key);
    // }

    // Available months
    // Aug 2023, Jul 2023, Jun 2023 etc for 12 months
    var months = [];
    var d = new Date();
    for (var i = 0; i < 12; i++) {
        months.push(d.toLocaleString('default', { month: 'short' }) + ' ' + d.getFullYear());
        d.setMonth(d.getMonth() - 1);
    }

    // get screen size
    var width = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
    var showContent = true;

    var mode = "<?php echo $mode; ?>";
    var selected_columns = [];
    
    var default_minDays = 0;
    var default_stats_time_start = "all";
    
    if (width>800) {
        showContent = true;
        if (mode == 'public') {
            selected_columns = ['location', 'installer_name', 'hp_type', 'hp_model', 'hp_output', 'combined_data_length', 'combined_cop'];
            default_minDays = 24;
            default_stats_time_start = "last30";
        } else {
            selected_columns = ['location','hp_model','combined_data_length','combined_cop','mid_metering'];
        }
    } else {
        showContent = false;
        if (mode == 'public') {
            selected_columns = ['installer_name', 'hp_model', 'hp_output', 'combined_cop']; 
            default_minDays = 24;
            default_stats_time_start = "last30";
        } else {
            selected_columns = ['location','hp_model','combined_cop'];
        }
    }

    var app = new Vue({
        el: '#app',
        data: {
            systems: <?php echo json_encode($systems); ?>,
            mode: "<?php echo $mode; ?>",
            chart_enable: false,
            columns: columns,
            column_groups: column_groups,
            selected_columns: selected_columns,
            currentSortColumn: 'combined_cop',
            currentSortDir: 'desc',
            // stats time selection
            stats_time_start: default_stats_time_start,
            stats_time_end: "only",
            stats_time_range: false,
            available_months_start: months,
            available_months_end: months,
            filterKey: window.location.hash.replace(/^#/, ''),
            minDays: default_minDays,
            showContent: showContent,
            public_mode_enabled: public_mode_enabled
        },
        methods: {
            create: function() {
                window.location = path+"system/new";
            },
            view: function(index) {
                // window.location = this.systems[index].url;
                let systemid = this.systems[index].id;
                window.location = path+"system/view?id=" + systemid;
            },
            edit: function(index) {
                let systemid = this.systems[index].id;
                window.location = path+"system/edit?id=" + systemid;
            },
            remove: function(index) {
                if (confirm("Are you sure you want to delete system: " + this.systems[index].location + "?")) {
                    // axios delete 
                    let systemid = this.systems[index].id;
                    axios.get(path+'system/delete?id=' + systemid)
                        .then(response => {
                            if (response.data.success) {
                                this.systems.splice(index, 1);
                            } else {
                                alert("Error deleting system: " + response.data.message);
                            }
                        })
                        .catch(error => {
                            alert("Error deleting system: " + error);
                        });
                }
            },
            select_column: function(column) {
                if (this.selected_columns.includes(column)) {
                    this.selected_columns.splice(this.selected_columns.indexOf(column), 1);
                    return;
                }
                this.selected_columns.push(column);
                this.sort(column, 'desc');

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
                if (this.stats_time_start=='last30' || this.stats_time_start=='last365' || this.stats_time_start=='all') {
                    this.stats_time_end = 'only';
                } else {
                    let start_index = this.available_months_start.indexOf(this.stats_time_start);
                    this.available_months_end = this.available_months_start.slice(0,start_index); 

                    if (this.stats_time_end!='only') {
                        this.stats_time_end = this.available_months_end[0]; 
                    }
                }
                
                if (this.stats_time_start=='last365') {
                    this.minDays = 290;
                    columns['combined_cop'].name = 'SCOP';
                } else if (this.stats_time_start=='last30') {
                    this.minDays = 24;
                    columns['combined_cop'].name = 'COP';
                } else {
                    this.minDays = 0;
                    columns['combined_cop'].name = 'COP';
                }
                
                this.load_system_stats();
            },
            stats_time_end_change: function () {
                this.load_system_stats();
            },
            load_system_stats: function () {
                
                // Start
                let start = this.stats_time_start;
                if (start!='last30' && start!='last365' && start!='all') {
                    // Convert e.g Mar 2023 to 2023-03-01
                    let months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sept','Oct','Nov','Dec'];
                    let month = start.split(' ')[0];
                    let year = start.split(' ')[1];
                    start = year + '-' + (months.indexOf(month)+1) + '-01';
                }

                // End
                let end = this.stats_time_end;
                if (end!='only') {
                    // Convert e.g Mar 2023 to 2023-03-01
                    let months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sept','Oct','Nov','Dec'];
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

                if (start == 'last30' || start == 'last365' || start == 'all') {
                    
                    if (start == 'all') {
                        url = path+'system/stats/last365';
                    } else {
                        url = path+'system/stats/'+start;
                    }
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
                                // for (var col in stats_columns) {
                                //    app.systems[i][stats_columns[col]] = 0;
                                // }
                                app.systems[i]['combined_cop'] = 0;
                                app.systems[i]['combined_data_length'] = 0;
                            }
                        }
                        // sort
                        app.sort_only(app.currentSortColumn);
                        if (app.chart_enable) draw_chart();
                        
                    })
                    .catch(error => {
                        alert("Error loading data: " + error);
                    });
            },
            toggle_chart: function() {
                this.chart_enable = !this.chart_enable;
                
                if (this.chart_enable) {
                    setTimeout(function() {
                        draw_chart();
                    }, 200);
                }
            },
            column_format: function (system,key) {
                var val = system[key];

                if (key=='last_updated' || key=='data_start') {
                    return time_ago(val,' ago');
                }
                if (key=='since') {
                    return time_ago(val);
                }
                if (key=='combined_data_length') {
                    return (val/(24*3600)).toFixed(0)+" days";
                }                
                if (key=='installer_name') {
                    if (val!=null && val!='') {
                        var heatgeek = "";
                        if (system['heatgeek']==1) heatgeek = "<img class='heatgeeklogo' src='theme/img/HeatGeekLogo.png' title='HeatGeek Trained'/>";
                        var betateach = "";
                        if (system['betateach']==1) betateach = "<img class='betateachlogo' src='theme/img/beta-teach.jpg' title='BetaTeach Heat Architect'/>";
                        return betateach+heatgeek+"<a class='installer_link' href='"+system['installer_url']+"'>"+val+"</a>";
                    } else {
                        return '';
                    }
                }
                if (key=='hp_type') {
                    if (val=="Air Source") {
                        return "<span style='color:#4f8baa'>Air</span>";
                    }
                    if (val=="Ground Source") {
                        return "<span style='color:#938e03'>Ground</span>";
                    }
                }
                if (key=='hp_output') {
                    return val + ' kW';
                }
                if (key=='cop') {
                    if (isNaN(val) || val == null) {
                        return val;
                    } else {
                        return val.toFixed(1);
                    }
                }
                if (key=='mid_metering') {
                    if (val==1) {
                        return 'Yes';
                    } else {
                        return '';
                    }
                }
                if (key=='quality_elec') {
                    if (val==undefined) return '';
                    return val + '%';
                }
                return val;
            },
            // grey if start date is less that 1 year ago
            sinceClass: function(system,column) {
                // return node.since > 0 ? 'partial ' : '';
                // node.since is unix time in seconds
                if (column=='combined_cop' || column=='since' || column=='combined_data_length' || column=='quality_elec') {
                    var days = system.combined_data_length / (24 * 3600)
                    if (system.combined_cop==0) {
                        return 'partial ';
                    }
                    if (this.stats_time_start=='last365' || this.stats_time_start=='all') {
                        
                        return (days<=360) ? 'partial ' : '';
                    } else {
                        return (days<=27) ? 'partial ' : '';
                    }
                }
                
                return '';
            },
 
            filterNodes(row) {
                if (this.filterKey != '') {
                    if (this.filterKey === 'MID') {
                        return row.mid_metering === 1;
                    } else if (this.filterKey === 'HG' || this.filterKey === 'HeatGeek') {
                        return row.heatgeek === 1;
                    } else {
                        return Object.keys(row).some((key) => {
                            return String(row[key]).toLowerCase().indexOf(this.filterKey.toLowerCase()) > -1
                        })
                    }
                }
                return true;
            },

            filterDays(row) {
                if (this.minDays==null || this.minDays=='' || isNaN(this.minDays)) this.minDays = 0;
                this.minDays = parseInt(this.minDays);
                let minDays = this.minDays-1;
                if (minDays<0) minDays = 0;
                return (row.combined_data_length/ (24 * 3600)) >= minDays;
            }
       },
        filters: {
            toFixed: function(val, dp) {
                if (isNaN(val) || val == null) {
                    return val;
                } else {
                    return val.toFixed(dp)
                }
            },
            time_ago: function(val) {
                return time_ago(val);
            }
        },

        computed: {
            fSystems: function () {
                return this.systems.filter(this.filterNodes).filter(this.filterDays);
            },
            // calculate total scop of fSystems
            totals: function () {
                var totals = {
                    average_cop: 0,
                    elec_kwh: 0,
                    heat_kwh: 0,
                    average_cop_kwh: 0,
                    count: 0
                };
                var count = 0;
                for (var i = 0; i < this.fSystems.length; i++) {
                    if (this.fSystems[i].combined_elec_kwh>0 && this.fSystems[i].combined_heat_kwh>0 && this.fSystems[i].combined_heat_kwh>this.fSystems[i].combined_elec_kwh) {
                        totals.average_cop += this.fSystems[i].combined_cop*1;
                        totals.elec_kwh += this.fSystems[i].combined_elec_kwh;
                        totals.heat_kwh += this.fSystems[i].combined_heat_kwh;
                        totals.count++;
                    }
                }
                totals.average_cop = totals.average_cop / totals.count;
                totals.average_cop_kwh = totals.heat_kwh / totals.elec_kwh;

                return totals;
            }
        }
    });

    init_chart();
    
    app.load_system_stats();
    app.sort_only('combined_cop');

    function time_ago(val,ago='') {
        if (val == null || val == 0) {
            return '';
        }
        // convert timestamp to date time
        let date = new Date(val * 1000);
        // format date time
        let year = date.getFullYear();
        let month = date.getMonth() + 1;
        let day = date.getDate();
        let hour = date.getHours();
        let min = date.getMinutes();
        let sec = date.getSeconds();
        // add leading zeros
        month = (month < 10) ? "0" + month : month;
        day = (day < 10) ? "0" + day : day;
        hour = (hour < 10) ? "0" + hour : hour;
        min = (min < 10) ? "0" + min : min;
        sec = (sec < 10) ? "0" + sec : sec;
        // return formatted date time

        let months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];


        // work out as 10 days ago
        let now = new Date();
        let diff = now - date;
        let days = Math.floor(diff / (1000 * 60 * 60 * 24));

        return days + " days"+ago;

        // return day + " " + months[month-1] + " " + year;
    }

    window.addEventListener("scroll", function() {
        var scroll = window.pageYOffset;
        var threshold = 200; // Change this to the desired threshold in pixels
        var stickyCard = document.querySelector(".sticky-card");
        if (scroll >= threshold) {
            stickyCard.classList.add("sticky");
        } else {
            stickyCard.classList.remove("sticky");
        }
    });

    function init_chart() {
        chart_options = {
            colors_style_guidlines: ['#29ABE2'],
            colors: ['#29AAE3'],
            chart: {
                type: 'bar',
                height: 500,
                toolbar: {
                    show: false
                }
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
            yaxis: { 
                title: {
                    text: 'COP / SCOP'
                }
            }
        };
        // y-axis label

        chart = new ApexCharts(document.querySelector("#chart"), chart_options);
        chart.render();
    }

    function draw_chart() {
        var x = [];
        var y = [];
        for (var i = 0; i < app.systems.length; i++) {
            x.push(app.systems[i].location);
            let cop = app.systems[i].combined_cop;
            if (cop<0) cop = 0;
            y.push(cop);
        }

        chart_options.xaxis.categories = x;
        chart_options.series = [{
            name: 'COP',
            data: y
        }];

        chart.updateOptions(chart_options);
    }

</script>
