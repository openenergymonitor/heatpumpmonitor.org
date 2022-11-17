<?php
  # TODO: pass filenames as args

  $input = fopen("sheet.tsv", "r");
  $output = fopen("www/data.tsv", "w");
  
  # append new columns to header
  $header = trim(fgets($input));
  fputs($output, $header . "\tyear_elec\tyear_heat\tsince\n");
  
  # read reach row and append kWh readings for last year
  while (($fields = fgetcsv($input, 1000, "\t")) !== FALSE) {
    $kwh = scrapeEnergyValues($fields[10]);
    $fields = array_merge($fields, $kwh);
    fputs($output, implode("\t", $fields)."\n");
  }
  fclose($input);
  fclose($output);

  function scrapeEnergyValues($url)
  {
    $site = dirname($url);
    
    $config = fetchConfig($url);
    if (!$config) {
      # failed to connect to site
      return [ '-', '-' ];
    }
    
    if (!isset($config->heatpump_elec_kwh) || !isset($config->heatpump_heat_kwh)) {
      # failed to read config
      return [ '-', '-' ];
    }
    
    # fetch meta data for kWh feeds
    $elec_data = fetchFeedMeta($site, $config->heatpump_elec_kwh);
    $heat_data = fetchFeedMeta($site, $config->heatpump_heat_kwh);

    # fetch last values from kWh feeds
    $last_elec = fetchValue($site, $config->heatpump_elec_kwh, $elec_data->end_time);
    $last_heat = fetchValue($site, $config->heatpump_heat_kwh, $heat_data->end_time);

    # determine how far back to go
    # either 1 year, start of feed or user configured start
    $year_ago = $elec_data->end_time - 31536000;
    $start_date = max($year_ago, $elec_data->start_time, $heat_data->start_time);
    if (isset($config->start_date)) {
      $start_date = max($start_date, $config->start_date);
    }
    
    # fetch values for a year ago (or since start_date)
    $year_elec = fetchValue($site, $config->heatpump_elec_kwh, $start_date);
    $year_heat = fetchValue($site, $config->heatpump_heat_kwh, $start_date);

    # return kWh values and start date (0 means one whole year)
    return [ "elec" => round($last_elec - $year_elec),
             "heat" => round($last_heat - $year_heat),
             "since" => $start_date > $year_ago ? $start_date : 0];
  }

  /* atempts to get the app config from emoncms
   * returns decoded json object
   */
  function fetchConfig($url)
  {
    # attempt to pull the config for the app
    $config = file_get_contents($url . '/app/getconfig');
    if (strncmp($config, '{"app":"myheatpump","config":', 29) === 0) {
      return json_decode($config)->config;
    }
    
    # fall-back: try pulling config out of html instead
    if (preg_match('/^config.db = ({.*});/m', $config, $matches)) {
      return json_decode($matches[1]);
    }
    
    return false;
  }
  
  /* fetch value from a feed at a specific unixtime 
   * returns float
   */
  function fetchValue($site, $feed, $time)
  {
    $url = sprintf("%s/feed/value.json?id=%d&time=%d", $site, $feed, $time);
    $data = file_get_contents($url);
    return floatval($data);
  }
  
  /* fetch the meta data for a feed
   * returns decoded json object
   */
  function fetchFeedMeta($site, $feed)
  {
    $url = sprintf("%s/feed/getmeta.json?id=%d", $site, $feed);
    $data = file_get_contents($url);
    return json_decode($data);
  }

?>
