<?php

$settings = array(
    // HeatpumpMonitor requires a linked Emoncms installation (Docker: set EMONCMS_HOST, e.g. http://emoncms)
    "emoncms_host" => getenv("EMONCMS_HOST") ?: "https://emoncms.org",
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
    // Password hashing. algo is "bcrypt" (default) or "argon2id".
    //
    // MUST match settings['password'] on the linked emoncms install above.
    // Accounts are shared, and this block decides only what gets WRITTEN, never
    // what can be READ: hashes carry their own algorithm and parameters, so a
    // mismatch does not lock anyone out. It does mean the two sites would each
    // decide the other's hashes need rewriting, and pay for a rehash on every
    // single login, forever. See Lib/SHARED.md.
    "password"=>array(
        'algo' => 'bcrypt',
        'bcrypt_cost' => 10,
        'argon2_memory_cost' => 65536,
        'argon2_time_cost' => 3,
        'argon2_threads' => 1
    ),

    // Log file configuration
    "log"=>array(
        "enabled" => false,
        "location" => "/var/log/heatpumpmonitor",
        // Log Level: 1=INFO, 2=WARN, 3=ERROR
        "level" => 2
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
