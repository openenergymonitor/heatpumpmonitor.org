<?php

$settings = array(
    // HeatpumpMonitor requires a linked Emoncms installation (Docker: set EMONCMS_HOST, e.g. http://emoncms)
    "emoncms_host" => getenv("EMONCMS_HOST") ?: "https://emoncms.org",
    "path"=>"/opt/openenergymonitor/heatpumpmonitor",

    // This site's own public host (plus any sub directory), used to build the
    // links in password reset emails, e.g "heatpumpmonitor.org" or
    // "localhost/heatpumpmonitor". Required for password reset to work: the
    // link is never built from the request's Host header, which an attacker
    // could point at their own server. With this false no reset email is sent.
    "domain" => getenv("DOMAIN") ?: false,

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

    // Maintenance mode, see Lib/maintenance.php. "mode" is "off" (or absent)
    // for normal operation, "offline" to stop the site with a message, or
    // "silent" to stop it saying nothing at all. "until" is when the site is
    // expected back, written as a person would write it, and drives the
    // countdown on the maintenance page. "access_override" is a random string
    // that lets an admin through while the site is down, sent as the
    // X-Access-Override header. Answered before any database connection, so it
    // still works while mysql is stopped.
    //
    // "maintenance"=>array(
    //     "mode" => "off",
    //     "message" => "HeatpumpMonitor.org is being updated, back shortly",
    //     "until" => "2026-09-02 18:00",
    //     "access_override" => ""
    // ),

    "admin_emails"=>array(
        // array("email" => "hello@example.com"),
    )
    // "clearkey"=>"",
);
