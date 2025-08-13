<?php

class Installer
{
    private $mysqli;
    private $default_color = "#ccc"; // Default color for installers without a logo

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Get list of all installers from the database
     * 
     * @return array Array of installer objects
     */
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

    /**
     * Populate the installer table with data from system_meta
     * Extracts installer information and calculates system counts
     * 
     * @return array Array of processed installer data
     */
    public function unmatched()
    {
        // Load all systems from system_meta
        $result = $this->mysqli->query("SELECT * FROM system_meta WHERE installer_name IS NOT NULL");
        $installers = [];

        while ($row = $result->fetch_object()) {
            $installer_name = trim($row->installer_name);
            if (!isset($installers[$installer_name])) {

                $installers[$installer_name] = [
                    'name' => $installer_name,
                    'url' => $row->installer_url ?? '',
                    'logo' => $row->installer_logo ?? '',
                    'systems' => 0
                ];
            }
            $installers[$installer_name]['systems']++;
        }

        // Find unmatched installers
        $unmatched_installers = [];
        foreach ($installers as $installer) {
            if (!$this->exists_by_name($installer['name'])) {
                $unmatched_installers[] = $installer;
            }
        }

        return $unmatched_installers;
    }

    /**
     * Find duplicate installer URLs that have different names
     * 
     * @return array Array of duplicate URL entries with their associated names
     */
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

    /**
     * Check if an installer exists by name
     * 
     * @param string $name The installer name to check
     * @param int|null $exclude_id Optional ID to exclude from the check (for updates)
     * @return bool True if installer exists, false otherwise
     */
    private function exists_by_name($name, $exclude_id = null)
    {
        if ($exclude_id !== null) {
            $stmt = $this->mysqli->prepare("SELECT COUNT(*) FROM installer WHERE name = ? AND id != ?");
            $stmt->bind_param("si", $name, $exclude_id);
        } else {
            $stmt = $this->mysqli->prepare("SELECT COUNT(*) FROM installer WHERE name = ?");
            $stmt->bind_param("s", $name);
        }
        
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        
        return $count > 0;
    }

    /**
     * Check if an installer exists by ID
     * 
     * @param int $id The installer ID to check
     * @return bool True if installer exists, false otherwise
     */
    private function exists_by_id($id)
    {
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) FROM installer WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        
        return $count > 0;
    }

    /**
     * Add a new installer to the database
     * 
     * @param string $name The installer name
     * @param string $url The installer URL (optional)
     * @param string $logo The installer logo filename (optional)
     * @param int $systems The number of systems using this installer (default: 0)
     * @return bool True if successful, false if installer already exists or insert failed
     */
    public function add($name, $url = '', $logo = '', $systems = 0)
    {
        // Name should not be empty
        if (empty($name)) {
            return array('success' => false, 'message' => 'Installer name required');
        }

        if ($this->exists_by_name($name)) {
            return array('success' => false, 'message' => 'Installer already exists with this name');
        }

        // Get color from logo if provided
        $color = $this->default_color;
        if (!empty($logo)) {
            $logo_path = '/home/oem/hpmon_main/www/theme/img/installers/' . $logo;
            $color = $this->get_dominant_color($logo_path);
        }

        $stmt = $this->mysqli->prepare("INSERT INTO installer (name, url, logo, color, systems) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $name, $url, $logo, $color, $systems);
        $result = $stmt->execute();
        $stmt->close();

        return array('success' => true, 'message' => 'Installer added successfully');
    }

    /**
     * Edit an existing installer in the database
     * 
     * @param int $id The installer ID to update
     * @param string $name The new installer name
     * @param string $url The new installer URL (optional)
     * @param string $logo The new installer logo filename (optional)
     * @param int $systems The new number of systems using this installer (default: 0)
     * @return bool True if successful, false if installer doesn't exist, name conflict, or update failed
     */
    public function edit($id, $name, $url = '', $logo = '', $systems = 0)
    {
        $id = (int) $id;

        // ID and name should not be empty
        if ($id <= 0 || empty($name)) {
            return array('success' => false, 'message' => 'ID and name are required');
        }

        if (!$this->exists_by_id($id)) {
            return array('success' => false, 'message' => 'Installer not found');
        }

        if ($this->exists_by_name($name, $id)) {
            return array('success' => false, 'message' => 'Installer already exists with this name');
        }

        // Get color from logo if provided
        $color = $this->default_color;
        if (!empty($logo)) {
            $logo_path = '/home/oem/hpmon_main/www/theme/img/installers/' . $logo;
            $color = $this->get_dominant_color($logo_path);
        }

        $stmt = $this->mysqli->prepare("UPDATE installer SET name = ?, url = ?, logo = ?, color = ?, systems = ? WHERE id = ?");
        $stmt->bind_param("ssssii", $name, $url, $logo, $color, $systems, $id);
        $result = $stmt->execute();
        $stmt->close();

        return array('success' => true, 'message' => 'Installer updated successfully');
    }

    /**
     * Extract the dominant color from an installer logo image
     * Analyzes the image and returns the most common non-black/white color
     * 
     * @param string $image_path Full path to the image file
     * @return string Hex color code (e.g., "#ff0000") or default color if image invalid
     */
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