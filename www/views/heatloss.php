<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.3/jquery.flot.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.3/jquery.flot.time.min.js"></script>
<script src="Lib/jquery.flot.axislabels.js"></script>

<div id="app">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container-fluid">
            <h3>Heat loss explorer</h3>
        </div>
    </div>

    <div class="container" style="margin-top:20px">
        <div class="row">
            <div class="input-group mb-3" style="max-width:600px">
                <span class="input-group-text">Select system</span>

                <select class="form-control" v-model="systemid" @change="change_system" >
                    <option v-for="s,i in system_list" :value="s.id">{{ s.location }},  {{ s.hp_model }}, {{ s.hp_output }} kW</option>
                </select>
            </div>
        
            <div id="placeholder" style="width:100%;height:600px; margin-bottom:20px"></div>
            
            <p>Each datapoint shows the average heat output over a 24 hour period. Hover over data point for more information.</p>
            
            <p><b>Note:</b> The measured heat output shown here is the combined heat output of space heating and hot water. 
            While it doesnt compare directly to the heat loss of the building, it is a good indicator of the heat demand from the heat pump.
            Technically the space heating demand from a heat pump should be the calculated heat loss of the building minus gains. These gains
            are not taken into account in the sizing of heat pumps when following the BS EN 12831:2003 simplified calculation method that most 
            heat loss tools use but are at least in a typical 100m2 house not that different from the average heat demand for hot water and 
            so could be viewed as cancelling out. 
            </p>
        </div>
    </div>
</div>

<script>

var systemid = <?php echo $systemid; ?>;
var mode = "combined";

var hp_output = 0;
var heat_loss = 0;
var data = [];
var series = [];

var app = new Vue({
    el: '#app',
    data: {
        systemid: systemid,
        system_list: {}
    },
    methods: {
       change_system: function() {
           load();
       }
    }
});

// Load list of systems
$.ajax({
    type: "GET",
    url: path + "system/list/public.json",
    success: function(result) {
    
        // System list by id
        app.system_list = {};
        for (var z in result) {
            app.system_list[result[z].id] = result[z];
        }
        
        load();
    }
});

function load() {

    app.system_list

    hp_output = app.system_list[app.systemid].hp_output * 1000;
    heat_loss = app.system_list[app.systemid].heat_loss * 1000;

    
    var fields = ['timestamp',mode+'_heat_mean',mode+'_roomT_mean',mode+'_outsideT_mean','running_flowT_mean','running_returnT_mean'];

    $.ajax({
        // text plain dataType:
        dataType: "text",
        url: path+"system/stats/daily",
        data: {
            'id': app.systemid,
            'start': 1,
            'end': 2,
            'fields': fields.join(',')
        },
        async: true, 
        success: function(result) {
            // split
            var lines = result.split('\n');

            // create data
            for (var z in fields) {
                let key = fields[z];
                data[key] = [];
            }

            for (var i = 1; i < lines.length; i++) {
                var parts = lines[i].split(',');
                if (parts.length != fields.length) {
                    continue;
                }

                var timestamp = parts[0]*1000;

                for (var j = 1; j < parts.length; j++) {
                    let value = parts[j]*1;
                    // add to data
                    data[fields[j]].push([timestamp, value]);
                }
            }

            // Create series with room - outside x-axis and heatpump heat output y-axis
            max_heat = heat_loss;
            if (max_heat<hp_output) max_heat = hp_output;

            data['heat_vs_dt'] = [];
            for (var i = 0; i < data[mode+'_heat_mean'].length; i++) {
                var x = data[mode+'_roomT_mean'][i][1] - data[mode+'_outsideT_mean'][i][1];
                if (x>0) {
                    var y = data[mode+'_heat_mean'][i][1];
                    data['heat_vs_dt'].push([x,y,i]);

                    if (y>max_heat) max_heat = y;
                }
            }

            draw();
        }
    });
}

