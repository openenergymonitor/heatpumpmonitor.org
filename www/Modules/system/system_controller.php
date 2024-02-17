<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function system_controller() {

    global $session, $route, $system, $mysqli, $system_stats, $settings;



    if ($route->action=="new") {
        $route->format = "html";
        if ($session['userid']) {
            $system_data = $system->new();
            return view("Modules/system/system_view.php", array(
                "mode"=>"edit", 
                "system_data"=>$system_data, 
                'admin'=>$session['admin'], 
                'schema'=>$system->schema_meta,
                'system_stats_monthly'=>$system_stats->schema['system_stats_monthly_v2']
            ));
        }
    }

    if ($route->action=="edit") {
        $route->format = "html";
        if ($session['userid']) {
            $systemid = get("id",false);
            $system_data = $system->get($session['userid'],$systemid);
            return view("Modules/system/system_view.php", array(
                "mode"=>"edit", 
                "system_data"=>$system_data, 
                'admin'=>$session['admin'], 
                'schema'=>$system->schema_meta,
                'system_stats_monthly'=>$system_stats->schema['system_stats_monthly_v2']
            ));
        }
    }

    if ($route->action=="view") {
        $route->format = "html";
        $systemid = get("id",false);
        $system_data = $system->get($session['userid'],$systemid);
        return view("Modules/system/system_view.php", array(
            "mode"=>"view", 
            "system_data"=>$system_data, 
            'admin'=>$session['admin'], 
            'schema'=>$system->schema_meta,
            'system_stats_monthly'=>$system_stats->schema['system_stats_monthly_v2']
        ));
    }

    if ($route->action=="list") {
        if ($route->format=="html") {
            // Public list view
            if ($route->subaction=="public" && $settings['public_mode_enabled']) {
                return view("Modules/system/system_list.php",array(
                    "mode"=>"public",
                    "systems"=>$system->list_public($session['userid']),
                    "columns"=>$system->get_columns(),
                    "stats_columns"=>$system_stats->schema['system_stats_monthly_v2']
                ));

            // User list view
            } else if ($route->subaction=="user") {
                if ($session['userid']) {
                    return view("Modules/system/system_list.php",array(
                        "mode" => "user",
                        "systems"=>$system->list_user($session['userid']),
                        "columns"=>$system->get_columns(),
                        "stats_columns"=>$system_stats->schema['system_stats_monthly_v2']
                    ));
                }

            // Admin list view
            } else if ($route->subaction=="admin") {
                if ($session['userid'] && $session['admin']) {
                    return view("Modules/system/system_list.php",array(
                        "mode" => "admin",
                        "systems"=>$system->list_admin(),
                        "columns"=>$system->get_columns(),
                        "stats_columns"=>$system_stats->schema['system_stats_monthly_v2']
                    ));
                }
            // Original
            } else if ($route->subaction=="original" && $settings['public_mode_enabled']) {
                return view("Modules/system/original/main.php",array());
            }
        } else {
            // Public list view
            if ($route->subaction=="public") {
                return $system->list_public($session['userid']);

            // User list view
            } else if ($route->subaction=="user") {
                if ($session['userid']) {
                    return $system->list_user($session['userid']);
                }

            // Admin list view
            } else if ($route->subaction=="admin") {
                if ($session['userid'] && $session['admin']) {
                    return $system->list_admin();
                }
            }
        }
    }

    // Return system stats
    if ($route->action=="stats") {
        $route->format = "json";
        
        $system_id = false;
        if (isset($_GET['id'])) {
            $system_id = (int) $_GET['id'];
        }

        // stats/last7
        if ($route->subaction == "last7") { 
            return $system_stats->get_last7($system_id);

        // stats/last30
        } else if ($route->subaction == "last30") { 
            return $system_stats->get_last30($system_id);

        // stats/last365
        } else if ($route->subaction == "last365") {
            return $system_stats->get_last365($system_id);

        // stats/all
        } else if ($route->subaction == "all") {
            return $system_stats->get_all($system_id);

        // stats?start=2016-01-01&end=2016-01-02
        } else if ($route->subaction == "") {
            return $system_stats->get_monthly(
                get('start',true),
                get('end',true),
                $system_id
            );
        }
    }

    if ($route->action=="monthly") {
        $route->format = "json";
        return $system_stats->system_get_monthly(get('id',true));
    }

    if ($route->action=="get") {
        $route->format = "json";
        if ($session['userid']) {
            $systemid = get("id",false);
            return $system->get($session['userid'],$systemid);
        }    
    }

    if ($route->action=="save") {
        $route->format = "json";
        if ($session['userid']) {
            $input = json_decode(file_get_contents('php://input'));
            return $system->save($session['userid'],$input->id,$input->data);
        }
    }

    if ($route->action=="delete") {
        $route->format = "json";
        if ($session['userid']) {
            $systemid = get("id",false);
            return $system->delete($session['userid'],$systemid);
        }
    }

    if ($route->action=="loadstats") {
        $route->format = "json";
        if ($session['userid']) {

            $systemid = get("id",false);
            $system_data = $system->get($session['userid'],$systemid);

            $result = $system_stats->load_from_url($system_data->url);
            if (isset($result['success']) && $result['success']) {
                if ($system->has_access($session['userid'], $systemid)) {
                    $system_stats->save_last30($systemid, $result['stats']); 
                    // $system_stats->save_last365($systemid, $result['stats']);
                    return array('success'=>true, 'message'=>'stats loaded');
                } else {
                    return array('success'=>false, 'message'=>'access denied');
                }
            } else {
                return array('success'=>false, 'message'=>'error loading stats');
            }
        }
    }

    if ($route->action=="load_daily_stats") {
        $route->format = "text";
        if ($session['userid']) {
            $systemid = get("id",false);

            // check access
            if (!$system->has_access($session['userid'], $systemid)) {
                return array('success'=>false, 'message'=>'access denied');
            }

            // get system data
            $system_data = $system->get($session['userid'],$systemid);

            // get data period
            $result = $system_stats->get_data_period($system_data->url);
            if (!$result['success']) {
                return array('success'=>false, 'message'=>'error loading data period');
            }

            $start = $result['period']->start;
            $data_end = $result['period']->end;

            // get most recent entry in db
            $result = $mysqli->query("SELECT MAX(timestamp) AS timestamp FROM system_stats_daily WHERE `id`='$systemid'");
            $row = $result->fetch_assoc();
            if ($row['timestamp']>$start) {
                $start = $row['timestamp'];
            }

            // datatime get midnight
            $date = new DateTime();
            $date->setTimezone(new DateTimeZone("Europe/London"));
            $date->setTimestamp($start);
            $date->modify("midnight");
            $start = $date->getTimestamp();
            // +30 days
            $date->modify("+160 days");
            $end = $date->getTimestamp();
            if ($end>$data_end) {
                $end = $data_end;
            }

            $result = $system_stats->load_from_url($system_data->url, $start, $end, 'getdaily');
            // split csv into array, first line is header
            $csv = explode("\n", $result);
            $fields = str_getcsv($csv[0]);

            // for each line, split into array
            for ($i=1; $i<count($csv); $i++) {
                if ($csv[$i]) {
                    $values = str_getcsv($csv[$i]);

                    $row = array();
                    for ($j=0; $j<count($fields); $j++) {
                        $row[$fields[$j]] = $values[$j];
                    }
                    $system_stats->save_day($systemid, $row);
                }
            }
        }
    }

    if ($route->action=="loadmonthlystats") {
        $route->format = "json";
        if ($session['userid']) {

            $systemid = get("id",false);

            if ($system->has_access($session['userid'], $systemid)) {

                $system_data = $system->get($session['userid'],$systemid);

                // timestamp start of July
                $date = new DateTime();
                // set timezone Europe/London
                $date->setTimezone(new DateTimeZone('Europe/London'));
                $date->setDate(2022, 6, 1);
                $date->setTime(0, 0, 0);
                $start = $date->getTimestamp();

                while (true) {
                    // +1 month
                    $date->modify('+1 month');
                    $end = $date->getTimestamp();

                    $stats = $system_stats->load_from_url($system_data->url,$start,$end);
                    if (isset($stats['success']) && !$stats['success']) {
                        break;
                    }
                    $system_stats->save_monthly($systemid,$start,$stats['stats']);
                    if ($end>time()) break;

                    $start = $end;
                }
                return array('success'=>true, 'message'=>'monthly stats loaded');
            } else {
                return array('success'=>false, 'message'=>'access denied');
            }
        }
    }

    return false;
}
