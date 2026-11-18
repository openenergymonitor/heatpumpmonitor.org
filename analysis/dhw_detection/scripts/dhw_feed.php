<?php

/**
 * Install predicted hot-water flags as a PHPFina feed in each system owner's emoncms account.
 *
 * Run on the emoncms server, after predict.py and export_phpfina.py:
 *
 *   php scripts/dhw_feed.php 748                 # dry run: say what would happen, change nothing
 *   php scripts/dhw_feed.php --apply 748         # create the feed if missing, write its data
 *   php scripts/dhw_feed.php --stage DIR 748     # staged files somewhere other than output/phpfina/
 *
 * For each system it:
 *   1. reads output/phpfina/system_<id>.json and .dat (written by export_phpfina.py) and checks them
 *   2. finds the owner: HPM system_meta.app_id -> emoncms app.userid, and checks that the owner
 *      also owns the system's flowT feed
 *   3. finds the feed by tag and name in that account, or creates it (PHPFina, 30 s)
 *   4. writes .meta and .dat in full (each to a temp file, then renamed over the old one)
 *   5. sets the feed's last time and value in redis, as a post would
 *
 * It refuses to write to a feed that is not PHPFina at 30 s in the owner's account, and refuses to
 * run unless it runs as the owner of the PHPFina data folder, so file ownership stays as it was.
 * Like detect_air_issue.php, it writes the files directly: the data does not go through the
 * feedqueue, so it is not replicated. It can be regenerated from the predictions at any time.
 * The values are predictions, not measurements.
 */

define('EMONCMS_EXEC', 1);

const EMONCMS_DIR = '/var/www/emoncms';
const HPM_DIR = '/opt/openenergymonitor/heatpumpmonitor';
const DEFAULT_STAGE_DIR = HPM_DIR . '/analysis/dhw_detection/output/phpfina';
const FEED_TAG = 'heatpumpmonitor';
const FEED_NAME = 'dhw_predicted';
const INTERVAL = 30;
const USAGE = "usage: php scripts/dhw_feed.php [--apply] [--stage DIR] SYSTEM_ID...\n";

// ------------------------------------------------------------------------------------------------
// Main
// ------------------------------------------------------------------------------------------------

$options = parseArguments($argv);
$apply = $options['apply'];
$stageDir = $options['stageDir'];

echo ($apply ? 'APPLY' : 'DRY RUN (add --apply to make changes)') . "\n";
echo "staged files: $stageDir\n";

$systems = loadSystems($options['systemIds']);

// emoncms, set up as the scripts in /home/oem/scripts do. These must be required at the top
// level: process_settings.php defines $settings as a global.
chdir(EMONCMS_DIR);
require 'process_settings.php';
require 'Lib/EmonLogger.php';
require 'Modules/feed/feed_model.php';

$mysqli = @new mysqli(
    $settings['sql']['server'],
    $settings['sql']['username'],
    $settings['sql']['password'],
    $settings['sql']['database'],
    $settings['sql']['port']
);
if ($mysqli->connect_error) {
    exit("can't connect to the emoncms database\n");
}

$redis = new Redis();
$redis->connect('127.0.0.1');

$feed = new Feed($mysqli, $redis, $settings['feed']);
$dataDir = $settings['feed']['phpfina']['datadir'];

$uid = posix_geteuid();
$dataDirOwner = fileowner($dataDir);
echo "phpfina datadir: $dataDir\n";
echo "running as uid $uid, datadir owned by uid $dataDirOwner\n";
if ($apply && $uid !== $dataDirOwner) {
    exit("ABORT: run as the owner of $dataDir (uid $dataDirOwner), e.g. with sudo -u\n");
}

$failed = 0;
foreach ($options['systemIds'] as $systemId) {
    echo "\nsystem $systemId\n";
    try {
        installSystem($systemId, $systems[$systemId] ?? null, $mysqli, $feed, $stageDir, $dataDir, $apply);
    } catch (RuntimeException $e) {
        echo "  FAILED: {$e->getMessage()}\n";
        $failed++;
    }
}

$succeeded = count($options['systemIds']) - $failed;
echo "\n$succeeded of " . count($options['systemIds']) . ' ok' . ($apply ? '' : ' (dry run, nothing changed)') . "\n";
exit($failed > 0 ? 1 : 0);

// ------------------------------------------------------------------------------------------------
// Functions
// ------------------------------------------------------------------------------------------------

/**
 * @return array{apply: bool, stageDir: string, systemIds: int[]}
 */
