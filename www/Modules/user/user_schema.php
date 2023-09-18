<?php

$schema['users'] = array(
    'id' => array('type' => 'int(11)', 'Null'=>false, 'Key'=>'PRI', 'Extra'=>'auto_increment'),
    'username' => array('type' => 'varchar(30)'),
    'name' => array('type' => 'varchar(30)'),
    'email' => array('type' => 'varchar(64)'),
    'hash' => array('type' => 'varchar(64)'),
    'salt' => array('type' => 'varchar(32)'),
    'admin' => array('type' => 'int(11)', 'Null'=>false),
    'email_verified' => array('type' => 'int(11)', 'default'=>0),
    'verification_key' => array('type' => 'varchar(64)', 'default'=>''),
    'created' => array('type' => 'int(11)', 'default'=>0),
    'last_login' => array('type' => 'int(11)', 'default'=>0),
    'welcome_email_sent' => array('type' => 'int(11)', 'default'=>0)
);

$schema['emoncmsorg_link'] = array(
    'userid' => array('type' => 'int(11)'),
    'emoncmsorg_userid' => array('type' => 'int(11)'),
    'emoncmsorg_apikey_write' => array('type' => 'varchar(64)'),
    'emoncmsorg_apikey_read' => array('type' => 'varchar(64)')
);