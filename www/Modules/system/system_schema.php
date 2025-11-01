<?php

$schema['system_access'] = array(
    'systemid' => array('type' => 'int(11)'),
    'userid' => array('type' => 'int(11)'),
    // 0 = no access, 1 = read only, 2 = read and write
    'access' => array('type' => 'int(11)') 
);

$schema['system_images'] = array(
    'id' => array(
        'type' => 'int(11)', 
        'Null' => false, 
        'Key' => 'PRI', 
        'Extra' => 'auto_increment'
    ),
    'system_id' => array('type' => 'int(11)', 'Null' => false),
    'image_path' => array('type' => 'varchar(255)', 'Null' => false),
    'original_filename' => array('type' => 'varchar(255)', 'Null' => false),
    'width' => array('type' => 'int(11)', 'Null' => true),
    'height' => array('type' => 'int(11)', 'Null' => true),
    'file_size' => array('type' => 'int(11)', 'Null' => true),
    'date_uploaded' => array('type' => 'int(11)', 'Null' => false)
);

$schema['system_meta'] = array(

    // Form meta data
    'id' => array(
        'type' => 'int(11)', 
        'Null' => false, 
        'Key' => 'PRI', 
        'Extra' => 'auto_increment', 
        'editable' => false,
        'name' => 'ID',
        'group' => 'Overview'
    ),
    'userid' => array('type' => 'int(11)', 'editable' => false),
    'published' => array('type' => 'tinyint(1)', 'editable' => false, 'optional' => false, 'name' => 'Published', 'group' => 'Overview'),
    'last_updated' => array('type' => 'int(11)', 'editable' => false, 'name' => 'Last updated', 'group' => 'Overview'),

    // New method of linking an emoncms instance myheatpump app
    'app_id' => array('type' => 'int(11)', 'editable' => true),
    'readkey' => array('type' => 'varchar(64)', 'editable' => true),
    
    /* ------------------------------ Overview ----------------------------- */

    'location' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Location', 
        'helper' => 'Roughly where the heat pump is installed, to nearest city or county',
        'group' => 'Overview'
    ),

    'latitude' => array(
        'type' => 'float', 
        'editable' => false, 
        'optional' => true, 
        'name' => 'Latitude', 
        'group' => 'Overview',
        'helper' => 'Rough location, nearest town or county',
    ),

    'longitude' => array(
        'type' => 'float', 
        'editable' => false, 
        'optional' => true, 
        'name' => 'Longitude', 
        'group' => 'Overview',
        'helper' => 'Rough location, nearest town or county',
    ),

    'installer_name' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Installer', 
        'group' => 'Overview',
        'helper' => 'Optional. If you are not the installer we recommend asking the installer if they are happy with their name being displayed. Self install is also an option..',
        'basic' => true
    ),

    'installer_url' => array(
        'type' => 'varchar(255)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Installer URL', 
        'group' => 'Overview',
        'basic' => true
    ),
    
    'installer_logo' => array(
        'type' => 'varchar(64)', 
        'editable' => false, 
        'optional' => true, 
        'name' => 'Installer Logo', 
        'heading' => '',
        'group' => 'Overview'
    ),

    'heatgeek' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Heat Geek Mastery', 
        'group' => 'Training',
        //'basic' => true
    ),
    
    'ultimaterenewables' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Ultimate Renewables Pro', 
        'group' => 'Training',
        //'basic' => true
    ),
    
    'heatingacademy' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Heating Academy Hydronics', 
        'group' => 'Training',
        //'basic' => true
    ),
    
    'betateach' => array(
        'type' => 'varchar(256)',
        'editable' => true, 
        'optional' => true, 
        'name' => 'BetaTalk', 
        'group' => 'Learn more',
        //'basic' => true
    ),
    
    'youtube' => array(
        'type' => 'varchar(256)',
        'editable' => true, 
        'optional' => true, 
        'name' => 'YouTube', 
        'group' => 'Learn more',
        //'basic' => true
    ),

    'url' => array(
        'type' => 'varchar(128)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'MyHeatpump App URL',
        'helper' => '',
        // 'group' => 'Overview',
        'show' => false,
        'show_to_admin' => true
    ),

    'share' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Share', 
        'group' => 'Overview',
        'show' => false
    ),

    /* ------------------------------ Heat pump system ----------------------------- */

    'hp_type' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Heat pump type', 
        'group' => 'Heat pump', 
        'options'=>array('Air Source','Ground Source','Water Source','Air-to-Air','Other'),
        'show'  => false
    ),

    'hp_manufacturer' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Heat pump manufacturer', 
        'group' => 'Heat pump',
        'show'  => false
    ),

    'hp_model' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Heat pump make & model',
        'group' => 'Heat pump',
        'show'  => false
    ),

    'hp_output' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Heat pump output', 
        'helper' => 'This is the badge output not necessarily the maximum output as given on the datasheet',
        'group' => 'Heat pump',
        'unit' => 'kW',
        'show'  => false
    ),
    
    'hp_max_output' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Heat pump max output', 
        'helper' => 'Maximum output as given on the datasheet for expected design flow temperature',
        'group' => 'Heat pump',
        'unit' => 'kW',
        'show' => false
    ),

    'hp_max_output_test' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => true,
        'show' => false,
        'name' => 'Heat pump max output test', 
        'helper' => 'Maximum output recorded over 2-3 defrost cycles using HeatpumpMonitor data',
        'group' => 'Heat pump',
        'unit' => 'kW'
    ),

    'refrigerant' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Refrigerant', 
        'group' => 'Heat pump', 
        'options'=>array('R290','R32','CO2','R410A','R210A','R134A','R407C','R454B','R454C','R452B'),
        'show' => false
    ),

    'uses_backup_heater' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Heat pump has backup heater installed and in use',
        'helper'=> 'This is an inline electric element that can top up the heat pump output mostly for space heating',
        'group' => 'Heat pump',
        'basic' => true,
        'show' => false
    ),

    /* ------------------------------ Space heating ----------------------------- */

    'flow_temp' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Design flow temperature', 
        'group' => 'Space heating',
        'helper' => "Design flow temperature (e.g 45°C at -3°C)",
        'unit' => '°C'
    ),

    'design_temp' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Outside design temperature', 
        'group' => 'Space heating',
        'helper' => "E.g -3°C",
        'unit' => '°C'
    ),

    'wc_curve' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Weather compensation curve', 
        'group' => 'Space heating',
        'helper' => '(if known e.g 0.6)'
    ),


    'space_heat_control_type' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Space heat control type', 
        'group' => 'Space heating',
        'options'=>array(
            'Pure weather compensation, no room influence', 
            'Weather compensation with a little room influence', 
            'Weather compensation with significant room influence',
            'Weather compensation with simple set point control',
            'Room influence only (e.g Auto adapt)', 
            'Manual flow temperature control',
            'Custom controller'
        )
    ),

    'zone_number' => array(
        'type' => 'int(11)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Number of zones', 
        'group' => 'Space heating',
        'default' => 1
    ),
    
    'new_radiators' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'New radiators', 
        'group' => 'Space heating'
    ),

    'old_radiators' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Old radiators', 
        'group' => 'Space heating'
    ),

    'fan_coil_radiators' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Fan coil radiators', 
        'group' => 'Space heating'
    ),

    'UFH' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Underfloor heating', 
        'group' => 'Space heating'
    ),

    'hydraulic_separation' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Hydraulic seperation', 
        'group' => 'Space heating', 
        'options'=> array('None','Low loss header','Buffer','Plate heat exchanger',"Don't know")
    ),

    'volumiser' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Volumiser', 
        'group' => 'Space heating',
        'helper' => "A volumiser is a 2-pipe tank that adds more water volume to the system to reduce cycling"
    ),

    /* --------------------------- Hot water cylinders -------------------------- */

    'dhw_method' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Hot water method', 
        'group' => 'Hot water', 
        'options'=>array(
            'None',
            'Cylinder with coil',
            'Cylinder with plate heat exchanger', 
            'Thermal store (heat exchanger on output)',
            'Phase change store',
            'Other'
        )
    ),

    'cylinder_volume' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Cylinder volume',
        'helper' => 'If applicable',
        'group' => 'Hot water', 
        'unit' => 'litres'
    ),

    'dhw_coil_hex_area' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Coil or heat exchanger area', 
        'group' => 'Hot water', 
        'unit' => 'm²'
    ),

    'dhw_target_temperature' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'DHW target temperature', 
        'group' => 'Hot water',
        'unit' => '°C'
    ),

    'dhw_control_type' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'DHW control type', 
        'group' => 'Hot water',
        'options' => array(
            'Daily scheduled heat up of tank', 
            'Twice daily scheduled heat up of tank', 
            'Automatic top up of tank if temperature drops by 3-6C', 
            'Automatic top up of tank if temperature drops by 6-10C', 
            'Manual control of tank temperature',
            'Not applicable'
        )
    ),

    'legionella_frequency' => array(
        'type' => 'varchar(32)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Legionella frequency',
        'group' => 'Hot water',
        'options' => array('Daily', 'Weekly', 'Fornightly', 'Monthly', 'Other', 'Flexible', 'Disabled', 'No cylinder')
    ),

    'legionella_target_temperature' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Legionella target temperature', 
        'group' => 'Hot water',
        'unit' => '°C'
    ),

    'legionella_immersion' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Legionella cycle uses immersion heater', 
        'group' => 'Hot water',
        'basic' => true
    ),

    /* --------------------------------- Other --------------------------------- */


    'freeze' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Freeze protection', 
        'group' => 'Misc',
        'options' => array('Glycol/water mixture', 'Anti-freeze valves', 'Central heat pump water circulation','Not applicable')
    ),

    /* --------------------------------- Property --------------------------------- */

    'heat_loss' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Heat loss at design temperature', 
        'group' => 'Property',
        'helper' => 'E.g as given in detailed installer assessment', 
        'unit' => 'kW @ -3°C'
    ),

    'property' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Property type',
        'group' => 'Property',
        'options' => array('Detached', 'Semi-detached', 'End-terrace', 'Mid-terrace', 'Flat / appartment', 'Bungalow', 'Office building')
    ),

    'floor_area' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Floor area', 
        'group' => 'Property', 
        'unit' => 'm²'
    ),

    'age' => array(
        'type' => 'varchar(64)',
        'editable' => true, 
        'optional' => false, 
        'name' => 'Age', 
        'group' => 'Property',
        'options' => array('2012 or newer', '1983 to 2011', '1940 to 1982', '1900 to 1939', 'Pre-1900')
    ),

    'insulation' => array(
        'type' => 'varchar(64)',
        'editable' => true, 
        'optional' => false, 
        'name' => 'Insulation level', 
        'group' => 'Property',
        'options' => array('Passivhaus', 'Fully insulated walls, floors and loft', 'Some insulation in walls and loft', 'Cavity wall, plus some loft insulation', 'Non-insulated cavity wall', 'Solid walls')
    ),

    /*
    'EPC_spaceheat_demand' => array(
        'type' => 'int(11)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'EPC space heating demand', 
        'group' => 'Property', 
        'helper' => 'As given on the EPC',
        'unit' => 'kWh/year'
    ),

    'EPC_waterheat_demand' => array(
        'type' => 'int(11)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'EPC water heating demand', 
        'group' => 'Property', 
        'helper' => 'As given on the EPC',
        'unit' => 'kWh/year'
    ),*/

    'heat_demand' => array(
        'type' => 'int(11)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Assessed space heat demand',
        'group' => 'Property', 
        'helper' => 'E.g as given in detailed installer assessment',
        'unit' => 'kWh/year'
    ),

    'water_heat_demand' => array(
        'type' => 'int(11)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Assessed water heat demand',
        'group' => 'Property', 
        'helper' => 'E.g as given in detailed installer assessment',
        'unit' => 'kWh/year'
    ),

    /*
    'kwh_m2' => array(
        'type' => 'float', 
        'editable' => false, 
        'optional' => true, 
        'name' => 'kWh/m²', 
        'group' => 'Property', 
        'helper' => 'Annual space and water heating demand per m2',
        'unit' => 'kWh/m²/yr'
    ),*/

    /* ----------------------------- Electricity tariff ----------------------------- */
    /*
    'electricity_tariff' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Electricity tariff', 
        'group' => 'Tariff & Generation'    
    ),

    'electricity_tariff_type' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Tariff type', 
        'group' => 'Tariff & Generation' ,
        'options' => array('Single fixed rate', 'On peak & Off peak', 'Variable half hourly') 
    ),

    'electricity_tariff_unit_rate_all' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Average unit rate paid for all electricity',
        'helper' => 'Including e.g off peak EV charging',
        'group' => 'Tariff & Generation', 
        'unit' => 'p/kWh'
    ),

    'electricity_tariff_unit_rate_hp' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Average unit rate for heat pump',
        'helper' => 'If known, based on time of use monitoring',
        'group' => 'Tariff & Generation', 
        'unit' => 'p/kWh'
    ),*/

    'solar_pv_generation' => array(
        'type' => 'int(11)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Annual solar PV generation',
        'helper' => 'If applicable', 
        'group' => 'Generation', 
        'unit' => 'kWh'
    ),

    'solar_pv_self_consumption' => array(
        'type' => 'int(11)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Solar PV self consumption', 
        'group' => 'Generation', 
        'unit' => '%'
    ),

    'solar_pv_divert' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Solar PV divert to hot water immersion', 
        'group' => 'Generation'
    ),

    'battery_storage_capacity' => array(
        'type' => 'int(11)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Battery storage capacity', 
        'helper' => 'If applicable',
        'group' => 'Generation', 
        'unit' => 'kWh'
    ),

    /* --------------------------------- Monitoring --------------------------------- */

    'mid_metering' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'MID Metering', 
        'heading' => 'MID',
        'helper' => 'Automatically selected if electric meter is class 1 and heat meter at least class 2',
        'group' => 'Metering',
        'disabled' => true,
        'basic' => true
    ),

    'electric_meter' => array(
        'type' => 'varchar(128)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Electric meter', 
        'group' => 'Metering',
        'options' => array(
            'SDM120 Modbus/MBUS Single Phase (class 1)',
            'SDM220 Modbus/MBUS Single Phase (class 1)',
            'SDM630 Modbus/MBUS Three Phase (class 1)',
            'OpenEnergyMonitor EmonPi v1, EmonTx3 or earlier',
            'OpenEnergyMonitor EmonPi v2, EmonTx4 or newer',
            'Other Modbus/MBUS meter (class 1)',
            'Other pulse output meter (class 1)',
            'Heat pump integration',
            'Other electricity meter'
        )
    ),

    'heat_meter' => array(
        'type' => 'varchar(128)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Heat meter', 
        'group' => 'Metering',
        'options' => array(
            'Axioma Qalcosonic heat meter (class 2)',
            'Sontex heat meter (class 2)',
            'Kamstrup heat meter (class 2)',
            'Sharky heat meter (class 2)',
            'SensoStar heat meter (class 2)',
            'Itron heat meter (class 2)',
            'Danfoss Sono heat meter (class 2)',
            'Ista Ultego heat meter (class 2)',
            'Sika or Grundfos VFS flow meter',
            'Heat pump integration',
            'Other heat meter',
            'No heat meter'
        )
    ),

    'metering_inc_boost' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Includes backup heater', 
        'group' => 'Metering',
        'basic' => true
    ),

    'metering_inc_immersion' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Includes DHW immersion heater', 
        'group' => 'Metering',
        'basic' => true
    ),

    'metering_inc_central_heating_pumps' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Includes primary circulation pump', 
        'group' => 'Metering',
        'basic' => true
    ),

    'metering_inc_secondary_heating_pumps' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Includes secondary circulation pump/s', 
        'group' => 'Metering',
        'basic' => true
    ),

    'metering_inc_brine_pumps' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Includes ground source brine pumps', 
        'group' => 'Metering',
        'basic' => true
    ),

    'metering_inc_controls' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Includes indoor controller or other controls', 
        'group' => 'Metering',
        'basic' => true
    ),
    
    'indoor_temperature' => array(
        'type' => 'tinyint(1)',
        'editable' => true, 
        'optional' => true, 
        'name' => 'Includes indoor temperature sensing', 
        'group' => 'Metering',
        'basic' => true
    ),

    'notes' => array(
        'type' => 'text', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Any other notes or comments', 
        'group' => 'Notes',
        'basic' => true
    ),
    
    'data_flag' => array('type' => 'tinyint(1)', 'editable' => true, 'optional' => true, 'name' => 'Data issue flag', 'group' => 'Metering'),
    'data_flag_note' => array(
        'type' => 'varchar(128)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Data issue flag note',
        'group' => 'Metering'
    ),


    /* ----------------------------- Measurements ----------------------------- */

    /*
    // Disabled for now, not enough data to make this useful
    'measured_emitter_spec' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => true,
        'show' => true,
        'name' => 'Measured emitter spec', 
        'group' => 'Measurements',
        'helper' => 'Measured emitter output at DT50', 
        'unit' => 'kW @ DT50'
    ),

    'measured_system_volume' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => true,
        'show' => true,
        'name' => 'Measured system volume', 
        'group' => 'Measurements',
        'helper' => 'Measured system volume from Heatpump dashboard', 
        'unit' => 'L'
    ),
    */

    'measured_base_DT' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => true,
        'show' => false,
        'name' => 'Measured base DT', 
        'group' => 'Measurements',
        'helper' => 'Use the heatpumpmonitor heat loss tool to fill this in once you have enough data', 
        'unit' => '°K'
    ),

    'measured_design_DT' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => true, 
        'show' => false,
        'name' => 'Measured design DT', 
        'group' => 'Measurements',
        'helper' => 'Use the heatpumpmonitor heat loss tool to fill this in once you have enough data', 
        'unit' => 'kW @ -3°C'
    ),

    'measured_heat_loss' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => true, 
        'show' => false,
        'name' => 'Measured heat demand', 
        'group' => 'Measurements',
        'helper' => 'Use the heatpumpmonitor heat loss tool to fill this in once you have enough data',
        'unit' => 'kW'
    ),

    'measured_heat_loss_range' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => true, 
        'show' => false,
        'name' => 'Measured heat demand range', 
        'group' => 'Measurements',
        'helper' => 'Use the heatpumpmonitor heat loss tool to fill this in once you have enough data',
        'unit' => 'kW'
    ),

    'measured_mean_flow_temp_coldest_day' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => true, 
        'show' => false,
        'name' => 'Mean flow temperature when running on coldest day', 
        'group' => 'Measurements',
        'helper' => '',
        'unit' => '°C'
    ),

    'measured_max_flow_temp_coldest_day' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => true, 
        'show' => false,
        'name' => 'Max flow temperature for space heating on coldest day', 
        'group' => 'Measurements',
        'helper' => '',
        'unit' => '°C'
    ),

    'measured_outside_temp_coldest_day' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => true, 
        'show' => false,
        'name' => 'Outside temperature on coldest day', 
        'group' => 'Measurements',
        'helper' => '',
        'unit' => '°C'
    ),

    'measured_room_temp_coldest_day' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => true, 
        'show' => false,
        'name' => 'Room temperature on coldest day', 
        'group' => 'Measurements',
        'helper' => '',
        'unit' => '°C'
    )
);

