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
        }

        return $heatpumps;
    }

    /*
     * Add a new heatpump
     * 
     * @param int $manufacturer_id
     * @param string $model
     * @param float $capacity
     * @return array
     */
    public function add($manufacturer_id, $model, $refrigerant, $type, $capacity) {
        // Validate inputs
        $manufacturer_id = (int) $manufacturer_id;
        $model = trim($model);
        $refrigerant = trim($refrigerant);
        $type = trim($type);
        $capacity = (float) $capacity;
        
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
        $stmt = $this->mysqli->prepare("INSERT INTO heatpump_model (manufacturer_id, name, refrigerant, type, capacity) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isssd", $manufacturer_id, $model, $refrigerant, $type, $capacity);
        
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
     * @param float $capacity
     * @return array
     */
    public function update($id, $manufacturer_id, $model, $refrigerant, $type, $capacity) {
        // Validate inputs
        $id = (int) $id;
        $manufacturer_id = (int) $manufacturer_id;
        $model = trim($model);
        $refrigerant = trim($refrigerant);
        $type = trim($type);
        $capacity = (float) $capacity;

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
        $stmt = $this->mysqli->prepare("UPDATE heatpump_model SET manufacturer_id = ?, name = ?, refrigerant = ?, type = ?, capacity = ? WHERE id = ?");
        $stmt->bind_param("isssdi", $manufacturer_id, $model, $refrigerant, $type, $capacity, $id);
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
            $stmt->bind_param("issdi", $manufacturer_id, $model, $refrigerant, $capacity, $exclude_id);
        } else {
            $stmt = $this->mysqli->prepare("SELECT id FROM heatpump_model WHERE manufacturer_id = ? AND name = ? AND refrigerant = ? AND capacity = ?");
            $stmt->bind_param("issd", $manufacturer_id, $model, $refrigerant, $capacity);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        
        return $exists;
    }

    public function populate_table() {

        // Get all manufacturers from the manufacturers table
        $manufacturers = $this->manufacturer_model->get_names();

        $result = $this->mysqli->query("SELECT * FROM system_meta");
        // hp_model, hp_output, refrigerant
        $heatpumps = [];
        // Group by hp_model
        while ($row = $result->fetch_object()) {

            // E.g Vaillant Arotherm+
            $hp_model = trim($row->hp_model);

            // Check if manufacturer is in the hp_model text string
            $manufacturer_name = false;
            foreach ($manufacturers as $manufacturer) {
                if (stripos($hp_model, $manufacturer) !== false) {
                    $manufacturer_name = $manufacturer;
                    break;
                }
            }

            if (!isset($heatpumps[$manufacturer_name])) {
                $heatpumps[$manufacturer_name] = [];
            }

            // Remove the manufacturer name from the hp_model
            if ($manufacturer_name) {
                $hp_model = str_ireplace($manufacturer_name, "", $hp_model);
                $hp_model = trim($hp_model);
            }

            // Add the heatpump to the list
            if (!isset($heatpumps[$manufacturer_name][$hp_model])) {
                $heatpumps[$manufacturer_name][$hp_model] = 0;
            }

            // Increment the count of heatpumps for this model
            $heatpumps[$manufacturer_name][$hp_model]++;
        }

        return $heatpumps;
    }

    /*
     * Get a single heatpump by id
     * 
     * @param int $id
     * @return array
     */
    public function get($id) {
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

        $heatpump["stats"] = $this->get_stats($heatpump["manufacturer_name"], $heatpump['name'], $heatpump['refrigerant'], $heatpump["capacity"]);

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
        $model = $manufacturer . " " . $model; // Combine manufacturer and model for search
        $capacity = (float) $capacity;
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
                sm.hp_model LIKE ? 
                AND sm.hp_output LIKE ? 
                AND sm.refrigerant LIKE ?
                AND sm.published = '1' 
                AND sm.share = '1'
        ";
        
        $stmt = $this->mysqli->prepare($query);
        $model_pattern = '%' . $model . '%';
        $refrigerant_pattern = '%' . $refrigerant . '%';
        $capacity_pattern = '%' . $capacity . '%';
        $stmt->bind_param("sss", $model_pattern, $capacity_pattern, $refrigerant_pattern);
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

        return array(
            "number_of_systems" => $number_of_systems,
            "number_of_systems_last365" => $cop_count,
            "average_spf" => $average_spf,
            "lowest_spf" => number_format($min_cop,2,".","")*1,
            "highest_spf" => number_format($max_cop,2,".","")*1
        );
    }
}