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

            </div>
        </div>
    </div>
</div>
