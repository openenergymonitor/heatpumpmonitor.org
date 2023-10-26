<?php
$dir = dirname(__FILE__);
chdir("$dir/www");

require "Lib/load_database.php";

require("Modules/user/user_model.php");
$user = new User($mysqli);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

// tabs
print "location\theatpump output\theatpump model\theat loss survey\theat demand\tdiv 2.9\n";

$data = $system->list_admin();
foreach ($data as $row) {
    $userid = (int) $row->userid;
    //if ($userid!=26) continue;
    if ($user_data = $user->get($userid)) {

        $url = $row->url;

        # decode the url to separate out any args
        $url_parts = parse_url($url);
        $server = $url_parts['scheme'] . '://' . $url_parts['host'];

        # check if url was to /app/view instead of username
        if (preg_match('/^(.*)\/app\/view$/', $url_parts['path'], $matches)) {
            $url = "$server$matches[1]/app/getconfig";
        } else {
            $url = $server.$url_parts['path']."/app/getconfig";
        }

        # if url has query string, pull out the readkey
        $readkey = false;
        if (isset($url_parts['query'])) {
            parse_str($url_parts['query'], $url_args);
            if (isset($url_args['readkey'])) {
                $readkey = $url_args['readkey'];
                $url .= '?' . $url_parts['query'];
            }
        }

        $result = json_decode(file_get_contents($url));
        if (isset($result->config->heatpump_heat_kwh)) {
            $feedid = $result->config->heatpump_heat_kwh;

            if (isset($result->config->heatpump_outsideT)) {
                $outside_temp_feedid = $result->config->heatpump_outsideT;
            } else {
                $outside_temp_feedid = false;
            }

            $end = time();
            // 1 year ago
            $start = $end - 365 * 24 * 60 * 60;
            // floor to nearest hour
            $start = floor($start / 3600) * 3600;
            $end = floor($end / 3600) * 3600;

            

            $apikeystr = "";
            if ($readkey) $apikeystr = "&apikey=$readkey";

            // get start date of feed
            $result = file_get_contents("$server/feed/getmeta.json?id=$feedid$apikeystr");
            $result = json_decode($result);

            $date = new DateTime();
            $date->setTimezone(new DateTimeZone('Europe/London'));
            $date->setTimestamp($result->start_time);
            $start_date = $date->format('d M Y');

            $result = file_get_contents("$server/feed/data.json?id=$feedid&start=$start&end=$end&skipmissing=0&limitinterval=1&average=0&delta=1&interval=3600$apikeystr");
            $hourly_heat = json_decode($result);
            
            
            $result = file_get_contents("$server/feed/data.json?id=$outside_temp_feedid&start=$start&end=$end&skipmissing=0&limitinterval=1&average=1&delta=0&interval=3600$apikeystr");
            $hourly_outsideT = json_decode($result);

            // if null continue
            if ($hourly_outsideT == null) continue;
            if ($hourly_heat == null) continue;

            // if not array continue
            if (!is_array($hourly_heat)) continue;
            if (!is_array($hourly_outsideT)) continue;

            if (count($hourly_heat) != 8761) continue;
            if (count($hourly_outsideT) != 8761) continue;

            // Create array of 4 hour periods with heat demand and outside temp
            $result = [];
            
            // Move through array 4 blocks at a time
            for ($i=0; $i<count($hourly_heat)-4; $i+=4) {
                $heat = 0;
                $outsideT = 0;
                $n = 0;
                for ($j=0; $j<4; $j++) {
                    if ($hourly_heat[$i+$j][1]!==null && $hourly_outsideT[$i+$j][1]!==null) {
                        $heat += $hourly_heat[$i+$j][1]*1000;
                        $outsideT += $hourly_outsideT[$i+$j][1];
                        $n++;
                    }
                }
                if ($n) {
                    $result[] = [$hourly_heat[$i][0], $heat/$n, $outsideT/$n];
                }
            }

            // Sort result by row 3 (outside temp)
            usort($result, function($a, $b) {
                return $a[2] - $b[2];
            });

            // Find maximum heat output for each 0.5 C outside temp
            $max_output = [];
            foreach ($result as $row2) {
                $outsideT = "".(floor($row2[2]*2)/2);
                if (!isset($max_output[$outsideT])) {
                    $max_output[$outsideT] = $row2[1];
                } else {
                    $max_output[$outsideT] = max($max_output[$outsideT], $row2[1]);
                }
            }


            // Find maxium heat output below 1 degrees
            $max_heat_below_1 = 0;
            $temperature_below_1 = 0;

            foreach ($max_output as $outsideT => $output) {
                $outsideT = (float) $outsideT;
                if ($outsideT<1) {
                    if ($output>$max_heat_below_1) {
                        $max_heat_below_1 = $output;
                        $temperature_below_1 = $outsideT;
                    }
                }
            }

            $csv = [];
            $csv[] = $row->location;
            $csv[] = $row->hp_output;
            $csv[] = $row->hp_model;
            $csv[] = $row->heat_loss;
            $csv[] = $row->heat_demand;
            $csv[] = number_format($row->heat_demand/2900,1);
            $csv[] = $temperature_below_1;
            $csv[] = number_format($max_heat_below_1*0.001,3);

            // print "$temperature_below_1\t$max_heat_below_1\n";

            /*
            // print output
            foreach ($result as $row) {
                // only if heat is more than 2000
                if ($row[1]>2000)
                    print date('d M Y H:i', $row[0]*0.001)."\t$row[1]\t$row[2]\n";
            }

            print count($result)."\n";*/
            print implode("\t", $csv). "\n";
            //die;

            // print max_output
            // $fh = fopen("max_4h/data/$userid.csv", "w");
            // foreach ($max_output as $outsideT => $output) {
                // print "$outsideT\t$output\n";
                // fputcsv($fh, [$outsideT, $output]);
            // }
            // fclose($fh);
            
            
        }
        //die;
    }
}
