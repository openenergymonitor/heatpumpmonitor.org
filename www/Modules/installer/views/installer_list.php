<?php global $path; ?>
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<style>
    #installer-app .badge {
        display: inline-block;
        width: 40px;
        height: 22px;
        border-radius: 5px;
        margin-right: 5px;
        margin-top:2px;
    }
</style>

<div id="installer-app">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container" style="max-width:1200px;">
            <button class="btn btn-primary" style="float:right" @click="openAddModal" v-if="admin">+ Add Installer</button>
            <h2>Installers</h2>
            
            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="input-group" style="max-width:350px">
                        <span class="input-group-text">Filter</span>
                        <input class="form-control" v-model="filterKey" placeholder="Search installers..." @input="filterInstallers">
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    {{ filteredInstallers.length }} installers
                </div>
            </div>
        </div>
    </div>

    <div class="container" style="max-width:1200px;">

        <table class="table mt-3">
            <thead class="table-light">
                <tr>
                    <th @click="sort('name', 'asc')" style="cursor:pointer">Name
                        <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='name'"></i>
                    </th>
                    <th @click="sort('url', 'asc')" style="cursor:pointer">URL
                        <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='url'"></i>
                    </th>
                    <th>Logo</th>
                    <th @click="sort('systems', 'desc')" style="cursor:pointer">Systems
                        <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='systems'"></i>
                    </th>
                    <th>Color</th>
                    <th v-if="admin">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="installer in filteredInstallers" :key="installer.id" v-if="installer.name">
                    <td>{{ installer.name }}</td>
                    <td>
                        <a v-if="installer.url" :href="installer.url" target="_blank">{{ installer.url }}</a>
                        <span v-else>-</span>
                    </td>
                    <td>
                        <a v-if="installer.logo" :href='installer.url'><img class='logo' :src="path+'theme/img/installers/'+installer.logo"/></a>
                        <span v-else>-</span>
                    </td>
                    <td>{{ installer.systems }}</td>
                    <td>
                        <div class="badge" :style="{backgroundColor: installer.color, color: '#fff'}" :title="installer.color"></div>
                    </td>
                    <td v-if="admin">
                        <button class="btn btn-secondary btn-sm me-1" @click="openEditModal(installer)" title="Edit"><i class="fas fa-pencil-alt" style="color: #ffffff;"></i></button>
                        <button class="btn btn-danger btn-sm" @click="deleteInstaller(installer)" title="Delete"><i class="fas fa-trash" style="color: #ffffff;"></i></button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Add/Edit Installer Modal -->
    <div class="modal fade" id="installerModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ isEdit ? 'Edit' : 'Add' }} Installer</h5>
                    <button type="button" class="close" @click="closeModal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="saveInstaller">
                        <div class="form-group">
                            <label for="name">Name</label>
                            <input type="text" class="form-control" id="name" v-model="form.name" required>
                        </div>
                        <div class="form-group">
                            <label for="url">URL</label>
                            <input type="url" class="form-control" id="url" v-model="form.url">
                        </div>

                        <div class="form-group mt-3">
                            <label for="color">Logo</label>
                            <div class="row">

                                <div class="col-4 text-center">
                                    <div style="border: 1px solid #ccc; padding: 5px; border-radius: 5px;">
                                        <img v-if="form.logo" :src="path+'theme/img/installers/'+form.logo" style="max-width: 32px; max-height: 32px;" alt="Logo preview">
                                        <span v-else class="text-muted">No logo</span>
                                    </div>  
                                </div>

                                <div class="col-8">
                                    <button type="button" class="btn btn-secondary btn-sm" @click="loadLogo" :disabled="!form.url || loadingLogo">
                                        <span v-if="loadingLogo">Loading...</span>
                                        <span v-else>Fetch Logo from URL</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mt-3">
                            <label for="color">Color</label>
                            <div class="row">
                                <div class="col-4">
                                    <input type="color" class="form-control" id="color" v-model="form.color" :title="form.color">
                                </div>
                                <div class="col-8">
                                    <button type="button" class="btn btn-secondary btn-sm" @click="getDominantColor" :disabled="!form.logo || loadingColor">
                                        <span v-if="loadingColor">Loading...</span>
                                        <span v-else>Get from Logo</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="closeModal">Cancel</button>
                    <button type="button" class="btn btn-primary" @click="saveInstaller">{{ isEdit ? 'Update' : 'Add' }}</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    new Vue({
        el: '#installer-app',
        data: {
            path: "<?php echo $path; ?>",
            userid: <?php echo $userid; ?>,
            admin: <?php echo $admin; ?>,
            installers: [],
            isEdit: false,
            form: {
                id: null,
                name: '',
                url: '',
                logo: '',
                color: '#cccccc'
            },
            loadingLogo: false,
            loadingColor: false,
            currentSortColumn: "systems",
            currentSortDir: "desc",
            filterKey: ""
        },
        computed: {
            sortedInstallers() {
                return this.installers.slice().sort((a, b) => {
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

                    if (aValue < bValue) return -1 * modifier;
                    if (aValue > bValue) return 1 * modifier;
                    return 0;
                });
            },
            filteredInstallers() {
                if (!this.filterKey.trim()) {
                    return this.sortedInstallers;
                }
                
                const filterTerms = this.filterKey
                    .split(/[\s,;]+/)
                    .map(term => term.trim().toLowerCase())
                    .filter(term => term.length > 0);
                
                if (filterTerms.length === 0) {
                    return this.sortedInstallers;
                }
                
                return this.sortedInstallers.filter(installer => {
                    return filterTerms.every(term => {
                        const searchableText = [
                            installer.name || '',
                            installer.url || ''
                        ].join(' ').toLowerCase();
                        
                        return searchableText.includes(term);
                    });
                });
            }
        },
        methods: {
            openAddModal() {
                this.isEdit = false;
                this.resetForm();
                $('#installerModal').modal('show');
            },
            openEditModal(installer) {
                this.isEdit = true;
                this.form = {
                    id: installer.id,
                    name: installer.name || '',
                    url: installer.url || '',
                    logo: installer.logo || '',
                    color: installer.color || '#cccccc'
                };
                $('#installerModal').modal('show');
            },
            closeModal() {
                $('#installerModal').modal('hide');
                this.resetForm();
            },
            resetForm() {
                this.form = {
                    id: null,
                    name: '',
                    url: '',
                    logo: '',
                    color: '#cccccc'
                };
            },
            loadLogo() {
                if (!this.form.url) return;
                
                this.loadingLogo = true;
                $.post(this.path + 'installer/load_logo.json', { url: this.form.url })
                    .done(response => {
                        if (response.success) {
                            this.form.logo = response.logo;
                        } else {
                            alert('Error loading logo: ' + (response.message || 'Failed to load logo'));
                        }
                    })
                    .fail(error => {
                        alert('Error loading logo: ' + error.statusText);
                    })
                    .always(() => {
                        this.loadingLogo = false;
                    });
            },
            getDominantColor() {
                if (!this.form.logo) return;
                
                this.loadingColor = true;
                $.post(this.path + 'installer/get_dominant_color.json', { logo: this.form.logo })
                    .done(response => {
                        if (response.success) {
                            this.form.color = response.color;
                        } else {
                            alert('Error getting dominant color: ' + (response.message || 'Failed to get color'));
                        }
                    })
                    .fail(error => {
                        alert('Error getting dominant color: ' + error.statusText);
                    })
                    .always(() => {
                        this.loadingColor = false;
                    });
            },
            deleteInstaller(installer) {
                if (confirm(`Are you sure you want to delete the installer "${installer.name}"?`)) {
                    $.post(this.path + 'installer/delete.json', { id: installer.id })
                        .done(response => {
                            if (response.success) {
                                this.loadInstallers();
                            } else {
                                alert('Error: ' + (response.message || 'Failed to delete installer'));
                            }
                        })
                        .fail(error => {
                            alert('Error: ' + error.statusText);
                        });
                }
            },
            saveInstaller() {
                const url = this.isEdit ? this.path + 'installer/edit.json' : this.path + 'installer/add.json';
                
                $.post(url, this.form)
                    .done(response => {
                        if (response.success) {
                            this.closeModal();
                            this.loadInstallers();
                        } else {
                            alert('Error: ' + (response.message || 'Failed to save installer'));
                        }
                    })
                    .fail(error => {
                        alert('Error: ' + error.statusText);
                    });
            },
            loadInstallers() {
                $.get(this.path + 'installer/list.json?system_count=1')
                    .done(res => {
                        this.installers = res;
                    });
            },
            sort(column, starting_order) {
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
            filterInstallers() {
                // Filtering is handled by the computed property
                // This method exists for the @input event binding
            },
        },
        mounted() {
            this.loadInstallers();
        }
    });
</script>