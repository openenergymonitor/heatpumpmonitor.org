<?php

// Set dhw_target_temperature to 50 °C where it's blank or 0, for systems with
// hot water storage (dhw_method not 'None'). The field was off the form from
// Dec 2025 to Oct 2026. Prints what it would change; pass --apply to write.
//
// php scripts/set_default_dhw_target.php [--apply]
//
// Then run scripts/update_design_estimates.php to update the SCOP estimates.

define('EMONCMS_EXEC', 1);
$dir = dirname(__FILE__);
chdir("$dir/../www");
require "core.php";

require "Lib/load_database.php";

const DEFAULT_DHW_TARGET = 50;
$apply = in_array('--apply', $argv);

$where = "(dhw_target_temperature IS NULL OR dhw_target_temperature = 0)
    AND dhw_method IS NOT NULL AND dhw_method != '' AND dhw_method != 'None'";

$result = $mysqli->query("SELECT dhw_method, COUNT(*) AS n FROM system_meta WHERE $where GROUP BY dhw_method ORDER BY n DESC");
$total = 0;
while ($row = $result->fetch_object()) {
    print str_pad($row->n, 5, " ", STR_PAD_LEFT) . "  " . $row->dhw_method . "\n";
    $total += $row->n;
}

$result = $mysqli->query("SELECT COUNT(*) AS n FROM system_meta WHERE (dhw_target_temperature IS NULL OR dhw_target_temperature = 0) AND NOT ($where)");
$skipped = $result->fetch_object()->n;
print "$total system(s) to set to " . DEFAULT_DHW_TARGET . " °C, $skipped blank left as is (no hot water storage or not given)\n";

if (!$apply) {
    print "Dry run, pass --apply to write\n";
    exit;
}

$mysqli->query("UPDATE system_meta SET dhw_target_temperature = " . DEFAULT_DHW_TARGET . " WHERE $where");
print "Updated " . $mysqli->affected_rows . " system(s)\n";
