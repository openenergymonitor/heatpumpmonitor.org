<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>

<link rel="stylesheet" href="<?php echo $path; ?>Lib/autocomplete.css?v=4">
<script src="Lib/autocomplete.js?v=8"></script>

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

    .btn {
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-radius: 5px;
    }

    /* Mobile responsive adjustments */
    @media (max-width: 767.98px) {
        .type-column {
            display: none !important;
        }
        .table td, .table th {
            padding: 0.5rem 0.25rem;
        }
        .table-sm td, .table-sm th {
            padding: 0.25rem 0.125rem;
        }
    }

</style>

<div id="app" class="bg-light">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container" style="max-width:1200px;">

            <div class="row">
                <div class="col-12">
                    <a href="<?php echo $path; ?>heatpump/unmatched" class="btn btn-warning" v-if="mode=='admin'" style="float:right; margin-left:10px;"><i class="fas fa-exclamation-triangle"></i> Unmatched Heat Pumps</a>
                    <button class="btn btn-primary" @click="openAddModal" v-if="mode=='admin'" style="float:right"><i class="fas fa-plus"></i> Add heatpump</button>

                    <h3>Heatpump database</h3>
                    <p class="text-muted">Explore heat pump models by manufacturer and model name.</p>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="input-group" style="max-width:350px">
                                <span class="input-group-text">Filter</span>
                                <input class="form-control" v-model="filterKey" placeholder="Search manufacturers, models..." @input="filterHeatpumps">
                            </div>
                            <!-- Mobile: Show count below filter -->
                            <div class="d-md-none mt-2">
                                <span class="badge bg-primary fs-6">{{ filteredHeatpumps.length }} heat pump models</span>
                            </div>
                        </div>
                        <!-- Desktop: Show count on right -->
                        <div class="col-md-6 text-end d-none d-md-block">
                            <span class="badge bg-primary fs-6">{{ filteredHeatpumps.length }} heat pump models</span>
                        </div>
                    </div>
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
                    <div class="mb-3 autocomplete">
                        <a href="manufacturer" class="mb-2" style="float:right">+ Add Manufacturer</a>
                        <label class="form-label">Manufacturer *</label>
                        <!--
                        <select v-model="newHeatpump.manufacturer_id" class="form-select" required>
                            <option value="">Select manufacturer...</option>
                            <option v-for="manufacturer in manufacturers" :value="manufacturer.id">
                                {{ manufacturer.name }}
                            </option>
                        </select>
                        -->
                        <input id="newHeatpumpManufacturer" v-model="newHeatpump.manufacturer_name" class="form-control" type="text" placeholder="Manufacturer name" required>
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
                        <label class="form-label">Type</label>
                        <select v-model="newHeatpump.type" class="form-select">
                            <option>Air Source</option>
                            <option>Ground Source</option>
                            <option>Water Source</option>
                            <option>Air-to-Air</option>
                            <option>Other</option>
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
                            :disabled="!newHeatpump.manufacturer_name || !newHeatpump.model.trim() || !newHeatpump.capacity">
                        Add Heat Pump
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container" style="max-width:1200px;">
        <div class="row">
            <div class="col">

                <div v-if="loading" class="text-center p-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading heat pump models...</p>
                </div>

                <table v-else id="custom" class="table table-striped table-sm mt-3">
                    <tr>
                        <!--
                        <th @click="sort('id', 'asc')" style="cursor:pointer">ID
                            <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='id'"></i>
                        </th>
                        -->
                        <th @click="sort('manufacturer_name', 'asc')" style="cursor:pointer">Make
                            <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='manufacturer_name'"></i>
                        </th>
                        <th @click="sort('name', 'asc')" style="cursor:pointer">Model
                            <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='name'"></i>
                        </th>
                        <th @click="sort('refrigerant', 'asc')" style="cursor:pointer">
                            <span class="d-none d-md-inline">Refrigerant</span>
                            <span class="d-md-none">Ref</span>
                            <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='refrigerant'"></i>
                        </th>
                        <th @click="sort('type', 'asc')" style="cursor:pointer" class="type-column d-none d-md-table-cell">Type
                            <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='type'"></i>
                        </th>
                        <th @click="sort('capacity', 'desc')" style="cursor:pointer">
                            <span class="d-none d-md-inline">Capacity</span>
                            <span class="d-md-none">Cap</span>
                            <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='capacity'"></i>
                        </th>
                        <th @click="sort('number_of_systems', 'desc')" style="cursor:pointer">
                            <span class="d-none d-md-inline">Systems</span>
                            <span class="d-md-none">Sys</span>
                            <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='number_of_systems'"></i>
                        </th>
                        <th @click="sort('min_output', 'desc')" style="cursor:pointer">Min
                            <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='min_output'"></i>
                        </th>
                        <th @click="sort('max_output', 'desc')" style="cursor:pointer">Max
                            <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='max_output'"></i>
                        </th>
                        <th style="width:120px"></th>
                    </tr>
                    <tr v-for="unit in filteredHeatpumps">
                        <!--<td>{{unit.id}}</td>-->
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
                        <td class="type-column d-none d-md-table-cell">
                            <select v-if="editingId === unit.id" v-model="editType" class="form-select form-select-sm">
                                <option>Air Source</option>
                                <option>Ground Source</option>
                                <option>Water Source</option>
                                <option>Air-to-Air</option>
                                <option>Other</option>
                            </select>
                            <span v-else>{{unit.type}}</span>
                        </td>
                        <td>
                            <div v-if="editingId === unit.id" class="input-group input-group-sm">
                                <input v-model="editCapacity" class="form-control" type="number" step="0.1">
                                <span class="input-group-text">kW</span>
                            </div>
                            <span v-else>{{unit.capacity}}<span class="text-muted" style="font-size: 0.85em;">
                                    <span class="d-none d-md-inline">&nbsp;</span>kW
                                </span>
                            </span>
                        </td>
                        <td>{{unit.stats.number_of_systems}}</td>
                        <td>
                            <span v-if="unit.tests.min_output">
                                {{ unit.tests.min_output*0.001 | toFixed(1) }} kW ({{unit.tests.min_count}})
                            </span>
                        </td>
                        <td>
                            <span v-if="unit.tests.max_output">
                                {{ unit.tests.max_output*0.001 | toFixed(1) }} kW ({{unit.tests.max_count}})
                            </span>
                        </td>
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

