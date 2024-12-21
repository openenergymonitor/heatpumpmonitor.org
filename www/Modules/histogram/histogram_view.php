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
                    <option value="kwh_at_flow">Flow temperature vs kWh heat delivered</option>
                    <option value="kwh_at_outside">Outside temperature vs kWh heat delivered</option>  
                    <option value="kwh_at_flow_minus_outside">Flow minus outside temperature vs kWh heat delivered</option>
                    <option value="kwh_at_ideal_carnot">Ideal carnot COP vs heat delivered</option>
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

            /* Order by location */
            result.sort(function(a,b) {
                if (a.location < b.location) return -1;
                if (a.location > b.location) return 1;
                return 0;
            });

            system_list = result; 



            // map by id
            for (var i=0; i<system_list.length; i++) {
                system_map[system_list[i].id] = i;
            }

        }
    });

    var default_start = "2023-10-01";
    var default_end = "2024-04-01";

    // get current date e.g 2023-10-01
    var date = new Date();
    var yyyy_start = date.getFullYear()-1;
    var yyyy_end = date.getFullYear();
    var mm = date.getMonth()+1;
    if (mm<10) mm = "0"+mm;
    var dd = date.getDate();
    if (dd<10) dd = "0"+dd;
    default_start = yyyy_start+"-"+mm+"-"+dd;
    default_end = yyyy_end+"-"+mm+"-"+dd;




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
            x_max: 8.0,
            average_x_values: []
        },
        methods: {
            change_histogram_type: function() {
                console.time("Histogram Type Logic Execution Time (Optimized)");//Just to measure execution time to see the performance for the optimized code

                //This the precompute logic where it will optimize of selecting histogram type and does not need to go through the multiple conditional check using if loop
                 const histogramSettings = {
                    kwh_at_cop: { title: "COP", min: 1.0, max: 8.0 },
                    kwh_at_flowTemp: { title: "Flow temperature", min: 20, max: 55 },
                    kwh_at_outsideTemp: { title: "Outside temperature", min: -10, max: 20 },
                    kwh_at_flow_minus_outside_Temp: { title: "Flow minus outside temperature", min: 0, max: 60 },
                    kwh_at_ideal_carnot: { title: "Ideal Carnot COP", min: 0, max: 20 }
                 };

                 //This the Lookup settings for the selected histogram type 
                const currentSettings = histogramSettings[this.histogram_type];
                 if (currentSettings) {

                     this.xaxis_title = currentSettings.title;
                     this.x_min = currentSettings.min;
                     this.x_max = currentSettings.max;
                 }

                console.timeEnd("Histogram Type Logic Execution Time (Optimized)") //Just to measure execution time to see the performance for the optimized code
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

        // Add markings for average x values
        options.grid.markings = [];
        for (var i=0; i<app.selected_systems.length; i++) {
            var avg_x = app.average_x_values[i];
            var color = app.selected_systems[i].color;
            if (avg_x!=undefined) {
                options.grid.markings.push({xaxis: {from: avg_x, to: avg_x}, color: color});
            }
        }

        var chart = $.plot("#placeholder", app.selected_systems, options);

        // Add vertical line for average x values
        for (var i=0; i<app.selected_systems.length; i++) {
            var avg_x = app.average_x_values[i];
            if (avg_x!=undefined) {

                var o = chart.pointOffset({ x: avg_x, y: 0});
                var top = chart.getPlotOffset().top + 5 + i*20;

                chart.getPlaceholder().append("<div style='position:absolute;left:" + (o.left + 8) + "px;top:" + top + "px;color:#666;font-size:smaller'>Weighted average: "+avg_x.toFixed(2)+"</div>");

            }
        }
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
                let sum = 0;
                let sum_y = 0;

                for (var i=result.min; i<=result.max; i+=result.div) {
                    data.push([i, result.data[index]]);

                    sum += i * result.data[index];
                    sum_y += result.data[index];

                    index++;
                }

                // Calculate average x value
                let avg_x = sum / sum_y;
                console.log("system: "+system.id+", average x: "+avg_x);
                app.average_x_values[idx] = avg_x;

                app.selected_systems[idx].data = data;

                options.series.bars.barWidth = result.div;

                // changes x axis label
                if (app.histogram_type=="kwh_at_cop") {
                    options.xaxis.axisLabel = "COP";
                } else if (app.histogram_type=="kwh_at_flow") {
                    options.xaxis.axisLabel = "Flow temperature (°C)";
                } else if (app.histogram_type=="kwh_at_outside") {
                    options.xaxis.axisLabel = "Outside temperature (°C)";
                } else if (app.histogram_type=="kwh_at_flow_minus_outside") {
                    options.xaxis.axisLabel = "Flow minus outside temperature (°K)";
                } else if (app.histogram_type=="kwh_at_ideal_carnot") {
                    options.xaxis.axisLabel = "Ideal carnot COP";
                } else if (app.histogram_type=="flow_temp_curve") {
                    options.xaxis.axisLabel = "Outside temperature (°C)";
                }
            }
        });
    }

   
    const placeholder = document.querySelector("#placeholder"); // To cache the placeholder element which will reduce repeated DOM queries

    let resizeTimeout;// Optimized resize logic with performance measurement and debouncing

    window.addEventListener("resize", () => {
    
        console.time("Resize Event Triggered (Optimized)");//Just to measure the optimized one
        clearTimeout(resizeTimeout);// Clear the previous timeout if a new resize event occur
        // Debounce the resize logic
         resizeTimeout = setTimeout(() => {
         console.time("Resize Logic Execution Time (Optimized)");//Just to measure the execution time for optimized on
        const newHeight = window.innerHeight - placeholder.offsetTop - 80;
        // The height is changed only when it is needed because to avoid redundant DOM updates
        if (placeholder.style.height !== `${newHeight}px`) {
            placeholder.style.height = `${newHeight}px`;

            // Just to measure the time taken by the draw function with the optimized logic
            console.time("Draw Function Execution Time (Optimized)");
            draw();
            console.timeEnd("Draw Function Execution Time (Optimized)");
        }

             console.timeEnd("Resize Logic Execution Time (Optimized)");
    }, 200); // Debounce interval of 200ms

    // End performance timer for resize event
    console.timeEnd("Resize Event Triggered (Optimized)");
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

    // Add tooltip
    var previousPoint = null;
    $("#placeholder").bind("plothover", function (event, pos, item) {
        if (item) {
            if (previousPoint != item.dataIndex) {
                previousPoint = item.dataIndex;
                
                $("#tooltip").remove();
                var x = item.datapoint[0].toFixed(2),
                    y = item.datapoint[1].toFixed(2);
                
                showTooltip(item.pageX, item.pageY, x+", "+y);
            }
        } else {
            $("#tooltip").remove();
            previousPoint = null;            
        }
    });

    function showTooltip(x, y, contents) {
        $('<div id="tooltip">' + contents + '</div>').css( {
            position: 'absolute',
            display: 'none',
            top: y - 30,
            left: x + 5,
            border: '1px solid #fdd',
            padding: '2px',
            'background-color': '#fee',
            opacity: 0.80
        }).appendTo("body").fadeIn(200);
    }


</script>
