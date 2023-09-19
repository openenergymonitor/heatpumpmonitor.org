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
                    <th scope="col">ID</th>
                    <th scope="col">Username</th>
                    <th scope="col">Name</th>   
                    <th scope="col">Email</th>
                    <th scope="col">Emoncms.org</th>
                    <th scope="col">Admin</th>
                    <th scope="col">Created</th>
                    <th scope="col">Last login</th>
                    <th scope="col">Welcome email</th>
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
            users: users
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