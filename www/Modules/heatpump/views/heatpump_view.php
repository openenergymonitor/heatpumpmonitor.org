<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>

<div id="app" class="bg-light">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container"  style="max-width:1200px;">

            <div class="row">
                <div class="col-12 col-sm-12 col-md-6 col-lg-6 col-xl-8">
                    <h3>{{ heatpump.manufacturer_name }} {{ heatpump.name }} {{ heatpump.refrigerant }} {{ heatpump.capacity }}kW</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="container"  style="max-width:1200px;" v-if="loaded">

        <div class="row" v-if="heatpump.stats">
            <div class="col-8">
                <table id="custom" class="table table-striped mt-3">
                    <tr>
                        <th>HeatpumpMonitor.org headline stats</th>
                        <th></th>
                    </tr>
                    </tr>
                    <tr>
                        <td>Total number of systems</th>
                        <td><a :href="path+'?filter=query:hp_model:'+encodeURIComponent(heatpump.name)+',hp_output:'+heatpump.capacity+'&period=all&minDays=0&other=1&hpint=1&errors=1'" target="_blank">{{ heatpump.stats.number_of_systems }}</a></td>
                    </tr>
                    <tr>
                        <td>Number of systems with 1 year of data</th>
                        <td><a :href="path+'?filter=query:hp_model:'+encodeURIComponent(heatpump.name)+',hp_output:'+heatpump.capacity+'&other=1&hpint=1&errors=1'" target="_blank">{{ heatpump.stats.number_of_systems_last365 }}</a></td>
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
            <div class="col-4" v-if="heatpump.img">
                <img :src="path+'Modules/heatpump/img/'+heatpump.img" class="img-thumbnail mt-3" :alt="heatpump.img">
            </div>
        </div>

        <div class="row">
            <div class="col">
                <div v-if="min_mod_enabled">
                    <br>
                    <h4>Minimum modulation test results</h4>
                    <table id="custom" class="table table-striped table-sm mt-3" v-if="min_mod_tests.length">
                        <tr>
                            <th>Test</th>
                            <th>System</th>
                            <th>Date</th>
                            <th>Duration</th>
                            <th>Elec input</th>
                            <th>Heat output</th>
                            <th></th>
                        </tr>
                        <tr v-for="test in min_mod_tests">
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
                                <button class="btn btn-danger btn-sm" title="Delete" @click="delete_min_mod_test(id)" v-if="mode=='admin'"><i class="fa fa-trash" style="color: #ffffff;"></i></button>
                            </td>
                        </tr>
                    </table>
                    <div v-if="min_mod_tests.length==0" class="alert alert-warning mt-3">No tests recorded</div>
                    <div class="row" v-if="userid>0">
                        <div class="col">
                            <div class="input-group mb-3">
                                <input type="text" class="form-control" placeholder="Paste MyHeatpump app URL of min modulation test period here" v-model="new_min_mod_test_url">
                                <button class="btn btn-primary" type="button" @click="load_min_mod_test_data">Load and save</button>
                            </div>
                        </div>
                    </div>
                </div>

                <br>
                <h4>Maximum capacity test results</h4>
                <table id="custom" class="table table-striped table-sm mt-3" v-if="max_cap_tests.length">
                    <tr>
                        <th>Test</th>
                        <th>System</th>
                        <th>Date</th>
                        <th>Duration</th>
                        <th>Flow temp</th>
                        <th>Outside temp</th>
                        <th>Flowrate</th>
                        <th>Elec input</th>
                        <th>COP</th>         
                        <th>Heat output</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                    <tr v-for="(test,index) in max_cap_tests" :class="{'table-warning': test.review_status != 1}">
                        <td>{{ index+1 }}</td>
                        <td>{{ test.system_id }}</td>
                        <td>{{ test.date }}</td>
                        <td>{{ test.data_length / 3600 | toFixed(1) }} hrs</td>
                        <td>{{ test.flowT | toFixed(1) }}&deg;C</td>
                        <td>{{ test.outsideT | toFixed(1) }}&deg;C</td>
                        <td>{{ test.flowrate | toFixed(1) }} L/min</td>
                        <td>{{ test.elec | toFixed(0) }}W</td>
                        <td>{{ test.cop }}</td>
                        <td>{{ test.heat | toFixed(0) }}W</td>
                        <td>
                            <span v-if="test.review_status==0" class="badge bg-secondary" :title="test.review_comment || ''">Pending review</span>
                            <span v-if="test.review_status==1" class="badge bg-success" :title="test.review_comment || ''">Approved</span>
                            <span v-if="test.review_status==2" class="badge bg-danger" :title="test.review_comment || ''">Rejected</span>
                            <span v-if="test.review_status==3" class="badge bg-warning" :title="test.review_comment || ''">Needs more data</span>
                        </td>
                        <td style="width:120px">
                            <a :href="test.test_url" target="_blank">
                                <button class="btn btn-secondary btn-sm" title="Dashboard"><i class="fa fa-chart-bar" style="color: #ffffff;"></i></button>
                            </a>
                            <button class="btn btn-warning btn-sm" title="Review" @click="open_review_modal(test)" v-if="mode=='admin'"><i class="fa fa-eye" style="color: #ffffff;"></i></button>
                            <button class="btn btn-danger btn-sm" title="Delete" @click="delete_max_cap_test(test.id)" v-if="mode=='admin' || userid==test.userid"><i class="fa fa-trash" style="color: #ffffff;"></i></button>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="9" class="text-end"><b>Average heat output<span v-if="unapprovedTestsCount > 0"> (approved tests)</span></b></td>
                        <td><b>{{ average_cap_test | toFixed(0) }}W</b></td>
                        <td></td>
                        <td></td>
                    </tr>
                </table>
                <div v-if="max_cap_tests.length==0" class="alert alert-secondary mt-3">No tests recorded</div>

                <div class="row" v-if="userid>0">
                    <div class="col">
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" placeholder="Paste MyHeatpump app URL of max capacity test period here" v-model="new_max_cap_test_url">
                            <button class="btn btn-primary" type="button" @click="load_max_cap_test_data">Load and save</button>
                        </div>
                    </div>
                </div>
                

                <!-- Review Modal (Admin Only) -->
                <div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true" v-if="mode=='admin'">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="reviewModalLabel">Review Test</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div v-if="review_test">
                                    <h6>Test Details:</h6>
                                    <p><strong>System:</strong> {{ review_test.system_id }}</p>
                                    <p><strong>Date:</strong> {{ review_test.date }}</p>
                                    <p><strong>Duration:</strong> {{ review_test.data_length / 3600 | toFixed(1) }} hrs</p>
                                    <p><strong>Heat Output:</strong> {{ review_test.heat | toFixed(0) }}W</p>
                                    <p><strong>COP:</strong> {{ review_test.cop }}</p>
                                    
                                    <div class="mb-3">
                                        <label for="review_status" class="form-label">Status</label>
                                        <select class="form-select" id="review_status" v-model="review_form.status">
                                            <option value="0">Pending review</option>
                                            <option value="1">Approved</option>
                                            <option value="2">Rejected</option>
                                            <option value="3">Needs more data</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="review_comment" class="form-label">Comment</label>
                                        <textarea class="form-control" id="review_comment" rows="3" v-model="review_form.comment" placeholder="Add review comments..."></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" @click="submit_review">Save Review</button>
                            </div>
                        </div>
                    </div>
                </div>

                <br>
                <button class="btn btn-primary btn-sm" @click="enable_edit" style="float:right" v-if="mode=='admin'">Edit</button>
                <h4>Heatpump properties</h4>

                <table id="custom" class="table table-striped mt-3">
                    <tr>
                        <th>Property</th>
                        <th>Value</th>
                    </tr>
                    <tr>
                        <td>Manufacturer</th>
                        <td v-if="!edit_properties">{{ heatpump.manufacturer_name }}</td>
                        <td v-if="edit_properties"><input type="text" class="form-control" v-model="heatpump.manufacturer_name"></td>
                    </tr>
                    <tr>
                        <td>Model</th>
                        <td v-if="!edit_properties">{{ heatpump.name }}</td>
                        <td v-if="edit_properties"><input type="text" class="form-control" v-model="heatpump.name"></td>
                    </tr>
                    <tr>
                        <td>Refrigerant</th>
                        <td v-if="!edit_properties">{{ heatpump.refrigerant }}</td>
                        <td v-if="edit_properties"><input type="text" class="form-control" v-model="heatpump.refrigerant"></td>
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
                    <!--
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
                    -->
                </table>
            </div>
        </div>
    </div>
