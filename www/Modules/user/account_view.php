<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');
global $settings;
?>

<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

<style>
    .password-section-enter-active, .password-section-leave-active {
        transition: all 0.3s ease;
    }
    .password-section-enter, .password-section-leave-to {
        opacity: 0;
        transform: translateY(-10px);
    }
</style>

<div id="app" class="bg-light min-vh-100">
    <div class="border-bottom shadow-sm" style="background-color: #f0f0f0;">
        <div class="container py-4" style="max-width:900px;">
            <div class="d-flex align-items-center">
                <i class="bi bi-person-circle fs-1 me-3" v-show="!gravatar_loaded"></i>
                <img width="48" height="48" class="rounded-circle me-3 border border-3 border-white" :src="gravatar_url" @load="gravatar_loaded = true" @error="gravatar_loaded = false" v-show="gravatar_loaded">
                <div>
                    <h2 class="mb-0">Account Settings</h2>
                    <p class="text-muted mb-0">Manage your profile and security settings</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container py-4" style="max-width:900px">
        
        <!-- Account Information Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-person-vcard me-2"></i>Account Information</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">User ID</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-hash"></i></span>
                            <input type="text" class="form-control" v-model="account.id" disabled>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Timezone</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-globe"></i></span>
                            <input type="text" class="form-control" v-model="account.timezone" disabled>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" v-model="account.username" @change="onchange_username" disabled>
                            <button class="btn btn-warning" v-if="username_changed">
                                <i class="bi bi-save me-1"></i>Save
                            </button>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" v-model="account.email" @change="onchange_email" disabled>
                            <button class="btn btn-warning" v-if="email_changed">
                                <i class="bi bi-check-circle me-1"></i>Verify
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Security</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">Password</h6>
                    </div>
                    <button class="btn btn-outline-primary" @click="show_change_password = !show_change_password">
                        <i class="bi me-1" :class="show_change_password ? 'bi-x-lg' : 'bi-key'"></i>
                        {{ show_change_password ? 'Cancel' : 'Change Password' }}
                    </button>
                </div>
                
                <transition name="password-section">
                    <div v-if="show_change_password" class="mt-4 pt-3 border-top">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Current Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" v-model="pass.old" placeholder="Enter your current password" @keyup.enter="change_password">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" class="form-control" v-model="pass.new1" placeholder="Minimum 6 characters" @keyup.enter="change_password">
                                </div>
                                <small class="text-muted">Must be at least 6 characters</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Confirm New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" class="form-control" v-model="pass.new2" placeholder="Re-enter new password" @keyup.enter="change_password">
                                </div>
                                <small class="text-muted">Passwords must match</small>
                            </div>
                            <div class="col-12">
                                <div class="d-flex gap-2">
                                    <button class="btn btn-success px-4" @click="change_password">
                                        <i class="bi bi-check-circle me-1"></i>Update Password
                                    </button>
                                </div>
                            </div>
                            <div v-if="password_change_message" class="col-12">
                                <div class="alert mb-0 d-flex align-items-center" :class="password_change_success ? 'alert-success' : 'alert-danger'" role="alert">
                                    <i class="bi fs-5 me-2" :class="password_change_success ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'"></i>
                                    <div>{{ password_change_message }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>
        </div>

        <!-- Sub Accounts Card -->
        <div class="card shadow-sm mb-4" v-if="sub_accounts.length>0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="bi bi-people me-2"></i>Sub Accounts
                    <span class="badge bg-primary rounded-pill ms-2">{{ sub_accounts.length }}</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4"><i class="bi bi-person me-2"></i>Username</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="sub_account in sub_accounts" :key="sub_account.username">
                                <td class="px-4 py-3">
                                    <i class="bi bi-person-circle text-muted me-2"></i>
                                    {{ sub_account.username }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    </div>
</div>

<script src="<?php echo $path; ?>Lib/md5.js"></script>

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
            password_change_message: '',
            gravatar_loaded: false
        },
        computed: {
            gravatar_url: function() {
                var email = this.account.email ? this.account.email.trim().toLowerCase() : '';
                var hash = CryptoJS.MD5(email).toString();
                return "https://www.gravatar.com/avatar/" + hash + "?s=48&d=404";
            }
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
                        }, 2000);
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