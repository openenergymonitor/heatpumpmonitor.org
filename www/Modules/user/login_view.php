<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

$enable_register = false;
$emoncmsorg_only = true;

?>

<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>

<!-- Fontawesome CDN Link -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

<style>
/* Google Font Link */
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;500;600;700&display=swap');

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

body {
  min-height: 100vh;
  display: flex;
  background: #28377c;
  
}

.form_container {
  position: relative;
  max-width: 850px;
  width: 100%;
  background: #fff;
  padding: 40px 30px;
  box-shadow: 0 5px 10px rgba(0, 0, 0, 0.2);
  perspective: 2700px;
}

.form_container .cover {
  position: absolute;
  top: 0;
  left: 50%;
  height: 100%;
  width: 50%;
  z-index: 98;
  transition: all 1s ease;
  transform-origin: left;
  transform-style: preserve-3d;
  backface-visibility: hidden;
}

.form_container #flip:checked ~ .cover {
  transform: rotateY(-180deg);
}

.form_container #flip:checked ~ .forms .login-form {
  pointer-events: none;
}

.form_container .cover .front,
.form_container .cover .back {
  position: absolute;
  top: 0;
  left: 0;
  height: 100%;
  width: 100%;
}

.cover .back {
  transform: rotateY(180deg);
}

.form_container .cover img {
  position: absolute;
  height: 100%;
  width: 100%;
  object-fit: cover;
  z-index: 10;
}

.form_container .cover .text {
  position: absolute;
  z-index: 10;
  height: 100%;
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.form_container .cover .text::before {
  content: '';
  position: absolute;
  height: 100%;
  width: 100%;
  opacity: 0.5;
  background: #28377c;
}

.cover .text .text-1,
.cover .text .text-2 {
  z-index: 20;
  font-size: 26px;
  font-weight: 600;
  color: #fff;
  text-align: center;
}

.cover .text .text-2 {
  font-size: 15px;
  font-weight: 500;
}

.form_container .forms {
  height: 100%;
  width: 100%;
  background: #fff;
}

.form_container .form-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.form-content .login-form,
.form-content .signup-form {
  width: calc(100% / 2 - 25px);
}

.forms .form-content .title {
  position: relative;
  font-size: 24px;
  font-weight: 500;
  color: #333;
}

.forms .form-content .title:before {
  content: '';
  position: absolute;
  left: 0;
  bottom: 0;
  height: 3px;
  width: 25px;
  background: #28377c;
}

.forms .signup-form .title:before {
  width: 20px;
}

.forms .form-content .input-boxes {
  margin-top: 30px;
}

.forms .form-content .input-box {
  display: flex;
  align-items: center;
  height: 50px;
  width: 100%;
  margin: 10px 0;
  position: relative;
}

.form-content .input-box input {
  height: 100%;
  width: 100%;
  outline: none;
  border: none;
  padding: 0 30px;
  font-size: 16px;
  font-weight: 500;
  border-bottom: 2px solid rgba(0, 0, 0, 0.2);
  transition: all 0.3s ease;
}

.form-content .input-box input:focus,
.form-content .input-box input:valid {
  border-color: #192351;
}

.form-content .input-box i {
  position: absolute;
  color: #28377c;
  font-size: 17px;
}

.forms .form-content .text {
  font-size: 14px;
  font-weight: 500;
  color: #333;
}

.forms .form-content .text a {
  text-decoration: none;
}

.forms .form-content .text a:hover {
  text-decoration: underline;
}

.forms .form-content .button {
  color: #fff;
  margin-top: 40px;
}

.forms .form-content .button input {
  color: #fff;
  background: #28377c;
  border-radius: 6px;
  padding: 0;
  cursor: pointer;
  transition: all 0.4s ease;
}

.forms .form-content .button input:hover {
  background: #495ebe;
}

.forms .form-content label {
  color: #495ebe;
  cursor: pointer;
}

.forms .form-content label:hover {
  text-decoration: underline;
}

.forms .form-content .login-text,
.forms .form-content .sign-up-text {
  text-align: center;
  margin-top: 25px;
}

.form_container #flip {
  display: none;
}

@media (max-width: 730px) {
  .form_container .cover {
    display: none;
  }

  .form-content .login-form,
  .form-content .signup-form {
    width: 100%;
  }

  .form-content .signup-form {
    display: none;
  }

  .form_container #flip:checked ~ .forms .signup-form {
    display: block;
  }

  .form_container #flip:checked ~ .forms .login-form {
    display: none;
  }
}
</style>

