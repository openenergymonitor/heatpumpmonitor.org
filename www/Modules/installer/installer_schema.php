<?php

// Installer schema definition
$schema['installer'] = array(
    'id' => array('type' => 'int(11)', 'Null' => false, 'Key' => 'PRI', 'Extra' => 'auto_increment'),
    'name' => array('type' => 'varchar(64)'),
    'url' => array('type' => 'varchar(64)'),
    'logo' => array('type' => 'varchar(64)'),
    'color' => array('type' => 'varchar(7)'), // Enough for #RRGGBB hex color
);