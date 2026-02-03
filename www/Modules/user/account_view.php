<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');
global $settings;
?>

<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>

<div id="app" class="bg-light">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container" style="max-width:800px;">
            <h3>Account settings</h3>
        </div>
    </div>
    <div class="container" style="max-width:800px">
        <br>

        <label><b>User ID</b></label>
        <div class="input-group mb-3">
            <input type="text" class="form-control" v-model="account.id" disabled>
        </div>

        <label><b>Username</b></label>
        <div class="input-group mb-3">
            <input type="text" class="form-control" v-model="account.username" @change="onchange_username" disabled>
            <button class="btn btn-warning" v-if="username_changed">Save</button>
        </div>

        <label><b>Email</b></label>
        <div class="input-group mb-3">
            <input type="text" class="form-control" v-model="account.email" @change="onchange_email" disabled>
            <button class="btn btn-warning" v-if="email_changed">Verify</button>
        </div>

        <label><b>Timezone</b></label>
        <div class="input-group mb-3">
            <input type="text" class="form-control" v-model="account.timezone" disabled>
        </div>


        <hr>
        
        <button class="btn btn-primary" @click="show_change_password = !show_change_password">Change password</button>
        
        <div v-if="show_change_password" class="card mt-3">
            <div class="card-header">Change password</div>
            <div class="card-body">
                <div class="mb-3">
                    <label><b>Old password</b></label>
                    <input type="password" class="form-control" v-model="pass.old">
                </div>
                <div class="mb-3">
                    <label><b>New password</b></label>
                    <input type="password" class="form-control" v-model="pass.new1">
                </div>
                <div class="mb-3">
                    <label><b>Confirm new password</b></label>
                    <input type="password" class="form-control" v-model="pass.new2">
                </div>
                <button class="btn btn-primary" @click="change_password">Save</button>
                <div v-if="password_change_message" class="alert mt-3" :class="password_change_success ? 'alert-success' : 'alert-danger'">
                    {{ password_change_message }}
                </div>
            </div>
        </div>
        
        <!--
        <button class="btn btn-danger" style="float:right">Delete account</button>-->
        <br>
        <br>

        <div class="card mb-3" v-if="sub_accounts.length>0">
            <div class="card-header">
                <b>Sub accounts</b>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Username</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="sub_account in sub_accounts">
                            <td>{{ sub_account.username }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
            
        
    </div>
</div>

<script>
    var account = <?php echo json_encode($account); ?>;

    var app = new Vue({
        el: '#app',
        data: {
            account: {...account},
            email_changed: false,
            username_changed: false,
            sub_accounts: [],
            show_change_password: false,
            pass: {
                old: '',
                new1: '',
                new2: ''
            },
            password_change_success: false,
            password_change_message: ''
        },
        methods: {
            onchange_username: function() {
                if (this.account.username!=account.username) {
                    this.username_changed = true;
                } else {
                    this.username_changed = false;
                }
            },
            onchange_email: function() {
                if (this.account.email!=account.email) {
                    this.email_changed = true;
                } else {
                    this.email_changed = false;
                }
            },
            change_password: function() {
                this.password_change_message = '';
                if (this.pass.new1 != this.pass.new2) {
                    this.password_change_success = false;
                    this.password_change_message = "New passwords do not match.";
                    return;
                }
                if (this.pass.new1.length < 6) {
                    this.password_change_success = false;
                    this.password_change_message = "Password must be at least 6 characters.";
                    return;
                }

                let formData = new FormData();
                formData.append('old', this.pass.old);
                formData.append('new', this.pass.new1);

                axios.post("<?php echo $path ?>user/changepassword", formData)
                .then(response => {
                    this.password_change_success = response.data.success;
                    this.password_change_message = response.data.message;
                    if (response.data.success) {
                        this.pass.old = '';
                        this.pass.new1 = '';
                        this.pass.new2 = '';
                        setTimeout(() => {
                            this.show_change_password = false;
                            this.password_change_message = '';
                        }, 1000);
                    }
                })
                .catch(error => {
                    this.password_change_success = false;
                    this.password_change_message = "An error occurred.";
                    console.log(error);
                });
            },
            get_sub_accounts: function() {
                axios.get("<?php echo $path ?>user/subaccounts")
                .then(response => {
                    if (response.data.success) {
                        this.sub_accounts = response.data.accounts;
                    } else {
                        console.log(response.data.message);
                    }
                })
                .catch(error => {
                    console.log(error);
                });
            }  
        }
    });

    app.get_sub_accounts();
</script>