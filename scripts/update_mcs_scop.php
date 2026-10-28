<?php

// Refresh the MCS product details and SCOP tables stored on heatpump_model
// (mcs_* columns) from the MCS product directory, for every model with a
// product chosen. With --map, first apply a mapping file of chosen products:
// a CSV with columns model_id, product_id, search (the certification or model
// number to find the product by).
//
// php scripts/update_mcs_scop.php [--map scripts/mcs_product_map.csv]
//
// Then run scripts/update_design_estimates.php to update the system estimates.

define('EMONCMS_EXEC', 1);
$dir = dirname(__FILE__);
$map_file = false;
$i = array_search('--map', $argv);
if ($i !== false && isset($argv[$i + 1])) $map_file = realpath($argv[$i + 1]);
chdir("$dir/../www");
require "core.php";

require "Lib/load_database.php";

require "Modules/manufacturer/manufacturer_model.php";
$manufacturer_model = new Manufacturer($mysqli);

require "Modules/heatpump/heatpump_model.php";
$heatpump_model = new Heatpump($mysqli, $manufacturer_model);

require "Modules/heatpump/mcs_directory.php";
$mcs = new McsDirectory();

function apply_product($heatpump_model, $mcs, $model_id, $product_id, $term)
{
    $product = $mcs->find($product_id, $term);
    if ($product === false) {
        print "$model_id: could not read the MCS directory\n";
        return false;
    }
    if ($product === null) {
        print "$model_id: product $product_id not found searching \"$term\"\n";
        return false;
    }
    $result = $heatpump_model->set_mcs_product($model_id, $product);
    print "$model_id: " . $product['model_number'] . " (" . $product['certification_number'] . "), "
        . count($product['scop']) . " SCOP values, " . $result['message'] . "\n";
    return $result['success'];
}

if ($map_file) {
    if (!$fh = fopen($map_file, 'r')) die("Can't open $map_file\n");
    $header = fgetcsv($fh, 0, ",", "\"", "");
    $count = 0;
    while (($row = fgetcsv($fh, 0, ",", "\"", "")) !== false) {
        if (count($row) < count($header)) continue;
        $row = array_combine($header, array_slice($row, 0, count($header)));
        if (apply_product($heatpump_model, $mcs, (int) $row['model_id'], (int) $row['product_id'], $row['search'])) $count++;
    }
    fclose($fh);
    print "Applied $count mapping(s) from $map_file\n";
    exit;
}

$count = 0;
foreach ($heatpump_model->get_mcs_linked() as $model) {
    // Search by certification number, then model number in case it was reissued
    foreach (array($model['mcs_certification_number'], $model['mcs_model_number']) as $term) {
        if (!$term) continue;
        if (apply_product($heatpump_model, $mcs, (int) $model['id'], (int) $model['mcs_product_id'], $term)) {
            $count++;
            break;
        }
    }
}
print "Refreshed $count model(s)\n";
