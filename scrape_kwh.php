<?php
  # check args
  if ($argc != 3) {
    printf("Syntax: php %s <input.tsv> <output.tsv>\n", $argv[0]);
    exit();
  }

  $input = @fopen($argv[1], "r");
  if (!$input) {
    printf("Cannot open file: %s\n", $argv[1]);
    exit();
  }

  $output = @fopen($argv[2], "w");
  if (!$output) {
    printf("Cannot open file: %s\n", $argv[2]);
    exit();
  }
  
  # append new columns to header
  $header = trim(fgets($input));
  fputs($output, $header . "\tyear_elec\tyear_heat\tsince\tmonth_elec\tmonth_heat\n");
  
  # read each row and append kWh readings for last year
  while (($fields = fgetcsv($input, 1000, "\t")) !== FALSE) {
    $kwh = scrapeEnergyValues($fields[10]);
    $fields = array_merge($fields, $kwh);
    fputs($output, implode("\t", $fields)."\n");
  }
  fclose($input);
  fclose($output);

  function scrapeEnergyValues($url)
  {
    $config = fetchConfig($url);
    if (!$config) {
      # failed to connect to site
      return [ '-', '-', '-', '-', '-' ];
    }
    
    # fetch meta data for kWh feeds
    $elec_data = fetchFeedMeta($config, $config->heatpump_elec_kwh);
    $heat_data = fetchFeedMeta($config, $config->heatpump_heat_kwh);
    
    # sychronise both feed on same start and end times
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
    $month_elec = fetchValue($config, $config->heatpump_elec_kwh, $month_ago);
    $month_heat = fetchValue($config, $config->heatpump_heat_kwh, $month_ago);
    
    # determine how far back to go
    # either 1 year, start of feed or user configured start
    $year_ago = $elec_data->end_time - 31536000; // whole year before last value
    $start_date = max($year_ago, $elec_data->start_time, $heat_data->start_time);
    if (isset($config->start_date)) {
      $start_date = max($start_date, $config->start_date);
    }
    
    # fetch values for a year ago (or since start_date)
    $year_elec = fetchValue($config, $config->heatpump_elec_kwh, $start_date);
    $year_heat = fetchValue($config, $config->heatpump_heat_kwh, $start_date);

    # return kWh values and start date (0 means one whole year)
    return [
      "year_elec" => ($last_elec > $year_elec) ? round($last_elec - $year_elec) : '-',
      "year_heat" => ($last_heat > $year_heat) ? round($last_heat - $year_heat) : '-',
      "since"     => ($start_date > $year_ago) ? $start_date : 0,
      "month_elec" => ($last_elec > $month_elec) ? round($last_elec - $month_elec) : '-',
      "month_heat" => ($last_heat > $month_heat) ? round($last_heat - $month_heat) : '-'
    ];
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
    if ($url_parts['path'] == '/app/view') {
      $getconfig = "$server/app/getconfig";
    }
    else {
      $getconfig = $server . $url_parts['path'] . "/app/getconfig";
    }

    # if url has query string, pull out the readkey
    if (isset($url_parts['query'])) {
      parse_str($url_parts['query'], $url_args);
      if (isset($url_args['readkey'])) {
        $readkey = $url_args['readkey'];
        $getconfig .= '?' . $url_parts['query'];
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
      if (isset($readkey)) {
        $config->apikey = $readkey;
      }
      if (!isset($config->heatpump_elec_kwh)) { $config->heatpump_elec_kwh = 0; }
      if (!isset($config->heatpump_heat_kwh)) { $config->heatpump_heat_kwh = 0; }
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

?>
