<?php

$settings = array(
    // HeatpumpMonitor requires a linked Emoncms installation
    "emoncms_host"=>"https://emoncms.org",
    "path"=>"/opt/openenergymonitor/heatpumpmonitor",

    "sql"=>array(
        "server"=>"localhost",
        "username"=>"username",
        "password"=>"password",
        "database"=>"heatpumpmonitor",
        "port"=>3306
    ),

    "emoncms_credentials"=>array(
        "server"=>"localhost",
        "username"=>"emoncms",
        "password"=>"emoncms",
        "database"=>"emoncms",
        "port"=>3306
    ),
    "mailersend_api_key"=>"",
    "email_verification"=>false,
    "change_notifications_enabled"=>false,
    "public_mode_enabled"=>true,
    "read_only_mode"=>false,

    "admin_emails"=>array(
        // array("email" => "hello@example.com"),
    ),

    "emoncmsorg_only"=>true,
    // "clearkey"=>"",
);
