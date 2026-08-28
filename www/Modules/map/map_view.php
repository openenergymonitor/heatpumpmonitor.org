<?php
defined('EMONCMS_EXEC') or die('Restricted access');
?>

<script src="<?php echo $path; ?>theme/vendor/ol-7.4.0/ol.js" integrity="sha384-UnFf8KXzbcP68LSCc4PSf4WbixBySdywUoVXLwlMw14SsRiQYHMAImua4dnHgwzo"></script>
<link rel="stylesheet" href="<?php echo $path; ?>theme/vendor/ol-7.4.0/ol.css" integrity="sha384-9bhh0d43Gp9ct3OBqOdccAg6nQ48H3ibQN30wXbD0oC/uFYftJt4aOK45aGS1TJL">
<script src="<?php echo $path; ?>theme/vendor/jquery-3.6.3/jquery.min.js" integrity="sha384-Ft/vb48LwsAEtgltj7o+6vtS2esTU9PCpDqcXs4OCVQFZu5BqprHtUCZ4kjK+bpE"></script>
<script src="<?php echo $path; ?>Modules/map/filter_systems.js?v=123"></script>
<link rel="stylesheet" href="<?php echo $path; ?>Modules/map/map_view.css?v=124>">

<div id="map"></div>

<div id="map-search-overlay" class="map-search-overlay">
    <input type="text" id="map-search-input" placeholder="Search for a location..." />
    <button id="map-search-btn" class="map-search-btn" type="button" title="Search">
        <!-- SVG magnifying glass icon -->
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <circle cx="9" cy="9" r="7" stroke="#555" stroke-width="2"/>
            <line x1="14.2" y1="14.2" x2="18" y2="18" stroke="#555" stroke-width="2" stroke-linecap="round"/>
        </svg>
    </button>
</div>

<div id="map-info-overlay" class="map-info-overlay">
    <div class="map-info-close" id="map-info-close" title="Close">×</div>
    <div id="map-info-content"></div>
</div>


<!-- load weather_map.js -->
<script src="<?php echo $path; ?>Modules/map/map_view.js?v=130"></script>