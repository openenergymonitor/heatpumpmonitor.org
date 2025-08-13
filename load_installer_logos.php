<?php
$dir = dirname(__FILE__);
chdir("$dir/www");

require "Lib/load_database.php";

require ("Modules/system/system_model.php");
$system = new System($mysqli);

require "Modules/installer/installer_model.php";
$installer_model = new Installer($mysqli);

$fails = array();

$data = $system->list_admin();
foreach ($data as $row) {
    $systemid = $row->id;
    
    $installer_logo = '';

    if ($row->installer_url!='') {
        // Fetch the installer logo
        $image = $installer_model->fetch_installer_logo($row->installer_url);
        if ($image === false) {
            $fails[] = $row->installer_url;
            continue; // Skip to the next iteration if fetching fails
        }
        // Write the image data to the file
        file_put_contents("theme/img/installers/".$image['filename'], $image['data']);
        $installer_logo = $image['filename'];
        print "success: ".$installer_logo."\n";
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