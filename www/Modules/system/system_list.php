<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/vue-select@3.20.4/dist/vue-select.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdn.jsdelivr.net/npm/vue-select@3.20.4/dist/vue-select.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>
<script src="https://cdn.plot.ly/plotly-2.16.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jstat@1.9.6/dist/jstat.min.js"></script>
<script src="Lib/clipboard.js"></script>
<script src="<?php echo $path; ?>Modules/system/system_list_chart.js?v=42"></script>

<link rel="stylesheet" href="<?php echo $path; ?>Lib/autocomplete.css?v=4">
<script src="Lib/autocomplete.js?v=8"></script>
<link rel="stylesheet" href="<?php echo $path; ?>Modules/system/system_view.css?v=6">
<script src="<?php echo $path; ?>Modules/system/photo_utils.js?v=1"></script>
<script src="<?php echo $path; ?>Modules/system/photo_lightbox.js?v=5"></script>

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

    .btn-xs {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
        line-height: 1;
        border-radius: 0.2rem;
    }

    .category {
        font-size: 12px;
        color: #555;
    }
    
    .column {
        font-size: 15px;
        color: #000;
    }

    .custom-select .vs__dropdown-toggle {
        font-size: 0.875rem; /* match the font size of .form-control-sm */
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        height: 34px; /* stop the box from expanding vertically */
    }

    .custom-select .vs__dropdown-menu {
        font-size: 0.875rem; /* match the font size of .form-control-sm */
        width: auto; /* allow the dropdown to expand */
        min-width: 100%; /* ensure it's at least as wide as the input */
        position: absolute; /* ensure it's positioned outside the normal flow */
        z-index: 1000; /* ensure it's above other elements */
        box-shadow: 0 3px 6px rgba(0,0,0,0.16), 0 3px 6px rgba(0,0,0,0.23); /* add a shadow */
    }

    .custom-select .vs__search {
        font-size: 0.875rem; /* match the font size of .form-control-sm */
    }

    .custom-select .vs__selected-options {
        font-size: 0.875rem; /* match the font size of .form-control-sm */
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .d-flex {
        display: flex;
    }

    .flex-grow-1 {
        flex-grow: 1;
    }

    .mb-2 {
        margin-bottom: 0.5rem;
    }

    .me-2 {
        margin-right: 0.5rem;
    }

    .align-items-center {
        align-items: center;
    }

    /* apply scroll only to the "Add fields" card */
    .add-fields-card {
        overflow-y: scroll;
        max-height: 780px;
    }

    .add-filters-card {
        overflow: visible !important;
        max-height: 780px;
    }

    /* Prevent flash of unstyled content before Vue initializes */
    [v-cloak] {
        display: none;
    }

    .data-status-circle {
        display: inline-block;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        cursor: pointer;
    }

    .data-status-very-fresh {
        background-color: #28a745;
    }

    .data-status-fresh {
        background-color: #e0d616ff;
    }

    .data-status-stale {
        background-color: #fd7e14;
    }

    .data-status-very-stale {
        background-color: #dc3545;
    }

    .data-status-unknown {
        background-color: #6c757d;
    }

</style>

<div id="app" class="bg-light" v-cloak>
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container-fluid">

            <div class="row">
                <div class="col-12 col-sm-12 col-md-6 col-lg-6 col-xl-8">

                    <h3 v-if="mode=='user'">My Systems</h3>
                    <h3 v-if="mode=='admin'">Admin Systems</h3>

                    <p v-if="mode=='user'">Add, edit and view systems associated with your account and sub-accounts.</p>
                    <p v-if="mode=='admin'">Add, edit and view all systems</p>
                    
                    <p v-if="mode=='public' && showContent">If you're monitoring a heat pump with <a href="https://openenergymonitor.org">OpenEnergyMonitor</a> login to add your system</p>
                    <p v-if="mode=='public' && showContent">Join the discussion on the <a href="https://community.openenergymonitor.org/c/hardware/heatpump/47">Forum</a> and read more about our <a href="https://docs.openenergymonitor.org/heatpumpmonitor">findings so far</a>.</p>
                    
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
                            <option value="custom">Custom (Nov 24 to Nov 25)</option>
                            <option v-for="month in available_months_start">{{ month }}</option>
                        </select>
                        
                        <span class="input-group-text" v-if="stats_time_end!='only'">to</span>

                        <select class="form-control" v-model="stats_time_end" v-if="stats_time_start!='all' && stats_time_start!='last7' && stats_time_start!='last30' && stats_time_start!='last90' && stats_time_start!='last365' && stats_time_start!='custom'" @change="stats_time_end_change" style="width:120px">
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
                    
                    <div class="card mt-3 add-fields-card">
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
                                <li v-for="column in group" class="list-group-item" v-if="show_field_group[group_name] && column.fields!=false">
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

                    <div class="card mt-3 add-filters-card">
                        <div class="card-header">
                            <button class="btn btn-sm btn-secondary" style="float:right; margin-right:-8px" @click="addFilterPart">
                                <i class="fa fa-plus"></i>
                                <i class="fa fa-filter"></i>
                            </button>
                            <div style="margin-top:2px; font-size:18px">Filters</div>
                        </div>
                        <div>
                            <ul class="list-group list-group-flush">
                                <li v-for="(part, index) in filter_query_parts" class="list-group-item d-flex justify-content-between align-items-center" style="background-color:#f7f7f7;">
                                    <div v-if="!part.editing" class="form-check">
                                        <input class="form-check-input" type="checkbox" v-model="part.enabled" :id="'filter_' + part.field + '_' + part.operator + '_' + part.value" @change="saveFilterPart(index)">
                                        <label class="form-check-label" :for="'filter_' + part.field + '_' + part.operator + '_' + part.value" style="font-size:15px">
                                            <div><span class="category">{{ part.category }}</span></div>                                            
                                            <div><span class="column">{{ part.column }}</span> {{ part.operatorSign }} <span v-html="part.formattedValue"></span></div>
                                        </label>
                                    </div>
                                    <div v-else class="d-flex w-100 flex-column">
                                        <v-select v-model="part.field" :options="columnOptions" placeholder="select column" @input="updatePossibleValues(part.field, index)" class="custom-select" :reduce="option => option.value"></v-select>
                                        <select v-model="part.operator" class="form-control form-control-sm" style="width: auto;">
                                            <option value="eq" v-if="!part.allNumerical">contains</option>
                                            <option value="ne" v-if="!part.allNumerical">does not contain</option>
                                            <option value="eq" v-if="part.allNumerical">=</option>
                                            <option value="ne" v-if="part.allNumerical">!=</option>
                                            <option value="gt" v-if="part.allNumerical">&gt;</option>
                                            <option value="lt" v-if="part.allNumerical">&lt;</option>
                                            <option value="gte" v-if="part.allNumerical">&gt;=</option>
                                            <option value="lte" v-if="part.allNumerical">&lt;=</option>
                                        </select>
                                        <input type="text" v-model="part.value" class="form-control form-control-sm mb-2" placeholder="enter value" list="possibleValues" style="width:auto;">
                                        <datalist id="possibleValues">
                                            <option v-for="value in possibleValues" :value="value"></option>
                                        </datalist>
                                        <div class="btn-group">
                                            <button class="btn btn-xs btn-success me-2" @click="saveFilterPart(index)" :disabled="!part.field || part.value === null || part.value === undefined">
                                                <i class="fa fa-check"></i>
                                            </button>
                                            <button class="btn btn-xs btn-danger" @click="removeFilterPart(index)">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div v-if="!part.editing" class="btn-group" style="margin-left: auto;">
                                        <button class="btn btn-xs btn-warning" @click="editFilterPart(index)" >
                                            <i class="fa fa-edit"></i>
                                        </button>                                        
                                        <button class="btn btn-xs btn-danger" @click="removeFilterPart(index)">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                </li>
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
                    <select class="form-select" style="max-width:230px" v-model="tariff_mode" @change="tariff_mode_changed">
                        <option value="flat">Price cap</option>
                        <option value="ovohp">OVO Heat Pump Plus</option>
                        <option value="agile">Octopus Agile</option>
                        <option value="cosy">Octopus Cosy</option>
                        <option value="go">Octopus GO</option>
                        <option value="eon_next_pumped_v2">E.ON Next Pumped v2</option>
                        <!--<option value="user">User entered</option>-->
                    </select>
                </div>

                <div class="card mt-3" v-show="chart_enable">
                    <h5 class="card-header">Data explorer</h5>
                    <div class="card-body">

                        <!-- row with x and y axis selection -->
                        <div class="row">

                            <!-- X-axis grouped by group -->
                            <div class="col-lg-3">
                                <div class="input-group mb-3">
                                    <span class="input-group-text">X-axis</span>
                                    <select class="form-control" v-model="selected_xaxis" @change="chartAxisChanged">
                                        <optgroup v-for="(group, group_name) in column_groups" :label="group_name">
                                            <option v-for="row in group" :value="row.key" v-if="row.name">{{ row.name }}</option>
                                        </optgroup>
                                    </select>
                                </div>
                            </div>

                            <!-- Y-axis grouped by group -->
                            <div class="col-lg-3">
                                <div class="input-group mb-3">
                                    <span class="input-group-text">Y-axis</span>
                                    <select class="form-control" v-model="selected_yaxis" @change="chartAxisChanged">
                                        <optgroup v-for="(group, group_name) in column_groups" :label="group_name">
                                            <option v-for="row in group" :value="row.key" v-if="row.name">{{ row.name }}</option>
                                        </optgroup>
                                    </select>
                                </div>
                            </div>

                            <!-- Colour map -->
                            <div class="col-lg-3">
                                <div class="input-group mb-3">
                                    <span class="input-group-text">Colour map</span>
                                    <select class="form-control" v-model="selected_color" @change="chartAxisChanged">
                                        <optgroup v-for="(group, group_name) in column_groups" :label="group_name">
                                            <option v-for="row in group" :value="row.key" v-if="row.name">{{ row.name }}</option>
                                        </optgroup>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Select between no linear regression, OLS and Orthogonal -->
                            <div class="col-lg-3">
                                <div class="input-group mb-3">
                                    <span class="input-group-text">Best fit</span>
                                    <select class="form-control" v-model="line_best_fit_type" @change="draw_scatter">
                                        <option value="none">None</option>
                                        <option value="ols">Ordinary Least Squares (OLS)</option>
                                        <option value="tls">Orthogonal Regression (TLS)</option>
                                    </select>
                                </div>
                            </div>

                        </div>
                        <p v-if="line_best_fit_type!='none'">{{ chart_info }}</p>
                        <div id="chart"></div>
                    </div>
                </div>

                <div v-if="loading" class="text-center p-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading systems...</p>
                </div>

                <table v-else id="custom" class="table table-striped table-sm mt-3">
                    <tr>
                        <th v-if="mode!='public'" @click="sort('id', 'asc')" style="cursor:pointer">ID</th>
                        <th v-if="mode=='admin' || mode=='user'" @click="sort('name', 'asc')" style="cursor:pointer">User
                            <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='name'"></i>
                        </th>
                        <th v-for="column in selected_columns" @click="sort(column, 'desc')" style="cursor:pointer" :title="columns[column].helper"><span v-html="columns[column].heading"></span>
                            <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn==column"></i>
                        </th>
                        <th v-if="mode!='public' && public_mode_enabled" @click="sort('share', 'desc')" style="cursor:pointer">Share
                            <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='share'"></i>
                        </th>
                        <th v-if="mode!='public' && public_mode_enabled" @click="sort('published', 'desc')" style="cursor:pointer">Published
                            <i :class="currentSortDir == 'asc' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'" v-if="currentSortColumn=='published'"></i>
                        </th>
                        <th v-if="mode=='public'" :style="(showContent)?'width:80px':'width:20px'">View</th>
                        <th v-if="mode!='public'" :style="(showContent)?'width:120px':'width:20px'"></th>

                    </tr>
                    <tr v-for="(system,index) in fSystems" v-if="mode!='public' || (mode=='public' && system.combined_data_length!=0)">
                        <td v-if="mode!='public'">{{ system.id }}</td>
                        <td v-if="mode=='admin' || mode=='user'"><span style="color:#888">{{ system.username }}</span></td>
                        <td v-for="column in selected_columns" v-html="column_format(system,column)" v-bind:class="sinceClass(system,column)" style=""></td>
                        <td v-if="mode!='public' && public_mode_enabled">
                            <span v-if="system.share" class="badge bg-success">Shared</span>
                            <span v-if="!system.share" class="badge bg-danger">Private</span>
                        </td>
                        <td v-if="mode!='public' && public_mode_enabled">
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
                            <a :href="'<?php echo $path;?>dashboard?id='+system.id+((!system.share || !system.published) && system.readkey ? '&readkey='+system.readkey : '')" target="_blank" v-if="showContent">
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
                    <tr ref="loadMoreSentinel" v-show="has_more && fSystems.length && !loading">
                        <td :colspan="999" class="text-center py-2 text-muted">
                            <span v-if="loadingNextPage" class="spinner-border spinner-border-sm" role="status"></span>
                            <span v-else>Scroll to load more…</span>
                        </td>
                    </tr>
                </table>
                
                <div class="card" v-if="!loading">
                  <h5 class="card-header">Totals</h5>
                  <div class="card-body">
                    <p class="card-text">Number of systems in selection: <b>{{ totals.listed_system_count }}</b></p>
                    <p class="card-text">Average of individual system {{ stats_time_start === 'last365' ? 'SPF' : 'COP' }} values: <b>{{ totals.average_cop | toFixed(2) }}</b></p>
                    <p class="card-text">Average {{ stats_time_start === 'last365' ? 'SPF' : 'COP' }} based on total sum of heat and electric values: <b>{{ totals.average_cop_kwh | toFixed(2) }}</b></p>
                    <!-- csv export button copy table data to clipboard -->
                    <button class="btn btn-primary" @click="export_csv">Copy table data to clipboard</button>                    
                  </div>
                </div>                
            </div>
        </div>

    </div>
    
    <!-- Photo Lightbox - Using shared template -->
    <?php include "Modules/system/photo_lightbox_template.html"; ?>
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
        selected_xaxis: 'weighted_flowT_minus_outsideT',
        selected_yaxis: 'combined_cop',
        selected_color: 'weighted_prc_carnot',
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

    var periods_available = ['last365','last90','last30','last7','all','custom'];

    var default_minDays = {
        'last365': 330,
        'last90': 72,
        'last30': 24,
        'last7': 5,
        'all': 0,
        'custom':330
    };

    // Get URL parameters
    var urlParams = new URLSearchParams(window.location.search);
    for (var key in default_settings) {
        if (urlParams.has(key)) {
            page_settings[key] = urlParams.get(key);
            // Trim string values to handle Safari's trailing space issue
            if (key === 'filter' && typeof page_settings[key] === 'string') {
                page_settings[key] = page_settings[key].trim();
            }
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
    var options = ['flat','agile','cosy','go','ovohp','eon_next_pumped_v2'];
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
    var systems = [];

    /** Boundary HTML + meter class flags for one row (stats/meta come from API). */
    function decorateListRow(system) {
        var boundary_code = system.boundary_code;
        var metering = system.boundary_metering || {};
        var type = system.hp_type;
        var helper = "";
        if (type == "Ground Source" || type == "Water Source") {
            helper = "- Compressor metered\n";
        } else if (type != "Air-to-Air") {
            helper = "- Compressor and fan metered\n";
        }
        if (metering.hydraulic_separation) {
            if (metering.secondary_pumps_metered === true) {
                helper += "- Hydraulic separation used and secondary pumps/fans metered\n";
            } else if (metering.secondary_pumps_metered === false) {
                helper += "- Hydraulic separation used but secondary pumps/fans not metered\n";
            }
        }
        if (metering.primary_pump_metered === true) {
            helper += "- Primary pump metered\n";
        } else if (metering.primary_pump_metered === false) {
            helper += "- Primary pump not metered\n";
        }
        if (metering.immersion_heater_used === true) {
            if (metering.immersion_heater_metered === true) {
                helper += "- Immersion heater used and metered\n";
            } else if (metering.immersion_heater_metered === false) {
                helper += "- Immersion heater used but not metered\n";
            }
        } else if (metering.immersion_heater_used === false) {
            helper += "- Immersion heater not installed or used\n";
        }
        if (metering.backup_heater_used === true) {
            if (metering.backup_heater_metered === true) {
                helper += "- Backup heater used and metered\n";
            } else if (metering.backup_heater_metered === false) {
                helper += "- Backup heater used but not metered\n";
            }
        } else if (metering.backup_heater_used === false) {
            helper += "- Backup heater not installed or used\n";
        }
        if ((type == "Ground Source" || type == "Water Source")) {
            if (metering.brine_pump_metered === true) {
                helper += "- Brine pump used and metered\n";
            } else if (metering.brine_pump_metered === false) {
                helper += "- Brine pump used but not metered\n";
            }
        }
        if (type == "Air-to-Air") {
            helper = "";
        }
        system.boundary = "<span class='H"+boundary_code+"' title='System boundary H"+boundary_code+"\n"+helper+"'>H"+boundary_code+"</span>";

        var heat_meter = system.heat_meter;
        var elec_meter = system.electric_meter;
        system.heat_meter_class2 = heat_meter != null && heat_meter.indexOf("class 2") != -1;
        system.elec_meter_class1 = elec_meter != null && elec_meter.indexOf("class 1") != -1;
        return system;
    }

    function monthLabelToIso(label) {
        if (!label || typeof label !== 'string') return '';
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sept','Oct','Nov','Dec'];
        var parts = label.trim().split(' ');
        if (parts.length < 2) return '';
        var month = parts[0];
        var year = parts[1];
        var idx = months.indexOf(month);
        if (idx < 0) return '';
        return year + '-' + (idx + 1) + '-01';
    }

    columns['hp_type'].name = "Source";
    columns['hp_manufacturer'].name = "Manufacturer";
    columns['hp_model'].name = "Model";
    columns['hp_output'].name = "Rating";
    // columns['heatgeek'].name = "Training";
    
    // custom columns
    columns['training'] = { name: "Combined", heading: "Training", group: "Training", helper: "Training" };
    columns['learnmore'] = { name: "Combined", heading: "", group: "Learn more" };
    columns['photos'] = { name: "Photos", heading: "", group: "Learn more" };
    columns['boundary'] = { name: "Boundary", heading: "Hx", group: "Metering" };
    columns['data_flag'].heading = "";

    columns['hp_make_model'] = {
        name: "Make & Model",
        heading: "Make & Model",
        group: "Heat pump",
        helper: "Heat pump make and model"
    };

    // Calculate oversizing factor
    columns['oversizing_factor'] = {
        name: 'Oversizing Factor',
        heading: 'Oversizing',
        group: 'Heat pump',
        helper: 'Oversizing factor'
    };

    columns['heatpump_max_age'] = { name: "Live Data", heading: "Live", group: "Data Status", helper: "Data freshness status" };
    
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
    columns['space_cop'].heading = 'CH';

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
    template_views['topofthescops']['wide'] = ['location', 'installer_logo', 'installer_name', 'training', 'hp_type', 'hp_make_model', 'hp_output', 'data_flag', 'combined_cop', 'space_cop', 'water_cop', 'boundary' , 'mid_metering', 'learnmore', 'photos'];
    template_views['topofthescops']['narrow'] = ['installer_logo', 'training', 'hp_make_model', 'hp_output', 'combined_cop', 'learnmore', 'photos'];

    template_views['heatpumpfabric'] = {}
    template_views['heatpumpfabric']['wide'] = ['installer_logo', 'location', 'property', 'insulation', 'age', 'floor_area', 'hp_type', 'hp_model', 'hp_output', 'combined_cop', 'combined_elec_kwh_per_m2', 'combined_heat_kwh_per_m2'];
    template_views['heatpumpfabric']['narrow'] = ['installer_logo', 'hp_make_model', 'hp_output', 'combined_elec_kwh_per_m2'];

    template_views['costs'] = {}
    template_views['costs']['wide'] = ['installer_logo', 'training', 'location' , 'hp_type', 'hp_make_model', 'hp_output', 'electricity_tariff', 'selected_unit_rate', 'combined_cop', 'combined_heat_unit_cost', 'combined_cost', 'learnmore', 'photos'];
    template_views['costs']['narrow'] = ['installer_logo', 'training', 'hp_make_model', 'hp_output', 'combined_heat_unit_cost', 'learnmore', 'photos'];

    // Filter out installer columns for user mode
    if (mode == 'user') {
        for (var template in template_views) {
            for (var view in template_views[template]) {
                // Remove installer_logo and installer_name from all views
                template_views[template][view] = template_views[template][view].filter(function(column) {
                    return column !== 'installer_name';
                });
            }
        }
    }

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

    // if not 365 days column heading is COP
    if (stats_time_start != 'last365') {
        columns['combined_cop'].name = 'COP';
        columns['combined_cop'].heading = 'COP';
    }


    var app = new Vue({
        el: '#app',
        mixins: [PhotoLightboxMixin],
        data: {
            systems: systems,
            fSystems: [],
            loading: true,
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
            filter_query_parts: [],
            possibleValues: [],
            columnOptions: [],
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
            admin_restricted_list: true,
            line_best_fit_type: 'ols',
            
            // Photo lightbox
            lightboxOpen: false,
            currentPhotoIndex: 0,
            system_photos: [],
            loadingPhotos: false,

            has_more: false,
            listOffset: 0,
            pageLimit: 50,
            loadingNextPage: false,
            chartPoints: [],
            summaryTotals: { average_cop: 0, average_cop_kwh: 0, listed_system_count: 0 },
            summaryCsv: { columns: [], rows: [] },
            listRequestSeq: 0,
            pageCancelSource: null,
            summaryCancelSource: null,
            loadMoreObserver: null,
            _observerAttached: false
        },
        methods: {
            tariff_mode_changed: function() {
                this.currentSortDir = 'asc';
                this.currentSortColumn = 'combined_heat_unit_cost';
                this.url_update();
                this.reload();
            },

            tariff_calc: function() {
                /* Costs come from server using tariff_mode */
            },
            template_view: function(template) {
                this.selected_template = template;
                if (template == 'topofthescops') {
                    this.currentSortDir = 'desc';
                    this.currentSortColumn = 'combined_cop';
                    this.columns['combined_cop'].dp = 1;
                    this.reload();
                } else if (template == 'heatpumpfabric') {
                    this.currentSortDir = 'asc';
                    this.currentSortColumn = 'combined_elec_kwh_per_m2';
                    this.stats_time_start_change();

                } else if (template == 'costs') {
                    this.currentSortDir = 'asc';
                    this.currentSortColumn = 'combined_heat_unit_cost';
                    this.columns['combined_cop'].dp = 2;
                    this.stats_time_start_change();
                }

                this.url_update();

                resize();
            },
            create: function() {
                window.location = path+"system/new";
            },
            view: function(index) {
                let systemid = this.fSystems[index].id;
                window.location = path+"system/view?id=" + systemid;
            },
            remove: function(systemid) {

                var index = this.fSystems.findIndex(x => x.id === systemid);

                if (confirm("Are you sure you want to delete system: " + systemid + " "+ this.fSystems[index].location + "?")) {
                    axios.get(path+'system/delete?id=' + systemid)
                        .then(response => {
                            if (response.data.success) {
                                this.reload();
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

                this.url_update();
                this.refreshSummaryOnly();
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
                this.reload();
            },
            sort_only: function(column) {
                this.currentSortColumn = column;
                this.reload();
            },
            export_csv: function() {
                var self = this;
                var buildAndCopy = function() {
                    var csv = [];
                    var header = [];
                    var rows = self.summaryCsv.rows || [];
                    for (var i = 0; i < self.selected_columns.length; i++) {
                        var k = self.selected_columns[i];
                        if (k=='installer_logo' || k=='training' || k=='learnmore') continue;
                        header.push('"'+self.columns[k].name+'"');
                    }
                    csv.push(header.join(","));
                    for (var r = 0; r < rows.length; r++) {
                        var rowObj = rows[r];
                        var row = [];
                        for (var j = 0; j < self.selected_columns.length; j++) {
                            var column = self.selected_columns[j];
                            if (column=='installer_logo' || column=='training' || column=='learnmore') continue;
                            var value = rowObj[column];
                            if (value==null || value==='') value = '';
                            if (stats_columns[column]!=undefined && stats_columns[column]['dp']!=undefined && value !== '' && !isNaN(value)) {
                                value = Number(value).toFixed(stats_columns[column]['dp']+1);
                            }
                            row.push('"'+String(value).replace(/"/g,'""')+'"');
                        }
                        csv.push(row.join(","));
                    }
                    copy_text_to_clipboard(csv.join("\n"), 'CSV data copied to clipboard');
                };
                if (!this.summaryCsv.rows || !this.summaryCsv.rows.length) {
                    axios.get(path + 'system/list/' + mode + '/summary.json', {
                        params: this.buildApiParams({ include_csv: 1, include_points: 0 })
                    }).then(function(res) {
                        if (!res.data || !res.data.success) return;
                        if (res.data.csv) {
                            self.summaryCsv = res.data.csv;
                        }
                        buildAndCopy();
                    }).catch(function(e) { console.warn(e); });
                    return;
                }
                buildAndCopy();
            },
            stats_time_start_change: function () {
                // change available_months_end to only show months after start
                if (this.stats_time_start=='last7' || this.stats_time_start=='last30' || this.stats_time_start=='last90' || this.stats_time_start=='last365' || this.stats_time_start=='all' || this.stats_time_start=='custom') {
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
                } else if (this.stats_time_start=='custom') {
                    if (this.mode == 'public') this.minDays = default_minDays['custom'];
                    columns['combined_cop'].name = 'SPF';
                    columns['combined_cop'].heading = 'SPF';
                } else {
                    if (this.mode == 'public') this.minDays = default_minDays['all'];
                    columns['combined_cop'].name = 'COP';
                    columns['combined_cop'].heading = 'COP';
                }
                
                this.reload();
                this.url_update();
            },
            stats_time_end_change: function () {
                this.reload();
                this.url_update();
            },
            buildApiParams: function(extra) {
                var presets = ['last7','last30','last90','last365','all','custom'];
                var p = Object.assign({
                    tariff: this.tariff_mode,
                    sort: this.currentSortColumn,
                    dir: this.currentSortDir,
                    min_days: this.minDays,
                    show_mid: this.show_mid ? 1 : '',
                    show_other: this.show_other ? 1 : '',
                    show_hpint: this.show_hpint ? 1 : '',
                    show_errors: this.show_errors ? 1 : '',
                    filter: this.filterKey,
                    admin_restricted: (this.mode === 'admin' && this.admin_restricted_list) ? 1 : '',
                    xaxis: this.selected_xaxis,
                    yaxis: this.selected_yaxis,
                    color: this.selected_color,
                    csv_columns: this.selected_columns.join(','),
                    include_csv: 0,
                    include_points: this.chart_enable ? 1 : 0
                }, extra || {});
                if (presets.indexOf(this.stats_time_start) >= 0) {
                    p.period = this.stats_time_start;
                    p.month_start = '';
                    p.month_end = '';
                } else {
                    p.period = this.stats_time_start;
                    p.month_start = monthLabelToIso(this.stats_time_start);
                    if (this.stats_time_end === 'only') {
                        p.month_end = p.month_start;
                    } else {
                        p.month_end = monthLabelToIso(this.stats_time_end);
                    }
                }
                return p;
            },
            applySummaryResponse: function(sum) {
                if (!sum || !sum.success) return;
                var avg = sum.totals.average_cop;
                var avgk = sum.totals.average_cop_kwh;
                if (isNaN(avg)) avg = 0;
                if (isNaN(avgk)) avgk = 0;
                this.summaryTotals = {
                    average_cop: avg,
                    average_cop_kwh: avgk,
                    listed_system_count: sum.counts.listed_system_count
                };
                this.num_flagged = sum.counts.num_flagged;
                this.num_mid = sum.counts.num_mid;
                this.num_other = sum.counts.num_other;
                this.num_hpint = sum.counts.num_hpint;
                if (sum.points !== undefined) {
                    this.chartPoints = sum.points || [];
                }
                if (sum.csv !== undefined) {
                    this.summaryCsv = sum.csv || { columns: [], rows: [] };
                }
            },
            applyPageResponse: function(page, replace) {
                if (!page || !page.success) return;
                this.has_more = page.has_more;
                this.listOffset = (page.offset || 0) + ((page.rows && page.rows.length) ? page.rows.length : 0);
                var rows = page.rows || [];
                for (var i = 0; i < rows.length; i++) {
                    decorateListRow(rows[i]);
                }
                if (replace) {
                    this.fSystems = rows;
                } else {
                    this.fSystems = this.fSystems.concat(rows);
                }
            },
            reload: function() {
                var seq = ++this.listRequestSeq;
                if (this.pageCancelSource) {
                    try { this.pageCancelSource.cancel('reload'); } catch (e) {}
                }
                if (this.summaryCancelSource) {
                    try { this.summaryCancelSource.cancel('reload'); } catch (e) {}
                }
                this.pageCancelSource = axios.CancelToken.source();
                this.summaryCancelSource = axios.CancelToken.source();
                this.loading = true;
                this.loadingNextPage = false;
                this.listOffset = 0;
                this.fSystems = [];
                this.has_more = false;
                this.chartPoints = [];
                this.summaryCsv = { columns: [], rows: [] };
                var appvm = this;
                var basePage = this.buildApiParams({ offset: 0, limit: this.pageLimit });
                var baseSum = this.buildApiParams({});
                Promise.all([
                    axios.get(path + 'system/list/' + mode + '/page.json', { params: basePage, cancelToken: this.pageCancelSource.token }),
                    axios.get(path + 'system/list/' + mode + '/summary.json', { params: baseSum, cancelToken: this.summaryCancelSource.token })
                ]).then(function(results) {
                    if (seq !== appvm.listRequestSeq) return;
                    appvm.applySummaryResponse(results[1].data);
                    appvm.applyPageResponse(results[0].data, true);
                    appvm.loading = false;
                    appvm.url_update();
                    appvm.$nextTick(function() {
                        appvm.attachLoadObserver();
                        if (appvm.chart_enable) draw_scatter();
                    });
                }).catch(function(err) {
                    if (axios.isCancel && axios.isCancel(err)) return;
                    appvm.loading = false;
                    alert('Error loading list: ' + err);
                });
            },
            refreshSummaryOnly: function() {
                var seq = this.listRequestSeq;
                if (this.summaryCancelSource) {
                    try { this.summaryCancelSource.cancel('chart'); } catch (e) {}
                }
                this.summaryCancelSource = axios.CancelToken.source();
                var appvm = this;
                axios.get(path + 'system/list/' + mode + '/summary.json', {
                    params: this.buildApiParams({}),
                    cancelToken: this.summaryCancelSource.token
                }).then(function(res) {
                    if (seq !== appvm.listRequestSeq) return;
                    appvm.applySummaryResponse(res.data);
                    appvm.$nextTick(function() {
                        if (appvm.chart_enable) draw_scatter();
                    });
                }).catch(function(err) {
                    if (!(axios.isCancel && axios.isCancel(err))) console.warn(err);
                });
            },
            loadNextPage: function() {
                if (!this.has_more || this.loadingNextPage || this.loading) return;
                var seq = this.listRequestSeq;
                this.loadingNextPage = true;
                if (this.pageCancelSource) {
                    try { this.pageCancelSource.cancel('next'); } catch (e) {}
                }
                this.pageCancelSource = axios.CancelToken.source();
                var appvm = this;
                var params = this.buildApiParams({ offset: this.listOffset, limit: this.pageLimit });
                axios.get(path + 'system/list/' + mode + '/page.json', { params: params, cancelToken: this.pageCancelSource.token })
                    .then(function(res) {
                        if (seq !== appvm.listRequestSeq) return;
                        appvm.applyPageResponse(res.data, false);
                        appvm.loadingNextPage = false;
                        appvm.$nextTick(function() { appvm.attachLoadObserver(); });
                    }).catch(function(err) {
                        appvm.loadingNextPage = false;
                        if (!(axios.isCancel && axios.isCancel(err))) console.warn(err);
                    });
            },
            attachLoadObserver: function() {
                var self = this;
                if (!this.loadMoreObserver) {
                    this.loadMoreObserver = new IntersectionObserver(function(entries) {
                        entries.forEach(function(en) {
                            if (en.isIntersecting) self.loadNextPage();
                        });
                    }, { root: null, rootMargin: '400px', threshold: 0 });
                }
                var el = this.$refs.loadMoreSentinel;
                if (el) {
                    try { this.loadMoreObserver.disconnect(); } catch (e2) {}
                    this.loadMoreObserver.observe(el);
                }
            },
            toggle_chart: function() {
                this.chart_enable = !this.chart_enable;

                this.url_update();

                if (this.chart_enable) {
                    this.refreshSummaryOnly();
                    var self = this;
                    setTimeout(function() {
                        draw_scatter();
                    }, 200);
                }
            },
            column_format: function (system,key) {
                var val = system[key];

                if (key=='heatpump_max_age') {
                    var elec_ago = system['heatpump_elec_ago'];
                    var heat_ago = system['heatpump_heat_ago'];
                    var max_age = system['heatpump_max_age'];
 
                    if (max_age == 876000) {
                        return "<span class='data-status-circle data-status-unknown' title='Data status unknown'></span>";
                    }
                    
                    // Determine worst status (both must be fresh for green)
                    var status_class = '';
                    var status_text = '';
                    var status_detail = '';

                    if (max_age < 1) {
                        status_class = 'data-status-very-fresh';
                        status_text = 'Live data (last update < 1h ago)';
                        status_detail = 'Electric: ' + (elec_ago ? Math.round(elec_ago*3600) + ' seconds ago' : 'N/A') + 
                                        '\nHeat: ' + (heat_ago ? Math.round(heat_ago*3600) + ' seconds ago' : 'N/A');
                    } else if (max_age < 24) {
                        status_class = 'data-status-fresh';
                        status_text = 'Last update < 24h ago';
                        status_detail = 'Electric: ' + (elec_ago ? Math.round(elec_ago) + ' hours ago' : 'N/A') + 
                                        '\nHeat: ' + (heat_ago ? Math.round(heat_ago) + ' hours ago' : 'N/A');
                    } else if (max_age < 168) {
                        status_class = 'data-status-stale';
                        status_text = 'Last update ' + Math.round(max_age) + 'h ago';
                        status_detail = 'Electric: ' + (elec_ago ? Math.round(elec_ago/24) + ' days ago' : 'N/A') + 
                                        '\nHeat: ' + (heat_ago ? Math.round(heat_ago/24) + ' days ago' : 'N/A');
                    } else {
                        status_class = 'data-status-very-stale';
                        status_text = 'Last update ' + Math.round(max_age/24) + ' days ago';
                        status_detail = 'Electric: ' + (elec_ago ? Math.round(elec_ago/24) + ' days ago' : 'N/A') + 
                                        '\nHeat: ' + (heat_ago ? Math.round(heat_ago/24) + ' days ago' : 'N/A');
                    }
                    
                    
                    return "<span class='data-status-circle " + status_class + "' title='" + status_text + "\n" + status_detail + "'></span>";
                }

                if (key=='hp_make_model') {
                    // HTML-escape user-editable fields to prevent XSS
                    var manufacturer = system['hp_manufacturer'] ? String(system['hp_manufacturer']).replace(/[&<>"']/g, function(m) {
                        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
                    }) : '';
                    var model = system['hp_model'] ? String(system['hp_model']).replace(/[&<>"']/g, function(m) {
                        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
                    }) : '';
                    var makeModel = manufacturer + ' ' + model;
                    // If we have a heatpump_url, make it a link
                    if (system['heatpump_url']) {
                        return '<a href="' + system['heatpump_url'] + '">' + makeModel + '</a>';
                    }
                    return makeModel;
                }
                
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
                    return (Number(val)/(24*3600)).toFixed(0)+" days";
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
                if (key=='photos') {
                    var photos = "";
                    if (system['photo_count'] && system['photo_count'] > 0) {
                        photos += "<a href='javascript:void(0)' onclick='app.openSystemPhotos(" + system['id'] + ")' style='color: #0d6efd; font-size: 1.2em; text-decoration: none;' title='View system photos (" + system['photo_count'] + ")'><i class='fas fa-camera'></i></a>";
                    }
                    return photos;
                }
                if (key=='hp_type') {

                    let hybrid_str = "";
                    if (system['hp_hybrid'] == 1) {
                        hybrid_str = " <span class='text-muted' style='font-size:0.8em'>(HYBRID)</span>";
                    }

                    if (val=="Air Source") {
                        val = "<span style='color:#4f8baa'>Air</span>";
                    }
                    if (val=="Ground Source") {
                        val = "<span style='color:#938e03'>Ground</span>";
                    }

                    return val + hybrid_str;
                }
                if (key=='hp_output') {
                    if (val==null || val==0) return '';
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
                    return (Number(val)/3600).toFixed(0) + ' hrs';
                }
                
                if (key=='installation_Cost') {
                    if (val==null) return '';
                    if (val==0) return '';
                    return "£"+val;
                }
                
                if (stats_columns[key]!=undefined) {
                    if (val == null || val === '' || isNaN(val)) {
                        return val;
                    }
                    var n = (typeof val === 'number') ? val : Number(val);
                    if (!isFinite(n)) return val;
                    
                    let unit = '';
                    if (stats_columns[key]['unit']!=undefined) {
                        unit = ' '+stats_columns[key]['unit'];
                    }

                    let prepend = '';
                    if (stats_columns[key]['prepend']!=undefined) {
                        prepend = stats_columns[key]['prepend'];
                    }
                
                    if (stats_columns[key]['dp']!=undefined) {
                        return "<span title='"+n.toFixed(stats_columns[key]['dp']+1)+"'>"+prepend+n.toFixed(stats_columns[key]['dp'])+unit+"</span>";
                    }
                }

                if (key == 'weighted_average_flow_minus_outside') {
                    if (val == null || val == 0 ) return '';
                    return Number(val).toFixed(1);
                }

                if (key == 'prc_demand_hot_water') {
                    if (val == null || val == 0 ) return '';
                    return Number(val).toFixed(0) + '%';
                }
                
                return val;
            },
            // grey if start date is less that 1 year ago
            sinceClass: function(system,column) {
                // return node.since > 0 ? 'partial ' : '';
                // node.since is unix time in seconds
                if (column=='combined_cop' || column=='since' || column=='combined_data_length' || 
                    column=='quality_elec' || column=='water_cop' || column=='prc_demand_hot_water' ||
                    column=='space_cop' || column=='running_cop')
                {
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
                    if (this.stats_time_start=='last365' || this.stats_time_start=='all' || this.stats_time_start=='custom') {
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

            // ensure the column options in the "Filters" are listed with the same grouping and ordering as in the "Add fields"
            populateColumnOptions() {
                this.columnOptions = [];
                for (const group_name in this.column_groups) {
                    const group = this.column_groups[group_name];
                    group.forEach(column => {
                        this.columnOptions.push({
                            label: `${group_name}: ${column.name}`,
                            value: column.key
                        });
                    });
                }
            },

            // adds a filter part to the UI and stored array
            addFilterPart() {                
                // if one has been already added, don't add another one
                if (this.filter_query_parts.length == 0 || (!this.filter_query_parts[0].editing && !this.filter_query_parts[0].field == '')) {
                    // insert new filter part at the start of the array
                    this.filter_query_parts.unshift({
                        field: '',
                        operator: 'eq',
                        value: '',
                        operatorSign: '=',
                        column: '',
                        category: '',
                        enabled: true,
                        editing: true,
                        allNumerical: false
                    });
                }
            },

            // sets the specific filter part to edit mode
            editFilterPart(index) {
                this.$set(this.filter_query_parts[index], 'editing', true);

                // update possible values when editing
                this.updatePossibleValues(this.filter_query_parts[index].field, index);
            },

            reconstructFilterKey() {
                // reconstruct the filterKey from the filter_query_parts array
                const queryParts = this.filter_query_parts.map(part => `${part.field}:${part.value}:${part.operator}:${part.enabled ? 't' : 'f'}`);
                if (queryParts.length == 0) {
                    this.filterKey = '';
                } else {
                    this.filterKey = `query:${queryParts.join(',')}`;
                }
            },

            deriveColumnValues(part) {
                // convert part.value to a number if it is a numeric string
                if (!isNaN(part.value)) {
                    part.value = Number(part.value);
                }

                part.column = this.columns[part.field] ? this.columns[part.field].name : part.field;
                part.category = this.columns[part.field] ? this.columns[part.field].group : '';
                part.operatorSign = this.getOperatorSign(part.operator);

                // don't have the installer URL and icons don't look great in filter display, so ignore the formatting
                var colMeta = this.columns[part.field];
                if (part.field == 'installer_name' || (colMeta && colMeta.group == 'Training')) {
                    part.formattedValue = part.value;
                } else {
                    part.formattedValue = this.column_format({ [part.field]: part.value }, part.field);
                }

                return part;
            },

            // saves the filter part after editing
            saveFilterPart(index) {
                // populate derived values before saving
                const part = this.filter_query_parts[index];
                this.deriveColumnValues(part);        
                part.editing = false;

                // reconstruct the filterKey after editing
                this.reconstructFilterKey();

                // reapply the filters after editing
                this.filter_systems(); 
            },

            removeFilterPart(index) {
                // remove the filter part from the array
                const removedPart = this.filter_query_parts.splice(index, 1)[0];

                // reconstruct the filterKey without the removed part
                this.reconstructFilterKey();

                // reapply the filters after removing a part
                this.filter_systems();
            },

            // map operator to its sign
            getOperatorSign(operator) {
                const operatorMap = {
                    'eq': '=',
                    'ne': '!=',
                    'gt': '>',
                    'lt': '<',
                    'gte': '>=',
                    'lte': '<='
                };
                return operatorMap[operator] || operator; // return the operator itself if not found in the map
            },

            updatePossibleValues(field, index) {
                const values = [...new Set(this.fSystems.map(system => system[field]).filter(value => value !== null && value !== undefined))];
                
                // check if all values are numerical
                const allNumerical = values.every(value => !isNaN(value));

                // set the allNumerical flag for the current filter part
                this.$set(this.filter_query_parts[index], 'allNumerical', allNumerical);
                
                if (allNumerical) {
                    // sort numerically
                    this.possibleValues = values.sort((a, b) => a - b);
                } else {
                    // sort alphabetically
                    this.possibleValues = values.sort();
                }
            },

            /** Parse query:… filterKey into filter_query_parts for the sidebar (filtering is server-side). */
            syncFilterPartsFromFilterKey: function() {
                this.filter_query_parts = [];
                if (!this.filterKey || this.filterKey.indexOf('query') !== 0) {
                    return;
                }
                var query = this.filterKey.substring(6);
                var query_parts = query.split(',');
                for (var i = 0; i < query_parts.length; i++) {
                    var query_part = query_parts[i].split(':');
                    var field = query_part[0];
                    var value = query_part[1];
                    var operator = query_part[2] || 'eq';
                    var enabled = query_part[3] || 't';
                    this.filter_query_parts.push(this.deriveColumnValues({
                        field: field,
                        value: value,
                        operator: operator,
                        enabled: enabled === 't'
                    }));
                }
            },

            filter_systems: function() {
                this.reload();
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

                let is_default = true;
                var url = new URL(window.location.href);
                for (var key in settings) {
                    if (settings[key] != default_settings[key]) {
                        url.searchParams.set(key, settings[key]);
                        is_default = false;
                    } else {
                        url.searchParams.delete(key);
                    }
                }
                var decodedUrl = decodeURIComponent(url.toString());
                window.history.pushState({}, '', decodedUrl);

                // Update #map-link href with filter and minDays
                if (!is_default) {
                    var mapLink = document.getElementById('map-link');
                    if (mapLink) {
                        var mapUrl = new URL(mapLink.href);
                        mapUrl.searchParams.set('filter', this.filterKey);
                        mapUrl.searchParams.set('period', this.stats_time_start);
                        mapUrl.searchParams.set('minDays', this.minDays);
                        mapLink.href = mapUrl.toString();
                    }
                }

            },
            toggle_restricted_list: function() {
                this.admin_restricted_list = !this.admin_restricted_list;
                this.reload();
            },
            chartAxisChanged: function() {
                this.url_update();
                this.refreshSummaryOnly();
            },
        },
        mounted: function() {
            var self = this;
            this.syncFilterPartsFromFilterKey();
            this.populateColumnOptions();
            this.$nextTick(function() {
                resize(true);
                self.reload();
            });
        },
        updated: function() {
            if (this.has_more && this.fSystems.length && !this.loading) {
                this.$nextTick(this.attachLoadObserver);
            }
        },
        filters: {
            toFixed: function(val, dp) {
                if (val == null || val === '' || isNaN(val)) {
                    return val;
                }
                var num = (typeof val === 'number') ? val : Number(val);
                if (!isFinite(num)) return val;
                return num.toFixed(dp);
            },
            time_ago: function(val) {
                return time_ago(val);
            }
        },

        computed: {
            // Totals from server (full filtered set; not just loaded rows)
            totals: function () {
                var t = this.summaryTotals;
                var ac = t.average_cop;
                var ack = t.average_cop_kwh;
                if (ac == null || isNaN(ac)) ac = 0;
                if (ack == null || isNaN(ack)) ack = 0;
                return {
                    average_cop: ac,
                    average_cop_kwh: ack,
                    listed_system_count: t.listed_system_count || 0
                };
            }
        }
    });
    Vue.component('v-select', VueSelect.VueSelect);

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

        // Public mode
        if (app.mode == 'public') {
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
        // User mode
        } else if (app.mode == 'user') {
            if (width<800) {
                app.selected_columns = template_views[app.selected_template]['narrow'];
                app.showContent = false;
                if (first) app.show_field_selector = false;
                app.columns['training'].heading = "";
                
            } else {
                app.selected_columns = [...template_views[app.selected_template]['wide']];
                app.selected_columns.push('heatpump_max_age');
                app.showContent = true;
                app.columns['training'].heading = "Training";
            }
        // Admin mode
        } else {
            if (width<800) {
                app.selected_columns = ['hp_model', 'hp_output', 'combined_cop'];
                app.showContent = false;
                if (first) app.show_field_selector = false;
            } else {
                app.selected_columns = ['location', 'hp_type', 'hp_model', 'hp_output', 'data_flag', 'combined_cop', 'space_cop', 'boundary', 'heatpump_max_age'];
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
