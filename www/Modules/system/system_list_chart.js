var first_chart_load = true;

function draw_scatter() 
{
    if (!app.chart_enable) {   
        return;
    }

    app.url_update();

    console.log("Drawing scatter chart");

    var trace = {
        mode: 'markers',
        marker: {
            size: 10,
            colorscale: 'Viridis',     // Built-in color scale
            showscale: false           // Display color scale bar
        }
    };

    // Create hover template
    trace.hovertemplate = '%{text}';

    trace.x = [];
    trace.y = [];
    trace.marker.color = [];
    trace.text = [];

    for (var z in app.fSystems) {
        let system = app.fSystems[z];

        let x = system[app.selected_xaxis];
        let y = system[app.selected_yaxis];

        if (x==0 || y==0) {
            continue;
        }

        if (x==null || y==null) {
            continue;
        }

        trace.x.push(x);
        trace.y.push(y);

        // Is app.selected_color a select item with options
        if (columns[app.selected_color].options != undefined) {
            // Get the option index
            let index = columns[app.selected_color].options.indexOf(system[app.selected_color]);
            trace.marker.color.push(index);
        } else {
            trace.marker.color.push(system[app.selected_color]);
        }

        if (columns[app.selected_xaxis].dp != undefined) {
            x = x.toFixed(columns[app.selected_xaxis].dp);
        }

        if (columns[app.selected_yaxis].dp != undefined) {
            y = y.toFixed(columns[app.selected_yaxis].dp);
        }


        var tooltip = "System: "+system.id+", "+system.location+"<br>"+system.hp_output+" kW "+system.hp_model+"<br>";
        tooltip += columns[app.selected_xaxis].name + ": "+x+"<br>";
        tooltip += columns[app.selected_yaxis].name + ": "+y+"<br>";
        tooltip += columns[app.selected_color].name + ": "+system[app.selected_color];

       
        trace.text.push(tooltip);
    }

    var data = [trace];

    // Calculate correlation coefficient AND line of best fit
    app.correlation = calculatePearsonCorrelation(trace.x, trace.y);
    var line = calculateLineOfBestFit(trace.x.map((x, i) => [x, trace.y[i]]), 0);
    app.r2 = calculate_determination_coefficient(trace.x, trace.y, line.m, line.b);
    
    var min_x = Math.min(...trace.x);
    var max_x = Math.max(...trace.x);

    // Add line of best fit
    data.push({
        type: 'scatter',
        x: [min_x, max_x],
        y: [(line.m * min_x) + line.b, (line.m * max_x) + line.b],
        mode: 'lines',
        line: {
            color: "#1f77b4",
            width: 2
        }
    });

    var x_name = columns[app.selected_xaxis].name;
    var y_name = columns[app.selected_yaxis].name;
    var x_group = columns[app.selected_xaxis].group;
    var y_group = columns[app.selected_yaxis].group;
    // remove 'Stats: ' from the group name
    x_group = x_group.replace("Stats: ", "");
    y_group = y_group.replace("Stats: ", "");

    if (y_name == "COP") {
        if (app.stats_time_start == 'last365') {
            y_name = "Seasonal Performance Factor (SPF)";
        } else {
            y_name = "Coefficient of Performance (COP)";
        }
    }

    var layout = {
        xaxis: {
            title: x_group + ": " + x_name,
            showgrid: true,
            zeroline: false,
            //dtick: 2.0
        },
        yaxis: {
            title: y_group + ": "+ y_name,
            showgrid: true,
            zeroline: false,
            //dtick: 0.5
        },
        margin: { t: 10 },
        dragmode: false,  // Disable the drag-to-zoom feature
        showlegend: false,
        margin: { t: 10, r: 10 }
    };

    var config = { displayModeBar: false };
    Plotly.newPlot('chart', data, layout, config);

    app.chart_info = "R: " + app.correlation.toFixed(2) + ", R²: " + app.r2.toFixed(2) + ", n=" + trace.x.length + ", (y=" + line.m.toFixed(2) + "x + " + line.b.toFixed(2) + ")";

    if (first_chart_load) {
        first_chart_load = false;
        resizeChart();
    }
}

function calculatePearsonCorrelation(x, y) {

    // Calculate the sum of the products of corresponding values
    let sumX = 0, sumY = 0, sumXY = 0, sumXSquare = 0, sumYSquare = 0;

    let n = x.length;

    for (let i = 0; i < n; i++) {
        sumX += x[i];
        sumY += y[i];
        sumXY += x[i] * y[i];
        sumXSquare += x[i] * x[i];
        sumYSquare += y[i] * y[i];
    }

    // Calculate the correlation coefficient
    let numerator = (n * sumXY) - (sumX * sumY);
    let denominator = Math.sqrt(((n * sumXSquare) - (sumX * sumX)) * ((n * sumYSquare) - (sumY * sumY)));

    if (denominator === 0) {
        return 0;  // To avoid division by zero
    }

    return numerator / denominator;
}

function calculateLineOfBestFit(dataPoints, min_x) {
    let xSum = 0,
        ySum = 0,
        xySum = 0,
        xxSum = 0,
        n = 0;


    // Calculate sums
    for (const [x, y] of dataPoints) {
        if (x >= min_x) {
            xSum += x;
            ySum += y;
            xxSum += x * x;
            xySum += x * y;
            n += 1;
        }
    }

    // Calculate slope (m) and y-intercept (b) for y = mx + b
    const m = (n * xySum - xSum * ySum) / (n * xxSum - xSum * xSum);
    const b = (ySum - m * xSum) / n;

    return {
        m,
        b
    };
}

function calculate_determination_coefficient(x, y, m, b) {
    let yMean = y.reduce((a, b) => a + b) / y.length;
    let yPredicted = x.map(x => m * x + b);
    let ssTot = y.reduce((a, b) => a + (b - yMean) ** 2, 0);
    let ssRes = y.reduce((a, b, i) => a + (b - yPredicted[i]) ** 2, 0);
    return 1 - ssRes / ssTot;
}

function resizeChart() {
    if (!app.chart_enable) {   
        return;
    }

    var chartDiv = document.getElementById('chart');
    var width = chartDiv.offsetWidth;

    if (!width) {
        return;
    }

    var height = width * 0.4;

    if (height < 400) {
        height = 400;
    }

    console.log("Resizing chart to width: " + width + ", height: " + height);

    Plotly.relayout(chartDiv, {
        width: width,
        height: height
    });
}

window.addEventListener('resize', resizeChart);

// on window load
window.onload = function() {
    // Load the data
    resizeChart();
};