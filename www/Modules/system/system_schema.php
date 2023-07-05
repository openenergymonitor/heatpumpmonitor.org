<?php

$schema['system_meta'] = array(
    // Form meta data
    'id' => array('type' => 'int(11)', 'Null'=>false, 'Key'=>'PRI', 'Extra'=>'auto_increment', 'code' => 'i', 'editable' => false),
    'userid' => array('type' => 'int(11)', 'code' => 'i', 'editable' => false),
    'last_updated' => array('type' => 'int(11)', 'code' => 'i', 'editable' => false),

    // Form data
    'location' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true, 'optional' => false),
    'installer_name' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true, 'optional' => true),
    'installer_url' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true, 'optional' => true),

    'hp_model' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true, 'optional' => false),
    'hp_type' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true, 'optional' => false),
    'hp_output' => array('type' => 'float', 'code' => 'd', 'editable' => true, 'optional' => false),

    // Hydraulic seperation
    'buffer' => array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => true, 'optional' => false),
    'LLH' => array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => true, 'optional' => false),
    'HEX' => array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => true, 'optional' => false),

    // Heat emitters
    'new_radiators' => array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => true, 'optional' => false),
    'old_radiators' => array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => true, 'optional' => false),
    'UFH' => array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => true, 'optional' => false),

    // Flow temperature & weather compensation
    'flow_temp' => array('type' => 'float', 'code' => 'd', 'editable' => true, 'optional' => false),
    'flow_temp_typical' => array('type' => 'float', 'code' => 'd', 'editable' => true, 'optional' => false),
    'wc_curve' => array('type' => 'float', 'code' => 'd', 'editable' => true, 'optional' => true),

    'heat_demand' => array('type' => 'int(11)', 'code' => 'i', 'editable' => true, 'optional' => false),
    'notes' => array('type' => 'varchar(300)', 'code' => 's', 'editable' => true, 'optional' => true),
    'property' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true, 'optional' => false),
    'floor_area' => array('type' => 'float', 'code' => 'd', 'editable' => true, 'optional' => false),
    'heat_loss' => array('type' => 'float', 'code' => 'd', 'editable' => true, 'optional' => false),
    'age' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true, 'optional' => false),
    'insulation' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true, 'optional' => false),
    
    'freeze' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true, 'optional' => false),
    'zone_number' => array('type' => 'int(11)', 'code' => 'i', 'editable' => true, 'optional' => false),
    'zone_notes' => array('type' => 'varchar(128)', 'code' => 's', 'editable' => true, 'optional' => true),
    'space_heat_control_type' => array('type' => 'varchar(32)', 'code' => 's', 'editable' => true, 'optional' => false),
    'space_heat_control_notes' => array('type' => 'varchar(128)', 'code' => 's', 'editable' => true, 'optional' => false),
    'refrigerant' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true, 'optional' => false),
    'dhw' => array('type' => 'varchar(200)', 'code' => 's', 'editable' => true, 'optional' => false),
    'legionella' => array('type' => 'varchar(64)', 'code' => 's', 'editable' => true, 'optional' => false),

    'url' => array('type' => 'varchar(128)', 'code' => 's', 'editable' => true, 'optional' => false),
    'electric_meter' => array('type' => 'varchar(128)', 'code' => 's', 'editable' => true, 'optional' => false),
    'heat_meter' => array('type' => 'varchar(128)', 'code' => 's', 'editable' => true, 'optional' => false),
    'metering_inc_boost' => array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => true, 'optional' => false),
    'metering_inc_central_heating_pumps' => array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => true, 'optional' => false),
    'metering_inc_brine_pumps' => array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => true, 'optional' => false),
    'metering_inc_controls'=> array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => true, 'optional' => false),
    'metering_notes' => array('type' => 'varchar(300)', 'code' => 's', 'editable' => true, 'optional' => true),

    'share' => array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => true, 'optional' => false),
    'published' => array('type' => 'tinyint(1)', 'code' => 'i', 'editable' => false, 'optional' => false)

);

$schema['system_stats'] = array(
    'id' => array('type' => 'int(11)', 'code' => 'i', 'editable' => false),

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
