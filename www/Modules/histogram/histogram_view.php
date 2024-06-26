<?php
$id = 2;
if (isset($_GET['id'])) {
    $id = $_GET['id'];
}
?>
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.3/jquery.flot.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.3/jquery.flot.time.min.js"></script>
<script src="Lib/jquery.flot.axislabels.js"></script>

<div id="app">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container-fluid">
            <h3>Histogram</h3>
        </div>
    </div>

    <div class="container-fluid" style="margin-top:20px">
        <div class="row">
            <div class="col-md-5">

                <select class="form-control" v-model="histogram_type" @change="change_histogram_type">
                    <option value="kwh_at_cop">COP vs kWh heat delivered</option>
                    <option value="kwh_at_temperature">Flow temperature vs kWh heat delivered</option>
                    <!--<option value="flow_temp_curve">Flow temperature curve</option>-->
                </select>
                <br>

                <p>Select systems...</p>
                <!-- Add your table here -->
                <table class="table">
                    <tr>
                        <th>Color</th>
                        <th>System</th>
                        <th>Start</th>
                        <th>End</th>
                        <th></th>
                    </tr>
                    <tr v-for="system,idx in selected_systems">
                        <td><input class="form-control" type="color" v-model="system.color" @change="change_color"></td>
                        <td>
                            <select class="form-control" v-model="system.id" @change="change_system(idx)">
                                <option v-for="s,i in system_list" :value="s.id">{{ s.location }}, {{ s.hp_model }}, {{ s.hp_output }} kW</option>
                            </select>
                        </td>
                        <td><input class="form-control" v-if="idx==0 || !match_dates" v-model="system.start" type="date" @change="date_changed(idx)"></td>
                        <td><input class="form-control" v-if="idx==0 || !match_dates" v-model="system.end" type="date" @change="date_changed(idx)"></td>
                        <td><button class="btn btn-danger" @click="remove_system">Delete</button></td>
                    </tr>
                </table>
                <button class="btn btn-primary" @click="add_system">+ Add system</button>
                <br><br>

                <h4>Options</h4>

                <div class="input-group mb-3">
                    <span class="input-group-text" style="width:200px">Match dates</span>
                    <span class="input-group-text">
                        <input class="form-check-input" type="checkbox" v-model="match_dates" @click="update_match_dates">
                    </span>
                </div>

                <div class="input-group mb-3" style="max-width:400px">
                    <span class="input-group-text" style="width:200px">Plot type</span>
                    <select class="form-control" v-model="plot_type" @change="update_plot_type">
                        <option value="lines">Lines</option>
                        <option value="points">Lines with points</option>
                        <option value="filled">Lines with fill</option>
                        <option value="bars">Bars</option>
                    </select>
                </div>

                <div class="input-group mb-3">
                    <span class="input-group-text" style="width:200px">{{ xaxis_title }} range</span>
                    <span class="input-group-text">Min</span>
                    <input type="text" class="form-control" v-model="x_min" @change="update_min">
                    <span class="input-group-text">Max</span>
                    <input type="text" class="form-control" v-model="x_max" @change="update_max">
                </div>

            </div>
            <div class="col-md-7">
                <!-- Add your flot chart here -->
                <div id="placeholder" style="width:100%;height:600px;"></div>

            </div>
        </div>
    </div>
</div>

