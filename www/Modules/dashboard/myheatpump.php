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

      <div id="error-message" style="display:none" class="alert alert-danger mt-2"></div>

      <div class="app-tiles hp-cop-tiles hp-power-only" style="display:none">
        <div class="show_stats_category" key="combined">
          <div class="app-tile-title">Full window</div>
          <div class="app-tile-value"><span class="cop_combined">---</span></div>
          <div class="app-tile-sub kwh_combined"></div>
        </div>

        <div class="show_stats_category" key="when_running" style="color:#698d5d">
          <div class="app-tile-title">When running</div>
          <div class="app-tile-value"><span class="cop_when_running">---</span></div>
          <div class="app-tile-sub kwh_when_running"></div>
        </div>

        <div class="show_stats_category" key="space_heating" style="color:#f6a801">
          <div class="app-tile-title">Space heating</div>
          <div class="app-tile-value"><span class="cop_space_heating">---</span></div>
          <div class="app-tile-sub kwh_space_heating"></div>
        </div>

        <div class="show_stats_category" key="water_heating" style="color:#014656">
          <div class="app-tile-title">Water heating</div>
          <div class="app-tile-value"><span class="cop_water_heating">---</span></div>
          <div class="app-tile-sub kwh_water_heating"></div>
        </div>

        <div class="show_stats_category" key="cooling" style="color:#014656; display:none">
          <div class="app-tile-title">Cooling</div>
          <div class="app-tile-value"><span class="cop_cooling">---</span></div>
          <div class="app-tile-sub kwh_cooling"></div>
        </div>
      </div>

      <div class="app-card-caption app-chart-foot">
        <span class="app-section-label hp-daily-only">COP in window</span>
        <b id="window-cop" class="hp-window-cop hp-daily-only"></b>
        <span id="window-carnot-cop" class="app-caption-note"></span>
        <span id="emitter-spec-volume" class="app-caption-note" style="display:none"></span>
        <span id="data-error" style="display:none">DATA ERROR</span>
        <div class="app-segmented hp-panel-toggles">
          <button id="advanced-toggle" class="btn" style="display:none" aria-expanded="false" aria-controls="advanced-block" title="Min, max and mean of each feed"><i class="bi bi-table"></i> <span>Stats</span></button>
          <button id="options-toggle" class="btn" style="display:none" aria-expanded="false" aria-controls="options-block" title="Chart options and tools"><i class="bi bi-sliders"></i> <span>Options</span></button>
        </div>
      </div>
    </div>

    <div id="advanced-block" class="app-card app-card-body" style="display:none">
      <div>
        <table class="table">
          <tr>
            <th></th>
            <th class="stats-col text-body-secondary">Min</th>
            <th class="stats-col text-body-secondary">Max</th>
            <th class="stats-col text-body-secondary">Diff</th>
            <th class="stats-col">Mean</th>
            <th class="stats-col" title="Temperatures and heat output averaged with each sample weighted by heat output. For heat output, the output level a typical kWh was delivered at">Heat-weighted mean</th>
            <th class="stats-col">kWh</th>
          </tr>
          <tbody class="stats_category" key="combined"></tbody>
          <tbody class="stats_category" key="when_running" style="display:none"></tbody>
          <tbody class="stats_category" key="water_heating" style="display:none"></tbody>
          <tbody class="stats_category" key="space_heating" style="display:none"></tbody>
          <tbody class="stats_category" key="cooling" style="display:none"></tbody>
        </table>
      </div>
    </div>

    <div id="options-block" class="app-card app-card-body" style="display:none">
      <div>
        <div class="app-section-label app-options-label">Chart options</div>
        <div class="app-options">
          <div id="show_immersion_bound" style="display:none" class="app-option">
            <label class="app-option-head">
              <span style="float:right"><span id="immersion_kwh"></span> kWh</span>
              <input id="show_immersion" type="checkbox">
              <b>Show immersion</b>
            </label>
          </div>

          <div id="show_flow_rate_bound" style="display:none" class="app-option">
            <label class="app-option-head">
              <input id="show_flow_rate" type="checkbox">
              <b>Show flow rate</b>
            </label>
          </div>
          <div id="show_dhw_temp_bound" style="display:none" class="app-option">
            <label class="app-option-head">
              <input id="show_dhw_temp" type="checkbox">
              <b>Show DHW temperature/charge</b>
            </label>
          </div>
          <div id="show_cooling_bound" class="app-option">
            <label class="app-option-head">
              <span style="float:right"><span id="total_defrost_and_loss_kwh"></span> kWh (<span id="prc_defrost_and_loss"></span>%)</span>
              <input id="show_defrost_and_loss" type="checkbox">
              <b>Show defrosts and other heat lost</b>
            </label>
          </div>

          <div id="show_inst_cop_bound" class="app-option">
            <label class="app-option-head">
              <input id="show_instant_cop" type="checkbox">
              <b>Show instantaneous COP</b>
            </label>

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
            <label class="app-option-head">
              <input id="carnot_enable" type="checkbox">
              <b>Show simulated carnot heat output</b>
            </label>

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
            <label class="app-option-head">
              <input id="carnot_enable_prc" type="checkbox">
              <b>Show as % of carnot COP</b>
            </label>

            <div id="carnot_prc_options" style="display:none">
              <p>Measured COP vs Carnot COP distribution:</p>
              <div id="histogram_bound" style="width:100%; height:400px;overflow:hidden">
                <div id="histogram" style="height:400px"></div>
              </div>
            </div>
          </div>

          <div class="app-option">
            <label class="app-option-head">
              <input id="emitter_spec_enable" type="checkbox">
              <b>Calculate emitter spec and system volume</b>
            </label>
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
            <label class="app-option-head">
              <input id="standby_dhw_hl_enable" type="checkbox">
              <b>Calculate DHW Standby Heat Loss Coefficient</b>
            </label>
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
            <label class="app-option-head">
              <span style="float:right"><span id="standby_kwh"></span> kWh</span>
              <input id="configure_standby" type="checkbox">
              <b>Configure standby</b>
            </label>
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

    <div class="app-card app-card-body">
      <div class="app-card-caption"><span class="app-section-label"><span id="all_time_history_title">All time history</span> <span id="total_mode"></span></span>
        <div class="hp-totals-tools ms-auto">
          <div class="btn-group app-segmented" id="totals-breakdown-bound" style="display:none">
            <button class="btn" id="totals-breakdown-toggle" title="Show totals for each mode"><i class="bi bi-chevron-down"></i> By mode</button>
          </div>
          <div class="btn-group app-segmented">
            <button class="btn total-period" period="last365" title="Totals for the last 365 days">Last year</button>
            <button class="btn total-period active" period="all" title="Totals since the start of monitoring">All time</button>
          </div>
        </div>
      </div>
      <!-- Totals for each mode, shown in place of the single mode row by By mode -->
      <div id="totals-breakdown" style="display:none">
        <table class="hp-totals-table">
          <thead>
            <tr>
              <th></th>
              <th class="app-live-label"><span class="hp-label-long">Electricity input</span><span class="hp-label-short">Electric</span></th>
              <th class="app-live-label"><span class="hp-label-long">Heat output</span><span class="hp-label-short">Heat</span></th>
              <th class="app-live-label">SCOP</th>
              <th class="app-live-label" title="Share of space and water heat output">Share</th>
            </tr>
          </thead>
          <tbody id="totals-breakdown-rows"></tbody>
        </table>
      </div>
      <div class="app-live" id="totals-single">
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

    <!-- Measured design parameters, pipework and data checks, filled by myheatpump_measurements.js -->
    <div id="measurements-block" class="app-card app-card-body" style="display:none"></div>

    <!-- System form details, filled by myheatpump_system.js -->
    <div id="system-block" class="app-card app-card-body" style="display:none"></div>

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
  // Measured design parameters by name: value, lower, upper, unit, start_time and
  // end_time (unix s, the period it comes from) and details (selected days etc.)
  var system_measurements = <?php
      $measurements_json = json_encode(isset($measurements) ? (object) $measurements : new stdClass(),
          JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE);
      echo $measurements_json === false ? "{}" : $measurements_json;
  ?>;
  // System form fields for the System card. A fixed list, so the read key,
  // app id and URL of the system never reach the page.
  var system_meta = <?php
      $system_meta_fields = array(
          "location", "installer_name", "installer_url", "installer_logo", "installation_Cost",
          "heatgeek", "ultimaterenewables", "heatingacademy", "guild", "betateach", "youtube",
          "hp_type", "hp_hybrid", "hp_manufacturer", "hp_model", "hp_output", "hp_max_output", "hp_max_output_test",
          "refrigerant", "uses_backup_heater",
          "flow_temp", "design_temp", "zone_number", "new_radiators", "old_radiators", "fan_coil_radiators", "UFH",
          "hydraulic_separation", "volumiser",
          "dhw_method", "dhw_make_model", "cylinder_volume", "dhw_target_temperature", "legionella_frequency", "legionella_immersion",
          "freeze", "heat_loss", "property", "floor_area", "age", "insulation",
          "mid_metering", "electric_meter", "heat_meter", "metering_inc_boost", "metering_inc_immersion",
          "metering_inc_central_heating_pumps", "metering_inc_secondary_heating_pumps", "metering_inc_brine_pumps",
          "metering_inc_controls", "metering_boundary_code", "notes", "data_flag", "data_flag_note"
      );
      $system_meta = array();
      foreach ($system_meta_fields as $f) {
          if (isset($system_data->$f)) $system_meta[$f] = $system_data->$f;
      }
      // Heat pump database model matched from make, model and output, for max output tests
      $system_meta["heatpump_model_id"] = $heatpump_model_id ? (int) $heatpump_model_id : null;
      // Its rated minimum and maximum flow rate, L/min, where the database has them
      $system_meta["hp_min_flowrate"] = isset($heatpump_flowrate["min"]) ? (float) $heatpump_flowrate["min"] : null;
      $system_meta["hp_max_flowrate"] = isset($heatpump_flowrate["max"]) ? (float) $heatpump_flowrate["max"] : null;
      // HEX_TAG: a "</script>" in the notes stays inside the string.
      // INVALID_UTF8_SUBSTITUTE: bad bytes in a text field cannot make
      // json_encode return false and leave a syntax error in this script.
      $system_meta_json = json_encode($system_meta, JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE);
      echo $system_meta_json === false ? "{}" : $system_meta_json;
  ?>;
</script>

<script src="<?php echo $path; ?>Modules/dashboard/myheatpump_process.js?v=<?php echo $v; ?>"></script>
<script src="<?php echo $path; ?>Modules/dashboard/myheatpump_powergraph.js?v=<?php echo $v; ?>"></script>
<script src="<?php echo $path; ?>Modules/dashboard/myheatpump_bargraph.js?v=<?php echo $v; ?>"></script>
<script src="<?php echo $path; ?>Modules/dashboard/myheatpump_heatdemand.js?v=<?php echo $v; ?>"></script>
<script src="<?php echo $path; ?>Modules/dashboard/myheatpump.js?v=<?php echo $v; ?>"></script>
<script src="<?php echo $path; ?>Modules/emitter/views/emitter_stats.js?v=<?php echo filemtime(__DIR__."/../emitter/views/emitter_stats.js"); ?>"></script>
<script src="<?php echo $path; ?>Modules/dashboard/myheatpump_measurements.js?v=<?php echo $v; ?>"></script>
<script src="<?php echo $path; ?>Modules/dashboard/myheatpump_system.js?v=<?php echo $v; ?>"></script>
