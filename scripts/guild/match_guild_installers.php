<?php
// Cross reference Guild of Master Heat Engineers installers (betateach.co.uk/c/map)
// with installer names and websites on heatpumpmonitor
// Usage: php match_guild_installers.php [installers.json] [--save] [--include-partial]
//   fetches the live list if no file given
//   --save applies the guild badge to systems matched by website or exact name (dry run otherwise)
//   --include-partial also applies it to partial name matches
define('EMONCMS_EXEC', 1);

$save = in_array('--save', $argv);
$include_partial = in_array('--include-partial', $argv);
$file = false;
foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '--') !== 0) $file = $arg;
}

$guild_url = "https://guild-of-master-heat-engineers-production.up.railway.app/api/installers";
$json = $file ? file_get_contents($file) : file_get_contents($guild_url);
$guild = json_decode($json);
if (!is_array($guild)) {
    print "Could not load guild installer list\n";
    exit(1);
}

$dir = dirname(__FILE__);
chdir("$dir/../../www");

require "Lib/load_database.php";

function normalise_domain($url) {
    $url = strtolower(trim((string) $url));
    if ($url == '') return '';
    if (strpos($url, '://') === false) $url = "http://".$url;
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return '';
    return preg_replace('/^www\./', '', $host);
}

function normalise_name($name) {
    $name = strtolower((string) $name);
    $name = str_replace('&', ' and ', $name);
    $name = preg_replace('/[^a-z0-9 ]/', ' ', $name);
    $name = preg_replace('/\b(ltd|limited|llp|plc|uk|co|the)\b/', ' ', $name);
    return trim(preg_replace('/\s+/', ' ', $name));
}

// Group heatpumpmonitor systems by installer
$installers = array();
$result = $mysqli->query("SELECT id, userid, installer_name, installer_url, guild FROM system_meta WHERE installer_name != '' OR installer_url != ''");
while ($row = $result->fetch_object()) {
    $key = normalise_name($row->installer_name)."|".normalise_domain($row->installer_url);
    if (!isset($installers[$key])) {
        $installers[$key] = array(
            'name' => $row->installer_name,
            'url' => $row->installer_url,
            'n_name' => normalise_name($row->installer_name),
            'domain' => normalise_domain($row->installer_url),
            'systems' => array(),
            'userids' => array(),
            'guild' => 0
        );
    }
    $installers[$key]['systems'][] = (int) $row->id;
    $installers[$key]['userids'][(int) $row->userid] = true;
    $installers[$key]['guild'] += (int) $row->guild;
}

$matched = 0;
$apply_systems = array();
foreach ($guild as $g) {
    if (!empty($g->test)) continue;
    $g_name = normalise_name($g->name);
    $g_domain = normalise_domain($g->website ?? '');

    $matches = array();
    foreach ($installers as $i) {
        $how = false;
        if ($g_domain != '' && $g_domain == $i['domain']) {
            $how = "website";
        } else if ($g_name != '' && $i['n_name'] != '') {
            if ($g_name == $i['n_name']) $how = "name";
            else if (strlen($i['n_name']) >= 5 && strlen($g_name) >= 5
                && (strpos($g_name, $i['n_name']) !== false || strpos($i['n_name'], $g_name) !== false)) $how = "partial name";
        }
        if ($how) $matches[] = array($how, $i);
    }

    if (count($matches)) {
        $matched++;
        print $g->name." (".($g->area ?? '').") ".($g->website ?? '')."\n";
        foreach ($matches as $m) {
            list($how, $i) = $m;
            print "    [$how] ".$i['name']." ".$i['url']."\n";
            print "        systems: ".implode(",", $i['systems'])." userids: ".implode(",", array_keys($i['userids']))." guild set: ".$i['guild']."/".count($i['systems'])."\n";
            if ($how != "partial name" || $include_partial) {
                foreach ($i['systems'] as $systemid) $apply_systems[$systemid] = true;
            }
        }
    }
}

print "\n$matched of ".count($guild)." guild installers matched to heatpumpmonitor installers\n";

$apply_systems = array_keys($apply_systems);
print count($apply_systems)." systems to apply guild badge to".($include_partial ? "" : " (excluding partial name matches)")."\n";

if (!$save) {
    print "Dry run, use --save to apply\n";
    exit(0);
}

if (count($apply_systems)) {
    $mysqli->query("UPDATE system_meta SET guild = 1 WHERE id IN (".implode(",", $apply_systems).")");
    print "Updated ".$mysqli->affected_rows." systems\n";
}
