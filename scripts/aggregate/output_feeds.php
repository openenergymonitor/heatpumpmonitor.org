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
    $list = json_decode(http_request2("GET", "$server/feed/list.json", array("apikey" => $apikey)));
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
            "apikey" => $apikey, "tag" => $tag, "name" => $name, "engine" => 5,
            "options" => json_encode(array("interval" => $interval))
        )));
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

function http_request2($method, $url, $data, $timeout = 5)
{
    $options = array();
    if ($method=="GET") {
        $urlencoded = http_build_query($data);
        $url = "$url?$urlencoded";
    } elseif ($method=="POST") {
        $options[CURLOPT_POST] = 1;
        $options[CURLOPT_POSTFIELDS] = http_build_query($data);
    }
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
