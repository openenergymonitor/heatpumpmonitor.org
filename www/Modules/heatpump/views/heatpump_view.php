<?php
defined('EMONCMS_EXEC') or die('Restricted access');
?>

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
                        <td><a :href="path+'?filter=query:hp_model:'+encodeURIComponent(heatpump.name)+',hp_output:'+heatpump.capacity+',refrigerant:'+heatpump.refrigerant+'&minDays=0&other=1&hpint=1&errors=1'" target="_blank">{{ heatpump.stats.number_of_systems }}</a></td>
                    </tr>
                    <tr>
                        <td>Number of systems with 1 year of data</th>
                        <td><a :href="path+'?filter=query:hp_model:'+encodeURIComponent(heatpump.name)+',hp_output:'+heatpump.capacity+',refrigerant:'+heatpump.refrigerant+'&other=1&hpint=1&errors=1'" target="_blank">{{ heatpump.stats.number_of_systems_last365 }}</a></td>
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

                <h4>Minimum modulation test results</h4>
                <table id="custom" class="table table-striped table-sm mt-3" v-if="min_cap_tests.length">
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
                    <tr v-for="(test,index) in min_cap_tests" :class="{'table-warning': test.review_status != 1}">
                        <td>{{ index+1 }}</td>
                        <td :title="test.system_hp_model+' '+test.system_refrigerant+' '+test.system_hp_output+'kW'"><a :href="path+'system/view?id='+test.system_id">{{ test.system_id }}</a></td>
                        <td>{{ test.date }}</td>
                        <td>{{ test.data_length / 60 | toFixed(0) }} mins</td>
                        <td>{{ test.flowT | toFixed(1) }}&deg;C</td>
                        <td>{{ test.outsideT | toFixed(1) }}&deg;C</td>
                        <td>{{ test.flowrate | toFixed(1) }} L/min</td>
                        <td><b>{{ test.elec | toFixed(0) }}W</b></td>
                        <td>{{ test.cop | toFixed(1) }}</td>
                        <td>{{ test.heat | toFixed(0) }}W</td>
                        <td>
                            <span v-if="test.review_status==0" class="badge bg-secondary" :title="test.review_comment || ''">Pending review</span>
                            <span v-if="test.review_status==1" class="badge bg-success" :title="test.review_comment || ''">Approved</span>
                            <span v-if="test.review_status==2" class="badge bg-danger" :title="test.review_comment || ''">Rejected</span>
                            <span v-if="test.review_status==3" class="badge bg-warning" :title="test.review_comment || ''">Needs more data</span>
                        </td>
                        <td style="width:120px">
                            <a :href="'https://heatpumpmonitor.org/dashboard?id='+test.system_id+'&mode=power&start='+test.start+'&end='+test.end" target="_blank">
                                <button class="btn btn-secondary btn-sm" title="Dashboard"><i class="fa fa-chart-bar" style="color: #ffffff;"></i></button>
                            </a>
                            <button class="btn btn-warning btn-sm" title="Review" @click="open_review_modal('min',test)" v-if="mode=='admin'"><i class="fa fa-eye" style="color: #ffffff;"></i></button>
                            <button class="btn btn-danger btn-sm" title="Delete" @click="delete_cap_test('min', test.id)" v-if="mode=='admin' || userid==test.userid"><i class="fa fa-trash" style="color: #ffffff;"></i></button>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="9" class="text-end"><b>Average heat output<span v-if="unapprovedMinTestsCount > 0"> (approved tests)</span></b></td>
                        <td><b>{{ average_min_cap_test | toFixed(0) }}W</b></td>
                        <td></td>
                        <td></td>
                    </tr>
                </table>
                <div v-if="min_cap_tests.length==0" class="alert alert-secondary mt-3">No tests recorded</div>

                <div class="row" v-if="userid>0">
                    <div class="col">
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" placeholder="Paste MyHeatpump app URL of min capacity test period here" v-model="new_min_cap_test_url">
                            <button class="btn btn-primary" type="button" @click="load_cap_test_data('min')">Load and save</button>
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
                        <td :title="test.system_hp_model+' '+test.system_refrigerant+' '+test.system_hp_output+'kW'"><a :href="path+'system/view?id='+test.system_id">{{ test.system_id }}</a></td>
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
                            <a :href="'https://heatpumpmonitor.org/dashboard?id='+test.system_id+'&mode=power&start='+test.start+'&end='+test.end+'&cool=1'" target="_blank">
                                <button class="btn btn-secondary btn-sm" title="Dashboard"><i class="fa fa-chart-bar" style="color: #ffffff;"></i></button>
                            </a>
                            <button class="btn btn-warning btn-sm" title="Review" @click="open_review_modal('max',test)" v-if="mode=='admin'"><i class="fa fa-eye" style="color: #ffffff;"></i></button>
                            <button class="btn btn-danger btn-sm" title="Delete" @click="delete_cap_test('max', test.id)" v-if="mode=='admin' || userid==test.userid"><i class="fa fa-trash" style="color: #ffffff;"></i></button>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="9" class="text-end"><b>Average heat output<span v-if="unapprovedMaxTestsCount > 0"> (approved tests)</span></b></td>
                        <td><b>{{ average_max_cap_test | toFixed(0) }}W</b></td>
                        <td></td>
                        <td></td>
                    </tr>
                </table>
                <div v-if="max_cap_tests.length==0" class="alert alert-secondary mt-3">No tests recorded</div>

                <div class="row" v-if="userid>0">
                    <div class="col">
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" placeholder="Paste MyHeatpump app URL of max capacity test period here" v-model="new_max_cap_test_url">
                            <button class="btn btn-primary" type="button" @click="load_cap_test_data('max')">Load and save</button>
                        </div>
                    </div>
                </div>
                

                <!-- Review Modal (Admin Only) -->
                <div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true" v-if="mode=='admin'">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header" v-if="review_test">
                                <h5 class="modal-title" id="reviewModalLabel">Review <span class="text-primary">{{ review_test.type }}</span> Test</h5>
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
                <div style="float:right" v-if="mode=='admin'">
                    <button class="btn btn-primary btn-sm" @click="enable_edit" v-if="!edit_properties">Edit</button>
                    <button class="btn btn-success btn-sm me-2" @click="save_properties" v-if="edit_properties">Save</button>
                    <button class="btn btn-secondary btn-sm" @click="cancel_edit" v-if="edit_properties">Cancel</button>
                </div>
                <h4>Heatpump properties</h4>

                <table id="custom" class="table table-striped mt-3">
                    <tr>
                        <th>Property</th>
                        <th>Value</th>
                        <th v-if="!edit_properties"></th>
                    </tr>
                    <tr>
                        <td>Manufacturer</th>
                        <td v-if="!edit_properties">{{ heatpump.manufacturer_name }}</td>
                        <td v-if="edit_properties"><input type="text" class="form-control" v-model="heatpump.manufacturer_name"></td>
                        <td v-if="!edit_properties"></td>
                    </tr>
                    <tr>
                        <td>Model</th>
                        <td v-if="!edit_properties">{{ heatpump.name }}</td>
                        <td v-if="edit_properties"><input type="text" class="form-control" v-model="heatpump.name"></td>
                        <td v-if="!edit_properties"></td>
                    </tr>
                    <tr>
                        <td>Refrigerant</th>
                        <td v-if="!edit_properties">{{ heatpump.refrigerant }}</td>
                        <td v-if="edit_properties"><input type="text" class="form-control" v-model="heatpump.refrigerant"></td>
                        <td v-if="!edit_properties"></td>
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
                        <td v-if="!edit_properties"></td>
                    </tr>
                    <tr>
                        <td>Min flow rate</th>
                        <td v-if="!edit_properties">{{ heatpump.min_flowrate || 'Not specified' }} <span v-if="heatpump.min_flowrate">L/min</span></td>
                        <td v-if="edit_properties">
                            <div class="input-group">
                                <input type="number" step="0.1" class="form-control" v-model="heatpump.min_flowrate" placeholder="e.g. 10.5">
                                <div class="input-group-append">
                                    <span class="input-group-text">L/min</span>
                                </div>
                            </div>
                        </td>
                        <td v-if="!edit_properties">@ DT5 = {{ (heatpump.min_flowrate/60)*5*4.150 | toFixed(1) }} kW</td>
                    </tr>
                    <tr>
                        <td>Max/nominal flow rate</th>
                        <td v-if="!edit_properties">{{ heatpump.max_flowrate || 'Not specified' }} <span v-if="heatpump.max_flowrate">L/min</span></td>
                        <td v-if="edit_properties">
                            <div class="input-group">
                                <input type="number" step="0.1" class="form-control" v-model="heatpump.max_flowrate" placeholder="e.g. 25.0">
                                <div class="input-group-append">
                                    <span class="input-group-text">L/min</span>
                                </div>
                            </div>
                        </td>
                        <td v-if="!edit_properties">@ DT5 = {{ (heatpump.max_flowrate/60)*5*4.150 | toFixed(1) }} kW</td>
                    </tr>
                    <tr>
                        <td>Max current</th>
                        <td v-if="!edit_properties">{{ heatpump.max_current || 'Not specified' }} <span v-if="heatpump.max_current">A</span></td>
                        <td v-if="edit_properties">
                            <div class="input-group">
                                <input type="number" step="0.1" class="form-control" v-model="heatpump.max_current" placeholder="e.g. 15.0">
                                <div class="input-group-append">
                                    <span class="input-group-text">A</span>
                                </div>
                            </div>
                        </td>
                        <td v-if="!edit_properties"></td>
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
            mode: "<?php echo $mode; ?>",
            userid: <?php echo $userid; ?>,
            loaded: false,
            id: "<?php echo $id; ?>",
            edit_properties: false,
            path: "<?php echo $path; ?>",
            heatpump: {},
            original_heatpump: {}, // Backup for cancel functionality
            // Min
            min_cap_tests: [],
            average_min_cap_test: 0,
            new_min_cap_test_url: null,

            // Max
            max_cap_tests: [],
            average_max_cap_test: 0,
            new_max_cap_test_url: null,
            
            // Review
            review_test: null,
            review_form: {
                status: 0,
                comment: ''
            }
        },
        created: function() {
            this.load_heatpump();
            this.load_cap_test_list("max");
            this.load_cap_test_list("min");
        },
        methods: {
            enable_edit: function() {
                this.original_heatpump = JSON.parse(JSON.stringify(this.heatpump)); // Deep copy for cancel
                this.edit_properties = true;
            },
            save_properties: function() {
                // Send update request
                $.post(this.path+'heatpump/update', {
                    id: this.heatpump.id,
                    manufacturer_id: this.heatpump.manufacturer_id,
                    model: this.heatpump.name,
                    refrigerant: this.heatpump.refrigerant,
                    type: this.heatpump.type,
                    capacity: this.heatpump.capacity,
                    min_flowrate: this.heatpump.min_flowrate,
                    max_flowrate: this.heatpump.max_flowrate,
                    max_current: this.heatpump.max_current
                })
                .done(response => {
                    if (response.success) {
                        this.edit_properties = false;
                        this.load_heatpump(); // Reload to get fresh data
                        alert('Heat pump properties updated successfully');
                    } else {
                        alert('Error: ' + response.message);
                    }
                })
                .fail(() => {
                    alert('Failed to update heat pump properties');
                });
            },
            cancel_edit: function() {
                this.heatpump = this.original_heatpump; // Restore original values
                this.edit_properties = false;
            },
            load_heatpump: function() {
                $.get(this.path+'heatpump/get?id='+this.id)
                    .done(response => {
                        this.heatpump = response;
                    });
            },
            load_cap_test_list: function(type) {
                let self = this;
                $.get(this.path+'heatpump/' + type + '_cap_test/list?id='+this.id)
                    .done(response => {
                        if (type === "max") {
                            self.max_cap_tests = response;
                        } else {
                            self.min_cap_tests = response;
                        }

                        // Calculate average heat output
                        const validTests = type === "max" ? self.max_cap_tests : self.min_cap_tests;
                        self['average_' + type + '_cap_test'] = validTests.filter(test => test.heat && test.review_status == 1).length > 0
                            ? (validTests.reduce((sum, test) => sum + parseFloat(test.heat), 0) / validTests.length).toFixed(0)
                            : 0;

                        self.loaded = true;
                    });
            },
            delete_cap_test: function(type, id) {
                if (confirm("Are you sure you want to delete this test?")) {
                    $.get(this.path+'heatpump/' + type + '_cap_test/delete?id='+id)
                        .done(response => {
                            this.load_cap_test_list(type);
                        });
                }
            },
            load_cap_test_data: function(type) {
                if (this['new_' + type + '_cap_test_url']) {
                    // send url in post request to server
                    $.post(this.path+'heatpump/' + type + '_cap_test/load?id='+this.id, {url: this['new_' + type + '_cap_test_url']})
                        .done(response => {
                            if (response.error) {
                                alert('Error: ' + response.error);
                            } else {
                                this.load_cap_test_list(type);
                                this['new_' + type + '_cap_test_url'] = null;
                            }
                        })
                        .fail((xhr, status, error) => {
                            alert('Failed to load test data: ' + error);
                        });
                }
            },
            open_review_modal: function(type, test) {
                test.type = type;
                this.review_test = test;
                this.review_form.status = test.review_status;
                this.review_form.comment = test.review_comment || '';
                $('#reviewModal').modal('show');
            },
            submit_review: function() {
                if (!this.review_test) return;

                let type = this.review_test.type;

                $.post(this.path+'heatpump/' + type + '_cap_test/update_status', {
                    id: this.review_test.id,
                    status: this.review_form.status,
                    message: this.review_form.comment
                })
                .done(response => {
                    if (response.success) {
                        $('#reviewModal').modal('hide');
                        this.load_cap_test_list(type);
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
            unapprovedMaxTestsCount: function() {
                return this.max_cap_tests.filter(test => test.review_status != 1).length;
            },
            unapprovedMinTestsCount: function() {
                return this.min_cap_tests.filter(test => test.review_status != 1).length;
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