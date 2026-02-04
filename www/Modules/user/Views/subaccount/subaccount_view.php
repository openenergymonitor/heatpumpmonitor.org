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
        <div class="container-fluid py-4">
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
    
    <div class="container-fluid py-4">
        
        <!-- Sub Accounts Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="bi bi-people me-2"></i>Sub Accounts
                    <span class="badge bg-primary rounded-pill ms-2" v-if="!loading">{{ sub_accounts.length }}</span>
                </h5>
            </div>
            
            <!-- Loading State -->
            <div class="card-body text-center py-5" v-if="loading">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted mb-0">Loading sub accounts...</p>
            </div>
            
            <!-- Accounts Table -->
            <div class="card-body p-0" v-else-if="sub_accounts.length > 0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4 cursor-pointer user-select-none" @click="sortBy('id')">
                                    <i class="bi bi-hash me-2"></i>User ID
                                    <i v-if="sortColumn === 'id'" :class="sortDirection === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" class="ms-1"></i>
                                </th>
                                <th class="px-4 cursor-pointer user-select-none" @click="sortBy('username')">
                                    <i class="bi bi-person me-2"></i>Username
                                    <i v-if="sortColumn === 'username'" :class="sortDirection === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" class="ms-1"></i>
                                </th>
                                <th class="px-4 cursor-pointer user-select-none" @click="sortBy('email')">
                                    <i class="bi bi-envelope me-2"></i>Email
                                    <i v-if="sortColumn === 'email'" :class="sortDirection === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" class="ms-1"></i>
                                </th>
                                <th class="px-4 cursor-pointer user-select-none" @click="sortBy('system_location')">
                                    <i class="bi bi-geo-alt me-2"></i>Location
                                    <i v-if="sortColumn === 'system_location'" :class="sortDirection === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" class="ms-1"></i>
                                </th>
                                <th class="px-4 cursor-pointer user-select-none" @click="sortBy('hp_model')">
                                    <i class="bi bi-lightning-charge me-2"></i>System
                                    <i v-if="sortColumn === 'hp_model'" :class="sortDirection === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" class="ms-1"></i>
                                </th>
                                <th class="px-4 cursor-pointer user-select-none" @click="sortBy('lastactive')">
                                    <i class="bi bi-clock-history me-2"></i>Last Active
                                    <i v-if="sortColumn === 'lastactive'" :class="sortDirection === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" class="ms-1"></i>
                                </th>
                                <th class="px-4 cursor-pointer user-select-none" @click="sortBy('access')">
                                    <i class="bi bi-shield-lock me-2"></i>Access
                                    <i v-if="sortColumn === 'access'" :class="sortDirection === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" class="ms-1"></i>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="sub_account in sortedAccounts" :key="sub_account.username">
                                <td class="px-4 py-3">
                                    <i class="bi bi-hash text-muted me-2"></i>
                                    {{ sub_account.id }}
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
                                    <span v-if="sub_account.system_location" class="text-muted">
                                        <i class="bi bi-geo-alt me-1"></i>{{ sub_account.system_location }}
                                    </span>
                                    <span v-else class="text-muted fst-italic">-</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="sub_account.hp_model">{{ sub_account.hp_output }} kW {{ sub_account.hp_manufacturer }} {{ sub_account.hp_model }} </span>
                                    <span v-else class="text-muted fst-italic">-</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="sub_account.lastactive">
                                        {{ formatDate(sub_account.lastactive) }}
                                    </span>
                                    <span v-else class="text-muted fst-italic">Never</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="sub_account.access==0" class="badge bg-secondary">Disabled</span>
                                    <span v-else-if="sub_account.access==1" class="badge bg-warning">Read only</span>
                                    <span v-else-if="sub_account.access==2" class="badge bg-success">Write access</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Empty State -->
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
            sub_accounts: [],
            loading: true,
            sortColumn: 'id',
            sortDirection: 'asc'
        },
        computed: {
            sortedAccounts() {
                const accounts = [...this.sub_accounts];
                
                accounts.sort((a, b) => {
                    let aVal = a[this.sortColumn];
                    let bVal = b[this.sortColumn];
                    
                    // Handle null/undefined values - put them at the end
                    if (aVal == null || aVal === '') aVal = this.sortDirection === 'asc' ? '\uffff' : '';
                    if (bVal == null || bVal === '') bVal = this.sortDirection === 'asc' ? '\uffff' : '';
                    
                    // Numeric comparison for id, lastactive, access
                    if (this.sortColumn === 'id' || this.sortColumn === 'lastactive' || this.sortColumn === 'access') {
                        aVal = Number(aVal) || 0;
                        bVal = Number(bVal) || 0;
                    } else {
                        // String comparison (case-insensitive)
                        aVal = String(aVal).toLowerCase();
                        bVal = String(bVal).toLowerCase();
                    }
                    
                    if (aVal < bVal) return this.sortDirection === 'asc' ? -1 : 1;
                    if (aVal > bVal) return this.sortDirection === 'asc' ? 1 : -1;
                    return 0;
                });
                
                return accounts;
            }
        },
        methods: {
            sortBy(column) {
                if (this.sortColumn === column) {
                    this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortColumn = column;
                    this.sortDirection = 'asc';
                }
            },
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
                })
                .finally(() => {
                    this.loading = false;
                });
            },
            formatDate: function(timestamp) {
                const now = Math.floor(Date.now() / 1000);
                const diff = now - timestamp;
                
                if (diff < 60) {
                    return diff === 1 ? '1 second ago' : `${diff} seconds ago`;
                } else if (diff < 3600) {
                    const minutes = Math.floor(diff / 60);
                    return minutes === 1 ? '1 minute ago' : `${minutes} minutes ago`;
                } else if (diff < 86400) {
                    const hours = Math.floor(diff / 3600);
                    return hours === 1 ? '1 hour ago' : `${hours} hours ago`;
                } else if (diff < 2592000) {
                    const days = Math.floor(diff / 86400);
                    return days === 1 ? '1 day ago' : `${days} days ago`;
                } else if (diff < 31536000) {
                    const months = Math.floor(diff / 2592000);
                    return months === 1 ? '1 month ago' : `${months} months ago`;
                } else {
                    const years = Math.floor(diff / 31536000);
                    return years === 1 ? '1 year ago' : `${years} years ago`;
                }
            }
        },
        mounted() {
            this.get_sub_accounts();
        }
    });
</script>

<style>
.cursor-pointer {
    cursor: pointer;
}
.cursor-pointer:hover {
    background-color: rgba(0, 0, 0, 0.05);
}
</style>
