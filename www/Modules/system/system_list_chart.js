var first_chart_load = true;
var scatter_chart = null;

// Plotly's 10-stop Viridis colorscale (kept so the chart looks the same as before)
var VIRIDIS = ['#440154','#482878','#3e4989','#31688e','#26828e','#1f9e89','#35b779','#6ece58','#b5de2b','#fde725'];

function viridis(t) {
    if (!isFinite(t)) t = 0;
    t = Math.min(Math.max(t, 0), 1);
    var pos = t * (VIRIDIS.length - 1);
    var i = Math.floor(pos);
    if (i >= VIRIDIS.length - 1) return VIRIDIS[VIRIDIS.length - 1];
    var f = pos - i;
    var c0 = hex_to_rgb(VIRIDIS[i]), c1 = hex_to_rgb(VIRIDIS[i + 1]);
    var r = Math.round(c0[0] + (c1[0] - c0[0]) * f);
    var g = Math.round(c0[1] + (c1[1] - c0[1]) * f);
    var b = Math.round(c0[2] + (c1[2] - c0[2]) * f);
    return 'rgb(' + r + ',' + g + ',' + b + ')';
}

function hex_to_rgb(hex) {
    return [parseInt(hex.substr(1, 2), 16), parseInt(hex.substr(3, 2), 16), parseInt(hex.substr(5, 2), 16)];
}

