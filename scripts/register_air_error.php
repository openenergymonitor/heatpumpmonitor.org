<?php
// Set the base directory and change to the appropriate directory for loading resources
$dir = dirname(__FILE__);
chdir("$dir/../www");

// Load necessary models for database operations
require "Lib/load_database.php";
require("Modules/user/user_model.php");
require("Modules/system/system_model.php");
require("Modules/system/system_stats_model.php");

// Initialize models
$user = new User($mysqli, false);
$system = new System($mysqli);
$system_stats = new SystemStats($mysqli, $system);

// Load system data and stats
$systems = $system->list_admin();
$stats = $system_stats->get_last30(false);

// Function: Merge system stats into systems
function mergeSystemStats($systems, $stats) {
    $systems_with_stats = [];
    foreach ($systems as $system) {
        if (isset($stats[$system->id])) {
            $systemstats = $stats[$system->id];
            foreach ($systemstats as $key => $stat) {
                $system->$key = $stat;
            }
            $systems_with_stats[] = $system;
        }
    }
    return $systems_with_stats;
}

// Refactor: Encapsulate merging of stats
$systems = mergeSystemStats($systems, $stats);

// Function: Sort systems by 'error_air' value
function sortSystemsByErrorAir($systems) {
    usort($systems, fn($a, $b) => $b->error_air <=> $a->error_air); // Use spaceship operator for cleaner logic
    return $systems;
}

// Refactor: Sorting moved into a reusable function
$systems = sortSystemsByErrorAir($systems);

// Function: Clear the "Heat meter air error" flag from a system
function clearHeatMeterAirError($mysqli, $systemId) {
    $stmt = $mysqli->prepare("UPDATE system_meta SET data_flag = 0, data_flag_note = '' WHERE id = ?");
    $stmt->bind_param("i", $systemId);
    $stmt->execute();
    $stmt->close();
}

// Function: Update system flags based on error difference
function updateSystemFlag($mysqli, $system, $diffThreshold = 0.2) {
    $cop = $system->combined_heat_kwh / $system->combined_elec_kwh;
    $cop_nc_error = $system->combined_heat_kwh / ($system->combined_elec_kwh - $system->error_air_kwh);
    $diff = abs($cop - $cop_nc_error);

    // Refactor: Parameterized SQL to improve security
    $stmt = $mysqli->prepare("UPDATE system_meta SET data_flag = ?, data_flag_note = ? WHERE id = ?");
    if ($diff > $diffThreshold) {
        $data_flag = 1;
        $note = 'Heat meter air error';
    } else {
        $data_flag = 0;
        $note = '';
    }
    $stmt->bind_param("isi", $data_flag, $note, $system->id);
    $stmt->execute();
    $stmt->close();
}

// Process each system and update the database
foreach ($systems as $system) {
    // Clear flag if it's set to "Heat meter air error"
    if ($system->data_flag_note === "Heat meter air error") {
        clearHeatMeterAirError($mysqli, $system->id);
    }

    // Skip processing for systems with no air error
    if ($system->error_air == 0) continue;

    // Update the system flag based on calculated error differences
    updateSystemFlag($mysqli, $system);
}

?>