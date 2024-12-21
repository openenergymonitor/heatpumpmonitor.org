<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

$enable_register = true;
$emoncmsorg_only = false;

?>

<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>

<div id="app" class="container" style="max-width:380px; padding-top:120px; height:800px;">

    <div class="card">
        <div class="card-body bg-light">

            <div v-if="!mode">
                <button type="button" class="btn btn-primary btn-lg" style="width:100%" @click="mode='emoncmsorg'">Login with emoncms.org</button>
                <button type="button" class="btn btn-outline-primary btn-lg" style="width:100%; margin-top:10px" @click="mode='selfhost'">Self hosted data</button>
            </div>

            <div v-else>
                <div v-if="mode!='register'">
                    <h1 class="h3 mb-3 fw-normal">Login <span v-if="mode=='emoncmsorg'">with emoncms.org</span><span style="color:#888" v-if="mode=='selfhost'">Self hosted data</span></h1>
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

                <?php if ($enable_register) { ?>
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
                <?php } ?>

                <button type="button" class="btn btn-primary" @click="login" v-if="mode!='register'">Login</button>
                <?php if ($enable_register) { ?><button type="button" class="btn btn-primary" @click="register" v-if="mode=='register'">Register</button><?php } ?>
                <?php if (!$emoncmsorg_only) { ?><button type="button" class="btn btn-light" @click="mode=false" v-if="public_mode_enabled">Cancel</button><?php } ?>
                <?php if ($enable_register) { ?><button type="button" class="btn btn-light" v-if="mode!='emoncmsorg' && mode!='register' && public_mode_enabled" @click="mode='register'">Register</button> <?php } ?>
                <!--<a href="#" v-if="mode=='selfhost'">Forgot password</a>-->
            </div>

            <div class="alert alert-danger" style="margin-top:20px; margin-bottom: 5px;" v-if="error" v-html="error"></div>
            <div class="alert alert-success" style="margin-top:20px; margin-bottom: 5px;" v-if="success" v-html="success"></div>

        </div>

    </div>
</div>

<script>

    document.body.style.backgroundColor = "#1d8dbc";

    var emoncmsorg_only = <?php echo $emoncmsorg_only ? "true" : "false"; ?>;

    var app = new Vue({
        el: '#app',
        data: {
            username: "",
            password: "",
            password2: "",
            email: "",
            error: false,
            success: false,
            mode: 'emoncmsorg',
            public_mode_enabled: public_mode_enabled
        },
        methods: {
            async login() {
                this.loading = true; // Start loading state
                const params = new URLSearchParams();
                params.append('username', this.username);
                params.append('password', this.password);
                params.append('emoncmsorg', this.mode == "emoncmsorg" ? 1 : 0);

                try {
                    const response = await axios.post(path + "user/login.json", params);
                    if (response.data.success) {
                        app.error = false;
                        window.location.href = path + "system/list/user";
                    } else {
                        app.error = response.data.message;
                        app.success = false;
                    }
                } catch (error) {
                    console.log(error);
                    app.error = "An error occurred. Please try again.";
                } finally {
                    this.loading = false; // End loading state
                }
            },
            async register() {
                this.loading = true; // Start loading state
                const params = new URLSearchParams();
                params.append('username', this.username);
                params.append('password', this.password);
                params.append('password2', this.password2);
                params.append('email', this.email);

                try {
                    const response = await axios.post(path + "user/register.json", params);
                    if (response.data.success) {
                        app.error = false;
                        if (response.data.verifyemail !== undefined && response.data.verifyemail) {
                            app.mode = 'login';
                            app.success = "Registration successful, please check your email to verify your account";
                        } else {
                            window.location.href = path + "system/list/user";
                        }
                    } else {
                        app.error = response.data.message;
                        app.success = false;
                    }
                } catch (error) {
                    console.log(error);
                    app.error = "An error occurred. Please try again.";
                } finally {
                    this.loading = false; // End loading state
                }
            }
        }

    });
    
    if (!public_mode_enabled) {
        app.mode = 'standard';
    }

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
