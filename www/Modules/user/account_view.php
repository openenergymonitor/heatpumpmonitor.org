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

        <label><b>Username</b></label>
        <div class="input-group mb-3">
            <input type="text" class="form-control" v-model="account.username" @change="onchange_username" :disabled="account.emoncmsorg_link">
            <button class="btn btn-warning" v-if="username_changed">Save</button>
        </div>

        <label><b>Email</b></label>
        <div class="input-group mb-3">
            <input type="text" class="form-control" v-model="account.email" @change="onchange_email" :disabled="account.emoncmsorg_link">
            <button class="btn btn-warning" v-if="email_changed">Verify</button>
        </div>
        <hr>
        <!--
        <button class="btn btn-primary" v-if="!account.emoncmsorg_link">Change password</button>
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
            sub_accounts: []
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