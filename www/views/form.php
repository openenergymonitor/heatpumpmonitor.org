<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Heat Pump Monitoring Submission</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
</head>
<body >

<div id="app">

<div style=" background-color:#44b3e2; color:#fff; padding-top:20px; padding-bottom:10px">
  <div class="container" style="max-width:800px;">
    <h3>Heat Pump Monitoring Submission</h3>
    <p>If you have a heat pump and publish stats via emoncms, submit your details here.</p>
  </div>
</div>

<div class="container" style="max-width:800px" >
  <br>

  <div class="row">
    <div class="col">   
      <p><b>Email *</b></p>
      <div class="input-group mb-3">
        <input type="text" class="form-control" v-model="email" @change="update"> 
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col">  
      <p><b>Name *</b><br>Your name. This will not be displayed publicly but will allow for updating records if necessary.</p>
      <div class="input-group mb-3">
        <input type="text" class="form-control" v-model="name" @change="update"> 
      </div>
    </div>
    <div class="col">  
      <p><b>Vague Location *</b><br>Roughly where the heat pump is installed, to nearest city or county.</p>
      <div class="input-group mb-3">
        <input type="text" class="form-control" v-model="location" @change="update"> 
      </div>
    </div>
  </div>
  
  <hr>
  <h4>About Your Heating System</h4>
  
  <div class="row">
    <div class="col">  
      <p><b>Heat Pump Make / Model</b></p>
      <div class="input-group mb-3">
        <input type="text" class="form-control" v-model="hp_model" @change="update"> 
      </div>
    </div>
    <div class="col">  
      <p><b>Heat Pump Type</b></p>
      <div class="input-group mb-3">
        <select class="form-control" @change="update" v-model="hp_type">
          <option>Air Source</option>
          <option>Ground Source</option>
          <option>Water Source</option>
          <option>Air-to-Air</option>
          <option>Other</option>
        </select>
      </div>
      
    </div>
  </div>
  <div class="row">
  
    <div class="col">
      <p><b>Refrigerant type</b><br>(e.g R410a, R32, R290)</p>
      <div class="input-group mb-3">
        <input type="text" class="form-control" v-model="refrigerant" @change="update"> 
      </div>
    </div>
    
    <div class="col">
      <p><b>Heat Output</b><br>Maximum rated heat output</p>
      <div class="input-group mb-3">
        <input type="text" class="form-control" v-model.number="hp_output" @change="update"> 
        <span class="input-group-text">kW</span>
      </div>
    </div>
  </div>
  
  <div class="row">
    <div class="col">

      <p><b>System includes buffer or low loss header</b></p>
      <div class="input-group mb-3">
        <select class="form-control" v-model="buffer" @change="update">
          <option>Yes</option>
          <option>No</option>
        </select>
      </div>
    </div>
  </div>
  
  <div class="row">
    <p><b>Heat Emitters</b></p>
    
    <div class="col">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" v-model="emitters" @click="update">
        <label class="form-check-label">
          New radiators
        </label>
      </div>
    </div>
    <div class="col">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" v-model="emitters" @click="update">
        <label class="form-check-label">
          Existing radiators
        </label>
      </div>
    </div>
    <div class="col">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" v-model="emitters" @click="update">
        <label class="form-check-label">
          Underfloor heating
        </label>
      </div>
      <br>
    </div>
  </div>
  <div class="row">
    <p><b>Weather compensation</b></p>
    <div class="col">    
      <p>Flow temperature of heat emitters at design temperature (e.g 45C at -3C outside)</p>
      <div class="input-group mb-3">
        <input type="text" class="form-control" v-model="flow_temp" @change="update"> 
        <span class="input-group-text">°C</span>
      </div> 
    </div>
    <div class="col">    
      <p>Typical flow temperature of heat emitters in January (e.g 35C at 6C outside)</p>
      <div class="input-group mb-3">
        <input type="text" class="form-control" @change="update"> 
        <span class="input-group-text">°C</span>
      </div> 
    </div>
    <div class="col">    
      <p>Curve setting<br>(if known e.g 0.6)<br><br></p>
      <div class="input-group mb-3">
        <input type="text" class="form-control" @change="update"> 
      </div> 
    </div>
  </div>
  <div class="row">
    <div class="col">
      <p><b>Heating zones</b><br>Number and configuration</p>
      <div class="input-group mb-3">
        <input type="text" class="form-control" v-model="zone" @change="update"> 
      </div> 

      <p><b>Space heating control settings</b><br>e.g weather-compensation, 3rd party thermostat, heat pumps own controller, auto-adapt.<br>Please provide details.</p>
      <div class="input-group mb-3">
        <input type="text" class="form-control" v-model="controls" @change="update"> 
      </div> 

      <p><b>Water heating control settings</b><br>(e.g scheduled 2am heat up period or top-up if temperature drops by 5 degrees)</p>
      <div class="input-group mb-3">
        <input type="text" class="form-control" v-model="dhw" @change="update"> 
      </div>

      <p><b>Legionella protection settings</b><br>e.g weekly immersion heater schedule 55°C</p>
      <div class="input-group mb-3">
        <input type="text" class="form-control" v-model="legionella" @change="update"> 
      </div>
    </div>
  </div>
  <div class="row">
    <p><b>Anti-freeze protection</b></p>
    <div class="col">
      <div class="form-check">
        <input class="form-check-input" type="radio" v-model="freeze">
        <label>
          Glycol/water mixture
        </label>
      </div>
    </div>
    <div class="col">
      <div class="form-check">
        <input class="form-check-input" type="radio" v-model="freeze">
        <label>
          Anti-freeze valves
        </label>
      </div>
    </div>
    <div class="col">
      <div class="form-check">
        <input class="form-check-input" type="radio" v-model="freeze">
        <label>
          Central heat pump water circulation
        </label>
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col">
      <p><b>Additional notes</b></p>
      <div class="input-group mb-3">
        <input type="text" class="form-control" v-model="notes" @change="update"> 
      </div>
          
      <hr>
      <h4>About Your Property</h4>
      <br>

    </div>
  </div>
  <div class="row">
    <div class="col"> 
      <p><b>Type of property</b></p>
      <div class="input-group mb-3">
        <select class="form-control" v-model="property">
          <option>Detached</option>
          <option>Semi-detached</option>
          <option>End-terrace</option>
          <option>Mid-terrace</option>
          <option>Flat / appartment</option>
          <option>Bungalow</option>
          <option>Office building</option>
        </select>
      </div>
    </div>    
    <div class="col"> 
      <p><b>Age of property</b></p>
      <div class="input-group mb-3">
        <select class="form-control" v-model="age">
          <option>2012 or newer</option>
          <option>1983 to 2011</option>
          <option>1940 to 1982</option>
          <option>1900 to 1939</option>
          <option>Pre-1900</option>
        </select>
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col"> 
      <p><b>Floor area</b></p>
      <div class="input-group mb-3">
        <input type="text" class="form-control" v-model.number="floor_area" @change="update"> 
        <span class="input-group-text">m2</span>
      </div> 
    </div>    
    <div class="col"> 
      <p><b>Level of Insulation</b></p>
      <div class="input-group mb-3">
        <select class="form-control" v-model="insulation">
          <option>Passivhaus</option>
          <option>Fully insulated walls, floors and loft</option>
          <option>Some insulation in walls and loft</option>
          <option>Cavity wall, plus some loft insulation</option>
          <option>Solid walls</option>
        </select>
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col"> 
      <p><b>Annual heating demand</b><br>For example, as given on the EPC for the property</p>
      <div class="input-group mb-3">
        <input type="text" class="form-control" v-model.number="heat_demand" @change="update"> 
        <span class="input-group-text">kWh</span>
      </div> 
    </div>    
    <div class="col"> 
      <p><b>Heat loss at design temperature</b><br>Usually available on heat pump quote</p>
      <div class="input-group mb-3">
        <input type="text" class="form-control" v-model.number="heat_loss" @change="update"> 
        <span class="input-group-text">kW @ -3°C</span>
      </div> 
    </div>
  </div>

  <div class="row">
    <div class="col"> 
      <hr>
      <h4>Monitoring information</h4>
      
      <p><b>URL of public MyHeatPump app</b><br>
      Requires an account on emoncms.org, or a self-hosted instance of emoncms</p>
      <div class="input-group mb-3">
        <input type="text" class="form-control" v-model="url" @change="update"> 
      </div> 
    </div>
  </div>
