<?php

// Signature schema
// Steady-state operating episodes derived from real-world system data.
// Each episode is a stable, compressor-running period collapsed to a single
// representative point (mean of each feed), used to characterise a system's
// real-world performance signature.
$schema['signature_episodes'] = array(
    'id'          => array('type' => 'int(11)', 'Null' => false, 'Key' => 'PRI', 'Extra' => 'auto_increment'),
    'system_id'   => array('type' => 'int(11)', 'Index' => true),

    'start_time'  => array('type' => 'int(11)', 'Index' => true), // unix timestamp
    'end_time'    => array('type' => 'int(11)'),
    'duration'    => array('type' => 'int(11)'),                  // seconds

    // Per-feed means over the episode (required feeds)
    'elec'        => array('type' => 'float'),                    // W
    'flowT'       => array('type' => 'float'),                    // degC

    // Per-feed means over the episode (optional feeds)
    'heat'        => array('type' => 'float', 'Null' => true, 'default' => null), // W
    'returnT'     => array('type' => 'float', 'Null' => true, 'default' => null), // degC
    'flowrate'    => array('type' => 'float', 'Null' => true, 'default' => null),
    'outsideT'    => array('type' => 'float', 'Null' => true, 'default' => null), // degC
    'roomT'       => array('type' => 'float', 'Null' => true, 'default' => null), // degC

    // Derived metrics
    'dT'          => array('type' => 'float', 'Null' => true, 'default' => null), // flowT - returnT
    'cop'         => array('type' => 'float', 'Null' => true, 'default' => null),
    'flowT_stdev' => array('type' => 'float'),
    'flowT_slope' => array('type' => 'float')                     // degC/hour
);
