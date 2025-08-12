<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>

<style>
    .sticky {
        position: sticky;
        top: 20px;
    }
    .unmatched-row {
        background-color: #fff3cd;
    }
    .count-badge {
        background-color: #28a745;
        color: white;
        border-radius: 12px;
        padding: 2px 8px;
        font-size: 12px;
        font-weight: bold;
    }
</style>

<div id="app" class="bg-light">
    <div style="background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container" style="max-width:1200px;">
            <div class="row">
                <div class="col-12">
                    <a href="<?php echo $path; ?>heatpump" class="btn btn-secondary" style="float:right; margin-left:10px;">Back to Heat Pumps</a>
                    
                    <h3>Unmatched Heat Pumps</h3>
                    <p class="text-muted">Heat pump models found in system data but not yet in the database</p>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="input-group" style="max-width:350px">
                                <span class="input-group-text">Filter</span>
                                <input class="form-control" v-model="filterKey" placeholder="Search manufacturers, models..." @input="filterUnmatched">
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <span class="badge bg-primary fs-6">{{ filteredUnmatched.length }} unmatched models</span>
                        </div>
                    </div>
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
                    <p class="mt-2">Loading unmatched heat pumps...</p>
                </div>

                <div v-else-if="unmatched.length === 0" class="alert alert-info">
                    <h5>No unmatched heat pumps found!</h5>
                    <p>All heat pump models in the system data have been matched to the database.</p>
                </div>

                <table v-else class="table table-striped table-sm mt-3">
                    <thead>
                        <tr>
                            <th @click="sort('manufacturer', 'asc')" style="cursor:pointer">Manufacturer
                                <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='manufacturer'"></i>
                            </th>
                            <th @click="sort('model', 'asc')" style="cursor:pointer">Model
                                <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='model'"></i>
                            </th>
                            <th @click="sort('refrigerant', 'asc')" style="cursor:pointer">Refrigerant
                                <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='refrigerant'"></i>
                            </th>
                            <th @click="sort('type', 'asc')" style="cursor:pointer">Type
                                <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='type'"></i>
                            </th>
                            <th @click="sort('capacity', 'desc')" style="cursor:pointer">Capacity
                                <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='capacity'"></i>
                            </th>
                            <th @click="sort('count', 'desc')" style="cursor:pointer">Count
                                <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='count'"></i>
                            </th>
                            <th>Systems</th>
                            <th v-if="mode=='admin'" style="width:120px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in filteredUnmatched" class="unmatched-row">
                            <td>{{ item.manufacturer }}</td>
                            <td>{{ item.model }}</td>
                            <td>{{ item.refrigerant }}</td>
                            <td>{{ item.type || 'Air Source' }}</td>
                            <td>{{ item.capacity }} kW</td>
                            <td>
                                <span class="count-badge">{{ item.count }}</span>
                            </td>
                            <td>
                                <div v-if="item.system_ids && item.system_ids.length > 0" class="d-flex flex-wrap gap-1">
                                    <a v-for="(systemId, index) in item.system_ids" 
                                       :key="systemId"
                                       :href="path + 'system/view?id=' + systemId" 
                                       class="btn btn-outline-primary btn-sm"
                                       :title="'View System ' + systemId"
                                       target="_blank">
                                        {{ systemId }}
                                    </a>
                                </div>
                                <span v-else class="text-muted">No systems</span>
                            </td>
                            <td v-if="mode=='admin'">
                                <button class="btn btn-primary btn-sm" @click="addToDatabase(item)" title="Add to Database">
                                    <i class="fas fa-plus"></i> Add
                                </button>
                            </td>
                        </tr>
                    </tbody>
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
        path: "<?php echo $path; ?>",
        unmatched: [],
        manufacturers: [],
        loading: true,
        currentSortColumn: "count",
        currentSortDir: "desc",
        filterKey: initialFilterKey
    },
    computed: {
        sortedUnmatched: function() {
            return this.unmatched.slice().sort((a, b) => {
                let modifier = 1;
                if (this.currentSortDir == 'desc') modifier = -1;

                let aValue = a[this.currentSortColumn];
                let bValue = b[this.currentSortColumn];

                // Handle null/undefined values
                if (aValue === null || aValue === undefined) aValue = this.currentSortDir == 'desc' ? -Infinity : Infinity;
                if (bValue === null || bValue === undefined) bValue = this.currentSortDir == 'desc' ? -Infinity : Infinity;

                // Convert to numbers if both values are numeric
                if (!isNaN(aValue) && !isNaN(bValue)) {
                    aValue = Number(aValue);
                    bValue = Number(bValue);
                }

                // String comparison
                if (typeof aValue === 'string' && typeof bValue === 'string') {
                    return aValue.localeCompare(bValue) * modifier;
                }

                if (aValue < bValue) return -1 * modifier;
                if (aValue > bValue) return 1 * modifier;
                return 0;
            });
        },
        filteredUnmatched: function() {
            if (!this.filterKey.trim()) {
                return this.sortedUnmatched;
            }
            
            const filterLower = this.filterKey.toLowerCase();
            return this.sortedUnmatched.filter(item => {
                return (
                    (item.manufacturer && item.manufacturer.toLowerCase().includes(filterLower)) ||
                    (item.model && item.model.toLowerCase().includes(filterLower)) ||
                    (item.refrigerant && item.refrigerant.toLowerCase().includes(filterLower)) ||
                    (item.type && item.type.toLowerCase().includes(filterLower)) ||
                    (item.capacity && item.capacity.toString().includes(filterLower))
                );
            });
        }
    },
    created: function() {
        this.loadUnmatched();
        this.loadManufacturers();
    },
    methods: {
        filterUnmatched: function() {
            // Filtering is handled by the computed property
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
        loadUnmatched: function() {
            this.loading = true;
            $.get(this.path + 'heatpump/unmatched_list')
                .done(response => {
                    this.unmatched = response;
                    this.loading = false;
                })
                .fail(() => {
                    this.loading = false;
                    alert('Error loading unmatched heat pumps');
                });
        },
        loadManufacturers: function() {
            $.get(this.path + 'manufacturer/list')
                .done(response => {
                    this.manufacturers = response;
                });
        },
        addToDatabase: function(item) {
            if (!confirm('Add "' + item.manufacturer + ' ' + item.model + '" to the database?')) {
                return;
            }

            // Find manufacturer ID
            const manufacturer = this.manufacturers.find(m => 
                m.name.toLowerCase() === item.manufacturer.toLowerCase()
            );
            
            if (!manufacturer) {
                alert("Manufacturer '" + item.manufacturer + "' not found. Please add the manufacturer first.");
                return;
            }

            $.post(this.path + 'heatpump/add', {
                manufacturer_id: manufacturer.id,
                model: item.model,
                refrigerant: item.refrigerant,
                type: item.type || "Air Source", // Use the type from data or default
                capacity: item.capacity
            })
            .done(response => {
                if (response.success) {
                    alert('Heat pump added successfully!');
                    this.loadUnmatched(); // Reload the list
                } else {
                    alert('Error: ' + response.message);
                }
            })
            .fail(() => {
                alert('Error adding heat pump to database');
            });
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
    }
});
</script>
