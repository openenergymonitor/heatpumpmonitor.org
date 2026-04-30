#!/usr/bin/env php
<?php
// One-shot: create / update a MyHeatpump app in Emoncms for the target user and map the testdataset
// feeds into the app config by name. The app's postprocess (run by emoncms via post_process.php
// before this) creates the `myheatpump_daily_stats` table on the emoncms DB.
//
// HeatpumpMonitor's system_meta is wired up later by dev_env/load_dev_env_data.php (see step 3c
// "Link system_meta to the EmonCMS myheatpump app"); the HPM schema does not exist yet at this
// point in the bootstrap and load_dev_env_data also owns system_meta seeding.
//
// Env:
//   EMONCMS_HOST (default http://emoncms) — base URL for Emoncms HTTP API
//   EMONCMS_MYSQL_HOST/PORT/USER/PASSWORD/DATABASE — emoncms DB (users, feed, app)
//   TESTDATASET_EMONCMS_USER_ID (default 1) — owner of the feeds/app
//   HPM_APP_NAME (default "My Heatpump")

function env_required(string $name): string {
    $v = getenv($name);
    if ($v === false || $v === '') {
        fwrite(STDERR, "ERROR: $name not set\n");
        exit(1);
    }
    return $v;
}

function env_default(string $name, string $default): string {
    $v = getenv($name);
    return ($v === false || $v === '') ? $default : $v;
}

function connect_db(string $host, int $port, string $user, string $pass, string $db, string $label): mysqli {
    mysqli_report(MYSQLI_REPORT_OFF);
    $m = @new mysqli($host, $user, $pass, $db, $port);
    if ($m->connect_errno) {
        fwrite(STDERR, "ERROR: $label connect failed ($host:$port/$db): ".$m->connect_error."\n");
        exit(1);
    }
    $m->set_charset('utf8mb4');
    return $m;
}

function http_get_json(string $url): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    // No curl_close: CurlHandle is released when $ch goes out of scope; curl_close is deprecated in PHP 8.5+.
    if ($body === false) {
        return ['success' => false, 'http_code' => $code, 'message' => "curl error: $err"];
    }
    $decoded = json_decode($body, true);
    if ($decoded === null && $body !== 'null') {
        return ['success' => false, 'http_code' => $code, 'message' => "non-JSON response: $body"];
    }
    return ['success' => true, 'http_code' => $code, 'data' => $decoded];
}

// -----------------------------------------------------------------------------
// Inputs
// -----------------------------------------------------------------------------
$emoncms_host   = rtrim(env_default('EMONCMS_HOST', 'http://emoncms'), '/');
$userid    = (int) env_default('TESTDATASET_EMONCMS_USER_ID', '1');
$app_name  = env_default('HPM_APP_NAME', 'My Heatpump');

$em_host = env_default('EMONCMS_MYSQL_HOST', env_default('MYSQL_HOST', 'db'));
$em_port = (int) env_default('EMONCMS_MYSQL_PORT', env_default('MYSQL_PORT', '3306'));
$em_user = env_default('EMONCMS_MYSQL_USER', env_default('MYSQL_USER', 'emoncms'));
$em_pass = env_default('EMONCMS_MYSQL_PASSWORD', env_default('MYSQL_PASSWORD', 'emoncms'));
$em_db   = env_default('EMONCMS_MYSQL_DATABASE', env_default('MYSQL_DATABASE', 'emoncms'));

echo "configure_hpm_app: userid=$userid, app_name=\"$app_name\", emoncms_host=$emoncms_host\n";

// -----------------------------------------------------------------------------
// 1. Look up user apikeys + feed list
// -----------------------------------------------------------------------------
$em = connect_db($em_host, $em_port, $em_user, $em_pass, $em_db, 'emoncms');

$stmt = $em->prepare("SELECT username, apikey_read, apikey_write FROM users WHERE id=?");
$stmt->bind_param('i', $userid);
$stmt->execute();
$res = $stmt->get_result();
$user_row = $res->fetch_assoc();
$stmt->close();
if (!$user_row) {
    fwrite(STDERR, "ERROR: emoncms user id=$userid not found (has load_dev_env_data / load_emoncms_testdata run?)\n");
    exit(1);
}
$username      = $user_row['username'];
$apikey_read   = $user_row['apikey_read'];
$apikey_write  = $user_row['apikey_write'];
echo "  emoncms user: $username (id=$userid)\n";

