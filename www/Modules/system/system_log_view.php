<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>

<div id="app">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container-fluid">
            <h3>Change log</h3>
            <p>Lists last 1000 changes to system meta data</p>
        </div>
    </div>
    <div class="container-fluid">
        <table class="table">
          <thead>
            <tr>
              <th scope="col" style="width:200px">Date & Time</th>
              <th scope="col" style="width:250px">User</th>
              <th scope="col">System</th>
              <th scope="col">Field</th>
              <th scope="col">Old value</th>
              <th scope="col">New value</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row,index) in log">
              <td><span v-if="index==0 || row.datetime != log[index-1].datetime">{{ row.datetime }}</span></td>
              <td><span v-if="index==0 || row.username != log[index-1].username">{{ row.username }} <span v-if="row.admin==1">(Admin)</span></span></td>
              <td><span v-if="index==0 || row.systemid != log[index-1].systemid">{{ row.systemid }}</span></td>
              <td><b>{{ row.field }}</b></td>
              <td><div style="max-width:450px; overflow:scroll">{{ row.old_value }}</div></td>
              <td><div style="max-width:450px; overflow:scroll">{{ row.new_value }}</div></td>
            </tr>
        </table>
    </div>
</div>

<script>

var system_id = "<?php echo $system_id; ?>";

var app = new Vue({
    el: '#app',
    data: {
        log: []
    }
});

var params = {};
if (system_id) {
    params.id = system_id;
}

// Load load ajax
$.ajax({
      url: path+"system/log.json",
      data: params,
      type: "GET",
      success: function(data) {
          app.log = data;

      }
});

</script>
