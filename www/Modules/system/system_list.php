<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>
<script src="https://cdn.plot.ly/plotly-2.16.1.min.js"></script>
<script src="Lib/clipboard.js"></script>
<script src="<?php echo $path; ?>Modules/system/system_list_chart.js?v=27"></script>

<style>
    .sticky {
        position: sticky;
        top: 20px;
    }
    .H4 {
        font-size:14px;
        color:green;
    }

    .H3 {
        font-size:14px;
        color:orange;
    }

    .H2 {
        font-size:14px;
        color:orange;
    }
    
    .H1 {
        font-size:14px;
        color:red;
    }
</style>

<div id="app" class="bg-light">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container-fluid">

            <div class="row">
                <div class="col-12 col-sm-12 col-md-6 col-lg-6 col-xl-8">

                    <h3 v-if="mode=='user'">My Systems</h3>
                    <h3 v-if="mode=='admin'">Admin Systems</h3>

                    <p v-if="mode=='user'">Add, edit and view systems associated with your account.</p>
                    <p v-if="mode=='admin'">Add, edit and view all systems</p>
                    
                    <p v-if="mode=='public' && showContent">If you're monitoring a heat pump with <a href="https://openenergymonitor.org">OpenEnergyMonitor</a> login to add your system</p>
                    <p v-if="mode=='public' && showContent">Join the discussion on the <a href="https://community.openenergymonitor.org/c/hardware/heatpump/47">Forum</a></p>
                    
                    <button v-if="mode!='public'" class="btn btn-primary" @click="create">Add new system</button>
                </div>

                        
                <div class="col-12 col-sm-12 col-md-6 col-lg-6 col-xl-auto ms-auto">
                    <div class="input-group">
                        <span class="input-group-text">Stats time period</span>

                        <select class="form-control" v-model="stats_time_start" @change="stats_time_start_change" style="width:130px">
                            <option value="all">All</option>
                            <option value="last7">Last 7 days</option>
                            <option value="last30">Last 30 days</option>
                            <option value="last90">Last 90 days</option>
                            <option value="last365">Last 365 days</option>
                            <option v-for="month in available_months_start">{{ month }}</option>
                        </select>
                        
                        <span class="input-group-text" v-if="stats_time_end!='only'">to</span>

                        <select class="form-control" v-model="stats_time_end" v-if="stats_time_start!='all' && stats_time_start!='last7' && stats_time_start!='last30' && stats_time_start!='last90' && stats_time_start!='last365'" @change="stats_time_end_change" style="width:120px">
                            <option value="only">Only</option>
                            <option v-for="month in available_months_end">{{ month }}</option>
                        </select>
                        <button class="btn btn-primary" @click="toggle_chart"><i class="fa fa-chart-bar"></i></button>
                    </div>

                    <div class="input-group" style="margin-top: 12px">
                        <div class="input-group-text">Filter</div>
                        <input class="form-control" name="query" v-model="filterKey" style="width:100px" @change="filter_systems">

                        <div class="input-group-text">Min days</div>
                        <input class="form-control" name="query" v-model="minDays" style="width:100px" @change="filter_systems">
                    </div>
                    
      
            </div>

        </div>


    </div>

    <div class="container-fluid">
        <div class="row">
            <!-- Side bar with field selection -->
            <div class="col-12 col-sm-12 col-md-12 col-lg-12 col-xl-2">


                <div class="sticky-top">

                    <ul class="list-group mt-3">
                        <li @click="template_view('topofthescops')" :class="'list-group-item list-group-item-action '+(selected_template=='topofthescops'?'active':'')" style="cursor:pointer"><i class="fa fa-trophy" style="margin: 0px 10px 0px 5px"></i> Top of the SCOPs</li>
                        <li @click="template_view('heatpumpfabric')" :class="'list-group-item list-group-item-action '+(selected_template=='heatpumpfabric'?'active':'')" style="cursor:pointer"><i class="fas fa-house-damage" style="margin: 0px 10px 0px 5px"></i> Heatpump + Fabric</li>
                        <li @click="template_view('costs')" :class="'list-group-item list-group-item-action '+(selected_template=='costs'?'active':'')" style="cursor:pointer"><i class="fas fa-pound-sign" style="margin: 0px 15px 0px 8px"></i> Costs</li>
                    </ul>
                    
                    <div class="card mt-3" style="max-height:780px; overflow-y:scroll">
                        <div class="card-header">
                        <button class="btn btn-sm btn-secondary" style="float:right; margin-right:-8px" @click="show_field_selector = !show_field_selector">
                            <i :class="{'fas fa-minus': show_field_selector, 'fas fa-plus': !show_field_selector}"></i>
                        </button>
                        <div style="margin-top:2px; font-size:18px">Add fields</div>
                        </div>
                        <div class="collapse show" :class="{ 'd-none': !show_field_selector, 'd-md-block': show_field_selector }">
                            <ul class="list-group list-group-flush">
                            <template v-for="(group, group_name) in column_groups" v-if="!((stats_time_start=='last365' || stats_time_start=='all') && (group_name=='When Running' || group_name=='Standby'))">
                                <li class="list-group-item" @click="toggle_field_group(group_name)" style="cursor:pointer; background-color:#f7f7f7;">
                                    <!-- plus icon -->
                                    <i :class="(show_field_group[group_name])?'fa fa-angle-up':'fa fa-angle-down'" style="float:right; margin-top:3px; margin-right:3px"></i>
                                    <b>{{ group_name }}</b>
                                </li>
                                <li v-for="column in group" class="list-group-item" v-if="show_field_group[group_name]">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault" @click="select_column(column.key)" :checked="selected_columns.includes(column.key)">
                                    <label class="form-check-label" for="flexCheckDefault" style="font-size:15px">
                                    {{ column.name }}
                                    </label>
                                </div>
                                </li>
                            </template>
                            </ul>
                        </div>
                    </div>
                    
                    <ul class="list-group mt-3">
                      <li class="list-group-item">
                      <b>Show systems with</b>
                      </li>
                      
                      <li class="list-group-item">
                        <div style="color:#666; float:right"> (<span v-html="num_mid"></span>)</div>
                        <input class="form-check-input me-1" type="checkbox" value="" id="show_mid_id" v-model="show_mid" @change="filter_systems">
                        <label class="form-check-label stretched-link" for="show_mid_id">Full MID metering</label>
                      </li>
                      <li class="list-group-item">
                        <div style="color:#666; float:right"> (<span v-html="num_other"></span>)</div>
                        <input class="form-check-input me-1" type="checkbox" value="" id="show_other_id" v-model="show_other" @change="filter_systems">
                        <label class="form-check-label stretched-link" for="show_other_id">Other metering</label>
                      </li>
                      <li class="list-group-item">
                        <div style="color:#666; float:right"> (<span v-html="num_hpint"></span>)</div>
                        <input class="form-check-input me-1" type="checkbox" value="" id="show_hpint_id" v-model="show_hpint" @change="filter_systems">
                        <label class="form-check-label stretched-link" for="show_hpint_id">Heatpump integration</label>
                      </li>
                      <li class="list-group-item">
                        <div style="color:#666; float:right"> (<span v-html="num_flagged"></span>)</div>
                        <input class="form-check-input me-1" type="checkbox" value="" id="show_flagged_id" v-model="show_errors" @change="filter_systems">
                        <label class="form-check-label stretched-link" for="show_flagged_id">Metering errors </label>
                      </li>
                    </ul>
                    
                </div>
            </div>
            <div class="col-12 col-sm-12 col-md-12 col-lg-12 col-xl-10 mt-3">

                <div class="btn-group" role="group" aria-label="Basic example" v-if="mode=='admin'" style="float:right">
                    <button type="button" class="btn btn-primary" @click="toggle_restricted_list">
                        <i :class="admin_restricted_list?'fa fa-eye':'fa fa-eye-slash'"></i> {{ admin_restricted_list?'Show all systems':'Show restricted' }}
                    </button>
                </div>

                <!-- add button group -->
                <!-- Last 365 days, Last 90 days, Last 30 days, Last 7 days, All -->
                
                <div class="btn-group" role="group" aria-label="Basic example">
                    <button type="button" :class="['btn', stats_time_start === 'last365' ? 'btn-primary' : 'btn-outline-primary']" @click="stats_time_start='last365'; stats_time_start_change()">Last 365 days</button>
                    <button type="button" :class="['btn', stats_time_start === 'last90' ? 'btn-primary' : 'btn-outline-primary']" @click="stats_time_start='last90'; stats_time_start_change()">90 days</button>
                    <button type="button" :class="['btn', stats_time_start === 'last30' ? 'btn-primary' : 'btn-outline-primary']" @click="stats_time_start='last30'; stats_time_start_change()">30 days</button>
                    <button type="button" :class="['btn', stats_time_start === 'last7' ? 'btn-primary' : 'btn-outline-primary']" @click="stats_time_start='last7'; stats_time_start_change()">7 days</button>
                    <button type="button" :class="['btn', stats_time_start === 'all' ? 'btn-primary' : 'btn-outline-primary']" @click="stats_time_start='all'; stats_time_start_change()">All</button>
                </div>
                                
                <div class="input-group mt-3" v-if="selected_template=='costs'">
                    <span class="input-group-text">Tariff</span>
                    <select class="form-select" style="max-width:200px" v-model="tariff_mode" @change="tariff_mode_changed">
                        <option value="flat">Price cap</option>
                        <option value="ovohp">OVO Heat Pump Plus</option>
                        <option value="agile">Octopus Agile</option>
                        <option value="cosy">Octopus Cosy</option>
                        <option value="go">Octopus GO</option>
                        <!--<option value="user">User entered</option>-->
                    </select>
                </div>

                <div class="card mt-3" v-show="chart_enable">
                    <h5 class="card-header">Data explorer</h5>
                    <div class="card-body">

                        <!-- row with x and y axis selection -->
                        <div class="row">

                            <!-- X-axis grouped by group -->
                            <div class="col-lg-4">
                                <div class="input-group mb-3">
                                    <span class="input-group-text">X-axis</span>
                                    <select class="form-control" v-model="selected_xaxis" @change="draw_scatter">
                                        <optgroup v-for="(group, group_name) in column_groups" :label="group_name">
                                            <option v-for="row in group" :value="row.key" v-if="row.name">{{ row.name }}</option>
                                        </optgroup>
                                    </select>
                                </div>
                            </div>

                            <!-- Y-axis grouped by group -->
                            <div class="col-lg-4">
                                <div class="input-group mb-3">
                                    <span class="input-group-text">Y-axis</span>
                                    <select class="form-control" v-model="selected_yaxis" @change="draw_scatter">
                                        <optgroup v-for="(group, group_name) in column_groups" :label="group_name">
                                            <option v-for="row in group" :value="row.key" v-if="row.name">{{ row.name }}</option>
                                        </optgroup>
                                    </select>
                                </div>
                            </div>

                            <!-- Colour map -->
                            <div class="col-lg-4">
                                <div class="input-group mb-3">
                                    <span class="input-group-text">Colour map</span>
                                    <select class="form-control" v-model="selected_color" @change="draw_scatter">
                                        <optgroup v-for="(group, group_name) in column_groups" :label="group_name">
                                            <option v-for="row in group" :value="row.key" v-if="row.name">{{ row.name }}</option>
                                        </optgroup>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <p>{{ chart_info }} <input type="checkbox" v-model="enable_line_best_fit" @change="draw_scatter"></p>
                        <div id="chart"></div>
                    </div>
                </div>

                <table id="custom" class="table table-striped table-sm mt-3">
                    <tr>
                        <th v-if="mode=='admin'" @click="sort('id', 'asc')" style="cursor:pointer">ID</th>
                        <th v-if="mode=='admin'" @click="sort('name', 'asc')" style="cursor:pointer">User
                            <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='name'"></i>
                        </th>
                        <th v-if="mode=='admin'">LINK</th>
                        <th v-for="column in selected_columns" @click="sort(column, 'desc')" style="cursor:pointer" :title="columns[column].helper"><span v-html="columns[column].heading"></span>
                            <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn==column"></i>
                        </th>
                        <th v-if="mode!='public' && public_mode_enabled">Status</th>
                        <th v-if="mode=='public'" :style="(showContent)?'width:80px':'width:20px'">View</th>
                        <th v-if="mode!='public'" :style="(showContent)?'width:120px':'width:20px'"></th>

                    </tr>
                    <tr v-for="(system,index) in fSystems" v-if="mode!='public' || (mode=='public' && system.combined_data_length!=0)">
                        <td v-if="mode=='admin'">{{ system.id }}</td>
                        <td v-if="mode=='admin'" :title="system.username+'\n'+system.email"><span v-if="system.name">{{ system.name }}</span><span v-if="!system.name" style="color:#888">{{ system.username }}</span></td>
                        <td v-if="mode=='admin'"><a v-if="system.emoncmsorg_userid" :href="'https://emoncms.org/admin/setuser?id='+system.emoncmsorg_userid" target="_blank">{{ system.emoncmsorg_userid }}</a></td>
                        <td v-for="column in selected_columns" v-html="column_format(system,column)" v-bind:class="sinceClass(system,column)" style=""></td>
                        <td v-if="mode!='public' && public_mode_enabled">
                            <span v-if="system.share" class="badge bg-success">Shared</span>
                            <span v-if="!system.share" class="badge bg-danger">Private</span>
                            <span v-if="system.published" class="badge bg-success">Published</span>
                            <span v-if="system.share && !system.published && !system.data_flag" class="badge bg-warning">Waiting for review</span>
                            <span v-if="system.share && !system.published && system.data_flag" class="badge bg-secondary">Not published</span>
                        </td>
                        <td>
                            <!--View button-->
                            <a :href="'<?php echo $path;?>system/view?id='+system.id" v-if="mode=='public'">
                                <button class="btn btn-primary btn-sm" title="Summary"><i class="fa fa-list-alt" style="color: #ffffff;"></i></button>
                            </a>

                            <!--Dashboard-->
                            <a :href="system.url" target="_blank" v-if="showContent">
                                <button class="btn btn-secondary btn-sm" title="Dashboard"><i class="fa fa-chart-bar" style="color: #ffffff;"></i></button>
                            </a>

                            <!--Edit button-->
                            <a :href="'<?php echo $path;?>system/edit?id='+system.id" v-if="mode!='public'">
                                <button class="btn btn-warning btn-sm" title="Edit"><i class="fa fa-edit" style="color: #ffffff;"></i></button>
                            </a>

                            <!--Delete button-->
                            <button v-if="mode!='public'" class="btn btn-danger btn-sm" @click="remove(system.id)" title="Delete"><i class="fa fa-trash" style="color: #ffffff;"></i></button>


                        </td>
                    </tr>
                </table>
                
                <div class="card">
                  <h5 class="card-header">Totals</h5>
                  <div class="card-body">
                    <p class="card-text">Number of systems in selection: <b>{{ totals.listed_system_count }}</b></p>
                    <p class="card-text">Average of individual system {{ stats_time_start === 'last365' ? 'SPF' : 'COP' }} values: <b>{{ totals.average_cop | toFixed(1) }}</b></p>
                    <p class="card-text">Average {{ stats_time_start === 'last365' ? 'SPF' : 'COP' }} based on total sum of heat and electric values: <b>{{ totals.average_cop_kwh | toFixed(1) }}</p>
                    <!-- csv export button copy table data to clipboard -->
                    <button class="btn btn-primary" @click="export_csv">Copy table data to clipboard</button>                    
                  </div>
                </div>                
            </div>
        </div>

    </div>
