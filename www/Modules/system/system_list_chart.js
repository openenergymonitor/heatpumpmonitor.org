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

    // Use appropriate regression method based on toggle
    var regression;
    if (app.line_best_fit_type == 'tls') {
        regression = calculateOrthogonalRegressionWithPI(trace.x, trace.y, 0.1);
    } else {
        regression = calculateRegressionWithPredictionInterval(trace.x, trace.y, 0.1);
    }

    app.correlation = regression.correlation;
    app.r2 = regression.r2;
    
    var min_x = Math.min(...trace.x);
    var max_x = Math.max(...trace.x);

    // Add line of best fit
    if (app.line_best_fit_type != 'none') {
        data.push({
            type: 'scatter',
            x: [min_x, max_x],
            y: [regression.slope * min_x + regression.intercept, regression.slope * max_x + regression.intercept],
            mode: 'lines',
            name: app.line_best_fit_type == 'tls' ? 'Orthogonal Fit Line' : 'Best Fit Line',
            line: {
                color: app.line_best_fit_type == 'tls' ? "#ff7f0e" : "#1f77b4",
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
            let bounds;
            if (app.line_best_fit_type == 'tls') {
                bounds = calculateOrthogonalPredictionInterval(x, trace.x, trace.y, regression, 0.1);
            } else {
                bounds = calculatePredictionInterval(x, trace.x, trace.y, regression, 0.1);
            }
            x_range.push(x);
            upper_bound.push(bounds.upper);
            lower_bound.push(bounds.lower);
        }

        var interval_color = app.line_best_fit_type == 'tls' ? 'rgba(255, 127, 14, 0.3)' : 'rgba(31, 119, 180, 0.3)';
        var fill_color = app.line_best_fit_type == 'tls' ? 'rgba(255, 127, 14, 0.1)' : 'rgba(31, 119, 180, 0.1)';

        // Add upper prediction interval
        data.push({
            type: 'scatter',
            x: x_range,
            y: upper_bound,
            mode: 'lines',
            name: '90% Prediction Interval',
            line: {
                color: interval_color,
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
                color: interval_color,
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
            fillcolor: fill_color,
            line: { color: 'transparent' },
            name: '90% Prediction Interval',
            showlegend: false,
            hoverinfo: 'skip'
        });
    }

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

    if (x_name == "COP") {
        if (app.stats_time_start == 'last365') {
            x_name = "Seasonal Performance Factor (SPF)";
        } else {
            x_name = "Coefficient of Performance (COP)";
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

    // Calculate prediction interval at mean x for display
    var mean_x = jStat.mean(trace.x);
    var mean_bounds;
    if (app.line_best_fit_type == 'tls') {
        mean_bounds = calculateOrthogonalPredictionInterval(mean_x, trace.x, trace.y, regression, 0.1);
    } else {
        mean_bounds = calculatePredictionInterval(mean_x, trace.x, trace.y, regression, 0.1);
    }
    var pi_half_width = ((mean_bounds.upper - mean_bounds.lower)/2).toFixed(2);

    var regression_type = app.line_best_fit_type == 'tls' ? "Orthogonal" : "Standard";
    app.chart_info = regression_type + " - R: " + app.correlation.toFixed(3) + ", R²: " + app.r2.toFixed(3) + ", n=" + trace.x.length + ", (y=" + regression.slope.toFixed(4) + "x + " + regression.intercept.toFixed(4) + ")" + ", 90% PI: ±" + pi_half_width;

    if (first_chart_load) {
        first_chart_load = false;
        resizeChart();
    }
}

function calculateOrthogonalRegressionWithPI(x, y, alpha) {
    var n = x.length;
    
    // Calculate correlation using jStat
    var correlation = jStat.corrcoeff(x, y);
    
    // Calculate means
    var x_mean = jStat.mean(x);
    var y_mean = jStat.mean(y);
    
    // Calculate variances and covariance
    var var_x = jStat.variance(x, true); // sample variance
    var var_y = jStat.variance(y, true);
    var cov_xy = 0;
    
    for (let i = 0; i < n; i++) {
        cov_xy += (x[i] - x_mean) * (y[i] - y_mean);
    }
    cov_xy = cov_xy / (n - 1);
    
    // Calculate orthogonal regression slope using proper method
    // Use the geometric mean of the two possible regression slopes
    var slope_xy = cov_xy / var_x;  // y on x
    var slope_yx = var_y / cov_xy;  // x on y, inverted
    
    var slope;
    if (correlation >= 0) {
        slope = Math.sqrt(slope_xy * slope_yx);
    } else {
        slope = -Math.sqrt(slope_xy * slope_yx);
    }
    
    // Handle edge cases
    if (!isFinite(slope) || isNaN(slope)) {
        slope = slope_xy; // fallback to ordinary least squares
    }
    
    var intercept = y_mean - slope * x_mean;
    
    // Calculate orthogonal residuals (perpendicular distances to line)
    var ss_orth = 0;
    var ss_tot = 0;
    
    for (let i = 0; i < n; i++) {
        // Perpendicular distance from point to line: |ax + by + c| / sqrt(a² + b²)
        // Line equation: slope*x - y + intercept = 0, so a=slope, b=-1, c=intercept
        var perp_dist = Math.abs(slope * x[i] - y[i] + intercept) / Math.sqrt(slope * slope + 1);
        ss_orth += perp_dist * perp_dist;
        ss_tot += (y[i] - y_mean) * (y[i] - y_mean);
    }
    
    // For orthogonal regression, R² is just the square of correlation
    var r2 = correlation * correlation;
    
    // Calculate standard error for orthogonal regression
    var mse_orth = ss_orth / (n - 2);
    var se_orth = Math.sqrt(mse_orth);
    
    // Calculate sum of squared deviations for x
    var sxx = 0;
    for (let i = 0; i < n; i++) {
        sxx += (x[i] - x_mean) * (x[i] - x_mean);
    }
    
    return {
        slope: slope,
        intercept: intercept,
        correlation: correlation,
        r2: r2,
        se: se_orth,
        sxx: sxx,
        x_mean: x_mean,
        y_mean: y_mean,
        n: n,
        method: 'orthogonal'
    };
}

function calculateOrthogonalPredictionInterval(x_val, x_data, y_data, regression, alpha) {
    // For orthogonal regression, prediction intervals need to account for 
    // uncertainty in both x and y directions
    
    var t_critical = jStat.studentt.inv(1 - alpha/2, regression.n - 2);
    
    // Calculate the prediction in the y-direction
    var y_pred = regression.slope * x_val + regression.intercept;
    
    // For orthogonal regression, the prediction interval is wider because
    // we account for errors in both x and y
    var slope_sq = regression.slope * regression.slope;
    
    // Enhanced standard error that accounts for orthogonal nature
    var se_pred_factor = Math.sqrt(1 + (1/regression.n) + 
        Math.pow(x_val - regression.x_mean, 2) / regression.sxx);
    
    // Amplification factor for orthogonal regression
    // This accounts for the fact that errors propagate in both dimensions
    var amplification = Math.sqrt(1 + slope_sq) / Math.abs(regression.correlation);
    
    var se_pred_orth = regression.se * se_pred_factor * amplification;
    
    var margin = t_critical * se_pred_orth;
    
    return {
        predicted: y_pred,
        upper: y_pred + margin,
        lower: y_pred - margin
    };
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