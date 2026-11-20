<?php

// Measurement schema
// Design parameters worked out from each system's monitoring data, written
// nightly by scripts/measurements/system_measurements.php. See
// analysis/performance_prediction/docs/system_page_insights.md.

// One row per system and measured quantity (e.g. heat_loss_design,
// design_outsideT_996, flowT_typical_design). A measurement that comes from
// one period (a day, a 4 hour window) gives it in start_time and end_time so
// the dashboard can link to it. One built from several selected days lists
// them, each with its own timestamps, in details.
$schema['measurement'] = array(
    'system_id'   => array('type' => 'int(11)', 'Null' => false, 'Key' => 'PRI'),
    'name'        => array('type' => 'varchar(64)', 'Null' => false, 'Key' => 'PRI'),

    'value'       => array('type' => 'float', 'Null' => true, 'default' => null),
    'lower'       => array('type' => 'float', 'Null' => true, 'default' => null), // interval, see details.interval
    'upper'       => array('type' => 'float', 'Null' => true, 'default' => null),
    'unit'        => array('type' => 'varchar(16)', 'Null' => true, 'default' => null),
    'samples'     => array('type' => 'int(11)', 'Null' => true, 'default' => null), // days, hours or windows used

    'start_time'  => array('type' => 'int(11)', 'Null' => true, 'default' => null), // unix timestamp
    'end_time'    => array('type' => 'int(11)', 'Null' => true, 'default' => null),

    'details'     => array('type' => 'mediumtext', 'Null' => true, 'default' => null), // JSON: method, inputs, selected days (flowT_map: a PNG, base64)
    'version'     => array('type' => 'int(11)', 'Null' => false, 'default' => 1),  // method version
    'updated'     => array('type' => 'int(11)', 'Null' => false, 'default' => 0)
);

// Per-day samples from the 10 s feed scan, one row per system and local day
// (Europe/London midnight, as the daily stats). Kept so the nightly run only
// scans new days, and so the distributions behind each measurement can be
// drawn later. 4 hour windows are rolled continuously at 10 minute steps and
// belong to the day they start in.
$schema['measurement_day'] = array(
    'system_id'           => array('type' => 'int(11)', 'Null' => false, 'Key' => 'PRI'),
    'timestamp'           => array('type' => 'int(11)', 'Null' => false, 'Key' => 'PRI'), // local midnight

    'data_s'              => array('type' => 'int(11)', 'Null' => true, 'default' => null),   // seconds with heat data
    'heat_kwh'            => array('type' => 'float', 'Null' => true, 'default' => null),     // all modes, net of cooling
    'outsideT'            => array('type' => 'float', 'Null' => true, 'default' => null),     // daily mean, °C
    'roomT'               => array('type' => 'float', 'Null' => true, 'default' => null),     // daily mean, °C
    'outsideT_hourly'     => array('type' => 'text', 'Null' => true, 'default' => null),      // JSON, hourly means from midnight

    // Space heating: running, not DHW, not cooling (as the dashboard's space heating stats)
    'space_s'             => array('type' => 'int(11)', 'Null' => true, 'default' => null),   // running seconds
    'space_kwh'           => array('type' => 'float', 'Null' => true, 'default' => null),
    'space_flowT'         => array('type' => 'float', 'Null' => true, 'default' => null),     // weighted by heat output
    'space_outsideT'      => array('type' => 'float', 'Null' => true, 'default' => null),     // weighted by heat output

    // Highest 4 hour heat output (all modes), kW, and its start
    'heat_max4h'          => array('type' => 'float', 'Null' => true, 'default' => null),
    'heat_max4h_start'    => array('type' => 'int(11)', 'Null' => true, 'default' => null),

    // Highest 4 hour space heating flow temperature weighted by heat output, and its start.
    // Only windows with at least 2 hours of space heating running count.
    'space_flowT_max4h'       => array('type' => 'float', 'Null' => true, 'default' => null),
    'space_flowT_max4h_start' => array('type' => 'int(11)', 'Null' => true, 'default' => null),

    'dhw_flag'            => array('type' => 'tinyint(1)', 'Null' => true, 'default' => null), // DHW feed used for the split

    // Heat (Wh) from each 10 s sample binned by outside and flow temperature, gzipped JSON:
    // {"ob": bins per K outside, "fb": bins per K flow, "s": {"outside bin,flow bin": Wh}, "w": {...}}
    // for space heating and hot water. Cleared after FLOWMAP_KEEP_DAYS
    'flowmap'             => array('type' => 'blob', 'Null' => true, 'default' => null)
);