$stmt = $em->prepare("SELECT id, name FROM feeds WHERE userid=?");
$stmt->bind_param('i', $userid);
$stmt->execute();
$res = $stmt->get_result();
$feeds_by_name = [];
while ($row = $res->fetch_assoc()) {
    $feeds_by_name[$row['name']] = (int) $row['id'];
}
$stmt->close();
echo "  feeds in account: ".count($feeds_by_name)."\n";
if (count($feeds_by_name) === 0) {
    fwrite(STDERR, "WARNING: user has no feeds — did load_emoncms_testdata complete? Continuing anyway.\n");
}

// -----------------------------------------------------------------------------
// 2. Create (or reuse) the myheatpump app via emoncms HTTP API
// -----------------------------------------------------------------------------
// First, check if an app with this name already exists for this user (idempotent).
$stmt = $em->prepare("SELECT id FROM app WHERE userid=? AND app='myheatpump' AND name=?");
$stmt->bind_param('is', $userid, $app_name);
$stmt->execute();
$res = $stmt->get_result();
$existing = $res->fetch_assoc();
$stmt->close();

if ($existing) {
    $app_id = (int) $existing['id'];
    echo "  reusing existing myheatpump app id=$app_id\n";
} else {
    $add_url = $emoncms_host.'/app/add.json?apikey='.urlencode($apikey_write)
        .'&app=myheatpump&name='.urlencode($app_name);
    $r = http_get_json($add_url);
    if (!$r['success'] || !isset($r['data']['success']) || !$r['data']['success']) {
        fwrite(STDERR, "ERROR: /app/add.json failed (http=".$r['http_code']."): ".json_encode($r)."\n");
        exit(1);
    }
    $app_id = (int) $r['data']['id'];
    echo "  created myheatpump app id=$app_id\n";
}

// -----------------------------------------------------------------------------
// 3. Build config by matching feed names to myheatpump config keys and push via /app/setconfig.json
// -----------------------------------------------------------------------------
// Keys with "type":"feed" in Modules/app/apps/OpenEnergyMonitor/myheatpump/myheatpump.js.
// Keep in sync if the upstream app grows new feed slots.
$myheatpump_feed_keys = [
    'heatpump_elec', 'heatpump_elec_kwh',
    'heatpump_heat', 'heatpump_heat_kwh',
    'heatpump_flowT', 'heatpump_returnT', 'heatpump_outsideT', 'heatpump_roomT',
    'heatpump_targetT', 'heatpump_flowrate',
    'heatpump_dhw', 'heatpump_ch', 'heatpump_cooling', 'heatpump_error',
    'immersion_elec',
    'heatpump_dhwT', 'heatpump_dhwTargetT',
    'boiler_heat',
];
$config = [];
foreach ($myheatpump_feed_keys as $key) {
    if (isset($feeds_by_name[$key])) {
        // set_config serialises back as JSON; emoncms stores numeric feed ids as strings in older dumps,
        // but modern sanitisation accepts numbers. Use string form to be safe against the alphanumeric filter.
        $config[$key] = (string) $feeds_by_name[$key];
    }
}
$config['app_name'] = $app_name;

echo "  mapped ".(count($config) - 1)." feeds into app config\n";

$config_json = json_encode($config, JSON_UNESCAPED_SLASHES);
$set_url = $emoncms_host.'/app/setconfig.json?apikey='.urlencode($apikey_write)
    .'&id='.$app_id.'&config='.urlencode($config_json);
$r = http_get_json($set_url);
if (!$r['success'] || !isset($r['data']['success']) || !$r['data']['success']) {
    fwrite(STDERR, "ERROR: /app/setconfig.json failed (http=".$r['http_code']."): ".json_encode($r)."\n");
    fwrite(STDERR, "  config payload: $config_json\n");
    exit(1);
}
echo "  app config saved\n";

$em->close();

echo "Done. Emoncms app: $emoncms_host/app/view?id=$app_id&readkey=$apikey_read\n";
echo "     HeatpumpMonitor system_meta linkage will be set by load_dev_env_data.php (step 3c).\n";
