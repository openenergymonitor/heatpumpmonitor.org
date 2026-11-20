<?php 
defined('EMONCMS_EXEC') or die('Restricted access');
global $path; 
?>
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
                  
                  <tr>
                    <th>Measurements</th>
                    <th></th>
                    <th></th>
                  </tr>

                  <tr>
                    <td><a target="_BLANK" href="<?php echo $path; ?>measurement/list.json">/measurement/list.json</a></td>
                    <td><b>mode=</b>user or admin (optional)</td>
                    <td>Design parameters worked out nightly from each system's monitoring data, for all public systems, keyed by system id. Values only, with an <code>m_</code> prefix as in the system list columns, e.g. <code>m_heat_loss_design</code>. With <b>mode=user</b> when logged in, your own systems.</td>
                  </tr>

                  <tr>
                    <td><a target="_BLANK" href="<?php echo $path; ?>measurement/system.json?id=2">/measurement/system.json?id=2</a></td>
                    <td><b>id=</b>SYSTEM_ID (required)</td>
                    <td>All measurements for one system, keyed by name, each with <code>value</code>, <code>lower</code> and <code>upper</code> (80% prediction interval where there is one), <code>unit</code>, <code>samples</code>, <code>start_time</code> and <code>end_time</code> (unix time of the day or 4 hour window it comes from), <code>details</code> (method, inputs and selected days with their timestamps) and <code>updated</code>. Public systems, or your own when logged in.</td>
                  </tr>

                  <tr>
                    <th>Timeseries</th>
                    <th></th>
                    <th></th>
                  </tr>

                  <tr>
                    <td><a target="_BLANK" href="<?php echo $path; ?>timeseries/available?id=2">/timeseries/available?id=2</a></td>
                    <td><b>id=</b>SYSTEM_ID (required)</td>
                    <td>Returns a list of available timeseries feeds.</td>
                  </tr>

                  <tr>
                    <td><a target="_BLANK" href="<?php echo $path; ?>timeseries/data?id=2&feeds=heatpump_outsideT&start=01-06-2024&end=02-06-2024&interval=3600&average=1&timeformat=notime">/timeseries/data?id=2<br>&feeds=heatpump_outsideT<br>&start=01-06-2024<br>&end=02-06-2024<br>&interval=3600<br>&average=1<br>&timeformat=notime</a></td>
                    <td>
                      <b>id=</b>SYSTEM_ID (required)<br>
                      <b>feeds=</b>CSV list of feeds by key (e.g heatpump_elec,heatpump_outsideT)<br>
                      <b>start=</b><br>
                      <b>end=</b><br>
                      <b>interval=</b>3600<br>
                      <b>average=</b>1<br>
                      <b>delta=</b>0</br>
                      <b>timeformat=</b>notime
                    </td>
                    <td>Returns timeseries data for a given system.</td>
                  </tr>

                </table>

                <h4 class="mt-4">Measurements</h4>
                <p>Worked out nightly for each system from its own monitoring data: heat loss from daily heat against inside - outside temperature difference, design outside temperature from hourly outside temperature, and heat output and space heating flow temperature on the coldest days. Design values are at the design outside temperature on the system form (or the measured one if the form has none) and 20 °C inside.</p>
                <table class="table table-sm">
                  <tr><th>Name</th><th>Unit</th><th>Description</th></tr>
                  <tr><td><code>heat_loss_design</code></td><td>kW</td><td>Heat demand at the design temperature</td></tr>
                  <tr><td><code>heat_loss_design_measured</code></td><td>kW</td><td>Heat demand at the measured 99.6% design outside temperature</td></tr>
                  <tr><td><code>heat_loss_htc</code></td><td>W/K</td><td>Heat transfer coefficient, the slope of the heat loss fit</td></tr>
                  <tr><td><code>heat_loss_base_dT</code></td><td>K</td><td>Balance point, the temperature difference where the fit reaches zero heat</td></tr>
                  <tr><td><code>heat_coldest_days</code></td><td>kW</td><td>Highest day of heat output divided by 24, all modes, of the 3 coldest days of the last year (the same days as <code>flowT_coldest_days</code>). Lower and upper are the lowest and highest of those days, listed in details</td></tr>
                  <tr><td><code>heat_peak_4h</code></td><td>kW</td><td>Highest 4 hour mean heat output on those days</td></tr>
                  <tr><td><code>design_outsideT_996</code>, <code>design_outsideT_990</code></td><td>°C</td><td>Hourly outside temperature equalled or exceeded for 99.6% / 99.0% of hours, complete years only</td></tr>
                  <tr><td><code>flowT_coldest_days</code></td><td>°C</td><td>Highest daily space heating flow temperature, weighted by heat output, of the 3 coldest days of the last year (by outside temperature weighted the same way). Lower and upper are the lowest and highest of those days, listed in details</td></tr>
                  <tr><td><code>flowT_peak_4h</code></td><td>°C</td><td>Highest 4 hour space heating flow temperature on those days</td></tr>
                  <tr><td><code>dT_coldest_days</code></td><td>K</td><td>Space heating mean flow - return temperature on those days, from the daily stats, weighted by space heating heat. Lower and upper are the lowest and highest day, listed in details</td></tr>
                  <tr><td><code>flowrate_coldest_days</code></td><td>l/min</td><td>Space heating flow rate on those days, implied as mean heat / (cp × mean flow - return) rather than read from the flow meter</td></tr>
                  <tr><td><code>coldest_day</code></td><td>°C</td><td>Coldest day of the last year, outside temperature weighted by heat output (in <code>/measurement/system.json</code> only)</td></tr>
                </table>


            <br><br>
            <button class="btn btn-secondary" style="float:right" onclick="copyCode(this)">Copy</button>
            <h4>Python example</h4>
            <p>This python example replicates the last 90 days stats view on HeatpumpMonitor.org</p>


<link rel="stylesheet" href="<?php echo $path; ?>theme/vendor/highlight.js-11.9.0/styles/monokai-sublime.min.css" integrity="sha384-fPmRQ3GXo2iBk8Sgxizmu0IOOv+vAPjYXqCHNut2KSkSBleqjCd7FpBJwrfyCfGc">
<script src="<?php echo $path; ?>theme/vendor/highlight.js-11.9.0/highlight.min.js" integrity="sha384-F/bZzf7p3Joyp5psL90p/p89AZJsndkSoGwRpXcZhleCWhd8SnRuoYo4d0yirjJp"></script>

<!-- and it's easy to individually load additional languages -->
<script src="<?php echo $path; ?>theme/vendor/highlight.js-11.9.0/languages/go.min.js" integrity="sha384-WmGkHEmwSI19EhTfO1nrSk3RziUQKRWg3vO0Ur3VYZjWvJRdRnX4/scQg+S2w1fI"></script>

<script>hljs.highlightAll();</script>

<pre><code class="python" id="code"><?php echo file_get_contents("views/example1.py"); ?></code></pre>

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
