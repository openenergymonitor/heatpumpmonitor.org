#!/usr/bin/env php
<?php
// Idempotency probe for load_emoncms_testdata. Exits 0 when the target user already has feeds AND
// a myheatpump app with the configured name (so run.sh can short-circuit), otherwise exits 1.
//
// Env (same defaults as configure_hpm_app.php):
//   TESTDATASET_EMONCMS_USER_ID (default 1)
//   HPM_APP_NAME (default "My Heatpump")
//   EMONCMS_MYSQL_HOST/PORT/USER/PASSWORD/DATABASE (falls back to MYSQL_*)

function env_default(string $name, string $default): string {
    $v = getenv($name);
    return ($v === false || $v === '') ? $default : $v;
}

$userid   = (int) env_default('TESTDATASET_EMONCMS_USER_ID', '1');
$app_name = env_default('HPM_APP_NAME', 'My Heatpump');

$host = env_default('EMONCMS_MYSQL_HOST', env_default('MYSQL_HOST', 'db'));
$port = (int) env_default('EMONCMS_MYSQL_PORT', env_default('MYSQL_PORT', '3306'));
$user = env_default('EMONCMS_MYSQL_USER', env_default('MYSQL_USER', 'emoncms'));
$pass = env_default('EMONCMS_MYSQL_PASSWORD', env_default('MYSQL_PASSWORD', 'emoncms'));
$db   = env_default('EMONCMS_MYSQL_DATABASE', env_default('MYSQL_DATABASE', 'emoncms'));

mysqli_report(MYSQLI_REPORT_OFF);
$m = @new mysqli($host, $user, $pass, $db, $port);
if ($m->connect_errno) {
    fwrite(STDERR, "check_loaded: emoncms DB connect failed: ".$m->connect_error."\n");
    exit(1);
}

// Both `feeds` and `app` are created by emoncms's own schema bootstrap (sql_ready.sh). If either is
// missing, treat as "not loaded" (exit 1) so run.sh proceeds with the full import.
foreach (['feeds', 'app'] as $tbl) {
    $r = $m->query("SHOW TABLES LIKE '$tbl'");
    if (!$r || $r->num_rows === 0) {
        exit(1);
    }
}

$stmt = $m->prepare("SELECT COUNT(*) FROM feeds WHERE userid=?");
$stmt->bind_param('i', $userid);
$stmt->execute();
$stmt->bind_result($feed_count);
$stmt->fetch();
$stmt->close();

$stmt = $m->prepare("SELECT COUNT(*) FROM app WHERE userid=? AND app='myheatpump' AND name=?");
$stmt->bind_param('is', $userid, $app_name);
$stmt->execute();
$stmt->bind_result($app_count);
$stmt->fetch();
$stmt->close();

echo "check_loaded: userid=$userid feeds=$feed_count myheatpump_apps[\"$app_name\"]=$app_count\n";

if ($feed_count > 0 && $app_count > 0) {
    exit(0);
}
exit(1);
