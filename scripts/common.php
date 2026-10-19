<?php

function auto_flag_air_error($system) {
    $combined_elec_kwh = isset($system->combined_elec_kwh) ? $system->combined_elec_kwh : (isset($system->stats['combined_elec_kwh']) ? $system->stats['combined_elec_kwh'] : null);
    $combined_heat_kwh = isset($system->combined_heat_kwh) ? $system->combined_heat_kwh : (isset($system->stats['combined_heat_kwh']) ? $system->stats['combined_heat_kwh'] : null);
    $combined_cop = isset($system->combined_cop) ? $system->combined_cop : (isset($system->stats['combined_cop']) ? $system->stats['combined_cop'] : null);
    $error_air_kwh = isset($system->error_air_kwh) ? $system->error_air_kwh : (isset($system->stats['error_air_kwh']) ? $system->stats['error_air_kwh'] : null);

    // Auto flag air errors if the air error changes the COP by more than 0.2
    if ($error_air_kwh !== null && $error_air_kwh > 0) {
        if ($combined_elec_kwh !== null && $combined_elec_kwh > 0) {

            $electric_not_including_air_error = $combined_elec_kwh - $error_air_kwh;
            $cop_not_including_air_error = 0;
            if ($electric_not_including_air_error > 0) {
                $cop_not_including_air_error = $combined_heat_kwh / $electric_not_including_air_error;
            }

            $difference = $combined_cop - $cop_not_including_air_error;
            if (abs($difference) > 0.2) {
                $system->data_flag = 1;
            }
        }
    }
}

function calc_mean($values) {
    if (count($values) == 0) return null;
    return array_sum($values) / count($values);
}

function calc_median($values) {
    if (count($values) == 0) return null;
    sort($values);
    $count = count($values);
    $mid = (int) floor($count / 2);
    if ($count % 2) {
        return $values[$mid];
    }
    return ($values[$mid - 1] + $values[$mid]) / 2;
}

// Print a table in the same psql style as python tabulate
function print_table($table, $headers) {

    $column_count = count($headers);

    // Column widths: widest of header and all cell values
    $widths = array();
    for ($i = 0; $i < $column_count; $i++) {
        $widths[$i] = strlen($headers[$i]);
        foreach ($table as $row) {
            $widths[$i] = max($widths[$i], strlen((string)$row[$i]));
        }
    }

    // Right-align columns where every value is numeric
    $numeric = array();
    for ($i = 0; $i < $column_count; $i++) {
        $numeric[$i] = count($table) > 0;
        foreach ($table as $row) {
            if (!is_numeric($row[$i])) {
                $numeric[$i] = false;
                break;
            }
        }
    }

    $print_row = function($row) use ($widths, $numeric, $column_count) {
        $cells = array();
        for ($i = 0; $i < $column_count; $i++) {
            $pad_type = $numeric[$i] ? STR_PAD_LEFT : STR_PAD_RIGHT;
            $cells[] = str_pad((string)$row[$i], $widths[$i], " ", $pad_type);
        }
        print "| ".implode(" | ", $cells)." |\n";
    };

    $line = function($left, $mid, $right) use ($widths) {
        $parts = array();
        foreach ($widths as $width) {
            $parts[] = str_repeat("-", $width + 2);
        }
        print $left.implode($mid, $parts).$right."\n";
    };

    $line("+", "+", "+");
    $print_row($headers);
    $line("|", "+", "|");
    foreach ($table as $row) {
        $print_row($row);
    }
    $line("+", "+", "+");
}