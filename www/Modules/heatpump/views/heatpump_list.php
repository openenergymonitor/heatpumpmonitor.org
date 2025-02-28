<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>
<script src="https://cdn.plot.ly/plotly-2.16.1.min.js"></script>
<script src="Lib/clipboard.js"></script>
<script src="<?php echo $path; ?>Modules/system/system_list_chart.js?v=27"></script>

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
                <div class="col-12 col-sm-12 col-md-6 col-lg-6 col-xl-8">
                    <h3>Heatpumps</h3>
                    <p>Heat pump models</p>
                    <button class="btn btn-primary">Add heatpump</button>
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
                        <th>Capacity</th>
                        <th>Systems</th>
                        <th></th>

                    </tr>
                    <tr v-for="unit in heatpumps">
                        <td>{{unit.id}}</td>
                        <td>{{unit.manufacturer}}</td>
                        <td>{{unit.model}}</td>
                        <td>{{unit.capacity}} kW</td>
                        <td>{{unit.stats.number_of_systems}}</td>
                        <td>
                            <!--View button-->
                            <a :href="'<?php echo $path;?>heatpump/view?id='+unit.id">
                                <button class="btn btn-primary btn-sm" title="Details"><i class="fa fa-list-alt" style="color: #ffffff;"></i></button>
                            </a>
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
        path : "<?php echo $path; ?>",
        heatpumps: []
    },
    created: function() {
            this.load_heatpumps();
    },
    methods: {
        load_heatpumps: function() {
            axios.get(this.path+'heatpump/list')
                .then(response => {
                    this.heatpumps = response.data;
                });
        }
    }
});

</script>
