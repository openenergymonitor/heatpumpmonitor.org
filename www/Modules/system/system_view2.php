<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

$schema = array();
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
?>
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>


<div id="app" class="bg-light">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container" style="max-width:800px;">
            <h3>{{ system.location }}</h3>
        </div>
    </div>

    <div class="container mt-3" style="max-width:800px">
        <div class="row">
            <div id="chart"></div>
        </div>
    </div>

    <div class="container mt-3" style="max-width:800px">
        <div class="row">
            <table class="table">
                <tbody v-for="group,key in schema_groups">
                <tr>
                    <th style="background-color:#f0f0f0;">{{ key }}</th>
                    <td style="background-color:#f0f0f0;"></td>
                </tr>
                <tr v-for="(field,f) in group">
                    <td>{{ field.name }}</td>
                    <td>{{ system[f] }}</td>
                </tr>
                </tbody>

            </table>

        </div>
    </div>
</div>
<script>
    var schema = <?php echo json_encode($schema['system_meta']); ?>;
    // arrange by group
    var schema_groups = {};
    for (var key in schema) {
        if (schema[key].group) {
            if (!schema_groups[schema[key].group]) {
                schema_groups[schema[key].group] = {};
            }
            schema_groups[schema[key].group][key] = schema[key];
        }
    }

    console.log(schema_groups)

    var app = new Vue({
        el: '#app',
        data: {
            system: <?php echo json_encode($system_data); ?>,
            monthly: [],
            schema_groups: schema_groups
        }
    });


    // CHART

    chart_options = {
        colors_style_guidlines: ['#29ABE2'],
        colors: ['#29AAE3'],
        chart: {
            type: 'bar',
            height: 300,
            toolbar: {
                show: false
            }
        },
        dataLabels: {
            enabled: false
        },
        series: [ ],
        xaxis: { 
            categories: [],
            type: 'datetime'
        },
        yaxis: { 
            title: {
                text: 'COP'
            }
        }
    };
    chart = new ApexCharts(document.querySelector("#chart"), chart_options);
    chart.render();

    axios.get(path+'system/monthly?id='+app.system.id)
        .then(function (response) {
            app.monthly = response.data;
            draw_chart();

        })
        .catch(function (error) {
            console.log(error);
        });
    
    function draw_chart() {
        var x = [];
        var y = [];

        // 12 months of dummy data peak in winter
        for (var i = 0; i < app.monthly.length; i++) {
            x.push(app.monthly[i]['timestamp']*1000);
            y.push(app.monthly[i]['cop']);
        }

        chart_options.xaxis.categories = x;
        chart_options.series = [{
            name: 'COP',
            data: y
        }];

        chart.updateOptions(chart_options);
    }
</script>