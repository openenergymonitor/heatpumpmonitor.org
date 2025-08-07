<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>

<link rel="stylesheet" href="<?php echo $path; ?>Lib/autocomplete.css?v=4">
<script src="Lib/autocomplete.js?v=3"></script>

<style>
    .sticky {
        position: sticky;
        top: 20px;
    }
    .H4 {
        font-size:14px;
        color:green;
    }

    .H3 {
        font-size:14px;
        color:orange;
    }

    .H2 {
        font-size:14px;
        color:orange;
    }
    
    .H1 {
        font-size:14px;
        color:red;
    }
</style>

<div id="app" class="bg-light">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container" style="max-width:1000px;">

            <div class="row">
                <div class="col-12">
                    <button class="btn btn-primary" @click="openAddModal" v-if="mode=='admin'" style="float:right">Add heatpump</button>

                    <h3>Heatpump database</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Heatpump Modal -->
    <div v-if="showAddModal" class="modal" style="display: block; background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Heat Pump Model</h5>
                    <button type="button" class="btn-close" @click="closeAddModal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <a href="manufacturer" class="mb-2" style="float:right">+ Add Manufacturer</a>
                        <label class="form-label">Manufacturer *</label>
                        <select v-model="newHeatpump.manufacturer_id" class="form-select" required>
                            <option value="">Select manufacturer...</option>
                            <option v-for="manufacturer in manufacturers" :value="manufacturer.id">
                                {{ manufacturer.name }}
                            </option>
                        </select>
                    </div>
                    <div class="mb-3 autocomplete">
                        <label class="form-label">Model *</label>
                        <input id="newHeatpumpModel" v-model="newHeatpump.model" class="form-control" type="text" placeholder="Model name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Refrigerant</label>
                        <!-- R290','R32','CO2','R410A','R210A','R134A','R407C','R454C','R452B -->
                        <select v-model="newHeatpump.refrigerant" class="form-control">
                            <option value="">Select refrigerant...</option>
                            <option v-for="refrigerant in refrigerants" :value="refrigerant">
                                {{ refrigerant }}
                            </option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Badge Capacity (kW) *</label>
                        <input v-model="newHeatpump.capacity" class="form-control" type="number" step="0.1" placeholder="e.g. 5.0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="closeAddModal">Cancel</button>
                    <button type="button" class="btn btn-primary" @click="add_heatpump" 
                            :disabled="!newHeatpump.manufacturer_id || !newHeatpump.model.trim() || !newHeatpump.capacity">
                        Add Heat Pump
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container" style="max-width:1000px;">
        <div class="row">
            <div class="col">


                <table id="custom" class="table table-striped table-sm mt-3">
                    <tr>
                        <th>ID</th>
                        <th>Make</th>
                        <th>Model</th>
                        <th>Refrigerant</th>
                        <th>Capacity</th>
                        <th>Systems</th>
                        <th style="width:120px"></th>
                    </tr>
                    <tr v-for="unit in heatpumps">
                        <td>{{unit.id}}</td>
                        <td>
                            <select v-if="editingId === unit.id" v-model="editManufacturerId" class="form-select form-select-sm">
                                <option v-for="manufacturer in manufacturers" :value="manufacturer.id">
                                    {{ manufacturer.name }}
                                </option>
                            </select>
                            <span v-else>{{unit.manufacturer_name}}</span>
                        </td>
                        <td>
                            <input v-if="editingId === unit.id" v-model="editModel" class="form-control form-control-sm" type="text">
                            <span v-else>{{unit.name}}</span>
                        </td>
                        <td>
                            <select v-if="editingId === unit.id" v-model="editRefrigerant" class="form-select form-select-sm">
                                <option v-for="refrigerant in refrigerants" :value="refrigerant">
                                    {{ refrigerant }}
                                </option>
                            </select>
                            <span v-else>{{unit.refrigerant}}</span>
                        </td>
                        <td>
                            <div v-if="editingId === unit.id" class="input-group input-group-sm">
                                <input v-model="editCapacity" class="form-control" type="number" step="0.1">
                                <span class="input-group-text">kW</span>
                            </div>
                            <span v-else>{{unit.capacity}} kW</span>
                        </td>
                        <td>{{unit.stats.number_of_systems}}</td>
                        <td>
                            <div v-if="editingId === unit.id">
                                <button class="btn btn-success btn-sm me-1" @click="save_heatpump(unit.id)"><i class="fas fa-check"></i></button>
                                <button class="btn btn-secondary btn-sm" @click="cancel_edit()"><i class="fas fa-times"></i></button>
                            </div>
                            <div v-else>
                                <a :href="'<?php echo $path;?>heatpump/view?id='+unit.id">
                                    <button class="btn btn-secondary btn-sm me-1" title="Details"><i class="fa fa-list-alt" style="color: #ffffff;"></i></button>
                                </a>
                                <button class="btn btn-warning btn-sm me-1" @click="edit_heatpump(unit.id)" title="Edit" v-if="mode=='admin'"><i class="fas fa-edit" style="color: #ffffff;"></i></button>
                                <button class="btn btn-danger btn-sm" @click="delete_heatpump(unit.id)" title="Delete" v-if="mode=='admin'"><i class="fas fa-trash"></i></button>
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
        mode: "<?php echo $mode; ?>",
        path : "<?php echo $path; ?>",
        heatpumps: [],
        manufacturers: [],
        showAddModal: false,
        newHeatpump: {
            manufacturer_id: "",
            model: "",
            refrigerant: "",
            capacity: ""
        },
        editingId: null,
        editManufacturerId: "",
        editModel: "",
        editRefrigerant: "",
        editCapacity: "",
        refrigerants: ["R290", "R32", "CO2", "R410A", "R210A", "R134A", "R407C", "R454C", "R452B"]
    },
    created: function() {
        this.load_heatpumps();
        this.load_manufacturers();
    },
    methods: {
        load_heatpumps: function() {
            $.get(this.path+'heatpump/list')
                .done(response => {
                    this.heatpumps = response;
                });
        },
        load_manufacturers: function() {
            $.get(this.path+'manufacturer/list')
                .done(response => {
                    // Order manufacturers by name alphabetically
                    response.sort((a, b) => a.name.localeCompare(b.name));
                    this.manufacturers = response;
                });
        },
        add_heatpump: function() {
            if (this.newHeatpump.refrigerant == "") {
                alert("Please select a refrigerant.");
                return;
            }

            if (!this.newHeatpump.manufacturer_id || !this.newHeatpump.model.trim() || !this.newHeatpump.refrigerant.trim() || !this.newHeatpump.capacity) return;
            
            $.post(this.path + 'heatpump/add', {
                manufacturer_id: this.newHeatpump.manufacturer_id,
                model: this.newHeatpump.model,
                refrigerant: this.newHeatpump.refrigerant,
                capacity: this.newHeatpump.capacity
            })
            .done(response => {
                this.load_heatpumps();
                this.closeAddModal();
            });
        },
        openAddModal: function() {
            this.showAddModal = true;
            this.newHeatpump = {
                manufacturer_id: "",
                model: "",
                refrigerant: "",
                capacity: ""
            };

            this.$nextTick(() => {
                let element = document.getElementById("newHeatpumpModel");
                let uniqueHeatpumpNames = [...new Set(this.heatpumps.map(h => h.name))];
                autocomplete(element, uniqueHeatpumpNames);
            });
        },

        closeAddModal: function() {
            this.showAddModal = false;
            this.newHeatpump = {
                manufacturer_id: "",
                model: "",
                capacity: ""
            };
        },
        edit_heatpump: function(id) {
            const heatpump = this.heatpumps.find(h => h.id === id);
            this.editingId = id;
            this.editManufacturerId = heatpump.manufacturer_id;
            this.editModel = heatpump.name;
            this.editRefrigerant = heatpump.refrigerant || ""; // Handle null refrigerant
            this.editCapacity = heatpump.capacity;
        },
        save_heatpump: function(id) {
            $.post(this.path + 'heatpump/update', {
                id: id,
                manufacturer_id: this.editManufacturerId,
                model: this.editModel,
                refrigerant: this.editRefrigerant,
                capacity: this.editCapacity
            })
            .done(response => {
                this.editingId = null;
                this.load_heatpumps();
            });
        },
        cancel_edit: function() {
            this.editingId = null;
            this.editManufacturerId = "";
            this.editModel = "";
            this.editRefrigerant = "";
            this.editCapacity = "";
        },
        delete_heatpump: function(id) {
            if (confirm('Are you sure you want to delete this heat pump model?')) {
                $.get(this.path + 'heatpump/delete?id=' + id)
                .done(response => {
                    this.load_heatpumps();
                });
            }
        }
    }
});

</script>
