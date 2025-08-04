<?php

// Model schema
$schema['heatpump_model'] = array(
    'id' => array('type' => 'int(11)', 'Null' => false, 'Key' => 'PRI', 'Extra' => 'auto_increment'),
    'manufacturer_id' => array('type' => 'int(11)'),
    'name' => array('type' => 'varchar(128)'),
    'capacity' => array('type' => 'float', 'Null' => false, 'default' => 0)
);