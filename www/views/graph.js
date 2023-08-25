
var plot_data = {
  "data": [
    {
      "mode": "markers",
      "type": "scatter",
      "x": [],
      "y": [],
      "marker": {
        "line": {
          "width": 0
        },
        "size": 10,
        "color": [],
        "colorscale": [
          [0,"#0000ff"],
          [1,"#ff0000"],
        ]
      },
      "autocolorscale": false
    }
  ],
  "layout": {
    "font": {
      "size": 14
    },
    "title": {
      "text": "COP vs Flow temperature"
    },
    "xaxis": {
      "type": "linear",
      "range": [],
      "title": {
        "text": "Flow temperature"
      },
      "autorange": true
    },
    "yaxis": {
      "type": "linear",
      "range": [],
      "title": {
        "text": "COP"
      },
      "autorange": true
    },
    "autosize": true,
    "showlegend": false,
    "annotations": []
  },
  "frames": []
}

var nodes = false;
var x_axis_select = "flowT";
var y_axis_select = "monthly_cop";
var c_axis_select = "buffer";

$.getJSON( "data.json", function( data ) {
  nodes = data;
  plot()
});

$("#x_axis_select").change(function() {
  plot_data.layout.xaxis.title.text = $("#x_axis_select option:selected" ).text();
  
  plot_data.layout.title.text = plot_data.layout.xaxis.title.text + " vs " + plot_data.layout.yaxis.title.text
  x_axis_select = $(this).val();
  plot()
});

$("#y_axis_select").change(function() {
  plot_data.layout.yaxis.title.text = $("#y_axis_select option:selected" ).text();
  y_axis_select = $(this).val();
  plot()
});

$("#c_axis_select").change(function() {
  c_axis_select = $(this).val();
  plot()
});
    
function plot() {
    console.log(nodes);
    var x = [];
    var y = [];
    var c = [];
    var tooltips = [];
    for (var z in nodes) {
      if (nodes[z].datapoints>0) {
        var x_val = null;
        if (x_axis_select=="flowT") {
          x_val = nodes[z].when_running_flowT;
        } else if (x_axis_select=="returnT") {
          x_val = nodes[z].when_running_returnT;
        } else if (x_axis_select=="outsideT") {
          x_val = nodes[z].when_running_outsideT;
        } else if (x_axis_select=="flow_minus_outside") {
          x_val = nodes[z].when_running_flow_minus_outside;
        } else if (x_axis_select=="flow_minus_return") {
          x_val = nodes[z].when_running_flow_minus_return;
        } else if (x_axis_select=="elec_W") {
          x_val = nodes[z].when_running_elec_W;
        } else if (x_axis_select=="heat_W") {
          x_val = nodes[z].when_running_heat_W;
        } else if (x_axis_select=="elec_kwh") {
          x_val = nodes[z].full_period_elec_kwh;
        } else if (x_axis_select=="heat_kwh") {
          x_val = nodes[z].full_period_heat_kwh;
        } else if (x_axis_select=="carnot_prc") {
          x_val = nodes[z].when_running_carnot_prc;
          if (x_val==0) x_val = null;
        } else if (x_axis_select=="floor_area") {
          x_val = nodes[z].floor_area*1;
        } else if (x_axis_select=="calc_heat_demand") {
          x_val = nodes[z].heat_demand*1;
        } else if (x_axis_select=="calc_heat_demand_m2") {
          x_val = nodes[z].heat_demand*1 / nodes[z].floor_area*1;
        } else if (x_axis_select=="monthly_heat_demand_m2") {
          x_val = nodes[z].when_running_heat_kwh / nodes[z].floor_area*1;
        }
        
        if (x_val===false) x_val = null;  
      
        var y_val = null;
        if (y_axis_select=="monthly_cop") {
          if (nodes[z].last_30_cop>0) {
            y_val = nodes[z].last_30_cop;
          }
        } else if (y_axis_select=="when_running_cop") {
          if (nodes[z].when_running_cop>0) y_val = nodes[z].when_running_cop;
        } else if (y_axis_select=="elec_kwh") {
          y_val = nodes[z].last_30_elec_kwh;
        } else if (y_axis_select=="heat_kwh") {
          y_val = nodes[z].last_30_heat_kwh;
        }
        
        var c_val = null;
        if (c_axis_select=="buffer") {
          if (nodes[z].buffer=="No") c_val = 0; else c_val = 1;
        } else if (c_axis_select=="R32") {
          if (nodes[z].refrigerant=="R32") c_val = 1; else c_val = 0;
        } else if (c_axis_select=="R410a") {
          if (nodes[z].refrigerant=="R410a") c_val = 1; else c_val = 0;
        } else if (c_axis_select=="R290") {
          if (nodes[z].refrigerant=="R290") c_val = 1; else c_val = 0;
        } else if (c_axis_select=="afglycol") {
          if (nodes[z].freeze=="Glycol/water mixture") c_val = 1; else c_val = 0;
        } else if (c_axis_select=="afvalves") {
          if (nodes[z].freeze=="Anti-freeze valves") c_val = 1; else c_val = 0;
        } else if (c_axis_select=="afcirc") {
          if (nodes[z].freeze=="Central heat pump water circulation") c_val = 1; else c_val = 0;
        } 

        
        if (x_val!==null && y_val!==null && c_val!==null) {
          x.push(x_val)
          y.push(y_val)
          c.push(c_val)
          tooltips.push(nodes[z].location+"<br>"+nodes[z].hp_model+"<br>"+nodes[z].hp_type)
        }
      }
    }
    
    plot_data.data[0].x = x;
    plot_data.data[0].y = y;
    plot_data.data[0].marker.color = c;
    plot_data.data[0].text = tooltips;
    var lr = linearRegression(x, y);

    var trace = {x: x,
                y: y,
                name: "Scatter"
                };  

    var fit_from = Math.min(...x)
    var fit_to = Math.max(...x)
    

    var fit = {
      x: [fit_from, fit_to],
      y: [fit_from*lr.sl+lr.off, fit_to*lr.sl+lr.off],
      mode: 'lines',
      type: 'scatter',
      name: "R2=".concat((Math.round(lr.r2 * 10000) / 10000).toString())
    };

    plot_data.data[1] = fit;
    
    $("#trend_line_equation").html("Trend line equation: "+lr.sl.toFixed(3)+"x + "+lr.off.toFixed(3)+", R<sup>2</sup>:"+lr.r2.toFixed(2));

    Plotly.newPlot("gd",plot_data);
}

// https://github.com/plotly/plotly.js/issues/4921
function linearRegression(x,y){
    var lr = {};
    var n = y.length;
    var sum_x = 0;
    var sum_y = 0;
    var sum_xy = 0;
    var sum_xx = 0;
    var sum_yy = 0;

    for (var i = 0; i < y.length; i++) {
        x[i] *= 1;
        y[i] *= 1;
        sum_x += x[i];
        sum_y += y[i];
        sum_xy += (x[i]*y[i]);
        sum_xx += (x[i]*x[i]);
        sum_yy += (y[i]*y[i]);
    } 

    lr['sl'] = (n * sum_xy - sum_x * sum_y) / (n*sum_xx - sum_x * sum_x);
    lr['off'] = (sum_y - lr.sl * sum_x)/n;
    lr['r2'] = Math.pow((n*sum_xy - sum_x*sum_y)/Math.sqrt((n*sum_xx-sum_x*sum_x)*(n*sum_yy-sum_y*sum_y)),2);

    return lr;
}