</div>

<script>
    // public, admin, user
    var mode = "<?php echo $mode; ?>";

    var default_settings = {
        mode: 'topofthescops',
        period: 'last365',
        minDays: 330,
        add: '',
        rm: '',
        filter: '',
        tariff: 'flat',
        mid: 1,
        other: 0,
        hpint: 0,
        errors: 0,
        chart: 0,
        selected_xaxis: 'running_flowT_mean',
        selected_yaxis: 'combined_cop',
        selected_color: 'combined_heat_kwh',
    };

    if (mode != 'public') {
        default_settings.mid = 1;
        default_settings.other = 1;
        default_settings.hpint = 1;
        default_settings.errors = 1;
        default_settings.minDays = 0;
    }

    var page_settings = JSON.parse(JSON.stringify(default_settings));

    // Apply these again
    var selected_template = default_settings.mode;
    var stats_time_start = default_settings.period;
    var minDays = default_settings.minDays;
    var filterKey = default_settings.filter;
    // not yet added
    var currentSortColumn = 'combined_cop';
    var currentSortDir = 'desc';
    
    if (mode == 'admin') {
        currentSortColumn = 'id';
    }

    var periods_available = ['last365','last90','last30','last7','all'];

    var default_minDays = {
        'last365': 330,
        'last90': 72,
        'last30': 24,
        'last7': 5,
        'all': 0
    };

    // Get URL parameters
    var urlParams = new URLSearchParams(window.location.search);
    for (var key in default_settings) {
        if (urlParams.has(key)) {
            page_settings[key] = urlParams.get(key);
        }
    }

    // Map these across to the main variables
    var added_columns = [];
    if (page_settings.add) added_columns = page_settings.add.split(',');
    var removed_columns = [];
    if (page_settings.rm) removed_columns = page_settings.rm.split(',');
    var filterKey = page_settings.filter;

    // Validate and apply period
    if (periods_available.includes(page_settings.period)) {
        stats_time_start = page_settings.period;
        minDays = default_minDays[page_settings.period];
    }

    minDays = parseInt(page_settings.minDays);
    if (minDays < 0) minDays = 0;

    // validate mid, other, hpint, errors boolean
    var metering = ['mid','other','hpint','errors'];
    for (var i in metering) {
        if (page_settings[metering[i]] == '1') page_settings[metering[i]] = 1;
        else page_settings[metering[i]] = 0;
    }

    
    if (page_settings.mode =='costs') {
        selected_template = 'costs';
        currentSortColumn = 'combined_heat_unit_cost';
        currentSortDir = 'asc';
    }
    else if (page_settings.mode =='heatpumpfabric') {
        selected_template = 'heatpumpfabric';
        currentSortColumn = 'combined_elec_kwh_per_m2';
        currentSortDir = 'asc';
    }

    var tariff_mode = 'flat';
    var options = ['flat','agile','cosy','go','ovohp']
    if (options.includes(page_settings.tariff)) {
        tariff_mode = page_settings.tariff;
    }

    var show_errors = false;
    if (page_settings.errors == 1) show_errors = true;

    var show_mid = false;
    if (page_settings.mid == 1) show_mid = true;

    var show_other = false;
    if (page_settings.other == 1) show_other = true;

    var show_hpint = false;
    if (page_settings.hpint == 1) show_hpint = true;

    var show_chart = false;
    if (page_settings.chart == 1) show_chart = true;
    
    var columns = <?php echo json_encode($columns); ?>;
    var stats_columns = <?php echo json_encode($stats_columns); ?>;
    var systems = <?php echo json_encode($systems); ?>;

    columns['hp_type'].name = "Source";
    columns['hp_model'].name = "Make & Model";
    columns['hp_output'].name = "Rating";
    // columns['heatgeek'].name = "Training";
    
    
    columns['training'] = { name: "Combined", heading: "Training", group: "Training", helper: "Training" };
    columns['learnmore'] = { name: "Combined", heading: "", group: "Learn more" };
    columns['boundary'] = { name: "Boundary", heading: "Hx", group: "Metering" };
    columns['data_flag'].heading = "";
    

    // Calculate oversizing factor
    columns['oversizing_factor'] = {
        name: 'Oversizing Factor',
        heading: 'Oversizing',
        group: 'Heat pump',
        helper: 'Oversizing factor'
    };
    
    // remove stats_columns id & timestmap
    delete stats_columns.id;
    delete stats_columns.timestamp;

    stats_columns['selected_unit_rate'] = {
        name: "Elec p/kWh",
        heading: "Elec<br>p/kWh",
        group: "Unit rates",
        helper: "Selected electricity unit rate",
        unit: "p/kWh",
        dp: 1
    };

    // post process columns
    var categories = ['combined','running','space','water'];
    var category_names = {
        'combined': 'Combined',
        'running': 'When Running',
        'space': 'Space heating',
        'water': 'Water heating'
    }
    for (var z in categories) {
        let category = categories[z];
        
        stats_columns[category+'_elec_kwh_per_m2'] = {
            name: "Electric kWh/m²",
            heading: "Elec kWh/m²",
            group: "Stats: "+category_names[category],
            helper: "Electricity consumption per m²",
            unit: "kWh/m²",
            dp: 1
        };
        
        stats_columns[category+'_heat_kwh_per_m2'] = {
            name: "Heat kWh/m²",
            heading: "Heat kWh/m²",
            group: "Stats: "+category_names[category],
            helper: "Electricity consumption per m²",
            unit: "kWh/m²",
            dp: 1
        };
        
        stats_columns[category+'_cost'] = {
            name: "Cost",
            heading: "Cost",
            group: "Stats: "+category_names[category],
            helper: "Electricity cost",
            unit: "",
            prepend: "£",
            dp: 0
        };
        
        stats_columns[category+'_heat_unit_cost'] = {
            name: "Heat p/kWh",
            heading: "Heat<br>p/kWh",
            group: "Stats: "+category_names[category],
            helper: "Heat unit cost",
            unit: "p/kWh",
            dp: 2
        };
    }

    // add stats_columns to columns
    for (var key in stats_columns) {
        columns[key] = stats_columns[key];
    }

    // Calculate % demand hot water
    columns['prc_demand_hot_water'] = {
        name: '% Demand Hot Water',
        heading: '%DHW',
        group: 'Stats: Water heating',
        helper: 'Percentage of demand for hot water'
    };
    
    columns['combined_cop'].heading = 'SPF';

    for (var key in columns) {
        if (columns[key].heading === undefined) {
            columns[key].heading = columns[key].name;
        }
    }

    // convert to column groups
    var column_groups = {};
    var show_field_group = {};

    for (var key in columns) {
        var column = columns[key];
        if (column_groups[column.group] == undefined) column_groups[column.group] = [];
        column_groups[column.group].push({key: key, name: column.name, helper: column.helper});
        show_field_group[column.group] = false;
    }

    columns['installer_logo'].heading = "";
    columns['mid_metering'].heading = "MID";
    // columns['electricity_tariff_unit_rate_all'].heading = "Elec<br>p/kWh";
    
    // Template views
    var template_views = {}
    template_views['topofthescops'] = {}
    template_views['topofthescops']['wide'] = ['location', 'installer_logo', 'installer_name', 'training', 'hp_type', 'hp_model', 'hp_output', 'combined_data_length', 'data_flag', 'combined_cop', 'water_cop', 'boundary' , 'mid_metering', 'learnmore'];
    template_views['topofthescops']['narrow'] = ['installer_logo', 'training', 'hp_model', 'hp_output', 'combined_cop', 'learnmore'];

    template_views['heatpumpfabric'] = {}
    template_views['heatpumpfabric']['wide'] = ['installer_logo', 'location', 'property', 'insulation', 'age', 'floor_area', 'hp_type', 'hp_model', 'hp_output', 'combined_cop', 'combined_elec_kwh_per_m2', 'combined_heat_kwh_per_m2'];
    template_views['heatpumpfabric']['narrow'] = ['installer_logo', 'hp_model', 'hp_output', 'combined_elec_kwh_per_m2'];

    template_views['costs'] = {}
    template_views['costs']['wide'] = ['installer_logo', 'training', 'location' , 'hp_type', 'hp_model', 'hp_output', 'electricity_tariff', 'selected_unit_rate', 'combined_cop', 'combined_heat_unit_cost', 'combined_cost', 'learnmore'];
    template_views['costs']['narrow'] = ['installer_logo', 'training', 'hp_model', 'hp_output', 'combined_heat_unit_cost', 'learnmore'];

    // add to template views
    for (var key in template_views) {
        for (var view in template_views[key]) {
            for (var i in added_columns) {
                if (added_columns[i] == 'id') {
                    template_views[key][view].unshift('id');
                } else {
                    template_views[key][view].push(added_columns[i]);
                }
            }
            for (var i in removed_columns) {
                var index = template_views[key][view].indexOf(removed_columns[i]);
                if (index > -1) {
                    template_views[key][view].splice(index, 1);
                }
            }
        }
    }

    // Available months
    // Aug 2023, Jul 2023, Jun 2023 etc for 12 months
    var months = [];
    var d = new Date();
    for (var i = 0; i < 12; i++) {
        months.push(d.toLocaleString('default', { month: 'short' }) + ' ' + d.getFullYear());
        d.setMonth(d.getMonth() - 1);
    }

    // Set boundary
    for (var i = 0; i < systems.length; i++) {
    
        var type = systems[i].hp_type;

        // Brine pump
        var metering_inc_brine = systems[i].metering_inc_brine_pumps;

        // Backup heater
        var uses_backup = systems[i].uses_backup_heater;
        var metering_inc_backup = systems[i].metering_inc_boost;

        // Immersion
        var legionella = systems[i].legionella_frequency;
        var uses_immersion = systems[i].legionella_immersion;
        var metering_inc_immersion = systems[i].metering_inc_immersion;

        // Primary pumps
        var metering_inc_primary_pump = systems[i].metering_inc_central_heating_pumps;

        // Secondary pumps
        var hydraulic_separation = systems[i].hydraulic_separation;
        var metering_inc_secondary_pumps = systems[i].metering_inc_secondary_heating_pumps;

        // Controls
        var controls = systems[i].metering_inc_controls;

        // ---- Boundary logic ----
        
        // Start at 4
        var helper = "";

        if ((type == "Ground Source" || type == "Water Source")) {
            helper = "- Compressor metered\n";
        } else {
            helper = "- Compressor and fan metered\n";
        }

        var boundary_code = 4;

        // If hydraulic seperation is used and secondary pumps are not metered then boundary can not be higher than 3
        if (hydraulic_separation != 'None' && metering_inc_secondary_pumps==0) {
            boundary_code = 3;
            helper += "- Hydraulic separation used but secondary pumps/fans not metered\n";
        }

        if (hydraulic_separation != 'None' && metering_inc_secondary_pumps==1) {
            helper += "- Hydraulic separation used and secondary pumps/fans metered\n";
        }

        // If primary pumps are not metered then boundary can not be higher than 3
        if (!metering_inc_primary_pump) {
            boundary_code = 3;
            helper += "- Primary pump not metered\n";
        } else {
            helper += "- Primary pump metered\n";
        }

        // If immersion heater is used and not metered then boundary can not be higher than 2
        if (uses_immersion == 1 && metering_inc_immersion == 0) {
            boundary_code = 2;
            helper += "- Immersion heater used but not metered\n";
        }
        if (uses_immersion == 1 && metering_inc_immersion == 1) {
            helper += "- Immersion heater used and metered\n";
        }
        if (uses_immersion == 0) {
            helper += "- Immersion heater not installed or used\n";
        }

        // If backup heater is used and not metered then boundary can not be higher than 2
        if (uses_backup == 1 && metering_inc_backup == 0) {
            boundary_code = 2;
            helper += "- Backup heater used but not metered\n";
        }
        if (uses_backup == 1 && metering_inc_backup == 1) {
            helper += "- Backup heater used and metered\n";
        }
        if (uses_backup == 0) {
            helper += "- Backup heater not installed or used\n";
        }

        // If brine pump is used and not metered then boundary can not be higher than 1
        if ((type == "Ground Source" || type == "Water Source")) {
            if (metering_inc_brine == 0) {
                boundary_code = 1;
                helper += "- Brine pump used but not metered\n";
            } else {
                helper += "- Brine pump used and metered\n";
            }
        }

        // Air to air is always 2
        if (type == "Air-to-Air") {
            boundary_code = 2;
            helper = "";
        }

        systems[i].boundary = "<span class='H"+boundary_code+"' title='System boundary H"+boundary_code+"\n"+helper+"'>H"+boundary_code+"</span>";
        
        systems[i].boundary_code = boundary_code;
    }

    // Calculate over-sizing factor
    for (var z in systems) {
        let system = systems[z];
        let oversizing_factor = 0;
        if (system['measured_heat_loss']>0 && system['hp_max_output']>0) {
            oversizing_factor = system['hp_max_output'] / system['measured_heat_loss'];
        }
        
        if (oversizing_factor != null) {
            oversizing_factor = oversizing_factor.toFixed(1)*1;
        }
        
        systems[z]['oversizing_factor'] = oversizing_factor;
    }

    var app = new Vue({
        el: '#app',
        data: {
            systems: systems,
            fSystems: [],
            mode: "<?php echo $mode; ?>",
            chart_enable: show_chart,
            columns: columns,
            column_groups: column_groups,
            show_field_group: show_field_group,
            selected_columns: [],
            currentSortColumn: currentSortColumn,
            currentSortDir: currentSortDir,
            // stats time selection
            stats_time_start: stats_time_start,
            stats_time_end: "only",
            stats_time_range: false,
            available_months_start: months,
            available_months_end: months,
            filterKey: filterKey,
            minDays: minDays,
            showContent: true,
            show_field_selector: false,
            public_mode_enabled: public_mode_enabled,
            selected_template: selected_template,

            show_mid: show_mid,
            show_other: show_other,
            show_hpint: show_hpint,
            show_errors: show_errors,

            num_flagged: 0,
            num_mid: 0,
            num_other: 0,
            num_hpint: 0,
            num_class2_heat: 0,
            num_class1_elec: 0,
            num_other_metering: 0,
            tariff_mode: tariff_mode,
            selected_xaxis: page_settings.selected_xaxis,
            selected_yaxis: page_settings.selected_yaxis,
            selected_color: page_settings.selected_color,
            chart_info: '',
            enable_line_best_fit: true,
            admin_restricted_list: true
        },
        methods: {
            tariff_mode_changed: function() {
                this.tariff_calc();
                // sort by heat unit cost
                app.currentSortDir = 'asc';
                app.currentSortColumn = 'combined_heat_unit_cost';
                app.sort_only('combined_heat_unit_cost');

                app.url_update();
            },
 
            tariff_calc: function() {

                for (var i = 0; i < app.systems.length; i++) {
                    if (this.tariff_mode == 'flat') {
                        app.systems[i].selected_unit_rate = 22.36;
                        // remove electricity_tariff from selected columns
                        if (app.selected_template == 'costs') {
                            if (app.selected_columns.includes('electricity_tariff')) {
                                app.selected_columns.splice(app.selected_columns.indexOf('electricity_tariff'), 1);
                            }
                        }
                    } else if (this.tariff_mode == 'agile') {
                        app.systems[i].selected_unit_rate = app.systems[i].unit_rate_agile;
                        // remove electricity_tariff from selected columns
                        if (app.selected_template == 'costs') {
                            if (app.selected_columns.includes('electricity_tariff')) {
                                app.selected_columns.splice(app.selected_columns.indexOf('electricity_tariff'), 1);
                            }
                        }
                    } else if (this.tariff_mode == 'cosy') {
                        app.systems[i].selected_unit_rate = app.systems[i].unit_rate_cosy;
                        // remove electricity_tariff from selected columns
                        if (app.selected_template == 'costs') {
                            if (app.selected_columns.includes('electricity_tariff')) {
                                app.selected_columns.splice(app.selected_columns.indexOf('electricity_tariff'), 1);
                            }
                        }
                    } else if (this.tariff_mode == 'go') {
                        app.systems[i].selected_unit_rate = app.systems[i].unit_rate_go;
                        // remove electricity_tariff from selected columns
                        if (app.selected_template == 'costs') {
                            if (app.selected_columns.includes('electricity_tariff')) {
                                app.selected_columns.splice(app.selected_columns.indexOf('electricity_tariff'), 1);
                            }
                        }
                    } else if (this.tariff_mode == 'ovohp') {
                        app.systems[i].selected_unit_rate = 15.0;
                        // remove electricity_tariff from selected columns
                        if (app.selected_template == 'costs') {
                            if (app.selected_columns.includes('electricity_tariff')) {
                                app.selected_columns.splice(app.selected_columns.indexOf('electricity_tariff'), 1);
                            }
                        }
                    }
                    /*
                    } else if (this.tariff_mode == 'user') {
                        app.systems[i].selected_unit_rate = app.systems[i].electricity_tariff_unit_rate_all;
                        // add electricity_tariff to selected columns if not already there
                        if (app.selected_template == 'costs') {
                            if (!app.selected_columns.includes('electricity_tariff')) {
                                // add after hp_model
                                app.selected_columns.splice(app.selected_columns.indexOf('hp_model')+1, 0, 'electricity_tariff');
                            }
                        }
                    }*/

                    // recalculate costs
                    // for each category
                    let categories = ['combined','running','space','water'];
                    for (var z in categories) {
                        let category = categories[z];

                        // cost
                        if (app.systems[i].selected_unit_rate==0 || app.systems[i].selected_unit_rate==null) {
                            app.systems[i].selected_unit_rate = null;
                        }
                        let cost = app.systems[i][category+"_elec_kwh"] * app.systems[i].selected_unit_rate * 0.01;
                        cost = cost.toFixed(columns[category+'_cost']['dp'])*1;
                        if (cost === 0) cost = null;
                        app.systems[i][category+"_cost"] = cost;

                        // unitcost
                        if (app.systems[i][category+"_cop"]>0) {
                            let unitcost = app.systems[i].selected_unit_rate / app.systems[i][category+"_cop"];
                            unitcost = unitcost.toFixed(columns[category+'_heat_unit_cost']['dp'])*1;
                            if (unitcost === 0) unitcost = null;
                            app.systems[i][category+"_heat_unit_cost"] = unitcost;
                        } else {
                            app.systems[i][category+"_heat_unit_cost"] = null;
                        }
                    }
                }
            },
            template_view: function(template) {
                this.selected_template = template;
                if (template == 'topofthescops') {
                    app.currentSortDir = 'desc'
                    app.sort_only('combined_cop');
                    app.columns['combined_cop'].dp = 1;
                } else if (template == 'heatpumpfabric') {
                    // app.stats_time_start = "last365";
                    app.currentSortDir = 'asc';
                    app.currentSortColumn = 'combined_elec_kwh_per_m2';
                    app.stats_time_start_change();

                } else if (template == 'costs') {
                    // app.stats_time_start = "last365";
                    app.currentSortDir = 'asc'
                    app.currentSortColumn = 'combined_heat_unit_cost';
                    app.columns['combined_cop'].dp = 2;
                    app.stats_time_start_change();
                }

                app.url_update();

                resize();
            },
            create: function() {
                window.location = path+"system/new";
            },
            view: function(index) {
                // window.location = this.systems[index].url;
                let systemid = this.systems[index].id;
                window.location = path+"system/view?id=" + systemid;
            },
            remove: function(systemid) {

                // find system
                var index = this.systems.findIndex(x => x.id === systemid);

                if (confirm("Are you sure you want to delete system: " + systemid + " "+ this.systems[index].location + "?")) {
                    // axios delete
                    axios.get(path+'system/delete?id=' + systemid)
                        .then(response => {
                            if (response.data.success) {
                                this.systems.splice(index, 1);
                            } else {
                                alert("Error deleting system: " + response.data.message);
                            }
                        })
                        .catch(error => {
                            alert("Error deleting system: " + error);
                        });
                }
            },
            select_column: function(column) {
                console.log('select column: ' + column);

                if (this.selected_columns.includes(column)) {
                    this.selected_columns.splice(this.selected_columns.indexOf(column), 1);

                    // remove from added_columns if present
                    // else add to removed_columns
                    if (added_columns.includes(column)) {
                        added_columns.splice(added_columns.indexOf(column), 1);
                    } else {
                        removed_columns.push(column);
                    }

                } else {
                    // ADD column (add to start if id)
                    if (column == 'id') {
                        this.selected_columns.unshift(column);
                    } else {
                        this.selected_columns.push(column);
                    }

                    // remove from removed_columns if present
                    // else add to added_columns
                    if (removed_columns.includes(column)) {
                        removed_columns.splice(removed_columns.indexOf(column), 1);
                    } else {
                        added_columns.push(column);
                    }
                }

                app.url_update();
            },
            toggle_field_group: function(group) {
                // hide all
                for (var key in this.show_field_group) {
                    if (key==group) continue;
                    this.show_field_group[key] = false;
                }
                this.show_field_group[group] = !this.show_field_group[group];
            },
            sort: function(column, starting_order) {

                if (this.currentSortColumn != column) {
                    this.currentSortDir = starting_order;
                    this.currentSortColumn = column;
                } else {
                    if (this.currentSortDir == 'desc') {
                        this.currentSortDir = 'asc';
                    } else {
                        this.currentSortDir = 'desc';
                    }
                }
                this.sort_only(column);
                this.filter_systems();
            },
            sort_only: function(column) {
                this.systems.sort((a, b) => {
                    let modifier = 1;
                    if (this.currentSortDir == 'desc') modifier = -1;

                    let aValue = a[column];
                    let bValue = b[column];

                    if (aValue === null || aValue === undefined) aValue = this.currentSortDir == 'desc' ? -Infinity : Infinity;
                    if (bValue === null || bValue === undefined) bValue = this.currentSortDir == 'desc' ? -Infinity : Infinity;

                    if (aValue < bValue) return -1 * modifier;
                    if (aValue > bValue) return 1 * modifier;
                    return 0;
                });
            },
            export_csv: function() {
                console.log('export csv');
                // copy table data to clipboard as csv
                

                var csv = [];

                var header = [];
                for (var i = 0; i < this.selected_columns.length; i++) {
                    // filter out logo, training, learnmore
                    if (this.selected_columns[i]=='installer_logo') continue;
                    if (this.selected_columns[i]=='training') continue;
                    if (this.selected_columns[i]=='learnmore') continue;
                    header.push('"'+this.columns[this.selected_columns[i]].name+'"');
                }
                csv.push(header.join(","));

                for (var i = 0; i < this.fSystems.length; i++) {
                    var row = [];
                    for (var j = 0; j < this.selected_columns.length; j++) {
                        // filter out logo, training, learnmore
                        if (this.selected_columns[j]=='installer_logo') continue;
                        if (this.selected_columns[j]=='training') continue;
                        if (this.selected_columns[j]=='learnmore') continue;

                        var column = this.selected_columns[j];

                        var value = this.fSystems[i][column];
                        if (value==null) value = '';

                        // if float 3dp
                        if (stats_columns[column]!=undefined) {
                            if (stats_columns[column]['dp']!=undefined && value != null && value != '') {
                                value = value.toFixed(stats_columns[column]['dp']+1);
                            }
                        }
                        row.push('"'+value+'"');
                    }
                    csv.push(row.join(","));
                }
                var csv_string = csv.join("\n");
                copy_text_to_clipboard(csv_string, 'CSV data copied to clipboard');
            },
            stats_time_start_change: function () {
                // change available_months_end to only show months after start
                if (this.stats_time_start=='last7' || this.stats_time_start=='last30' || this.stats_time_start=='last90' || this.stats_time_start=='last365' || this.stats_time_start=='all') {
                    this.stats_time_end = 'only';
                } else {
                    let start_index = this.available_months_start.indexOf(this.stats_time_start);
                    this.available_months_end = this.available_months_start.slice(0,start_index);

                    if (this.stats_time_end!='only') {
                        this.stats_time_end = this.available_months_end[0];
                    }
                }
                
                if (this.stats_time_start=='last365') {
                    if (this.mode == 'public') this.minDays = default_minDays['last365'];
                    columns['combined_cop'].name = 'SPF';
                    columns['combined_cop'].heading = 'SPF';
                } else if (this.stats_time_start=='last90') {
                    if (this.mode == 'public') this.minDays = default_minDays['last90'];
                    columns['combined_cop'].name = 'COP';
                    columns['combined_cop'].heading = 'COP';
                } else if (this.stats_time_start=='last30') {
                    if (this.mode == 'public') this.minDays = default_minDays['last30'];
                    columns['combined_cop'].name = 'COP';
                    columns['combined_cop'].heading = 'COP';
                } else if (this.stats_time_start=='last7') {
                    if (this.mode == 'public') this.minDays = default_minDays['last7'];
                    columns['combined_cop'].name = 'COP';
                    columns['combined_cop'].heading = 'COP';
                } else {
                    if (this.mode == 'public') this.minDays = default_minDays['all'];
                    columns['combined_cop'].name = 'COP';
                    columns['combined_cop'].heading = 'COP';
                }
                
                this.load_system_stats();
                this.url_update();
            },
            stats_time_end_change: function () {
                this.load_system_stats();
            },
            load_system_stats: function () {
                
                // Start
                let start = this.stats_time_start;
                if (start!='last7' && start!='last30' && start!='last90' && start!='last365' && start!='all') {
                    // Convert e.g Mar 2023 to 2023-03-01
                    let months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sept','Oct','Nov','Dec'];
                    let month = start.split(' ')[0];
                    let year = start.split(' ')[1];
                    start = year + '-' + (months.indexOf(month)+1) + '-01';
                }

                // End
                let end = this.stats_time_end;
                if (end!='only') {
                    // Convert e.g Mar 2023 to 2023-03-01
                    let months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sept','Oct','Nov','Dec'];
                    let month = end.split(' ')[0];
                    let year = end.split(' ')[1];
                    end = year + '-' + (months.indexOf(month)+1) + '-01';
                } else {
                    end = start;
                }

                var url = path+'system/stats';
                var params = {
                    start: start,
                    end: end
                };

                if (start == 'last7' || start == 'last30' || start == 'last90' || start == 'last365' || start == 'all') {
                    
                    url = path+'system/stats/'+start;
                    params = {};
                }
                // Load system/stats data
                axios.get(url, {
                        params: params
                    })
                    .then(response => {
                        var stats = response.data;
                        for (var i = 0; i < app.systems.length; i++) {
                            let id = app.systems[i].id;
                            if (stats[id]) {
                                // copy stats data to system
                                for (var key in stats[id]) {
                                    app.systems[i][key] = stats[id][key];
                                }

                                // for each category
                                let categories = ['combined','running','space','water'];
                                for (var z in categories) {
                                    let category = categories[z];

                                    
                                    if (app.systems[i].floor_area!=null && app.systems[i].floor_area>0) {
                                        // elec kwh/m2
                                        let elec_kwh_per_m2 = 1*app.systems[i][category+"_elec_kwh"] / app.systems[i].floor_area;
                                        elec_kwh_per_m2 = elec_kwh_per_m2.toFixed(columns[category+'_elec_kwh_per_m2']['dp'])*1;
                                        if (elec_kwh_per_m2===0) elec_kwh_per_m2 = null;
                                        app.systems[i][category+"_elec_kwh_per_m2"] = elec_kwh_per_m2;

                                        // heat kwh/m2
                                        let heat_kwh_per_m2 = 1*app.systems[i][category+"_heat_kwh"] / app.systems[i].floor_area;
                                        heat_kwh_per_m2 = heat_kwh_per_m2.toFixed(columns[category+'_elec_kwh_per_m2']['dp'])*1;
                                        if (heat_kwh_per_m2===0) heat_kwh_per_m2 = null;
                                        app.systems[i][category+"_heat_kwh_per_m2"] = heat_kwh_per_m2;
                                    } else {
                                        app.systems[i][category+"_elec_kwh_per_m2"] = null;
                                        app.systems[i][category+"_heat_kwh_per_m2"] = null;
                                    }
                                }

                                // Auto flag air errors if error_air_kwh / combined_elec_kwh > 0.1
                                if (app.systems[i].error_air_kwh!=null && app.systems[i].error_air_kwh>0) {
                                    if (app.systems[i].combined_elec_kwh!=null && app.systems[i].combined_elec_kwh>0) {

                                        let prc = (app.systems[i].error_air_kwh / app.systems[i].combined_elec_kwh * 100).toFixed(1);

                                        let electric_not_including_air_error = app.systems[i].combined_elec_kwh - app.systems[i].error_air_kwh;
                                        let cop_not_including_air_error = 0;
                                        if (electric_not_including_air_error>0) {
                                            cop_not_including_air_error = app.systems[i].combined_heat_kwh / electric_not_including_air_error;
                                        }
                                        let difference = app.systems[i].combined_cop - cop_not_including_air_error;
                                        // abs difference
                                        let abs_difference = Math.abs(difference);

                                        if (abs_difference > 0.125) {

                                            let note = 'Heat meter air error\n';
                                            note += (app.systems[i].error_air / 3600).toFixed(0) + " hours, ";
                                            note += (app.systems[i].error_air_kwh).toFixed(0) + " kWh electric\n";
                                            note += "% of electric consumption: " + prc + "%\n";
                                            note += "COP listed: " + app.systems[i].combined_cop.toFixed(2) + "\n";
                                            note += "COP not including air error: " + cop_not_including_air_error.toFixed(2)+"\n";
                                            note += "Difference: " + difference.toFixed(2);

                                            app.systems[i].data_flag = 1;
                                            app.systems[i].data_flag_note = note;

                                        }
                                    }
                                } else {
                                    app.systems[i].data_flag = 0;
                                    app.systems[i].data_flag_note = '';
                                }

                                let prc_demand_hot_water = null;
                                if (app.systems[i]['water_heat_kwh']>0 && app.systems[i]['space_heat_kwh']>0) {
                                    prc_demand_hot_water = app.systems[i]['water_heat_kwh'] / (app.systems[i]['water_heat_kwh'] + app.systems[i]['space_heat_kwh']) * 100;
                                }
                                app.systems[i]['prc_demand_hot_water'] = prc_demand_hot_water;      

                            } else {
                                // for (var col in stats_columns) {
                                //    app.systems[i][stats_columns[col]] = 0;
                                // }
                                app.systems[i]['combined_cop'] = 0;
                                app.systems[i]['combined_data_length'] = 0;
                            }
                        }

                        app.tariff_calc();

                        // sort
                        app.sort_only(app.currentSortColumn);

                        app.filter_systems();
                        
                    })
                    .catch(error => {
                        alert("Error loading data: " + error);
                    });
            },
            toggle_chart: function() {
                this.chart_enable = !this.chart_enable;

                app.url_update();
                
                if (this.chart_enable) {
                    setTimeout(function() {
                        draw_scatter();
                    }, 200);
                }
            },
            column_format: function (system,key) {
                var val = system[key];
                
                if (key=='last_updated' || key=='data_start') {
                    return time_ago(val,' ago');
                }
                if (key=='since') {
                    return time_ago(val);
                }
                if (key=='combined_data_length') {
                    // Calculate as % of period
                    if (val==null) return '';
                    /*
                    let dp = 0;
                    let append_prc = "<span style='font-size:14px'>%</span>";
                    if (this.stats_time_start=='last7') return (100*val/(7*24*3600)).toFixed(dp) + append_prc;
                    else if (this.stats_time_start=='last30') return (100*val/(30*24*3600)).toFixed(dp) + append_prc;
                    else if (this.stats_time_start=='last90') return (100*val/(90*24*3600)).toFixed(dp) + append_prc;
                    else if (this.stats_time_start=='last365') return (100*val/(365*24*3600)).toFixed(dp) + append_prc;
                    */
                    return (val/(24*3600)).toFixed(0)+" days";
                }

                if (key=='data_flag') {

                    var flag = "";
                    if (system['data_flag']) {


                        var note = system.data_flag_note;
                        // trim and to lower case
                        if (note == null) {
                          note = "";
                        }
                        note = note.trim().toLowerCase();

                        var color = "#FFD43B";
                        if (note.indexOf('heat meter air error') > -1) {
                            // blue
                            color = "#4f8baa"; 
                        } else if (note == 'temperature sensor offset error') {
                            // orange
                            color = "#FFA500";
                        } else if (note == 'invalid url') {
                            // grey
                            color = "#808080";
                        } else if (note == 'no heat data' || note == 'no electric data') {
                            // red
                            color = "#ff0000";
                        }

                        flag = "<i class='fas fa-exclamation-circle' style='color: "+color+"; margin-left:10px; cursor:pointer' title='"+system['data_flag_note']+"'></i>";
                    }
                    return flag;
                }

                if (key=='installer_logo') {
                    if (val!=null && val!='') {
                        var installer_logo = '';
                        if (system['installer_logo']) {
                            installer_logo = "<a href='"+system['installer_url']+"'><img class='logo' src='"+path+"theme/img/installers/"+val+"'/></a>";
                        }
                        return installer_logo;
                    } else {
                        return '';
                    }
                }
                   
                if (key=='installer_name') {
                    if (val!=null && val!='') {
                        return "<a class='installer_link' href='"+system['installer_url']+"'>"+val+"</a>";
                    } else {
                        return '';
                    }
                }
                if (key=='training') {
                    var training = "";
                    if (system['heatgeek']==1) {
                        training += "<a href='https://www.heatgeek.com'><img class='heatgeeklogo' src='"+path+"theme/img/HeatGeekLogo.png' title='HeatGeek Mastery'/></a>";
                    }
                    if (system['ultimaterenewables']==1) {
                        training += "<a href='https://www.ultimatetrainingandtechnical.co.uk'><img class='ultimatelogo' src='"+path+"theme/img/ultimate.png' title='Ultimate Pro'/></a>";
                    }
                    if (system['heatingacademy']==1) {
                        training += "<a href='https://heatingacademynorthampton.co.uk'><img class='heatingacademylogo' src='"+path+"theme/img/HA2.png' title='Heating Academy Hydronics'/></a>";
                    }
                    return training;
                }
                
                if (key=='heatgeek') {
                    if (val==1) {
                        return "<img class='heatgeeklogo' src='"+path+"theme/img/HeatGeekLogo.png' title='HeatGeek Mastery'/>";
                    } else {
                        return "";
                    }
                }
                if (key=='learnmore') {
                    var learnmore = "";
                    if (system['youtube']!=null && system['youtube']!="" && system['youtube']!='0') {
                        learnmore += "<a href='"+system['youtube']+"'><img class='betateachlogo' src='"+path+"theme/img/youtube.png' title='Learn more about this system on YouTube'/></a>";
                    }
                    if (system['betateach']!=null && system['betateach']!="" && system['betateach']!='0') {
                        learnmore += "<a href='"+system['betateach']+"'><img class='betateachlogo' src='"+path+"theme/img/beta-teach.jpg' title='Learn more on the BetaTalk Podcast'/></a>";
                    }

                    return learnmore;
                }
                if (key=='hp_type') {
                    if (val=="Air Source") {
                        return "<span style='color:#4f8baa'>Air</span>";
                    }
                    if (val=="Ground Source") {
                        return "<span style='color:#938e03'>Ground</span>";
                    }
                }
                if (key=='hp_output') {
                    return val + ' kW';
                }
                if (key=='floor_area') {
                    return val + ' m2';
                }
                if (key=='mid_metering') {
                    if (val==1) {
                        return '<input type="checkbox" disabled checked title="This system has class 1 electric and class 2 heat metering">';
                    } else {
                        return '';
                    }
                }
                
                /*
                if (key=='electricity_tariff_unit_rate_all') {
                    if (val==null) return '';
                    return val + ' p/kWh';
                }
                */

                if (key=='error_air') {
                    // convert seconds to hours
                    if (val==null) return '';
                    return (val/3600).toFixed(0) + ' hrs';
                }
                
                if (stats_columns[key]!=undefined) {
                    if (isNaN(val) || val == null) {
                        return val;
                    }
                    
                    let unit = '';
                    if (stats_columns[key]['unit']!=undefined) {
                        unit = ' '+stats_columns[key]['unit'];
                    }

                    let prepend = '';
                    if (stats_columns[key]['prepend']!=undefined) {
                        prepend = stats_columns[key]['prepend'];
                    }
                
                    if (stats_columns[key]['dp']!=undefined) {
                        return "<span title='"+val.toFixed(stats_columns[key]['dp']+1)+"'>"+prepend+val.toFixed(stats_columns[key]['dp'])+unit+"</span>";
                    }
                }

                if (key == 'weighted_average_flow_minus_outside') {
                    if (val == null || val == 0 ) return '';
                    return val.toFixed(1);
                }

                if (key == 'prc_demand_hot_water') {
                    if (val == null || val == 0 ) return '';
                    return val.toFixed(0) + '%';
                }
                
                return val;
            },
            // grey if start date is less that 1 year ago
            sinceClass: function(system,column) {
                // return node.since > 0 ? 'partial ' : '';
                // node.since is unix time in seconds
                if (column=='combined_cop' || column=='since' || column=='combined_data_length' || column=='quality_elec') {
                
                    if (system.boundary_code!=4) {
                        return 'partial';
                    }
                    
                    if (system.mid_metering==0) {
                        return 'partial';
                    }
                
                    var days = system.combined_data_length / (24 * 3600)
                    if (system.combined_cop==0) {
                        return 'partial ';
                    }
                    if (this.stats_time_start=='last365' || this.stats_time_start=='all') {
                        
                        return (days<=330) ? 'partial ' : '';
                    } else if (this.stats_time_start=='last90') {
                        return (days<=72) ? 'partial ' : '';
                    } else if (this.stats_time_start=='last30') {
                        return (days<=27) ? 'partial ' : '';
                    } else if (this.stats_time_start=='last7') {
                        return (days<=5) ? 'partial ' : '';
                    }
                }
                
                return '';
            },
 
            filterNodes(row) {

                if (this.filterKey != '') {
                    if (this.filterKey === 'MID') {
                        return row.mid_metering === 1;
                    } else if (this.filterKey === 'HG' || this.filterKey === 'HeatGeek') {
                        return row.heatgeek === 1;
                    } else if (this.filterKey === 'NHG') {
                        return row.heatgeek === 0;
                    } else if (this.filterKey === 'UR') {
                        return row.ultimaterenewables === 1;
                    } else if (this.filterKey === 'HA') {
                        return row.heatingacademy === 1;
                    } else {

                        // if first part of this.filterKey is 'query' then format is query:field_name:value,field_name:value
                        if (this.filterKey.indexOf('query') === 0) {
                            // remove 'query:' from start
                            var query = this.filterKey.substring(6);
                            var query_parts = query.split(',');

                            var result = true;

                            for (var i = 0; i < query_parts.length; i++) {
                                var query_part = query_parts[i].split(':');
                                var field = query_part[0];
                                var value = query_part[1];

                                // if numeric check for exact match otherwise check for partial match
                                if (!isNaN(value)) {
                                    if (row[field] != value) {
                                        result = false;
                                    }
                                } else {
                                    if (String(row[field]).toLowerCase().indexOf(value.toLowerCase()) == -1) {
                                        result = false;
                                    }
                                }
                            }
                            return result;

                        } else {
                            return Object.keys(row).some((key) => {
                                return String(row[key]).toLowerCase().indexOf(this.filterKey.toLowerCase()) > -1
                            })
                        }
                    }
                }
                return true;
            },

            // Filter systems with combined_cop <= 0
            filterCop(row) {
                return row.combined_cop > 0;
            },

            filterDays(row) {
                if (this.minDays==null || this.minDays=='' || isNaN(this.minDays)) this.minDays = 0;
                this.minDays = parseInt(this.minDays);
                let minDays = this.minDays-1;
                if (minDays<0) minDays = 0;
                return (row.combined_data_length/ (24 * 3600)) >= minDays;
            },
            
            filterMetering(row) {
            
                var show = false;
                
                if (this.show_mid && row.mid_metering) {
                    show = true;
                }
                if (this.show_other && !row.mid_metering && row.heat_meter != 'Heat pump integration') {
                    show = true;
                }
                if (this.show_hpint && !row.mid_metering && row.heat_meter == 'Heat pump integration') {
                    show = true;
                }

                
                if (this.show_errors && row.data_flag) {
                    show = true;
                } else {
                    if (row.data_flag) {
                        show = false;
                    }
                }
                return show;
            },
            
            system_count(systems) {
                // Count flagged systems
                this.num_flagged = 0
                this.num_mid = 0
                this.num_other = 0
                this.num_hpint = 0
                this.num_class2_heat = 0
                this.num_class1_elec = 0
                this.num_other_metering = 0
                
                for (var i = 0; i < systems.length; i++) {
                
                    if (systems[i].data_flag) {
                        this.num_flagged ++;
                    } else {
                        if (systems[i].mid_metering) {
                            this.num_mid ++;
                        } else {
                            if (systems[i].heat_meter == 'Heat pump integration') {
                                this.num_hpint ++;
                            } else {
                                this.num_other ++;
                            }
                        }
                        
                        /*
                        if (systems[i].heat_meter_class2) {
                            this.num_class2_heat++;
                        }

                        if (systems[i].elec_meter_class1) {
                            this.num_class1_elec++;
                        }
                        
                        if (!systems[i].elec_meter_class1 && !systems[i].heat_meter_class2) {
                            this.num_other_metering++;
                        }
                        */
                    }
                }
            },

            filter_systems: function() {
            
                var filtered_nodes_days = this.systems.filter(this.filterNodes).filter(this.filterDays);

                if (app.mode == 'admin' && app.admin_restricted_list) {
                    // Only show systems that are private, awaiting approval or have error flag
                    filtered_nodes_days = filtered_nodes_days.filter(function(row) {
                        if (row.share == 0) return true;
                        if (row.published == 0) return true;
                        if  (row.data_flag) return true;
                        return false;
                    });
                }
                
                this.system_count(filtered_nodes_days);
            
                this.fSystems = filtered_nodes_days.filter(this.filterMetering)

                if (this.chart_enable) {
                    draw_scatter();
                }

                this.url_update();
            },
            url_update(field_keys) {

                // Supported url params
                //
                // - mode       (app.selected_template)
                // - period     (app.stats_time_start)
                // - minDays    (app.minDays)
                // - add        (added_columns)
                // - rm         (removed_columns)
                // - filter     (app.filterKey)
                // - tariff     (app.tariff_mode)
                // - mid        (app.show_mid)
                // - other      (app.show_other)
                // - hpint      (app.show_hpint)
                // - errors     (app.show_errors)

                var settings = {
                    mode: this.selected_template,
                    period: this.stats_time_start,
                    minDays: this.minDays,
                    add: added_columns.join(','),
                    rm: removed_columns.join(','),
                    filter: this.filterKey,
                    tariff: this.tariff_mode,
                    mid: 1*this.show_mid,
                    other: 1*this.show_other,
                    hpint: 1*this.show_hpint,
                    errors: 1*this.show_errors,
                    chart: 1*this.chart_enable,
                    selected_xaxis: this.selected_xaxis,
                    selected_yaxis: this.selected_yaxis,
                    selected_color: this.selected_color,
                };

                if (settings.mode != 'costs') {
                    settings.tariff = default_settings.tariff;
                }

                var url = new URL(window.location.href);
                for (var key in settings) {
                    if (settings[key] != default_settings[key]) {
                        url.searchParams.set(key, settings[key]);
                    } else {
                        url.searchParams.delete(key);
                    }
                }
                var decodedUrl = decodeURIComponent(url.toString());
                window.history.pushState({}, '', decodedUrl);   
            },
            toggle_restricted_list: function() {
                this.admin_restricted_list = !this.admin_restricted_list;
                this.filter_systems();
            },
       },
        filters: {
            toFixed: function(val, dp) {
                if (isNaN(val) || val == null) {
                    return val;
                } else {
                    return val.toFixed(dp)
                }
            },
            time_ago: function(val) {
                return time_ago(val);
            }
        },

        computed: {
            /*
            fSystems: function () {
            
                var filtered_nodes_days = this.systems.filter(this.filterNodes).filter(this.filterDays);

                // if public mode only show systems with data
                if (this.mode=='public') {
                    //filtered_nodes_days = filtered_nodes_days.filter(this.filterCop);
                }
                
                this.system_count(filtered_nodes_days);
            
            
                return filtered_nodes_days.filter(this.filterMetering)
            },*/
            // calculate total scop of fSystems
            totals: function () {
                var totals = {
                    average_cop: 0,
                    elec_kwh: 0,
                    heat_kwh: 0,
                    average_cop_kwh: 0,
                    count: 0,
                    listed_system_count: 0
                };
                var count = 0;
                for (var i = 0; i < this.fSystems.length; i++) {
                    if (this.fSystems[i].combined_elec_kwh>0 && this.fSystems[i].combined_heat_kwh>0 && this.fSystems[i].combined_heat_kwh>this.fSystems[i].combined_elec_kwh) {
                        totals.average_cop += this.fSystems[i].combined_cop*1;
                        totals.elec_kwh += this.fSystems[i].combined_elec_kwh;
                        totals.heat_kwh += this.fSystems[i].combined_heat_kwh;
                        totals.count++;
                    }
                    totals.listed_system_count++;
                }
                totals.average_cop = totals.average_cop / totals.count;
                totals.average_cop_kwh = totals.heat_kwh / totals.elec_kwh;

                return totals;
            }
        }
    });
   
   
    for (var i = 0; i < app.systems.length; i++) {
        let heat_meter = app.systems[i].heat_meter;
        let elec_meter = app.systems[i].electric_meter;
        
        if (heat_meter!=null && heat_meter.indexOf("class 2")!=-1) {
            app.systems[i].heat_meter_class2 = true;
        } else {
            app.systems[i].heat_meter_class2 = false;
        }
        
        if (elec_meter!=null && elec_meter.indexOf("class 1")!=-1) {
            app.systems[i].elec_meter_class1 = true;
        } else {
            app.systems[i].elec_meter_class1 = false;
        }
    }
    
    app.load_system_stats();
    app.sort_only('combined_cop');
    resize(true);

    function time_ago(val,ago='') {
        if (val == null || val == 0) {
            return '';
        }
        // convert timestamp to date time
        let date = new Date(val * 1000);
        // format date time
        let year = date.getFullYear();
        let month = date.getMonth() + 1;
        let day = date.getDate();
        let hour = date.getHours();
        let min = date.getMinutes();
        let sec = date.getSeconds();
        // add leading zeros
        month = (month < 10) ? "0" + month : month;
        day = (day < 10) ? "0" + day : day;
        hour = (hour < 10) ? "0" + hour : hour;
        min = (min < 10) ? "0" + min : min;
        sec = (sec < 10) ? "0" + sec : sec;
        // return formatted date time

        let months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];


        // work out as 10 days ago
        let now = new Date();
        let diff = now - date;
        let days = Math.floor(diff / (1000 * 60 * 60 * 24));

        return days + " days"+ago;

        // return day + " " + months[month-1] + " " + year;
    }

    window.addEventListener('resize', function(event) {
        resize();
    }, true);
    
    function resize(first = false) {
        var width = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;

        if (app.mode != 'admin') {
            if (width<800) {
                app.selected_columns = template_views[app.selected_template]['narrow'];
                app.showContent = false;
                if (first) app.show_field_selector = false;
                app.columns['training'].heading = "";
                
            } else {
                app.selected_columns = template_views[app.selected_template]['wide'];
                app.showContent = true;
                app.columns['training'].heading = "Training";
            }
        } else {
            if (width<800) {
                app.selected_columns = ['hp_model', 'hp_output', 'combined_cop'];
                app.showContent = false;
                if (first) app.show_field_selector = false;
            } else {
                app.selected_columns = ['location', 'hp_type', 'hp_model', 'hp_output', 'combined_data_length', 'data_flag', 'combined_cop', 'boundary'];
                app.showContent = true;
            }
        }
    }


    // event listener for change of URL params
    window.addEventListener('popstate', function(event) {
        // reload page
        // apply changes here directly in future
        location.reload();
    }); 

</script>
