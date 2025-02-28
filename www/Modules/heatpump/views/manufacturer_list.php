
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

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
                    <th scope="col">Models</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="manufacturer in manufacturers">
                    <td>{{ manufacturer.id }}</td>
                    <td>{{ manufacturer.name }}</td>
                    <td>0 models</td>
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
            manufacturers: []
        },
        created: function() {
            this.get_manufacturers();
        },
        methods: {
            add_manufacturer: function() {
                axios.get('heatpump/manufacturer/add?name='+this.add_manufacturer_name+'&website='+this.add_manufacturer_website)
                    .then(response => {
                        this.get_manufacturers();
                    });
            },
            get_manufacturers: function() {
                axios.get('heatpump/manufacturer/get')
                    .then(response => {
                        this.manufacturers = response.data;
                    });
            },
            delete_manufacturer: function(id) {
                axios.get('heatpump/manufacturer/delete?id='+id)
                    .then(response => {
                        this.get_manufacturers();
                    });
            }
        }
    });
</script>