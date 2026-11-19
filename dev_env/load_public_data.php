<?php

// -------------------------------------------------------------------------------------
// Import public heatpumpmonitor.org data into an existing local dev install, alongside
// a local emoncms (e.g. login as your own emoncms user 2 "test").
//
// Unlike load_dev_env_data.php (aimed at a fresh docker stack) this script:
// - never drops tables and never creates or touches emoncms users
// - leaves locally owned systems alone: a production system whose id is already used
//   by a local system is skipped, together with its stats, photos and monthly data
// - gives imported systems a placeholder owner (PUBLIC_IMPORT_USERID) that has no user
//   row, so they behave as read-only public systems; only admin can edit them
// - can be re-run to refresh: placeholder-owned systems are updated, and ones no longer
//   in the public list are removed
// - copies installer logos, heat pump images and system photos from heatpumpmonitor.org
//   rather than scraping installer websites
//
// Usage (from the repository root):
//   php dev_env/load_public_data.php              systems, models, stats and logos
//   php dev_env/load_public_data.php --photos     ... plus system photos
//   php dev_env/load_public_data.php --monthly    ... plus monthly stats (one request per system, slow)
//   php dev_env/load_public_data.php --monthly=2,51,60
//   php dev_env/load_public_data.php --only=logos,stats   run just these steps
//
// Steps: schema, models, systems, stats, logos, photos, monthly
// -------------------------------------------------------------------------------------

const PUBLIC_IMPORT_USERID = 99999999;
const SOURCE_HOST = "https://heatpumpmonitor.org";

if (PHP_SAPI !== 'cli') die("CLI only\n");

$options = getopt("", ["photos", "monthly::", "only:", "help"]);
if (isset($options['help'])) {
    $lines = file(__FILE__);
    echo implode("", array_slice($lines, 2, 26));
    exit;
}

$steps = ["schema", "models", "systems", "stats", "logos"];
if (isset($options['photos'])) $steps[] = "photos";
if (isset($options['monthly'])) $steps[] = "monthly";
if (isset($options['only'])) {
    $steps = array_map('trim', explode(",", $options['only']));
}
$monthly_ids = false;
if (isset($options['monthly']) && $options['monthly'] !== false && $options['monthly'] !== "") {
    $monthly_ids = array_map('intval', explode(",", $options['monthly']));
}

chdir(dirname(__DIR__) . "/www");
define('EMONCMS_EXEC', 1);
require "Lib/load_database.php";
require "core.php";
require "Lib/dbschemasetup.php";
require "Lib/load_module_schemas.php";

// Guard against running this against production
if (isset($settings['domain']) && stripos($settings['domain'], "heatpumpmonitor.org") !== false) {
    die("Refusing to run: settings domain is ".$settings['domain']."\n");
}

echo "Steps: ".implode(", ", $steps)."\n";

// -------------------------------------------------------------------------------------
// Helpers
// -------------------------------------------------------------------------------------

function http_get($path) {
    $ch = curl_init(SOURCE_HOST . "/" . ltrim($path, "/"));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_USERAGENT => "heatpumpmonitor-dev-env",
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false || $code != 200) return false;
    return $body;
}

function http_get_json($path) {
    $body = http_get($path);
    if ($body === false) return null;
    return json_decode($body, true);
}

/** Column name => type for a table */
function table_columns($mysqli, $table) {
    $cols = [];
    $result = $mysqli->query("SHOW COLUMNS FROM `$table`");
    while ($row = $result->fetch_assoc()) $cols[$row['Field']] = strtolower($row['Type']);
    return $cols;
}

/**
 * INSERT (or upsert) the keys of $row that are columns of $table.
 * Empty strings in non-text columns become NULL; arrays are JSON encoded.
 */
function insert_row($mysqli, $table, $columns, $row, $upsert = false) {
    $fields = [];
    $values = [];
    foreach ($row as $key => $value) {
        if (!isset($columns[$key])) continue;
        if (is_array($value)) $value = json_encode($value);
        if (is_bool($value)) $value = (int) $value;
        $is_text = preg_match('/char|text|json|enum/', $columns[$key]);
        if ($value === "" && !$is_text) $value = null;
        $fields[] = "`$key`";
        $values[] = $value === null ? null : (string) $value;
    }
    if (!count($fields)) return false;

    $sql = "INSERT INTO `$table` (".implode(",", $fields).") VALUES (".implode(",", array_fill(0, count($fields), "?")).")";
    if ($upsert) {
        $sql .= " ON DUPLICATE KEY UPDATE ".implode(",", array_map(fn($f) => "$f=VALUES($f)", $fields));
    }
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        echo "  prepare failed on $table: ".$mysqli->error."\n";
        return false;
    }
    $stmt->bind_param(str_repeat("s", count($values)), ...$values);
    $ok = $stmt->execute();
    if (!$ok) echo "  insert failed on $table: ".$stmt->error."\n";
    $stmt->close();
    return $ok;
}

