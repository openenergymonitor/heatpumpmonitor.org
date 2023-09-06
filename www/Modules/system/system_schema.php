<?php

$schema['system_meta'] = array(

    // Form meta data
    'id' => array('type' => 'int(11)', 'Null' => false, 'Key' => 'PRI', 'Extra' => 'auto_increment', 'editable' => false),
    'userid' => array('type' => 'int(11)', 'editable' => false),
    'published' => array('type' => 'tinyint(1)', 'editable' => false, 'optional' => false, 'name' => 'Published', 'group' => 'Overview'),
    'last_updated' => array('type' => 'int(11)', 'editable' => false, 'name' => 'Last updated', 'group' => 'Overview'),

    /* ------------------------------ Overview ----------------------------- */

    'location' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Location', 
        'helper' => 'Roughly where the heat pump is installed, to nearest city or county',
        'group' => 'Overview'
    ),

    'installer_name' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Installer', 
        'group' => 'Overview',
        'helper' => 'Optional. If you are not the installer we recommend asking the installer if they are happy with their name being displayed. Self install is also an option..'
    ),

    'installer_url' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Installer URL', 
        'group' => 'Overview'
    ),

    'url' => array(
        'type' => 'varchar(128)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'MyHeatpump App URL',
        'helper' => 'Requires an account on emoncms.org, or a self-hosted instance of emoncms',
        'group' => 'Overview'
    ),

    'share' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Share', 
        'group' => 'Overview'
    ),

    /* ------------------------------ Heat pump system ----------------------------- */

    'hp_model' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Heat pump make & model',
        'group' => 'Heat pump'
    ),

    'hp_type' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Heat pump type', 
        'group' => 'Heat pump', 
        'options'=>array('Air Source','Ground Source','Water Source','Air-to-Air','Other')
    ),

    'hp_output' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Heat pump output', 
        'helper' => 'This is the badge output not necessarily the maximum output as given on the datasheet',
        'group' => 'Heat pump',
        'unit' => 'kW'
    ),

    'refrigerant' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Refrigerant', 
        'group' => 'Heat pump', 
        'options'=>array('R290','R32','CO2','R410A','R210A','R134A','R407C')
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

    /* ------------------------------ Heat emitters ----------------------------- */

    'new_radiators' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'New radiators', 
        'group' => 'Space heating'
    ),

    'old_radiators' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Old radiators', 
        'group' => 'Space heating'
    ),

    'fan_coil_radiators' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Fan coil radiators', 
        'group' => 'Space heating'
    ),

    'UFH' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => false, 
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
    /* ------------------------------ System controls ----------------------------- */

    'flow_temp' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Design flow temperature', 
        'group' => 'Heat pump controls',
        'helper' => "Design flow temperature (e.g 45°C at -3°C)",
        'unit' => '°C'
    ),

    'design_temp' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Outside design temperature', 
        'group' => 'Heat pump controls',
        'helper' => "E.g -3°C",
        'unit' => '°C'
    ),

    'flow_temp_typical' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => false, 
        'name' => 
        'Typical flow temperature', 
        'helper' => "Typical flow temperature (e.g 35°C at 6°C)",
        'group' => 'Heat pump controls', 
        'unit' => '°C @ 6°C'
    ),

    'wc_curve' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Weather compensation curve', 
        'group' => 'Heat pump controls',
        'helper' => '(if known e.g 0.6)'
    ),

    'freeze' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Freeze protection', 
        'group' => 'Heat pump controls',
        'options' => array('Glycol/water mixture', 'Anti-freeze valves', 'Central heat pump water circulation')
    ),
    
    'zone_number' => array(
        'type' => 'int(11)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Number of zones', 
        'group' => 'Heat pump controls'
    ),
    
    'space_heat_control_type' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Space heat control type', 
        'group' => 'Heat pump controls', 
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

    'dhw_control_type' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'DHW control type', 
        'group' => 'Heat pump controls',
        'options' => array(
            'Daily scheduled heat up of tank', 
            'Twice daily scheduled heat up of tank', 
            'Automatic top up of tank if temperature drops by 3-6C', 
            'Automatic top up of tank if temperature drops by 6-10C', 
            'Manual control of tank temperature',
            'Not applicable'
        )
    ),

    'dhw_target_temperature' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'DHW target temperature', 
        'group' => 'Heat pump controls', 
        'unit' => '°C'
    ),

    'legionella_frequency' => array(
        'type' => 'varchar(32)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Legionella frequency', 
        'group' => 'Heat pump controls',
        'options' => array('Daily', 'Weekly', 'Fornightly', 'Monthly', 'Other', 'Flexible', 'Disabled', 'No cylinder')
    ),

    'legionella_target_temperature' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Legionella target temperature', 
        'group' => 'Heat pump controls', 
        'unit' => '°C'
    ),

    /* --------------------------------- Property --------------------------------- */

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

    'heat_demand' => array(
        'type' => 'int(11)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Detailed assessment space heat demand',
        'group' => 'Property', 
        'helper' => 'E.g as given in detailed installer assessment',
        'unit' => 'kWh/year'
    ),

    'water_heat_demand' => array(
        'type' => 'int(11)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Detailed assessment water heat demand',
        'group' => 'Property', 
        'helper' => 'E.g as given in detailed installer assessment',
        'unit' => 'kWh/year'
    ),

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
    ),

    'heat_loss' => array(
        'type' => 'float', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Heat loss at design temperature', 
        'group' => 'Property',
        'helper' => 'E.g as given in detailed installer assessment', 
        'unit' => 'kW @ -3°C'
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

    'kwh_m2' => array(
        'type' => 'float', 
        'editable' => false, 
        'optional' => true, 
        'name' => 'kWh/m²', 
        'group' => 'Property', 
        'helper' => 'Annual space and water heating demand per m2',
        'unit' => 'kWh/m²/yr'
    ),

    /* ----------------------------- Electricity tariff ----------------------------- */

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
    /*
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
        'group' => 'Tariff & Generation', 
        'unit' => 'kWh'
    ),

    'solar_pv_self_consumption' => array(
        'type' => 'int(11)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Solar PV self consumption', 
        'group' => 'Tariff & Generation', 
        'unit' => '%'
    ),

    'solar_pv_divert' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Solar PV divert to hot water immersion', 
        'group' => 'Tariff & Generation'
    ),

    'battery_storage_capacity' => array(
        'type' => 'int(11)', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Battery storage capacity', 
        'helper' => 'If applicable',
        'group' => 'Tariff & Generation', 
        'unit' => 'kWh'
    ),

    /* --------------------------------- Monitoring --------------------------------- */

    'mid_metering' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'MID Metering', 
        'helper' => 'Tick if electric meter is class 1 and heat meter at least class 2',
        'group' => 'Metering'
    ),

    'electric_meter' => array(
        'type' => 'varchar(128)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Electric meter', 
        'group' => 'Metering',
        'options' => array(
            'OpenEnergyMonitor EmonPi v1, EmonTx3 or earlier',
            'OpenEnergyMonitor EmonPi v2, EmonTx4 or newer',
            'SDM120 Modbus/MBUS Single Phase (class 1)',
            'SDM220 Modbus/MBUS Single Phase (class 1)',
            'SDM630 Modbus/MBUS Three Phase (class 1)',
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
            'Sontex heat meter (class 2)',
            'Kamstrup heat meter (class 2)',
            'Sharky heat meter (class 2)',
            'Qalcosonic heat meter (class 2)',
            'SensoStar heat meter (class 2)',
            'Itron heat meter (class 2)',
            'Danfoss Sono heat meter (class 2)',
            'Ista Ultego heat meter (class 2)',
            'Sika or Grundfos VFS flow meter',
            'Heat pump integration',
            'Other heat meter'
        )
    ),

    'metering_inc_boost' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Includes booster & immersion heater', 
        'group' => 'Metering'
    ),

    'metering_inc_central_heating_pumps' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Includes central heating pumps', 
        'group' => 'Metering'
    ),

    'metering_inc_brine_pumps' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Includes ground source brine pumps', 
        'group' => 'Metering'
    ),

    'metering_inc_controls' => array(
        'type' => 'tinyint(1)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Includes indoor controller or other controls', 
        'group' => 'Metering'
    ),

    'notes' => array(
        'type' => 'text', 
        'editable' => true, 
        'optional' => true, 
        'name' => 'Any other notes or comments', 
        'group' => 'Notes'
    ),
);

