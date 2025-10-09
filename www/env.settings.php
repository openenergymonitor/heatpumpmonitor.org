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
    "emoncmsorg_only"=>false
);
