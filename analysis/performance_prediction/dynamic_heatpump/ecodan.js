// Work out Mitsubushi Ecodan COP using look up table approach
function get_ecodan_cop(outlet,outside,load)
{
    var ecodan = {
      "min": {
        "data": [
          //-15,  -10,   -7,    2,    7,   12,   15,   20
          [null, 3.64, 3.52, 4.16, 5.69, 6.59, 7.06, 7.78], // 25
          [2.66, 3.01, 2.99, 3.59, 4.64, 5.26, 5.64, 6.26], // 35
          [2.38, 2.68, 2.67, 3.23, 4.03, 4.49, 4.78, 5.25], // 40
          [2.10, 2.34, 2.35, 2.86, 3.41, 3.73, 3.91, 4.23], // 45
          [null, 2.10, 2.12, 2.54, 3.07, 3.32, 3.46, 3.71], // 50
          [null, 1.86, 1.89, 2.21, 2.73, 2.91, 3.01, 3.19], // 55
          [null, null, null, null, null, null, null, null]  // 60
        ]
      },
      "mid": {
        "data": [
          //-15,  -10,   -7,    2,    7,   12,   15,   20
          [null, 3.64, 3.85, 4.90, 5.89, 6.58, 7.08, 7.98], // 25
          [2.66, 3.01, 3.25, 3.54, 4.63, 5.35, 5.79, 6.54], // 35
          [2.38, 2.68, 2.87, 3.35, 4.18, 4.66, 4.97, 5.48], // 40
          [2.10, 2.34, 2.50, 3.15, 3.73, 3.98, 4.15, 4.43], // 45
          [null, 2.10, 2.25, 2.78, 3.23, 3.43, 3.56, 3.78], // 50
          [null, 1.86, 2.00, 2.41, 2.74, 2.88, 2.98, 3.14], // 55
          [null, null, 1.70, 2.05, 2.56, 2.59, 2.62, 2.68]  // 60 (note 1.7 at -7 is not from datasheet)
        ]
      },
      "max": {
        "data": [
          //-15,  -10,   -7,    2,    7,   12,   15,   20
          [null, 3.30, 3.60, 4.20, 5.48, 6.20, 6.65, 7.41], // 25
          [2.44, 2.78, 3.00, 3.50, 4.50, 4.98, 5.28, 5.79], // 35
          [2.22, 2.51, 2.70, 3.15, 4.01, 4.37, 4.59, 4.98], // 40
          [2.00, 2.25, 2.40, 2.80, 3.52, 3.75, 3.91, 4.16], // 45
          [null, 2.05, 2.16, 2.47, 3.10, 3.27, 3.38, 3.57], // 50
          [null, 1.85, 1.92, 2.13, 2.68, 2.78, 2.85, 2.97], // 55
          [null, null, null, 1.80, 2.26, 2.30, 2.33, 2.38]  // 60
        ]
      }
    }
    
    var load_index = "min";
    if (load>=0.6) load_index = "mid";
    if (load>=0.8) load_index = "max";

    // 1. Find outlet index
    var outlet_index = false;
    var rows = [25,35,40,45,50,55,60];
    for (var z=0; z<rows.length; z++) {
        if (rows[z]>outlet) {
            outlet_index = z-1;
            break 
        } 
        else if (rows[z]==outlet) {
            outlet_index = z;
            break 
        }
    }
    // Limit outlet_index by available data
    if (outlet_index<0) outlet_index = 0;
    if (outlet_index===false || outlet_index == rows.length-1) {
        outlet_index = rows.length - 2;
    }

    // 2. Find outside index
    var outside_index = false;
    var cols = [-15,-10,-7,2,7,12,15,20];
    for (var z=0; z<rows.length; z++) {
        if (cols[z]>outside) {
            outside_index = z-1;
            break 
        }
        else if (cols[z]==outside) {
            outside_index = z;
            break 
        }
    }
    // Limit outlet_index by available data
    if (outside_index<0) outside_index = 0;
    if (outside_index===false || outside_index == cols.length-1) {
        outside_index = cols.length - 2;
    }

    

    // Work out linear interpolation part 1
    var a = ecodan[load_index].data[outlet_index][outside_index]
    var b = ecodan[load_index].data[outlet_index][outside_index+1]
    var c1 = a + (b-a) * (outside - cols[outside_index]) / (cols[outside_index+1] - cols[outside_index]);
    if (a==null || b==null) c1 = null;

    // Work out linear interpolation part 2
    var a = ecodan[load_index].data[outlet_index+1][outside_index]
    var b = ecodan[load_index].data[outlet_index+1][outside_index+1]
    var c2 = a + (b-a) * (outside - cols[outside_index]) / (cols[outside_index+1] - cols[outside_index]);
    if (a==null || b==null) c2 = null;

    // Work out linear interpolation part 3
    var cop = c1 + (c2-c1) * (outlet - rows[outlet_index]) / (rows[outlet_index+1] - rows[outlet_index]);
    if (c1==null || c2==null) cop = null;
    
    return cop;
}



