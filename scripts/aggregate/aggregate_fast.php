<?php
// Fast replacement for scripts/aggregate_v2_last7.php.
//
// This script does the metadata work (system list, app configs, feed engines
// and meta) through the same emoncms models as the original, writes a plan and
// hands the data reading and aggregation to fast_aggregate.py (numpy). Feeds
// that fast_aggregate.py cannot read itself (other engines, virtual feeds
// using other processes) are rendered here with $feed->get_data first.
//
// Default: last 7 days to the nearest hour, posted like the original.
//   php aggregate_fast.php [--post]
//
// Each group of systems is aggregated in the same pass and uploaded to the feeds
// named in output_feeds.php under the group's name as tag, looked up on the
// remote emoncms and created there if missing. --groups picks which ones
// (default "live,mid", all systems and MID metered systems).
//
// Reprocess a longer range, cooling detection and range checks per 7-day window:
//   php aggregate_fast.php --start 2024-11-18 --end 2026-10-26 [--post]
//
// Benchmark against a synthetic dataset (synth/generate.py):
//   php aggregate_fast.php --start 1792540800 --end 1793145600 --dataset DIR

define('EMONCMS_EXEC', 1);
$dir = dirname(__FILE__);

$opts = getopt("", array("start:", "end:", "window:", "post", "upload", "groups:", "workers:", "dataset:", "workdir:", "python:", "quiet", "verbose"));
require "$dir/output_feeds.php";
$interval = 60;
$window_days = isset($opts["window"]) ? (float) $opts["window"] : 7;
// Each run gets its own workdir (removed at the end) so the hourly run and a
// long reprocess can't overwrite each other's plan and output
$keep_workdir = isset($opts["workdir"]);
$workdir = $keep_workdir ? $opts["workdir"] : sys_get_temp_dir()."/hpmon_aggregate_".getmypid()."_".bin2hex(random_bytes(4));
$python = isset($opts["python"]) ? $opts["python"] : "python3";
$workers = isset($opts["workers"]) ? (int) $opts["workers"] : 0;
$quiet = isset($opts["quiet"]);
$verbose = isset($opts["verbose"]);

// Groups of systems to aggregate, by tag of the output feeds
$group_filters = array(
    "live" => function ($row) { return true; },
    "mid" => function ($row) { return !empty($row->mid_metering); }
);
$groups = explode(",", isset($opts["groups"]) ? $opts["groups"] : "live,mid");
foreach ($groups as $g) {
    if (!isset($group_filters[$g])) die("Unknown group '$g', expected one of: ".implode(", ", array_keys($group_filters))."\n");
}
@mkdir($workdir, 0700, true);

// Same limits as aggregate_v2_last7.php
$limits = array("outsideT_min" => -40, "outsideT_max" => 50, "heat_max" => 100000, "elec_max" => 20000);

// Feed processes fast_aggregate.py evaluates itself (VIRTUAL_PROCESSES there)
$virtual_supported = array(53, 55, 56, 57, 58, 2, 3, 24, 25, 63);

$t_begin = microtime(true);
$exit_code = 0;

if (isset($opts["dataset"])) {
    // Synthetic benchmark: system list from manifest.json, feeds/app from its database
    $dataset = rtrim($opts["dataset"], "/");
    $manifest = json_decode(file_get_contents("$dataset/manifest.json"));
    $systems = $manifest->systems;
    $emoncms_mysqli = new mysqli("localhost", "emoncms", "emoncms", $manifest->db, 3306);
    $redis = new Redis();
    $redis->connect("127.0.0.1");
    $redis->select(9);
    $redis->flushDB();
    $phpfina_dir = "$dataset/phpfina/";
    $phptimeseries_dir = "$dataset/phptimeseries/";
    $settings = array('log' => array('enabled' => false));
    // a test --post needs $aggregate_host/$aggregate_apikey, e.g. for a local emoncms
    if (file_exists("$dataset/config.php")) require "$dataset/config.php";
} else {
    require "$dir/../config.php";
    chdir("$dir/../../www");
    require "core.php";
    require "Lib/load_database.php";
    require("Modules/user/user_model.php");
    $user = new User($mysqli,false);
    require("Modules/system/system_model.php");
    $system = new System($mysqli);
    $systems = $system->list_admin();

    $emoncms_mysqli = @new mysqli("localhost", "emoncms", "emoncms", "emonphp", 3306);
    $phpfina_dir = '/var/opt/emoncms/phpfina/';
    $phptimeseries_dir = '/var/opt/emoncms/phptimeseries/';
}

