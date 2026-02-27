/**
 * geofence.js — Geofence UI for ClockinAPP
 *
 * Handles:
 * 1) Admin: map + circle for configuring project geofence
 * 2) User:  location acquisition, success animation, mini-map, error overlays
 */

// ========================================================
// SECTION A: Success & Error Overlays (User-facing)
// ========================================================

/**
 * Show an animated success overlay after clock in/out.
 * @param {string} message - Success message text
 * @param {object|null} coords - {lat, lng} of the user (for mini-map)
 * @param {object|null} projectCoords - {lat, lng, radius} of the project
 */
function showClockInSuccess(message, coords, projectCoords) {
    // Remove any existing overlay
    removeOverlays();

    const overlay = document.createElement('div');
    overlay.className = 'clockin-success-overlay';
    overlay.id = 'clockin-overlay';

    overlay.innerHTML = `
        <div class="checkmark-circle">
            <svg viewBox="0 0 52 52">
                <path d="M14 27 l8 8 l16 -16"/>
            </svg>
        </div>
        <div class="success-text">${escapeHtml(message)}</div>
        ${coords ? '<div class="success-minimap" id="success-minimap-canvas"></div>' : ''}
        <button class="dismiss-btn" id="dismiss-overlay-btn">OK</button>
    `;

    document.body.appendChild(overlay);

    // Initialize mini-map if coordinates available and Google Maps loaded
    if (coords && typeof google !== 'undefined' && google.maps) {
        setTimeout(() => {
            const mapEl = document.getElementById('success-minimap-canvas');
            if (!mapEl) return;

            const map = new google.maps.Map(mapEl, {
                center: coords,
                zoom: 16,
                disableDefaultUI: true,
                gestureHandling: 'none',
                styles: [
                    { elementType: 'geometry', stylers: [{ color: '#1d2c4d' }] },
                    { elementType: 'labels.text.fill', stylers: [{ color: '#8ec3b9' }] },
                    { elementType: 'labels.text.stroke', stylers: [{ color: '#1a3646' }] },
                    { featureType: 'water', elementType: 'geometry.fill', stylers: [{ color: '#17263c' }] }
                ]
            });

            // User marker
            new google.maps.Marker({
                position: coords,
                map: map,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 8,
                    fillColor: '#2ecc71',
                    fillOpacity: 1,
                    strokeColor: '#fff',
                    strokeWeight: 2
                },
                title: 'Tu ubicación'
            });

            // Project geofence circle
            if (projectCoords && projectCoords.lat && projectCoords.lng) {
                new google.maps.Circle({
                    strokeColor: '#3498db',
                    strokeOpacity: 0.8,
                    strokeWeight: 2,
                    fillColor: '#3498db',
                    fillOpacity: 0.15,
                    map: map,
                    center: { lat: projectCoords.lat, lng: projectCoords.lng },
                    radius: projectCoords.radius || 100
                });

                // Project center marker
                new google.maps.Marker({
                    position: { lat: projectCoords.lat, lng: projectCoords.lng },
                    map: map,
                    icon: {
                        path: google.maps.SymbolPath.BACKWARD_CLOSED_ARROW,
                        scale: 5,
                        fillColor: '#3498db',
                        fillOpacity: 1,
                        strokeColor: '#fff',
                        strokeWeight: 1
                    },
                    title: 'Ubicación del proyecto'
                });
            }
        }, 100);
    }

    // Auto-dismiss after 5 seconds
    const autoTimer = setTimeout(() => removeOverlays(), 5000);

    document.getElementById('dismiss-overlay-btn').addEventListener('click', () => {
        clearTimeout(autoTimer);
        removeOverlays();
    });
}

/**
 * Show a geofence error overlay.
 */
function showGeofenceError(message, details) {
    removeOverlays();

    let detailsText = '';
    if (details && details.distance_meters !== undefined) {
        const dist = Math.round(details.distance_meters);
        const allowed = details.allowed_radius;
        detailsText = `Estás a ${dist}m del proyecto. Radio permitido: ${allowed}m.`;
    }

    const overlay = document.createElement('div');
    overlay.className = 'geofence-error-overlay';
    overlay.id = 'clockin-overlay';

    overlay.innerHTML = `
        <div class="error-circle">
            <svg viewBox="0 0 52 52">
                <line x1="16" y1="16" x2="36" y2="36"/>
                <line x1="36" y1="16" x2="16" y2="36"/>
            </svg>
        </div>
        <div class="error-text">${escapeHtml(message)}</div>
        ${detailsText ? `<div class="error-details">${escapeHtml(detailsText)}</div>` : ''}
        <button class="dismiss-btn" id="dismiss-overlay-btn">Entendido</button>
    `;

    document.body.appendChild(overlay);

    document.getElementById('dismiss-overlay-btn').addEventListener('click', () => removeOverlays());
}

