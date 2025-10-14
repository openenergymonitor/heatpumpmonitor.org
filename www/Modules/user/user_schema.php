<?php

$schema['users'] = array(
    // user id should link to emoncms.org user id
    'id' => array('type' => 'int(11)', 'Null'=>false, 'Key'=>'PRI', 'Extra'=>'auto_increment'),
    'username' => array('type' => 'varchar(30)'),
    'name' => array('type' => 'varchar(30)'),
    'email' => array('type' => 'varchar(64)'),
    'apikey_write' => array('type' => 'varchar(64)'),
    'apikey_read' => array('type' => 'varchar(64)'),
    'admin' => array('type' => 'int(11)', 'Null'=>false),
    'created' => array('type' => 'int(11)', 'default'=>0),
    'last_login' => array('type' => 'int(11)', 'default'=>0),
);

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

$schema['accounts'] = array(
    'adminuser' => array('type' => 'int(11)'),
    'linkeduser' => array('type' => 'int(11)')
);