</div>

<script>

    var app = new Vue({
        el: '#app',
        data: {
            mode: "<?php echo $mode; ?>",
            userid: <?php echo $userid; ?>,
            loaded: false,
            id: "<?php echo $id; ?>",
            edit_properties: false,
            path: "<?php echo $path; ?>",
            heatpump: {},
            max_cap_tests: [],
            average_cap_test: 0,
            min_mod_tests: [],
            new_max_cap_test_url: null,
            new_min_mod_test_url: null,
            min_mod_enabled: false,
            review_test: null,
            review_form: {
                status: 0,
                comment: ''
            }
        },
        created: function() {
            this.load_heatpump();
            this.load_max_cap_test_list();
            this.load_min_mod_test_list();
        },
        methods: {
            enable_edit: function() {
                this.edit_properties = true;
            },
            load_heatpump: function() {
                $.get(this.path+'heatpump/get?id='+this.id)
                    .done(response => {
                        this.heatpump = response;
                    });
            },
            load_max_cap_test_list: function() {
                let self = this;
                $.get(this.path+'heatpump/max_cap_test/list?id='+this.id)
                    .done(response => {
                        self.max_cap_tests = response;

                        // Calculate average heat output
                        const validTests = self.max_cap_tests.filter(test => test.heat && test.review_status == 1);
                        self.average_cap_test = validTests.length > 0 
                            ? (validTests.reduce((sum, test) => sum + parseFloat(test.heat), 0) / validTests.length).toFixed(0)
                            : 0;

                        self.loaded = true;
                    });
            },
            load_min_mod_test_list: function() {
                // Not yet implemented
            },
            delete_min_mod_test: function(id) {
                if (confirm("Are you sure you want to delete this test?")) {
                    /*
                    $.get(this.path+'heatpump/min_mod_test/delete?id='+id)
                        .done(response => {
                            this.load_heatpump();
                        });
                    */
                }
            },
            delete_max_cap_test: function(id) {
                if (confirm("Are you sure you want to delete this test?")) {
                    $.get(this.path+'heatpump/max_cap_test/delete?id='+id)
                        .done(response => {
                            this.load_max_cap_test_list();
                        });
                }
            },
            load_max_cap_test_data: function() {
                if (this.new_max_cap_test_url) {
                    // send url in post request to server
                    $.post(this.path+'heatpump/max_cap_test/load?id='+this.id, {url: this.new_max_cap_test_url})
                        .done(response => {
                            this.load_max_cap_test_list();
                            this.new_max_cap_test_url = null;
                        });
                }
            },
            load_min_mod_test_data: function() {
                if (this.new_min_mod_test_url) {
                    // send url in post request to server
                    $.post(this.path+'heatpump/max_cap_test/load', {url: this.new_min_mod_test_url})
                        .done(response => {
                            var test_result = response;
                            app.heatpump.min_mod_tests.push(test_result);
                        });
                }
            },
            open_review_modal: function(test) {
                this.review_test = test;
                this.review_form.status = test.review_status;
                this.review_form.comment = test.review_comment || '';
                $('#reviewModal').modal('show');
            },
            submit_review: function() {
                if (!this.review_test) return;
                
                $.post(this.path+'heatpump/max_cap_test/update_status', {
                    id: this.review_test.id,
                    status: this.review_form.status,
                    message: this.review_form.comment
                })
                .done(response => {
                    if (response.success) {
                        $('#reviewModal').modal('hide');
                        this.load_max_cap_test_list();
                        this.review_test = null;
                        this.review_form = { status: 0, comment: '' };
                    } else {
                        alert('Error: ' + response.message);
                    }
                })
                .fail(() => {
                    alert('Failed to update test status');
                });
            }
        },
        computed: {
            unapprovedTestsCount: function() {
                return this.max_cap_tests.filter(test => test.review_status != 1).length;
            }
        },
        filters: {
            toFixed: function (val, dp) {
                if (isNaN(val)) {
                    return val;
                } else {
                    val = parseFloat(val);
                    return val.toFixed(dp)
                }
            }
        }
    });
</script>