// Get URL parameters
var urlParams = new URLSearchParams(window.location.search);
var initialFilterKey = urlParams.get('filter') || '';

var app = new Vue({
    el: '#app',
    data: {
        mode: "<?php echo $mode; ?>",
        path : "<?php echo $path; ?>",
        heatpumps: [],
        manufacturers: [],
        loading: true,
        showAddModal: false,
        newHeatpump: {
            manufacturer_id: "",
            manufacturer_name: "",
            model: "",
            refrigerant: "",
            type: "Air Source", // Default source
            capacity: ""
        },
        editingId: null,
        editManufacturerId: "",
        editModel: "",
        editRefrigerant: "",
        editType: "",
        editCapacity: "",
        refrigerants: ["R290", "R32", "CO2", "R410A", "R210A", "R134A", "R407C", "R454C", "R452B"],
        currentSortColumn: "number_of_systems",
        currentSortDir: "desc",
        filterKey: initialFilterKey
    },
    computed: {
        sortedHeatpumps: function() {
            return this.heatpumps.slice().sort((a, b) => {
                let modifier = 1;
                if (this.currentSortDir == 'desc') modifier = -1;

                let aValue = a[this.currentSortColumn];
                let bValue = b[this.currentSortColumn];

                // Handle nested properties for systems count
                if (this.currentSortColumn == 'number_of_systems') {
                    aValue = a.stats ? a.stats.number_of_systems : 0;
                    bValue = b.stats ? b.stats.number_of_systems : 0;
                }

                // Handle nested properties for test counts
                if (this.currentSortColumn == 'max_count') {
                    aValue = a.tests ? a.tests.max_count : 0;
                    bValue = b.tests ? b.tests.max_count : 0;
                }

                if (this.currentSortColumn == 'max_output') {
                    aValue = a.tests ? a.tests.max_output : 0;
                    bValue = b.tests ? b.tests.max_output : 0;
                }

                if (this.currentSortColumn == 'min_count') {
                    aValue = a.tests ? a.tests.min_count : 0;
                    bValue = b.tests ? b.tests.min_count : 0;
                }

                if (this.currentSortColumn == 'min_output') {
                    aValue = a.tests ? a.tests.min_output : 0;
                    bValue = b.tests ? b.tests.min_output : 0;
                }

                // Handle null/undefined values
                if (aValue === null || aValue === undefined) aValue = this.currentSortDir == 'desc' ? -Infinity : Infinity;
                if (bValue === null || bValue === undefined) bValue = this.currentSortDir == 'desc' ? -Infinity : Infinity;

                // Convert to numbers if both values are numeric
                if (!isNaN(aValue) && !isNaN(bValue)) {
                    aValue = Number(aValue);
                    bValue = Number(bValue);
                }

                if (aValue < bValue) return -1 * modifier;
                if (aValue > bValue) return 1 * modifier;
                return 0;
            });
        },
        filteredHeatpumps: function() {
            if (!this.filterKey.trim()) {
                return this.sortedHeatpumps;
            }
            
            // Split filter terms by space, comma, or semicolon
            const filterTerms = this.filterKey
                .split(/[\s,;]+/)
                .map(term => term.trim().toLowerCase())
                .filter(term => term.length > 0);
            
            if (filterTerms.length === 0) {
                return this.sortedHeatpumps;
            }
            
            return this.sortedHeatpumps.filter(unit => {
                return filterTerms.every(term => {
                    // Check if this is a capacity search (ends with "kw")
                    if (term.endsWith('kw')) {
                        let capacityValue = parseFloat(term.replace('kw', ''));
                        if (!isNaN(capacityValue) && unit.capacity) {
                            // Allow for small tolerance in capacity matching (±0.1 kW)
                            return Math.abs(parseFloat(unit.capacity) - capacityValue) <= 0.1;
                        }
                        return false;
                    }
                    
                    // Regular text search for non-capacity terms
                    const searchableText = [
                        unit.manufacturer_name || '',
                        unit.name || '',
                        unit.refrigerant || '',
                        unit.type || '',
                        unit.capacity || ''
                    ].join(' ').toLowerCase();
                    
                    return searchableText.includes(term);
                });
            });
        }
    },
    created: function() {
        this.load_heatpumps();
        this.load_manufacturers();
    },
    methods: {
        filterHeatpumps: function() {
            // Filtering is handled by the computed property
            // This method exists for the @input event binding
            this.updateUrl();
        },
        sort: function(column, starting_order) {
            if (this.currentSortColumn != column) {
                this.currentSortDir = starting_order;
                this.currentSortColumn = column;
            } else {
                if (this.currentSortDir == 'desc') {
                    this.currentSortDir = 'asc';
                } else {
                    this.currentSortDir = 'desc';
                }
            }
        },
        load_heatpumps: function() {
            this.loading = true;
            $.get(this.path+'heatpump/list')
                .done(response => {
                    this.heatpumps = response;
                    this.loading = false;
                })
                .fail(() => {
                    this.loading = false;
                    alert('Error loading heat pump models');
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

            // Fetch manufacturer ID based on name
            const manufacturer = this.manufacturers.find(m => m.name.toLowerCase() === this.newHeatpump.manufacturer_name.toLowerCase());
            
            if (!manufacturer) {
                alert("Manufacturer '" + this.newHeatpump.manufacturer_name + "' not found. Please select a valid manufacturer or add it first.");
                return;
            }
            
            this.newHeatpump.manufacturer_id = manufacturer.id;

            if (!this.newHeatpump.manufacturer_id || !this.newHeatpump.model.trim() || !this.newHeatpump.refrigerant.trim() || !this.newHeatpump.capacity) return;
            
            $.post(this.path + 'heatpump/add', {
                manufacturer_id: this.newHeatpump.manufacturer_id,
                model: this.newHeatpump.model,
                refrigerant: this.newHeatpump.refrigerant,
                type: this.newHeatpump.type,
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
                manufacturer_name: "",
                model: "",
                refrigerant: "",
                type: "Air Source", // Default source
                capacity: ""
            };

            this.$nextTick(() => {

                autocomplete(document.getElementById("newHeatpumpManufacturer"), this.manufacturers.map(m => m.name));

                let uniqueHeatpumpNames = [...new Set(this.heatpumps.map(h => h.name))];
                autocomplete(document.getElementById("newHeatpumpModel"), uniqueHeatpumpNames);
            });
        },

        closeAddModal: function() {
            this.showAddModal = false;
            this.newHeatpump = {
                manufacturer_id: "",
                manufacturer_name: "",
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
            this.editType = heatpump.type || "Air Source"; // Default to Air if null
            this.editCapacity = heatpump.capacity;
        },
        save_heatpump: function(id) {
            $.post(this.path + 'heatpump/update', {
                id: id,
                manufacturer_id: this.editManufacturerId,
                model: this.editModel,
                refrigerant: this.editRefrigerant,
                type: this.editType,
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
            this.editType = "Air Source"; // Reset to default source
            this.editCapacity = "";
        },
        delete_heatpump: function(id) {
            if (confirm('Are you sure you want to delete this heat pump model?')) {
                $.get(this.path + 'heatpump/delete?id=' + id)
                .done(response => {
                    this.load_heatpumps();
                });
            }
        },
        updateUrl: function() {
            if (this.filterKey) {
                const url = new URL(window.location);
                url.searchParams.set('filter', this.filterKey);
                window.history.pushState({}, '', url);
            } else {
                const url = new URL(window.location);
                url.searchParams.delete('filter');
                window.history.pushState({}, '', url);
            }
        }
    },
    filters: {
        toFixed: function(value, decimals) {
            if (!isNaN(value)) {
                return parseFloat(value).toFixed(decimals);
            }
            return value;
        }
    }
});

</script>
