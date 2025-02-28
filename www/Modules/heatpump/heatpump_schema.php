<?php

// Manufacturer schema
$schema['heatpump_manufacturer'] = array(
    'id' => array('type' => 'int(11)', 'Null' => false, 'Key' => 'PRI', 'Extra' => 'auto_increment'),
    'name' => array('type' => 'varchar(128)'),
    'website' => array('type' => 'varchar(128)')
);

// Model schema
$schema['heatpump_model'] = array(
    'id' => array('type' => 'int(11)', 'Null' => false, 'Key' => 'PRI', 'Extra' => 'auto_increment'),
    'manufacturer_id' => array('type' => 'int(11)'),
    'name' => array('type' => 'varchar(128)')
);