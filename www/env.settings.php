<?php

$settings = array(
    // HeatpumpMonitor requires a linked Emoncms installation
    "emoncms_host"=>$_ENV["EMONCMS_HOST"],
    "path"=>"/opt/openenergymonitor/heatpumpmonitor",

    "sql"=>array(
        "server"=>$_ENV["MYSQL_HOST"],
        "username"=>$_ENV["MYSQL_USER"],
        "password"=>$_ENV["MYSQL_PASSWORD"],
        "database"=>$_ENV["MYSQL_DATABASE"],
        "port"=>$_ENV["MYSQL_PORT"]
    ),

    "emoncms_credentials"=>array(
        "server"=>$_ENV["EMONCMS_MYSQL_HOST"],
        "username"=>$_ENV["EMONCMS_MYSQL_USER"],
        "password"=>$_ENV["EMONCMS_MYSQL_PASSWORD"],
        "database"=>$_ENV["EMONCMS_MYSQL_DATABASE"],
        "port"=>$_ENV["EMONCMS_MYSQL_PORT"]
    ),

    "mailersend_api_key"=>"",
    "email_verification"=>false,
    "change_notifications_enabled"=>false,
    "public_mode_enabled"=>true,
    "read_only_mode"=>false,

    "admin_emails"=>array(
        // array("email" => "hello@example.com"),
    )
    // "clearkey"=>"",
);
