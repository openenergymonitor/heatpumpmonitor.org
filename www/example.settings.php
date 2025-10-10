<?php

$settings = array(
    "sql"=>array(
        "server"=>"localhost",
        "username"=>"username",
        "password"=>"password",
        "database"=>"heatpumpmonitor",
        "port"=>3306
    ),
    "mailersend_api_key"=>"",
    "change_notifications_enabled"=>false,
    "public_mode_enabled"=>true,
    
    // Enable development environment login (default admin/admin)
    // This enables a simple login method for development environments
    // Do not enable on production systems
    "dev_env_login_enabled"=>false
);
