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
                <th v-if="admin">User</th>
                <th>Location</th>
                <th>Last updated</th>
                <th v-for="field in custom_fields">{{ field }}</th>
                <th>Year COP</th>
                <th>Status</th>
                <th style="width:150px">Actions</th>
            </tr>
            <tr v-for="(system,index) in systems">
                <td v-if="admin" :title="system.username+'\n'+system.email">{{ system.name }}</td>
                <td>{{ system.location }}</td>
                <td>{{ system.last_updated | time_ago }}</td>
                <td v-for="field in custom_fields">{{ system[field] }}</td>
                <td>{{ system.year_cop | toFixed(1) }}</td>
                <td>
                    <span v-if="system.share" class="badge bg-success">Shared</span>
                    <span v-if="!system.share" class="badge bg-danger">Private</span>
                    <span v-if="system.published" class="badge bg-success">Published</span>
                    <span v-if="!system.published" class="badge bg-secondary">Waiting for review</span>
                </td>
                <td>
                    <button class="btn btn-warning btn-sm" @click="edit(index)" title="Edit"><i class="fa fa-edit" style="color: #ffffff;"></i></button>
                    <button class="btn btn-danger btn-sm" @click="remove(index)" title="Delete"><i class="fa fa-trash" style="color: #ffffff;"></i></button>
                </td>
            </tr>
        </table>


        <!-- add field -->
        <div class="input-group mb-3" style="max-width:300px">
            <select class="form-control" v-model="add_field_select">
                <option>SELECT FIELD</option>
                <option v-for="opt in field_options">{{ opt }}</option>
            </select>
            <button class="btn btn-primary" @click="add_field">Add</button>
        </div>

    </div>
</div>

<script>
    var app = new Vue({
        el: '#app',
        data: {
            systems: <?php echo json_encode($systems); ?>,
            admin: <?php echo ($admin) ? 'true' : 'false'; ?>,
            add_field_select: 'SELECT FIELD',
            field_options: [],
            custom_fields: []
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
            add_field: function() {
                if (this.add_field_select=='SELECT FIELD') return;
                // this.custom_fields = [];
                this.custom_fields.push(this.add_field_select);
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

    app.field_options = [];
    for (var key in app.systems[0]) {
        if (key!='id' && key!='userid' && key!='name' && key!='location' && key!='last_updated' && key!='year_cop' && key!='share' && key!='published' && key!='stats') {
            app.field_options.push(key);
        }
    }
</script>