<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>

<div id="app" class="bg-light">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container" style="max-width:1200px;">
            <h3>My Systems</h3>

            <button class="btn btn-primary btn-sm" @click="create" style="float:right; margin-right:30px">Add new system</button>
            <p>Add, edit and view systems associated with your account.</p>
        </div>
    </div>

    <div class="container" style="max-width:1200px">

        
        <table class="table">
            <tr>
                <th>ID</th>
                <th>Location</th>
                <th>Year COP</th>
                <th style="width:150px">Actions</th>
            </tr>
            <tr v-for="(system,index) in systems">
                <td>{{ system.id }}</td>
                <td>{{ system.location }}</td>
                <td>{{ system.year_cop | toFixed(1) }}</td>
                <td>
                    <button class="btn btn-info btn-sm" @click="edit(index)">Edit</button>
                    <button class="btn btn-danger btn-sm" @click="remove(index)">Delete</button>
                </td>
            </tr>
        </table>

    </div>
</div>

<script>
    var app = new Vue({
        el: '#app',
        data: {
            systems: <?php echo json_encode($systems); ?>
        },
        methods: {
            create: function() {
                axios.get('create')
                    .then(response => {
                            if (response.data.success) {
                                window.location = "edit?id="+response.data.id;
                            } else {
                                alert("Error creating system: "+response.data.message);
                            }
                        })
                        .catch(error => {
                            alert("Error creating system: "+error);
                        });     
            },
            edit: function(index) {
                let systemid = this.systems[index].id;
                window.location = "edit?id="+systemid;
            },
            remove: function(index) {
                if (confirm("Are you sure you want to delete system: "+this.systems[index].location+"?")) {
                    // axios delete 
                    let systemid = this.systems[index].id;
                    axios.get('delete?id='+systemid)
                        .then(response => {
                            if (response.data.success) {
                                this.systems.splice(index,1);
                            } else {
                                alert("Error deleting system: "+response.data.message);
                            }
                        })
                        .catch(error => {
                            alert("Error deleting system: "+error);
                        });
                }
            }
        },
        filters: {
            toFixed: function(val, dp) {
                if (isNaN(val)) {
                    return val;
                } else {
                    return val.toFixed(dp)
                }
            }
        }
    });
</script>