/**
 * Download a site-relative image path (e.g. theme/img/installers/x.png) into the same
 * local path, if missing. The path must sit under $prefix and look like a plain file.
 */
function download_image($relative_path, $prefix) {
    $relative_path = ltrim($relative_path, "/");
    if (strpos($relative_path, $prefix) !== 0) return "bad";
    $rest = substr($relative_path, strlen($prefix));
    if (!preg_match('#^([0-9]+/)?[A-Za-z0-9._-]+\.(png|jpe?g|gif|webp|svg)$#i', $rest) || strpos($rest, "..") !== false) return "bad";
    if (file_exists($relative_path)) return "exists";

    $data = http_get($relative_path);
    if ($data === false) return "failed";
    $is_svg = preg_match('/\.svg$/i', $relative_path);
    if (!$is_svg && @getimagesizefromstring($data) === false) return "failed";

    $dir = dirname($relative_path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) return "failed";
    if (file_put_contents($relative_path, $data) === false) return "failed";
    @chmod($relative_path, 0644);
    return "downloaded";
}

function tally(&$counts, $result) {
    if (!isset($counts[$result])) $counts[$result] = 0;
    $counts[$result]++;
}

function format_tally($counts) {
    $out = [];
    foreach ($counts as $k => $v) $out[] = "$v $k";
    return count($out) ? implode(", ", $out) : "none";
}

// -------------------------------------------------------------------------------------
// Public system list, and which ids we are allowed to write
// -------------------------------------------------------------------------------------

$systems = http_get_json("system/list/public.json");
if (!is_array($systems)) die("Error: could not load public system list from ".SOURCE_HOST."\n");
echo "- Public system list: ".count($systems)." systems\n";

// Schema first so the ownership query sees the current table
if (in_array("schema", $steps)) {
    $schema = load_module_schemas();
    unset($schema['system_stats_daily']); // lives in the emoncms myheatpump app
    $changes = db_schema_setup($mysqli, $schema, true);
    echo "- Schema: ".count($changes)." changes applied\n";
}

$local_owner = [];
$result = $mysqli->query("SELECT id, userid FROM system_meta");
while ($row = $result->fetch_assoc()) $local_owner[(int) $row['id']] = (int) $row['userid'];

// Ids this script manages: in the public list and not already owned by a local user
$import_ids = [];
$skipped = [];
foreach ($systems as $s) {
    $id = (int) $s['id'];
    if (isset($local_owner[$id]) && $local_owner[$id] !== PUBLIC_IMPORT_USERID) {
        $skipped[] = $id;
        continue;
    }
    $import_ids[$id] = true;
}
if (count($skipped)) {
    echo "- Keeping local systems, skipping production ids: ".implode(",", $skipped)."\n";
}

// -------------------------------------------------------------------------------------
// Manufacturers and heat pump models (production ids kept so links match)
// -------------------------------------------------------------------------------------

if (in_array("models", $steps)) {
    $manufacturers = http_get_json("manufacturer/list.json");
    if (is_array($manufacturers)) {
        $cols = table_columns($mysqli, "manufacturers");
        $n = 0;
        foreach ($manufacturers as $m) if (insert_row($mysqli, "manufacturers", $cols, $m, true)) $n++;
        echo "- Manufacturers: $n\n";
    } else {
        echo "- Manufacturers: could not load\n";
    }

    $models = http_get_json("heatpump/list.json");
    if (is_array($models)) {
        $cols = table_columns($mysqli, "heatpump_model");
        $n = 0;
        $images = [];
        foreach ($models as $hp) {
            if (insert_row($mysqli, "heatpump_model", $cols, $hp, true)) $n++;
            if (!empty($hp['img'])) {
                tally($images, download_image("theme/img/heatpumps/".$hp['img'], "theme/img/heatpumps/"));
            }
            $thumbs = !empty($hp['img_thumbnails']) ? json_decode($hp['img_thumbnails'], true) : [];
            foreach ((array) $thumbs as $t) {
                if (isset($t['url'])) tally($images, download_image($t['url'], "theme/img/heatpumps/"));
            }
        }
        echo "- Heat pump models: $n (images: ".format_tally($images).")\n";
    } else {
        echo "- Heat pump models: could not load\n";
    }
}

// -------------------------------------------------------------------------------------
// Systems
// -------------------------------------------------------------------------------------

