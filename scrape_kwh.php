<?php
  # check args
  if ($argc != 3) {
    printf("Syntax: php %s <input.tsv> <output.tsv>\n", $argv[0]);
    exit();
  }

  $infile = $argv[1];
  $outfile = $argv[2];

  $input = @fopen($infile, "r");
  if (!$input) {
    printf("Cannot open file: %s\n", $infile);
    exit();
  }

  $header = fgetcsv($input, 1000, "\t");
  $data = array();
  
  # read each row and append kWh readings for last year
  while (($row = fgetcsv($input, 1000, "\t")) !== FALSE) {
    $system = ['id' => count($data) + 1];
    $details = array_combine($header, $row);
    $system['hash'] = hash("crc32b", $details['submitted']);
    $stats = scrapeEnergyValues($details['url']);
    $data[] = array_merge($system, $details, $stats);
    #if (count($data) > 5) {break;}
  }
  
  file_put_contents($outfile, json_encode($data)); // JSON_PRETTY_PRINT
  fclose($input);

  function scrapeEnergyValues($url)
  {
    $config = fetchConfig($url);
    if (!$config) {
      # failed to connect to site
      return [];
    }
    
    # fetch meta data for kWh feeds
    $elec_data = fetchFeedMeta($config, $config->heatpump_elec_kwh);
    $heat_data = fetchFeedMeta($config, $config->heatpump_heat_kwh);
    
    # sychronise both feeds on same start and end times
    if ($elec_data->start_time > 0 && $heat_data->start_time > 0) {
      $elec_data->start_time = max($elec_data->start_time, $heat_data->start_time);
      $heat_data->start_time = max($elec_data->start_time, $heat_data->start_time);
      $elec_data->end_time = min($elec_data->end_time, $heat_data->end_time);
      $heat_data->end_time = min($elec_data->end_time, $heat_data->end_time);
    }

    # fetch last values from kWh feeds
    $last_elec = fetchValue($config, $config->heatpump_elec_kwh, $elec_data->end_time);
    $last_heat = fetchValue($config, $config->heatpump_heat_kwh, $heat_data->end_time);

    # fetch last 30 days, or the most available
    $month_ago = strtotime(strftime("%x")) - 2592000; // 30 days before midnight
    $month_ago = max($month_ago, $elec_data->start_time, $heat_data->start_time);
    if (isset($config->start_date)) {
      $month_ago = max($month_ago, $config->start_date);
    }
    
    # fetch values for a month ago (or since start_date)
    $month_elec = $last_elec - fetchValue($config, $config->heatpump_elec_kwh, $month_ago);
    $month_heat = $last_heat - fetchValue($config, $config->heatpump_heat_kwh, $month_ago);
    
    # determine how far back to go
    # either 1 year, start of feed or user configured start
    $year_ago = $elec_data->end_time - 31536000; // whole year before last value
    $start_date = max($year_ago, $elec_data->start_time, $heat_data->start_time);
    if (isset($config->start_date)) {
      $start_date = max($start_date, $config->start_date);
    }
    
    # fetch values for a year ago (or since start_date)
    $year_elec = $last_elec - fetchValue($config, $config->heatpump_elec_kwh, $start_date);
    $year_heat = $last_heat - fetchValue($config, $config->heatpump_heat_kwh, $start_date);

    $values = [
      "month_elec" => $month_elec > 0 ? round($month_elec) : 0,
      "month_heat" => $month_heat > 0 ? round($month_heat) : 0,
      "month_cop"  => $month_heat > 0 ? round($month_heat / $month_elec, 1) : 0,
      "year_elec"  => $year_elec > 0 ? round($year_elec) : 0,
      "year_heat"  => $year_heat > 0 ? round($year_heat) : 0,
      "year_cop"   => $year_heat > 0 ? round($year_heat / $year_elec, 1) : 0,
      "since"      => ($start_date > $year_ago) ? intval($start_date) : 0,
    ];

    $stats = fetchMoreStats($config, $month_ago, $elec_data->end_time);
    if ($stats != null && isset($stats->full_period)) {
      unset($stats->full_period);
    
      # append the other stats
      $values['stats'] = $stats;
    }
    
    # return kWh values and start date (0 means one whole year)
    return $values;
  }

  /* atempts to get the app config from emoncms
   * returns decoded json object plus server url and readkey
   */
  function fetchConfig($url)
  {
    # decode the url to separate out any args
    $url_parts = parse_url($url);
    $server = $url_parts['scheme'] . '://' . $url_parts['host'];
    
    # check if url was to /app/view instead of username
    if (preg_match('/^(.*)\/app\/view$/', $url_parts['path'], $matches)) {
      $getconfig = "$server$matches[1]/app/getconfig";
      $getstats = "$server$matches[1]/app/getstats";
    }
    else {
      $getconfig = $server . $url_parts['path'] . "/app/getconfig";
      $getstats = $server . $url_parts['path'] . "/app/getstats";
    }

    # if url has query string, pull out the readkey
    if (isset($url_parts['query'])) {
      parse_str($url_parts['query'], $url_args);
      if (isset($url_args['readkey'])) {
        $readkey = $url_args['readkey'];
        $getconfig .= '?' . $url_parts['query'];
        $getstats .= '?' . $url_parts['query'];
      }
    }
    
    # attempt to pull the config for the app
    $content = file_get_contents($getconfig);
    if (strncmp($content, '{"app":"myheatpump","config":', 29) === 0) {
      #print "Loaded config from $getconfig\n";
      $config = json_decode($content)->config;
    }
    else {
      # fall-back: try pulling config out of html instead
      $content = file_get_contents($url);
      if (preg_match('/^config.db = ({.*});/m', $content, $matches)) {
        #print "Scraped config from $url\n";
        $config = json_decode($matches[1]);
      }
    }
    
    # add server and apikey values
    if (isset($config)) {
      $config->server = $server;
      $config->getstats = $getstats;
      if (isset($readkey)) {
        $config->apikey = $readkey;
      }
      if (!isset($config->heatpump_elec_kwh)) { $config->heatpump_elec_kwh = 0; }
      if (!isset($config->heatpump_heat_kwh)) { $config->heatpump_heat_kwh = 0; }
      if (isset($readkey) && $readkey=='4024d6f31ff9de4e30eb00744590bdc5') {
        $config->start_date = 1674736200;
      }
      return $config;
    }
    
    print "Could not load config for $url\n";
    return false;
  }
  
  /* fetch value from a feed at a specific unixtime 
   * returns float
   */
  function fetchValue($config, $feed, $time)
  {
    if ($feed == 0) { return 0; }

    $url = sprintf("%s/feed/value.json?id=%d&time=%d", $config->server, $feed, $time);
    if (isset($config->apikey)) {
      $url .= "&apikey=" . $config->apikey;
    }
    $data = file_get_contents($url);
    return floatval($data);
  }
  
  /* fetch the meta data for a feed
   * returns decoded json object
   */
  function fetchFeedMeta($config, $feed)
  {
    if ($feed == 0) { return json_decode('{"start_time":0,"end_time":0}'); }

    $url = sprintf("%s/feed/getmeta.json?id=%d", $config->server, $feed);
    if (isset($config->apikey)) {
      $url .= "&apikey=" . $config->apikey;
    }
    $data = file_get_contents($url);
    return json_decode($data);
  }
  
  function fetchMoreStats($config, $start, $end)
  {
    $url = sprintf("%s&start=%d&end=%d", $config->getstats, $start, $end);
    $data = file_get_contents($url);
    return json_decode($data);
  }

?>
