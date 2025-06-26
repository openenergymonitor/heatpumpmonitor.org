<?php

// Installer schema definition
$schema['installer'] = array(
    'id' => array('type' => 'int(11)', 'Null' => false, 'Key' => 'PRI', 'Extra' => 'auto_increment'),
    'name' => array('type' => 'varchar(64)'),
    'url' => array('type' => 'varchar(64)'),
    'logo' => array('type' => 'varchar(64)'),
    'systems' => array('type' => 'int(11)', 'default' => 0), // Number of systems using this installer
    'color' => array('type' => 'varchar(7)'), // Enough for #RRGGBB hex color
);