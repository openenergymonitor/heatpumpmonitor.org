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
                    <th scope="col">Created</th>
                    <th scope="col">Last login</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="user in users">
                    <td>{{ user.id }}</td>
                    <td>{{ user.username }}</td>       
                    <td>{{ user.name }}</td>
                    <td>{{ user.email }}</td>
                    <td>{{ user.created }}</td>
                    <td>{{ user.lastlogin }}</td>
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
            users: users
        },
        methods: {
            switch_user: function(userid) {
                window.location.href = "<?php echo $path; ?>user/switch?userid="+userid;
            }
        }
    });

</script>