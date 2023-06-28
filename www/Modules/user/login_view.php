<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>

<div class="container" style="max-width:380px; padding-top:120px; height:800px;" >

<div class="card">
  <div class="card-body bg-light">

  <h1 class="h3 mb-3 fw-normal">Login</h1>
  <p>Login with your emoncms.org account</p>

  <label>Username</label>
  <div class="input-group mb-3">
    <input type="text" class="form-control" id="username"> 
  </div>

  <label>Password</label>
  <div class="input-group mb-3">
    <input type="password" class="form-control" id="password"> 
  </div>
  
  <button type="button" class="btn btn-primary" id="login">Login</button>
  
  <div id="error" class="alert alert-danger" role="alert" style="display: none; margin-top:20px; margin-bottom: 5px;"></div>

  </div>
</div>

</div>

<script>
$("body").css("background-color","#1d8dbc");

$("#login").click(function() {

  var username
  $.ajax({
    type: "POST", url: "login.json",
    data: {
        username: encodeURIComponent($("#username").val()),
        password: encodeURIComponent($("#password").val())
    },
    dataType: "json", async: false,
    success: function(result) {
       console.log(result);
       if (!result.success) {
           $("#error").html("<b>Error:</b> "+result.message).show();
       } else {
           window.location = path+"system/list";
       }
    }
  });
});
</script>