$feed_settings = array(
    'phpfina'       => array('datadir'  => $phpfina_dir),
    'phptimeseries' => array('datadir'  => $phptimeseries_dir),
    'max_datapoints'        => 70000
);
if (isset($opts["dataset"])) $feed_settings['redisbuffer'] = array('enabled' => false);

chdir("/var/www/emoncms");
require_once "Lib/enum.php";
if (!class_exists('EmonLogger')) require_once "Lib/EmonLogger.php";
if (!function_exists('tr')) { function tr($s) { return $s; } }
$log = new EmonLogger(__FILE__);
require_once "Modules/feed/feed_model.php";
$feed = new Feed($emoncms_mysqli,$redis,$feed_settings);

// --------------------------------------------------------------------------------
// Time range
// --------------------------------------------------------------------------------
$date = new DateTime();
$date->setTimezone(new DateTimeZone("Europe/London"));
if (isset($opts["end"])) {
    $end = parse_time($opts["end"], $date);
} else {
    $end = floor($date->getTimestamp() / 3600) * 3600;
}
if (isset($opts["start"])) {
    $start = parse_time($opts["start"], $date);
} else {
    $date->setTimestamp($end);
    $date->modify("-7 days");
    $start = $date->getTimestamp();
}
if (($end - $start) % $interval != 0 || $end <= $start) die("start/end must be whole minutes, end after start\n");
$window = (int) round($window_days * 86400);
// The default range is "-7 days" in Europe/London, 167 or 169 hours across a
// DST change, and is always one window as in the original
if (!isset($opts["start"]) && !isset($opts["window"])) $window = $end - $start;

// --------------------------------------------------------------------------------
// Plan: which feeds each system uses and how to read them
// --------------------------------------------------------------------------------
$plan_feeds = array();
$plan_systems = array();
$count_all = array_fill_keys($groups, 0);
$rendered = array();

foreach ($systems as $row) {
    if (!$row->share) continue;
    if (!$row->published) continue;
    $in_groups = array();
    foreach ($groups as $g) {
        if ($group_filters[$g]($row)) {
            $in_groups[] = $g;
            $count_all[$g]++;
        }
    }
    if (!count($in_groups)) continue;

    $s = array("id" => (int) $row->id, "groups" => $in_groups, "location" => $row->location, "userid" => (int) $row->userid,
               "app_id" => (int) $row->app_id, "hp_output" => 1*$row->hp_output,
               "elec" => null, "heat" => null, "elec_meta" => null, "outsideT" => null);

    $app_config = get_app_config($emoncms_mysqli,$row->app_id);
    if ($app_config && isset($app_config->heatpump_elec) && isset($app_config->heatpump_heat)) {
        $elec_id = (int) $app_config->heatpump_elec;
        $heat_id = (int) $app_config->heatpump_heat;
        if ($feed->exist($elec_id) && $feed->exist($heat_id)) {
            $meta = $feed->get_meta($elec_id);
            $s["elec_meta"] = array(
                "start_time" => isset($meta->start_time) ? (int) $meta->start_time : null,
                "end_time" => isset($meta->end_time) ? (int) $meta->end_time : null
            );
            $s["elec"] = $elec_id;
            $s["heat"] = $heat_id;
            // Only read the data if some window will use it
            $em = $s["elec_meta"];
            if (!($em["start_time"] > $end || $em["end_time"] === null || $em["end_time"] < $start)) {
                plan_feed($elec_id);
                plan_feed($heat_id);
            }
        }
    }
    if ($app_config && isset($app_config->heatpump_outsideT)) {
        $outsideT_id = (int) $app_config->heatpump_outsideT;
        if ($feed->exist($outsideT_id)) {
            $s["outsideT"] = $outsideT_id;
            plan_feed($outsideT_id);
        }
    }
    $plan_systems[] = $s;
}

$plan = array(
    "start" => $start, "end" => $end, "interval" => $interval, "window" => $window, "dp" => 0,
    "groups" => $groups, "limits" => $limits, "phpfina_dir" => $phpfina_dir, "phptimeseries_dir" => $phptimeseries_dir, "count_all" => $count_all,
    "feeds" => (object) $plan_feeds, "systems" => $plan_systems
);
file_put_contents("$workdir/plan.json", json_encode($plan, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));
$t_plan = microtime(true) - $t_begin;