$schema['system_stats_daily'] = array(
    // System ID
    'id' => array('type' => 'int(11)'),
    
    // Start of day timestamp
    'timestamp' => array('type' => 'int(11)'),
    
    // Full period stats
    'combined_elec_kwh' => array('type' => 'float', 'name'=>'Electricity consumption', 'group'=>'Stats: Combined', 'dp'=>0, 'unit'=>'kWh'),
    'combined_heat_kwh' => array('type' => 'float', 'name'=>'Heat output', 'group'=>'Stats: Combined', 'dp'=>0, 'unit'=>'kWh'),
    'combined_cop' => array('type' => 'float', 'name'=>'COP', 'heading'=>"COP", 'group'=>'Stats: Combined', 'dp'=>1, 'unit'=>''),
    'combined_data_length' => array('type' => 'float', 'name'=>'Length', 'group'=>'Stats: Combined', 'dp'=>0, 'unit'=>''),
    'combined_elec_mean' => array('type' => 'float', 'name'=>'Elec mean', 'group'=>'Stats: Combined', 'dp'=>0, 'unit'=>'W'),
    'combined_heat_mean' => array('type' => 'float', 'name'=>'Heat mean', 'group'=>'Stats: Combined', 'dp'=>0, 'unit'=>'W'),
    'combined_flowT_mean' => array('type' => 'float', 'name'=>'FlowT mean', 'group'=>'Stats: Combined', 'dp'=>1, 'unit'=>'°C'),
    'combined_returnT_mean' => array('type' => 'float', 'name'=>'ReturnT mean', 'group'=>'Stats: Combined', 'dp'=>1, 'unit'=>'°C'),
    'combined_outsideT_mean' => array('type' => 'float', 'name'=>'OutsideT mean', 'group'=>'Stats: Combined', 'dp'=>1, 'unit'=>'°C'),
    'combined_roomT_mean' => array('type' => 'float', 'name'=>'RoomT mean', 'group'=>'Stats: Combined', 'dp'=>1, 'unit'=>'°C'),
    'combined_prc_carnot' => array('type' => 'float', 'name'=>'% Carnot', 'group'=>'Stats: Combined', 'dp'=>1, 'unit'=>'%'),
    'combined_cooling_kwh' => array('type' => 'float', 'name'=>'Cooling energy', 'group'=>'Stats: Combined', 'dp'=>0, 'unit'=>'kWh'),
    'combined_starts' => array('type' => 'float', 'name'=>'Starts', 'group'=>'Stats: Combined', 'dp'=>0, 'unit'=>''),
    'combined_starts_per_hour' => array('type' => 'float', 'name'=>'Starts per hour', 'group'=>'Stats: Combined', 'dp'=>2, 'unit'=>''),
    
    // When Running
    'running_elec_kwh' => array('type' => 'float', 'name'=>'Electricity consumption', 'group'=>'Stats: When Running', 'dp'=>0, 'unit'=>'kWh'),
    'running_heat_kwh' => array('type' => 'float', 'name'=>'Heat output', 'group'=>'Stats: When Running', 'dp'=>0, 'unit'=>'kWh'),
    'running_cop' => array('type' => 'float', 'name'=>'COP', 'heading'=>"Running<br>COP", 'group'=>'Stats: When Running', 'dp'=>1, 'unit'=>''),
    'running_data_length' => array('type' => 'float', 'name'=>'Data length', 'group'=>'Stats: When Running', 'dp'=>0, 'unit'=>''),
    'running_elec_mean' => array('type' => 'float', 'name'=>'Elec mean', 'group'=>'Stats: When Running', 'dp'=>0, 'unit'=>'W'),
    'running_heat_mean' => array('type' => 'float', 'name'=>'Heat mean', 'group'=>'Stats: When Running', 'dp'=>0, 'unit'=>'W'),
    'running_flowT_mean' => array('type' => 'float', 'name'=>'FlowT mean', 'group'=>'Stats: When Running', 'dp'=>1, 'unit'=>'°C'),
    'running_returnT_mean' => array('type' => 'float', 'name'=>'ReturnT mean', 'group'=>'Stats: When Running', 'dp'=>1, 'unit'=>'°C'),
    'running_outsideT_mean' => array('type' => 'float', 'name'=>'OutsideT mean', 'group'=>'Stats: When Running', 'dp'=>1, 'unit'=>'°C'),
    'running_roomT_mean' => array('type' => 'float', 'name'=>'RoomT mean', 'group'=>'Stats: When Running', 'dp'=>1, 'unit'=>'°C'),
    'running_prc_carnot' => array('type' => 'float', 'name'=>'% Carnot', 'group'=>'Stats: When Running', 'dp'=>1, 'unit'=>'%'),
    
    // Space heating
    'space_elec_kwh' => array('type' => 'float', 'name'=>'Electricity consumption', 'group'=>'Stats: Space heating', 'dp'=>0, 'unit'=>'kWh'),
    'space_heat_kwh' => array('type' => 'float', 'name'=>'Heat output', 'group'=>'Stats: Space heating', 'dp'=>0, 'unit'=>'kWh'),
    'space_cop' => array('type' => 'float', 'name'=>'COP', 'heading'=>"Space<br>COP", 'group'=>'Stats: Space heating', 'dp'=>1, 'unit'=>''),
    'space_data_length' => array('type' => 'float', 'name'=>'Data length', 'group'=>'Stats: Space heating', 'dp'=>0, 'unit'=>''),
    'space_elec_mean' => array('type' => 'float', 'name'=>'Elec mean', 'group'=>'Stats: Space heating', 'dp'=>0, 'unit'=>'W'),
    'space_heat_mean' => array('type' => 'float', 'name'=>'Heat mean', 'group'=>'Stats: Space heating', 'dp'=>0, 'unit'=>'W'),
    'space_flowT_mean' => array('type' => 'float', 'name'=>'FlowT mean', 'group'=>'Stats: Space heating', 'dp'=>1, 'unit'=>'°C'),
    'space_returnT_mean' => array('type' => 'float', 'name'=>'ReturnT mean', 'group'=>'Stats: Space heating', 'dp'=>1, 'unit'=>'°C'),
    'space_outsideT_mean' => array('type' => 'float', 'name'=>'OutsideT mean', 'group'=>'Stats: Space heating', 'dp'=>1, 'unit'=>'°C'),
    'space_roomT_mean' => array('type' => 'float', 'name'=>'RoomT mean', 'group'=>'Stats: Space heating', 'dp'=>1, 'unit'=>'°C'),
    'space_prc_carnot' => array('type' => 'float', 'name'=>'% Carnot', 'group'=>'Stats: Space heating', 'dp'=>1, 'unit'=>'%'),
        
    // Water heating
    'water_elec_kwh' => array('type' => 'float', 'name'=>'Electricity consumption', 'group'=>'Stats: Water heating', 'dp'=>0, 'unit'=>'kWh'),
    'water_heat_kwh' => array('type' => 'float', 'name'=>'Heat output', 'group'=>'Stats: Water heating', 'dp'=>0, 'unit'=>'kWh'),
    'water_cop' => array('type' => 'float', 'name'=>'COP', 'heading'=>"DHW", 'group'=>'Stats: Water heating', 'dp'=>1, 'unit'=>''),
    'water_data_length' => array('type' => 'float', 'name'=>'Data length', 'group'=>'Stats: Water heating', 'dp'=>0, 'unit'=>''),
    'water_elec_mean' => array('type' => 'float', 'name'=>'Elec mean', 'group'=>'Stats: Water heating', 'dp'=>0, 'unit'=>'W'),
    'water_heat_mean' => array('type' => 'float', 'name'=>'Heat mean', 'group'=>'Stats: Water heating', 'dp'=>0, 'unit'=>'W'),
    'water_flowT_mean' => array('type' => 'float', 'name'=>'FlowT mean', 'group'=>'Stats: Water heating', 'dp'=>1, 'unit'=>'°C'),
    'water_returnT_mean' => array('type' => 'float', 'name'=>'ReturnT mean', 'group'=>'Stats: Water heating', 'dp'=>1, 'unit'=>'°C'),
    'water_outsideT_mean' => array('type' => 'float', 'name'=>'OutsideT mean', 'group'=>'Stats: Water heating', 'dp'=>1, 'unit'=>'°C'),
    'water_roomT_mean' => array('type' => 'float', 'name'=>'RoomT mean', 'group'=>'Stats: Water heating', 'dp'=>1, 'unit'=>'°C'),
    'water_prc_carnot' => array('type' => 'float', 'name'=>'% Carnot', 'group'=>'Stats: Water heating', 'dp'=>1, 'unit'=>'%'),

    // Cooling
    'cooling_elec_kwh' => array('type' => 'float', 'name'=>'Electricity consumption', 'group'=>'Stats: Cooling', 'dp'=>0, 'unit'=>'kWh'),
    'cooling_heat_kwh' => array('type' => 'float', 'name'=>'Heat output', 'group'=>'Stats: Cooling', 'dp'=>0, 'unit'=>'kWh'),
    'cooling_cop' => array('type' => 'float', 'name'=>'COP', 'heading'=>"Cooling<br>COP", 'group'=>'Stats: Cooling', 'dp'=>1, 'unit'=>''),
    'cooling_data_length' => array('type' => 'float', 'name'=>'Data length', 'group'=>'Stats: Cooling', 'dp'=>0, 'unit'=>''),
    'cooling_elec_mean' => array('type' => 'float', 'name'=>'Elec mean', 'group'=>'Stats: Cooling', 'dp'=>0, 'unit'=>'W'),
    'cooling_heat_mean' => array('type' => 'float', 'name'=>'Heat mean', 'group'=>'Stats: Cooling', 'dp'=>0, 'unit'=>'W'),
    'cooling_flowT_mean' => array('type' => 'float', 'name'=>'FlowT mean', 'group'=>'Stats: Cooling', 'dp'=>1, 'unit'=>'°C'),
    'cooling_returnT_mean' => array('type' => 'float', 'name'=>'ReturnT mean', 'group'=>'Stats: Cooling', 'dp'=>1, 'unit'=>'°C'),
    'cooling_outsideT_mean' => array('type' => 'float', 'name'=>'OutsideT mean', 'group'=>'Stats: Cooling', 'dp'=>1, 'unit'=>'°C'),
    'cooling_roomT_mean' => array('type' => 'float', 'name'=>'RoomT mean', 'group'=>'Stats: Cooling', 'dp'=>1, 'unit'=>'°C'),
    'cooling_prc_carnot' => array('type' => 'float', 'name'=>'% Carnot', 'group'=>'Stats: Cooling', 'dp'=>1, 'unit'=>'%'),

    // Weighted averages
    'weighted_flowT' => array('type' => 'float', 'name'=>'Weighted flowT', 'group'=>'Weighted averages', 'dp'=>1, 'unit'=>'°C'),
    'weighted_outsideT' => array('type' => 'float', 'name'=>'Weighted outsideT', 'group'=>'Weighted averages', 'dp'=>1, 'unit'=>'°C'),
    'weighted_flowT_minus_outsideT' => array('type' => 'float', 'name'=>'Weighted flowT - outsideT', 'group'=>'Weighted averages', 'dp'=>1, 'unit'=>'°C'),
    'weighted_flowT_minus_returnT' => array('type' => 'float', 'name'=>'Weighted flowT - returnT', 'group'=>'Weighted averages', 'dp'=>1, 'unit'=>'°C'),
    'weighted_elec' => array('type' => 'float', 'name'=>'Weighted elec', 'group'=>'Weighted averages', 'dp'=>0, 'unit'=>'W'),
    'weighted_heat' => array('type' => 'float', 'name'=>'Weighted heat', 'group'=>'Weighted averages', 'dp'=>0, 'unit'=>'W'),
    'weighted_prc_carnot' => array('type' => 'float', 'name'=>'Weighted % Carnot', 'group'=>'Weighted averages', 'dp'=>1, 'unit'=>'%'),
    'weighted_kwh_elec' => array('type' => 'float', 'name'=>'Total elec kWh', 'group'=>'Weighted averages', 'dp'=>0, 'unit'=>'kWh'),
    'weighted_kwh_heat' => array('type' => 'float', 'name'=>'Total heat kWh', 'group'=>'Weighted averages', 'dp'=>0, 'unit'=>'kWh'),
    'weighted_kwh_heat_running' => array('type' => 'float', 'name'=>'Total heat running kWh', 'group'=>'Weighted averages', 'dp'=>0, 'unit'=>'kWh'),
    'weighted_kwh_elec_running' => array('type' => 'float', 'name'=>'Total elec running kWh', 'group'=>'Weighted averages', 'dp'=>0, 'unit'=>'kWh'),
    'weighted_kwh_carnot_elec' => array('type' => 'float', 'name'=>'Total carnot elec kWh', 'group'=>'Weighted averages', 'dp'=>0, 'unit'=>'kWh'),
    'weighted_time_on' => array('type' => 'float', 'name'=>'Total time on', 'group'=>'Weighted averages', 'dp'=>0, 'unit'=>'s'),
    'weighted_time_total' => array('type' => 'float', 'name'=>'Total time', 'group'=>'Weighted averages', 'dp'=>0, 'unit'=>'s'),
    'weighted_cycle_count' => array('type' => 'float', 'name'=>'Total cycle count', 'group'=>'Weighted averages', 'dp'=>0, 'unit'=>''),

    // from energy feeds
    'from_energy_feeds_elec_kwh' => array('type' => 'float', 'name'=>'Electricity consumption', 'group'=>'From energy feeds', 'dp'=>0, 'unit'=>'kWh'),
    'from_energy_feeds_heat_kwh' => array('type' => 'float', 'name'=>'Heat output', 'group'=>'From energy feeds', 'dp'=>0, 'unit'=>'kWh'),
    'from_energy_feeds_cop' => array('type' => 'float', 'name'=>'COP', 'group'=>'From energy feeds', 'dp'=>1, 'unit'=>''),
    
    // Quality
    'quality_elec' => array('type' => 'float', 'name'=>'Quality elec', 'group'=>'Quality', 'dp'=>1, 'unit'=>'%'),
    'quality_heat' => array('type' => 'float', 'name'=>'Quality heat', 'group'=>'Quality', 'dp'=>1, 'unit'=>'%'),
    'quality_flowT' => array('type' => 'float', 'name'=>'Quality flowT', 'group'=>'Quality', 'dp'=>1, 'unit'=>'%'),
    'quality_returnT' => array('type' => 'float', 'name'=>'Quality returnT', 'group'=>'Quality', 'dp'=>1, 'unit'=>'%'),
    'quality_outsideT' => array('type' => 'float', 'name'=>'Quality outsideT', 'group'=>'Quality', 'dp'=>1, 'unit'=>'%'),
    'quality_roomT' => array('type' => 'float', 'name'=>'Quality roomT', 'group'=>'Quality', 'dp'=>1, 'unit'=>'%'),

    // Errors
    'error_air' => array('type' => 'int(11)', 'name'=>'Air error', 'group'=>'Errors', 'dp'=>0, 'unit'=>'s'),
    'error_air_kwh' => array('type' => 'float', 'name'=>'Air error elec kWh', 'group'=>'Errors', 'dp'=>1, 'unit'=>'kWh'),

    // Unit rates
    'unit_rate_agile' => array('type' => 'float', 'name'=>'Unit rate agile', 'group'=>'Unit rates', 'dp'=>1, 'unit'=>'p/kWh'),
    'unit_rate_cosy' => array('type' => 'float', 'name'=>'Unit rate cosy', 'group'=>'Unit rates', 'dp'=>1, 'unit'=>'p/kWh'),
    'unit_rate_go' => array('type' => 'float', 'name'=>'Unit rate GO', 'group'=>'Unit rates', 'dp'=>1, 'unit'=>'p/kWh'),
    'unit_rate_eon_next_pumped_v2' => array('type' => 'float', 'name'=>'Unit rate EON Next Pumped V2', 'group'=>'Unit rates', 'dp'=>1, 'unit'=>'p/kWh'),


);

