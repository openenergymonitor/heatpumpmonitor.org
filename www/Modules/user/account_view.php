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
        <div class="card" v-if="account.emoncmsorg_link" style="margin-bottom:10px">
            <div class="card-body">
                <b>This account is linked to an emoncms.org account</b><br>Please update account details on emoncms.org, logout and log back in here to update details.
            </div>
        </div>

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

        <button class="btn btn-primary" v-if="!account.emoncmsorg_link">Change password</button>
        <button class="btn btn-danger" style="float:right">Delete account</button>
        <br>
        <br>
        
    </div>
</div>

<script>
    var account = <?php echo json_encode($account); ?>;

    var app = new Vue({
        el: '#app',
        data: {
            account: {...account},
            email_changed: false,
            username_changed: false
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
            }       
        }
    });
</script>