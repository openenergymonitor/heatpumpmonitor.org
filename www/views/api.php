<?php global $path; ?>
<div id="app">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container">
            <h3>API</h3>
        </div>
    </div>
    <div class="container" style="margin-top:20px">
        <div class="row">
            <div class="col">

                <p>List of HeatpumpMonitor.org API end points</p>
                
                <table class="table">
                
                  <tr>
                    <th>URL</th>
                    <th>Params</th>
                    <th>Description</th>
                  </tr>

                  <tr>
                    <th>System list & meta data</th>
                    <th></th>
                    <th></th>
                  </tr> 
                
                  <tr>
                    <td><a target="_BLANK" href="<?php echo $path; ?>system/list/public.json">/system/list/public.json</a></td>
                    <td></td>
                    <td>List of public systems with all system form meta data</td>
                  </tr>
                  
                  <tr>
                    <td><a target="_BLANK" href="<?php echo $path; ?>system/list/user.json">/system/list/user.json</a></td>
                    <td></td>
                    <td>When logged in this returns the system list for the logged in user</td>
                  </tr>

                  <tr>
                    <th>Single system meta data</th>
                    <th></th>
                    <th></th>
                  </tr>

                  <tr>
                    <td><a target="_BLANK" href="<?php echo $path; ?>system/get.json?id=1">system/get.json?id=1</a></td>
                    <td><b>id=</b>SYSTEM_ID (required)</td>
                    <td>Returns meta data for selected system</td>
                  </tr>
                  
                  <tr>
                    <th>Stats</th>
                    <th></th>
                    <th></th>
                  </tr>               

                  <tr>
                    <td><a target="_BLANK" href="<?php echo $path; ?>system/stats/last7">/system/stats/last7</a></td>
                    <td><b>id=</b>SYSTEM_ID (optional)</td>
                    <td>Stats summary for all systems or specified system for the last 7 days</td>
                  </tr> 

                  <tr>
                    <td><a target="_BLANK" href="<?php echo $path; ?>system/stats/last30">/system/stats/last30</a></td>
                    <td><b>id=</b>SYSTEM_ID (optional)</td>
                    <td>Stats summary for all systems or specified system for the last 30 days</td>
                  </tr> 
                
                  <tr>
                    <td><a target="_BLANK" href="<?php echo $path; ?>system/stats/last90">/system/stats/last90</a></td>
                    <td><b>id=</b>SYSTEM_ID (optional)</td>
                    <td>Stats summary for all systems or specified system for the last 90 days</td>
                  </tr> 

                  <tr>
                    <td><a target="_BLANK" href="<?php echo $path; ?>system/stats/last365">/system/stats/last365</a></td>
                    <td><b>id=</b>SYSTEM_ID (optional)</td>
                    <td>Stats summary for all systems or specified system for the last 365 days</td>
                  </tr> 

                  <tr>
                    <td><a target="_BLANK" href="<?php echo $path; ?>system/stats/all">/system/stats/all</a></td>
                    <td><b>id=</b>SYSTEM_ID (optional)</td>
                    <td>Stats summary for all systems or specified system for all time</td>
                  </tr> 

                  <tr>
                    <?php 
                      $start = "2024-01-01";
                      $end = "2024-02-01";
                    ?>
                    <td><a target="_BLANK" href="<?php echo $path; ?>system/stats?start=<?php echo $start; ?>&end=<?php echo $end; ?>">/system/stats?start=<?php echo $start; ?>&end=<?php echo $end; ?></a></td>
                    <td><b>id=</b>SYSTEM_ID (optional)<br><b>start</b>=<?php echo $start; ?>&<b>end</b>=<?php echo $end; ?></td>
                    <td>Stats summary for all systems or specified system for specified time window</td>
                  </tr> 
                  
                  <tr>
                    <td><a target="_BLANK" href="<?php echo $path; ?>system/stats/daily?id=1">/system/stats/daily?id=1</a></td>
                    <td><b>id=</b>SYSTEM_ID (required)</td>
                    <td>Currently returns all daily data for the specified user. Will have option to select specific date range in future. Data is returned as CSV.</td>
                  </tr>                  

                  <tr>
                    <td><a target="_BLANK" href="<?php echo $path; ?>system/stats/monthly?id=1">/system/stats/monthly?id=1</a></td>
                    <td><b>id=</b>SYSTEM_ID (required)</td>
                    <td>Returns a monthly data summary for the specified user. Data is returned as JSON.</td>
                  </tr> 

                </table>

            <br><br>
            <button class="btn btn-secondary" style="float:right" onclick="copyCode(this)">Copy</button>
            <h4>Python example</h4>
            <p>This python example replicates the last 90 days stats view on HeatpumpMonitor.org</p>


<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/monokai-sublime.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

<!-- and it's easy to individually load additional languages -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/go.min.js"></script>