// Copy the same structure for aggregated stats
$schema['system_stats_monthly_v2'] = $schema['system_stats_daily'];
$schema['system_stats_last7_v2'] = $schema['system_stats_daily'];
$schema['system_stats_last30_v2'] = $schema['system_stats_daily'];
$schema['system_stats_last90_v2'] = $schema['system_stats_daily'];
$schema['system_stats_last365_v2'] = $schema['system_stats_daily'];
$schema['system_stats_all_v2'] = $schema['system_stats_daily'];

// Used for specific date range 05 September 2024 to 04 September 2025
$schema['system_stats_custom'] = $schema['system_stats_daily'];

// Remove the timestamp field from the aggregated stats
unset($schema['system_stats_last7_v2']['timestamp']);
unset($schema['system_stats_last30_v2']['timestamp']);
unset($schema['system_stats_last90_v2']['timestamp']);
unset($schema['system_stats_last365_v2']['timestamp']);
unset($schema['system_stats_all_v2']['timestamp']);
unset($schema['system_stats_custom']['timestamp']);

$schema['system_stats_script'] = array(

    'running' => array('type' => 'int(11)'),
    'systemid' => array('type' => 'int(11)')
    
);

// Schema to hold a list of system_meta changes
// timestamp, systemid, userid, field, old_value, new_value
$schema['system_meta_changes'] = array(
    'timestamp' => array('type' => 'int(11)'),
    'systemid' => array('type' => 'int(11)'),
    'userid' => array('type' => 'int(11)'),
    'field' => array('type' => 'varchar(64)'),
    'old_value' => array('type' => 'text'),
    'new_value' => array('type' => 'text')
);
