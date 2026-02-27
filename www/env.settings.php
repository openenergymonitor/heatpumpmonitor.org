<?php

$settings = array(
    // HeatpumpMonitor requires a linked Emoncms installation
    "emoncms_host"=>$_ENV["EMONCMS_HOST"],

    "sql"=>array(
        "server"=>$_ENV["MYSQL_HOST"],
        "username"=>$_ENV["MYSQL_USER"],
        "password"=>$_ENV["MYSQL_PASSWORD"],
        "database"=>$_ENV["MYSQL_DATABASE"],
        "port"=>$_ENV["MYSQL_PORT"]
    ),
    "mailersend_api_key"=>"",
    "change_notifications_enabled"=>false,
    "public_mode_enabled"=>true
);
