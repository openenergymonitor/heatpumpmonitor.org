<?php

$settings = array(
    "sql"=>array(
        "server"=>$_ENV["MYSQL_HOST"],
        "username"=>$_ENV["MYSQL_USER"],
        "password"=>$_ENV["MYSQL_PASSWORD"],
        "database"=>$_ENV["MYSQL_DATABASE"],
        "port"=>$_ENV["MYSQL_PORT"]
    ),
    "mailersend_api_key"=>"",
    "change_notifications_enabled"=>false,
    "public_mode_enabled"=>true,

    // Enable development environment login (default admin/admin)
    // This enables a simple login method for development environments
    // Do not enable on production systems
    "dev_env_login_enabled"=>$_ENV["DEV_ENV_LOGIN_ENABLED"]
);
