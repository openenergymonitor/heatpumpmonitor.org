<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');
?>

<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>

<div id="app" class="bg-light">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container-fluid">
            <h3>Admin users</h3>
            <p>Number of users: {{ filteredUsers.length }} ({{ users.length }} total)</p>
        </div>
    </div>
    <div class="container-fluid">
        <br>
        <div class="row mb-3">
            <div class="col-md-6">
                <input type="text" class="form-control" placeholder="Search users..." v-model="search">
            </div>
        </div>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th scope="col" @click="sort_list('id')">ID</th>
                    <th scope="col" @click="sort_list('username')">Username</th>
                    <th scope="col" @click="sort_list('name')">Name</th>   
                    <th scope="col" @click="sort_list('email')">Email</th>
                    <th scope="col" @click="sort_list('last_login')">Last login</th>
                    <th scope="col" @click="sort_list('systems')">Systems</th>
                    <th scope="col" @click="sort_list('subaccounts')">Sub accounts</th>
                    <th scope="col" @click="sort_list('adminusername')">Admin user</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="user in filteredUsers">
                    <td>{{ user.id }}</td>
                    <td>{{ user.username }}</td>       
                    <td>{{ user.name }}</td>
                    <td>{{ user.email }}</td>
                    <td style="font-size:14px">{{ user.last_login | formatTime }}</td>
                    <td>{{ user.systems }}</td>
                    <td>{{ user.subaccounts }}</td>
                    <td>{{ user.adminusername }}</td>
                    <td>
                        <button class="btn btn-primary btn-sm" v-on:click="switch_user(user.id)">Switch</button>
                    </td>
                </tr>
            </tbody>
        </table>
        
    </div>
</div>

<script>
    var users = <?php echo json_encode($users); ?>;
    var app = new Vue({
        el: '#app',
        data: {
            users: users,
            search: '',
            sort_by: 'systems',
            sort_order: 'desc'
        },
        computed: {
            filteredUsers: function() {
                if (!this.search) {
                    return this.users;
                }
                
                var searchTerm = this.search.toLowerCase();
                return this.users.filter(function(user) {
                    return String(user.id).toLowerCase().includes(searchTerm) ||
                           String(user.username || '').toLowerCase().includes(searchTerm) ||
                           String(user.name || '').toLowerCase().includes(searchTerm) ||
                           String(user.email || '').toLowerCase().includes(searchTerm) ||
                           String(user.admin || '').toLowerCase().includes(searchTerm) ||
                           String(user.systems || '').toLowerCase().includes(searchTerm) ||
                           String(user.subaccounts || '').toLowerCase().includes(searchTerm);
                });
            }
        },
        methods: {
            switch_user: function(userid) {
                window.location.href = "<?php echo $path; ?>user/switch?userid="+userid;
            },
            sort_list: function(key) {
                if (this.sort_by==key) {
                    if (this.sort_order=='asc') {
                        this.sort_order = 'desc';
                    } else {
                        this.sort_order = 'asc';
                    }
                } else {
                    this.sort_by = key;
                    this.sort_order = 'asc';
                }
                this.filteredUsers.sort(function(a,b) {
                    if (app.sort_order=='asc') {
                        return a[app.sort_by] > b[app.sort_by];
                    } else {
                        return a[app.sort_by] < b[app.sort_by];
                    }
                });
            }
        },
        filters: {
            formatTime: function (value) {
                if (value!=0) {
                    // 12th Aug 20:00
                    var date = new Date(value*1000);
                    var day = date.getDate();
                    var month = date.getMonth();
                    var year = date.getFullYear();
                    year = year.toString().substr(-2);
                    
                    var hour = date.getHours();
                    if (hour<10) hour = "0"+hour;

                    var min = date.getMinutes();
                    if (min<10) min = "0"+min;

                    var months = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
                    return day+" "+months[month]+" "+year+", "+hour+":"+min;
                }
                return "";
            }
        }
    });

</script>