function draw_scatter() 
{
    if (!app.chart_enable) {   
        return;
    }

    app.url_update();

    console.log("Drawing scatter chart");

    var trace = { x: [], y: [], color: [], text: [] };

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
            trace.color.push(index);
        } else {
            trace.color.push(system[app.selected_color]);
        }

        if (columns[app.selected_xaxis].dp != undefined) {
            x = x.toFixed(columns[app.selected_xaxis].dp);
        }

        if (columns[app.selected_yaxis].dp != undefined) {
            y = y.toFixed(columns[app.selected_yaxis].dp);
        }

        trace.text.push([
            "System: "+system.id+", "+system.location,
            system.hp_output+" kW "+system.hp_model,
            columns[app.selected_xaxis].name + ": "+x,
            columns[app.selected_yaxis].name + ": "+y,
            columns[app.selected_color].name + ": "+system[app.selected_color]
        ]);
    }

    // Map colour values onto the Viridis scale (Plotly auto-ranged min..max)
    var cmin = Infinity, cmax = -Infinity;
    for (let i = 0; i < trace.color.length; i++) {
        let c = 1 * trace.color[i];
        if (!isFinite(c)) continue;
        if (c < cmin) cmin = c;
        if (c > cmax) cmax = c;
    }
    var points = [];
    var point_colors = [];
    for (let i = 0; i < trace.x.length; i++) {
        points.push({ x: trace.x[i], y: trace.y[i], text: trace.text[i] });
        let c = 1 * trace.color[i];
        point_colors.push(viridis(cmax > cmin ? (c - cmin) / (cmax - cmin) : 0.5));
    }

    var datasets = [{
        type: 'scatter',
        label: 'systems',
        data: points,
        backgroundColor: point_colors,
        borderColor: point_colors,
        pointRadius: 5,
        pointHoverRadius: 6,
        order: 0
    }];

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
        var line_color = app.line_best_fit_type == 'tls' ? "#ff7f0e" : "#1f77b4";
        var interval_color = app.line_best_fit_type == 'tls' ? 'rgba(255, 127, 14, 0.3)' : 'rgba(31, 119, 180, 0.3)';
        var fill_color = app.line_best_fit_type == 'tls' ? 'rgba(255, 127, 14, 0.1)' : 'rgba(31, 119, 180, 0.1)';

        datasets.push({
            type: 'line',
            label: app.line_best_fit_type == 'tls' ? 'Orthogonal Fit Line' : 'Best Fit Line',
            data: [
                { x: min_x, y: regression.slope * min_x + regression.intercept },
                { x: max_x, y: regression.slope * max_x + regression.intercept }
            ],
            borderColor: line_color,
            borderWidth: 2,
            pointRadius: 0,
            pointHitRadius: 0,
            fill: false,
            order: 1
        });

        // Prediction interval bands
        var upper_bound = [];
        var lower_bound = [];
        
        for (let i = 0; i <= 50; i++) {
            let x = min_x + (max_x - min_x) * (i / 50);
            let bounds;
            if (app.line_best_fit_type == 'tls') {
                bounds = calculateOrthogonalPredictionInterval(x, trace.x, trace.y, regression, 0.1);
            } else {
                bounds = calculatePredictionInterval(x, trace.x, trace.y, regression, 0.1);
            }
            upper_bound.push({ x: x, y: bounds.upper });
            lower_bound.push({ x: x, y: bounds.lower });
        }

        // Upper bound (dashed), filled down to the lower bound dataset that follows it
        datasets.push({
            type: 'line',
            label: '90% Prediction Interval',
            data: upper_bound,
            borderColor: interval_color,
            borderWidth: 1,
            borderDash: [6, 4],
            pointRadius: 0,
            pointHitRadius: 0,
            backgroundColor: fill_color,
            fill: '+1',
            order: 2
        });

        datasets.push({
            type: 'line',
            label: '90% Prediction Interval',
            data: lower_bound,
            borderColor: interval_color,
            borderWidth: 1,
            borderDash: [6, 4],
            pointRadius: 0,
            pointHitRadius: 0,
            fill: false,
            order: 2
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

    var options = {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        layout: { padding: { top: 10, right: 10 } },
        interaction: { mode: 'nearest', intersect: true },
        plugins: {
            legend: { display: false },
            tooltip: {
                filter: function(item) { return item.dataset.label == 'systems'; },
                callbacks: {
                    title: function() { return ''; },
                    label: function(ctx) { return ctx.raw.text; }
                }
            }
        },
        scales: {
            x: {
                type: 'linear',
                title: { display: true, text: x_group + ": " + x_name },
                grid: { color: '#eee' }
            },
            y: {
                type: 'linear',
                title: { display: true, text: y_group + ": " + y_name },
                grid: { color: '#eee' }
            }
        }
    };

    if (scatter_chart) scatter_chart.destroy();
    scatter_chart = new Chart(document.getElementById('chart'), {
        type: 'scatter',
        data: { datasets: datasets },
        options: options
    });

    // Calculate prediction interval at mean x for display
    var mean_x = stat_mean(trace.x);
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

// ---------------------------------------------------------------------------
// Statistics helpers (mean, sample variance, Pearson r, Student's t quantile)
// ---------------------------------------------------------------------------

function stat_mean(a) {
    var s = 0;
    for (var i = 0; i < a.length; i++) s += a[i];
    return s / a.length;
}

// Sample variance (n-1 denominator)
function stat_variance(a) {
    var m = stat_mean(a);
    var s = 0;
    for (var i = 0; i < a.length; i++) s += (a[i] - m) * (a[i] - m);
    return s / (a.length - 1);
}

// Pearson correlation coefficient
function stat_corrcoeff(x, y) {
    var mx = stat_mean(x), my = stat_mean(y);
    var sxy = 0, sxx = 0, syy = 0;
    for (var i = 0; i < x.length; i++) {
        sxy += (x[i] - mx) * (y[i] - my);
        sxx += (x[i] - mx) * (x[i] - mx);
        syy += (y[i] - my) * (y[i] - my);
    }
    return sxy / Math.sqrt(sxx * syy);
}

// log-gamma via Lanczos approximation
function stat_gammaln(x) {
    var cof = [76.18009172947146, -86.50532032941677, 24.01409824083091,
               -1.231739572450155, 0.1208650973866179e-2, -0.5395239384953e-5];
    var y = x, tmp = x + 5.5;
    tmp -= (x + 0.5) * Math.log(tmp);
    var ser = 1.000000000190015;
    for (var j = 0; j < 6; j++) ser += cof[j] / ++y;
    return -tmp + Math.log(2.5066282746310005 * ser / x);
}

// Continued fraction for the incomplete beta function
function stat_betacf(x, a, b) {
    var fpmin = 1e-30, m = 1, qab = a + b, qap = a + 1, qam = a - 1;
    var c = 1, d = 1 - qab * x / qap;
    if (Math.abs(d) < fpmin) d = fpmin;
    d = 1 / d;
    var h = d;
    for (; m <= 100; m++) {
        var m2 = 2 * m;
        var aa = m * (b - m) * x / ((qam + m2) * (a + m2));
        d = 1 + aa * d; if (Math.abs(d) < fpmin) d = fpmin;
        c = 1 + aa / c; if (Math.abs(c) < fpmin) c = fpmin;
        d = 1 / d; h *= d * c;
        aa = -(a + m) * (qab + m) * x / ((a + m2) * (qap + m2));
        d = 1 + aa * d; if (Math.abs(d) < fpmin) d = fpmin;
        c = 1 + aa / c; if (Math.abs(c) < fpmin) c = fpmin;
        d = 1 / d;
        var del = d * c;
        h *= del;
        if (Math.abs(del - 1) < 3e-7) break;
    }
    return h;
}

// Regularised incomplete beta I_x(a, b)
function stat_ibeta(x, a, b) {
    if (x <= 0) return 0;
    if (x >= 1) return 1;
    var bt = Math.exp(stat_gammaln(a + b) - stat_gammaln(a) - stat_gammaln(b) + a * Math.log(x) + b * Math.log(1 - x));
    if (x < (a + 1) / (a + b + 2)) return bt * stat_betacf(x, a, b) / a;
    return 1 - bt * stat_betacf(1 - x, b, a) / b;
}

// Student's t CDF
function stat_studentt_cdf(t, df) {
    var x = df / (df + t * t);
    var p = 0.5 * stat_ibeta(x, df / 2, 0.5);
    return t >= 0 ? 1 - p : p;
}

// Inverse Student's t CDF (quantile)
function stat_studentt_inv(p, df) {
    if (!(df > 0)) return NaN;
    if (p <= 0) return -Infinity;
    if (p >= 1) return Infinity;
    // bracket then bisect on the CDF
    var lo = -1, hi = 1;
    while (stat_studentt_cdf(lo, df) > p) lo *= 2;
    while (stat_studentt_cdf(hi, df) < p) hi *= 2;
    for (var i = 0; i < 200; i++) {
        var mid = 0.5 * (lo + hi);
        if (stat_studentt_cdf(mid, df) < p) lo = mid; else hi = mid;
        if (hi - lo < 1e-10) break;
    }
    return 0.5 * (lo + hi);
}

// ---------------------------------------------------------------------------
// Regression
// ---------------------------------------------------------------------------

function calculateOrthogonalRegressionWithPI(x, y, alpha) {
    var n = x.length;
    
    // Calculate correlation
    var correlation = stat_corrcoeff(x, y);
    
    // Calculate means
    var x_mean = stat_mean(x);
    var y_mean = stat_mean(y);
    
    // Calculate variances and covariance
    var var_x = stat_variance(x); // sample variance
    var var_y = stat_variance(y);
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
    
    var t_critical = stat_studentt_inv(1 - alpha/2, regression.n - 2);
    
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
    var n = x.length;
    
    // Calculate correlation
    var correlation = stat_corrcoeff(x, y);
    
    // Calculate means
    var x_mean = stat_mean(x);
    var y_mean = stat_mean(y);
    
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
    var t_critical = stat_studentt_inv(1 - alpha/2, regression.n - 2);
    
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

// Pearson correlation
function calculatePearsonCorrelation(x, y) {
    return stat_corrcoeff(x, y);
}

function resizeChart() {
    if (!app.chart_enable) {   
        return;
    }

    var wrap = document.getElementById('chart_wrap');
    var width = wrap.offsetWidth;

    if (!width) {
        return;
    }

    var height = width * 0.4;

    if (height < 400) {
        height = 400;
    }

    console.log("Resizing chart to width: " + width + ", height: " + height);

    wrap.style.height = height + "px";
    if (scatter_chart) scatter_chart.resize();
}

window.addEventListener('resize', resizeChart);

// on window load
window.onload = function() {
    // Load the data
    resizeChart();
};
