<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');
global $settings;
?>

<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

<div id="app" class="bg-light min-vh-100">
    <div class="border-bottom shadow-sm" style="background-color: #f0f0f0;">
        <div class="container py-4" style="max-width:1200px;">
            <div class="d-flex align-items-center">
                <a href="<?php echo $path; ?>user/account" class="btn btn-light me-3">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div>
                    <h2 class="mb-0">Sub Accounts</h2>
                    <p class="text-muted mb-0">Manage access for your sub accounts</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container py-4" style="max-width:1200px">
        
        <!-- Sub Accounts Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="bi bi-people me-2"></i>Sub Accounts
                    <span class="badge bg-primary rounded-pill ms-2">{{ sub_accounts.length }}</span>
                </h5>
            </div>
            <div class="card-body p-0" v-if="sub_accounts.length > 0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4"><i class="bi bi-hash me-2"></i>ID</th>
                                <th class="px-4"><i class="bi bi-person me-2"></i>Username</th>
                                <th class="px-4"><i class="bi bi-envelope me-2"></i>Email</th>
                                <th class="px-4"><i class="bi bi-shield-lock me-2"></i>User Access</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="sub_account in sub_accounts" :key="sub_account.username">
                                <td class="px-4 py-3">
                                    <i class="bi bi-hash text-muted me-2"></i>
                                    {{ sub_account.userid }}
                                </td>
                                <td class="px-4 py-3">
                                    <i class="bi bi-person-circle text-muted me-2"></i>
                                    {{ sub_account.username }}
                                </td>
                                <td class="px-4 py-3">
                                    <i class="bi bi-envelope text-muted me-2"></i>
                                    {{ sub_account.email }}
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="sub_account.access==0" class="badge bg-secondary text-light">Disabled</span>
                                    <span v-else-if="sub_account.access==1" class="badge bg-warning text-light">Read only</span>
                                    <span v-else-if="sub_account.access==2" class="badge bg-success text-light">Write access</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-body text-center py-5" v-else>
                <i class="bi bi-people fs-1 text-muted mb-3 d-block"></i>
                <h5 class="text-muted">No Sub Accounts</h5>
                <p class="text-muted mb-0">You don't have any sub accounts yet.</p>
            </div>
        </div>
        
    </div>
</div>

<script>
    var app = new Vue({
        el: '#app',
        data: {
            sub_accounts: []
        },
        methods: {
            get_sub_accounts: function() {
                axios.get("<?php echo $path ?>user/subaccounts.json")
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
        },
        mounted() {
            this.get_sub_accounts();
        }
    });
</script>
