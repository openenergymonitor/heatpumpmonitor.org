<?php
/*
  heatpumpmonitor.org version of the emoncms MyHeatpump app view
  (emoncms/app apps/OpenEnergyMonitor/myheatpump/myheatpump.php).
  Markup follows the emoncms card layout. Differences: title from the system
  details, no app config, Back to System link, Start and End markup inline
  (emoncms includes Lib/timebar_manual.php) and no Solar split in heat demand.
  Navigation is the one click period and power window buttons of the emoncms
  classic-nav branch (commit 769cfbc).
*/
defined('EMONCMS_EXEC') or die('Restricted access');
global $path, $session;
// Cache busting version for the dashboard scripts and styles: the newest
// modification time of the files in this folder, so a deployed change gets new URLs
$v = max(array_map('filemtime', array_merge(glob(__DIR__."/*.js"), glob(__DIR__."/*.css"))));

$app_title = "My Heatpump";
if ($system_data->hp_output>0 && $system_data->hp_model!="" && $system_data->refrigerant!="" && $system_data->location!="") {
    $app_title = $system_data->hp_output."kW ".$system_data->hp_model." ".$system_data->refrigerant.", ".$system_data->location;
}

// if not shared add (private) to the title
if ($private_mode || $system_data->share==0) {
    $app_title .= " (Private)";
}
?>
<link href="<?php echo $path; ?>Modules/dashboard/app-theme.css?v=<?php echo $v; ?>" rel="stylesheet">
<link href="<?php echo $path; ?>Modules/dashboard/app-kit.css?v=<?php echo $v; ?>" rel="stylesheet">
<link href="<?php echo $path; ?>Modules/dashboard/style.css?v=<?php echo $v; ?>" rel="stylesheet">
<link rel="stylesheet" href="<?php echo $path; ?>theme/vendor/montserrat/montserrat.css" integrity="sha384-AMYJBiYWg2fH/qW9FBMISO0zcqcNjcZlfNz2wBgd5TALef3TT+VTOri6yfRQAD5J" />

<script src="<?php echo $path; ?>theme/vendor/jquery-3.6.3/jquery.min.js" integrity="sha384-Ft/vb48LwsAEtgltj7o+6vtS2esTU9PCpDqcXs4OCVQFZu5BqprHtUCZ4kjK+bpE"></script>
<script src="<?php echo $path; ?>theme/vendor/flot-5.1.0-mod/flot-5.1.0.mod.min.js" integrity="sha384-rKblsh+7b/6K5Ae+sg/HcUG7rP1MOMKv6V5ABCM6odDFU/hNCW8/fdQPDxvAwQon"></script>
<script src="<?php echo $path; ?>Modules/dashboard/vis.helper.js?v=<?php echo $v; ?>"></script>

<!-- Start and End date picker, Vue 3 component from emoncms Lib/js/DateTimePicker.js -->
<script src="<?php echo $path; ?>theme/vendor/vue-3.5.22/vue.global.prod.js" integrity="sha384-8b+88atXoPL4ijtVqe+2pA+PC8VNYSGqubRseMLV0qxfa5GMHeftEEHvk4eM4RFT"></script>
<script src="<?php echo $path; ?>Modules/dashboard/DateTimePicker.js?v=<?php echo $v; ?>"></script>
<link href="<?php echo $path; ?>Modules/dashboard/datetimepicker.css?v=<?php echo $v; ?>" rel="stylesheet">

