(function() {

    // ------------------------------
    // Constants & Config
    // ------------------------------
    const COLOR_VAR = 'installer_color';
    const MIN_COP = 1;
    const MAX_COP = 7;
    const SYSTEM_LIST_URL = path + "system/list/public.json";
    const SYSTEM_STATS_URL = path + "system/stats/";
    const INSTALLER_LIST_URL = path + "installer/list.json";
    const MARKER_ICON_URL = 'https://openlayers.org/en/latest/examples/data/dot.png'; // Configurable marker icon

    // ------------------------------
    // State
    // ------------------------------
    let systems = [];
    let period = 'last365'; // Default period
    let highlightLayer = null; // Add this to track the highlight layer

    SystemFilter.filterKey = '';
    SystemFilter.minDays = 0; // Minimum days of data

    // ------------------------------
    // Map Setup
    // ------------------------------
    const map = new ol.Map({
        target: 'map',
        controls: [],
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
            const [systemsRes, statsRes, installerRes] = await Promise.all([
                fetch(SYSTEM_LIST_URL).then(r => r.json()),
                fetch(SYSTEM_STATS_URL+period).then(r => r.json()),
                fetch(INSTALLER_LIST_URL).then(r => r.json())
            ]);
            systems = systemsRes;
            mergeStatsIntoSystems(statsRes);
            applyInstallerColors(installerRes);

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

    function applyInstallerColors(installerList) {
        if (!installerList || !Array.isArray(installerList)) {
            console.error("Invalid installer list format");
            return;
        }

        // create associative array for installer name => colors 
        let installerColors = {};
        installerList.forEach((installer, index) => {
            if (installer.name && installer.color) {
                installerColors[installer.name.trim()] = installer.color;
            }
        });

        // Assign colors to systems based on installer name
        systems.forEach(system => {
            if (system.installer_name && installerColors[system.installer_name.trim()]) {
                system.installer_color = installerColors[system.installer_name.trim()];
            } else {
                system.installer_color = 0; // Default color if not found
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

    // Add function to create highlight ring
    function createHighlightRing(coordinates) {
        // Remove existing highlight layer
        if (highlightLayer) {
            map.removeLayer(highlightLayer);
            highlightLayer = null;
        }

        // Create a new feature for the highlight ring
        const highlightFeature = new ol.Feature({
            geometry: new ol.geom.Point(coordinates)
        });

        // Style for the hollow blue highlight ring
        highlightFeature.setStyle(new ol.style.Style({
            image: new ol.style.Circle({
                radius: 12,
                fill: new ol.style.Fill({
                    color: 'rgba(0, 123, 255, 0.6)' // Very light blue fill for slight visibility
                })
            })
        }));

        // Create vector source and layer for highlight
        const highlightSource = new ol.source.Vector();
        highlightSource.addFeature(highlightFeature);

        highlightLayer = new ol.layer.Vector({
            source: highlightSource
        });
        highlightLayer.set('key', 'highlight_ring');
        map.addLayer(highlightLayer);
    }

    // Add function to remove highlight ring
    function removeHighlightRing() {
        if (highlightLayer) {
            map.removeLayer(highlightLayer);
            highlightLayer = null;
        }
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
            <div class="overlay-title">${system.location}</div>
            <div class="overlay-line">${system.hp_output}kW, ${system.hp_model} (SPF: ${system.combined_cop.toFixed(1)})</div>
            <div class="overlay-line">${system.installer_name ? `${system.installer_name}` : ''}</div>
            <button class="btn btn-outline-primary btn-sm" onclick="window.location.href='${path}system/view?id=${system.id}'">View System</button>
            <button class="btn btn-outline-primary btn-sm" onclick="window.location.href='${system.installer_url}'">View Installer</button>
            <div class="location-note">Note: Locations are not precise</div>

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

            let color = system.installer_color;

            if (color == 0) {
                // Default color if no installer color is set
                color = hexToRgba('#ccc', 0.8); // Default color with 80% opacity
            } else if (typeof color === 'string' && color.startsWith('#')) {
                // If color is a hex string, convert to rgba
                color = hexToRgba(color, 0.8); // Convert to rgba with 80% opacity
            }
            // color = getTemperatureColor(system[COLOR_VAR], minValue, maxValue);

            marker.setStyle(new ol.style.Style({
                image: new ol.style.Icon({
                    src: MARKER_ICON_URL, // (5) Configurable marker icon
                    scale: 0.5,
                    color: color
                })
            }));
            marker.set('index', index);
            markerVectorSource.addFeature(marker);
        });

        const markerVectorLayer = new ol.layer.Vector({
            source: markerVectorSource
        });
        markerVectorLayer.set('key', 'location_marker');
        markerVectorLayer.setZIndex(100);
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
            return;
        }

        let hoveredFeature = null;
        
        // Find the hovered feature first
        map.forEachFeatureAtPixel(e.pixel, function(feature) {
            // Skip if this is the highlight ring itself
            const layer = feature.get('layer');
            if (layer && layer.get('key') === 'highlight_ring') {
                return false;
            }
            hoveredFeature = feature;
            return true; // Stop at first feature
        }, { hitTolerance: 10 });

        // Reset all markers and then scale up the hovered one
        map.getLayers().forEach(layer => {
            if (layer.get('key') === 'location_marker') {
                layer.getSource().getFeatures().forEach(feature => {
                    const style = feature.getStyle();
                    if (style && style.getImage()) {
                        if (feature === hoveredFeature) {
                            style.getImage().setScale(0.6); // Larger hover size
                        } else {
                            style.getImage().setScale(0.5); // Normal size
                        }
                    }
                });
                // Force layer to redraw
                layer.changed();
            }
        });

    }, 10)); // Slightly longer debounce for better performance

    
    map.on('singleclick', function(e) {
        let found = false;
        map.forEachFeatureAtPixel(e.pixel, function(feature) {
            // Skip if this is the highlight ring itself
            const layer = feature.get('layer');
            if (layer && layer.get('key') === 'highlight_ring') {
                return false; // Skip highlight ring
            }

            const coordinates = feature.getGeometry().getCoordinates();
            overlay.setPosition(coordinates);

            centerMap(coordinates); // Center the map on the clicked marker

            // Create highlight ring around the clicked marker
            createHighlightRing(coordinates);

            const system_index = feature.get('index');
            if (SystemFilter.fSystems[system_index] == undefined) return false;
            const system = SystemFilter.fSystems[system_index];

            // Use template literal for HTML
            let tooltipHtml = getMapTooltipHTML(system);
            $("#map-info-content").html(tooltipHtml);
            $("#map-info-overlay").show();

            found = true;
            return true; // Stop iterating
        }, { hitTolerance: 10 });

        if (!found) {
            mapTooltip.style.display = 'none';
            removeHighlightRing(); // Remove highlight when clicking elsewhere
        }
    });

    function centerMap(coordinates) {
        // Center the view on the clicked marker
        const view = map.getView();
        const resolution = view.getResolution();
        
        // Only apply vertical offset on small screens (less than 600px)
        const isSmallScreen = window.innerWidth < 600;
        const yOffset = isSmallScreen ? (100 * resolution) : 0;
        
        const targetCenter = [
            coordinates[0],
            coordinates[1] + yOffset  // Add Y offset only on small screens
        ];

        view.animate({ 
            center: targetCenter, 
            duration: 500 // Smooth animation duration in milliseconds
        });
    }

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

    // 
    function hexToRgba(hex, alpha = 1) {
        // Remove leading #
        hex = hex.replace(/^#/, '');
        // Expand shorthand form (#abc) to full form (#aabbcc)
        if (hex.length === 3) {
            hex = hex.split('').map(x => x + x).join('');
        }
        if (hex.length !== 6) return `rgba(0,0,0,${alpha})`;
        const num = parseInt(hex, 16);
        const r = (num >> 16) & 255;
        const g = (num >> 8) & 255;
        const b = num & 255;
        return `rgba(${r},${g},${b},${alpha})`;
    }


    // ------------------------------
    // Resize Map
    // ------------------------------
    function resizeMap() {
        var windowHeight = $(window).height();
        var availableHeight = windowHeight - 58; 

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