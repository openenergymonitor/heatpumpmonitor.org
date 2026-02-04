<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');
global $settings;
?>

<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

<div id="app">
<div class="bg-light min-vh-100">
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
                                <th class="px-4">Actions</th>
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
                                <td class="px-4 py-3">
                                    <button class="btn btn-sm btn-outline-primary" @click="openEditModal(sub_account)">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
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

<!-- Edit Sub Account Modal -->
<div class="modal fade" id="editSubAccountModal" tabindex="-1" aria-labelledby="editSubAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editSubAccountModalLabel">
                    <i class="bi bi-pencil-square me-2"></i>Edit Sub Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div v-if="editingAccount">
                    
                    <!-- Username -->
                    <div class="mb-3">
                        <label class="form-label d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-person me-2"></i>Username</span>
                            <span v-if="isFieldModified('username')" class="badge bg-warning">Modified</span>
                        </label>
                        <input type="text" class="form-control" :class="{ 'border-warning border-2': isFieldModified('username') }" v-model="editForm.username" placeholder="Username">
                    </div>
                    
                    <!-- Email -->
                    <div class="mb-3">
                        <label class="form-label d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-envelope me-2"></i>Email</span>
                            <span v-if="isFieldModified('email')" class="badge bg-warning">Modified</span>
                        </label>
                        <input type="email" class="form-control" :class="{ 'border-warning border-2': isFieldModified('email') }" v-model="editForm.email" placeholder="Email address">
                    </div>
                    
                    <!-- Access Level -->
                    <div class="mb-3">
                        <label class="form-label d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-shield-lock me-2"></i>Access Level</span>
                            <span v-if="isFieldModified('access')" class="badge bg-warning">Modified</span>
                        </label>
                        <select class="form-select" :class="{ 'border-warning border-2': isFieldModified('access') }" v-model.number="editForm.access">
                            <option :value="0">Disabled - No access</option>
                            <option :value="1">Read Only - View data only</option>
                            <option :value="2">Write Access - Full permissions</option>
                        </select>
                    </div>
                    
                    <!-- Password -->
                    <div class="mb-3">
                        <label class="form-label d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-key me-2"></i>Password</span>
                            <span v-if="editForm.password" class="badge bg-warning">Modified</span>
                        </label>
                        <div class="input-group mb-2">
                            <input :type="showPassword ? 'text' : 'password'" class="form-control" :class="{ 'border-warning border-2': editForm.password }" v-model="editForm.password" placeholder="New password (leave blank to keep current)" @input="checkPasswordStrength">
                            <button class="btn btn-outline-secondary" type="button" @click="showPassword = !showPassword">
                                <i :class="showPassword ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                            </button>
                        </div>
                        
                        <!-- Password Strength Indicator -->
                        <div v-if="editForm.password" class="mb-2">
                            <div class="d-flex align-items-center">
                                <small class="text-muted me-2">Strength:</small>
                                <div class="progress flex-grow-1" style="height: 6px;">
                                    <div class="progress-bar" :class="passwordStrengthClass" :style="{width: passwordStrengthPercent + '%'}"></div>
                                </div>
                                <small class="ms-2" :class="passwordStrengthTextClass">{{ passwordStrengthText }}</small>
                            </div>
                        </div>
                        
                        <!-- Password Generator -->
                        <button class="btn btn-sm btn-outline-secondary" @click="generatePassword">
                            <i class="bi bi-arrow-repeat me-1"></i>Generate Password
                        </button>
                    </div>
                    
                    <!-- Save Status Alert -->
                    <div v-if="saveStatus" class="alert mb-0" :class="saveStatus.success ? 'alert-success' : 'alert-danger'">
                        <i :class="saveStatus.success ? 'bi bi-check-circle me-2' : 'bi bi-exclamation-triangle me-2'"></i>
                        {{ saveStatus.message }}
                    </div>
                    
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" @click="saveChanges" :disabled="saving || !hasChanges">
                    <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                    <i v-else class="bi bi-check-lg me-1"></i>Save Changes
                </button>
            </div>
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
            sortDirection: 'asc',
            editingAccount: null,
            originalForm: null,
            editForm: {
                username: '',
                email: '',
                access: 0,
                password: ''
            },
            saving: false,
            saveStatus: null,
            showPassword: false,
            passwordStrength: 0
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
            },
            passwordStrengthPercent() {
                return this.passwordStrength * 25;
            },
            passwordStrengthClass() {
                if (this.passwordStrength <= 1) return 'bg-danger';
                if (this.passwordStrength === 2) return 'bg-warning';
                if (this.passwordStrength === 3) return 'bg-info';
                return 'bg-success';
            },
            passwordStrengthText() {
                if (this.passwordStrength === 0) return 'Very Weak';
                if (this.passwordStrength === 1) return 'Weak';
                if (this.passwordStrength === 2) return 'Fair';
                if (this.passwordStrength === 3) return 'Good';
                return 'Strong';
            },
            passwordStrengthTextClass() {
                if (this.passwordStrength <= 1) return 'text-danger';
                if (this.passwordStrength === 2) return 'text-warning';
                if (this.passwordStrength === 3) return 'text-info';
                return 'text-success';
            },
            hasChanges() {
                if (!this.originalForm) return false;
                return this.editForm.username !== this.originalForm.username ||
                       this.editForm.email !== this.originalForm.email ||
                       this.editForm.access !== this.originalForm.access ||
                       this.editForm.password !== '';
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
            openEditModal(account) {
                this.editingAccount = account;
                this.editForm = {
                    username: account.username,
                    email: account.email,
                    access: account.access,
                    password: ''
                };
                // Store original values for comparison
                this.originalForm = {
                    username: account.username,
                    email: account.email,
                    access: account.access
                };
                this.saveStatus = null;
                this.passwordStrength = 0;
                this.showPassword = false;
                
                // Show modal using Bootstrap 5
                const modal = new bootstrap.Modal(document.getElementById('editSubAccountModal'));
                modal.show();
            },
            isFieldModified(field) {
                if (!this.originalForm) return false;
                if (field === 'password') return this.editForm.password !== '';
                return this.editForm[field] !== this.originalForm[field];
            },
            saveChanges() {
                // Client-side validation
                if (!this.editForm.username.trim()) {
                    this.saveStatus = { success: false, message: 'Username cannot be empty' };
                    return;
                }
                if (!this.isValidEmail(this.editForm.email)) {
                    this.saveStatus = { success: false, message: 'Please enter a valid email address' };
                    return;
                }
                if (this.editForm.password && this.editForm.password.length < 6) {
                    this.saveStatus = { success: false, message: 'Password must be at least 6 characters' };
                    return;
                }
                
                this.saving = true;
                this.saveStatus = null;
                
                // Prepare data - always include userid, only include changed fields
                const data = {
                    sub_account_userid: this.editingAccount.id
                };
                
                // Only include fields that have actually been modified
                if (this.editForm.username !== this.originalForm.username) {
                    data.username = this.editForm.username;
                }
                if (this.editForm.email !== this.originalForm.email) {
                    data.email = this.editForm.email;
                }
                if (this.editForm.access !== this.originalForm.access) {
                    data.access = this.editForm.access;
                }
                if (this.editForm.password) {
                    data.password = this.editForm.password;
                }
                
                axios.post("<?php echo $path ?>user/update-subaccount.json", data)
                    .then(response => {
                        if (response.data.success) {
                            this.saveStatus = { 
                                success: true, 
                                message: response.data.message || 'Sub account updated successfully!' 
                            };
                            
                            // Update local data to reflect changes
                            if (data.username) this.editingAccount.username = this.editForm.username;
                            if (data.email) this.editingAccount.email = this.editForm.email;
                            if (data.access !== undefined) this.editingAccount.access = this.editForm.access;
                            
                            // Update original form values so changes are no longer marked as modified
                            this.originalForm = {
                                username: this.editForm.username,
                                email: this.editForm.email,
                                access: this.editForm.access
                            };
                            
                            // Clear password field after successful save
                            this.editForm.password = '';
                            this.passwordStrength = 0;
                            this.showPassword = false;
                            
                            // Auto-clear success message after 3 seconds
                            setTimeout(() => {
                                this.saveStatus = null;
                            }, 3000);
                        } else {
                            this.saveStatus = { 
                                success: false, 
                                message: response.data.message || 'Failed to update sub account' 
                            };
                        }
                    })
                    .catch(error => {
                        console.error('Error updating sub account:', error);
                        this.saveStatus = { 
                            success: false, 
                            message: error.response?.data?.message || 'Error: ' + error.message 
                        };
                    })
                    .finally(() => {
                        this.saving = false;
                    });
            },
            generatePassword() {
                const length = 16;
                const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?";
                let password = "";
                
                // Ensure at least one of each type
                password += "ABCDEFGHIJKLMNOPQRSTUVWXYZ"[Math.floor(Math.random() * 26)];
                password += "abcdefghijklmnopqrstuvwxyz"[Math.floor(Math.random() * 26)];
                password += "0123456789"[Math.floor(Math.random() * 10)];
                password += "!@#$%^&*"[Math.floor(Math.random() * 8)];
                
                // Fill the rest randomly
                for (let i = password.length; i < length; i++) {
                    password += charset[Math.floor(Math.random() * charset.length)];
                }
                
                // Shuffle the password
                password = password.split('').sort(() => Math.random() - 0.5).join('');
                
                this.editForm.password = password;
                this.showPassword = true;
                this.checkPasswordStrength();
            },
            checkPasswordStrength() {
                const password = this.editForm.password;
                if (!password) {
                    this.passwordStrength = 0;
                    return;
                }
                
                let strength = 0;
                
                // Length check
                if (password.length >= 8) strength++;
                if (password.length >= 12) strength++;
                
                // Character variety checks
                if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
                if (/\d/.test(password)) strength++;
                if (/[^a-zA-Z\d]/.test(password)) strength++;
                
                this.passwordStrength = Math.min(strength, 4);
            },
            isValidEmail(email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
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
.border-warning {
    border-width: 2px !important;
}
</style>
