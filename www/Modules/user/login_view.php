<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');
?>

<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>

<div id="app" class="container" style="max-width:380px; padding-top:120px; height:800px;">

    <div class="card">
        <div class="card-body bg-light">

            <div v-if="!mode">
                <button type="button" class="btn btn-primary btn-lg" style="width:100%" @click="mode='emoncmsorg'">Login with emoncms.org</button>
                <button type="button" class="btn btn-outline-primary btn-lg" style="width:100%; margin-top:10px" @click="mode='other'">Use another account</button>
                <button type="button" class="btn btn-outline-secondary btn-lg" style="width:100%; margin-top:10px" @click="mode='register'">Sign up</button>
            </div>

            <div v-else>
                <div v-if="mode!='register'">
                    <h1 class="h3 mb-3 fw-normal">Login <span v-if="mode=='emoncmsorg'">with emoncms.org</span></h1>
                </div>
                <div v-if="mode=='register'">
                    <h1 class="h3 mb-3 fw-normal">Register</h1>
                    <p>If you already have an emoncms.org account, you can login with that. Click cancel and select Login with emoncms.org.</p>
                </div>

                <label>Username</label>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" v-model="username">
                </div>

                <label>Password</label>
                <div class="input-group mb-3">
                    <input type="password" class="form-control" v-model="password">
                </div>

                <div v-if="mode=='register'">
                    <label>Repeat password</label>
                    <div class="input-group mb-3">
                        <input type="password" class="form-control" v-model="password2">
                    </div>

                    <label>Email</label>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" v-model="email">
                    </div>
                </div>

                <button type="button" class="btn btn-primary" @click="login" v-if="mode!='register'">Login</button>
                <button type="button" class="btn btn-primary" @click="register" v-if="mode=='register'">Register</button>
                <button type="button" class="btn btn-light" @click="mode=false">Cancel</button>
                <!--<a href="#" v-if="mode=='other'">Forgot password</a>-->
            </div>

            <div class="alert alert-danger" style="margin-top:20px; margin-bottom: 5px;" v-if="error" v-html="error"></div>
            <div class="alert alert-success" style="margin-top:20px; margin-bottom: 5px;" v-if="success" v-html="success"></div>

        </div>

    </div>
</div>

<script>
    document.body.style.backgroundColor = "#1d8dbc";

    var app = new Vue({
        el: '#app',
        data: {
            username: "",
            password: "",
            password2: "",
            email: "",
            error: false,
            success: false,
            mode: false
        },
        methods: {
            login: function() {
                const params = new URLSearchParams();
                params.append('username', this.username);
                params.append('password', this.password);
                params.append('emoncmsorg', this.mode == "emoncmsorg" ? 1 : 0);

                axios.post(path + "user/login.json", params)
                    .then(function(response) {
                        if (response.data.success) {
                            window.location.href = path + "system/list/user";
                        } else {
                            app.error = response.data.message;
                            app.success = false;
                        }
                    })
                    .catch(function(error) {
                        console.log(error);
                    });
            },
            register: function() {
                const params = new URLSearchParams();
                params.append('username', this.username);
                params.append('password', this.password);
                params.append('password2', this.password2);
                params.append('email', this.email);

                axios.post(path + "user/register.json", params)
                    .then(function(response) {
                        if (response.data.success) {
                            if (response.data.verifyemail!=undefined && response.data.verifyemail) {
                                app.mode = 'login';
                                app.success = "Registration successful, please check your email to verify your account";
                            } else {
                                window.location.href = path + "system/list/user"
                            }
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