function draw() {

    // Flot options
    var options = {
        series: {
        },
        xaxis: {
            axisLabel: 'Room - Outside Temperature',
            max: 23
        },
        yaxis: {
            min: 0,
            max: max_heat*1.1,
            axisLabel: 'Heatpump heat output (W)'
        },
        grid: {
            hoverable: true,
            clickable: true
        },
        axisLabels: {
            show: true
        }
    };

    var series = [
        {
            data: data['heat_vs_dt'],
            
            color: 'blue',
            lines: { show: false, fill: false },
            points: { show: true, radius: 2 }
        }
    ];

    // Add horizontal line for heat loss
    series.push({
        data: [[0,heat_loss],[23,heat_loss]],
        color: 'grey',
        lines: { show: true, fill: false },
        points: { show: false }
    });

    // Add horizontal line for heatpump output
    series.push({
        data: [[0,hp_output],[23,hp_output]],
        color: 'black',
        lines: { show: true, fill: false },
        points: { show: false }
    });

    var chart = $.plot("#placeholder", series, options);

    var placeholder = $("#placeholder");
    var o = chart.pointOffset({ x: 0, y: hp_output});
    placeholder.append("<div style='position:absolute;left:" + (o.left + 4) + "px;top:" + (o.top-23) + "px;color:#666;font-size:smaller'>Heatpump badge capacity</div>");

    o = chart.pointOffset({ x: 0, y: heat_loss});
    placeholder.append("<div style='position:absolute;left:" + (o.left + 4) + "px;top:" + (o.top-23) + "px;color:#666;font-size:smaller'>Heat loss value on form</div>");

}

// Flot tooltip
var previousPoint = null;
$("#placeholder").bind("plothover", function (event, pos, item) {
    if (item) {
        if (previousPoint != item.datapoint) {
            previousPoint = item.datapoint;

            $("#tooltip").remove();
            var DT = item.datapoint[0];
            var HEAT = item.datapoint[1];

            var str = "";
            str += "Heat: " + HEAT.toFixed(0) + " W<br>";
            str += "DT: " + DT.toFixed(1) + " °K<br>";

            var original_index = data['heat_vs_dt'][item.dataIndex][2];

            str += "Room: " + data[mode+'_roomT_mean'][original_index][1].toFixed(1) + " °C<br>";
            str += "Outside: " + data[mode+'_outsideT_mean'][original_index][1].toFixed(1) + " °C<br>";
            str += "FlowT: " + data['running_flowT_mean'][original_index][1].toFixed(1) + " °C<br>";
            str += "ReturnT: " + data['running_returnT_mean'][original_index][1].toFixed(1) + " °C<br>";

            var d = new Date(data[mode+'_heat_mean'][original_index][0]);
            str += d.getDate() + " " + d.toLocaleString('default', { month: 'short' }) + " " + d.getFullYear() + "<br>";

            tooltip(item.pageX, item.pageY, str, "#fff", "#000");
        }
    } else {
        $("#tooltip").remove();
        previousPoint = null;
    }
});

// Creates a tooltip for use with flot graphs
function tooltip(x, y, contents, bgColour, borderColour="rgb(255, 221, 221)")
{
    var offset = 10; // use higher values for a little spacing between `x,y` and tooltip
    var elem = $('<div id="tooltip">' + contents + '</div>').css({
        position: 'absolute',
        color: "#000",
        display: 'none',
        'font-weight':'bold',
        border: '1px solid '+borderColour,
        padding: '2px',
        'background-color': bgColour,
        opacity: '0.8',
        'text-align': 'left'
    }).appendTo("body").fadeIn(200);

    var elemY = y - elem.height() - offset;
    var elemX = x - elem.width()  - offset;
    if (elemY < 0) { elemY = 0; } 
    if (elemX < 0) { elemX = 0; } 
    elem.css({
        top: elemY,
        left: elemX
    });
}


</script>