</div>
<div style=" background-color:#eee; padding-top:20px; padding-bottom:10px">
  <div class="container" style="max-width:800px;">
  <div class="row">
    <div class="col">
      <p><b>Agree to share this information publicly</b><br>
      (except for name and email address)</p>
    </div>
    <div class="col"> 
      <br>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" checked>
        <label class="form-check-label">
          Yes
        </label>
      </div>
    </div>
  </div>
  
  <button type="button" class="btn btn-primary" disabled>Save</button>
  <br><br>
</div>
</div>

</body>
</html>

<script>

var data = <?php echo json_encode($system); ?>;
console.log(data)


data.email = "---"
data.name = "---"
data.room = 20
var app = new Vue({
  el: '#app',
  /*
  data: {
    email: "trystan.lea@gmail.com",
    name: "Trystan",
    location: "Llanberis",
    heatpump: {
      make_model: "Mitsubushi EcoDan",
      type: "Air Source",
      refrigerant: "R410a",
      capacity: "5",
      buffer: "No"
    },
    emitters: {
      new_radiators: true,
      existing_radiators: false,
      underfloor: false
    },
    controls: {
      design_flow_temperature: 42,
      average_flow_temperature: 33,
      curve_setting: ''
    },
    room: 20
  },
  */
  data: data,
  methods: {
    update: function () {
     
    }
  },
  filters: {
    toFixed: function(val,dp) {
      if (isNaN(val)) {
          return val;
      } else {
          return val.toFixed(dp)
      }
    }
  }
});

</script>
