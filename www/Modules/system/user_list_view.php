<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>

<div id="app" class="bg-light">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container" style="max-width:1200px;">
            <h3 v-if="!admin">My Systems</h3>
            <h3 v-if="admin">Admin Systems</h3>

            <button class="btn btn-primary btn-sm" @click="create" style="float:right; margin-right:30px">Add new system</button>
            <p v-if="!admin">Add, edit and view systems associated with your account.</p>
            <p v-if="admin">Add, edit and view all systems.</p>
        </div>
    </div>

    <div class="container" style="max-width:1200px">

        
        <table class="table">
            <tr>
                <th>System ID</th>
                <th v-if="admin">User ID</th>
                <th>Location</th>
                <th>Last updated</th>
                <th>Year COP</th>
                <th>Public</th>
                <th style="width:150px">Actions</th>
            </tr>
            <tr v-for="(system,index) in systems">
                <td>{{ system.id }}</td>
                <td v-if="admin">{{ system.userid }}</td>
                <td>{{ system.location }}</td>
                <td>{{ system.last_updated | time_ago }}</td>
                <td>{{ system.year_cop | toFixed(1) }}</td>
                <td>
                    <input v-if="admin" type="checkbox" v-model="system.public" @click="public(index)">
                    <!-- badge -->
                    <span v-if="!admin && system.public" class="badge bg-success">Public</span>
                    <span v-if="!admin && !system.public" class="badge bg-secondary">Waiting for review</span>
                </td>
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
            systems: <?php echo json_encode($systems); ?>,
            admin: <?php echo ($admin) ? 'true' : 'false'; ?>
        },
        methods: {
            create: function() {
                window.location = "new";
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
            },
            public: function(index) {
                let systemid = this.systems[index].id;
                let public = !this.systems[index].public*1;
                // axios get
                axios.get('public?id='+systemid+"&public="+public)
                    .then(response => {
                        if (!response.data.success) {
                            alert("Error updating public: "+response.data.message);
                        }
                    })
                    .catch(error => {
                        alert("Error updating public: "+error);
                    });
            }
        },
        filters: {
            toFixed: function(val, dp) {
                if (isNaN(val) || val==null) {
                    return val;
                } else {
                    return val.toFixed(dp)
                }
            },
            time_ago: function(val) {
                if (val==null || val==0) {
                    return '';
                }
                // convert timestamp to date time
                let date = new Date(val*1000);
                // format date time
                let year = date.getFullYear();
                let month = date.getMonth()+1;
                let day = date.getDate();
                let hour = date.getHours();
                let min = date.getMinutes();
                let sec = date.getSeconds();
                // add leading zeros
                month = (month < 10) ? "0"+month : month;
                day = (day < 10) ? "0"+day : day;
                hour = (hour < 10) ? "0"+hour : hour;
                min = (min < 10) ? "0"+min : min;
                sec = (sec < 10) ? "0"+sec : sec;
                // return formatted date time
                return year+"-"+month+"-"+day+" "+hour+":"+min+":"+sec;
            }
        }
    });
</script>