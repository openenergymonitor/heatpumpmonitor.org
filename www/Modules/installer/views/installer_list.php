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
        </div>
    </div>

    <div class="container" style="max-width:1200px;">

        <table class="table mt-3">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>URL</th>
                    <th>Logo</th>
                    <th>Systems</th>
                    <th>Color</th>
                    <th v-if="admin">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="installer in sortedInstallers" :key="installer.id" v-if="installer.name">
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
                        <div class="badge" :style="{backgroundColor: installer.color, color: '#fff'}"></div>
                    </td>
                    <td v-if="admin">
                        <button class="btn btn-secondary btn-sm me-1" @click="openEditModal(installer)" title="Edit"><i class="fas fa-pencil-alt" style="color: #ffffff;"></i></button>
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
                        <div class="form-group">
                            <label for="color">Color</label>
                            <input type="color" class="form-control" id="color" v-model="form.color">
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
                color: '#000000'
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
                    color: installer.color || '#000000'
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
                    color: '#000000'
                };
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
            }
        },
        computed: {
            sortedInstallers() {
                return this.installers.slice().sort((a, b) => b.systems - a.systems);
            }
        },
        mounted() {
            this.loadInstallers();
        }
    });
</script>