(function() {

    // ------------------------------
    // Constants & Config
    // ------------------------------
    const COLOR_VAR = 'installer_color';
    const MIN_COP = 1;
    const MAX_COP = 7;
    const SYSTEM_LIST_URL = path + "system/list/public.json";
    const SYSTEM_STATS_URL = path + "system/stats/";
    const MARKER_ICON_URL = 'https://openlayers.org/en/latest/examples/data/dot.png'; // Configurable marker icon

    // ------------------------------
    // State
    // ------------------------------
    let systems = [];
    let period = 'last365'; // Default period

    SystemFilter.filterKey = '';
    SystemFilter.minDays = 0; // Minimum days of data

    // ------------------------------
    // Map Setup
    // ------------------------------
    const map = new ol.Map({
        target: 'map',
        view: new ol.View({
            center: ol.proj.fromLonLat([-4.0, 54]),
            zoom: 6,
            maxZoom: 14
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

    const mapTooltip = document.getElementById('map-tooltip');
    const overlay = new ol.Overlay({
        element: mapTooltip,
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
                fetch(SYSTEM_STATS_URL+period).then(r => r.json())
            ]);
            systems = systemsRes;
            mergeStatsIntoSystems(statsRes);
            generateInstallerColors(systems);

            drawLocations();
        } catch (err) {
            console.error("Failed to load systems or stats", err);
        }
    }

    function mergeStatsIntoSystems(statsResult) {
        systems.forEach(system => {
            if (statsResult[system.id]) {
                for (const key in statsResult[system.id]) {
                    system[key] = statsResult[system.id][key];
                }
            }
        });

        SystemFilter.init(systems);
        SystemFilter.applyFilters();
        console.log("Filtered systems:", SystemFilter.fSystems.length);
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
    function getMapTooltipHTML(system) {
        return `
            <b>System ID:</b> ${system.id}<br>
            <b>Location:</b><br>${system.location}<br>
            <b>Heatpump:</b><br>${system.hp_output}kW, ${system.hp_model}<br>
            <b>SCOP:</b> ${system.combined_cop.toFixed(1)}<br>
            ${system.installer_name ? `<b>Installer:</b> ${system.installer_name}` : ''}
        `;
    }

    // ------------------------------
    // Drawing Functions
    // ------------------------------
    function drawLocations() {
        // Find min/max for color scaling
        let minValue = 100, maxValue = -100;
        SystemFilter.fSystems.forEach(system => {
            if (!system[COLOR_VAR]) return;
            const val = system[COLOR_VAR];
            if (val == null) return;
            if (val < minValue) minValue = val;
            if (val > maxValue) maxValue = val;
        });

        // Clamp to fixed range
        //minValue = MIN_COP;
        //maxValue = MAX_COP;
        console.log("Min val:", minValue, "Max val:", maxValue);

        // Remove old marker layers (2)
        removeMarkerLayers();

        // Create marker vector source
        const markerVectorSource = new ol.source.Vector();

        SystemFilter.fSystems.forEach((system, index) => {
            if (!system.combined_cop) return;
            const cop = system.combined_cop;
            if (cop == null || cop < MIN_COP || cop > MAX_COP) return;

            let longitude = system.longitude;
            let latitude = system.latitude;
            // add 100m variation to each marker
            const variation = 0.003; // 100m in degrees (approx)
            longitude += (Math.random() - 0.5) * variation;
            latitude += (Math.random() - 0.5) * variation;

            const marker = new ol.Feature({
                geometry: new ol.geom.Point(ol.proj.fromLonLat([longitude, latitude])),
            });

            marker.setStyle(new ol.style.Style({
                image: new ol.style.Icon({
                    src: MARKER_ICON_URL, // (5) Configurable marker icon
                    scale: 0.5,
                    color: getTemperatureColor(system[COLOR_VAR], minValue, maxValue)
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
            mapTooltip.style.display = 'none';
            return;
        }

        let found = false;
        map.forEachFeatureAtPixel(e.pixel, function(feature) {
            const coordinates = feature.getGeometry().getCoordinates();
            overlay.setPosition(coordinates);

            const system_index = feature.get('index');
            if (SystemFilter.fSystems[system_index] == undefined) return false;
            const system = SystemFilter.fSystems[system_index];

            // (6) Use template literal for HTML
            const mapTooltipContent = document.getElementById('map-tooltip-content');
            mapTooltipContent.innerHTML = getMapTooltipHTML(system);
            mapTooltip.style.display = 'block';
            found = true;
            return true; // Stop iterating
        }, { hitTolerance: 5 });

        if (!found) {
            mapTooltip.style.display = 'none';
        }
    }, 20)); // Debounce delay in ms

    map.on('singleclick', function(e) {
        map.forEachFeatureAtPixel(e.pixel, function(feature) {
            const system_index = feature.get('index');
            if (SystemFilter.fSystems[system_index] == undefined) return false;
            const system = SystemFilter.fSystems[system_index];
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
        var windowHeight = $(window).height();
        var availableHeight = windowHeight - 116; 

        // Min height check
        if (availableHeight < 300) {
            availableHeight = 300; // Set a minimum height
        }

        $('#map').height(availableHeight);
    }

    // ------------------------------
    // URL Param Sync for Filters
    // ------------------------------
    function updateFiltersFromURL() {
        const params = new URLSearchParams(window.location.search);
        if (params.has('filter')) {
            SystemFilter.filterKey = params.get('filter');
        }
        if (params.has('minDays')) {
            const val = parseInt(params.get('minDays'), 10);
            if (!isNaN(val)) SystemFilter.minDays = val;
        }
        if (params.has('period')) {
            period = params.get('period');
            SystemFilter.stats_time_start = period; // Update the stats time start
        } else {
            SystemFilter.stats_time_start = 'last365'; // Default to last 365 days
        }

    }

    function generateInstallerColors(systems) {
        let installer_list_by_name = {};
        systems.forEach(system => {
            if (system.installer_name) {
                const name = system.installer_name.trim();
                if (!installer_list_by_name[name]) {
                    installer_list_by_name[name] = 0;
                }
                installer_list_by_name[name]++;
            }
        });

        const installerNames = Object.keys(installer_list_by_name).sort();
        console.log("Installer names:", installerNames);

        // Assign colors to systems
        systems.forEach(system => {
            system.installer_color = 0; // Default color
            if (system.installer_name) {
                const name = system.installer_name.trim();
                // if name in installerNames, assign a color based on index
                system.installer_color = installerNames.indexOf(name)+1;
                console.log(`System ${system.id} installer color: ${system.installer_color} (${name})`);
            }
        });
    }
    
    // Map search
    $("#map-search-btn").on("click", function() {
        map_search();
    });

    // On Enter key press in search input
    $("#map-search-input").on("keypress", function(event) {
        if (event.key === "Enter") {
            event.preventDefault(); // Prevent form submission
            map_search();
        }
    });

    function map_search() {
        const searchInput = $("#map-search-input").val().trim();
        if (searchInput) {
            console.log("Searching for:", searchInput);

            $.getJSON("map/search", { location: searchInput }, function(response) {
                if (response.success && response.lat && response.lng) {
                    // Center the map on the searched location
                    const view = map.getView();
                    const coords = ol.proj.fromLonLat([response.lng, response.lat]);
                    view.animate({ center: coords, duration: 800, zoom: 12 });
                } else {
                    alert("Location not found.");
                }
            }).fail(function() {
                alert("Error searching for location.");
            });
        }
    }


    // ------------------------------
    // Init
    // ------------------------------

    // Call on init
    updateFiltersFromURL();

    // Optional: Listen for URL changes (popstate)
    window.addEventListener('popstate', updateFiltersFromURL);

    resizeMap();

    loadSystemsAndStats();

    $(window).resize(resizeMap);

    

})();