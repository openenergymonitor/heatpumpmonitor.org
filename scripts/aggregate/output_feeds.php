<?php
// Output feeds on the remote emoncms that the aggregate is posted to.
//
// Each dataset is a tag, every dataset has the same feed names. The existing
// all-systems feeds on emoncms.org are tagged "live".

$output_feed_names = array(
    "elec" => "aggregated_electric_consumption",
    "elec_kw_nrml" => "elec_sum_kw_nrml",
    "heat" => "heat_sum",
    "heat_kw_nrml" => "heat_sum_kw_nrml",
    "capacity" => "capacity_sum",
    "outsideT" => "outsideT",
    "numsys" => "number_of_systems"
);

// Feed ids by series for $tag, creating any missing feeds as PHPFina at $interval.
// Returns an error message instead if a feed can't be found or created, so that
// nothing is posted to a partial set.
function resolve_output_feeds($server, $apikey, $tag, $names, $interval) {
    $list = json_decode(http_request2("GET", "$server/feed/list.json", array(), 30, $apikey));
    if (!is_array($list)) return "could not list feeds on $server";

    $ids = array();
    foreach ($names as $series => $name) {
        $found = array();
        foreach ($list as $f) {
            if ((string) $f->tag === $tag && (string) $f->name === $name) $found[] = $f;
        }
        if (count($found) > 1) {
            return "more than one feed tagged '$tag' named '$name': ".implode(", ", array_column($found, "id"));
        }
        if (count($found) == 1) {
            if ((int) $found[0]->engine != 5) return "feed $tag:$name (".$found[0]->id.") is not PHPFina";
            $ids[$series] = (int) $found[0]->id;
            continue;
        }

        $result = json_decode(http_request2("GET", "$server/feed/create.json", array(
            "tag" => $tag, "name" => $name, "engine" => 5,
            "options" => json_encode(array("interval" => $interval))
        ), 30, $apikey));
        if (empty($result->success) || empty($result->feedid)) {
            return "could not create feed $tag:$name: ".(isset($result->message) ? $result->message : "no response");
        }
        $ids[$series] = (int) $result->feedid;
        echo "Created feed $tag:$name (".$ids[$series].")\n";
    }
    return $ids;
}

// Post [[time,value],...] to a feed, retrying; true once emoncms reports success
function post_feed_data($server, $apikey, $feedid, $data, $attempts = 3) {
    for ($a = 1; $a <= $attempts; $a++) {
        $result = json_decode(http_request2("POST", "$server/feed/post.json?id=$feedid",
            array("data" => json_encode($data), "apikey" => $apikey), 60));
        if (!empty($result->success)) return true;
        if ($a < $attempts) sleep(5 * $a);
    }
    return false;
}

// Write a fixed interval series (values at $start + i*$interval, NaN = missing)
// into a PHPFina feed with feed/upload.json, which writes the block straight into
// the data file. On emoncms.org feed/post.json instead queues every datapoint
// individually through the same queue as everyone's live data.
//
// upload.json writes at (start - feed start_time) / interval without checks, so
// the block is lined up here: data before the feed's start is dropped (a negative
// position would overwrite the start of the file) and a gap after the feed's
// current end is filled with NaN (it would otherwise read back as zeros).
//
// Returns array("ok" => blocks written, "blocks" => total, "failed" => [[from, to], ...],
// "skipped" => points dropped before the feed's start), or an error string.
function upload_feed_data($server, $apikey, $feedid, $start, $interval, $values, $block = 43200, $attempts = 3) {
    $meta = json_decode(http_request2("GET", "$server/feed/getmeta.json", array("id" => $feedid), 30, $apikey));
    if (!isset($meta->interval) || !isset($meta->start_time) || !isset($meta->npoints)) return "could not read meta for feed $feedid";
    if ((int) $meta->interval != $interval) return "feed $feedid interval is ".$meta->interval."s, expected {$interval}s";

    $skipped = 0;
    if ((int) $meta->npoints > 0 || (int) $meta->start_time > 0) {
        $feed_start = (int) $meta->start_time;
        if (($start - $feed_start) % $interval != 0) return "feed $feedid start_time $feed_start not aligned with $start";
        if ($start < $feed_start) {
            $skipped = min(count($values), intdiv($feed_start - $start, $interval));
            $values = array_slice($values, $skipped);
            $start = $feed_start;
        }
        $pos = intdiv($start - $feed_start, $interval);
        $npoints = (int) $meta->npoints;
        if ($pos > $npoints) {
            $values = array_merge(array_fill(0, $pos - $npoints, NAN), $values);
            $start = $feed_start + $npoints * $interval;
        }
    }

    $result = array("ok" => 0, "blocks" => 0, "failed" => array(), "skipped" => $skipped);
    // blocks are written in order, so each one starts at or before the end of the file
    for ($b = 0; $b < count($values); $b += $block) {
        $chunk = array_slice($values, $b, $block);
        $t = $start + $b * $interval;
        $body = pack("g*", ...$chunk);
        $result["blocks"]++;
        $done = false;
        for ($a = 1; $a <= $attempts && !$done; $a++) {
            $resp = http_request2("POST", "$server/feed/upload.json?".http_build_query(array(
                "id" => $feedid, "start" => $t, "interval" => $interval, "npoints" => count($chunk))), $body, 60, $apikey);
            $done = trim((string) $resp) === "true";
            if (!$done && $a < $attempts) sleep(5 * $a);
        }
        if ($done) $result["ok"]++;
        else $result["failed"][] = array($t, $t + count($chunk) * $interval);
    }
    return $result;
}

// $data: array of query/form fields, or a string sent as a raw binary body.
// $apikey, when given, is sent as an Authorization: Bearer header.
function http_request2($method, $url, $data, $timeout = 5, $apikey = false)
{
    $options = array();
    if ($method=="GET") {
        $urlencoded = http_build_query($data);
        $url = "$url?$urlencoded";
    } elseif ($method=="POST") {
        $options[CURLOPT_POST] = 1;
        $options[CURLOPT_POSTFIELDS] = is_string($data) ? $data : http_build_query($data);
    }
    $headers = array();
    if (is_string($data)) $headers[] = "Content-Type: application/octet-stream";
    if ($apikey !== false) $headers[] = "Authorization: Bearer $apikey";
    if (count($headers)) $options[CURLOPT_HTTPHEADER] = $headers;
    $options[CURLOPT_URL] = $url;
    $options[CURLOPT_RETURNTRANSFER] = 1;
    $options[CURLOPT_CONNECTTIMEOUT] = 2;
    $options[CURLOPT_TIMEOUT] = $timeout;
    $curl = curl_init();
    curl_setopt_array($curl, $options);
    $resp = curl_exec($curl);
    if (PHP_VERSION_ID < 80000) {
        // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated
        curl_close($curl);
    }
    return $resp;
}
