<?php
defined('EMONCMS_EXEC') or die('Restricted access');
?>

<script src="https://cdn.jsdelivr.net/npm/ol@v7.4.0/dist/ol.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v7.4.0/ol.css">
<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
<script src="<?php echo $path; ?>Modules/map/filter_systems.js?v=<?php echo time(); ?>"></script>

<style>
    #map {
        margin: 0;
        padding: 0;
        width: 100%;
        height: 750px;
    }

    .map-tooltip {
        position: absolute;
        background-color: white;
        border: 1px solid black;
        border-radius: 5px;
        padding: 8px;
        display: none; /* Initially hidden */
        pointer-events: none; /* Allows map interaction */
        width: 250px; /* Fixed width */
    }

    .map-tooltip-close {
        position: absolute;
        top: 4px;
        right: 10px;
        cursor: pointer;
        font-size: 18px;
        font-weight: bold;
        color: #888;
        pointer-events: auto; /* Allow clicking */
        z-index: 10;
    }

    .map-tooltip-close:hover {
        color: #000;
    }
</style>

<div id="map"></div>
<div id="map-tooltip" class="map-tooltip">
    <!--<span id="map-tooltip-close" class="map-tooltip-close">&times;</span>-->
    <div id="map-tooltip-content">
        <!-- Content will be dynamically inserted here -->
    </div>
</div>

<!-- load weather_map.js -->
<script src="<?php echo $path; ?>Modules/map/map_view.js?v=<?php echo time(); ?>"></script>