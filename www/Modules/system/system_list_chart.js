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
            colorscale: 'Viridis',
            showscale: false
        }
    };

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

        if (columns[app.selected_color].options != undefined) {
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

    // Use jStat for correlation and regression analysis
    var regression = calculateRegressionWithPredictionInterval(trace.x, trace.y, 0.1);
    app.correlation = regression.correlation;
    app.r2 = regression.r2;
    
    var min_x = Math.min(...trace.x);
    var max_x = Math.max(...trace.x);

    // Add line of best fit
    if (app.enable_line_best_fit) {
        data.push({
            type: 'scatter',
            x: [min_x, max_x],
            y: [regression.slope * min_x + regression.intercept, regression.slope * max_x + regression.intercept],
            mode: 'lines',
            name: 'Best Fit Line',
            line: {
                color: "#1f77b4",
                width: 2
            },
            showlegend: false
        });

        // Add prediction interval bands
        var x_range = [];
        var upper_bound = [];
        var lower_bound = [];
        
        // Create smooth curves for prediction intervals
        for (let i = 0; i <= 50; i++) {
            let x = min_x + (max_x - min_x) * (i / 50);
            let bounds = calculatePredictionInterval(x, trace.x, trace.y, regression, 0.1);
            x_range.push(x);
            upper_bound.push(bounds.upper);
            lower_bound.push(bounds.lower);
        }

        // Add upper prediction interval
        data.push({
            type: 'scatter',
            x: x_range,
            y: upper_bound,
            mode: 'lines',
            name: '90% Prediction Interval',
            line: {
                color: 'rgba(31, 119, 180, 0.3)',
                width: 1,
                dash: 'dash'
            },
            showlegend: false
        });

        // Add lower prediction interval
        data.push({
            type: 'scatter',
            x: x_range,
            y: lower_bound,
            mode: 'lines',
            name: '90% Prediction Interval',
            line: {
                color: 'rgba(31, 119, 180, 0.3)',
                width: 1,
                dash: 'dash'
            },
            showlegend: false
        });

        // Add filled area between bounds
        data.push({
            type: 'scatter',
            x: [...x_range, ...x_range.slice().reverse()],
            y: [...upper_bound, ...lower_bound.slice().reverse()],
            fill: 'toself',
            fillcolor: 'rgba(31, 119, 180, 0.1)',
            line: { color: 'transparent' },
            name: '90% Prediction Interval',
            showlegend: false,
            hoverinfo: 'skip'
        });
    }

    // ...existing layout code...
    var x_name = columns[app.selected_xaxis].name;
    var y_name = columns[app.selected_yaxis].name;
    var x_group = columns[app.selected_xaxis].group;
    var y_group = columns[app.selected_yaxis].group;
    
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
        },
        yaxis: {
            title: y_group + ": "+ y_name,
            showgrid: true,
            zeroline: false,
        },
        margin: { t: 10 },
        dragmode: false,
        showlegend: false,
        margin: { t: 10, r: 10 }
    };

    var config = { displayModeBar: false };
    Plotly.newPlot('chart', data, layout, config);

//    app.chart_info = "R: " + app.correlation.toFixed(2) + ", R²: " + app.r2.toFixed(2) + ", n=" + trace.x.length + ", (y=" + regression.slope.toFixed(2) + "x + " + regression.intercept.toFixed(2) + ")";
    // Calculate prediction interval at mean x for display
    var mean_x = jStat.mean(trace.x);
    var mean_bounds = calculatePredictionInterval(mean_x, trace.x, trace.y, regression, 0.1);
    var pi_half_width = ((mean_bounds.upper - mean_bounds.lower)/2).toFixed(2);

    app.chart_info = "R: " + app.correlation.toFixed(2) + ", R²: " + app.r2.toFixed(2) + ", n=" + trace.x.length + ", (y=" + regression.slope.toFixed(2) + "x + " + regression.intercept.toFixed(2) + ")" + ", 90% PI: ±" + pi_half_width;


    if (first_chart_load) {
        first_chart_load = false;
        resizeChart();
    }
}

function calculateRegressionWithPredictionInterval(x, y, alpha) {
    // Convert to jStat matrices for easier computation
    var n = x.length;
    
    // Calculate correlation using jStat
    var correlation = jStat.corrcoeff(x, y);
    
    // Calculate means
    var x_mean = jStat.mean(x);
    var y_mean = jStat.mean(y);
    
    // Calculate slope and intercept
    var numerator = 0;
    var denominator = 0;
    
    for (let i = 0; i < n; i++) {
        numerator += (x[i] - x_mean) * (y[i] - y_mean);
        denominator += (x[i] - x_mean) * (x[i] - x_mean);
    }
    
    var slope = numerator / denominator;
    var intercept = y_mean - slope * x_mean;
    
    // Calculate R²
    var ss_res = 0;
    var ss_tot = 0;
    
    for (let i = 0; i < n; i++) {
        var y_pred = slope * x[i] + intercept;
        ss_res += (y[i] - y_pred) * (y[i] - y_pred);
        ss_tot += (y[i] - y_mean) * (y[i] - y_mean);
    }
    
    var r2 = 1 - (ss_res / ss_tot);
    
    // Calculate standard error of estimate
    var mse = ss_res / (n - 2);
    var se = Math.sqrt(mse);
    
    // Calculate sum of squared deviations of x
    var sxx = 0;
    for (let i = 0; i < n; i++) {
        sxx += (x[i] - x_mean) * (x[i] - x_mean);
    }
    
    return {
        slope: slope,
        intercept: intercept,
        correlation: correlation,
        r2: r2,
        se: se,
        sxx: sxx,
        x_mean: x_mean,
        n: n
    };
}

function calculatePredictionInterval(x_val, x_data, y_data, regression, alpha) {
    // Calculate prediction interval for a given x value
    var t_critical = jStat.studentt.inv(1 - alpha/2, regression.n - 2);
    
    // Standard error for prediction
    var se_pred = regression.se * Math.sqrt(1 + (1/regression.n) + 
        Math.pow(x_val - regression.x_mean, 2) / regression.sxx);
    
    var y_pred = regression.slope * x_val + regression.intercept;
    var margin = t_critical * se_pred;
    
    return {
        predicted: y_pred,
        upper: y_pred + margin,
        lower: y_pred - margin
    };
}

// Simplified correlation function using jStat (you can replace the existing one)
function calculatePearsonCorrelation(x, y) {
    return jStat.corrcoeff(x, y);
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