<div class="app-page" data-bs-theme="light">
  <section id="app-block">

    <div class="app-card">
      <nav class="app-card-head">
        <div class="nav nav-underline">
          <button class="nav-link active"><i class="svg-icon-hpmon hp-title-icon"></i><span id="app_name"><?php echo htmlspecialchars($app_title); ?></span></button>
        </div>
        <div class="app-card-tools">
          <span id="live-status" class="app-status"><span class="app-status-dot"></span><span class="app-status-text"></span></span>
          <div class="nav">
            <a id="permalink" href="" title="Share this view" class="nav-link"><i class="svg-icon-link"></i></a>
          </div>
        </div>
      </nav>
      <div id="live_table" class="app-live">
        <div id="realtime_cop_div" style="cursor:pointer" title="Switch between 30 minute and instant COP">
          <div class="app-live-label" id="realtime_cop_title">COP 30 mins</div>
          <div class="app-live-value text-hp-cop" id="realtime_cop_value">---</div>
        </div>
        <div>
          <div class="app-live-label">Electric</div>
          <div class="app-live-value text-hp-elec"><span id="heatpump_elec">---</span><span class="power-unit-static">W</span></div>
        </div>
        <div>
          <div class="app-live-label">Heat output</div>
          <div class="app-live-value text-hp-heat"><span id="heatpump_heat">---</span><span class="power-unit-static">W</span></div>
        </div>
        <div>
          <div class="app-live-label">Flow<span class="hp-return-part"> &middot; return</span></div>
          <div class="app-live-value text-hp-flow"><span id="heatpump_flowT">---</span><span class="power-unit-static">&deg;C</span><span class="hp-return-part hp-return"><span id="heatpump_returnT">---</span>&deg;</span></div>
        </div>
      </div>
    </div>

    <div class="app-card app-card-body">
      <div id="graph-nav" class="app-navbar">
        <div class="btn-group app-segmented bargraph-navigation">
          <button class="btn bargraph_mode active" mode="combined" title="Combined">All</button>
          <button class="btn bargraph_mode" mode="running" title="When running"><i class="svg-icon-play"></i> Running</button>
          <button class="btn bargraph_mode" mode="space" title="Space heating"><i class="svg-icon-radiator"></i> Space</button>
          <button class="btn bargraph_mode" mode="water" title="Water heating"><i class="svg-icon-shower"></i> Water</button>
          <button class="btn bargraph_mode" mode="cooling" title="Cooling"><i class="svg-icon-snowflake"></i> Cooling</button>
        </div>
        <span id="window-label" class="app-window-text"></span>
        <div class="btn-group app-segmented bargraph-navigation hp-periods ms-auto">
          <button class="btn hp-period" days="day" title="Power view, last 24 hours">Day</button>
          <button class="btn hp-period" days="7" title="Daily bars, last 7 days">Week</button>
          <button class="btn hp-period" days="30" title="Daily bars, last 30 days">Month</button>
          <button class="btn hp-period" days="90" title="Daily bars, last 90 days">3 Months</button>
          <button class="btn hp-period" days="365" title="Daily bars, last 365 days">Year</button>
          <button class="btn hp-period" days="all" title="Daily bars, all time">All</button>
        </div>
        <div class="powergraph-navigation hp-power-nav ms-auto" style="display:none">
          <div class="btn-group app-segmented">
            <button class="btn hp-time" hours="1" title="Last hour">H</button>
            <button class="btn hp-time" hours="6" title="Last 6 hours">6</button>
            <button class="btn hp-time" hours="24" title="Last 24 hours">D</button>
            <button class="btn hp-time" hours="168" title="Last 7 days">W</button>
            <button class="btn hp-time" hours="720" title="Last 30 days">M</button>
          </div>
          <div class="btn-group app-timebar">
            <button id="zoomin" class="btn" title="Zoom in"><i class="svg-icon-zoom_in"></i></button>
            <button id="zoomout" class="btn" title="Zoom out"><i class="svg-icon-zoom_out"></i></button>
            <button id="left" class="btn" title="Earlier"><i class="svg-icon-pan_left"></i></button>
            <button id="right" class="btn" title="Later"><i class="svg-icon-pan_right"></i></button>
          </div>
          <div class="btn-group app-segmented">
            <button class="btn viewhistory" title="Back to daily summary">Back</button>
          </div>
        </div>
        <div class="btn-group app-timebar hp-manual">
          <button id="time-manual-open" class="btn" title="Select time window"><i class="bi bi-calendar-range"></i></button>
        </div>
      </div>
      <div id="graph-nav-manual" class="app-navbar d-none">
        <div class="input-group w-auto">
          <span class="input-group-text">Start</span>
          <input id="request-start" class="form-control" type="text" placeholder="YYYY-MM-DD HH:MM:SS" />
        </div>
        <div class="input-group w-auto">
          <span class="input-group-text">End</span>
          <input id="request-end" class="form-control" type="text" placeholder="YYYY-MM-DD HH:MM:SS" />
        </div>
        <div class="btn-group app-timebar">
          <button id="time-manual-close" class="btn" title="Done"><i class="bi bi-check-lg"></i></button>
        </div>
      </div>

      <div id="placeholder_bound" style="width:100%; height:500px; overflow:hidden; position:relative">
        <div id="placeholder" style="height:500px"></div>
        <div id="overlay" style="display:none">
          <div id="overlay_text"></div>
        </div>
      </div>
      <div id="chart-legend" class="app-legend"></div>

      <div class="app-card-caption app-chart-foot">
        <span class="app-section-label">COP in window</span>
        <b id="window-cop" class="hp-window-cop"></b>
        <span id="window-carnot-cop" class="app-caption-note"></span>
        <span id="emitter-spec-volume" class="app-caption-note" style="display:none"></span>
        <span id="data-error" style="display:none">DATA ERROR</span>
        <div class="nav ms-auto"><button id="advanced-toggle" class="nav-link" style="display:none">SHOW DETAIL</button><button id="heatdemand-toggle" class="nav-link" style="display:none">SHOW HEAT DEMAND</button></div>
      </div>
    </div>

    <div id="advanced-block" class="app-card app-card-body" style="display:none">
      <div>

        <div id="error-message" style="display:none" class="alert alert-danger"></div>

        <div class="app-tiles">
          <div class="show_stats_category active" key="combined">
            <div class="app-tile-title">Full window</div>
            <div class="app-tile-value"><span class="cop_combined">---</span></div>
          </div>

          <div class="show_stats_category" key="when_running" style="color:#698d5d">
            <div class="app-tile-title">When running</div>
            <div class="app-tile-value"><span class="cop_when_running">---</span></div>
          </div>

          <div class="show_stats_category" key="space_heating" style="color:#f6a801">
            <div class="app-tile-title">Space heating</div>
            <div class="app-tile-value"><span class="cop_space_heating">---</span></div>
          </div>

          <div class="show_stats_category" key="water_heating" style="color:#014656">
            <div class="app-tile-title">Water heating</div>
            <div class="app-tile-value"><span class="cop_water_heating">---</span></div>
          </div>

          <div class="show_stats_category" key="cooling" style="color:#014656; display:none">
            <div class="app-tile-title">Cooling</div>
            <div class="app-tile-value"><span class="cop_cooling">---</span></div>
          </div>
        </div>

        <table class="table">
          <tr>
            <th></th>
            <th class="stats-col text-body-secondary">Min</th>
            <th class="stats-col text-body-secondary">Max</th>
            <th class="stats-col text-body-secondary">Diff</th>
            <th class="stats-col">Mean</th>
            <th class="stats-col">kWh</th>
          </tr>
          <tbody class="stats_category" key="combined"></tbody>
          <tbody class="stats_category" key="when_running" style="display:none"></tbody>
          <tbody class="stats_category" key="water_heating" style="display:none"></tbody>
          <tbody class="stats_category" key="space_heating" style="display:none"></tbody>
          <tbody class="stats_category" key="cooling" style="display:none"></tbody>
        </table>

        <div class="app-section-label app-options-label">Chart options</div>
        <div class="app-options">
          <div id="show_immersion_bound" style="display:none" class="app-option">
            <div style="float:right"><span id="immersion_kwh"></span> kWh</div>
            <input id="show_immersion" type="checkbox">
            <b>Show immersion</b>
          </div>

          <div id="show_flow_rate_bound" style="display:none" class="app-option">
            <input id="show_flow_rate" type="checkbox">
            <b>Show flow rate</b>
          </div>
          <div id="show_dhw_temp_bound" style="display:none" class="app-option">
            <input id="show_dhw_temp" type="checkbox">
            <b>Show DHW temperature/charge</b>
          </div>
          <div id="show_cooling_bound" class="app-option">
            <div style="float:right"><span id="total_defrost_and_loss_kwh"></span> kWh (<span id="prc_defrost_and_loss"></span>%)</div>
            <input id="show_defrost_and_loss" type="checkbox">
            <b>Show defrosts and other heat lost</b>
          </div>

          <div id="show_inst_cop_bound" class="app-option">
            <input id="show_instant_cop" type="checkbox">
            <b>Show instantaneous COP</b>

            <div id="inst_cop_options" style="display:none">
              <div class="input-group">
                <span class="input-group-text">Valid COP</span>
                <span class="input-group-text">Min</span>
                <input type="text" class="form-control" style="width:64px" id="inst_cop_min" value="1.0">
                <span class="input-group-text">Max</span>
                <input type="text" class="form-control" style="width:64px" id="inst_cop_max" value="8.0">
              </div>

              <div class="input-group">
                <span class="input-group-text">Moving average</span>
                <select id="inst_cop_mv_av_dp" class="form-select" style="width:100px">
                  <option value="0" selected>Disabled</option>
                  <option value="3">3 points</option>
                  <option value="5">5 points</option>
                  <option value="15">15 points</option>
                </select>
              </div>
            </div>
          </div>

          <div class="app-option">
            <input id="carnot_enable" type="checkbox">
            <b>Show simulated carnot heat output</b>

            <div id="carnot_sim_options" style="display:none">
              <div class="input-group">
                <span class="input-group-text">Condensing offset (K)</span>
                <input type="text" class="form-control" style="width:64px" id="condensing_offset" value="2">
              </div>
              <div class="input-group">
                <span class="input-group-text">Evaporator offset (K)</span>
                <input type="text" class="form-control" style="width:64px" id="evaporator_offset" value="-6">
              </div>
              <div id="heatpump_factor_bound" class="input-group">
                <span class="input-group-text">Heatpump factor</span>
                <input type="text" class="form-control" style="width:64px" id="heatpump_factor" value="0.47">
              </div>
              <div id="fixed_outside_temperature_bound" class="input-group">
                <span class="input-group-text">Fixed outside temperature (C)</span>
                <input type="text" class="form-control" style="width:64px" id="fixed_outside_temperature" value="6.0">
              </div>
            </div>
          </div>

          <div class="app-option">
            <input id="carnot_enable_prc" type="checkbox">
            <b>Show as % of carnot COP</b>

            <div id="carnot_prc_options" style="display:none">
              <p>Measured COP vs Carnot COP distribution:</p>
              <div id="histogram_bound" style="width:100%; height:400px;overflow:hidden">
                <div id="histogram" style="height:400px"></div>
              </div>
            </div>
          </div>

          <div class="app-option">
            <input id="emitter_spec_enable" type="checkbox">
            <b>Calculate emitter spec and system volume</b>
            <div id="emitter_spec_options" style="margin-top:10px; display:none">
              <p>Make sure there is at least a short period of steady state running in the window.<br>Heat output spikes after hot water cycles can skew results.</p>
              <div class="input-group">
                <input type="text" class="form-control" style="width:64px" id="kW_at_50" disabled>
                <span class="input-group-text">kW @ DT50</span>
                <span class="input-group-text">Fix <input type="checkbox" id="fix_kW_at_50" class="ms-1"></span>
              </div>
              <div class="input-group">
                <input type="text" class="form-control" style="width:64px" id="system_volume" disabled>
                <span class="input-group-text">Litres</span>
              </div>
              <div class="input-group">
                <span class="input-group-text">Room temperature</span>
                <input type="text" class="form-control" style="width:64px" id="room_temperature" disabled>
                <span class="input-group-text">&deg;C</span>
                <span class="input-group-text">Manual <input type="checkbox" id="manual_roomT_enable" class="ms-1"></span>
              </div>
            </div>
          </div>

          <!-- DHW Standby Heat Loss Calculation Option -->
          <div class="app-option">
            <input id="standby_dhw_hl_enable" type="checkbox">
            <b>Calculate DHW Standby Heat Loss Coefficient</b>
            <div id="standby_dhw_hl_options" style="margin-top:10px; display:none;">
              <p class="small text-body-secondary"><i>Ensure the selected window only shows natural DHW temperature decay (no heating cycles, no DHW use such as showering). Cylinder volume and environmental temperature are required to compute standby heat loss coefficient, which only works if DHW temperature is measured in °C and not % Charge. </i></p>
              <div class="input-group">
                <span class="input-group-text">Cylinder Volume (V<sub>cyl</sub>)</span>
                <input type="text" class="form-control" style="width:74px" id="cylinder_volume" value="200">
                <span class="input-group-text">L</span>
              </div>
              <div class="input-group">
                <span class="input-group-text">Environment Temp (T<sub>env</sub>)</span>
                <input type="text" class="form-control" style="width:64px" id="env_temperature" value="15">
                <span class="input-group-text">°C</span>
              </div>
              <div style="margin-top:10px;">
                DHW Heat Loss Coefficient (U): <b id="standby_dhw_hl_result">---</b> W/K | DHW charge half-life (T<sub>1/2</sub>): <b id="standby_dhw_t_half_result">---</b> days
              </div>
            </div>
          </div>
          <!-- End DHW Standby Heat Loss -->

          <div class="app-option">
            <div style="float:right"><span id="standby_kwh"></span> kWh</div>
            <input id="configure_standby" type="checkbox">
            <b>Configure standby</b>
            <div id="configure_standby_options" style="display:none">
              <div class="input-group">
                <span class="input-group-text">Starting power</span>
                <input type="text" class="form-control" style="width:64px" id="starting_power" value="150">
                <span class="input-group-text">W</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div id="heatdemand-block" class="app-card app-card-body" style="display:none">
      <div id="heatdemand_bound" style="width:100%; height:400px; overflow:hidden">
        <div id="heatdemand" style="height:400px"></div>
      </div>
      <p id="heatdemand-design" class="mb-1"></p>
      <p id="heatdemand-result" class="small"></p>
      <div class="d-flex flex-wrap gap-2">
        <div class="input-group w-auto" title="Outside temperature for the heat demand estimate">
          <span class="input-group-text">Design outside</span>
          <input type="number" class="form-control" style="width:64px" id="heatdemand_design_outsideT" value="-3" step="0.5">
          <span class="input-group-text">&deg;C</span>
        </div>
        <div class="input-group w-auto" title="Target room temperature for the heat demand estimate">
          <span class="input-group-text">Room</span>
          <input type="number" class="form-control" style="width:64px" id="heatdemand_roomT" value="20" step="0.5">
          <span class="input-group-text">&deg;C</span>
          <span class="input-group-text"><input type="checkbox" id="heatdemand_fixed_roomT" class="me-1"> Use as room T</span>
        </div>
        <div class="input-group w-auto" title="Days with a smaller inside to outside temperature difference are excluded">
          <span class="input-group-text">Min ΔT</span>
          <input type="number" class="form-control" style="width:64px" id="heatdemand_min_dT" value="0">
          <span class="input-group-text">K</span>
        </div>
        <div class="input-group w-auto" title="Days with less heat meter data coverage than this are excluded">
          <span class="input-group-text">Min quality</span>
          <input type="number" class="form-control" style="width:64px" id="heatdemand_min_quality" value="95">
          <span class="input-group-text">%</span>
        </div>
        <div class="input-group w-auto" title="Plot and fit groups of days separately">
          <span class="input-group-text">Split by</span>
          <select class="form-select" id="heatdemand_split">
            <option value="none">None</option>
            <option value="year">Year</option>
            <option value="season">Heating season</option>
          </select>
        </div>
      </div>
      <div class="small text-body-secondary mt-3">
        <p class="mb-1">Each point is one day: mean heat output against mean room minus outside temperature. Hover over a point for details, click to open the power view for that day. The slope of the fit line is the heat loss in W/K.</p>
        <ul class="mb-0 ps-3">
          <li><b>Fit:</b> the balance point is where the fit line reaches zero heat. Days below the balance point are mostly water heating and standby, so the fit excludes them and refits until the balance point settles. Days more than 2.5 standard errors from the line are also excluded.</li>
          <li><b>Design outside and Room:</b> set the design ΔT for the heat demand estimate.</li>
          <li><b>Use as room T:</b> replaces measured room temperature with the Room value. Used automatically when there is no room temperature feed. Useful when the feed is not representative of the whole house.</li>
          <li><b>Min ΔT:</b> optional, excludes mild days before the fit.</li>
          <li><b>Min quality:</b> percentage of the day with heat meter data. Days with gaps under-count heat and are excluded.</li>
          <li><b>Split by:</b> heating season runs July to June.</li>
        </ul>
        <p class="mt-2 mb-1">Notes on the estimate:</p>
        <ul class="mb-0 ps-3">
          <li>Heat comes from the mode selected above the chart. All includes water heating. For a typical 100 m² house the extra heat for water heating is similar to the reduction from internal gains, so All is often a good approximation of the building heat loss. Space gives a lower figure.</li>
          <li>Estimate is mean output over a day at the design temperature, not peak output within the day.</li>
          <li>Range is an 80% prediction interval: 8 out of 10 days are expected to fall within it. A design temperature colder than any recorded day is an extrapolation.</li>
          <li id="heatdemand-scope-note" style="display:none"></li>
        </ul>
      </div>
    </div>

    <div class="app-card app-card-body">
      <div class="app-card-caption"><span class="app-section-label"><span id="all_time_history_title">All time history</span> <span id="total_mode"></span></span>
        <div class="btn-group app-segmented ms-auto">
          <button class="btn total-period" period="last365" title="Totals for the last 365 days">Last year</button>
          <button class="btn total-period active" period="all" title="Totals since the start of monitoring">All time</button>
        </div>
      </div>
      <div class="app-live">
        <div>
          <div class="app-live-label">Electricity input</div>
          <div class="app-live-value text-hp-elec"><span id="total_elec"></span><span class="power-unit-static">kWh</span></div>
        </div>
        <div>
          <div class="app-live-label">Heat output</div>
          <div class="app-live-value text-hp-heat"><span id="total_heat"></span><span class="power-unit-static">kWh</span></div>
        </div>
        <div>
          <div class="app-live-label">SCOP</div>
          <div class="app-live-value text-hp-cop"><span id="total_cop"></span></div>
        </div>
        <div id="boiler_total_bound" style="display:none">
          <div class="app-live-label">Boiler heat</div>
          <div class="app-live-value"><span id="total_boiler"></span><span class="power-unit-static">kWh</span></div>
        </div>
      </div>
    </div>

  </section>

  <?php if ($private_mode === false) { ?>
  <div style="text-align:center; padding:10px 0 20px">
    <a href="<?php echo $path; ?>system/view?id=<?php echo $id; ?>" class="btn btn-outline-primary">Back to System</a>
  </div>
  <?php } ?>

  <div class="ajax-loader"></div>
</div>

<script>
  var apikey = <?php echo json_encode($apikey); ?>;
  var session_write = 0;

  var config = {};
  config.id = <?php echo (int) $id; ?>;
  config.db = {};
</script>

<script src="<?php echo $path; ?>Modules/dashboard/myheatpump_process.js?v=<?php echo $v; ?>"></script>
<script src="<?php echo $path; ?>Modules/dashboard/myheatpump_powergraph.js?v=<?php echo $v; ?>"></script>
<script src="<?php echo $path; ?>Modules/dashboard/myheatpump_bargraph.js?v=<?php echo $v; ?>"></script>
<script src="<?php echo $path; ?>Modules/dashboard/myheatpump_heatdemand.js?v=<?php echo $v; ?>"></script>
<script src="<?php echo $path; ?>Modules/dashboard/myheatpump.js?v=<?php echo $v; ?>"></script>
