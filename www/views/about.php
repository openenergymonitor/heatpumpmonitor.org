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

<p>HeatpumpMonitor.org is an <a href="https://openenergymonitor.org">OpenEnergyMonitor</a> open source community initiative to share and compare real-world heat pump performance data.</p>
<p>As of <?php echo $date; ?> there are <?php echo $number_of_systems; ?> heat pump systems uploading data to the site, including systems installed by some of the best heat pump engineers in the UK.</p>

<p>The site is a useful peer-to-peer learning resource used by both customers and installers wishing to understand how to design, install and optimise heat pumps for high performance and low running costs.</p>

<p>Systems marked with 'MID' use billing grade MID approved Class 1 electricity metering and Class 2 heat metering equipment.</p>

<p>The majority of systems are monitored using the <a href="https://shop.openenergymonitor.com/level-3-heat-pump-monitoring-bundle-emonhp/"> OpenEnergyMonitor Level 3 Monitoring Bundle</a>.</p>

<p>Contact us:</p>
<ul>
<li><a href="https://community.openenergymonitor.org">Community Forum</a>
<li><a href="mailto: hello@openenergymonitor.zendesk.com"> hello@openenergymonitor.zendesk.com</a></li>
<li><a href="tell:+441248800870">+44(0)1248800870</a></li>
</ul>


            </div>
        </div>
    </div>
</div>
