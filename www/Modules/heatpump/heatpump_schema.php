<?php

// Model schema
$schema['heatpump_model'] = array(
    'id' => array('type' => 'int(11)', 'Null' => false, 'Key' => 'PRI', 'Extra' => 'auto_increment'),
    'manufacturer_id' => array('type' => 'int(11)', 'Index' => true),
    'name' => array('type' => 'varchar(128)', 'Index' => true),
    'capacity' => array('type' => 'varchar(10)', 'Null' => true, 'default' => null, 'Index' => true),
    'refrigerant' => array('type' => 'varchar(32)', 'Null' => true, 'default' => null),
    'type' => array('type' => 'varchar(32)', 'Null' => true, 'default' => null),
    'min_flowrate' => array('type' => 'float', 'Null' => true, 'default' => null),
    'max_flowrate' => array('type' => 'float', 'Null' => true, 'default' => null),
    'max_current' => array('type' => 'float', 'Null' => true, 'default' => null),
    'img' => array('type' => 'varchar(255)', 'Null' => true, 'default' => null),
    'img_width' => array('type' => 'int(11)', 'Null' => true, 'default' => null),
    'img_height' => array('type' => 'int(11)', 'Null' => true, 'default' => null),
    'img_thumbnails' => array('type' => 'json', 'Null' => true, 'default' => null)
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
    'flowrate' => array('type' => 'float'),

    // Used for test management
    'userid' => array('type' => 'int(11)', 'Null' => true, 'default' => null),
    'created' => array('type' => 'datetime', 'Null' => true, 'default' => null),
    'review_status' => array('type' => 'int(11)', 'Null' => true, 'default' => 0),
    'review_comment' => array('type' => 'text', 'Null' => true, 'default' => '')
);

$schema['heatpump_min_cap_test'] = array(
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
    'flowrate' => array('type' => 'float'),

    // Used for test management
    'userid' => array('type' => 'int(11)', 'Null' => true, 'default' => null),
    'created' => array('type' => 'datetime', 'Null' => true, 'default' => null),
    'review_status' => array('type' => 'int(11)', 'Null' => true, 'default' => 0),
    'review_comment' => array('type' => 'text', 'Null' => true, 'default' => '')
);