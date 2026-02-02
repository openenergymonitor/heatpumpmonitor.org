<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');
?>

<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>

<style>
    .search-container {
        max-width: 600px;
        margin: 0 auto;
    }
    .table th {
        cursor: pointer;
        user-select: none;
    }
    .table th:hover {
        background-color: #e9ecef;
    }
    .results-info {
        text-align: center;
        color: #6c757d;
        margin: 20px 0;
        font-size: 14px;
    }
</style>

<div id="app" class="bg-light">
    <div style="background-color:#f0f0f0; padding-top:20px; padding-bottom:20px">
        <div class="container-fluid">
            <h3 class="text-center mb-4">Admin Users</h3>
            <div class="search-container">
                <div class="input-group input-group-lg">
                    <input type="text" 
                           class="form-control" 
                           placeholder="Search by username, email or ID..." 
                           v-model="search" 
                           @keyup.enter="searchUsers">
                    <button class="btn btn-primary" 
                            type="button" 
                            @click="searchUsers"
                            :disabled="search.length < 2">
                        <i class="icon-search"></i> Search
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <p v-if="users.length>0" class="results-info">
            <strong>{{ users.length }}</strong> user{{ users.length !== 1 ? 's' : '' }} found
        </p>
        
        <div v-if="users.length > 0" class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th scope="col" @click="sort_list('id')">ID</th>
                        <th scope="col" @click="sort_list('username')">Username</th>
                        <th scope="col" @click="sort_list('email')">Email</th>
                        <th scope="col" @click="sort_list('lastactive')">Last Login</th>
                        <th scope="col" class="text-center" @click="sort_list('systems')">Systems</th>
                        <th scope="col" class="text-center" @click="sort_list('subaccounts')">Sub Accounts</th>
                        <th scope="col" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users" :key="user.id">
                        <td>{{ user.id }}</td>
                        <td><strong>{{ user.username }}</strong></td>       
                        <td>{{ user.email }}</td>
                        <td style="font-size:14px">{{ user.lastactive | formatTime }}</td>
                        <td class="text-center">{{ user.systems || 0 }}</td>
                        <td class="text-center">{{ user.subaccounts || 0 }}</td>
                        <td class="text-center">
                            <button class="btn btn-primary btn-sm" v-on:click="switch_user(user.id)">
                                Switch
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div v-else-if="searchPerformed && users.length === 0" class="text-center text-muted" style="padding: 60px 0;">
            <p style="font-size: 18px;">No users found matching "{{ search }}"</p>
        </div>
        
        <div v-else class="text-center text-muted" style="padding: 60px 0;">
            <p style="font-size: 18px;">Enter a search term to find users</p>
        </div>
    </div>
</div>

<script>
    var app = new Vue({
        el: '#app',
        data: {
            users: [],
            search: '',
            sort_by: 'systems',
            sort_order: 'desc',
            searchPerformed: false
        },
        mounted: function() {
            // No initial load - wait for user to search
        },
        methods: {
            searchUsers: function() {
                var self = this;
                
                // If search is less than 2 chars, clear results
                if (this.search.length < 2) {
                    this.users = [];
                    this.searchPerformed = false;
                    return;
                }
                
                axios.get('<?php echo $path; ?>user/admin/list.json', {
                    params: {
                        search: self.search
                    }
                })
                .then(function(response) {
                    self.users = response.data;
                    self.searchPerformed = true;
                })
                .catch(function(error) {
                    console.error('Error fetching users:', error);
                });
            },
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
                this.users.sort(function(a,b) {
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
                if (value && value != 0) {
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
                return "---";
            }
        }
    });

</script>