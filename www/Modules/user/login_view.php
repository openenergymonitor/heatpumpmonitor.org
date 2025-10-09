<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

global $settings;

?>

<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>

<div id="app" class="container" style="max-width:380px; padding-top:120px; height:800px;">

    <div class="card">
        <div class="card-body bg-light">

            <h1 class="h3 mb-3 fw-normal">Login <span v-if="emoncmsorg_only">with emoncms.org</span><span v-if="!emoncmsorg_only">(dev env)</span></h1>

            <label>Username</label>
            <div class="input-group mb-3">
                <input type="text" class="form-control" v-model="username">
            </div>

            <label>Password</label>
            <div class="input-group mb-3">
                <input type="password" class="form-control" v-model="password">
            </div>

            <button type="button" class="btn btn-primary" @click="login">Login</button>


            <div class="alert alert-danger" style="margin-top:20px; margin-bottom: 5px;" v-if="error" v-html="error"></div>
            <div class="alert alert-success" style="margin-top:20px; margin-bottom: 5px;" v-if="success" v-html="success"></div>

        </div>

    </div>
</div>

<script>

    document.body.style.backgroundColor = "#1d8dbc";

    var emoncmsorg_only = <?php echo $settings['emoncmsorg_only'] ? "true" : "false"; ?>;

    var app = new Vue({
        el: '#app',
        data: {
            username: "",
            password: "",
            password2: "",
            email: "",
            error: false,
            success: false,
            emoncmsorg_only: emoncmsorg_only
        },
        methods: {
            login: function() {
                const params = new URLSearchParams();
                params.append('username', this.username);
                params.append('password', this.password);

                axios.post(path + "user/login.json", params)
                    .then(function(response) {
                        if (response.data.success) {
                            app.error = false;
                            window.location.href = path + "system/list/user";
                        } else {
                            app.error = response.data.message;
                            app.success = false;
                        }
                    })
                    .catch(function(error) {
                        console.log(error);
                    });
            }
        }
    });

    var result = <?php echo json_encode($result); ?>;
    if (result.success!=undefined) {
        if (result.success) {
            app.success = result.message;
            app.error = false;
        } else {
            app.error = result.message;
            app.success = false;
        }
    }
</script>
