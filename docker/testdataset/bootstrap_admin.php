#!/usr/bin/env php
<?php
// Idempotent: ensure an emoncms user exists for TESTDATASET_EMONCMS_USER_ID before the testdata
// import runs. add_feeds_to_account.php and configure_hpm_app.php both look this user up by id.
//
// When userid=1 we create username='admin' / password='admin' to match dev_env/load_dev_env_data.php
// (which then sees the user already exists and won't overwrite the apikeys created here). Other
// ids get a generic username/password and a low admin flag.
//
// Env (defaults match configure_hpm_app.php):
//   TESTDATASET_EMONCMS_USER_ID (default 1)
//   EMONCMS_MYSQL_HOST/PORT/USER/PASSWORD/DATABASE (falls back to MYSQL_*)

function env_default(string $name, string $default): string {
    $v = getenv($name);
    return ($v === false || $v === '') ? $default : $v;
}

$userid = (int) env_default('TESTDATASET_EMONCMS_USER_ID', '1');
if ($userid === 1) {
    $username = 'admin';
    $email    = 'admin@example.com';
    $password = 'admin';
    $admin    = 1;
} else {
    $username = 'user'.$userid;
    $email    = $username.'@example.com';
    $password = 'password';
    $admin    = 0;
}

$host = env_default('EMONCMS_MYSQL_HOST', env_default('MYSQL_HOST', 'db'));
$port = (int) env_default('EMONCMS_MYSQL_PORT', env_default('MYSQL_PORT', '3306'));
$user = env_default('EMONCMS_MYSQL_USER', env_default('MYSQL_USER', 'emoncms'));
$pass = env_default('EMONCMS_MYSQL_PASSWORD', env_default('MYSQL_PASSWORD', 'emoncms'));
$db   = env_default('EMONCMS_MYSQL_DATABASE', env_default('MYSQL_DATABASE', 'emoncms'));

mysqli_report(MYSQLI_REPORT_OFF);
$m = @new mysqli($host, $user, $pass, $db, $port);
if ($m->connect_errno) {
    fwrite(STDERR, "bootstrap_admin: emoncms DB connect failed: ".$m->connect_error."\n");
    exit(1);
}
$m->set_charset('utf8mb4');

// Idempotency: skip if either the id OR username slot is already populated. The dev-env loader
// uses a SELECT-by-username guard, so pre-existing usernames must not be re-created here either.
$stmt = $m->prepare("SELECT id, username FROM users WHERE id=? OR username=?");
$stmt->bind_param('is', $userid, $username);
$stmt->execute();
$res = $stmt->get_result();
if ($existing = $res->fetch_assoc()) {
    echo "bootstrap_admin: user already exists (id={$existing['id']}, username={$existing['username']}); leaving as-is\n";
    $stmt->close();
    exit(0);
}
$stmt->close();

function gen_key(int $bytes): string {
    return bin2hex(random_bytes($bytes));
}
function uuid_v4(): string {
    $b = random_bytes(16);
    $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
    $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

// Same hash as www/Modules/user/user_model.php::login (sha256(salt . sha256(password))).
$salt        = gen_key(8);
$passhash    = hash('sha256', $salt . hash('sha256', $password));
$apikey_read = gen_key(16);
$apikey_write= gen_key(16);
$uuid        = uuid_v4();
$timezone    = 'Europe/London';
$access      = 2;
$lastactive  = time();
$verified    = 1;

$stmt = $m->prepare(
    "INSERT INTO users (id, username, password, email, salt, apikey_read, apikey_write, timezone, uuid, admin, access, lastactive, email_verified) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
);
if (!$stmt) {
    fwrite(STDERR, "bootstrap_admin: prepare failed: ".$m->error."\n");
    exit(1);
}
$stmt->bind_param(
    'issssssssiiii',
    $userid, $username, $passhash, $email, $salt,
    $apikey_read, $apikey_write, $timezone, $uuid,
    $admin, $access, $lastactive, $verified
);
if (!$stmt->execute()) {
    fwrite(STDERR, "bootstrap_admin: insert failed: ".$m->error."\n");
    exit(1);
}
$stmt->close();

echo "bootstrap_admin: created user id=$userid username=$username (password=$password, admin=$admin)\n";
