<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class Heatpump
{
    private $mysqli;

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /*
     * Get a list of all heatpumps
     * 
     * @return array
     */
    public function get_list() {
        $heatpumps = json_decode(file_get_contents("Modules/heatpump/heatpump_list.json"), true);

        foreach ($heatpumps as $key => $unit) {
            $heatpumps[$key]["stats"] = $this->get_stats($unit["manufacturer"], $unit["capacity"]);
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

        $heatpump["stats"] = $this->get_stats($heatpump["manufacturer"], $heatpump["capacity"]); // ." ".$heatpump["model"];
        $heatpump["min_mod_tests"] = $this->get_min_mod_tests($id);
        $heatpump["max_cap_tests"] = $this->get_max_cap_tests($id);

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
    public function get_stats($model, $capacity)
    {
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

        return array(
            "number_of_systems" => count($heatpumps),
            "number_of_systems_last365" => $cop_count,
            "average_spf" => number_format($sum_cop / $cop_count,2,".","")*1,
            "lowest_spf" => number_format($min_cop,2,".","")*1,
            "highest_spf" => number_format($max_cop,2,".","")*1
        );
    }

    /*
     * Get min_mod_tests
     * 
     * @param string $id
     * @return array
     */
    public function get_min_mod_tests($id) {
        $min_mod_tests = json_decode(file_get_contents("Modules/heatpump/min_mod_tests.json"), true);

        $tests = [];
        foreach ($min_mod_tests as $test) {
            if ($test['heatpump_id'] == $id) {
                $tests[] = $test;
            }
        }
        return $tests;
    }

    /*
     * Get max_cap_tests
     * 
     * @param string $id
     * @return array
     */
    public function get_max_cap_tests($id) {
        $max_cap_tests = json_decode(file_get_contents("Modules/heatpump/max_cap_tests.json"), true);

        $tests = [];
        foreach ($max_cap_tests as $test) {
            if ($test['heatpump_id'] == $id) {
                $tests[] = $test;
            }
        }
        return $tests;

    }

}