// --------------------------------------------------------------------------------
// Aggregate
// --------------------------------------------------------------------------------
$cmd = escapeshellarg($python)." ".escapeshellarg("$dir/fast_aggregate.py")." ".escapeshellarg("$workdir/plan.json")
     ." --out ".escapeshellarg("$workdir/out").($workers ? " --workers $workers" : "");
passthru($cmd, $rc);
if ($rc != 0) die("fast_aggregate.py failed ($rc)\n");

$summary = json_decode(file_get_contents("$workdir/out/summary.json"));
if (!$quiet) {
    foreach ($count_all as $g => $n) {
        if ($n == 0) echo "No systems found ($g)\n";
        else echo "Total systems ($g): $n\n";
    }

    if (count((array) $summary->flagged)) {
        echo "\nWARNING: systems with implausible data (excluded from aggregate):\n";
        foreach ($summary->flagged as $id => $f) {
            echo "  System $id ".$f->location." userid ".$f->userid." app ".$f->app_id.": ".$f->count." datapoints ";
            echo date("Y-m-d H:i", $f->first)." to ".date("Y-m-d H:i", $f->last).", ";
            echo "max heat ".round($f->max_heat)." W, max elec ".round($f->max_elec)." W\n";
        }
        echo "\n";
    }
}
$t_render = array_sum(array_column($rendered, "seconds"));
fprintf(STDERR, "plan %.2fs (metadata %.2fs, %d feeds rendered by emoncms %.2fs), total %.2fs\n",
    $t_plan, $t_plan - $t_render, count($rendered), $t_render, microtime(true) - $t_begin);
if ($verbose) {
    foreach ($rendered as $r) {
        fprintf(STDERR, "  rendered feed %d engine %s %.2fs %s\n", $r["id"], $r["engine"], $r["seconds"], $r["processList"]);
    }
}

// --post goes through feed/post.json: on emoncms.org every datapoint is queued and
// written individually, and is replicated to the backup server. Right for the
// hourly run, slow for years of data.
// --upload writes blocks straight into the feed files with feed/upload.json, for a
// reprocess. It bypasses replication, so copy the output feeds' files to the backup
// server afterwards.
if (isset($opts["post"]) || isset($opts["upload"])) {
    // $aggregate_host is the post.json url, as for aggregate_v2_last7.php
    $server = preg_replace('#/feed/post\.json$#', '', $aggregate_host);
    $apikey = $aggregate_apikey;
    // Resolve every group's feeds before writing any
    $outputs = array();
    foreach ($groups as $g) {
        $outputs[$g] = resolve_output_feeds($server, $apikey, $g, $output_feed_names, $interval);
        if (!is_array($outputs[$g])) die("Not posted: ".$outputs[$g]."\n");
    }
    $total_dp = ($end - $start) / $interval;
    $failed = array();
    foreach ($outputs as $g => $feeds) {
        foreach ($feeds as $name => $feedid) {
            $label = "$g:".$output_feed_names[$name]." ($feedid)";
            $values = array_values(unpack("e*", file_get_contents("$workdir/out/$g/$name.f64")));

            if (isset($opts["upload"])) {
                $r = upload_feed_data($server, $apikey, $feedid, $start, $interval, $values);
                unset($values);
                if (!is_array($r)) {
                    echo "Feed $label: $r\n";
                    $failed[] = "$label: $r";
                    continue;
                }
                echo "Feed $label: uploaded ".$r["ok"]."/".$r["blocks"]." blocks";
                if ($r["skipped"]) echo ", skipped ".$r["skipped"]." minutes before the feed's start";
                echo "\n";
                foreach ($r["failed"] as $f) $failed[] = "$label ".date("Y-m-d H:i", $f[0])." to ".date("Y-m-d H:i", $f[1]);
                continue;
            }

            $chunk = 7 * 1440;
            $nchunks = (int) ceil($total_dp / $chunk);
            $ok = 0;
            for ($c = 0; $c < $total_dp; $c += $chunk) {
                $data = array();
                for ($i = $c; $i < min($c + $chunk, $total_dp); $i++) {
                    $v = $values[$i];
                    $data[] = array($start + $i * $interval, is_nan($v) ? null : $v);
                }
                if (post_feed_data($server, $apikey, $feedid, $data)) {
                    $ok++;
                } else {
                    $failed[] = "$label ".date("Y-m-d H:i", $start + $c * $interval)
                              ." to ".date("Y-m-d H:i", $start + min($c + $chunk, $total_dp) * $interval);
                }
            }
            echo "Feed $label: posted $ok/$nchunks\n";
            unset($values);
        }
    }
    if (count($failed)) {
        echo "\nERROR: ".count($failed)." writes failed after retries, re-run these:\n  ".implode("\n  ", $failed)."\n";
        $exit_code = 1;
    }
}

