<?php
  # TODO: pass filenames as args

  $input = fopen("sheet.tsv", "r");
  $output = fopen("www/data.tsv", "w");
  
  # append new columns to header
  $header = trim(fgets($input));
  fputs($output, $header . "\tyear_elec\tyear_heat\n");
  
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
    
    $fd = fopen($url, "r");
    if (!$fd) {
      # failed to connect to site
      return [ '-', '-' ];
    }
    
    # scrape the config info from the HTML
    while (($line = fgets($fd)) !== false) {
      #if (preg_match('/^var apikey = "(.*)";/', $line, $matches)) {
      #  $apikey = $matches[1];
      #}
      if (preg_match('/^config.db = ({.*});/', $line, $matches)) {
        $config = json_decode($matches[1]);
      }
    }
    fclose($fd);
    
    if (!isset($config->heatpump_elec_kwh) || !isset($config->heatpump_heat_kwh)) {
      # failed to read config
      return [ '-', '-' ];
    }
    
    # fetch latest time and value for kWh feeds
    $elec_data = fetchTimeValue($site, $config->heatpump_elec_kwh);
    $heat_data = fetchTimeValue($site, $config->heatpump_heat_kwh);

    # fetch values for 1 week ago  
    #$weekago = $elec_data->time - 7 * 24 * 3600;
    #$week_elec = fetchValue($site, $config->heatpump_elec_kwh, $weekago);
    #$week_heat = fetchValue($site, $config->heatpump_heat_kwh, $weekago);
    
    # fetch values for a year ago
    $yearago = $elec_data->time - 31536000;
    $year_elec = fetchValue($site, $config->heatpump_elec_kwh, $yearago);
    $year_heat = fetchValue($site, $config->heatpump_heat_kwh, $yearago);

    # return kWh values
    return [ "elec" => round($elec_data->value - $year_elec),
             "heat" => round($heat_data->value - $year_heat) ];
  }

  function fetchValue($site, $feed, $time)
  {
    $url = "$site/feed/value.json?id=%d&time=%d";
    #if ($apikey != '') { $url .= "&apikey=$apikey"; }
    $url = sprintf($url, $feed, $time);
    $data = file_get_contents($url);
    return floatval($data);
  }

  function fetchTimeValue($site, $feed)
  {
    $url = "$site/feed/timevalue.json?id=%d";
    #if ($apikey != '') { $url .= "&apikey=$apikey"; }
    $url = sprintf($url, $feed);
    $data = file_get_contents($url);
    return json_decode($data);
  }

?>
