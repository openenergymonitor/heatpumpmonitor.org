<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>

<style>
    .sticky {
        position: sticky;
        top: 20px;
    }
</style>

<div id="app" class="bg-light">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container-fluid">
            <h3 v-if="mode=='user'">My Systems</h3>
            <h3 v-if="mode=='admin'">Admin Systems</h3>

            <button v-if="mode!='public'" class="btn btn-primary" @click="create" style="float:right; margin-right:30px">Add new system</button>

            <div style="float:right; margin-right:30px">
                <div class="input-group">
                    <span class="input-group-text">Stats time period</span>

                    <select class="form-control" v-model="stats_time_start" @change="stats_time_start_change" style="width:120px">
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

            <p v-if="mode=='user'">Add, edit and view systems associated with your account.</p>
            <p v-if="mode=='admin'">Add, edit and view all systems.</p>
            <p v-if="mode=='public'">Here you can see a variety of installations monitored with OpenEnergyMonitor, and compare detailed statistic to see how performance can vary.</p>
        </div>
    </div>

    <div class="container-fluid">

        <div class="row">
            <!-- Side bar with field selection -->
            <div class="col-md-2">
                <div class="card mt-3 sticky-card">
                    <div class="card-header">
                        <h5>Select Fields</h5>
                    </div>
                    <ul class="list-group list-group-flush" style="overflow-x:hidden; height:600px">
                    <template v-for="(group, group_name) in column_groups">
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
            <div class="col-md-10">
                <table id="custom" class="table table-sm mt-3">
                    <tr>
                        <th v-if="mode=='admin'" @click="sort('name', 'asc')" style="cursor:pointer">User
                            <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='name'"></i>
                        </th>
                        <th v-for="column in selected_columns" @click="sort(column, 'desc')" style="cursor:pointer">{{ columns[column].name }}
                            <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn==column"></i>
                        </th>
                        <th v-if="mode!='public'">Status</th>
                        <th style="width:150px">Actions</th>
                    </tr>
                    <tr v-for="(system,index) in systems">
                        <td v-if="mode=='admin'" :title="system.username+'\n'+system.email">{{ system.name }}</td>
                        <td v-for="column in selected_columns">{{ system[column] | column_format(column) }}</td>
                        <td v-if="mode!='public'">
                            <span v-if="system.share" class="badge bg-success">Shared</span>
                            <span v-if="!system.share" class="badge bg-danger">Private</span>
                            <span v-if="system.published" class="badge bg-success">Published</span>
                            <span v-if="!system.published" class="badge bg-secondary">Waiting for review</span>
                        </td>
                        <td>
                            <button v-if="mode!='public'" class="btn btn-warning btn-sm" @click="edit(index)" title="Edit"><i class="fa fa-edit" style="color: #ffffff;"></i></button>
                            <button v-if="mode!='public'" class="btn btn-danger btn-sm" @click="remove(index)" title="Delete"><i class="fa fa-trash" style="color: #ffffff;"></i></button>
                            <button class="btn btn-primary btn-sm" @click="view(index)" title="View"><i class="fa fa-eye" style="color: #ffffff;"></i></button>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

    </div>
</div>

<script>

    var columns = <?php echo json_encode($columns); ?>;

    columns['cop'] = {name: 'COP', group: 'Stats'};
    columns['elec_kwh'] = {name: 'Electricity (kWh)', group: 'Stats'};
    columns['heat_kwh'] = {name: 'Heat (kWh)', group: 'Stats'};

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
    var selected_columns = ['location', 'last_updated','cop'];
    if (mode == 'public') {
        selected_columns = ['location', 'hp_model', 'cop'];
    }

    var app = new Vue({
        el: '#app',
        data: {
            systems: <?php echo json_encode($systems); ?>,
            mode: "<?php echo $mode; ?>",
            columns: columns,
            column_groups: column_groups,
            selected_columns: selected_columns,
            currentSortColumn: 'cop',
            currentSortDir: 'desc',
            // stats time selection
            stats_time_start: "last365",
            stats_time_end: "only",
            stats_time_range: false,
            available_months_start: months,
            available_months_end: months
        },
        methods: {
            create: function() {
                window.location = "new";
            },
            view: function(index) {
                window.location = this.systems[index].url;
            },
            edit: function(index) {
                let systemid = this.systems[index].id;
                window.location = "edit?id=" + systemid;
            },
            remove: function(index) {
                if (confirm("Are you sure you want to delete system: " + this.systems[index].location + "?")) {
                    // axios delete 
                    let systemid = this.systems[index].id;
                    axios.get('delete?id=' + systemid)
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
                            }
                        }
                        // sort
                        app.sort_only(app.currentSortColumn);
                    })
                    .catch(error => {
                        alert("Error loading data: " + error);
                    });
            }
        },
        filters: {
            column_format: function (val,key) {
                if (key=='last_updated') {
                    return time_ago(val);
                }
                return val;
            },
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
        }
    });
    
    app.load_system_stats();
    app.sort_only('cop');

    function time_ago(val) {
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
        return year + "-" + month + "-" + day + " " + hour + ":" + min + ":" + sec; 
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



</script>