<?php
defined('EMONCMS_EXEC') or die('Restricted access');
global $path, $session, $v;
$v=1;
?>
<link href="<?php echo $path; ?>Modules/dashboard/config.css?v=<?php echo $v; ?>" rel="stylesheet">
<link href="<?php echo $path; ?>Modules/dashboard/light.css?v=<?php echo $v; ?>" rel="stylesheet">

<link rel="stylesheet" href="//fonts.googleapis.com/css?family=Montserrat&amp;lang=en" />

<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.3/jquery.flot.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.3/jquery.flot.time.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.3/jquery.flot.selection.min.js"></script>

<script type="text/javascript" src="<?php echo $path; ?>Modules/dashboard/date.format.js?v=<?php echo $v; ?>"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/dashboard/vis.helper.js?v=<?php echo $v; ?>"></script>
<link href="<?php echo $path; ?>Modules/dashboard/style.css?v=39>" rel="stylesheet">

<div id="app" class="bg-light">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container" style="max-width:1060px;">
            <div style="float:right" v-if="admin"><a :href="path+'system/log?id='+system.id" class="btn btn-light">Change log</a></div>

            <div v-if="system.hp_model!=''">
                <h3>{{ system.hp_output }} kW, {{ system.hp_model }}</h3>
                <p>{{ system.location }}, <span v-if="system.installer_name"><a :href="system.installer_url">{{ system.installer_name }}</a></span></p>
            </div>
        </div>
    </div>
</div>

<script>
      var app = new Vue({
        el: '#app',
        data: {
            admin: false,
            path: path,
            mode: "view", // edit, view
            system: <?php echo json_encode($system_data); ?>,
        }
      });
</script>

<div class="container" style="max-width:1100px;">

