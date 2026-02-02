<?php

// Remember me table
$schema['user_sessions'] = array(
    'id' => array('type' => 'int(11)', 'Null'=>false, 'Key'=>'PRI', 'Extra'=>'auto_increment'),
    'userid' => array('type' => 'int(11)'),
    // selector
    'selector' => array('type' => 'varchar(255)'),
    // hashed validator
    'hash_validator' => array('type' => 'varchar(255)'),
    'expires' => array('type' => 'int(11)')
);