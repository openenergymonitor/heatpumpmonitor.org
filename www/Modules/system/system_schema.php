<?php

$schema['system_meta'] = array(
    // Form meta data
    'id' => array('type' => 'int(11)', 'Null'=>false, 'Key'=>'PRI', 'Extra'=>'auto_increment', 'editable' => false),
    'userid' => array('type' => 'int(11)', 'editable' => false),
    'last_updated' => array('type' => 'int(11)', 'editable' => false, 'name'=>'Last updated', 'group'=>'Overview'),

    // Form data
    'location' => array('type' => 'varchar(64)', 'editable' => true, 'optional' => false, 'name'=>'Location', 'group'=>'Overview'),
    'installer_name' => array('type' => 'varchar(64)', 'editable' => true, 'optional' => true, 'name'=>'Installer name', 'group'=>'Overview'),
    'installer_url' => array('type' => 'varchar(64)', 'editable' => true, 'optional' => true, 'name'=>'Installer URL', 'group'=>'Overview'),

    'hp_model' => array('type' => 'varchar(64)', 'editable' => true, 'optional' => false, 'name'=>'Heat pump model', 'group'=>'Heat pump'),
    'hp_type' => array('type' => 'varchar(64)', 'editable' => true, 'optional' => false, 'name'=>'Heat pump type', 'group'=>'Heat pump'),
    'hp_output' => array('type' => 'float', 'editable' => true, 'optional' => false, 'name'=>'Heat pump output', 'group'=>'Heat pump'),
    'refrigerant' => array('type' => 'varchar(64)', 'editable' => true, 'optional' => false, 'name'=>'Refrigerant', 'group'=>'Heat pump'),

    // Hydraulic seperation
    'buffer' => array('type' => 'tinyint(1)', 'editable' => true, 'optional' => false, 'name'=>'Buffer tank', 'group'=>'Hydraulic seperation'),
    'LLH' => array('type' => 'tinyint(1)', 'editable' => true, 'optional' => false, 'name'=>'Low loss header', 'group'=>'Hydraulic seperation'),
    'HEX' => array('type' => 'tinyint(1)', 'editable' => true, 'optional' => false, 'name'=>'Heat exchanger', 'group'=>'Hydraulic seperation'),

    // Heat emitters
    'new_radiators' => array('type' => 'tinyint(1)', 'editable' => true, 'optional' => false, 'name'=>'New radiators', 'group'=>'Heat emitters'),
    'old_radiators' => array('type' => 'tinyint(1)', 'editable' => true, 'optional' => false, 'name'=>'Old radiators', 'group'=>'Heat emitters'),
    'UFH' => array('type' => 'tinyint(1)', 'editable' => true, 'optional' => false, 'name'=>'Underfloor heating', 'group'=>'Heat emitters'),

    // Flow temperature & weather compensation
    'flow_temp' => array('type' => 'float', 'editable' => true, 'optional' => false, 'name'=>'Flow temperature', 'group'=>'Heat pump controls'),
    'flow_temp_typical' => array('type' => 'float', 'editable' => true, 'optional' => false, 'name'=>'Typical flow temperature', 'group'=>'Heat pump controls'),
    'wc_curve' => array('type' => 'float', 'editable' => true, 'optional' => true, 'name'=>'Weather compensation curve', 'group'=>'Heat pump controls'),

    'heat_demand' => array('type' => 'int(11)', 'editable' => true, 'optional' => false, 'name'=>'Heat demand', 'group'=>'Property'),
    'property' => array('type' => 'varchar(64)', 'editable' => true, 'optional' => false, 'name'=>'Property type', 'group'=>'Property'),
    'floor_area' => array('type' => 'float', 'editable' => true, 'optional' => false, 'name'=>'Floor area', 'group'=>'Property'),
    'heat_loss' => array('type' => 'float', 'editable' => true, 'optional' => false, 'name'=>'Heat loss', 'group'=>'Property'),
    'age' => array('type' => 'varchar(64)', 'editable' => true, 'optional' => false, 'name'=>'Age', 'group'=>'Property'),
    'insulation' => array('type' => 'varchar(64)', 'editable' => true, 'optional' => false, 'name'=>'Insulation', 'group'=>'Property'),
    
    'freeze' => array('type' => 'varchar(64)', 'editable' => true, 'optional' => false, 'name'=>'Freeze protection', 'group'=>'Heat pump controls'),
    'zone_number' => array('type' => 'int(11)', 'editable' => true, 'optional' => false, 'name'=>'Number of zones', 'group'=>'Heat pump controls'),
    'zone_notes' => array('type' => 'varchar(128)', 'editable' => true, 'optional' => true, 'name'=>'Zone notes', 'group'=>'Heat pump controls'),
    'space_heat_control_type' => array('type' => 'varchar(32)', 'editable' => true, 'optional' => false, 'name'=>'Space heat control type', 'group'=>'Heat pump controls'),
    'space_heat_control_notes' => array('type' => 'varchar(128)', 'editable' => true, 'optional' => false, 'name'=>'Space heat control notes', 'group'=>'Heat pump controls'),
    'dhw_control_type' => array('type' => 'varchar(32)', 'editable' => true, 'optional' => false, 'name'=>'DHW control type', 'group'=>'Heat pump controls'),
    'dhw_target_temperature' => array('type' => 'float', 'editable' => true, 'optional' => false, 'name'=>'DHW target temperature', 'group'=>'Heat pump controls'),
    'legionella_frequency' => array('type' => 'varchar(32)', 'editable' => true, 'optional' => false, 'name'=>'Legionella frequency', 'group'=>'Heat pump controls'),
    'legionella_target_temperature' => array('type' => 'float', 'editable' => true, 'optional' => false, 'name'=>'Legionella target temperature', 'group'=>'Heat pump controls'),

    'url' => array('type' => 'varchar(128)', 'editable' => true, 'optional' => false, 'name'=>'URL', 'group'=>'Overview'),
    'electric_meter' => array('type' => 'varchar(128)', 'editable' => true, 'optional' => false, 'name'=>'Electric meter', 'group'=>'Metering'),
    'heat_meter' => array('type' => 'varchar(128)', 'editable' => true, 'optional' => false, 'name'=>'Heat meter', 'group'=>'Metering'),
    'metering_inc_boost' => array('type' => 'tinyint(1)', 'editable' => true, 'optional' => false, 'name'=>'Metering includes booster heater', 'group'=>'Metering'),
    'metering_inc_central_heating_pumps' => array('type' => 'tinyint(1)', 'editable' => true, 'optional' => false, 'name'=>'Metering includes central heating pumps', 'group'=>'Metering'),
    'metering_inc_brine_pumps' => array('type' => 'tinyint(1)', 'editable' => true, 'optional' => false, 'name'=>'Metering includes brine pumps', 'group'=>'Metering'),
    'metering_inc_controls'=> array('type' => 'tinyint(1)', 'editable' => true, 'optional' => false, 'name'=>'Metering includes controls', 'group'=>'Metering'),
    'metering_notes' => array('type' => 'varchar(300)', 'editable' => true, 'optional' => true, 'name'=>'Metering notes', 'group'=>'Metering'),

    'notes' => array('type' => 'text', 'editable' => true, 'optional' => true, 'name'=>'Notes', 'group'=>'Property'),

    'share' => array('type' => 'tinyint(1)', 'editable' => true, 'optional' => false, 'name'=>'Share', 'group'=>'Overview'),
    'published' => array('type' => 'tinyint(1)', 'editable' => false, 'optional' => false, 'name'=>'Published', 'group'=>'Overview'),

);

