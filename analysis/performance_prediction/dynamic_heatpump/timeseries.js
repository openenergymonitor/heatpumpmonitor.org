function timeseries(data_array) {
    var result = [];
    var timestep = 30; // seconds
    var start_time = 0;

    // Calculate how many original data points fit in each downsampled interval
    var points_per_interval = Math.floor(view.interval / timestep);
    
    // Limit to view range
    var view_start_index = Math.floor(view.start / timestep);
    var view_end_index = Math.ceil(view.end / timestep);

    // Clamp to data array bounds
    if (view_start_index < 0) view_start_index = 0;
    if (view_end_index > data_array.length) view_end_index = data_array.length;

    // Group and average data
    for (var i = view_start_index; i < view_end_index; i += points_per_interval) {
        var sum = 0;
        var count = 0;
        
        // Average all points in this interval
        for (var j = 0; j < points_per_interval && (i + j) < data_array.length; j++) {
            sum += data_array[i + j];
            count++;
        }
        
        var avg = count > 0 ? sum / count : 0;
        var time = start_time + i * timestep * 1000;
        result.push([time, avg]);
    }
    
    return result;
}

function view_calc_interval() {
    var range_seconds = view.end - view.start;
    
    // Target ~6000-9000 data points on screen for optimal performance
    var ideal_interval = range_seconds / 6000;
    
    // Available downsample intervals (in seconds)
    var intervals = [3600, 1800, 900, 600, 300, 60, 30];
    
    // Select the smallest interval that meets or exceeds the ideal
    view.interval = intervals.find(function(interval) {
        return ideal_interval >= interval;
    }) || 30;
}