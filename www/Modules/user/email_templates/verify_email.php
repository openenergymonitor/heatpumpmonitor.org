<?php
defined('EMONCMS_EXEC') or die('Restricted access');
?>
<!DOCTYPE html>
<html>
<head>
  <title>Email Verification</title>
  <style>
    /* CSS styles for the email body */
    body {
      font-family: Arial, sans-serif;
      background-color: #f5f5f5;
      margin: 0;
      padding: 0;
    }
    .container {
      max-width: 600px;
      margin: 0 auto;
      padding: 20px;
    }
    h1 {
      color: #333333;
    }
    p {
      color: #555555;
      line-height: 1.5;
    }
    .button {
      display: inline-block;
      background-color: #4caf50;
      color: #ffffff;
      text-decoration: none;
      padding: 10px 20px;
      border-radius: 4px;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Email Verification</h1>
    <p>Dear <?php echo $username; ?>,</p>
    <p>Please click the button below to verify your email address:</p>
    <p>
      <a class="button" href="<?php echo $verification_url; ?>">Verify Email</a>
    </p>
    <p>If you did not request this verification, you can safely ignore this email.</p>
    <p>Thank you,</p>
    <p><b>HeatpumpMonitor</b>.org</p>
  </div>
</body>
</html>