function parseArguments(array $argv): array
{
    $apply = false;
    $stageDir = DEFAULT_STAGE_DIR;
    $systemIds = [];

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if ($arg === '--apply') {
            $apply = true;
        } elseif ($arg === '--stage' && isset($argv[$i + 1])) {
            $stageDir = $argv[++$i];
        } elseif (ctype_digit($arg)) {
            $systemIds[] = (int) $arg;
        } else {
            exit(USAGE);
        }
    }

    if (count($systemIds) === 0) {
        exit(USAGE);
    }

    $realStageDir = realpath($stageDir);
    if ($realStageDir === false) {
        exit("no staging folder $stageDir\n");
    }

    return ['apply' => $apply, 'stageDir' => rtrim($realStageDir, '/') . '/', 'systemIds' => $systemIds];
}

/**
 * Read each system's app id and sharing flags from the HPM database.
 *
 * @param int[] $systemIds
 * @return array<int, object> system id => {app_id, share, published}
 */
function loadSystems(array $systemIds): array
{
    // load_database.php reads HPM's settings.php from the current folder and creates $mysqli;
    // inside this function its connections are local and closed on return.
    chdir(HPM_DIR . '/www');
    require 'Lib/load_database.php';

    $systems = [];
    $stmt = $mysqli->prepare('SELECT app_id, share, published FROM system_meta WHERE id = ?');
    foreach ($systemIds as $systemId) {
        $stmt->bind_param('i', $systemId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_object();
        if ($row) {
            $systems[$systemId] = $row;
        }
    }
    $stmt->close();
    $mysqli->close();

    return $systems;
}

/**
 * Check, and with $apply install, one system's predicted hot-water feed.
 *
 * @throws RuntimeException when anything does not check out; nothing is written for that system
 */
function installSystem(
    int $systemId,
    ?object $system,
    mysqli $mysqli,
    Feed $feed,
    string $stageDir,
    string $dataDir,
    bool $apply
): void {
    $staged = readStaged($stageDir, $systemId);
    echo "  staged: $staged->npoints points, " . utc($staged->start_time) . ' to ' . utc($staged->end_time)
        . " UTC, model $staged->model\n";
    echo "  last value $staged->last_value at " . utc($staged->last_time, 'Y-m-d H:i:s') . " UTC\n";

    if ($system === null) {
        throw new RuntimeException('not in system_meta');
    }
    $owner = findOwner($mysqli, (int) $system->app_id);
    echo "  app $owner->appId ($owner->appName), owner userid $owner->userId ($owner->username), "
        . "share=$system->share published=$system->published\n";

    $feedId = findFeed($mysqli, $feed, $owner->userId);

    if (!$apply) {
        $target = $feedId ?? '<new id>';
        if ($feedId === null) {
            echo '  would create feed ' . FEED_TAG . ':' . FEED_NAME . ' (PHPFina, ' . INTERVAL
                . " s) for userid $owner->userId\n";
        }
        $megabytes = round($staged->npoints * 4 / 1e6, 1);
        echo "  would write $staged->npoints points ($megabytes MB) to $dataDir$target.dat and .meta\n";
        echo "  would set redis feed:$target time=$staged->last_time value=$staged->last_value\n";
        return;
    }

    if ($feedId === null) {
        $feedId = createFeed($feed, $owner->userId);
    }
    writeFeed($feed, $dataDir, $feedId, $staged);
}

/**
 * Read and check the files export_phpfina.py staged for a system.
 */
function readStaged(string $stageDir, int $systemId): object
{
    $staged = json_decode((string) @file_get_contents($stageDir . "system_$systemId.json"));
    $datFile = $stageDir . "system_$systemId.dat";

    if (!$staged || !file_exists($datFile)) {
        throw new RuntimeException('no staged files; run export_phpfina.py');
    }
    if ((int) $staged->interval !== INTERVAL) {
        throw new RuntimeException("staged interval $staged->interval, not " . INTERVAL);
    }
    if ($staged->start_time % INTERVAL !== 0) {
        throw new RuntimeException("staged start $staged->start_time not on the " . INTERVAL . ' s grid');
    }

    $expectedBytes = (int) $staged->npoints * 4;
    if (filesize($datFile) !== $expectedBytes) {
        throw new RuntimeException("$datFile holds " . filesize($datFile) . " bytes, expected $expectedBytes");
    }

    $staged->datFile = $datFile;
    return $staged;
}

/**
 * Find the emoncms user who owns a system's app, and check they also own its flowT feed.
 *
 * @return object {appId, appName, userId, username}
 */
function findOwner(mysqli $mysqli, int $appId): object
{
    $stmt = $mysqli->prepare('SELECT userid, app, config FROM app WHERE id = ?');
    $stmt->bind_param('i', $appId);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_object();
    $stmt->close();
    if (!$app) {
        throw new RuntimeException("app $appId not found");
    }
    $userId = (int) $app->userid;

    $stmt = $mysqli->prepare('SELECT username FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_object();
    $stmt->close();

    $config = json_decode($app->config);
    $flowTFeedId = (int) ($config->heatpump_flowT ?? 0);
    if ($flowTFeedId === 0) {
        throw new RuntimeException('app has no heatpump_flowT feed');
    }

    $stmt = $mysqli->prepare('SELECT userid FROM feeds WHERE id = ?');
    $stmt->bind_param('i', $flowTFeedId);
    $stmt->execute();
    $flowTFeed = $stmt->get_result()->fetch_object();
    $stmt->close();
    if (!$flowTFeed || (int) $flowTFeed->userid !== $userId) {
        throw new RuntimeException(
            "flowT feed $flowTFeedId is not owned by userid $userId; not sure whose account this is"
        );
    }

    return (object) [
        'appId' => $appId,
        'appName' => $app->app,
        'userId' => $userId,
        'username' => $user->username ?? '?',
    ];
}

/**
 * Find the user's predicted hot-water feed, and check it is safe to write to.
 *
 * @return int|null the feed id, or null if the user has no such feed yet
 */
function findFeed(mysqli $mysqli, Feed $feed, int $userId): ?int
{
    $feedId = $feed->exists_tag_name($userId, FEED_TAG, FEED_NAME);
    if (!$feedId) {
        return null;
    }
    $feedId = (int) $feedId;

    $stmt = $mysqli->prepare('SELECT engine, server FROM feeds WHERE id = ? AND userid = ?');
    $stmt->bind_param('ii', $feedId, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_object();
    $stmt->close();
    if (!$row || (int) $row->engine !== Engine::PHPFINA) {
        throw new RuntimeException("feed $feedId exists but is not PHPFina");
    }
    if ((int) $row->server !== 0) {
        throw new RuntimeException("feed $feedId is on storage server $row->server, not this one");
    }

    $meta = $feed->get_meta($feedId);
    if ((int) $meta->interval !== INTERVAL) {
        throw new RuntimeException("feed $feedId has interval $meta->interval, not " . INTERVAL);
    }

    echo "  feed $feedId exists (" . FEED_TAG . ':' . FEED_NAME . '), '
        . "now $meta->npoints points from " . utc($meta->start_time) . " UTC\n";
    return $feedId;
}

/**
 * Create the predicted hot-water feed in the user's account.
 */
function createFeed(Feed $feed, int $userId): int
{
    $result = $feed->create($userId, FEED_TAG, FEED_NAME, Engine::PHPFINA, (object) ['interval' => INTERVAL]);
    if (!$result['success']) {
        throw new RuntimeException('create: ' . json_encode($result));
    }
    $feedId = (int) $result['feedid'];

    $meta = $feed->get_meta($feedId);
    if ((int) $meta->interval !== INTERVAL) {
        throw new RuntimeException("created feed $feedId with interval $meta->interval, not " . INTERVAL);
    }

    echo "  created feed $feedId (" . FEED_TAG . ':' . FEED_NAME . ")\n";
    return $feedId;
}

/**
 * Write the staged data and its meta to the feed, check it reads back, then set its last value.
 *
 * Each file is written to a temp file and renamed over the old one, so a reader never sees half of
 * one. The data goes first: until the meta follows, the old start time still applies.
 */
function writeFeed(Feed $feed, string $dataDir, int $feedId, object $staged): void
{
    $base = $dataDir . $feedId;
    $expectedBytes = (int) $staged->npoints * 4;

    if (!copy($staged->datFile, "$base.dat.tmp")) {
        throw new RuntimeException("could not write $base.dat.tmp");
    }
    if (filesize("$base.dat.tmp") !== $expectedBytes) {
        unlink("$base.dat.tmp");
        throw new RuntimeException('short copy');
    }

    $metaBytes = pack('I', 0) . pack('I', 0) . pack('I', INTERVAL) . pack('I', (int) $staged->start_time);
    if (file_put_contents("$base.meta.tmp", $metaBytes) !== 16) {
        unlink("$base.dat.tmp");
        throw new RuntimeException("could not write $base.meta.tmp");
    }

    if (!rename("$base.dat.tmp", "$base.dat") || !rename("$base.meta.tmp", "$base.meta")) {
        throw new RuntimeException('rename failed');
    }

    clearstatcache();
    $meta = $feed->get_meta($feedId);
    if ((int) $meta->npoints !== (int) $staged->npoints || (int) $meta->start_time !== (int) $staged->start_time) {
        throw new RuntimeException("written feed reads back as $meta->npoints points from $meta->start_time");
    }

    if ($staged->last_time !== null) {
        $feed->set_timevalue($feedId, $staged->last_value, $staged->last_time);
    }

    echo "  wrote feed $feedId: $meta->npoints points, "
        . utc($meta->start_time) . ' to ' . utc($meta->end_time) . " UTC\n";
}

function utc(?int $time, string $format = 'Y-m-d H:i'): string
{
    return $time === null ? '-' : gmdate($format, $time);
}
