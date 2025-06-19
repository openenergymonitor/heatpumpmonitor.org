(function() {
    // ------------------------------
    // Constants & Config
    // ------------------------------
    const COLOR_VAR = 'combined_cop';
    const MIN_COP = 1;
    const MAX_COP = 7;
    const SYSTEM_LIST_URL = path + "system/list/public.json";
    const SYSTEM_STATS_URL = path + "system/stats/last365";
    const MARKER_ICON_URL = 'https://openlayers.org/en/latest/examples/data/dot.png'; // Configurable marker icon

    // ------------------------------
    // State
    // ------------------------------
    let systems = [];

    // ------------------------------
    // Map Setup
    // ------------------------------
    const map = new ol.Map({
        target: 'map',
        view: new ol.View({
            center: ol.proj.fromLonLat([-4.0, 54]),
            zoom: 6
        })
    });

    const baseLayer = new ol.layer.Tile({
        source: new ol.source.OSM()
    });
    map.addLayer(baseLayer);

    const darkLayer = new ol.layer.Tile({
        source: new ol.source.XYZ({
            url: 'https://{a-c}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png'
        })
    });
    map.addLayer(darkLayer);

    const speechBubble = document.getElementById('speech-bubble');
    const overlay = new ol.Overlay({
        element: speechBubble,
        positioning: 'bottom-center',
        offset: [0, -10]
    });
    map.addOverlay(overlay);

    // ------------------------------
    // Data Loading
    // ------------------------------
    async function loadSystemsAndStats() {
        try {
            const [systemsRes, statsRes] = await Promise.all([
                fetch(SYSTEM_LIST_URL).then(r => r.json()),
                fetch(SYSTEM_STATS_URL).then(r => r.json())
            ]);
            systems = systemsRes;
            mergeStatsIntoSystems(statsRes);
            drawLocations();
        } catch (err) {
            console.error("Failed to load systems or stats", err);
        }
    }

    function mergeStatsIntoSystems(statsResult) {
        systems.forEach(system => {
            if (statsResult[system.id]) {
                system.stats = statsResult[system.id];
            }
        });
    }

    // ------------------------------
    // Utility Functions
    // ------------------------------
    // (2) Extract Marker Layer Removal
    function removeMarkerLayers() {
        map.getLayers().getArray().slice().forEach(layer => {
            if (!layer) return;
            const key = layer.get && layer.get('key');
            if (key === 'location_marker' || key === 'location_value' || key === 'new_location_marker') {
                map.removeLayer(layer);
            }
        });
    }

    // (3) Debounce Pointer Events
    function debounce(fn, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    // (5) Use Template Literals for HTML
    function getSpeechBubbleHTML(system) {
        return `
            <b>System ID:</b> ${system.id}<br>
            <b>Location:</b><br>${system.location}<br>
            <b>Heatpump:</b><br>${system.hp_output}kW, ${system.hp_model}<br>
            <b>SCOP:</b> ${system.stats.combined_cop.toFixed(1)}<br>
            ${system.installer_name ? `<b>Installer:</b> <a href='${system.installer_url}' target='_blank'>${system.installer_name}</a><br>` : ''}
        `;
    }

    // ------------------------------
    // Drawing Functions
    // ------------------------------
    function drawLocations() {
        // Find min/max for color scaling
        let minValue = 100, maxValue = -100;
        systems.forEach(system => {
            if (!system.stats) return;
            const val = system.stats[COLOR_VAR];
            if (val == null) return;
            if (val < minValue) minValue = val;
            if (val > maxValue) maxValue = val;
        });

        // Clamp to fixed range
        minValue = MIN_COP;
        maxValue = MAX_COP;

        // Remove old marker layers (2)
        removeMarkerLayers();

        // Create marker vector source
        const markerVectorSource = new ol.source.Vector();

        systems.forEach((system, index) => {
            if (!system.stats) return;
            const cop = system.stats.combined_cop;
            if (cop == null || cop < MIN_COP || cop > MAX_COP) return;

            const marker = new ol.Feature({
                geometry: new ol.geom.Point(ol.proj.fromLonLat([system.longitude, system.latitude]))
            });

            marker.setStyle(new ol.style.Style({
                image: new ol.style.Icon({
                    src: MARKER_ICON_URL, // (5) Configurable marker icon
                    scale: 0.5,
                    color: getTemperatureColor(system.stats[COLOR_VAR], minValue, maxValue)
                })
            }));
            marker.set('index', index);
            markerVectorSource.addFeature(marker);
        });

        const markerVectorLayer = new ol.layer.Vector({
            source: markerVectorSource
        });
        markerVectorLayer.set('key', 'location_marker');
        map.addLayer(markerVectorLayer);
    }

    // ------------------------------
    // Map Events
    // ------------------------------
    // (3) Debounced pointermove
    map.on('pointermove', debounce(function(e) {
        const pixel = map.getEventPixel(e.originalEvent);
        const hit = map.hasFeatureAtPixel(pixel);
        map.getTargetElement().style.cursor = hit ? 'pointer' : '';

        if (e.dragging) {
            speechBubble.style.display = 'none';
            return;
        }

        let found = false;
        map.forEachFeatureAtPixel(e.pixel, function(feature) {
            const coordinates = feature.getGeometry().getCoordinates();
            overlay.setPosition(coordinates);

            const system_index = feature.get('index');
            if (systems[system_index] == undefined) return false;
            const system = systems[system_index];

            // (6) Use template literal for HTML
            speechBubble.innerHTML = getSpeechBubbleHTML(system);
            speechBubble.style.display = 'block';
            found = true;
            return true; // Stop iterating
        }, { hitTolerance: 5 });

        if (!found) {
            speechBubble.style.display = 'none';
        }
    }, 20)); // Debounce delay in ms

    map.on('singleclick', function(e) {
        map.forEachFeatureAtPixel(e.pixel, function(feature) {
            const system_index = feature.get('index');
            if (systems[system_index] == undefined) return false;
            const system = systems[system_index];
            const url = 'https://heatpumpmonitor.org/system/view?id=' + encodeURIComponent(system.id);
            window.open(url, '_blank');
            return true;
        }, { hitTolerance: 5 });
    });

    // ------------------------------
    // Utility Functions
    // ------------------------------
    function getTemperatureColor(currentTemp, minTemp, maxTemp) {
        // Clamp
        if (currentTemp < minTemp) currentTemp = minTemp;
        if (currentTemp > maxTemp) currentTemp = maxTemp;
        const fraction = (currentTemp - minTemp) / (maxTemp - minTemp);

        let r, g, b;
        if (fraction <= 0.5) {
            r = 0;
            g = Math.round(255 * (2 * fraction));
            b = 255;
        } else {
            r = Math.round(255 * (2 * (fraction - 0.5)));
            g = 255 - Math.round(255 * (2 * (fraction - 0.5)));
            b = 0;
        }
        return `rgb(${r},${g},${b})`;
    }

    // ------------------------------
    // Resize Map
    // ------------------------------
    function resizeMap() {
        var topbarHeight = 64;
        var footerHeight = 128;
        var windowHeight = $(window).height();
        var availableHeight = windowHeight - topbarHeight - footerHeight;

        // Min height check
        if (availableHeight < 300) {
            availableHeight = 300; // Set a minimum height
        }

        $('#map').height(availableHeight);
    }


    // ------------------------------
    // Init
    // ------------------------------
    resizeMap();

    loadSystemsAndStats();

    $(window).resize(resizeMap);

})();