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
        // $heatpumps = json_decode(file_get_contents("Modules/heatpump/heatpump_list.json"), true);

        $result = $this->mysqli->query("SELECT * FROM heatpump_model");
        $heatpumps = [];
        while ($row = $result->fetch_assoc()) {

            // Get manufacturer name
            $manufacturer = $this->manufacturer_model->get_by_id($row['manufacturer_id']);
            if ($manufacturer) {
                $row['manufacturer_name'] = $manufacturer->name;
            } else {
                $row['manufacturer_name'] = 'Unknown';
            }

            $heatpumps[] = $row;
        }

        foreach ($heatpumps as $key => $unit) {
             $heatpumps[$key]["stats"] = $this->get_stats($unit["manufacturer_name"], $unit['name'], $unit["capacity"]);
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
    public function add($manufacturer_id, $model, $capacity) {
        // Validate inputs
        $manufacturer_id = (int) $manufacturer_id;
        $model = trim($model);
        $capacity = (float) $capacity;
        
        if (empty($model)) {
            return array("success" => false, "message" => "Model name is required");
        }
        
        if ($capacity <= 0) {
            return array("success" => false, "message" => "Capacity must be greater than 0");
        }
        
        // Validate manufacturer exists
        if (!$this->manufacturer_model->get_by_id($manufacturer_id)) {
            return array("success" => false, "message" => "Invalid manufacturer ID");
        }
        
        // Check if this model already exists for this manufacturer and capacity
        if ($this->model_exists($manufacturer_id, $model, $capacity)) {
            return array("success" => false, "message" => "This heat pump model already exists");
        }
        
        // Insert the new heat pump model
        $stmt = $this->mysqli->prepare("INSERT INTO heatpump_model (manufacturer_id, name, capacity) VALUES (?, ?, ?)");
        $stmt->bind_param("isd", $manufacturer_id, $model, $capacity);
        
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
    public function update($id, $manufacturer_id, $model, $capacity) {
        // Validate inputs
        $id = (int) $id;
        $manufacturer_id = (int) $manufacturer_id;
        $model = trim($model);
        $capacity = (float) $capacity;

        if (empty($model)) {
            return array("success" => false, "message" => "Model name is required");
        }

        if ($capacity <= 0) {
            return array("success" => false, "message" => "Capacity must be greater than 0");
        }

        // Validate manufacturer exists
        if (!$this->manufacturer_model->get_by_id($manufacturer_id)) {
            return array("success" => false, "message" => "Invalid manufacturer ID");
        }

        // Check if this model already exists for this manufacturer and capacity (excluding current record)
        if ($this->model_exists($manufacturer_id, $model, $capacity, $id)) {
            return array("success" => false, "message" => "This heat pump model already exists");
        }

        // Update the heat pump model
        $stmt = $this->mysqli->prepare("UPDATE heatpump_model SET manufacturer_id = ?, name = ?, capacity = ? WHERE id = ?");
        $stmt->bind_param("isdi", $manufacturer_id, $model, $capacity, $id);
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
    public function model_exists($manufacturer_id, $model, $capacity, $exclude_id = null) {
        if ($exclude_id) {
            $stmt = $this->mysqli->prepare("SELECT id FROM heatpump_model WHERE manufacturer_id = ? AND name = ? AND capacity = ? AND id != ?");
            $stmt->bind_param("isdi", $manufacturer_id, $model, $capacity, $exclude_id);
        } else {
            $stmt = $this->mysqli->prepare("SELECT id FROM heatpump_model WHERE manufacturer_id = ? AND name = ? AND capacity = ?");
            $stmt->bind_param("isd", $manufacturer_id, $model, $capacity);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        
        return $exists;
    }

    /*
     * Check if a heatpump model exists 
     * @param int $manufacturer_id
     * @param string $model
     * @param float $capacity
     * @return bool
     */
    public function check_exists($manufacturer_id, $model, $capacity) {
        return $this->model_exists($manufacturer_id, $model, $capacity);
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
        $heatpumps = $this->get_list();

        $heatpump = false;
        foreach ($heatpumps as $unit) {
            if ($unit['id'] == $id) {
                $heatpump = $unit;
                break;
            }
        }
        if (!$heatpump) return array("success" => false, "message" => "Heatpump not found");

        $heatpump["stats"] = $this->get_stats($heatpump["manufacturer_name"], $heatpump['name'], $heatpump["capacity"]);

        // continue here
        return $heatpump;
    }

    /*
     * Load stats on a particular heatpump model
     * 
     * @param string $model
     * @param int $capacity
     * @return array
     */
    public function get_stats($manufacturer, $model, $capacity)
    {

        // Sanitize inputs
        $manufacturer = $this->mysqli->real_escape_string($manufacturer);
        $model = $this->mysqli->real_escape_string($model);

        $model = $manufacturer . " " . $model; // Combine manufacturer and model for search

        $capacity = (int) $this->mysqli->real_escape_string($capacity);

        // Get all systems with the given model and capacity that are published and shared
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
                sm.hp_model LIKE '%$model%' 
                AND sm.hp_output = '$capacity' 
                AND sm.published = '1' 
                AND sm.share = '1'
        ";
        
        // Execute the query and fetch the results
        $result = $this->mysqli->query($query);
        $heatpumps = [];
        while ($row = $result->fetch_assoc()) {
            $heatpumps[] = $row;
        }

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