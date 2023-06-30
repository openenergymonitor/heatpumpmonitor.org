<?php

$schema['form'] = array(
    // Form meta data
    'id' => array('type' => 'int(11)', 'Null'=>false, 'Key'=>'PRI', 'Extra'=>'auto_increment', 'code' => 'i', 'editable' => false),
    'userid' => array('type' => 'int(11)', 'code' => 'i', 'editable' => false),
    'last_updated' => array('type' => 'int(11)', 'code' => 'i', 'editable' => false),

    // Form data
    'location' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),
    'installer_name' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),
    'installer_url' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),

    'hp_model' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),
    'hp_type' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),
    'hp_output' => array('type' => 'float', 'code' => 'd', 'editable' => true),

    // Hydraulic seperation
    'buffer' => array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => true),
    'LLH' => array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => true),
    'HEX' => array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => true),

    // Heat emitters
    'new_radiators' => array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => true),
    'old_radiators' => array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => true),
    'UFH' => array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => true),

    // Flow temperature & weather compensation
    'flow_temp' => array('type' => 'float', 'code' => 'd', 'editable' => true),
    'flow_temp_typical' => array('type' => 'float', 'code' => 'd', 'editable' => true),
    'wc_curve' => array('type' => 'float', 'code' => 'd', 'editable' => true),

    'heat_demand' => array('type' => 'int(11)', 'code' => 'i', 'editable' => true),
    'notes' => array('type' => 'varchar(300)', 'code' => 's', 'editable' => true),
    'property' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),
    'floor_area' => array('type' => 'float', 'code' => 'd', 'editable' => true),
    'heat_loss' => array('type' => 'float', 'code' => 'd', 'editable' => true),
    'age' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),
    'insulation' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),
    
    'freeze' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),
    'zone' => array('type' => 'varchar(128)', 'code' => 's', 'editable' => true),
    'controls' => array('type' => 'varchar(128)', 'code' => 's', 'editable' => true),
    'refrigerant' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),
    'dhw' => array('type' => 'varchar(200)', 'code' => 's', 'editable' => true),
    'legionella' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true),

    'url' => array('type' => 'varchar(128)', 'code' => 's', 'editable' => true),
    'electric_meter' => array('type' => 'varchar(128)', 'code' => 's', 'editable' => true),
    'heat_meter' => array('type' => 'varchar(128)', 'code' => 's', 'editable' => true),
    'metering_inc_boost' => array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => true),
    'metering_inc_central_heating_pumps' => array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => true),
    'metering_inc_brine_pumps' => array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => true),
    'metering_inc_controls'=> array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => true),
    'metering_notes' => array('type' => 'varchar(300)', 'code' => 's', 'editable' => true),

    'share' => array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => true),

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