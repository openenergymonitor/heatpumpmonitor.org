<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class Heatpump
{
    private $mysqli;
    private $manufacturer_model;

    public function __construct($mysqli, $manufacturer_model)
    {
        $this->mysqli = $mysqli;
        $this->manufacturer_model = $manufacturer_model;
    }

    /*
     * Get a list of all heatpumps
     * 
     * @return array
     */
    public function get_list() {

        $result = $this->mysqli->query("
            SELECT 
                hm.*, 
                m.name as manufacturer_name 
            FROM heatpump_model hm 
            LEFT JOIN manufacturers m ON hm.manufacturer_id = m.id
        ");
        $heatpumps = [];
        while ($row = $result->fetch_assoc()) {
            $heatpumps[] = $row;
        }

        foreach ($heatpumps as $key => $unit) {
             $heatpumps[$key]["stats"] = $this->get_stats($unit["manufacturer_name"], $unit['name'], $unit["refrigerant"], $unit["capacity"]);
             $heatpumps[$key]["tests"] = $this->get_tests($unit["id"]);
        }

        return $heatpumps;
    }

    /*
     * Add a new heatpump
     * 
     * @param int $manufacturer_id
     * @param string $model
     * @param string $refrigerant
     * @param string $type
     * @param float $capacity
     * @param float $min_flowrate
     * @param float $max_flowrate
     * @param float $max_current
     * @return array
     */
    public function add($manufacturer_id, $model, $refrigerant, $type, $capacity, $min_flowrate = null, $max_flowrate = null, $max_current = null) {
        // Validate inputs
        $manufacturer_id = (int) $manufacturer_id;
        $model = trim($model);
        $refrigerant = trim($refrigerant);
        $type = trim($type);
        $capacity = trim($capacity);
        $min_flowrate = $min_flowrate !== null && $min_flowrate !== '' ? (float) $min_flowrate : null;
        $max_flowrate = $max_flowrate !== null && $max_flowrate !== '' ? (float) $max_flowrate : null;
        $max_current = $max_current !== null && $max_current !== '' ? (float) $max_current : null;
        
        if (empty($model)) {
            return array("success" => false, "message" => "Model name is required");
        }

        if (empty($refrigerant)) {
            return array("success" => false, "message" => "Refrigerant is required");
        }

        if (empty($type)) {
            return array("success" => false, "message" => "Type is required");
        }
        
        if ($capacity <= 0) {
            return array("success" => false, "message" => "Capacity must be greater than 0");
        }
        
        // Validate manufacturer exists
        if (!$this->manufacturer_model->get_by_id($manufacturer_id)) {
            return array("success" => false, "message" => "Invalid manufacturer ID");
        }
        
        // Check if this model already exists for this manufacturer and capacity
        if ($this->model_exists($manufacturer_id, $model, $refrigerant, $capacity)) {
            return array("success" => false, "message" => "This heat pump model already exists");
        }
        
        // Insert the new heat pump model
        $stmt = $this->mysqli->prepare("INSERT INTO heatpump_model (manufacturer_id, name, refrigerant, type, capacity, min_flowrate, max_flowrate, max_current) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssddd", $manufacturer_id, $model, $refrigerant, $type, $capacity, $min_flowrate, $max_flowrate, $max_current);
        
        if ($stmt->execute()) {
            $new_id = $this->mysqli->insert_id;
            $stmt->close();
            return array("success" => true, "message" => "Heat pump model added successfully", "id" => $new_id);
        } else {
            $error = $stmt->error;
            $stmt->close();
            return array("success" => false, "message" => "Failed to add heat pump model: " . $error);
        }
    }

    /*
     * Update an existing heatpump
     * 
     * @param int $id
     * @param int $manufacturer_id
     * @param string $model
     * @param string $refrigerant
     * @param string $type
     * @param float $capacity
     * @param float $min_flowrate
     * @param float $max_flowrate
     * @param float $max_current
     * @return array
     */
    public function update($id, $manufacturer_id, $model, $refrigerant, $type, $capacity, $min_flowrate = null, $max_flowrate = null, $max_current = null) {
        // Validate inputs
        $id = (int) $id;
        $manufacturer_id = (int) $manufacturer_id;
        $model = trim($model);
        $refrigerant = trim($refrigerant);
        $type = trim($type);
        $capacity = trim($capacity);
        $min_flowrate = $min_flowrate !== null && $min_flowrate !== '' ? (float) $min_flowrate : null;
        $max_flowrate = $max_flowrate !== null && $max_flowrate !== '' ? (float) $max_flowrate : null;
        $max_current = $max_current !== null && $max_current !== '' ? (float) $max_current : null;

        if (empty($model)) {
            return array("success" => false, "message" => "Model name is required");
        }

        if (empty($refrigerant)) {
            return array("success" => false, "message" => "Refrigerant is required");
        }

        if (empty($type)) {
            return array("success" => false, "message" => "Type is required");
        }

        if ($capacity <= 0) {
            return array("success" => false, "message" => "Capacity must be greater than 0");
        }

        // Validate manufacturer exists
        if (!$this->manufacturer_model->get_by_id($manufacturer_id)) {
            return array("success" => false, "message" => "Invalid manufacturer ID");
        }

        // Check if this model already exists for this manufacturer and capacity (excluding current record)
        if ($this->model_exists($manufacturer_id, $model, $refrigerant, $capacity, $id)) {
            return array("success" => false, "message" => "This heat pump model already exists");
        }

        // Update the heat pump model
        $stmt = $this->mysqli->prepare("UPDATE heatpump_model SET `manufacturer_id` = ?, `name` = ?, `refrigerant` = ?, `type` = ?, `capacity` = ?, `min_flowrate` = ?, `max_flowrate` = ?, `max_current` = ? WHERE id = ?");
        $stmt->bind_param("issssdddi", $manufacturer_id, $model, $refrigerant, $type, $capacity, $min_flowrate, $max_flowrate, $max_current, $id);
        if ($stmt->execute()) {
            $stmt->close();
            return array("success" => true, "message" => "Heat pump model updated successfully");
        } else {
            $error = $stmt->error;
            $stmt->close();
            return array("success" => false, "message" => "Failed to update heat pump model: " . $error);
        }
    }

    /*
     * Delete a heatpump model
     * 
     * @param int $id
     * @return array
     */
    public function delete($id) {
        $id = (int) $id;
        
        $stmt = $this->mysqli->prepare("DELETE FROM heatpump_model WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            
            if ($affected_rows > 0) {
                return array("success" => true, "message" => "Heat pump model deleted successfully");
            } else {
                return array("success" => false, "message" => "Heat pump model not found");
            }
        } else {
            $error = $stmt->error;
            $stmt->close();
            return array("success" => false, "message" => "Failed to delete heat pump model: " . $error);
        }
    }

    /*
     * Check if a heatpump model exists 
     * @param int $manufacturer_id
     * @param string $model
     * @param float $capacity
     * @param int $exclude_id (optional) - exclude this ID from the check
     * @return bool
     */
    public function model_exists($manufacturer_id, $model, $refrigerant, $capacity, $exclude_id = null) {
        if ($exclude_id) {
            $stmt = $this->mysqli->prepare("SELECT id FROM heatpump_model WHERE manufacturer_id = ? AND name = ? AND refrigerant = ? AND capacity = ? AND id != ?");
            $stmt->bind_param("isssi", $manufacturer_id, $model, $refrigerant, $capacity, $exclude_id);
        } else {
            $stmt = $this->mysqli->prepare("SELECT id FROM heatpump_model WHERE manufacturer_id = ? AND name = ? AND refrigerant = ? AND capacity = ?");
            $stmt->bind_param("isss", $manufacturer_id, $model, $refrigerant, $capacity);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        
        return $exists;
    }

    /*
     * Get a single heatpump by id
     * 
     * @param int $id
     * @return array
     */
    public function get($id, $include_stats = true) {
        $id = (int) $id;
        
        $stmt = $this->mysqli->prepare("
            SELECT 
                hm.*, 
                m.name as manufacturer_name 
            FROM heatpump_model hm 
            LEFT JOIN manufacturers m ON hm.manufacturer_id = m.id 
            WHERE hm.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $heatpump = $result->fetch_assoc();
        $stmt->close();
        
        if (!$heatpump) {
            return array("success" => false, "message" => "Heatpump not found");
        }

        if ($include_stats) {
            $heatpump["stats"] = $this->get_stats($heatpump["manufacturer_name"], $heatpump['name'], $heatpump['refrigerant'], $heatpump["capacity"]);
        }

        return $heatpump;
    }

    /*
     * Load stats on a particular heatpump model
     * 
     * @param string $model
     * @param int $capacity
     * @return array
     */
    public function get_stats($manufacturer, $model, $refrigerant, $capacity)
    {
        $manufacturer = trim($manufacturer);
        $model = trim($model);
        $capacity = trim($capacity);
        $refrigerant = trim($refrigerant);

        // Prepare the query with placeholders
        $query = "
            SELECT 
                sm.id, 
                ss.combined_elec_kwh, 
                ss.combined_heat_kwh, 
                ss.combined_cop, 
                ss.combined_data_length
            FROM 
                system_meta sm
            INNER JOIN 
                system_stats_last365_v2 ss
            ON 
                sm.id = ss.id
            WHERE 
                sm.hp_manufacturer LIKE ?
                AND sm.hp_model LIKE ? 
                AND sm.hp_output = ? 
                AND sm.refrigerant LIKE ?
                AND sm.published = '1' 
                AND sm.share = '1'
        ";
        
        $stmt = $this->mysqli->prepare($query);
        $manufacturer_pattern = '%' . $manufacturer . '%';
        $model_pattern = '%' . $model . '%';
        $refrigerant_pattern = '%' . $refrigerant . '%';
        $capacity_pattern = $capacity;
        $stmt->bind_param("ssss", $manufacturer_pattern, $model_pattern, $capacity_pattern, $refrigerant_pattern);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $heatpumps = [];
        while ($row = $result->fetch_assoc()) {
            $heatpumps[] = $row;
        }
        $stmt->close();

        $number_of_systems = count($heatpumps);

        if ($number_of_systems == 0) {
            return array(
                "number_of_systems" => 0,
                "number_of_systems_last365" => 0,
                "average_spf" => 0,
                "lowest_spf" => 0,
                "highest_spf" => 0
            );
        }

        // Calculate min, max, average COP and count of systems with 1 year of data
        $min_cop = null;
        $max_cop = null;
        $sum_cop = 0;
        $cop_count = 0;

        foreach ($heatpumps as $unit) {
            // Skip systems with less than 1 year of data
            if ($unit['combined_data_length'] < 330*24*3600) continue;
            $cop = $unit['combined_cop'];
            if ($min_cop === null || $cop < $min_cop) $min_cop = $cop;
            if ($max_cop === null || $cop > $max_cop) $max_cop = $cop;
            $sum_cop += $cop;
            $cop_count++;
        }

        $average_spf = $cop_count > 0 ? number_format($sum_cop / $cop_count, 2, ".", "") * 1 : 0;

        if ($min_cop !== null) {
            $min_cop = number_format($min_cop, 2, ".", "") * 1;
        }

        if ($max_cop !== null) {
            $max_cop = number_format($max_cop, 2, ".", "") * 1;
        }


        return array(
            "number_of_systems" => $number_of_systems,
            "number_of_systems_last365" => $cop_count,
            "average_spf" => $average_spf,
            "lowest_spf" => $min_cop,
            "highest_spf" => $max_cop,
            "heatpumps" => $heatpumps
        );
    }


    public function populate_table() {

        // Get all manufacturers from the manufacturers table
        $manufacturers = $this->manufacturer_model->get_names();

        $result = $this->mysqli->query("SELECT * FROM system_meta WHERE published = '1' AND share = '1'");
        // hp_model, hp_output, refrigerant
        $heatpumps = [];
        // Group by hp_model
        while ($row = $result->fetch_object()) {

            // E.g Vaillant Arotherm+
            $hp_type = trim($row->hp_type);
            $hp_manufacturer = trim($row->hp_manufacturer);
            $hp_model = trim($row->hp_model);
            $hp_refrigerant = trim($row->refrigerant);
            $hp_output = (float) trim($row->hp_output);

            // get manufacturer id$
            $manufacturer_id = false;
            if ($manufacturer = $this->manufacturer_model->get_by_name($hp_manufacturer)) {
                $manufacturer_id = $manufacturer->id;
                if ($this->model_exists($manufacturer_id, $hp_model, $hp_refrigerant, $hp_output)) {
                    continue; // Skip if this model already exists
                }
            }

            $key = $hp_manufacturer . ' ' . $hp_model . ' ' . $hp_refrigerant . ' ' . $hp_output;

            if (!isset($heatpumps[$key])) {
                $heatpumps[$key] = array(
                    'manufacturer_id' => $manufacturer_id,
                    'type' => $hp_type,
                    'manufacturer' => $hp_manufacturer,
                    'model' => $hp_model,
                    'refrigerant' => $hp_refrigerant,
                    'capacity' => $hp_output,
                    'system_ids' => [],
                    'count' => 0
                );
            }

            $heatpumps[$key]['count']++;
            $heatpumps[$key]['system_ids'][] = $row->id;
        }

        // arrange by most common
        usort($heatpumps, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        return $heatpumps;
    }

    /*
     * Get a list of unmatched heat pumps from system_meta
     * 
     * @return array
     */
    public function get_unmatched_list() {
        return $this->populate_table();
    }

    /*
     * Get test counts for a heatpump model
     * 
     * @param int $model_id
     * @return array
     */
    public function get_tests($model_id) {
        $model_id = (int) $model_id;

        $max_count = 0;
        $max_sum = 0;
        
        $result = $this->mysqli->query("SELECT review_status, heat FROM heatpump_max_cap_test WHERE model_id = $model_id");
        while ($row = $result->fetch_object()) {
            if ($row->review_status == 1) {
                $max_count++;
                $max_sum += $row->heat;
            }
        }
        $max_output = $max_count > 0 ? $max_sum / $max_count : 0;

        $min_count = 0;
        $min_sum = 0;

        $result = $this->mysqli->query("SELECT review_status, heat FROM heatpump_min_cap_test WHERE model_id = $model_id");
        while ($row = $result->fetch_object()) {
            if ($row->review_status == 1) {
                $min_count++;
                $min_sum += $row->heat;
            }
        }
        $min_output = $min_count > 0 ? $min_sum / $min_count : 0;


        
        return array(
            "max_count" => $max_count,
            "max_output" => $max_output,
            "min_count" => $min_count,
            "min_output" => $min_output
        );
    }
    
    /*
     * Upload image for heatpump model
     * 
     * @param int $id
     * @param array $photo
     * @return array
     */
    public function upload_image($id, $photo) {
        $id = (int) $id;
        
        // Check for upload errors
        if ($photo['error'] !== UPLOAD_ERR_OK) {
            return array("success" => false, "message" => "Upload failed with error: " . $photo['error']);
        }
        
        // Validate file size (5MB max)
        $max_size = 5 * 1024 * 1024; // 5MB in bytes
        if ($photo['size'] > $max_size) {
            return array("success" => false, "message" => "File size exceeds 5MB limit");
        }
        
        // Validate file type
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/webp');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $photo['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            return array("success" => false, "message" => "Invalid file type. Only JPG, PNG, and WebP are allowed");
        }
        
        // Check if heatpump model exists
        $heatpump = $this->get($id, false);
        if (!$heatpump || isset($heatpump['success'])) {
            return array("success" => false, "message" => "Heat pump model not found");
        }
        
        // Create directory structure
        $upload_dir = "theme/img/heatpumps/";
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                return array("success" => false, "message" => "Failed to create upload directory");
            }
        }
        
        // Generate filename based on heatpump info
        $extension = strtolower(pathinfo($photo['name'], PATHINFO_EXTENSION));
        $safe_name = preg_replace('/[^a-z0-9_-]/', '_', strtolower($heatpump['manufacturer_name'] . '_' . $heatpump['name'] . '_' . $heatpump['capacity'] . 'kw'));
        $filename = $safe_name . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        // Remove old image if exists
        if (!empty($heatpump['img']) && file_exists("theme/img/heatpumps/" . $heatpump['img'])) {
            unlink("theme/img/heatpumps/" . $heatpump['img']);
        }
        
        // Move uploaded file
        if (!move_uploaded_file($photo['tmp_name'], $filepath)) {
            return array("success" => false, "message" => "Failed to save uploaded file");
        }
        
        // Update database with new filename
        $stmt = $this->mysqli->prepare("UPDATE heatpump_model SET img = ? WHERE id = ?");
        $stmt->bind_param("si", $filename, $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            return array("success" => true, "message" => "Image uploaded successfully", "filename" => $filename);
        } else {
            // Clean up file if database update fails
            unlink($filepath);
            $error = $stmt->error;
            $stmt->close();
            return array("success" => false, "message" => "Failed to save image information: " . $error);
        }
    }
    
    /*
     * Delete image for heatpump model
     * 
     * @param int $id
     * @return array
     */
    public function delete_image($id) {
        $id = (int) $id;
        
        // Get current image filename
        $stmt = $this->mysqli->prepare("SELECT img FROM heatpump_model WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if (!$row) {
            return array("success" => false, "message" => "Heat pump model not found");
        }
        
        $filename = $row['img'];
        
        // Remove from database
        $stmt = $this->mysqli->prepare("UPDATE heatpump_model SET img = NULL WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            // Delete file if it exists
            if (!empty($filename) && file_exists("theme/img/heatpumps/" . $filename)) {
                unlink("theme/img/heatpumps/" . $filename);
            }
            $stmt->close();
            return array("success" => true, "message" => "Image deleted successfully");
        } else {
            $error = $stmt->error;
            $stmt->close();
            return array("success" => false, "message" => "Failed to delete image: " . $error);
        }
    }
}
