<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

global $settings;
// get only the domain part of the URL
$emoncms_host = parse_url($settings['emoncms_host'], PHP_URL_HOST);

?>

<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>

<style>
    body {
        background-color: #1d8dbc;
        min-height: 100vh;
    }
    
    .card-body {
        background-color: whitesmoke;
    }

    .auth-card {
        max-width: 420px;
        margin: 80px auto;
    }
    
    .auth-header {
        background-color: #44b3e2;
        color: white;
    }
    
    .form-control::placeholder {
        opacity: 0.4;
    }
    
    .password-strength {
        height: 4px;
        background: #e9ecef;
        border-radius: 2px;
        margin-top: 8px;
        overflow: hidden;
    }
    
    .password-strength-bar {
        height: 100%;
        transition: width 0.3s ease;
    }
    
    .strength-weak { background: #dc3545; width: 33%; }
    .strength-medium { background: #ffc107; width: 66%; }
    .strength-strong { background: #198754; width: 100%; }
</style>

<div id="app" class="container">
    <div class="card auth-card shadow-lg border-0">
        <!-- Dynamic Header -->
        <div class="auth-header rounded-top p-4 text-center">
            <transition name="fade" mode="out-in">
                <div v-if="mode === 'login'" key="login">
                    <h1 class="h3 mb-2">Sign In</h1>
                    <p class="mb-0 opacity-75">Login with {{ emoncmsHost }}</p>
                </div>
                <div v-else-if="mode === 'register'" key="register">
                    <h1 class="h3 mb-2">Create Account</h1>
                    <p class="mb-0 opacity-75">Join HeatpumpMonitor.org</p>
                </div>
                <div v-else-if="mode === 'reset'" key="reset">
                    <h1 class="h3 mb-2">Reset Password</h1>
                    <p class="mb-0 opacity-75">We'll send you recovery instructions</p>
                </div>
            </transition>
        </div>

        <div class="card-body p-4">
            <!-- Login Form -->
            <transition name="fade" mode="out-in">
                <div v-if="mode === 'login'" key="login-form">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username</label>
                        <input type="text" class="form-control form-control-lg" v-model="username" 
                               placeholder="Enter your username" @keyup.enter="login">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password</label>
                        <input type="password" class="form-control form-control-lg" v-model="password" 
                               placeholder="Enter your password" @keyup.enter="login">
                    </div>

                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" v-model="rememberMe" id="rememberMe">
                            <label class="form-check-label" for="rememberMe">
                                Remember me
                            </label>
                        </div>
                        <a href="#" class="link-primary text-decoration-none" @click.prevent="switchMode('reset')">
                            Forgot password?
                        </a>
                    </div>

                    <button type="button" class="btn btn-primary btn-lg w-100 mb-3" @click="login">
                        Login
                    </button>

                    <div class="text-center border-top pt-3" v-if="!disable_account_creation">
                        <span class="text-muted">Don't have an account? </span>
                        <a href="#" class="link-primary text-decoration-none fw-semibold" @click.prevent="switchMode('register')">
                            Create one
                        </a>
                    </div>
                </div>

                <!-- Register Form -->
                <div v-else-if="mode === 'register'" key="register-form">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" class="form-control form-control-lg" v-model="email" 
                               placeholder="your@email.com">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username</label>
                        <input type="text" class="form-control form-control-lg" v-model="username" 
                               placeholder="Choose a username">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password</label>
                        <input type="password" class="form-control form-control-lg" v-model="password" 
                               placeholder="Create a strong password"
                               @input="checkPasswordStrength">
                        <div class="password-strength" v-if="password">
                            <div class="password-strength-bar" :class="passwordStrengthClass"></div>
                        </div>
                        <small class="text-muted" v-if="password">
                            {{ passwordStrengthText }}
                        </small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirm Password</label>
                        <input type="password" class="form-control form-control-lg" v-model="password2" 
                               placeholder="Re-enter your password"
                               @keyup.enter="register">
                    </div>

                    <button type="button" class="btn btn-primary btn-lg w-100 mb-3" @click="register">
                        Create Account
                    </button>

                    <div class="text-center border-top pt-3">
                        <span class="text-muted">Already have an account? </span>
                        <a href="#" class="link-primary text-decoration-none fw-semibold" @click.prevent="switchMode('login')">
                            Login
                        </a>
                    </div>
                </div>

                <!-- Password Reset Form -->
                <div v-else-if="mode === 'reset'" key="reset-form">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username</label>
                        <input type="text" class="form-control form-control-lg" v-model="resetUsername" 
                               placeholder="Enter your username">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" class="form-control form-control-lg" v-model="resetEmail" 
                               placeholder="your@email.com"
                               @keyup.enter="resetPassword">
                    </div>

                    <button type="button" class="btn btn-primary btn-lg w-100 mb-3" @click="resetPassword">
                        Send Recovery Email
                    </button>

                    <div class="text-center border-top pt-3">
                        <a href="#" class="link-primary text-decoration-none fw-semibold" @click.prevent="switchMode('login')">
                            Back to login
                        </a>
                    </div>
                </div>
            </transition>

            <!-- Messages -->
            <transition name="fade">
                <div class="alert alert-danger mt-3" v-if="error" v-html="error"></div>
            </transition>
            <transition name="fade">
                <div class="alert alert-success mt-3" v-if="success" v-html="success"></div>
            </transition>
        </div>
    </div>
</div>

<script>
    var app = new Vue({
        el: '#app',
        data: {
            mode: 'login', // 'login', 'register', 'reset'
            username: "",
            password: "",
            password2: "",
            email: "",
            resetUsername: "",
            resetEmail: "",
            rememberMe: false,
            error: false,
            success: false,
            emoncmsHost: "<?php echo $emoncms_host; ?>",
            passwordStrength: 0,
            // disabled for now
            disable_account_creation: true
        },
        computed: {
            passwordStrengthClass() {
                if (this.passwordStrength < 2) return 'strength-weak';
                if (this.passwordStrength < 3) return 'strength-medium';
                return 'strength-strong';
            },
            passwordStrengthText() {
                if (this.passwordStrength < 2) return 'Weak password';
                if (this.passwordStrength < 3) return 'Medium strength';
                return 'Strong password';
            }
        },
        methods: {
            switchMode(newMode) {
                this.mode = newMode;
                this.error = false;
                this.success = false;
                this.password = "";
                this.password2 = "";
            },
            
            checkPasswordStrength() {
                let strength = 0;
                if (this.password.length >= 8) strength++;
                if (/[a-z]/.test(this.password) && /[A-Z]/.test(this.password)) strength++;
                if (/\d/.test(this.password)) strength++;
                if (/[^a-zA-Z0-9]/.test(this.password)) strength++;
                this.passwordStrength = strength;
            },
            
            login: function() {
                this.error = false;
                this.success = false;
                
                if (!this.username || !this.password) {
                    this.error = "Please enter username and password";
                    return;
                }
                
                const params = new URLSearchParams();
                params.append('username', this.username);
                params.append('password', this.password);
                params.append('rememberme', this.rememberMe ? '1' : '0');

                axios.post(path + "user/login.json", params)
                    .then(function(response) {
                        if (response.data.success) {
                            window.location.href = path + "system/list/user";
                        } else {
                            app.error = response.data.message;
                        }
                    })
                    .catch(function(error) {
                        app.error = "An error occurred. Please try again.";
                        console.log(error);
                    });
            },
            
            register: function() {
                this.error = false;
                this.success = false;
                
                // Validation
                if (!this.email || !this.username || !this.password || !this.password2) {
                    this.error = "Please fill in all fields";
                    return;
                }
                
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email)) {
                    this.error = "Please enter a valid email address";
                    return;
                }
                
                if (this.password.length < 6) {
                    this.error = "Password must be at least 6 characters long";
                    return;
                }
                
                if (this.password !== this.password2) {
                    this.error = "Passwords do not match";
                    return;
                }
                
                // Placeholder API call
                const params = new URLSearchParams();
                params.append('username', this.username);
                params.append('password', this.password);
                params.append('email', this.email);
                
                // TODO: Implement actual API endpoint
                axios.post(path + "user/register.json", params)
                    .then(function(response) {
                        if (response.data.success) {
                            app.success = "Registration successful! Please check your email to verify your account.";
                            setTimeout(() => {
                                app.switchMode('login');
                            }, 3000);
                        } else {
                            app.error = response.data.message || "Registration failed. Please try again.";
                        }
                    })
                    .catch(function(error) {
                        // For now, show placeholder message
                        app.error = "Registration endpoint not yet implemented. This is a placeholder.";
                        console.log(error);
                    });
            },
            
            resetPassword: function() {
                this.error = false;
                this.success = false;
                
                if (!this.resetUsername || !this.resetEmail) {
                    this.error = "Please enter both username and email";
                    return;
                }
                
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.resetEmail)) {
                    this.error = "Please enter a valid email address";
                    return;
                }
                
                // Placeholder API call
                const params = new URLSearchParams();
                params.append('username', this.resetUsername);
                params.append('email', this.resetEmail);
                
                // TODO: Implement actual API endpoint
                axios.post(path + "user/passwordreset.json", params)
                    .then(function(response) {
                        if (response.data.success) {
                            app.success = response.data.message || "Password reset email sent! Please check your inbox.";
                            setTimeout(() => {
                                app.switchMode('login');
                            }, 3000);
                        } else {
                            app.error = response.data.message || "Password reset failed. Please try again.";
                        }
                    })
                    .catch(function(error) {
                        // For now, show placeholder message
                        app.error = "Password reset endpoint not yet implemented. This is a placeholder.";
                        console.log(error);
                    });
            }
        },
        mounted() {
        }
    });
</script>
