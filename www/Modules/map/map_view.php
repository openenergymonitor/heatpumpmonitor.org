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

    .speech-bubble {
        position: absolute;
        background-color: white;
        border: 1px solid black;
        border-radius: 5px;
        padding: 8px;
        display: none; /* Initially hidden */
        pointer-events: none; /* Allows map interaction */
        width: 250px; /* Fixed width */
    }

</style>

<div id="map"></div>
<div id="speech-bubble" class="speech-bubble"></div>

<!-- load weather_map.js -->
<script src="<?php echo $path; ?>Modules/map/map_view.js?v=<?php echo time(); ?>"></script>