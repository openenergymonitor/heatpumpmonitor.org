<?php
$dir = dirname(__FILE__);
if(is_dir("/var/www/heatpumpmonitororg")) {
    chdir("/var/www/heatpumpmonitororg");
} elseif(is_dir("$dir/www")) {
    chdir("$dir/www");
} else {
    die("Error: could not find heatpumpmonitor.org directory");
}

define('EMONCMS_EXEC', 1);
require "core.php";
require "Lib/load_database.php";

require ("Modules/system/system_model.php");
$system = new System($mysqli);

require "Modules/installer/installer_model.php";
$installer_model = new Installer($mysqli);

$fails = array();
$logo_cache = array(); // Cache for installer_url -> logo filename mapping

$installers_img_dir = "theme/img/installers";
if (!file_exists($installers_img_dir)) {
    if (mkdir($installers_img_dir, 0755, true)) {
        chown($installers_img_dir, 'www-data');
        chgrp($installers_img_dir, 'www-data');
        echo "Created directory: $installers_img_dir\n";
    } else {
        echo "Failed to create directory: $installers_img_dir\n";
    }
}

$data = $system->list_admin();
foreach ($data as $row) {
    $systemid = $row->id;
    
    $installer_logo = '';

    if ($row->installer_url!='') {
        // Check if we already have this logo cached
        if (isset($logo_cache[$row->installer_url])) {
            $installer_logo = $logo_cache[$row->installer_url];
            print "cached: ".$installer_logo." for ".$row->installer_url."\n";
        } else {
            // Fetch the installer logo
            $image = $installer_model->fetch_installer_logo($row->installer_url);
            if ($image === false) {
                $fails[] = $row->installer_url;
                continue; // Skip to the next iteration if fetching fails
            }
            // Write the image data to the file
            file_put_contents("$installers_img_dir/".$image['filename'], $image['data']);
            $installer_logo = $image['filename'];
            
            // Cache the result
            $logo_cache[$row->installer_url] = $installer_logo;
            
            print "success: ".$installer_logo."\n";
        }
    }

    // Update the database with the installer logo
    $stmt = $mysqli->prepare("UPDATE system_meta SET `installer_logo`=? WHERE `id`=?");
    $stmt->bind_param("si", $installer_logo, $systemid);
    $stmt->execute();
    $stmt->close();
}

foreach ($fails as $fail) {
    print "fail $fail\n";
}