<?php

$settings = array(
    // HeatpumpMonitor requires a linked Emoncms installation
    "emoncms_host" => getenv("EMONCMS_HOST") ?: "https://emoncms.org",
    "path"=>"/opt/openenergymonitor/heatpumpmonitor",

    // This site's own public host, used to build password reset email links.
    // Never taken from the request's Host header. Unset: no reset email is sent.
    "domain" => getenv("DOMAIN") ?: false,

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

    // Password hashing. MUST match settings['password'] on the linked emoncms
    // install: accounts are shared, and if the two disagree each site rewrites
    // the other's hashes on every login. Only affects what is written, never
    // what can be read. See Lib/SHARED.md.
    "password"=>array(
        'algo' => getenv("PASSWORD_ALGO") ?: 'bcrypt',
        'bcrypt_cost' => (int) (getenv("PASSWORD_BCRYPT_COST") ?: 10),
        'argon2_memory_cost' => (int) (getenv("PASSWORD_ARGON2_MEMORY_COST") ?: 65536),
        'argon2_time_cost' => (int) (getenv("PASSWORD_ARGON2_TIME_COST") ?: 3),
        'argon2_threads' => (int) (getenv("PASSWORD_ARGON2_THREADS") ?: 1)
    ),

    // Log file configuration
    "log"=>array(
        "enabled" => (bool) getenv("LOG_ENABLED"),
        "location" => getenv("LOG_LOCATION") ?: "/var/log/heatpumpmonitor",
        // Log Level: 1=INFO, 2=WARN, 3=ERROR
        "level" => (int) (getenv("LOG_LEVEL") ?: 2)
    ),

    "mailersend_api_key"=>"",
    "email_verification"=>false,
    "change_notifications_enabled"=>false,
    "public_mode_enabled"=>true,
    "read_only_mode"=>false,

    // Maintenance mode, see Lib/maintenance.php. MAINTENANCE_MODE is "off"
    // (the default), "offline" to stop the site with a message, or "silent" to
    // stop it saying nothing at all. MAINTENANCE_UNTIL is when the site is
    // expected back ("2026-09-02 18:00"), driving the countdown on the page.
    // MAINTENANCE_ACCESS_OVERRIDE is a random string that lets an admin
    // through, sent as the X-Access-Override header.
    "maintenance"=>array(
        "mode" => getenv("MAINTENANCE_MODE") ?: "off",
        "message" => getenv("MAINTENANCE_MESSAGE") ?: "HeatpumpMonitor.org is being updated, back shortly",
        "until" => getenv("MAINTENANCE_UNTIL") ?: "",
        "access_override" => getenv("MAINTENANCE_ACCESS_OVERRIDE") ?: ""
    ),

    "admin_emails"=>array(
        // array("email" => "hello@example.com"),
    )
    // "clearkey"=>"",
);
