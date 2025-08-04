<?php

// Model schema
$schema['heatpump_model'] = array(
    'id' => array('type' => 'int(11)', 'Null' => false, 'Key' => 'PRI', 'Extra' => 'auto_increment'),
    'manufacturer_id' => array('type' => 'int(11)'),
    'name' => array('type' => 'varchar(128)'),
    'capacity' => array('type' => 'float', 'Null' => false, 'default' => 0)
);

$schema['heatpump_max_cap_test'] = array(
    'id' => array('type' => 'int(11)', 'Null' => false, 'Key' => 'PRI', 'Extra' => 'auto_increment'),
    'model_id' => array('type' => 'int(11)'),
    'system_id' => array('type' => 'int(11)'),
    'test_url' => array('type' => 'varchar(255)'),
    'start' => array('type' => 'int(11)'),
    'end' => array('type' => 'int(11)'),
    'date' => array('type' => 'varchar(32)'),
    'data_length' => array('type' => 'int(11)'),
    'flowT' => array('type' => 'float'),
    'outsideT' => array('type' => 'float'),
    'elec' => array('type' => 'float'),
    'heat' => array('type' => 'float'),
    'cop' => array('type' => 'float'),
    'flowrate' => array('type' => 'float')
);