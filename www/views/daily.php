<?php
    $mode = "combined";
    if (isset($_GET['mode'])) {
        $mode = $_GET['mode'];
    }
?>
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
            <div id="placeholder" style="width:100%;height:600px; margin-bottom:20px"></div>
            
            <p><b>Note:</b> The measured heat output shown here is the combined heat output of space heating and hot water. 
            While it doesnt compare directly to the heat loss of the building, it is a good indicator of the heat demand from the heat pump.
            Technically the space heating demand from a heat pump should be the calculated heat loss of the building minus gains. These gains
            are bizzarely not taken into account in the sizing of heat pumps when following the BS EN 12831:2003 standard but are at least in a typical
            100m2 house not that different from the average heat demand for hot water and so could be viewed as cancelling out. 
            </p>
        </div>
    </div>
</div>

<script>

var path = "https://dev.heatpumpmonitor.org/";
var systemid = <?php echo $systemid; ?>;
var system_data = <?php echo json_encode($system_data); ?>;

var hp_output = system_data.hp_output * 1000;
var heat_loss = system_data.heat_loss * 1000;

var data = [];
var series = [];


load();

function load() {

    var mode = "<?php echo $mode; ?>";
    var fields = ['timestamp',mode+'_heat_mean',mode+'_roomT_mean',mode+'_outsideT_mean'];

    $.ajax({
        // text plain dataType:
        dataType: "text",
        url: path+"system/stats/daily",
        data: {
            'id': systemid,
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
                    data['heat_vs_dt'].push([x,y]);

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


</script>