$schema['system_stats_last30'] = array(
    // System ID
    'id' => array('type' => 'int(11)'),

    // Full period stats
    'elec_kwh' => array('type' => 'float', 'name'=>'Last 30 days electricity consumption', 'group'=>'Stats'),
    'heat_kwh' => array('type' => 'float', 'name'=>'Last 30 days heat output', 'group'=>'Stats'),
    'cop' => array('type' => 'float', 'name'=>'Last 30 days COP', 'group'=>'Stats'),
    'since' => array('type' => 'int(11)'),
    'data_length' => array('type' => 'int(11)'),

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

    // standby
    'standby_threshold' => array('type' => 'float'),
    'standby_kwh' => array('type' => 'float'),

    // quality
    'quality_elec' => array('type' => 'int(11)'),
    'quality_heat' => array('type' => 'int(11)'),
    'quality_flow' => array('type' => 'int(11)'),
    'quality_return' => array('type' => 'int(11)'),
    'quality_outside' => array('type' => 'int(11)'),
    'data_start' => array('type' => 'int(11)')
);

// Monthly stats
$schema['system_stats_monthly'] = array(
    // System ID
    'id' => array('type' => 'int(11)'),
    // Timestamp of the start of the month
    'timestamp' => array('type' => 'int(11)'),

    // Full period stats
    'elec_kwh' => array('type' => 'float', 'name'=>'Electricity (kWh)', 'group'=>'Stats'),
    'heat_kwh' => array('type' => 'float', 'name'=>'Heat (kWh)', 'group'=>'Stats'),
    'cop' => array('type' => 'float', 'name'=>'SCOP', 'group'=>'Stats'),
    'since' => array('type' => 'int(11)'),
    'data_length' => array('type' => 'int(11)'),

    // when running
    'when_running_elec_kwh' => array('type' => 'float', 'name'=>'Electricity running (kWh)', 'group'=>'When Running'),
    'when_running_heat_kwh' => array('type' => 'float', 'name'=>'Heat running (kWh)', 'group'=>'When Running'),
    'when_running_cop' => array('type' => 'float', 'name'=>'COP (Running)', 'group'=>'When Running'),
    'when_running_elec_W' => array('type' => 'float'),
    'when_running_heat_W' => array('type' => 'float'),
    'when_running_flowT' => array('type' => 'float', 'name'=>'Flow temperature (°C)', 'group'=>'When Running'),
    'when_running_returnT' => array('type' => 'float', 'name'=>'Return temperature (°C)', 'group'=>'When Running'),
    'when_running_flow_minus_return' => array('type' => 'float', 'name'=>'Flow minus return (°C)', 'group'=>'When Running'),
    'when_running_outsideT' => array('type' => 'float', 'name'=>'Outside temperature (°C)', 'group'=>'When Running'),
    'when_running_flow_minus_outside' => array('type' => 'float', 'name'=>'Flow minus outside (°C)', 'group'=>'When Running'),
    'when_running_carnot_prc' => array('type' => 'float', 'name'=>'Carnot efficiency (%)', 'group'=>'When Running'),

    // standby
    'standby_threshold' => array('type' => 'float', 'name'=>'Standby threshold (W)', 'group'=>'Standby'),
    'standby_kwh' => array('type' => 'float', 'name'=>'Standby (kWh)', 'group'=>'Standby'),

    // quality
    'quality_elec' => array('type' => 'int(11)', 'name'=>'Electricity data quality', 'group'=>'Quality'),
    'quality_heat' => array('type' => 'int(11)', 'name'=>'Heat data quality', 'group'=>'Quality'),
    'quality_flow' => array('type' => 'int(11)', 'name'=>'Flow temperature data quality', 'group'=>'Quality'),
    'quality_return' => array('type' => 'int(11)', 'name'=>'Return temperature data quality', 'group'=>'Quality'),
    'quality_outside' => array('type' => 'int(11)', 'name'=>'Outside temperature data quality', 'group'=>'Quality'),
    'data_start' => array('type' => 'int(11)', 'name'=>'Data start', 'group'=>'Stats')
);

$schema['system_stats_last365'] = array(
    'id' => array('type' => 'int(11)'),
    'elec_kwh' => array('type' => 'float', 'name'=>'Last 365 days electricity consumption', 'group'=>'Stats'),
    'heat_kwh' => array('type' => 'float', 'name'=>'Last 365 days heat output', 'group'=>'Stats'),
    'cop' => array('type' => 'float', 'name'=>'Last 365 days COP', 'group'=>'Stats'),
    'since' => array('type' => 'int(11)'),
    'data_length' => array('type' => 'int(11)'),
    'data_start' => array('type' => 'int(11)')

);
