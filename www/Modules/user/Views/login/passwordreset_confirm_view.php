<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

// The token is validated server side, both on this page load and again on
// redemption. It is echoed below into a script variable, so escape it rather
// than trusting the query string.
$key = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');

// Set by the controller from User::passwordreset_key_is_valid()
$key_valid = !empty($key_valid);
?>

<script src="<?php echo $path; ?>theme/vendor/vue-2.7.16/vue.min.js" integrity="sha384-YVYXhPGIH/Gmcr0W5Rin4PcpcsG1a4pcdUUod1CnbDEJut7XiUaJtSlNKeRLJBPk"></script>
<script src="<?php echo $path; ?>theme/vendor/axios-1.4.0/axios.min.js" integrity="sha384-I4Qw/vWb/sK/7VwepTtkaq636YLYClbEgEwKp3ueUCvjiLFrcoKUFAY5mOl40Fj3"></script>

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

    .auth-brand {
        font-size: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12px;
    }

    .auth-brand-logo {
        height: 36px;
        width: 36px;
        margin-right: 10px;
    }

    .form-control::placeholder {
        opacity: 0.4;
    }
</style>

<div id="app" class="container">
    <div class="card auth-card shadow-lg border-0">
        <div class="auth-header rounded-top p-4 text-center">
            <a class="auth-brand text-white text-decoration-none" href="<?php echo $path; ?>">
                <img src="<?php echo $path; ?>theme/img/logo/apple-touch-icon.png" alt="HeatpumpMonitor Logo" class="auth-brand-logo">
                <span><b>HeatpumpMonitor</b>.org</span>
            </a>
            <p class="mb-0 opacity-75">Choose a new password</p>
        </div>

        <div class="card-body p-4">
<?php if (!$key_valid) { ?>
            <div class="alert alert-danger mb-3">This password reset link is invalid or has expired, please request a new one.</div>
<?php } else { ?>
            <div v-if="!done">
                <div class="mb-3">
                    <label class="form-label fw-semibold">New password</label>
                    <input type="password" class="form-control form-control-lg" v-model="password"
                           autocomplete="new-password" placeholder="Enter a new password">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Confirm new password</label>
                    <input type="password" class="form-control form-control-lg" v-model="password2"
                           autocomplete="new-password" placeholder="Re-enter your new password"
                           @keyup.enter="submit">
                </div>

                <button type="button" class="btn btn-primary btn-lg w-100 mb-3" @click="submit" :disabled="busy">
                    Set new password
                </button>
            </div>

            <transition name="fade">
                <div class="alert alert-danger mb-3" v-if="error">{{ error }}</div>
            </transition>
            <transition name="fade">
                <div class="alert alert-success mb-3" v-if="success">{{ success }}</div>
            </transition>
<?php } ?>

            <div class="text-center border-top pt-3">
                <a href="<?php echo $path; ?>user/login" class="link-primary text-decoration-none fw-semibold">
                    <?php echo $key_valid ? "Back to login" : "Request a new link"; ?>
                </a>
            </div>
        </div>
    </div>
</div>

<?php if ($key_valid) { ?>
<script>
    var reset_key = <?php echo json_encode($key); ?>;

    var app = new Vue({
        el: '#app',
        data: {
            password: "",
            password2: "",
            error: false,
            success: false,
            busy: false,
            done: false
        },
        methods: {
            submit: function() {
                this.error = false;
                this.success = false;

                if (this.password.length < 4) {
                    this.error = "Password must be at least 4 characters";
                    return;
                }
                if (this.password !== this.password2) {
                    this.error = "Passwords do not match";
                    return;
                }

                const params = new URLSearchParams();
                params.append('key', reset_key);
                params.append('password', this.password);

                this.busy = true;
                axios.post(path + "user/passwordreset-confirm.json", params)
                    .then(function(response) {
                        if (response.data.success) {
                            // The token is spent: hide the form so it cannot be resubmitted
                            app.done = true;
                            app.success = response.data.message;
                        } else {
                            app.error = response.data.message || "Password reset failed";
                        }
                    })
                    .catch(function(error) {
                        app.error = "Password reset failed, please try again";
                        console.log(error);
                    })
                    .then(function() {
                        app.busy = false;
                    });
            }
        }
    });
</script>
<?php } ?>