if (isset($opts["dataset"])) $redis->flushDB();

if (!$keep_workdir) {
    foreach (array_merge(glob("$workdir/out/*/*"), glob("$workdir/out/*.*"), glob("$workdir/*.*")) as $f) unlink($f);
    foreach (glob("$workdir/out/*", GLOB_ONLYDIR) as $d) rmdir($d);
    @rmdir("$workdir/out");
    rmdir($workdir);
}
exit($exit_code);

// --------------------------------------------------------------------------------

// Add a feed to the plan: PHPFina, PHPTimeSeries and simple virtual feeds are read by
// fast_aggregate.py, anything else is rendered here through emoncms
function plan_feed($feedid) {
    global $feed, $plan_feeds;
    if (isset($plan_feeds[$feedid])) return;

    $engine = (int) $feed->get_field($feedid, "engine");
    if ($engine == Engine::PHPFINA || $engine == Engine::PHPTIMESERIES) {
        $plan_feeds[$feedid] = array("engine" => $engine);
        return;
    }
    if ($engine == Engine::VIRTUALFEED && $sources = virtual_sources($feedid)) {
        $plan_feeds[$feedid] = array("engine" => Engine::VIRTUALFEED, "processList" => $feed->get_processlist($feedid));
        foreach ($sources as $source => $engine) $plan_feeds[$source] = array("engine" => $engine);
        return;
    }
    $plan_feeds[$feedid] = array("engine" => "file", "path" => render_feed($feedid));
}

// Source feeds (id => engine) of a virtual feed if fast_aggregate.py can evaluate it, else false
function virtual_sources($feedid) {
    global $feed, $virtual_supported;
    $process_list = $feed->get_processlist($feedid);
    if (!$process_list) return false;
    $sources = array();
    foreach (explode(",", $process_list) as $i => $pair) {
        $p = explode(":", $pair);
        if (!is_numeric($p[0]) || !in_array((int) $p[0], $virtual_supported)) return false;
        if ($i == 0 && (int) $p[0] != 53) return false;
        if (in_array((int) $p[0], array(53, 55, 56, 57, 58))) {
            $source = (int) $p[1];
            if (!$feed->exist($source)) return false;
            $engine = (int) $feed->get_field($source, "engine");
            if ($engine != Engine::PHPFINA && $engine != Engine::PHPTIMESERIES) return false;
            $sources[$source] = $engine;
        }
    }
    return $sources;
}

// Render a feed exactly as the original script reads it: one request per window,
// as some engines' first datapoint depends on where the request starts
function render_feed($feedid) {
    global $feed, $start, $end, $interval, $window, $workdir, $rendered;
    $t = microtime(true);
    $path = "$workdir/feed_$feedid.f64";
    $fh = fopen($path, "wb");
    $chunk = $window <= 60000 * $interval ? $window : 60000 * $interval;
    for ($cs = $start; $cs < $end; $cs += $chunk) {
        $ce = min($cs + $chunk, $end);
        $n = ($ce - $cs) / $interval + ($ce == $end ? 1 : 0);
        $data = $feed->get_data($feedid,$cs,$ce,$interval,1,"UTC","notime",false,0,0,false,0);
        $bin = "";
        for ($i = 0; $i < $n; $i++) {
            $v = (is_array($data) && !isset($data["success"]) && isset($data[$i])) ? (float) $data[$i] : NAN;
            $bin .= pack("e", $v);
        }
        fwrite($fh, $bin);
    }
    fclose($fh);
    $rendered[] = array("id" => $feedid, "engine" => $feed->get_field($feedid, "engine"),
                        "processList" => (string) $feed->get_processlist($feedid), "seconds" => microtime(true) - $t);
    return $path;
}

function parse_time($s, $date) {
    if (is_numeric($s)) return (int) $s;
    $d = new DateTime($s, $date->getTimezone());
    return $d->getTimestamp();
}

function get_app_config($emoncms_mysqli,$app_id) {
    $app_id = (int) $app_id;
    $result = $emoncms_mysqli->query("SELECT config FROM app WHERE id=".$app_id);
    $row_app = $result->fetch_object();
    if (!isset($row_app->config)) {
        return false;
    }
    return json_decode($row_app->config);
}