<script>hljs.highlightAll();</script>



 <pre><code class="python" id="code">
# HeatPumpMonitor.org API example
# Part of the OpenEnergyMonitor project: 
# https://openenergymonitor.org

import requests
from tabulate import tabulate

# Useful meta fields: 
# location, installer_name, installer_url, installer_logo, heatgeek, ultimaterenewables, heatingacademy, betateach, youtube, url, share, 
# hp_model, hp_type, hp_output, refrigerant, 
# dhw_method, cylinder_volume, dhw_coil_hex_area, 
# new_radiators, old_radiators, fan_coil_radiators, UFH, hydraulic_separation, 
# flow_temp, design_temp, flow_temp_typical, wc_curve, freeze, zone_number, space_heat_control_type, 
# dhw_control_type, dhw_target_temperature, legionella_frequency, legionella_target_temperature, 
# property, floor_area, heat_demand, water_heat_demand, EPC_spaceheat_demand, EPC_waterheat_demand, heat_loss, age, insulation, kwh_m2, 
# electricity_tariff, electricity_tariff_type, electricity_tariff_unit_rate_all, solar_pv_generation, solar_pv_self_consumption, solar_pv_divert, battery_storage_capacity, 
# mid_metering, electric_meter, heat_meter, metering_inc_boost, metering_inc_central_heating_pumps, metering_inc_brine_pumps, metering_inc_controls, indoor_temperature, 
# notes

url = "https://heatpumpmonitor.org/system/list/public.json"
response = requests.get(url)
meta = response.json()

# Useful stats fields: 
# timestamp
# combined_elec_kwh, combined_heat_kwh, combined_cop, combined_data_length, combined_elec_mean, combined_heat_mean, combined_flowT_mean, combined_returnT_mean, combined_outsideT_mean, combined_roomT_mean, combined_prc_carnot, combined_cooling_kwh
# running_elec_kwh, running_heat_kwh, running_cop, running_data_length, running_elec_mean, running_heat_mean, running_flowT_mean, running_returnT_mean, running_outsideT_mean, running_roomT_mean, running_prc_carnot
# space_elec_kwh, space_heat_kwh, space_cop, space_data_length, space_elec_mean, space_heat_mean, space_flowT_mean, space_returnT_mean, space_outsideT_mean, space_roomT_mean, space_prc_carnot
# water_elec_kwh, water_heat_kwh, water_cop, water_data_length, water_elec_mean, water_heat_mean, water_flowT_mean, water_returnT_mean, water_outsideT_mean, water_roomT_mean, water_prc_carnot
# from_energy_feeds_elec_kwh, from_energy_feeds_heat_kwh, from_energy_feeds_cop
# quality_elec, quality_heat, quality_flowT, quality_returnT, quality_outsideT, quality_roomT

url = "https://heatpumpmonitor.org/system/stats/last90"
response = requests.get(url)
stats = response.json()

# Compile list of systems with stats
systems = []
for system in meta:
    if str(system['id']) in stats:
        system['stats'] = stats[str(system['id'])]
        systems.append(system)

# Sort by combined_cop
systems.sort(key=lambda x: x['stats']['combined_cop'] if x['stats']['combined_cop'] is not None else float('-inf'), reverse=True)

# Filter and prepare data for tabulation
table = []
headers = ["ID", "Location", "Output", "Model", "COP", "FlowT", "OutsideT", "Days"]
for system in systems:

    # Only include systems with a valid COP figure
    if system['stats']['combined_cop']!=None:
    
        # Combined COP
        cop = "%.2f" % system['stats']['combined_cop'] if system['stats'] else "N/A"
        
        # flow temperature and outside temperature when the heat pump is running
        flowT_when_running = "%.1f" % system['stats']['running_flowT_mean'] if system['stats'] else "N/A"
        outsideT_when_running = "%.1f" % system['stats']['running_outsideT_mean'] if system['stats'] else "N/A"
        
        # combined_data_length in seconds converted to days
        days = "%.0f" % (system['stats']['combined_data_length'] / 86400) if system['stats'] else "N/A"

        # Append to table
        table.append([system['id'], system['location'], f"{system['hp_output']} kW", system['hp_model'], cop, flowT_when_running, outsideT_when_running, days])

# Print title
print ("HeatPumpMonitor.org systems (sorted by COP, descending, last 90 days)")

# Use tabulate to print the table
print(tabulate(table, headers=headers, tablefmt="psql"))
</code></pre>  

<script>
function copyCode(button) {
    var codeBlock = document.getElementById("code").innerText;
    var textArea = document.createElement("textarea");
    textArea.value = codeBlock;
    document.body.appendChild(textArea);
    textArea.select();
    document.execCommand("copy");
    document.body.removeChild(textArea);
    button.textContent = "Copied!";
    setTimeout(function() { button.textContent = "Copy"; }, 2000);
}
</script>
            </div>
        </div>
    </div>
</div>