$schema['system_stats'] = array(
    'id' => array('type' => 'int(11)'),

    // Request period details
    'start' => array('type' => 'int(11)'),
    'end' => array('type' => 'int(11)'),
    'interval' => array('type' => 'int(11)'),
    'datapoints' => array('type' => 'int(11)'),
    'standby_threshold' => array('type' => 'float'),

    // Request period totals
    'full_period_elec_kwh' => array('type' => 'float'),
    'full_period_heat_kwh' => array('type' => 'float'),
    'full_period_cop' => array('type' => 'float'),
    'standby_kwh' => array('type' => 'float'),

    // when running
    'when_running_elec_kwh' => array('type' => 'float'),
    'when_running_heat_kwh' => array('type' => 'float'),
    'when_running_cop' => array('type' => 'float'),
    'when_running_elec_W' => array('type' => 'float'),
    'when_running_heat_W' => array('type' => 'float'),
    'when_running_flowT' => array('type' => 'float'),
    'when_running_returnT' => array('type' => 'float'),
    'when_running_flow_minus_return' => array('type' => 'float'),
    'when_running_outsideT' => array('type' => 'float'),
    'when_running_flow_minus_outside' => array('type' => 'float'),
    'when_running_carnot_prc' => array('type' => 'float'),

    // Last 365
    'last_365_elec_kwh' => array('type' => 'float', 'name'=>'Last 365 days electricity consumption', 'group'=>'Stats'),
    'last_365_heat_kwh' => array('type' => 'float', 'name'=>'Last 365 days heat output', 'group'=>'Stats'),
    'last_365_cop' => array('type' => 'float', 'name'=>'Last 365 days COP', 'group'=>'Stats'),
    'last_365_since' => array('type' => 'int(11)'),

    // Last 30
    'last_30_elec_kwh' => array('type' => 'float', 'name'=>'Last 30 days electricity consumption', 'group'=>'Stats'),
    'last_30_heat_kwh' => array('type' => 'float', 'name'=>'Last 30 days heat output', 'group'=>'Stats'),
    'last_30_cop' => array('type' => 'float', 'name'=>'Last 30 days COP', 'group'=>'Stats'),
    'last_30_since' => array('type' => 'int(11)'),
);
