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
        </div>
    </div>
    <div class="container-fluid">
        <br>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th scope="col" @click="sort_list('id')">ID</th>
                    <th scope="col" @click="sort_list('username')">Username</th>
                    <th scope="col" @click="sort_list('name')">Name</th>   
                    <th scope="col" @click="sort_list('email')">Email</th>
                    <th scope="col" @click="sort_list('emoncmsorg_link')">Emoncms.org</th>
                    <th scope="col" @click="sort_list('admin')">Admin</th>
                    <th scope="col" @click="sort_list('created')">Created</th>
                    <th scope="col" @click="sort_list('last_login')">Last login</th>
                    <th scope="col" @click="sort_list('welcome_email_sent')">Welcome email</th>
                    <th scope="col" @click="sort_list('systems')">Systems</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="user in users">
                    <td>{{ user.id }}</td>
                    <td>{{ user.username }}</td>       
                    <td>{{ user.name }}</td>
                    <td>{{ user.email }}</td>
                    <td>{{ user.emoncmsorg_link }}</td>
                    <td>{{ user.admin }}</td>
                    <td style="font-size:14px">{{ user.created | formatTime }}</td>
                    <td style="font-size:14px">{{ user.last_login | formatTime }}</td>
                    <td style="font-size:14px">{{ user.welcome_email_sent | formatTime }}</td>
                    <td>{{ user.systems }}</td>
                    <td>
                        <button class="btn btn-primary btn-sm" v-on:click="switch_user(user.id)">Switch</button>

                        <button class="btn btn-warning btn-sm" v-on:click="send_welcome_email(user.id,user.name)"><i class="fa fa-envelope" style="color: #ffffff;"></i></button>

                        <button class="btn btn-danger btn-sm" v-on:click="delete_user(user.id)"><i class="fa fa-trash" style="color: #ffffff;"></i></button>
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
            sort_by: 'systems',
            sort_order: 'desc'
        },
        methods: {
            switch_user: function(userid) {
                window.location.href = "<?php echo $path; ?>user/switch?userid="+userid;
            },
            send_welcome_email: function(userid,name) {
                
                if (confirm("Send welcome email to "+name+"?")) {
                    axios.get("<?php echo $path; ?>user/welcome?userid="+userid)
                    .then(function (response) {
                        if (response.data.success) {
                            alert("Welcome email sent");
                            // reload page
                            window.location.href = "<?php echo $path; ?>user/admin";
                        } else {
                            alert("Error sending welcome email");
                        }
                    })
                    .catch(function (error) {
                        console.log(error);
                    });
                }

            },
            delete_user: function(userid) {
                if (confirm("Delete user?")) {
                    axios.get("<?php echo $path; ?>user/delete?userid="+userid)
                    .then(function (response) {
                        if (response.data.success) {
                            alert("User deleted");
                            // reload page
                            window.location.href = "<?php echo $path; ?>user/admin";
                        } else {
                            alert(response.data.message);
                        }
                    })
                    .catch(function (error) {
                        console.log(error);
                    });
                }
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