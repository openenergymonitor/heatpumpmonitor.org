<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');
?>

<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>

<div id="app" class="bg-light">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container">
            <h3>Admin users</h3>
        </div>
    </div>
    <div class="container">
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
                    <th scope="col">Actions</th>
                    <th scope="col">Welcome</th>
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
                    <td>{{ user.created | formatTime }}</td>
                    <td>{{ user.last_login | formatTime }}</td>
                    <td>
                        <button class="btn btn-primary btn-sm" v-on:click="switch_user(user.id)">Switch</button>
                    </td>
                    <td>
                        <button class="btn btn-warning btn-sm" v-on:click="send_welcome_email(user.id,user.name)"><i class="fa fa-envelope" style="color: #ffffff;"></i></button>
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
                        } else {
                            alert("Error sending welcome email");
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
                    var hour = date.getHours();
                    var min = date.getMinutes();
                    var months = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
                    return day+" "+months[month]+" "+year+", "+hour+":"+min;
                }
                return "";
            }
        }
    });

</script>