<?php

class Installer
{
    private $mysqli;
    private $default_color = "#cccccc"; // Default color for installers without a logo

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Get list of all installers from the database
     * 
     * @return array Array of installer objects
     */
    public function get_list($include_system_count = false)
    {
        // Get installer system count from system_meta
        if ($include_system_count) {
            $installer_systems = $this->get_installers_from_system_meta();
        } else {
            $installer_systems = [];
        }

        // Example implementation, replace with actual logic
        $result = $this->mysqli->query("SELECT * FROM installer");
        $installers = [];
        while ($row = $result->fetch_object()) {
            // Add system count from system_meta
            $row->systems = isset($installer_systems[$row->name]) ? $installer_systems[$row->name] : 0;
            $installers[] = $row;
        }
        return $installers;
    }

    /**
     * Get list of installers and installer count from system_meta
     * 
     * @return array Array of installer names and their system counts
     */
    public function get_installers_from_system_meta()
    {
        $result = $this->mysqli->query("SELECT installer_name FROM system_meta WHERE published = '1' AND share = '1'");
        $installer_systems = [];
        while ($row = $result->fetch_object()) {
            $installer_name = trim($row->installer_name);
            if (!isset($installer_systems[$installer_name])) {
                $installer_systems[$installer_name] = 0;
            }
            $installer_systems[$installer_name]++;
        }
        return $installer_systems;
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
        $result = $this->mysqli->query("SELECT * FROM system_meta WHERE published = '1' AND share = '1'");
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
     * @return bool True if successful, false if installer already exists or insert failed
     */
    public function add($name, $url = '', $color = '')
    {
        // Validate inputs
        $name = trim($name);
        $url = trim($url);
        $color = trim($color);

        // Default color if not provided
        if (empty($color)) {
            $color = $this->default_color;
        }

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

        $stmt = $this->mysqli->prepare("INSERT INTO installer (name, url, logo, color) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $url, $logo, $color);
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
     * @return bool True if successful, false if installer doesn't exist, name conflict, or update failed
     */
    public function edit($id, $name, $url = '', $color = '')
    {
        $id = (int) $id;

        // Validate inputs
        $name = trim($name);
        $url = trim($url);
        $color = trim($color);

        // Default color if not provided
        if (empty($color)) {
            $color = $this->default_color;
        }
        
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

        // Fetch the logo from URL if provided
        $image = $this->fetch_installer_logo($url);
        if ($image === false) {
            return array('success' => false, 'message' => 'Failed to load logo from URL');
        }
        file_put_contents("theme/img/installers/".$image['filename'], $image['data']);
        $logo = $image['filename'];

        $stmt = $this->mysqli->prepare("UPDATE installer SET name = ?, url = ?, logo = ?, color = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $name, $url, $logo, $color, $id);
        $result = $stmt->execute();
        $stmt->close();

        return array('success' => true, 'message' => 'Installer updated successfully');
    }

    /**
     * Delete an installer from the database
     * 
     * @param int $id The installer ID to delete
     * @return bool True if successful, false if installer doesn't exist or delete failed
     */
    public function delete($id)
    {
        $id = (int) $id;
        if ($id <= 0 || !$this->exists_by_id($id)) {
            return array('success' => false, 'message' => 'Installer not found');
        }

        $stmt = $this->mysqli->prepare("DELETE FROM installer WHERE id = ?");
        $stmt->bind_param("i", $id);
        $result = $stmt->execute();
        $stmt->close();

        return array('success' => true, 'message' => 'Installer deleted successfully');
    }

    /**
     * Extract the dominant color from an installer logo image
     * Analyzes the image and returns the most common non-black/white color
     * 
     * @param string $image_path Full path to the image file
     * @return string Hex color code (e.g., "#ff0000") or default color if image invalid
     */
    public function get_dominant_color($image_path) {
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

    public function fetch_installer_logo($url) {
        $url = parse_url($url);
        if (isset($url['host'])) {
            $host = $url['host'];
            $host_parts = explode(".",$host);
            
            $i = 0;
            if ($host_parts[0] == "www") {
                $i++;
            }
            $filename = $host_parts[$i];
                
            $image_data = $this->getFaviconContent($url['host']);
            if ($image_data === false) {
                $url['host'] = str_replace("www.","",$url['host']);
                $image_data = $this->getFaviconContent($url['host']);
            }

            if ($image_data !== false) {
            
                // Detect MIME type
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->buffer($image_data);
                
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
                
                $filename .= $extension;
                return array(
                    'filename' => $filename,
                    'data' => $image_data
                );
            }
        }
        return false;
    }
    private function getFaviconContent($url) {

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
}