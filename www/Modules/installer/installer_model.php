<?php

class Installer
{
    private $mysqli;
    private $default_color = "#ccc"; // Default color for installers without a logo

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function get_list()
    {
        // Example implementation, replace with actual logic
        $result = $this->mysqli->query("SELECT * FROM installer");
        $installers = [];
        while ($row = $result->fetch_object()) {
            $installers[] = $row;
        }
        return $installers;
    }

    public function populate()
    {
        // Load all systems from system_meta
        $result = $this->mysqli->query("SELECT * FROM system_meta WHERE installer_name IS NOT NULL");
        $installers = [];

        while ($row = $result->fetch_object()) {
            $installer_name = trim($row->installer_name);
            if (!isset($installers[$installer_name])) {

                $logo_path = '/home/oem/hpmon_main/www/theme/img/installers/' . $row->installer_logo;
                $logo_color = $this->get_dominant_color($logo_path);

                $installers[$installer_name] = [
                    'name' => $installer_name,
                    'url' => $row->installer_url ?? '',
                    'logo' => $row->installer_logo ?? '',
                    'color' => $logo_color,
                    'systems' => 0
                ];
            }
            $installers[$installer_name]['systems']++;
        }

        // Sort installers by name
        usort($installers, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        // Truncate the installer table
        $this->mysqli->query("TRUNCATE TABLE installer");

        // Populate the installer table with new installers (if not already present)
        $check_stmt = $this->mysqli->prepare("SELECT COUNT(*) FROM installer WHERE name = ?");
        $insert_stmt = $this->mysqli->prepare("INSERT INTO installer (name, url, logo, color, systems) VALUES (?, ?, ?, ?, ?)");

        foreach ($installers as $installer) {
            $check_stmt->bind_param("s", $installer['name']);
            $check_stmt->execute();
            $check_stmt->bind_result($count);
            $check_stmt->fetch();
            $check_stmt->reset();

            if ($count == 0) {
                $insert_stmt->bind_param("ssssi", 
                    $installer['name'], 
                    $installer['url'], 
                    $installer['logo'], 
                    $installer['color'], 
                    $installer['systems']
                );
                $insert_stmt->execute();
            }
        }

        $check_stmt->close();
        $insert_stmt->close();

        return $installers;
    }

    public function find_duplicate_urls_with_different_names()
    {
        $query = "
            SELECT installer_url, GROUP_CONCAT(DISTINCT installer_name) AS names, COUNT(DISTINCT installer_name) AS name_count
            FROM system_meta
            WHERE installer_url IS NOT NULL AND installer_name IS NOT NULL
            GROUP BY installer_url
            HAVING name_count > 1
        ";
        $result = $this->mysqli->query($query);
        $duplicates = [];
        while ($row = $result->fetch_assoc()) {
            $duplicates[] = $row;
        }
        return $duplicates;
    }

    private function get_dominant_color($image_path) {
        if (!file_exists($image_path) || is_dir($image_path)) return $this->default_color;
        $info = getimagesize($image_path);

        if (!$info) return $this->default_color;
        switch ($info[2]) {
            case IMAGETYPE_JPEG: $img = imagecreatefromjpeg($image_path); break;
            case IMAGETYPE_PNG:  $img = imagecreatefrompng($image_path);  break;
            case IMAGETYPE_GIF:  $img = imagecreatefromgif($image_path);  break;
            default: return $this->default_color;
        }
        $w = imagesx($img); $h = imagesy($img);
        $small = imagecreatetruecolor(16, 16);
        imagecopyresampled($small, $img, 0, 0, 0, 0, 16, 16, $w, $h);
        $colors = [];
        for ($x = 0; $x < 16; $x++) {
            for ($y = 0; $y < 16; $y++) {
                $rgb = imagecolorat($small, $x, $y);
                $c = imagecolorsforindex($small, $rgb);
                // Skip near-black or near-white
                if (($c['red'] < 30 && $c['green'] < 30 && $c['blue'] < 30) ||
                    ($c['red'] > 225 && $c['green'] > 225 && $c['blue'] > 225)) continue;
                $hex = sprintf("#%02x%02x%02x", $c['red'], $c['green'], $c['blue']);
                if (!isset($colors[$hex])) $colors[$hex] = 0;
                $colors[$hex]++;
            }
        }
        imagedestroy($img); imagedestroy($small);
        if (!$colors) return $this->default_color;
        arsort($colors);
        return key($colors);
    }
}