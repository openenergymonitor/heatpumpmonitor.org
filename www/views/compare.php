<?php
$id = 0;
if (isset($_GET['id'])) {
    $id = $_GET['id'];
}
?>

<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
<script src="https://cdn.plot.ly/plotly-2.18.1.min.js" charset="utf-8"></script>

<div id="app">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container-fluid">
            <h3>Compare</h3>
        </div>
    </div>
    <div class="container-fluid" style="margin-top:20px">
        <div class="row">
            <div class="col-md-5">

                <p>Compare hourly performance data over the last 30 days</p>
                <div class="input-group">
                    <div class="input-group-text">Mode</div>
                    <select class="form-control" v-model="mode" @change="change_mode">
                        <option value="cop_vs_dt">COP vs Flow - Outside temperature</option>
                        <option value="cop_vs_outside">COP vs Outside temperature</option>
                        <option value="cop_vs_flow">COP vs Flow temperature</option>
                        <option value="cop_vs_return">COP vs Return temperature</option>
                        <option value="cop_vs_carnot">COP vs Carnot COP</option>
                        <option value="flow_vs_outside">Flow temperature vs Outside temperature</option>
                        <option value="heat_vs_outside">Heat output vs Outside temperature</option>
                        <option value="elec_vs_outside">Electric input vs Outside temperature</option>
                        <option value="profile">Average Profile</option>
                    </select>

                    <div class="input-group-text">Interval</div>
                    <select class="form-control" v-model="interval" @change="change_interval">
                        <option value="1800">Half hourly</option>  
                        <option value="3600">Hourly</option>
                        <option value="86400">Daily</option>
                    </select>
                </div>

                <br><br>
                <table class="table">
                    <tr>
                        <th>Color</th>
                        <th>System</th>
                        <th>Start</th>
                        <th>End</th>
                        <th></th>
                        <th></th>
                    </tr>
                    <tr v-for="system,idx in selected_systems">
                        <td><input class="form-control" type="color" v-model="system.color" @change="change_color"></td>
                        <td>
                            <select class="form-control" v-model="system.id" @change="change_system(idx)">
                                <option v-for="s in system_list" :value="s.id">{{ s.location }}, {{ s.hp_model }}, {{ s.hp_output }} kW</option>
                            </select>
                        </td>
                        <td><input class="form-control" v-if="idx==0 || !match_dates" v-model="system.start" type="date"
                                @change="date_changed(idx)"></td>
                        <td><input class="form-control" v-if="idx==0 || !match_dates" v-model="system.end" type="date"
                                @change="date_changed(idx)"></td>
                        <td><button class="btn btn-warning" v-if="system.time_changed"
                                @click="change_dates(idx)">Load</button></td>
                        <td><button class="btn btn-danger" v-if="idx>0"
                                @click="remove_system(idx)">Remove</button>
                    </tr>
                </table>
                <button class="btn btn-primary" @click="add_system">+ Add system</button>
                <br><br>

                <h4>Options</h4>
                <div class="input-group">
                    <div class="input-group-text">Match dates</div>
                    <div class="input-group-text"><input v-model="match_dates" type="checkbox" @click="match_dates_fn"></div>
                </div>
                
            </div>
            <div class="col-md-7">
                <div id="gd" style="height:690px"></div>
            </div>  
        </div> 
    </div>  
</div> 

<script>
var id = <?php echo $id; ?>;
// initialise dates to be from 30 days ago to today
var today = new Date();
var daysago = new Date(new Date().setDate(today.getDate() - 30));
var start_date = daysago.toISOString().substring(0,10);
var end_date = today.toISOString().substring(0,10);

var selected_systems = [];

if (id) {
    selected_systems = [
        {color:"#0000ff", id:id, start: start_date, end: end_date, time_changed: false, data: false}
    ];
} else {
    selected_systems = [
        // {color:"#0000ff", id:1, start: start_date, end: end_date, time_changed: false, data: false},
        {color:"#ff0000", id:2, start: start_date, end: end_date, time_changed: false, data: false}
    ];
}
</script>

<script src="views/compare.js?v=15"></script>