/**
 * Show a location-required error overlay.
 */
function showLocationRequiredError() {
    showGeofenceError(
        'La ubicación es obligatoria para registrar tu entrada/salida. Activa la geolocalización en tu navegador e intenta de nuevo.',
        null
    );
}

function removeOverlays() {
    const existing = document.getElementById('clockin-overlay');
    if (existing) existing.remove();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}


// ========================================================
// SECTION B: Admin — Project Geofence Configuration
// ========================================================

let geofenceMap = null;
let geofenceMarker = null;
let geofenceCircle = null;
let geofenceSearchBox = null;

/**
 * Initialize the geofence configuration map in the project form.
 * @param {object|null} initialCoords - {lat, lng, radius} or null for new project
 */
function initGeofenceConfigMap(initialCoords) {
    const mapContainer = document.getElementById('geofence-map-canvas');
    if (!mapContainer) return;
    if (typeof google === 'undefined' || !google.maps) {
        console.warn('Google Maps not loaded yet; geofence map skipped.');
        return;
    }

    const defaultCenter = initialCoords && initialCoords.lat
        ? { lat: initialCoords.lat, lng: initialCoords.lng }
        : { lat: 25.6866, lng: -100.3161 }; // Monterrey default

    const defaultRadius = (initialCoords && initialCoords.radius) ? initialCoords.radius : 100;
    const defaultZoom = initialCoords && initialCoords.lat ? 16 : 12;

    geofenceMap = new google.maps.Map(mapContainer, {
        center: defaultCenter,
        zoom: defaultZoom,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: false,
    });

    // Search box
    const searchInput = document.getElementById('geofence-search-input');
    if (searchInput) {
        geofenceSearchBox = new google.maps.places.SearchBox(searchInput);
        geofenceSearchBox.addListener('places_changed', () => {
            const places = geofenceSearchBox.getPlaces();
            if (!places || places.length === 0) return;
            const place = places[0];
            if (!place.geometry || !place.geometry.location) return;
            const pos = place.geometry.location;
            placeGeofenceMarker({ lat: pos.lat(), lng: pos.lng() });
            geofenceMap.setCenter(pos);
            geofenceMap.setZoom(16);
        });
    }

    // Click to place marker
    geofenceMap.addListener('click', (e) => {
        placeGeofenceMarker({ lat: e.latLng.lat(), lng: e.latLng.lng() });
    });

    // Place initial marker if coords exist
    if (initialCoords && initialCoords.lat) {
        placeGeofenceMarker(defaultCenter, defaultRadius);
    }

    // Radius slider
    const radiusSlider = document.getElementById('geofence-radius-slider');
    const radiusValue = document.getElementById('geofence-radius-value');
    const radiusInput = document.getElementById('project-geofence-radius');

    if (radiusSlider) {
        radiusSlider.value = defaultRadius;
        if (radiusValue) radiusValue.textContent = defaultRadius + 'm';
        if (radiusInput) radiusInput.value = defaultRadius;

        radiusSlider.addEventListener('input', () => {
            const r = parseInt(radiusSlider.value, 10);
            if (radiusValue) radiusValue.textContent = r + 'm';
            if (radiusInput) radiusInput.value = r;
            if (geofenceCircle) {
                geofenceCircle.setRadius(r);
            }
        });
    }

    // Current location button
    const currentLocBtn = document.getElementById('geofence-current-loc-btn');
    if (currentLocBtn) {
        currentLocBtn.addEventListener('click', () => {
            if (!navigator.geolocation) {
                alert('Tu navegador no soporta geolocalización.');
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    const coords = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                    placeGeofenceMarker(coords);
                    geofenceMap.setCenter(coords);
                    geofenceMap.setZoom(16);
                },
                () => alert('No se pudo obtener tu ubicación.')
            );
        });
    }
}

