<?php

// Manufacturer schema
// Heat pumps, hot water cylinders, etc.
$schema['manufacturers'] = array(
    'id' => array('type' => 'int(11)', 'Null' => false, 'Key' => 'PRI', 'Extra' => 'auto_increment'),
    'name' => array('type' => 'varchar(128)'),
    'website' => array('type' => 'varchar(128)')
);