<script>
    var id = <?php echo $id; ?>;
    
    var system_list = [];
    var system_map = {};
    $.ajax({
        dataType: "json", 
        url: path+"system/list/public.json", 
        async: false, 
        success: function(result) { 
            system_list = result; 

            // map by id
            for (var i=0; i<system_list.length; i++) {
                system_map[system_list[i].id] = i;
            }

        }
    });

    var default_start = "2023-10-01";
    var default_end = "2024-04-01";
    var colours = ["#fec601","#ea7317","#73bfb8","#3da5d9","#2364aa"];

    var app = new Vue({
        el: '#app',
        data: {
            histogram_type: "kwh_at_cop",
            system_list: system_list,
            selected_systems: [
                {id: id, color: colours[0], start: default_start, end: default_end, time_changed: false, data: []}
            ],
            match_dates: true,
            interval: 600,
            plot_type: "points",
            months: ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],
            xaxis_title: "COP",
            x_min: 1.0,
            x_max: 8.0
        },
        methods: {
            change_histogram_type: function() {

                if (this.histogram_type == "kwh_at_cop") {
                    this.xaxis_title = "COP";
                    this.x_min = 1.0;
                    this.x_max = 8.0;
                } else {
                    this.xaxis_title = "Flow temperature";
                    this.x_min = 20;
                    this.x_max = 55;
                }

                for (var i=0; i<app.selected_systems.length; i++) {
                    load_system_data(i);
                }
                draw();
            },
            add_system: function () {
                if (this.selected_systems.length == 0) {
                    // add empty system
                    this.selected_systems.push({id: 1, color: colours[0], start: default_start, end: default_end, time_changed: false, data: []});
                    load_system_data(this.selected_systems.length-1);
                    draw();
                } else {
                    // add copy of last system
                    this.selected_systems.push(JSON.parse(JSON.stringify(this.selected_systems[this.selected_systems.length-1])));
                    this.selected_systems[this.selected_systems.length-1].color = colours[this.selected_systems.length-1];
                    draw();
                }
                
            },
            change_color: function() {
                draw();
            },
            change_system: function(idx) {
                load_system_data(idx);
                draw();
            },
            date_changed: function(idx) {
                if (app.match_dates) {
                    // load all systems
                    for (var i=0; i<app.selected_systems.length; i++) {
                        app.selected_systems[i].start = app.selected_systems[idx].start;
                        app.selected_systems[i].end = app.selected_systems[idx].end;
                        load_system_data(i);
                    }
                    draw();
                } else {
                    load_system_data(idx);
                    draw();
                }
            },
            update_match_dates: function() {
                if (!this.match_dates) {
                    for (var i=0; i<app.selected_systems.length; i++) {
                        app.selected_systems[i].start = app.selected_systems[0].start;
                        app.selected_systems[i].end = app.selected_systems[0].end;
                        load_system_data(i);
                    }
                    draw();        
                }
            },
            remove_system: function(idx) {
                this.selected_systems.splice(idx, 1);
                draw();
            },
            update_plot_type: function() {
                draw();
            },
            update_min: function() {
                for (var i=0; i<app.selected_systems.length; i++) {
                    load_system_data(i);
                }
                draw();
            },
            update_max: function() {
                for (var i=0; i<app.selected_systems.length; i++) {
                    load_system_data(i);
                }
                draw();
            }

        }
    });

    // Flot options
    var options = {
        series: {
        },
        xaxis: {
            axisLabel: 'COP'
        },
        yaxis: {
            min: 0,
            axisLabel: 'kWh heat'
        },
        grid: {
            hoverable: true,
            clickable: true
        },
        axisLabels: {
            show: true
        }
    };

    // Create flot chart
    draw();
    function draw() {
        // Set options
        if (app.plot_type=="lines") {
            options.series.bars = {show: false};
            options.series.lines = {show: true};
            options.series.points = {show: false};
            options.series.lines.fill = false;
        } else if (app.plot_type=="points") {
            options.series.bars = {show: false};
            options.series.lines = {show: true};
            options.series.points = {show: true};
            options.series.lines.fill = false;
        } else if (app.plot_type=="filled") {
            options.series.bars = {show: false};
            options.series.lines = {show: true};
            options.series.points = {show: false};
            options.series.lines.fill = true;
        } else if (app.plot_type=="bars") {
            options.series.bars = {show: true};
            options.series.lines = {show: false};
            options.series.points = {show: false};
            options.series.bars.barWidth = 0.1;
        }

        var chart = $.plot("#placeholder", app.selected_systems, options);
    }

    // Load system data
    load_system_data(0);
    draw();


    function load_system_data(idx) {
        var system = app.selected_systems[idx];
        
        console.log(system)

        var view_start = date_str_to_time(system.start);

        $.ajax({
            dataType: "json", 
            url: path+"histogram/"+app.histogram_type,
            data: {
                'id': system.id, 
                'start': date_str_to_time(system.start), 
                'end': date_str_to_time(system.end),
                'x_min': app.x_min,
                'x_max': app.x_max,
            },
            async: false, 
            success: function(result) {
                if (result.success!=undefined && !result.success) {
                    alert("Error: "+result.message);
                    return;
                }
                let data = [];
                let index = 0;
                for (var i=result.min; i<=result.max; i+=result.div) {
                    data.push([i, result.data[index]]);
                    index++;
                }
                app.selected_systems[idx].data = data;

                options.series.bars.barWidth = result.div;

                // changes x axis label
                if (app.histogram_type=="kwh_at_cop") {
                    options.xaxis.axisLabel = "COP";
                } else if (app.histogram_type=="kwh_at_temperature") {
                    options.xaxis.axisLabel = "Flow temperature (°C)";
                } else if (app.histogram_type=="flow_temp_curve") {
                    options.xaxis.axisLabel = "Outside temperature (°C)";
                }
            }
        });
    }

    resize();
    function resize() {
        var height = $(window).height() - $("#placeholder").offset().top - 80;
        $("#placeholder").height(height);
        draw();
    }

    // Expand height of chart to fill available space
    $(window).resize(function() {
        resize();
    });

    function time_to_date_str(time) {
        var date = new Date(time*1000);
        var yyyy = date.getFullYear();
        var mm = date.getMonth()+1;
        if (mm<10) mm = "0"+mm;
        var dd = date.getDate();
        if (dd<10) dd = "0"+dd;
        return yyyy+"-"+mm+"-"+dd;
    }

    function date_str_to_time(str) {
        return (new Date(str+" 00:00:00")).getTime()*0.001;
}


</script>
