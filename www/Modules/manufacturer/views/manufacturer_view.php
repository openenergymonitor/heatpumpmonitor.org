
<?php
defined('EMONCMS_EXEC') or die('Restricted access');
?>
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

<div id="app" class="bg-light">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container" style="max-width:1200px;">
            <h2>{{ mode }} manufacturer</h2>
        </div>
    </div>

    <div class="container" style="max-width:1200px;">

    <!-- add manufacturer -->
    <div class="form-group">
        <label for="add_manufacturer_name">Name</label>
        <input type="text" class="form-control" id="add_manufacturer_name" v-model="add_manufacturer_name">
    </div>
    <div class="form-group">
        <label for="add_manufacturer_website">Website</label>
        <input type="text" class="form-control" id="add_manufacturer_website" v-model="add_manufacturer_website">
    </div>
    <button class="btn btn-primary"
            v-on:click="add_manufacturer()">Add manufacturer</button>


    </div>

</div>

<script>

    var path = "<?php echo $path; ?>";

    var app = new Vue({
        el: '#app',
        data: {
            mode: 'Add',
            add_manufacturer_name: "",
            add_manufacturer_website: "",
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