if (in_array("systems", $steps)) {
    $cols = table_columns($mysqli, "system_meta");
    unset($cols['userid'], $cols['app_id'], $cols['readkey']);
    $created = 0;
    $updated = 0;
    foreach ($systems as $s) {
        $id = (int) $s['id'];
        if (!isset($import_ids[$id])) continue;
        $s['published'] = 1;
        $exists = isset($local_owner[$id]);
        if (!$exists) {
            $mysqli->query("INSERT INTO system_meta (id, userid) VALUES ($id, ".PUBLIC_IMPORT_USERID.")");
        }
        if (insert_row($mysqli, "system_meta", $cols + ["userid" => "int"], $s + ["userid" => PUBLIC_IMPORT_USERID], true)) {
            $exists ? $updated++ : $created++;
        }
    }

    // Remove placeholder-owned systems that are no longer public
    $removed = 0;
    $result = $mysqli->query("SELECT id FROM system_meta WHERE userid=".PUBLIC_IMPORT_USERID);
    while ($row = $result->fetch_assoc()) {
        $id = (int) $row['id'];
        if (isset($import_ids[$id])) continue;
        $mysqli->query("DELETE FROM system_meta WHERE id=$id");
        foreach (["last7", "last30", "last90", "last365", "all", "monthly"] as $t) {
            $mysqli->query("DELETE FROM system_stats_{$t}_v2 WHERE id=$id");
        }
        $removed++;
    }
    echo "- Systems: $created created, $updated refreshed, $removed removed (owner userid ".PUBLIC_IMPORT_USERID.")\n";
}

// -------------------------------------------------------------------------------------
// Running stats summaries
// -------------------------------------------------------------------------------------

if (in_array("stats", $steps)) {
    foreach (["last7", "last30", "last90", "last365", "all"] as $period) {
        $table = "system_stats_{$period}_v2";
        $stats = http_get_json("system/stats/$period");
        if (!is_array($stats)) {
            echo "- Stats $period: could not load\n";
            continue;
        }
        $cols = table_columns($mysqli, $table);
        $n = 0;
        foreach ($stats as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if (!isset($import_ids[$id])) continue;
            $mysqli->query("DELETE FROM `$table` WHERE id=$id");
            if (insert_row($mysqli, $table, $cols, $row)) $n++;
        }
        echo "- Stats $period: $n systems\n";
    }
}

// -------------------------------------------------------------------------------------
// Installer logos
// -------------------------------------------------------------------------------------

if (in_array("logos", $steps)) {
    $logos = [];
    foreach ($systems as $s) {
        if (isset($import_ids[(int) $s['id']]) && !empty($s['installer_logo'])) $logos[$s['installer_logo']] = true;
    }
    $counts = [];
    foreach (array_keys($logos) as $logo) {
        tally($counts, download_image("theme/img/installers/".$logo, "theme/img/installers/"));
    }
    echo "- Installer logos: ".count($logos)." (".format_tally($counts).")\n";
}

// -------------------------------------------------------------------------------------
// System photos
// -------------------------------------------------------------------------------------

if (in_array("photos", $steps)) {
    require "Modules/system/system_model.php";
    $system_class = new System($mysqli);
    require "Modules/system/system_photos_model.php";
    $system_photos = new SystemPhotos($mysqli, $system_class);

    $counts = [];
    $records = 0;
    foreach ($systems as $s) {
        $id = (int) $s['id'];
        if (!isset($import_ids[$id]) || empty($s['photo_count'])) continue;
        $photos = http_get_json("system/photos?id=$id");
        if (empty($photos['success']) || empty($photos['photos'])) continue;

        $prefix = "theme/img/system/$id/";
        foreach ($photos['photos'] as $photo) {
            if (empty($photo['url'])) continue;
            $r = download_image($photo['url'], $prefix);
            tally($counts, $r);
            if ($r !== "downloaded" && $r !== "exists") continue;
            foreach ((array) ($photo['thumbnails'] ?? []) as $t) {
                if (isset($t['url'])) download_image($t['url'], $prefix);
            }
            $result = $system_photos->save_external_photo($id, $photo['url'], $photo);
            if (!empty($result['success']) && empty($result['existing'])) $records++;
        }
        echo ".";
    }
    echo "\n- Photos: ".format_tally($counts).", $records new records\n";
}

// -------------------------------------------------------------------------------------
// Monthly stats (one request per system)
// -------------------------------------------------------------------------------------

if (in_array("monthly", $steps)) {
    $ids = $monthly_ids !== false ? $monthly_ids : array_keys($import_ids);
    $cols = table_columns($mysqli, "system_stats_monthly_v2");
    $total = 0;
    foreach ($ids as $id) {
        if (!isset($import_ids[$id])) {
            echo "- Monthly $id: skipped (not an imported public system)\n";
            continue;
        }
        $months = http_get_json("system/stats/monthly?id=$id");
        if (!is_array($months)) {
            echo "- Monthly $id: could not load\n";
            continue;
        }
        $mysqli->query("DELETE FROM system_stats_monthly_v2 WHERE id=$id");
        foreach ($months as $row) if (insert_row($mysqli, "system_stats_monthly_v2", $cols, $row)) $total++;
        echo ".";
    }
    echo "\n- Monthly stats: $total rows\n";
}

echo "Done\n";