<div id="app" class="containers" style="padding-top:50px; padding-left:20px; padding-bottom: 30px; padding-right:20px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
    <div v-if="!mode">
        <button type="button" class="btn btn-primary btn-lg" style="width:100%" @click="mode='emoncmsorg'">Login with emoncms.org</button>
        <button type="button" class="btn btn-outline-primary btn-lg" style="width:100%; margin-top:10px" @click="mode='selfhost'">Self hosted data</button>
    </div>

    <div v-else class="form_container">
        <input type="checkbox" id="flip" v-bind:checked="mode=='register'">
        <div class="cover">
        <div class="front">
            <img src="..\..\theme\img\login.png" alt="">
            <div class="text">
            <span class="text-1">Monitor your energy <br> with precision</span>
            <span class="text-2">Stay connected, stay efficient</span>
            </div>
        </div>
        <div class="back">
            <img class="backImg" src="..\..\theme\img\register.png" alt="">
            <div class="text">
            <span class="text-1">Join the energy <br> monitoring revolution</span>
            <span class="text-2">Get started today</span>
            </div>
        </div>
        </div>
        <div class="forms">
            <div class="form-content">
            <div class="login-form">
                <div class="title">Login <span style="font-size: 12px;" v-if="mode=='emoncmsorg'">with emoncms.org</span><span style="color:#888; font-size: 12px;" v-if="mode=='selfhost'">Self hosted data</span></div>

            <form @submit.prevent="login">
                <div class="input-boxes">
                <div class="input-box ">
                    <i class="fas fa-envelope"></i>
                    <input type="text" placeholder="Enter your username" class="form-control" v-model="username" required>
                </div>
                <div class="input-box">
                    <i class="fas fa-lock"></i>
                    <input type="password" class="form-control" v-model="password" placeholder="Enter your password" required>
                </div>
                <div class="text"><a href="#">Forgot password?</a></div>
                <div class="button input-box">
                    <input type="submit" value="Login">
                </div>
                <div class="text sign-up-text">Don't have an account? <label for="flip">Sigup now</label></div>
            </div>
            </form>
        </div>
        <div class="signup-form">
            <div class="title">Signup</div>
            <form @submit.prevent="register">
                <div class="input-boxes">
                <div class="input-box">
                    <i class="fas fa-user"></i>
                    <input type="text" placeholder="Enter your username" class="form-control" v-model="username" required>
                </div>
                <div class="input-box">
                    <i class="fas fa-envelope"></i>
                    <input type="text" class="form-control" placeholder="Enter your email" v-model="email" required>
                </div>
                <div class="input-box">
                    <i class="fas fa-lock"></i>
                    <input type="password" placeholder="Enter your password" required>
                </div>
                <div class="input-box">
                    <i class="fas fa-lock"></i>
                    <input type="password" class="form-control" v-model="password2" placeholder="Re-Enter your password" required>
                </div>
                <div class="button input-box">
                    <input type="submit" value="Register">
                </div>
                <div class="text sign-up-text">Already have an emoncms.org account? <label for="flip">Login now</label></div>
            </div>
            </form>
            </div>
        </div>
    

            <div class="alert alert-danger" style="margin-top:20px; margin-bottom: 5px; width:90%; z-index:150; position: absolute; left: 5px; bottom: 0px;"  v-if="error" v-html="error"></div>
            <div class="alert alert-success" style="margin-top:20px; margin-bottom: 5px; width:90%; z-index:150; position: absolute; left: 5px; bottom: 0px;" v-if="success" v-html="success"></div>
    </div>
</div>
    
</div>

<script>

    document.body.style.backgroundColor = "#1d8dbc";

    var emoncmsorg_only = <?php echo $emoncmsorg_only ? "true" : "false"; ?>;

    var app = new Vue({
        el: '#app',
        data: {
            username: "",  // The username of the user
            password: "",  // The password of the user
            password2: "", // The password re-entered by the user
            email: "",     // The email of the user
            error: false,
            success: false,
            mode: 'emoncmsorg',
            public_mode_enabled: public_mode_enabled
        },
        methods: {
            /* The below code is a JavaScript function that handles a login request using Axios to send
            a POST request to a specified URL with the username, password, and emoncmsorg
            parameters. Upon receiving a response, it checks if the login was successful based on
            the "success" property in the response data. If successful, it redirects the user to a
            specified URL for system listing. If unsuccessful, it sets an error message based on the
            response data. If there is an error during the request, it logs the error to the
            console. */
            login: function() {
                const params = new URLSearchParams();
                params.append('username', this.username);
                params.append('password', this.password);
                params.append('emoncmsorg', emoncmsorg_only ? 0 : (this.mode === "emoncmsorg" ? 1 : 0));

                axios.post(path + "user/login.json", params)
                    .then((response) => {
                        if (response.data.success) {
                            this.error = false;
                            window.location.href = path + "system/list/user";
                        } else {
                            this.error = response.data.message;
                            this.success = false;
                        }
                    })
                    .catch((error) => {
                        console.log(error);
                    });
            },
            /* The below code is a JavaScript function that is making a POST request to a PHP backend
            endpoint for user registration. It is sending the username, password, password2, and
            email as parameters in the request. Upon receiving a response from the server, it checks
            if the registration was successful. If successful, it may prompt the user to verify
            their email or redirect them to a user list page. If there is an error during
            registration, it will display the error message. The code uses Axios for making the HTTP
            request and handles both successful and error responses. */
            register: function() {
                const params = new URLSearchParams();
                params.append('username', this.username);
                params.append('password', this.password);
                params.append('password2', this.password2);
                params.append('email', this.email);

                axios.post(path + "user/register.json", params)
                    .then((response) => {
                        if (response.data.success) {
                            this.error = false;
                            if (response.data.verifyemail !== undefined && response.data.verifyemail) {
                                this.mode = 'login';
                                this.success = "Registration successful, please check your email to verify your account";
                            } else {
                                window.location.href = path + "system/list/user";
                            }
                        } else {
                            this.error = response.data.message;
                            this.success = false;
                        }
                    })
                    .catch((error) => {
                        console.log(error);
                    });
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