function placeGeofenceMarker(coords, radius) {
    if (!geofenceMap) return;

    const radiusSlider = document.getElementById('geofence-radius-slider');
    const r = radius || (radiusSlider ? parseInt(radiusSlider.value, 10) : 100);

    // Update hidden inputs
    const latInput = document.getElementById('project-latitude');
    const lngInput = document.getElementById('project-longitude');
    const coordsDisplay = document.getElementById('geofence-coords-display');

    if (latInput) latInput.value = coords.lat.toFixed(8);
    if (lngInput) lngInput.value = coords.lng.toFixed(8);
    if (coordsDisplay) coordsDisplay.textContent = `${coords.lat.toFixed(6)}, ${coords.lng.toFixed(6)}`;

    // Remove existing marker + circle
    if (geofenceMarker) geofenceMarker.setMap(null);
    if (geofenceCircle) geofenceCircle.setMap(null);

    // Place new marker
    geofenceMarker = new google.maps.Marker({
        position: coords,
        map: geofenceMap,
        draggable: true,
        title: 'Ubicación del proyecto'
    });

    // Place circle
    geofenceCircle = new google.maps.Circle({
        strokeColor: '#e63946',
        strokeOpacity: 0.8,
        strokeWeight: 2,
        fillColor: '#e63946',
        fillOpacity: 0.2,
        map: geofenceMap,
        center: coords,
        radius: r,
        editable: true,
    });

    // Drag marker → update circle + inputs
    geofenceMarker.addListener('dragend', () => {
        const pos = geofenceMarker.getPosition();
        const newCoords = { lat: pos.lat(), lng: pos.lng() };
        geofenceCircle.setCenter(newCoords);
        if (latInput) latInput.value = newCoords.lat.toFixed(8);
        if (lngInput) lngInput.value = newCoords.lng.toFixed(8);
        if (coordsDisplay) coordsDisplay.textContent = `${newCoords.lat.toFixed(6)}, ${newCoords.lng.toFixed(6)}`;
    });

    // Edit circle radius → update slider + input
    geofenceCircle.addListener('radius_changed', () => {
        const newR = Math.round(geofenceCircle.getRadius());
        const clampedR = Math.max(10, Math.min(5000, newR));
        if (radiusSlider) radiusSlider.value = clampedR;
        const radiusValue = document.getElementById('geofence-radius-value');
        const radiusInput = document.getElementById('project-geofence-radius');
        if (radiusValue) radiusValue.textContent = clampedR + 'm';
        if (radiusInput) radiusInput.value = clampedR;
    });
}

/**
 * Get the current geofence configuration from the admin form.
 */
function getGeofenceConfig() {
    const lat = document.getElementById('project-latitude');
    const lng = document.getElementById('project-longitude');
    const radius = document.getElementById('project-geofence-radius');

    return {
        latitude: lat && lat.value ? parseFloat(lat.value) : null,
        longitude: lng && lng.value ? parseFloat(lng.value) : null,
        geofence_radius: radius && radius.value ? parseInt(radius.value, 10) : 100,
    };
}

/**
 * Destroy / reset the geofence map state.
 */
function destroyGeofenceConfigMap() {
    if (geofenceMarker) { geofenceMarker.setMap(null); geofenceMarker = null; }
    if (geofenceCircle) { geofenceCircle.setMap(null); geofenceCircle = null; }
    geofenceMap = null;
    geofenceSearchBox = null;
}


// ========================================================
// SECTION C: User — Geolocation acquisition helper
// ========================================================

/**
 * Get the user's current GPS position.
 * Returns a Promise resolving to {lat, lng, accuracy}.
 * Rejects if geolocation is denied or unavailable.
 */
function getUserLocation() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('GEOLOCATION_NOT_SUPPORTED'));
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                resolve({
                    lat: pos.coords.latitude,
                    lng: pos.coords.longitude,
                    accuracy: pos.coords.accuracy,
                });
            },
            (err) => {
                if (err.code === 1) reject(new Error('GEOLOCATION_DENIED'));
                else if (err.code === 2) reject(new Error('GEOLOCATION_UNAVAILABLE'));
                else if (err.code === 3) reject(new Error('GEOLOCATION_TIMEOUT'));
                else reject(new Error('GEOLOCATION_UNKNOWN'));
            },
            {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 30000,
            }
        );
    });
}
