<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>

<div id="app" class="bg-light">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container" style="max-width:1200px;">
            <button class="btn btn-primary" style="float:right" v-click="add_manufacturer">+ Add manufacturer</button>
            <h2>Manufacturers</h2>
        </div>
    </div>

    <div class="container" style="max-width:1200px;">

        <table class="table table-striped mt-3">
            <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Name</th>
                    <th scope="col">Website</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="manufacturer in manufacturers">
                    <td>{{ manufacturer.id }}</td>
                    <td>
                        <input v-if="editingId === manufacturer.id" v-model="editName" class="form-control" type="text">
                        <span v-else>{{ manufacturer.name }}</span>
                    </td>
                    <td>
                        <input v-if="editingId === manufacturer.id" v-model="editWebsite" class="form-control"
                            type="text" placeholder="Website URL" @change="formatWebsite">
                        <a v-else-if="manufacturer.website" :href="'https://'+manufacturer.website" target="_blank">{{
                            manufacturer.website }}</a>
                        <span v-else>-</span>
                    </td>
                    <td>
                        <div v-if="editingId === manufacturer.id">
                            <button class="btn btn-success btn-sm me-1" @click="save_manufacturer(manufacturer.id)"><i
                                    class="fas fa-check"></i></button>
                            <button class="btn btn-secondary btn-sm" @click="cancel_edit()"><i
                                    class="fas fa-times"></i></button>
                        </div>
                        <div v-else>
                            <button class="btn btn-primary btn-sm me-1" @click="edit_manufacturer(manufacturer.id)"><i
                                    class="fas fa-edit"></i></button>
                            <button class="btn btn-danger btn-sm" @click="delete_manufacturer(manufacturer.id)"><i
                                    class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <br>
    </div>

</div>

<script>

    var path = "<?php echo $path; ?>";

    var app = new Vue({
        el: '#app',
        data: {
            add_manufacturer_name: "",
            add_manufacturer_website: "",
            manufacturers: [],
            editingId: null,
            editName: "",
            editWebsite: ""
        },
        created: function () {
            this.get_manufacturers();
        },
        methods: {
            add_manufacturer: function () {
                $.get(path + 'manufacturer/add?name=' + this.add_manufacturer_name + '&website=' + this.add_manufacturer_website)
                    .done(response => {
                        this.get_manufacturers();
                    });
            },
            get_manufacturers: function () {
                $.get(path + 'manufacturer/list')
                    .done(response => {
                        this.manufacturers = response;
                    });
            },
            delete_manufacturer: function (id) {
                $.get(path + 'manufacturer/delete?id=' + id)
                    .done(response => {
                        this.get_manufacturers();
                    });
            },
            edit_manufacturer: function (id) {
                const manufacturer = this.manufacturers.find(m => m.id === id);
                this.editingId = id;
                this.editName = manufacturer.name;
                this.editWebsite = manufacturer.website || "";
            },
            save_manufacturer: function (id) {
                $.post(path + 'manufacturer/update', {
                    id: id,
                    name: this.editName,
                    website: this.editWebsite
                })
                .done(response => {
                    this.editingId = null;
                    this.get_manufacturers();
                });
            },
            cancel_edit: function () {
                this.editingId = null;
                this.editName = "";
                this.editWebsite = "";
            },
            formatWebsite: function () {
                // Remove http:// or https:// from the beginning of the URL
                // Remove www. if present
                this.editWebsite = this.editWebsite.replace(/^(https?:\/\/)?(www\.)?/, '');
                // Remove trailing slashes
                this.editWebsite = this.editWebsite.replace(/\/+$/, '');
            }
        }
    });
</script>