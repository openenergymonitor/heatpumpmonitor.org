<?php

$dir = dirname(__FILE__);
chdir("$dir/www");

require "Lib/load_database.php";
require "Modules/user/user_model.php";
require "Modules/system/system_model.php";

$user = new User($mysqli, false);
$system = new System($mysqli);

// Output table headers
echo "location\theatpump output\theatpump model\theat loss survey\theat demand\tdiv 2.9\ttemp below 1°C\tmax heat below 1°C\n";

// Retrieve all system data
$data = $system->list_admin();

foreach ($data as $row) {
    $userid = (int) $row->userid;

    // Retrieve user data for the current system
    if ($user_data = $user->get($userid)) {
        $url = buildConfigUrl($row->url);
        $result = json_decode(file_get_contents($url));

        if (isset($result->config->heatpump_heat_kwh)) {
            $feedid = $result->config->heatpump_heat_kwh;
            $outside_temp_feedid = $result->config->heatpump_outsideT ?? false;

            list($hourly_heat, $hourly_outsideT) = fetchData($feedid, $outside_temp_feedid, $url);

            if (validateHourlyData($hourly_heat, $hourly_outsideT)) {
                $result = calculateHeatOutput($hourly_heat, $hourly_outsideT);
                $max_output = getMaxHeatPerTemp($result);
                list($max_heat_below_1, $temperature_below_1) = getMaxHeatBelowOne($max_output);

                $csv = [
                    $row->location,
                    $row->hp_output,
                    $row->hp_model,
                    $row->heat_loss,
                    $row->heat_demand,
                    number_format($row->heat_demand / 2900, 1),
                    $temperature_below_1,
                    number_format($max_heat_below_1 * 0.001, 3),
                ];

                echo implode("\t", $csv) . "\n";
            }
        }
    }
}

// Functions

/**
 * Build the configuration URL based on the system's URL.
 */
function buildConfigUrl($url)
{
    $url_parts = parse_url($url);
    $server = $url_parts['scheme'] . '://' . $url_parts['host'];

    if (preg_match('/^(.*)\/app\/view$/', $url_parts['path'], $matches)) {
        $url = "$server$matches[1]/app/getconfig";
    } else {
        $url = $server . $url_parts['path'] . "/app/getconfig";
    }

    if (isset($url_parts['query'])) {
        $url .= '?' . $url_parts['query'];
    }

    return $url;
}

/**
 * Fetch heat and outside temperature data.
 */
function fetchData($feedid, $outside_temp_feedid, $server_url)
{
    $end = time();
    $start = $end - 365 * 24 * 60 * 60;
    $start = floor($start / 3600) * 3600;
    $end = floor($end / 3600) * 3600;

    $hourly_heat = json_decode(file_get_contents("$server_url/feed/data.json?id=$feedid&start=$start&end=$end&interval=3600&delta=1"));
    $hourly_outsideT = json_decode(file_get_contents("$server_url/feed/data.json?id=$outside_temp_feedid&start=$start&end=$end&interval=3600&average=1"));

    return [$hourly_heat, $hourly_outsideT];
}

/**
 * Validate hourly data for heat and temperature.
 */
function validateHourlyData($hourly_heat, $hourly_outsideT)
{
    return is_array($hourly_heat) && is_array($hourly_outsideT) && count($hourly_heat) === 8761 && count($hourly_outsideT) === 8761;
}

/**
 * Calculate heat output and outside temperature averages for 4-hour periods.
 */
function calculateHeatOutput($hourly_heat, $hourly_outsideT)
{
    $result = [];
    for ($i = 0; $i < count($hourly_heat) - 4; $i += 4) {
        $heat = $outsideT = $n = 0;
        for ($j = 0; $j < 4; $j++) {
            if ($hourly_heat[$i + $j][1] !== null && $hourly_outsideT[$i + $j][1] !== null) {
                $heat += $hourly_heat[$i + $j][1] * 1000;
                $outsideT += $hourly_outsideT[$i + $j][1];
                $n++;
            }
        }
        if ($n) {
            $result[] = [$hourly_heat[$i][0], $heat / $n, $outsideT / $n];
        }
    }
    usort($result, fn($a, $b) => $a[2] <=> $b[2]);
    return $result;
}

/**
 * Find the maximum heat output for each temperature.
 */
function getMaxHeatPerTemp($result)
{
    $max_output = [];
    foreach ($result as $row) {
        $outsideT = "" . (floor($row[2] * 2) / 2);
        $max_output[$outsideT] = isset($max_output[$outsideT]) ? max($max_output[$outsideT], $row[1]) : $row[1];
    }
    return $max_output;
}

/**
 * Get maximum heat output below 1°C.
 */
function getMaxHeatBelowOne($max_output)
{
    $max_heat = $temp = 0;
    foreach ($max_output as $outsideT => $output) {
        if ((float)$outsideT < 1 && $output > $max_heat) {
            $max_heat = $output;
            $temp = $outsideT;
        }
    }
    return [$max_heat, $temp];
}