<div style="font-family: Montserrat, Veranda, sans-serif;">
  <div id="app-block">

    <div class="col1">
      <div class="col1-inner">

        <div class="block-bound">

          <div class="bargraph-navigation">
            <div class="bluenav bargraph_mode" mode="combined" style="float:left">ALL</div>
            <div class="bluenav bargraph_mode" mode="running" style="float:left">RUN</div>
            <div class="bluenav bargraph_mode" mode="space" style="float:left">SPACE</div>
            <div class="bluenav bargraph_mode" mode="water" style="float:left">DHW</div>


            <div class="bluenav bargraph-alltime">ALL</div>
            <div class="bluenav bargraph-period" days=365>YEAR</div>
            <div class="bluenav bargraph-period" days=90>3 MONTHS</div>
            <div class="bluenav bargraph-period" days=30>MONTH</div>
            <div class="bluenav bargraph-period" days=7>WEEK</div>
            <div class="bluenav bargraph-day">DAY</div>
          </div>

          <div class="powergraph-navigation" style="display:none">
            <div class="bluenav viewhistory" title="Back to daily summary">BACK</div>
            <span class="bluenav" id="live" title="Live scroll" style="display:none; color: yellow; cursor: default">&gt;&gt;</span>
            <span class="bluenav" id="right" title="Scroll right">&gt;</span>
            <span class="bluenav" id="left" title="Scroll left">&lt;</span>
            <span class="bluenav" id="zoomout" title="Zoom out">-</span>
            <span class="bluenav" id="zoomin" title="Zoom in">+</span>
            <span class="bluenav time dmy" time='720' title="Last 30 days">M</span>
            <span class="bluenav time dmy" time='168' title="Last 7 days">W</span>
            <span class="bluenav time" time='24' title="Last 24 hours">D</span>
            <span class="bluenav time" time='6' title="Last 6 hours">6</span>
            <span class="bluenav time" time='1' title="Last hour">H</span>
          </div>
        </div>

        <div style="background-color:#fff; padding:10px;">
            <div id="placeholder_bound" style="width:100%; height:500px;overflow:hidden; position:relative;">
                <div id="placeholder" style="height:500px"></div>
                <div id="overlay" style="display:none; position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.8); color:#333; display:flex; align-items:center; justify-content:center; font-size:20px;">
                    <div id="overlay_text"></div>
                </div>
            </div>
        </div>

        <div style="background-color:#eee; color:#333">
          <div id='advanced-toggle' class='bluenav' style="display:none">SHOW DETAIL</div>

          <div id='data-error' style="display:none">DATA ERROR</div>

          <div style="padding:10px">
            COP in window: <b id="window-cop" style="cursor:pointer"></b> <span id="window-carnot-cop"></span>
          </div>
        </div>

        <div id="advanced-block" style="background-color:#fff; padding:10px; display:none">
          <div style="color:#000">



            <table style="width:100%; color:#333;">
              <tr>
                <td valign="top" class="show_stats_category" key="combined" style="border-bottom:1px solid #000">
                  <div class="cop-title">Full window</div>
                  <div class="cop-value"><span class="cop_combined">---</span></div>
                </td>

                <td valign="top" class="show_stats_category" key="when_running" style="color:#698d5d">
                  <div class="cop-title">When running</div>
                  <div class="cop-value"><span class="cop_when_running">---</span></div>
                </td>

                <td valign="top" class="show_stats_category" key="space_heating" style="color:#f6a801">
                  <div class="cop-title">Space heating</div>
                  <div class="cop-value"><span class="cop_space_heating">---</span></div>
                </td>

                <td valign="top" class="show_stats_category" key="water_heating" style="color:#014656">
                  <div class="cop-title">Water heating</div>
                  <div class="cop-value"><span class="cop_water_heating">---</span></div>
                </td>
              </tr>
            </table>


            <table class="table">
              <tr>
                <th></th>
                <th style="text-align:center; width:150px; color:#777">Min</th>
                <th style="text-align:center; width:150px; color:#777">Max</th>
                <th style="text-align:center; width:150px; color:#777">Diff</th>
                <th style="text-align:center; width:150px">Mean</th>
                <th style="text-align:center; width:150px">kWh</th>
              </tr>
              <tbody class="stats_category" key="combined"></tbody>
              <tbody class="stats_category" key="when_running" style="display:none"></tbody>
              <tbody class="stats_category" key="water_heating" style="display:none"></tbody>
              <tbody class="stats_category" key="space_heating" style="display:none"></tbody>
            </table>

            <div id="show_flow_rate_bound" style="display:none" class="advanced-options">
              <input id="show_flow_rate" type="checkbox" class="advanced-options-checkbox">
              <b>Show flow rate</b>
            </div>

            <div id="show_cooling_bound" class="advanced-options">
              <div style="float:right"><span id="total_negative_heat_kwh"></span> kWh (<span id="prc_negative_heat"></span>%)</div>
              <input id="show_negative_heat" type="checkbox" class="advanced-options-checkbox">
              <b>Show cooling / defrosts</b>
            </div>

            <div id="show_inst_cop_bound" class="advanced-options">

              <input id="show_instant_cop" type="checkbox" class="advanced-options-checkbox">
              <b>Show instantaneous COP</b>

              <div id="inst_cop_options" style="display:none">
                <div class="input-prepend input-append" style="margin-top:10px; margin-bottom:0px">
                  <span class="add-on">Valid COP</span>
                  <span class="add-on">Min</span>
                  <input type="text" style="width:50px" id="inst_cop_min" value="1.0">
                  <span class="add-on">Max</span>
                  <input type="text" style="width:50px" id="inst_cop_max" value="8.0">
                </div>

                <div class="input-prepend input-append" style="margin-top:10px; margin-bottom:0px">
                  <span class="add-on">Moving average</span>
                  <select id="inst_cop_mv_av_dp" style="width:100px">
                    <option value="0" selected>Disabled</option>
                    <option value="3">3 points</option>
                    <option value="5">5 points</option>
                  </select>
                </div>
              </div>

            </div>

            <div id="show_inst_cop_bound" class="advanced-options">
              <input id="carnot_enable" type="checkbox" class="advanced-options-checkbox">
              <b>Show simulated carnot heat output</b>

              <div id="carnot_sim_options" style="display:none">
                <div class="input-prepend" style="margin-top:5px; margin-bottom:0px">
                  <span class="add-on">Condensing offset (K)</span>
                  <input type="text" style="width:50px" id="condensing_offset" value="2">
                </div>
                <div class="input-prepend" style="margin-top:5px; margin-bottom:0px">
                  <span class="add-on">Evaporator offset (K)</span>
                  <input type="text" style="width:50px" id="evaporator_offset" value="-6">
                </div>
                <div id="heatpump_factor_bound" class="input-prepend" style="margin-top:5px; margin-bottom:0px">
                  <span class="add-on">Heatpump factor</span>
                  <input type="text" style="width:50px" id="heatpump_factor" value="0.47">
                </div>
                <div id="fixed_outside_temperature_bound" class="input-prepend input-append" style="margin-top:5px; margin-bottom:0px">
                  <span class="add-on">Fixed outside temperature (C)</span>
                  <input type="text" style="width:50px" id="fixed_outside_temperature" value="6.0">
                </div>
              </div>

            </div>

            <div id="show_inst_cop_bound" class="advanced-options">
              <input id="carnot_enable_prc" type="checkbox" class="advanced-options-checkbox">
              <b>Show as % of carnot COP</b>

              <div id="carnot_prc_options" style="display:none">
                <p>Measured COP vs Carnot COP distribution:</p>
                <div id="histogram_bound" style="width:100%; height:400px;overflow:hidden">
                  <div id="histogram" style="height:400px"></div>
                </div>
              </div>

            </div>

            <div id="show_inst_cop_bound" class="advanced-options">
              <input id="emitter_spec_enable" type="checkbox" class="advanced-options-checkbox">
              <b>Calculate emitter spec and system volume</b>
              <div id="emitter_spec_options" style="margin-top:10px; display:none">
                <p>1. Select period of steady state operation where flow and return temperatures are flat</p>

                <div class="input-append" style="margin-top:5px">
                  <input type="text" style="width:50px" id="kW_at_50" disabled>
                  <span class="add-on">kW @ DT50</span>
                  <button class="btn" id="use_for_volume_calc">Use for volume calc</button>
                </div>

                <p>2. Select space heating period with increasing flow and return temperatures</p>

                <div class="input-append" style="margin-top:5px">
                  <input type="text" style="width:50px" id="system_volume" disabled>
                  <span class="add-on">Litres</span>
                </div>
              </div>
            </div>

            <div class="advanced-options" style="border-bottom:1px solid #ccc">
              <div style="float:right"><span id="standby_kwh"></span> kWh</span></div>
              <input id="configure_standby" type="checkbox" class="advanced-options-checkbox">
              <b>Configure standby</b>
              <div id="configure_standby_options" style="display:none">
                <div class="input-prepend input-append" style="margin-top:10px; margin-bottom:0px;">
                  <span class="add-on">Starting power</span>
                  <input type="text" style="width:50px" id="starting_power" value="150">
                  <span class="add-on">W</span>
                </div>
              </div>

            </div>

          </div>
        </div>

      </div>
    </div>
    <div class="col1">
      <div class="col1-inner">

        <div class="block-bound">
          <div class="block-title" id="all_time_history_title">ALL TIME HISTORY</div>
        </div>

        <div style="background-color:#fff; padding:10px;">
          <table style="width:100%; color:#333;">
            <tr>
              <td style="width:33.3%; text-align:center" valign="top">
                <div class="title1">Total Electricity input</div>
                <div class="value1"><span id="total_elec"></span>
                  <div class="units1">kWh</div>
                </div>
              </td>

              <td style="width:33.3%; text-align:center" valign="top">
                <div class="title1">Total Heat output</div>
                <div class="value1"><span id="total_heat"></span>
                  <div class="units1">kWh</div>
                </div>
              </td>

              <td style="width:33.3%; text-align:center" valign="top">
                <div class="title1">SCOP</div>
                <div class="value1"><span id="total_cop"></span></div>
              </td>
            </tr>

            <!-- 
            <tr>
              <td style="width:33.3%; text-align:center" valign="top">
                <div class="title1">When running electric</div>
                <div class="value1"><span id="running_elec"></span>
                  <div class="units1">kWh</div>
                </div>
              </td>

              <td style="width:33.3%; text-align:center" valign="top">
                <div class="title1">When running heat</div>
                <div class="value1"><span id="running_heat"></span>
                  <div class="units1">kWh</div>
                </div>
              </td>

              <td style="width:33.3%; text-align:center" valign="top">
                <div class="title1">When running SCOP</div>
                <div class="value1"><span id="running_cop"></span></div>
              </td>
            </tr>

            <tr>
              <td style="width:33.3%; text-align:center" valign="top">
                <div class="title1">Space heating electric</div>
                <div class="value1"><span id="space_elec"></span>
                  <div class="units1">kWh</div>
                </div>
              </td>

              <td style="width:33.3%; text-align:center" valign="top">
                <div class="title1">Space heating heat</div>
                <div class="value1"><span id="space_heat"></span>
                  <div class="units1">kWh</div>
                </div>
              </td>

              <td style="width:33.3%; text-align:center" valign="top">
                <div class="title1">Space heating SCOP</div>
                <div class="value1"><span id="space_cop"></span></div>
              </td>
            </tr>
            <tr>
              <td style="width:33.3%; text-align:center" valign="top">
                <div class="title1">Water heating electric</div>
                <div class="value1"><span id="water_elec"></span>
                  <div class="units1">kWh</div>
                </div>
              </td>

              <td style="width:33.3%; text-align:center" valign="top">
                <div class="title1">Water heating heat</div>
                <div class="value1"><span id="water_heat"></span>
                  <div class="units1">kWh</div>
                </div>
              </td>

              <td style="width:33.3%; text-align:center" valign="top">
                <div class="title1">Water heating SCOP</div>
                <div class="value1"><span id="water_cop"></span></div>
              </td>
            </tr>
            -->
          </table>
        </div>

      </div>
    </div>

  </div>
</div>

<div class="ajax-loader"></div>

<script>
  var apikey = "";
  var session_write = 0;

  var config = {};
  config.id = <?php echo $id; ?>;
  config.db = {};
</script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/dashboard/myheatpump.js?v=<?php echo time(); ?>"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/dashboard/myheatpump_process.js?v=1"></script>
