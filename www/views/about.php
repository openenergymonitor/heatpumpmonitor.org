<?php

// Get current date Month and Year: June 2024
$date = date('F Y');

?>

<div id="app">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container">
            <h3>About</h3>
        </div>
    </div>
    <div class="container" style="margin-top:20px">
        <div class="row">
            <div class="col">

<p>HeatpumpMonitor.org is an <a href="https://openenergymonitor.org">OpenEnergyMonitor</a> open source community initiative to share and compare heat pump performance data.</p>
<p>As of <?php echo $date; ?> there are <?php echo $number_of_systems; ?> heat pump systems uploading data to the site including systems installed by some of the best heat pump engineers in the UK.</p>

<p>HeatpumpMonitor.org is a peer-to-peer learning resource used by both customers and installers wishing to understand how to design, install and operate high performance and low running cost heat pump installations.</p>



            </div>
        </div>
    </div>
</div>
