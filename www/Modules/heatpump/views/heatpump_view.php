<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>

<div id="app" class="bg-light">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container"  style="max-width:1000px;">

            <div class="row">
                <div class="col-12 col-sm-12 col-md-6 col-lg-6 col-xl-8">
                    <h3>{{ heatpump.manufacturer }} {{ heatpump.model }} {{ heatpump.capacity }}kW</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="container"  style="max-width:1000px;">

        <div class="row">
            <div class="col-8">
                <table id="custom" class="table table-striped mt-3">
                    <tr>
                        <th>HeatpumpMonitor.org headline stats</th>
                        <th></th>
                    </tr>
                    </tr>
                    <tr>
                        <td>Total number of systems</th>
                        <td><a :href="path+'?filter=query:hp_model:Vaillant,hp_output:5&period=all&minDays=0&other=1&hpint=1&errors=1'" target="_blank">{{ heatpump.stats.number_of_systems }}</a></td>
                    </tr>
                    <tr>
                        <td>Number of systems with 1 year of data</th>
                        <td><a :href="path+'?filter=query:hp_model:Vaillant,hp_output:5&other=1&hpint=1&errors=1'" target="_blank">{{ heatpump.stats.number_of_systems_last365 }}</a></td>
                    </tr>
                    <tr>
                        <td>Average SPF H4</th>
                        <td><b>{{ heatpump.stats.average_spf }}</b></td>
                    </tr>
                    <tr>
                        <td>Highest SPF H4</th>
                        <td>{{ heatpump.stats.highest_spf }}</td>
                    </tr>
                    <tr>
                        <td>Lowest SPF H4</th>
                        <td>{{ heatpump.stats.lowest_spf }}</td>
                    </tr>
                </table>
            </div>
            <div class="col-4">
                <img :src="path+'Modules/heatpump/img/'+heatpump.img" class="img-thumbnail mt-3" :alt="heatpump.img">
            </div>
        </div>



        <div class="row">
            <div class="col">
                <br>
                <button class="btn btn-primary btn-sm" style="float:right">+ Add</button>
                <h4>Minimum modulation test results</h4>
                <table id="custom" class="table table-striped table-sm mt-3" v-if="heatpump.min_mod_tests.length">
                    <tr>
                        <th>Test</th>
                        <th>System</th>
                        <th>Date</th>
                        <th>Duration</th>
                        <th>Elec input</th>
                        <th>Heat output</th>
                        <th></th>
                    </tr>
                    <tr v-for="test in heatpump.min_mod_tests">
                        <td>{{ test.id }}</td>
                        <td>{{ test.system }}</td>
                        <td>{{ test.date }}</td>
                        <td>{{ test.data_length / 3600 | toFixed(1) }} hrs</td>
                        <td>{{ test.elec | toFixed(0) }}W</td>
                        <td>{{ test.heat | toFixed(0) }}W</td>
                        <td style="width:120px">
                            <a :href="test.data" target="_blank">
                                <button class="btn btn-secondary btn-sm" title="Dashboard"><i class="fa fa-chart-bar" style="color: #ffffff;"></i></button>
                            </a>
                            <button class="btn btn-warning btn-sm" title="Edit"><i class="fa fa-edit" style="color: #ffffff;"></i></button>
                            <button class="btn btn-danger btn-sm" title="Delete" @click="delete_min_mod_test(id)"><i class="fa fa-trash" style="color: #ffffff;"></i></button>
                        </td>
                </table>
                <div v-else class="alert alert-warning mt-3">No tests recorded</div>
                <div class="row">
                    <div class="col">
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" placeholder="Paste MyHeatpump app URL of max capacity test period here" v-model="new_min_mod_test_url">
                            <button class="btn btn-primary" type="button" @click="load_min_mod_test_data">Load and save</button>
                        </div>
                    </div>
                </div>


                <br>
                <button class="btn btn-primary btn-sm" style="float:right">+ Add</button>
                <h4>Maximum capacity test results</h4>
                <table id="custom" class="table table-striped table-sm mt-3" v-if="heatpump.max_cap_tests.length">
                    <tr>
                        <th>Test</th>
                        <th>System</th>
                        <th>Date</th>
                        <th>Duration</th>
                        <th>Flow temp</th>
                        <th>Outside temp</th>
                        <th>Elec input</th>
                        <th>COP</th>         
                        <th>Heat output</th>
                        <th></th>
                    </tr>
                    <tr v-for="test in heatpump.max_cap_tests">
                        <td>{{ test.id }}</td>
                        <td>{{ test.system }}</td>
                        <td>{{ test.date }}</td>
                        <td>{{ test.data_length / 3600 | toFixed(1) }} hrs</td>
                        <td>{{ test.flowT | toFixed(1) }}&deg;C</td>
                        <td>{{ test.outsideT | toFixed(1) }}&deg;C</td>
                        <td>{{ test.elec | toFixed(0) }}W</td>
                        <td>{{ test.cop }}</td>
                        <td>{{ test.heat | toFixed(0) }}W</td>
                        <td style="width:120px">
                            <a :href="test.data" target="_blank">
                                <button class="btn btn-secondary btn-sm" title="Dashboard"><i class="fa fa-chart-bar" style="color: #ffffff;"></i></button>
                            </a>
                            <button class="btn btn-warning btn-sm" title="Edit"><i class="fa fa-edit" style="color: #ffffff;"></i></button>
                            <button class="btn btn-danger btn-sm" title="Delete" @click="delete_max_cap_test(id)"><i class="fa fa-trash" style="color: #ffffff;"></i></button>
                        </td>
                    </tr>

                </table>

                <div class="row">
                    <div class="col">
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" placeholder="Paste MyHeatpump app URL of max capacity test period here" v-model="new_max_cap_test_url">
                            <button class="btn btn-primary" type="button" @click="load_max_cap_test_data">Load and save</button>
                        </div>
                    </div>
                </div>
                

                <div v-else class="alert alert-warning mt-3">No tests recorded</div>

                <br>
                <button class="btn btn-primary btn-sm" @click="enable_edit" style="float:right">Edit</button>
                <h4>Heatpump properties</h4>

                <table id="custom" class="table table-striped mt-3">
                    <tr>
                        <th>Property</th>
                        <th>Value</th>
                    </tr>
                    <tr>
                        <td>Manufacturer</th>
                        <td v-if="!edit_properties">{{ heatpump.manufacturer }}</td>
                        <td v-if="edit_properties"><input type="text" class="form-control" v-model="heatpump.manufacturer"></td>
                    </tr>
                    <tr>
                        <td>Model</th>
                        <td v-if="!edit_properties">{{ heatpump.model }}</td>
                        <td v-if="edit_properties"><input type="text" class="form-control" v-model="heatpump.model"></td>
                    </tr>
                    <tr>
                        <td>Capacity</th>
                        <td v-if="!edit_properties">{{ heatpump.capacity }} kW</td>
                        <td v-if="edit_properties">
                            <div class="input-group">
                                <input type="text" class="form-control" v-model="heatpump.capacity">
                                <div class="input-group-append">
                                    <span class="input-group-text">kW</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>Minimum flow rate</th>
                        <td v-if="!edit_properties">{{ heatpump.min_flow_rate }} L/min (Min mod @ DT5 = {{ (heatpump.min_flow_rate/60)*5*4150 | toFixed(0) }}W)</td>
                        <td v-if="edit_properties">
                            <div class="input-group">
                                <input type="text" class="form-control" v-model="heatpump.min_flow_rate">
                                <div class="input-group-append">
                                    <span class="input-group-text">L/min</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<script>

    var app = new Vue({
        el: '#app',
        data: {
            id: "<?php echo $id; ?>",
            edit_properties: false,
            path: "<?php echo $path; ?>",
            heatpump: {},
            new_max_cap_test_url: null,
            new_min_mod_test_url: null
        },
        created: function() {
            this.load_heatpump();
        },
        methods: {
            enable_edit: function() {
                this.edit_properties = true;
            },
            load_heatpump: function() {
                axios.get(this.path+'heatpump/get?id='+this.id)
                    .then(response => {
                        this.heatpump = response.data;
                    });
            },
            delete_min_mod_test: function(id) {
                if (confirm("Are you sure you want to delete this test?")) {
                    /*
                    axios.get(this.path+'heatpump/min_mod_test/delete?id='+id)
                        .then(response => {
                            this.load_heatpump();
                        });
                    */
                }
            },
            delete_max_cap_test: function(id) {
                if (confirm("Are you sure you want to delete this test?")) {
                    /*
                    axios.get(this.path+'heatpump/max_cap_test/delete?id='+id)
                        .then(response => {
                            this.load_heatpump();
                        });
                    */
                }
            },
            load_max_cap_test_data: function() {
                if (this.new_max_cap_test_url) {
                    // send url in post request to server
                    axios.post(this.path+'heatpump/max_cap_test/load', {url: this.new_max_cap_test_url})
                        .then(response => {
                            var test_result = response.data;
                            app.heatpump.max_cap_tests.push(test_result);
                        });
                }
            },
            load_min_mod_test_data: function() {
                if (this.new_min_mod_test_url) {
                    // send url in post request to server
                    axios.post(this.path+'heatpump/max_cap_test/load', {url: this.new_min_mod_test_url})
                        .then(response => {
                            var test_result = response.data;
                            app.heatpump.min_mod_tests.push(test_result);
                        });
                }
            }
        },
        filters: {
            toFixed: function (val, dp) {
                if (isNaN(val)) {
                    return val;
                } else {
                    return val.toFixed(dp)
                }
            }
        }
    });
</script>