<?php
$dir = dirname(__FILE__);
chdir("$dir/www");

require "Lib/load_database.php";

require ("Modules/system/system_model.php");
$system = new System($mysqli);

$logos = array();

$data = $system->list_admin();
foreach ($data as $row) {
    $systemid = $row->id;
    
    if ($row->installer_url!='') {
        
        $url = parse_url($row->installer_url);
        if (isset($url['host'])) {
            $host = $url['host'];
            $host_parts = explode(".",$host);
            
            $i = 0;
            if ($host_parts[0] == "www") {
                $i++;
            }
            $imagefile = $host_parts[$i];
            
            // if (!isset($logos[$imagefile])) {
                
                $image = getFaviconContent($url['host']);
                if ($image === false) {
                    $url['host'] = str_replace("www.","",$url['host']);
                    $image = getFaviconContent($url['host']);
                }

                if ($image !== false) {
                
                    // Detect MIME type
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->buffer($image);
                    
                    // Determine file extension based on MIME type
                    switch ($mimeType) {
                        case 'image/jpeg':
                            $extension = '.jpg';
                            break;
                        case 'image/png':
                            $extension = '.png';
                            break;
                        case 'image/gif':
                            $extension = '.gif';
                            break;
                        default:
                            $extension = ''; // Or assume a default extension or handle error
                            break;
                    }
                    
                    $filename = "theme/img/installers/".$imagefile.$extension;
                    
                    $logos[$imagefile] = 1;
                    
                    // Write the image data to the file
                    file_put_contents($filename, $image);
                    
                    $mysqli->query("UPDATE system_meta SET `installer_logo`='".$imagefile.$extension."' WHERE `id`='$systemid'"); 
                } else {
                    print $url['host']."\n";
                }
            // }
        }
    }
}

function getFaviconContent($url) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://t0.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&url=https://$url&size=16");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    $image = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) { // Check if successful response
        return $image;
    } else {
        return false; // Or handle error as needed
    }
}

