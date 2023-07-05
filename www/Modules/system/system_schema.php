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
    'notes' => array('type' => 'text', 'code' => 's', 'editable' => true, 'optional' => true),
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
    'dhw_control_type' => array('type' => 'varchar(32)', 'code' => 's', 'editable' => true, 'optional' => false),
    'dhw_target_temperature' => array('type' => 'float', 'code' => 'd', 'editable' => true, 'optional' => false),
    'legionella_frequency' => array('type' => 'varchar(32)', 'code' => 's', 'editable' => true, 'optional' => false),
    'legionella_target_temperature' => array('type' => 'float', 'code' => 'd', 'editable' => true, 'optional' => false),

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
    'id' => array('type' => 'int(11)', 'code' => 'i'),

    // Request period details
    'start' => array('type' => 'int(11)', 'code' => 'i'),
    'end' => array('type' => 'int(11)', 'code' => 'i'),
    'interval' => array('type' => 'int(11)', 'code' => 'i'),
    'datapoints' => array('type' => 'int(11)', 'code' => 'i'),
    'standby_threshold' => array('type' => 'float', 'code' => 'd'),

    // Request period totals
    'full_period_elec_kwh' => array('type' => 'float', 'code' => 'd'),
    'full_period_heat_kwh' => array('type' => 'float', 'code' => 'd'),
    'full_period_cop' => array('type' => 'float', 'code' => 'd'),
    'standby_kwh' => array('type' => 'float', 'code' => 'd'),

    // when running
    'when_running_elec_kwh' => array('type' => 'float', 'code' => 'd'),
    'when_running_heat_kwh' => array('type' => 'float', 'code' => 'd'),
    'when_running_cop' => array('type' => 'float', 'code' => 'd'),
    'when_running_elec_W' => array('type' => 'float', 'code' => 'd'),
    'when_running_heat_W' => array('type' => 'float', 'code' => 'd'),
    'when_running_flowT' => array('type' => 'float', 'code' => 'd'),
    'when_running_returnT' => array('type' => 'float', 'code' => 'd'),
    'when_running_flow_minus_return' => array('type' => 'float', 'code' => 'd'),
    'when_running_outsideT' => array('type' => 'float', 'code' => 'd'),
    'when_running_flow_minus_outside' => array('type' => 'float', 'code' => 'd'),
    'when_running_carnot_prc' => array('type' => 'float', 'code' => 'd'),

    // Last 365
    'last_365_elec_kwh' => array('type' => 'float', 'code' => 'd'),
    'last_365_heat_kwh' => array('type' => 'float', 'code' => 'd'),
    'last_365_cop' => array('type' => 'float', 'code' => 'd'),
    'last_365_since' => array('type' => 'int(11)', 'code' => 'i'),

    // Last 30
    'last_30_elec_kwh' => array('type' => 'float', 'code' => 'd'),
    'last_30_heat_kwh' => array('type' => 'float', 'code' => 'd'),
    'last_30_cop' => array('type' => 'float', 'code' => 'd'),
    'last_30_since' => array('type' => 'int(11)', 'code' => 'i'),
);
