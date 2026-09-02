<?php
/*
All HeatpumpMonitor.org code is released under the GNU Affero General Public License.
See COPYRIGHT.txt and LICENSE.txt.
Part of the OpenEnergyMonitor project:
http://openenergymonitor.org
*/

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

/*
 Maintenance mode helpers, modelled on the emoncms.org implementation
 (Lib/maintenance.php there) so the two sites are operated the same way.

 Configured by the "maintenance" entry in settings.php:

     "maintenance"=>array(
         "mode" => "offline",
         "message" => "HeatpumpMonitor.org is being updated, back shortly",
         "until" => "2026-09-02 18:00",
         "access_override" => "<random string>"
     ),

 "until" is when the site is expected back, written the way a person would
 write it while starting the work rather than as a unix timestamp. Anything
 strtotime() understands is accepted, so an absolute time ("2026-09-02 18:00",
 "2 Sep 2026 18:00 UTC") or a relative one ("+2 hours"), though relative times
 are resolved at each page load so they never count down. No timezone in the
 string means the server's PHP timezone, so include one if writing local time.
 A plain integer is still read as unix seconds. Empty, 0 or unparseable means
 no expected time and the countdown falls back to one hour from page load.

 The admin bypass is the X-Access-Override header carrying access_override. It
 has to work without a session: the check runs before any database connection,
 so that maintenance mode still answers while mysql and redis are stopped,
 which is the point of having it. Curl or a browser header extension is enough:

     curl -H "X-Access-Override: <random string>" https://heatpumpmonitor.org/

 Modes:
   off       normal operation
   offline   nothing is accepted. Browsers get offline.html, JSON callers get
             a 503 with a JSON body, everything else a 503 with a plain text
             body. No database is touched.
   silent    offline, but saying nothing about it. Every request gets a 503
             with an empty body and browsers get a blank light page: no
             message, no expected time back, no branding. For a standby or
             restore-in-progress server that should not be announcing that it
             exists or what is being done to it. "message" and "until" are
             ignored in this mode.

 Only the known mode words do anything. Anything else, a typo included, means
 normal operation: settings.php is edited for many other reasons, and an
 accidental site wide outage is a worse failure than a flag that quietly did
 not engage.

 This is the heavy hammer: the whole site stops. For "the site is up but
 nothing can be changed" there is the separate read_only_mode setting.
*/

function maintenance_config($settings)
{
    if (!isset($settings['maintenance']) || !is_array($settings['maintenance'])) return false;
    $c = $settings['maintenance'];

    $mode = isset($c['mode']) ? strtolower(trim((string) $c['mode'])) : 'off';
    if ($mode !== 'offline' && $mode !== 'silent') return false;

    $m = array(
        'mode' => $mode,
        'message' => 'HeatpumpMonitor.org is down for maintenance, please try again shortly',
        'until' => 0,
        'access_override' => ''
    );

    if (isset($c['message']) && $c['message'] !== '') $m['message'] = (string) $c['message'];
    if (isset($c['until'])) $m['until'] = maintenance_until($c['until']);
    if (isset($c['access_override'])) $m['access_override'] = (string) $c['access_override'];

    return $m;
}

// Resolve the "until" setting to unix seconds for the offline.html countdown.
// A bad date is not worth failing the page over: it just means no expected
// time, and the countdown falls back to one hour from load.
function maintenance_until($until)
{
    if (is_int($until) || is_float($until)) return (int) $until;

    $until = trim((string) $until);
    if ($until === '') return 0;

    // A bare number stays unix seconds, as it was before this took strings.
    if (ctype_digit($until)) return (int) $until;

    // Relative strings ("+2 hours") are resolved at each page load, so they
    // never actually count down, every refresh shows the same two hours.
    // Use an absolute time if the number on the page should mean anything.
    $time = strtotime($until);
    if ($time === false) return 0;

    return (int) $time;
}

function maintenance_bypass($m)
{
    if ($m['access_override'] === '') return false;
    if (!isset($_SERVER['HTTP_X_ACCESS_OVERRIDE'])) return false;
    return hash_equals($m['access_override'], (string) $_SERVER['HTTP_X_ACCESS_OVERRIDE']);
}

// The site's own pages are Vue talking to routes that end in .json, and the
// public API is the same routes. Anything that asked for JSON gets JSON, so a
// fetch() in a page left open in a browser sees the message rather than a
// parse error; everything else is treated as a browser navigation.
function maintenance_wants_json()
{
    $q = isset($_GET['q']) ? (string) $_GET['q'] : '';
    if (substr($q, -5) === '.json') return true;

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') return true;

    if (isset($_SERVER['HTTP_ACCEPT']) &&
        strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) return true;

    return false;
}

// Base path for the logo and favicon on the maintenance page, the equivalent
// of core.php's $path but without the protocol and host, and without needing
// core.php, which is not loaded this early. SCRIPT_NAME is set by the web
// server, not the client, and every request is rewritten to index.php, so this
// is "/" at the domain root and "/heatpumpmonitor/" in a sub directory.
function maintenance_path()
{
    $script = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
    return rtrim(str_replace('\\', '/', dirname($script)), '/') . '/';
}

function maintenance_respond($m)
{
    http_response_code(503);
    header('Retry-After: 60');
    header('Cache-Control: no-store');

    // index.php sends the site's security headers, but it never runs during
    // maintenance, so the equivalents are repeated here. The page carries its
    // own style and countdown script inline and loads nothing but the logo and
    // favicon from theme/, so everything else stays denied.
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: "
        ."default-src 'none'; "
        ."style-src 'unsafe-inline'; "
        ."script-src 'unsafe-inline'; "
        ."img-src 'self'; "
        ."base-uri 'none'; "
        ."form-action 'none'; "
        ."frame-ancestors 'self'");

    // Silent mode says nothing at all. Machine callers get a bodyless 503,
    // which every client handles the same way as the offline mode 503: the
    // status is checked before the body. Browsers get a blank page rather than
    // an empty response, so a person who lands on it sees a deliberately blank
    // page and not a browser error.
    if ($m['mode'] === 'silent') {
        if (maintenance_wants_json()) exit();

        header('Content-Type: text/html');
        print "<!doctype html>\n"
            . "<html lang=\"en\">\n"
            . "    <head>\n"
            . "        <meta charset=\"utf-8\">\n"
            . "        <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n"
            . "        <style>html, body { background-color: #fff; margin: 0px; height: 100%; }</style>\n"
            . "    </head>\n"
            . "    <body></body>\n"
            . "</html>\n";
        exit();
    }

    if (maintenance_wants_json()) {
        header('Content-Type: application/json');
        print json_encode(array('success' => false, 'message' => $m['message']));
        exit();
    }

    // offline.html lives next to index.php. Read by absolute path rather than
    // relative, because the CLI scripts chdir into www and this file should not
    // depend on where it was called from.
    $template = @file_get_contents(dirname(__DIR__) . '/offline.html');

    // If the template is missing the site still has to say something, and a
    // blank 503 during maintenance looks like a broken server.
    if ($template === false || $template === '') {
        header('Content-Type: text/plain');
        print $m['message'];
        exit();
    }

    header('Content-Type: text/html');
    print str_replace(
        array('{{message}}', '{{until}}', '{{path}}'),
        array(
            htmlspecialchars($m['message'], ENT_QUOTES, 'UTF-8'),
            (int) $m['until'],
            htmlspecialchars(maintenance_path(), ENT_QUOTES, 'UTF-8')
        ),
        $template
    );
    exit();
}
