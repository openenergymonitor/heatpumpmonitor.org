<?php

$schema['form'] = array(
    // Form meta data
    'id' => array('type' => 'int(11)', 'Null'=>false, 'Key'=>'PRI', 'Extra'=>'auto_increment', 'code' => 'i', 'editable' => false),
    'userid' => array('type' => 'int(11)', 'code' => 'i', 'editable' => false),
    'submitted' => array('type' => 'datetime', 'code' => 's', 'editable' => false),

    // Form data
    'name' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),
    'email' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),
    'location' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),
    'hp_model' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),
    'hp_type' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),
    'hp_output' => array('type' => 'float', 'code' => 'd', 'editable' => true),
    'emitters' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),
    'heat_demand' => array('type' => 'int(11)', 'code' => 'i', 'editable' => true),
    'notes' => array('type' => 'varchar(300)', 'code' => 's', 'editable' => true),
    'property' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),
    'floor_area' => array('type' => 'float', 'code' => 'd', 'editable' => true),
    'heat_loss' => array('type' => 'float', 'code' => 'd', 'editable' => true),
    'url' => array('type' => 'varchar(128)', 'code' => 's', 'editable' => true),
    'age' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),
    'insulation' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),
    'flow_temp' => array('type' => 'float', 'code' => 'd', 'editable' => true),
    'buffer' => array('type' => 'varchar(8)', 'code' => 's', 'editable' => true),
    'freeze' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),
    'zone' => array('type' => 'varchar(128)', 'code' => 's', 'editable' => true),
    'controls' => array('type' => 'varchar(128)', 'code' => 's', 'editable' => true),
    'refrigerant' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),
    'dhw' => array('type' => 'varchar(200)', 'code' => 's', 'editable' => true),
    'legionella' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),

    // Scraped heat pump data
    'month_elec' => array('type' => 'float', 'code' => 'd', 'editable' => false),
    'month_heat' => array('type' => 'float', 'code' => 'd', 'editable' => false),
    'month_cop' => array('type' => 'float', 'code' => 'd', 'editable' => false),
    'year_elec' => array('type' => 'float', 'code' => 'd', 'editable' => false),
    'year_heat' => array('type' => 'float', 'code' => 'd', 'editable' => false),
    'year_cop' => array('type' => 'float', 'code' => 'd', 'editable' => false),
    'since' => array('type' => 'int(11)', 'code' => 'i', 'editable' => false),
    'stats' => array('type' => 'varchar(1024)', 'code' => 's', 'editable' => false)
);



/*
{"id":1,"hash":"1c63d2e1",
    "submitted":"2022-11-05T22:10:10",
    "location":"North Yorkshire",
    "hp_model":"Mitsubishi Ecodan",
    "hp_type":"Air Source",
    "hp_output":"11.2",
    "emitters":"New radiators",
    "heat_demand":"22000",
    "notes":"",
    "property":"Detached",
    "floor_area":"134",
    "heat_loss":"9.4",
    "url":"https:\/\/emon.cryosphere.co.uk\/ashp",
    "01\/03\/2023":"Yes",
    "age":"1940 to 1982",
    "insulation":"Cavity wall, plus some loft insulation",
    "flow_temp":"45C",
    "buffer":"No",
    "freeze":"Glycol\/water mixture",
    "zone":"1",
    "controls":"Custom software",
    "refrigerant":"R32",
    "dhw":"Daily cycle at noon to 45C",
    "legionella":"None"
    }
*/