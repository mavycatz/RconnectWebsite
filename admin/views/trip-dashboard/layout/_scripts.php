<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>
    let googleMapsBlacklisted = true;

    function clearGoogleErrorArtifacts() {
        document.querySelectorAll('.gm-err-container, .gm-err-content, .gm-err-message, .gm-err-title, .gm-style-pbc, .dismissButton').forEach(el => {
            try { el.remove(); } catch (e) {}
        });
    }

    (function hookGoogleMapsErrors() {
        window.gm_authFailure = function () {
            googleMapsBlacklisted = true;
            clearGoogleErrorArtifacts();
            switchToLeafletFallback('Google Maps gm_authFailure fired');
        };

        const origError = console.error;
        const origWarn = console.warn;

        function checkArgsForGoogleError(args) {
            if (activeProvider === 'leaflet') return;
            try {
                const fullText = args.map(a => (a && a.message ? a.message : String(a || ''))).join(' ');
                const isGoogleError = 
                fullText.includes('BillingNotEnabledMapError') ||
                fullText.includes('RefererNotAllowedMapError') ||
                fullText.includes('ApiNotActivatedMapError') ||
                fullText.includes('InvalidKeyMapError') ||
                fullText.includes('DeletedKeyMapError') ||
                fullText.includes('MissingKeyMapError') ||
                fullText.includes('NoApiKeys') ||
                fullText.includes('UrlAuthenticationCommonError') ||
                fullText.includes('Google Maps JavaScript API error:') ||
                fullText.includes('error-messages#billing') ||
                fullText.includes('error-messages#referer') ||
                fullText.includes('error-messages#api-not-activated') ||
                fullText.includes('error-messages#invalid-key') ||
                fullText.includes('For development purposes only') ||
                fullText.includes("can't load Google Maps correctly");

                if (isGoogleError) {
                    googleMapsBlacklisted = true;
                    switchToLeafletFallback('Google Maps error: ' + fullText.substring(0, 80));
                }
            } catch (e) {}
        }

        console.error = function (...args) {
            origError.apply(console, args);
            checkArgsForGoogleError(args);
        };

        console.warn = function (...args) {
            origWarn.apply(console, args);
            checkArgsForGoogleError(args);
        };
    })();

    const MAP_CONFIG = {
        googleKey: 'AIzaSyAqQ5vyq1uczNwuIrfGKbqYvYiaLZHgxx8',
        timeoutMs: 2500,
        initialCenter: { lat: 15.60, lng: 121.0 },
        initialZoom: 11,
        defaultRoute: 'Cabanatuan to Rizal Route'
    };

    const FALLBACK_ROUTE_STOPS = [
    { stop_name: "Cabanatuan Terminal", latitude: 15.4947, longitude: 120.9655, sequence_order: 1 },
    { stop_name: "Bitas", latitude: 15.5017, longitude: 120.9841, sequence_order: 2 },
    { stop_name: "Valdefuente", latitude: 15.5126, longitude: 120.9571, sequence_order: 3 },
    { stop_name: "Mayapyap", latitude: 15.5193, longitude: 120.9564, sequence_order: 4 },
    { stop_name: "Caalibangbangan", latitude: 15.5174, longitude: 120.9393, sequence_order: 5 },
    { stop_name: "Casile", latitude: 15.6003, longitude: 120.9765, sequence_order: 6 },
    { stop_name: "General Luna", latitude: 15.6306, longitude: 120.9914, sequence_order: 7 },
    { stop_name: "General Ricarte", latitude: 15.6293, longitude: 121.0126, sequence_order: 8 },
    { stop_name: "Mabini", latitude: 15.6371, longitude: 121.0190, sequence_order: 9 },
    { stop_name: "Inanama", latitude: 15.6489, longitude: 121.0320, sequence_order: 10 },
    { stop_name: "Baluga", latitude: 15.5601, longitude: 120.9461, sequence_order: 11 },
    { stop_name: "Homestead II", latitude: 15.5767, longitude: 120.9644, sequence_order: 12 },
    { stop_name: "Homestead I", latitude: 15.5686, longitude: 120.9554, sequence_order: 13 },
    { stop_name: "Bagong Sikat", latitude: 15.5868, longitude: 120.9771, sequence_order: 14 },
    { stop_name: "San Miguel na Munti", latitude: 15.5369, longitude: 120.9330, sequence_order: 15 },
    { stop_name: "Mataas Kanto", latitude: 15.5488, longitude: 120.9335, sequence_order: 16 },
    { stop_name: "Bicos / Rizal", latitude: 15.6602, longitude: 121.0414, sequence_order: 17 }
    ];

    const LIVE_MAP_DEBUG = new URLSearchParams(window.location.search).has('debug');
    const GPS_ROUTE_TOLERANCE_KM = 2.5;
    const GPS_MAX_SPEED_KMH = 150;
    const lastValidGps = {};

    let activeProvider = 'leaflet';
    let dashboardMap = null;
    let currentRouteName = MAP_CONFIG.defaultRoute;
    let currentRouteStops = [];
    let trips = [];
    let focusedBusId = null;
    let hasInitialCentered = false;
    let isFetchingDrivers = false;
    let liveDriversInterval = null;
    let dashboardStatsInterval = null;
    let currentMapMode = 'live';
    let currentFleetTab = 'active';
    let latestRecentActivity = [];

    function getBusSvg(busNum) {
        return `
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 54 50" width="54" height="50">
            <rect x="3" y="14" width="48" height="26" rx="5" fill="#295738"/>
            <rect x="6" y="17" width="42" height="14" rx="3" fill="#ffffff" opacity="0.9"/>
            <rect x="6" y="32" width="42" height="6" rx="2" fill="#295738"/>
            <circle cx="15" cy="42" r="4.5" fill="#222"/>
            <circle cx="39" cy="42" r="4.5" fill="#222"/>
            <rect x="20" y="19" width="14" height="10" rx="2" fill="#cdd9b5"/>
            <rect x="10" y="19" width="7" height="10" rx="2" fill="#cdd9b5"/>
            <rect x="37" y="19" width="7" height="10" rx="2" fill="#cdd9b5"/>
            <rect x="2" y="2" width="50" height="12" rx="3" fill="#f9f4da" stroke="#cdd9b5" stroke-width="1.2"/>
            <text x="27" y="11" text-anchor="middle" font-family="monospace" font-size="7.5" font-weight="bold" fill="#295738">${escapeHtml(busNum)}</text>
        </svg>
    `;
    }

    function getModalBusSvg() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 56" width="40" height="36"><rect x="4" y="16" width="52" height="28" rx="5" fill="#295738"/><rect x="7" y="19" width="46" height="16" rx="3" fill="#ffffff" opacity="0.88"/><rect x="7" y="35" width="46" height="7" rx="2" fill="#295738"/><circle cx="17" cy="46" r="5" fill="#333"/><circle cx="43" cy="46" r="5" fill="#333"/></svg>';
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function getDistanceKm(lat1, lon1, lat2, lon2) {
        if (isNaN(lat1) || isNaN(lon1) || isNaN(lat2) || isNaN(lon2)) return 0;
        const R = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
        Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }

    function isValidCoord(lat, lng) {
        const la = Number(lat);
        const lo = Number(lng);
        if (!isFinite(la) || !isFinite(lo)) return false;
        if (Math.abs(la) > 90 || Math.abs(lo) > 180) return false;
        if (Math.abs(la) < 0.000001 && Math.abs(lo) < 0.000001) return false;
        return true;
    }

    function getValidRouteStops() {
        return (currentRouteStops || [])
            .filter(stop => stop && isValidCoord(stop.latitude, stop.longitude))
            .slice()
            .sort((a, b) => (parseInt(a.sequence_order || 0, 10) - parseInt(b.sequence_order || 0, 10)));
    }

    function projectOnRoute(stops, lat, lng) {
        if (!stops || stops.length < 2) return null;
        const cosLat = Math.cos(lat * Math.PI / 180);
        let best = { distanceKm: Infinity, offsetKm: 0, stopIndex: 0 };
        let cumulative = 0;
        for (let i = 0; i < stops.length - 1; i++) {
            const alat = parseFloat(stops[i].latitude);
            const alng = parseFloat(stops[i].longitude);
            const blat = parseFloat(stops[i + 1].latitude);
            const blng = parseFloat(stops[i + 1].longitude);
            const ax = (alng - lng) * cosLat * 111.32;
            const ay = (alat - lat) * 110.57;
            const bx = (blng - lng) * cosLat * 111.32;
            const by = (blat - lat) * 110.57;
            const dx = bx - ax;
            const dy = by - ay;
            const lenSq = dx * dx + dy * dy;
            const t = lenSq > 0 ? Math.max(0, Math.min(1, -(ax * dx + ay * dy) / lenSq)) : 0;
            const cx = ax + dx * t;
            const cy = ay + dy * t;
            const distance = Math.sqrt(cx * cx + cy * cy);
            const segKm = getDistanceKm(alat, alng, blat, blng);
            if (distance < best.distanceKm) {
                best = { distanceKm: distance, offsetKm: cumulative + segKm * t, stopIndex: i };
            }
            cumulative += segKm;
        }
        return best;
    }

    function routeDistanceKm(stops, lat, lng) {
        const proj = projectOnRoute(stops, lat, lng);
        return proj ? proj.distanceKm : null;
    }

    function formatSpeed(trip) {
        if (!trip || trip.speed === null || trip.speed === undefined) return '--';
        const value = Number(trip.speed);
        if (!isFinite(value) || value < 0 || value > GPS_MAX_SPEED_KMH) return '--';
        return value.toFixed(1) + ' km/h';
    }

    function formatClockTime(timeStr) {
        if (!timeStr) return '—';
        const cleanStr = String(timeStr).replace(/-/g, '/');
        const date = new Date(cleanStr);
        if (isNaN(date.getTime())) return timeStr;
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function formatTimeAgo(timeStr) {
        if (!timeStr) return '—';
        const cleanStr = String(timeStr).replace(/-/g, '/');
        const date = new Date(cleanStr);
        if (isNaN(date.getTime())) return timeStr;
        const now = new Date();
        const diffSec = Math.floor((now - date) / 1000);
        if (diffSec < 45) return 'Just now';
        const diffMin = Math.floor(diffSec / 60);
        if (diffMin < 60) return `${diffMin}m ago`;
        const diffHrs = Math.floor(diffMin / 60);
        if (diffHrs < 24) return `${diffHrs}h ago`;
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function evaluateGpsStatus(updatedAtStr, hasGpsCoords) {
        if (!hasGpsCoords || !updatedAtStr) {
            return {
                status: 'Offline',
                badgeClass: 'offline',
                text: '○ GPS Offline',
                displayStatus: 'Offline',
                timeText: 'No signal'
            };
        }

        const cleanStr = String(updatedAtStr).replace(/-/g, '/');
        const date = new Date(cleanStr);
        if (isNaN(date.getTime())) {
            return {
                status: 'Offline',
                badgeClass: 'offline',
                text: '○ GPS Offline',
                displayStatus: 'Offline',
                timeText: 'Unknown'
            };
        }

        const ageSec = Math.floor((Date.now() - date.getTime()) / 1000);
        const formattedClock = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        if (ageSec <= 180) {
            return {
                status: 'Online',
                badgeClass: 'online',
                text: '● GPS Online',
                displayStatus: '● Online',
                timeText: formattedClock
            };
        } else if (ageSec <= 900) {
            const mins = Math.round(ageSec / 60);
            return {
                status: 'Delayed',
                badgeClass: 'delayed',
                text: 'GPS Delayed',
                displayStatus: 'Delayed',
                timeText: `${mins} min ago (${formattedClock})`
            };
        } else {
            return {
                status: 'Offline',
                badgeClass: 'offline',
                text: '○ GPS Offline',
                displayStatus: '○ Offline',
                timeText: `Last signal ${formattedClock}`
            };
        }
    }

    function updateProviderStatus(provider) {
        const badge = document.getElementById('map-provider-status');
        const icon = document.getElementById('map-provider-icon');
        const text = document.getElementById('map-provider-text');
        if (!badge || !text) return;

        badge.style.display = 'inline-flex';
        if (provider === 'google') {
            if (icon) { icon.className = 'bi bi-geo-alt me-1'; icon.textContent = ''; }
            text.textContent = 'Google Maps';
        } else if (provider === 'leaflet') {
            if (icon) { icon.className = 'bi bi-map me-1'; icon.textContent = ''; }
            text.textContent = 'OpenStreetMap';
        }
    }

    function updateOpsClock() {
        const el = document.getElementById('ops-clock');
        if (!el) return;
        const now = new Date();
        el.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' PHT';
    }

    function checkGoogleDevMode() {
        if (activeProvider === 'leaflet') return false;
        const mapEl = document.getElementById('map');
        if (!mapEl) return false;

        const hasDevElement = mapEl.querySelector('.gm-style-pbc, .gm-err-container, .gm-err-message, .dismissButton');
        const mapText = (mapEl.textContent || '').toLowerCase();
        const hasDevText = mapText.includes('development purposes') || 
        mapText.includes("can't load google maps correctly") ||
        mapText.includes('do you own this website');

        if (hasDevElement || hasDevText) {
            googleMapsBlacklisted = true;
            switchToLeafletFallback('Google Maps development mode detected in map container');
            return true;
        }
        return false;
    }

    function loadGoogleMapsScript(apiKey, timeoutMs = 2500) {
        return new Promise((resolve, reject) => {
            if (googleMapsBlacklisted || activeProvider === 'leaflet') {
                reject(new Error('Google Maps is blacklisted'));
                return;
            }
            if (window.google && window.google.maps && window.google.maps.Map) {
                resolve(window.google.maps);
                return;
            }
            if (!apiKey || typeof apiKey !== 'string' || apiKey.trim() === '') {
                reject(new Error('Google Maps API key missing'));
                return;
            }

            let settled = false;
            const timer = setTimeout(() => {
                if (!settled) {
                    settled = true;
                    reject(new Error(`Google Maps load timeout (${timeoutMs}ms)`));
                }
            }, timeoutMs);

            window.__initGoogleMapsCallback = function () {
                if (!settled) {
                    settled = true;
                    clearTimeout(timer);
                    resolve(window.google.maps);
                }
            };

            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&loading=async&callback=__initGoogleMapsCallback`;
            script.async = true;
            script.defer = true;
            script.onerror = function () {
                if (!settled) {
                    settled = true;
                    clearTimeout(timer);
                    reject(new Error('Google Maps script failed to load'));
                }
            };
            document.head.appendChild(script);
        });
    }

    class DashboardMapAdapter {
        constructor(provider) {
            this.provider = provider;
            this.map = null;
            this.traveledPolyline = null;
            this.remainingPolyline = null;
            this.stopMarkers = [];
            this.busMarkers = {};
        }

        init(container, center, zoom) {
            if (!container) return;

            if (this.provider === 'google') {
                this.map = new google.maps.Map(container, {
                    center: { lat: center.lat, lng: center.lng },
                    zoom: zoom,
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: false,
                    styles: [
                    { elementType: 'geometry', stylers: [{ color: '#fef8f3' }] },
                    { elementType: 'labels.text.fill', stylers: [{ color: '#2e4836' }] },
                    { elementType: 'labels.text.stroke', stylers: [{ color: '#ffffff' }] },
                    { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#cdd9b5' }] },
                    { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#dcefe2' }] },
                    { featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: '#e7f0d8' }] }
                    ]
                });

                checkGoogleDevMode();
                google.maps.event.addListener(this.map, 'tilesloaded', checkGoogleDevMode);
                google.maps.event.addListener(this.map, 'idle', checkGoogleDevMode);

                const devCheckTimer = setInterval(() => {
                    if (activeProvider === 'leaflet') {
                        clearInterval(devCheckTimer);
                        return;
                    }
                    if (checkGoogleDevMode()) {
                        clearInterval(devCheckTimer);
                    }
                }, 350);
                setTimeout(() => clearInterval(devCheckTimer), 8000);

            } else if (this.provider === 'leaflet') {
                if (container._leaflet_id) {
                    delete container._leaflet_id;
                }

                this.map = L.map(container, {
                    center: [center.lat, center.lng],
                    zoom: zoom,
                    zoomControl: true,
                    preferCanvas: true
                });

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    subdomains: ['a', 'b', 'c'],
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'
                }).addTo(this.map);

                setTimeout(() => {
                    if (this.map) this.map.invalidateSize();
                }, 100);
            }
        }

        destroy() {
            if (this.provider === 'google' && this.map) {
                if (this.traveledPolyline) { try { this.traveledPolyline.setMap(null); } catch (e) {} }
                if (this.remainingPolyline) { try { this.remainingPolyline.setMap(null); } catch (e) {} }
                this.stopMarkers.forEach(m => { try { m.setMap(null); } catch (e) {} });
                this.stopMarkers = [];
                Object.keys(this.busMarkers).forEach(id => {
                    if (this.busMarkers[id]) { try { this.busMarkers[id].setMap(null); } catch (e) {} }
                });
                this.busMarkers = {};
                this.map = null;
            } else if (this.provider === 'leaflet' && this.map) {
                try { this.map.remove(); } catch (e) {}
                this.traveledPolyline = null;
                this.remainingPolyline = null;
                this.stopMarkers = [];
                this.busMarkers = {};
                this.map = null;
            }

            const container = document.getElementById('map');
            if (container) {
                if (container._leaflet_id) delete container._leaflet_id;
                container.innerHTML = '';
            }
        }

        setRoute(routeName, stops, currentStopIndex = -1) {
            if (!this.map) return;

            const valid = (stops || []).filter(stop =>
                stop && isValidCoord(stop.latitude, stop.longitude)
            );
            const idx = (currentStopIndex >= 0 && currentStopIndex < valid.length) ? currentStopIndex : -1;
            const traveledStops = idx >= 0 ? valid.slice(0, idx + 1) : [];
            const remainingStops = idx >= 0 ? valid.slice(idx) : valid;

            const clearLines = () => {
                if (this.provider === 'google') {
                    if (this.traveledPolyline) { try { this.traveledPolyline.setMap(null); } catch (e) {} this.traveledPolyline = null; }
                    if (this.remainingPolyline) { try { this.remainingPolyline.setMap(null); } catch (e) {} this.remainingPolyline = null; }
                    this.stopMarkers.forEach(m => { try { m.setMap(null); } catch (e) {} });
                } else {
                    if (this.traveledPolyline) { try { this.map.removeLayer(this.traveledPolyline); } catch (e) {} this.traveledPolyline = null; }
                    if (this.remainingPolyline) { try { this.map.removeLayer(this.remainingPolyline); } catch (e) {} this.remainingPolyline = null; }
                    this.stopMarkers.forEach(m => { try { this.map.removeLayer(m); } catch (e) {} });
                }
                this.stopMarkers = [];
            };

            if (valid.length === 0) {
                clearLines();
                console.warn('[LiveMap] route "' + routeName + '" has no valid route_stops; nothing drawn.');
                return;
            }

            if (this.provider === 'google') {
                clearLines();

                const traveledPath = traveledStops.map(s => ({ lat: parseFloat(s.latitude), lng: parseFloat(s.longitude) }));
                const remainingPath = remainingStops.map(s => ({ lat: parseFloat(s.latitude), lng: parseFloat(s.longitude) }));

                if (remainingPath.length > 1) {
                    this.remainingPolyline = new google.maps.Polyline({
                        path: remainingPath,
                        geodesic: true,
                        strokeColor: '#9ca3af',
                        strokeOpacity: 0.65,
                        strokeWeight: 3
                    });
                    this.remainingPolyline.setMap(this.map);
                }
                if (traveledPath.length > 1) {
                    this.traveledPolyline = new google.maps.Polyline({
                        path: traveledPath,
                        geodesic: true,
                        strokeColor: '#2e4836',
                        strokeOpacity: 0.9,
                        strokeWeight: 4
                    });
                    this.traveledPolyline.setMap(this.map);
                }

                if (!focusedBusId) {
                    const bounds = new google.maps.LatLngBounds();
                    valid.forEach(stop => bounds.extend({ lat: parseFloat(stop.latitude), lng: parseFloat(stop.longitude) }));
                    this.map.fitBounds(bounds);
                }

                const infoWindow = new google.maps.InfoWindow();
                valid.forEach((stop, i) => {
                    const isCurrent = (i === idx);
                    const isCompleted = (idx >= 0 && i < idx);

                    const marker = new google.maps.Marker({
                        position: { lat: parseFloat(stop.latitude), lng: parseFloat(stop.longitude) },
                        map: this.map,
                        title: stop.stop_name,
                        icon: {
                            path: google.maps.SymbolPath.CIRCLE,
                            scale: isCurrent ? 6.5 : (isCompleted ? 4 : 3.5),
                            fillColor: isCurrent ? '#cdd9b5' : (isCompleted ? '#2e4836' : '#ffffff'),
                            fillOpacity: isCurrent || isCompleted ? 1 : 0.8,
                            strokeColor: isCurrent || isCompleted ? '#2e4836' : '#9ca3af',
                            strokeWeight: isCurrent ? 2.2 : 1.2
                        }
                    });

                    marker.addListener('click', () => {
                        infoWindow.setContent(`
                        <div style="font-family:Poppins,sans-serif;padding:6px;font-size:12px;color:#2e4836;">
                            <div style="display:flex;align-items:center;gap:6px;">
                                <i class="bi bi-geo-alt-fill text-success" style="font-size:13px;"></i>
                                <strong style="font-weight:600;">${escapeHtml(stop.stop_name)}</strong>
                            </div>
                            <div style="color:#6b7280;font-size:11px;margin-top:2px;">Stop #${stop.sequence_order}</div>
                        </div>
                    `);
                        infoWindow.open(this.map, marker);
                    });

                    this.stopMarkers.push(marker);
                });

            } else if (this.provider === 'leaflet') {
                clearLines();

                const traveledLatLngs = traveledStops.map(s => [parseFloat(s.latitude), parseFloat(s.longitude)]);
                const remainingLatLngs = remainingStops.map(s => [parseFloat(s.latitude), parseFloat(s.longitude)]);

                if (remainingLatLngs.length > 1) {
                    this.remainingPolyline = L.polyline(remainingLatLngs, {
                        color: '#9ca3af',
                        weight: 3,
                        opacity: 0.7,
                        dashArray: '6,8'
                    }).addTo(this.map);
                }
                if (traveledLatLngs.length > 1) {
                    this.traveledPolyline = L.polyline(traveledLatLngs, {
                        color: '#2e4836',
                        weight: 4,
                        opacity: 0.9
                    }).addTo(this.map);
                }

                if (valid.length > 0 && !focusedBusId) {
                    const bounds = L.latLngBounds(valid.map(s => [parseFloat(s.latitude), parseFloat(s.longitude)]));
                    this.map.fitBounds(bounds, { padding: [30, 30] });
                }

                valid.forEach((stop, i) => {
                    const isCurrent = (i === idx);
                    const isCompleted = (idx >= 0 && i < idx);

                    const stopMarker = L.circleMarker([parseFloat(stop.latitude), parseFloat(stop.longitude)], {
                        radius: isCurrent ? 6 : (isCompleted ? 4 : 3.5),
                        fillColor: isCurrent ? '#cdd9b5' : (isCompleted ? '#2e4836' : '#ffffff'),
                        fillOpacity: isCurrent || isCompleted ? 1 : 0.85,
                        color: isCurrent || isCompleted ? '#2e4836' : '#9ca3af',
                        weight: isCurrent ? 2.5 : 1.2
                    }).addTo(this.map);

                    const popupContent = `
                    <div style="font-family:Poppins,sans-serif;padding:6px;font-size:12px;color:#2e4836;">
                        <div style="display:flex;align-items:center;gap:6px;">
                            <i class="bi bi-geo-alt-fill text-success" style="font-size:13px;"></i>
                            <strong style="font-weight:600;">${escapeHtml(stop.stop_name)}</strong>
                        </div>
                        <div style="color:#6b7280;font-size:11px;margin-top:2px;">Stop #${stop.sequence_order}</div>
                    </div>
                `;

                    stopMarker.bindPopup(popupContent, { className: 'custom-stop-popup' });
                    this.stopMarkers.push(stopMarker);
                });

                setTimeout(() => {
                    if (this.map) this.map.invalidateSize();
                }, 60);
            }

            if (LIVE_MAP_DEBUG) {
                console.debug('[LiveMap] route drawn', {
                    route: routeName,
                    stops: valid.length,
                    currentStopIndex: idx,
                    traveledStops: traveledStops.length,
                    remainingStops: remainingStops.length
                });
            }
        }

        updateBusMarker(trip, infoContentHtml, svgHtml) {
            if (!this.map || !trip.hasGps) return;

            const busId = trip.id;
            const lat = trip.latitude;
            const lng = trip.longitude;

            if (this.provider === 'google') {
                if (this.busMarkers[busId]) {
                    const marker = this.busMarkers[busId];
                    marker.setPosition({ lat, lng });
                    if (marker.infoWindow) {
                        marker.infoWindow.setContent(infoContentHtml);
                    }
                } else {
                    const marker = new google.maps.Marker({
                        position: { lat, lng },
                        map: this.map,
                        title: busId,
                        zIndex: 999999,
                        optimized: false,
                        animation: google.maps.Animation.DROP,
                        icon: {
                            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svgHtml),
                            scaledSize: new google.maps.Size(54, 50),
                            anchor: new google.maps.Point(27, 36)
                        }
                    });

                    const infoWindow = new google.maps.InfoWindow({ content: infoContentHtml });
                    marker.infoWindow = infoWindow;
                    marker.addListener('click', () => infoWindow.open(this.map, marker));
                    this.busMarkers[busId] = marker;
                }

            } else if (this.provider === 'leaflet') {
                if (this.busMarkers[busId]) {
                    const marker = this.busMarkers[busId];
                    marker.setLatLng([lat, lng]);
                    const popup = marker.getPopup();
                    if (popup) {
                        popup.setContent(infoContentHtml);
                    }
                } else {
                    const busIcon = L.divIcon({
                        className: 'leaflet-bus-icon',
                        html: svgHtml,
                        iconSize: [54, 50],
                        iconAnchor: [27, 36]
                    });

                    const marker = L.marker([lat, lng], {
                        icon: busIcon,
                        zIndexOffset: 1000
                    }).addTo(this.map);

                    marker.bindPopup(infoContentHtml, {
                        maxWidth: 260,
                        className: 'custom-bus-popup'
                    });

                    this.busMarkers[busId] = marker;
                }
            }
        }

        pruneGhostBuses(activeBusIds) {
            Object.keys(this.busMarkers).forEach(busId => {
                if (!activeBusIds.includes(busId)) {
                    if (this.provider === 'google') {
                        try { this.busMarkers[busId].setMap(null); } catch (e) {}
                    } else if (this.provider === 'leaflet') {
                        try { this.map.removeLayer(this.busMarkers[busId]); } catch (e) {}
                    }
                    delete this.busMarkers[busId];
                }
            });
        }

        panTo(lat, lng, zoom = null) {
            if (!this.map) return;
            if (this.provider === 'google') {
                this.map.panTo({ lat, lng });
                if (zoom !== null) this.map.setZoom(zoom);
            } else if (this.provider === 'leaflet') {
                if (zoom !== null) {
                    this.map.setView([lat, lng], zoom);
                } else {
                    this.map.panTo([lat, lng]);
                }
            }
        }

        focusBus(busId) {
            if (!this.map) return;
            focusedBusId = busId;

            Object.keys(this.busMarkers).forEach(id => {
                if (this.provider === 'google') {
                    if (!this.busMarkers[id].getMap()) {
                        this.busMarkers[id].setMap(this.map);
                    }
                } else if (this.provider === 'leaflet') {
                    if (!this.map.hasLayer(this.busMarkers[id])) {
                        this.busMarkers[id].addTo(this.map);
                    }
                }
            });

            const marker = this.busMarkers[busId];
            if (marker) {
                if (this.provider === 'google') {
                    this.map.panTo(marker.getPosition());
                    this.map.setZoom(16);
                } else if (this.provider === 'leaflet') {
                    this.map.setView(marker.getLatLng(), 16);
                }
            }
        }

        showAllBuses() {
            if (!this.map) return;
            focusedBusId = null;

            Object.keys(this.busMarkers).forEach(id => {
                if (this.provider === 'google') {
                    if (!this.busMarkers[id].getMap()) {
                        this.busMarkers[id].setMap(this.map);
                    }
                } else if (this.provider === 'leaflet') {
                    if (!this.map.hasLayer(this.busMarkers[id])) {
                        this.busMarkers[id].addTo(this.map);
                    }
                }
            });

            if (this.provider === 'google') {
                const bounds = new google.maps.LatLngBounds();
                let hasPoints = false;
                [this.remainingPolyline, this.traveledPolyline].forEach(line => {
                    if (line && line.getPath().getLength() > 0) {
                        line.getPath().forEach(p => { bounds.extend(p); hasPoints = true; });
                    }
                });
                if (hasPoints) this.map.fitBounds(bounds);
            } else if (this.provider === 'leaflet') {
                if (this.remainingPolyline) {
                    this.map.fitBounds(this.remainingPolyline.getBounds(), { padding: [30, 30] });
                } else if (this.traveledPolyline) {
                    this.map.fitBounds(this.traveledPolyline.getBounds(), { padding: [30, 30] });
                }
            }
        }
    }

    function switchToLeafletFallback(reason) {
        if (activeProvider === 'leaflet') return;

        console.info('Switching to Leaflet + OpenStreetMap. Reason:', reason);
        googleMapsBlacklisted = true;

        if (dashboardMap) {
            dashboardMap.destroy();
            dashboardMap = null;
        }

        const mapContainer = document.getElementById('map');
        if (mapContainer) {
            if (mapContainer._leaflet_id) delete mapContainer._leaflet_id;
            mapContainer.innerHTML = '';
        }

        document.querySelectorAll('.gm-err-container, .gm-err-message, .gm-err-title, .dismissButton').forEach(el => {
            try { el.remove(); } catch (e) {}
        });

        dashboardMap = new DashboardMapAdapter('leaflet');
        dashboardMap.init(mapContainer, MAP_CONFIG.initialCenter, MAP_CONFIG.initialZoom);

        activeProvider = 'leaflet';
        updateProviderStatus('leaflet');

        loadRoute(currentRouteName || MAP_CONFIG.defaultRoute);
        loadLiveDrivers();

        setTimeout(() => {
            if (dashboardMap && dashboardMap.map && dashboardMap.map.invalidateSize) {
                dashboardMap.map.invalidateSize();
            }
        }, 120);

        const modalEl = document.getElementById('busPassengerModal');
        if (modalEl && modalEl.classList.contains('show') && modalEl._modalBusData) {
            const mb = modalEl._modalBusData;
            initModalMapLeaflet(mb.busId, mb.lat, mb.lng, mb.data);
        }
    }

    async function initDashboardMap() {
        activeProvider = 'leaflet';
        googleMapsBlacklisted = true;

        clearGoogleErrorArtifacts();

        const mapContainer = document.getElementById('map');
        if (mapContainer) {
            if (mapContainer._leaflet_id) delete mapContainer._leaflet_id;
            mapContainer.innerHTML = '';
        }

        dashboardMap = new DashboardMapAdapter('leaflet');
        dashboardMap.init(
        mapContainer,
        MAP_CONFIG.initialCenter,
        MAP_CONFIG.initialZoom
        );

        updateProviderStatus('leaflet');

        loadTripDashboard();
        if (dashboardStatsInterval) clearInterval(dashboardStatsInterval);
        dashboardStatsInterval = setInterval(loadTripDashboard, 10000);

        updateOpsClock();
        setInterval(updateOpsClock, 1000);

        if (!currentRouteStops || currentRouteStops.length === 0) {
            loadRoute(MAP_CONFIG.defaultRoute);
        }
        if (!trips || trips.length === 0) {
            loadLiveDrivers();
        }

        handleOpenTripParam();

        if (liveDriversInterval) clearInterval(liveDriversInterval);
        liveDriversInterval = setInterval(() => {
            loadLiveDrivers();
        }, 2500);

        window.addEventListener('resize', () => {
            if (dashboardMap && dashboardMap.map && dashboardMap.map.invalidateSize) {
                dashboardMap.map.invalidateSize();
            }
            syncFleetActivityHeights();
        });

        if (window.ResizeObserver) {
            const fleetSection = document.getElementById('fleet-activity-section');
            if (fleetSection) {
                new ResizeObserver(() => {
                    syncFleetActivityHeights();
                }).observe(fleetSection);
            }
        }

        setTimeout(() => {
            if (dashboardMap && dashboardMap.map && dashboardMap.map.invalidateSize) {
                dashboardMap.map.invalidateSize();
            }
            clearGoogleErrorArtifacts();
        }, 150);
    }

    window.initMap = initDashboardMap;

    async function loadTripDashboard() {
        try {
            const response = await fetch('/admin/api/TripDashboard/dashboard_stats.php');
            const stats = await response.json();

            if (stats && stats.success) {
                const elToday = document.getElementById('kpi-today-trips');
                if (elToday) elToday.textContent = stats.total ?? 0;

                const elActive = document.getElementById('kpi-active-buses');
                if (elActive) {
                    const departed = stats.departed ?? 0;
                    const totalFleet = stats.total_buses ?? 25;
                    elActive.textContent = `${departed} / ${totalFleet}`;
                }

                const elCompleted = document.getElementById('kpi-completed-trips');
                if (elCompleted) elCompleted.textContent = stats.completed ?? 0;

                const elScheduled = document.getElementById('kpi-scheduled-trips');
                if (elScheduled) elScheduled.textContent = stats.scheduled ?? 0;
            }
        } catch (e) {
            console.warn('Dashboard stats fetch failed:', e);
        }
    }

    async function loadRoute(routeName) {
        try {
            currentRouteName = routeName;
            let stops = [];

            try {
                const response = await fetch(
                `/admin/api/TripDashboard/getRouteStops.php?route=${encodeURIComponent(routeName)}`
                );
                const result = await response.json();

                if (result && result.success && Array.isArray(result.data) && result.data.length > 0) {
                    stops = result.data;
                }
            } catch (apiErr) {
                console.warn('API route stops fetch failed, using fallback corridor:', apiErr);
            }

            stops = (stops || []).filter(stop =>
                stop && isValidCoord(stop.latitude, stop.longitude)
            );

            if (stops.length === 0) {
                console.warn('[LiveMap] No valid route_stops returned for route "' + routeName + '". Route will not be drawn.');
            } else if (LIVE_MAP_DEBUG) {
                console.debug('[LiveMap] route loaded', {
                    route: routeName,
                    stops: stops.length,
                    coordinates: stops.map(stop => [parseFloat(stop.latitude), parseFloat(stop.longitude)])
                });
            }

            currentRouteStops = stops;

            if (dashboardMap) {
                dashboardMap.setRoute(routeName, currentRouteStops);
            }

            renderTripOverview();

        } catch (error) {
            console.error('Error loading route stops:', error);
        }
    }

    async function loadLiveDrivers() {
        if (isFetchingDrivers) return;
        isFetchingDrivers = true;

        try {
            const response = await fetch('/admin/api/TripDashboard/getDriverLocations.php');
            const result = await response.json();

            const liveStatusText = document.getElementById('live-data-status-text');
            if (liveStatusText) liveStatusText.textContent = 'LIVE DATA';

            if (!result || !result.success || !Array.isArray(result.data)) {
                trips = [];
                renderAllOperationsPanels();
                return;
            }

            trips = [];
            let firstBusPosition = null;
            const activeGpsBusIds = [];

            result.data.forEach(driver => {
                const lat = isValidCoord(driver.latitude, driver.longitude) ? parseFloat(driver.latitude) : NaN;
                const lng = isValidCoord(driver.latitude, driver.longitude) ? parseFloat(driver.longitude) : NaN;
                const coordsValid = !isNaN(lat) && !isNaN(lng);
                const backendValid = driver.gps_valid !== false;
                const gpsValid = coordsValid && backendValid;
                const gpsReason = driver.gps_reason || (!coordsValid ? 'invalid_coordinates' : null);

                const gpsEvaluation = evaluateGpsStatus(driver.updated_at, gpsValid);

                const rawBusId = (driver.bus_id || '').trim();
                const rawPlate = (driver.plate_number || '').trim();
                let resolvedBusId = rawBusId;
                if (!resolvedBusId || resolvedBusId.toUpperCase() === 'NO BUS') {
                    if (rawPlate && rawPlate.toUpperCase() !== 'N/A') {
                        resolvedBusId = rawPlate;
                    } else if (driver.trip_id) {
                        resolvedBusId = `Bus #${driver.trip_id}`;
                    } else {
                        resolvedBusId = 'Active Bus';
                    }
                }

                const resolvedPlate = (rawPlate && rawPlate.toUpperCase() !== 'N/A')
                ? rawPlate
                : (rawBusId && rawBusId.toUpperCase() !== 'NO BUS' ? rawBusId : 'N/A');

                const totalPass = parseInt(driver.total_passengers || 0, 10);
                const boardedPass = (totalPass === 0) ? 0 : parseInt(driver.boarded_passengers || 0, 10);
                const droppedPass = (totalPass === 0) ? 0 : parseInt(driver.dropped_off_passengers || 0, 10);
                const unpaidPass = (totalPass === 0) ? 0 : parseInt(driver.unpaid_passengers || 0, 10);
                const paymentStatus = unpaidPass > 0 ? `${unpaidPass} Unpaid` : (driver.payment_status || 'Paid');

                const speedValue = (driver.speed === null || driver.speed === undefined) ? null : Number(driver.speed);
                const speedValid = speedValue !== null && isFinite(speedValue) && speedValue >= 0 && speedValue <= GPS_MAX_SPEED_KMH;

                const trip = {
                    id: resolvedBusId,
                    tripId: driver.trip_id || null,
                    driver: driver.driver_id,
                    driverName: driver.driver_name || 'Not Assigned',
                    speed: speedValid ? speedValue : null,
                    speedSource: driver.speed_source || 'none',
                    occupancy: boardedPass,
                    totalPassengers: totalPass,
                    droppedOff: droppedPass,
                    unpaid_passengers: unpaidPass,
                    has_discrepancy: Boolean(driver.has_discrepancy),
                    payment_status: paymentStatus,
                    revenue: parseFloat(driver.total_revenue || 0),
                    capacity: parseInt(driver.seats || 0, 10) || 25,
                    fuel: parseFloat(driver.fuel_cost || 0),
                    route: driver.route_name || 'No Route',
                    status: driver.trip_status || 'Departed',
                    departureTime: driver.departure_time || null,
                    busType: driver.bus_type || '—',
                    plateNumber: resolvedPlate,
                    updatedAtRaw: driver.updated_at || null,
                    time: gpsEvaluation.timeText,
                    gpsStatusText: gpsValid ? gpsEvaluation.displayStatus : (gpsReason === 'off_route' ? 'Invalid GPS (off route)' : 'Invalid GPS'),
                    gpsBadgeClass: gpsValid ? gpsEvaluation.badgeClass : 'offline',
                    gpsValid: gpsValid,
                    gpsReason: gpsReason,
                    distanceFromRouteKm: (driver.distance_from_route_km !== undefined && driver.distance_from_route_km !== null)
                        ? Number(driver.distance_from_route_km) : null,
                    routeStopsCount: parseInt(driver.route_stops_count || 0, 10),
                    hasGps: false,
                    latitude: null,
                    longitude: null,
                    inspector_id: driver.inspector_id || null,
                    inspector_name: driver.inspector_name || 'Not Assigned',
                    inspector_status: driver.inspector_status || 'Not Assigned',
                    inspector_on_board: Boolean(driver.inspector_on_board),
                    inspector_assigned_at: driver.inspector_assigned_at || null,
                    inspection_status: driver.inspection_status || 'Not Started',
                    inspection_result: driver.inspection_result || 'None'
                };

                if (LIVE_MAP_DEBUG) {
                    console.debug('[LiveMap] trip', {
                        tripId: trip.tripId, bus: trip.id, route: trip.route,
                        routeStops: trip.routeStopsCount, gps: coordsValid ? [lat, lng] : null,
                        updatedAt: trip.updatedAtRaw, gpsValid: gpsValid, gpsReason: gpsReason,
                        distanceFromRouteKm: trip.distanceFromRouteKm,
                        speedKmh: trip.speed, speedSource: trip.speedSource
                    });
                }

                if (gpsValid) {
                    lastValidGps[trip.id] = { lat: lat, lng: lng };
                    trip.hasGps = true;
                    trip.latitude = lat;
                    trip.longitude = lng;
                } else if (lastValidGps[trip.id]) {
                    trip.hasGps = true;
                    trip.latitude = lastValidGps[trip.id].lat;
                    trip.longitude = lastValidGps[trip.id].lng;
                    trip.gpsStalePosition = true;
                    console.warn('[LiveMap] keeping last valid GPS for ' + trip.id + ' (' + (gpsReason || 'stale') + ')');
                }

                trips.push(trip);

                if (!trip.hasGps) return;

                activeGpsBusIds.push(trip.id);

                if (!firstBusPosition) {
                    firstBusPosition = { lat: trip.latitude, lng: trip.longitude };
                }

                if (dashboardMap) {
                    dashboardMap.updateBusMarker(
                    trip,
                    createInfoWindowContent(trip),
                    getBusSvg(trip.id)
                    );
                }
            });

            if (dashboardMap) {
                dashboardMap.pruneGhostBuses(activeGpsBusIds);
            }

            if (firstBusPosition && !hasInitialCentered && !focusedBusId) {
                hasInitialCentered = true;
                if (dashboardMap) {
                    dashboardMap.panTo(firstBusPosition.lat, firstBusPosition.lng, 15);
                }
            }

            if (!focusedBusId || !trips.some(t => t.id === focusedBusId)) {
                const firstActive = trips.find(t => t.status === 'Departed') || trips[0];
                if (firstActive) {
                    focusedBusId = firstActive.id;
                } else {
                    focusedBusId = null;
                }
            }

            if (dashboardMap && currentRouteStops.length > 0) {
                const focusedTrip = trips.find(t => t.id === focusedBusId);
                const currentStopIdx = focusedTrip ? findNearestStopIndex(focusedTrip) : -1;
                dashboardMap.setRoute(currentRouteName, currentRouteStops, currentStopIdx);
            }

            if (Array.isArray(result.recent_activity)) {
                latestRecentActivity = result.recent_activity;
            }

            populateRouteFilterOptions();
            renderAllOperationsPanels();

        } catch (error) {
            console.error('Error fetching live drivers:', error);
            const liveStatusText = document.getElementById('live-data-status-text');
            if (liveStatusText) liveStatusText.textContent = 'RECONNECTING...';
            renderAllOperationsPanels();
        } finally {
            isFetchingDrivers = false;
        }
    }

    function createInfoWindowContent(trip) {
        const seatPercent = Math.round((trip.occupancy / (trip.capacity || 1)) * 100);
        return `
        <div style="min-width:210px;background:#fef8f3;border:1.5px solid #cdd9b5;border-radius:12px;padding:12px;font-family:Poppins,sans-serif;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <strong style="font-size:14px;color:#295738;">${escapeHtml(trip.id)}</strong>
                <span style="background:#295738;color:#fff;font-size:10px;font-weight:600;padding:2px 6px;border-radius:6px;">${escapeHtml(trip.status)}</span>
            </div>
            <div style="font-size:11px;color:#6b7280;margin-bottom:8px;">${escapeHtml(trip.route)}</div>

            <div style="font-size:11px;color:#4b5563;display:flex;flex-direction:column;gap:3px;margin-bottom:8px;">
                <div>Driver: <strong style="color:#2e4836;">${escapeHtml(trip.driverName)}</strong></div>
                <div>Occupancy: <strong style="color:#2e4836;">${trip.occupancy} / ${trip.capacity} (${seatPercent}%)</strong></div>
                <div>Speed: <strong style="color:#2e4836;">${formatSpeed(trip)}</strong></div>
            </div>

            <div style="display:flex;gap:6px;margin-top:6px;">
                <button type="button" onclick="selectBus('${escapeHtml(trip.id)}')" class="ops-btn-primary" style="flex:1;padding:5px 8px;font-size:11px;border-radius:7px;">
                    <i class="bi bi-crosshair"></i> Focus
                </button>
                <button type="button" onclick="openBusModal('${escapeHtml(trip.id)}', '${escapeHtml(trip.tripId || '')}', ${trip.latitude || 0}, ${trip.longitude || 0})" class="ops-btn-details" style="flex:1;padding:5px 8px;font-size:11px;border-radius:7px;">
                    <i class="bi bi-eye-fill"></i> Details
                </button>
            </div>
        </div>
    `;
    }

    function renderAllOperationsPanels() {
        renderFloatingBusCard();
        renderLiveBusDetails();
        filterFleetTable();
        renderTripOverview();
    }

    function renderFloatingBusCard() {
        const card = document.getElementById('floating-bus-card');
        if (!card) return;

        if (!trips || trips.length === 0) {
            card.style.display = 'none';
            return;
        }

        card.style.display = 'block';

        const selector = document.getElementById('floating-bus-selector');
        if (selector) {
            const prevSelected = selector.value;
            selector.innerHTML = '';
            trips.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.id;
                const hasDistinctPlate = t.plateNumber && t.plateNumber !== 'N/A' && t.plateNumber.toLowerCase() !== t.id.toLowerCase();
                opt.textContent = hasDistinctPlate ? `${t.id} (${t.plateNumber})` : `${t.id}`;
                if (t.id === (focusedBusId || prevSelected)) {
                    opt.selected = true;
                }
                selector.appendChild(opt);
            });
        }

        const currentTrip = trips.find(t => t.id === focusedBusId) || trips[0];
        if (!currentTrip) return;

        const routeEl = document.getElementById('floating-bus-route');
        if (routeEl) routeEl.textContent = currentTrip.route;

        const speedEl = document.getElementById('floating-bus-speed');
        if (speedEl) speedEl.textContent = formatSpeed(currentTrip);

        const gpsEl = document.getElementById('floating-bus-gps');
        if (gpsEl) {
            gpsEl.className = `ops-gps-badge ${currentTrip.gpsBadgeClass}`;
            gpsEl.textContent = currentTrip.gpsStatusText;
        }
    }

    function renderLiveBusDetails() {
        const activeState = document.getElementById('bus-details-active-state');
        const emptyState = document.getElementById('bus-details-empty-state');
        if (!activeState || !emptyState) return;

        const currentTrip = trips.find(t => t.id === focusedBusId) || trips[0];

        if (!currentTrip) {
            activeState.style.display = 'none';
            emptyState.style.display = 'block';
            return;
        }

        activeState.style.display = 'block';
        emptyState.style.display = 'none';

        const busIdEl = document.getElementById('detail-bus-id');
        if (busIdEl) busIdEl.textContent = currentTrip.id;

        const plateEl = document.getElementById('detail-plate-number');
        if (plateEl) {
            if (currentTrip.plateNumber && currentTrip.plateNumber !== 'N/A' && currentTrip.plateNumber.toLowerCase() !== currentTrip.id.toLowerCase()) {
                plateEl.textContent = currentTrip.plateNumber;
                plateEl.style.display = 'inline-block';
            } else {
                plateEl.textContent = '';
                plateEl.style.display = 'none';
            }
        }

        const routeEl = document.getElementById('detail-route-name');
        if (routeEl) routeEl.textContent = currentTrip.route;

        const statusEl = document.getElementById('detail-trip-status');
        if (statusEl) {
            if (currentTrip.status === 'Departed') {
                statusEl.className = 'ops-status-badge in-progress';
                statusEl.textContent = '● ON TRIP';
            } else if (currentTrip.status === 'Scheduled') {
                statusEl.className = 'ops-status-badge scheduled';
                statusEl.textContent = '○ SCHEDULED';
            } else {
                statusEl.className = 'ops-status-badge completed';
                statusEl.textContent = currentTrip.status.toUpperCase();
            }
        }

        const gpsStatusEl = document.getElementById('detail-gps-status');
        if (gpsStatusEl) {
            gpsStatusEl.className = `ops-gps-badge ${currentTrip.gpsBadgeClass}`;
            gpsStatusEl.textContent = currentTrip.gpsStatusText;
        }

        const speedEl = document.getElementById('detail-speed');
        if (speedEl) speedEl.textContent = formatSpeed(currentTrip);

        const lastUpEl = document.getElementById('detail-last-update');
        if (lastUpEl) lastUpEl.textContent = currentTrip.time;

        const driverNameEl = document.getElementById('detail-driver-name');
        if (driverNameEl) driverNameEl.textContent = currentTrip.driverName;

        const driverSubEl = document.getElementById('detail-driver-sub');
        if (driverSubEl) {
            driverSubEl.textContent = currentTrip.driverName !== 'Not Assigned' ? 'Assigned' : 'Unassigned';
        }

        const inspBlock = document.getElementById('detail-inspector-block');
        const inspSubEl = document.getElementById('detail-inspector-sub');
        if (inspBlock) {
            if (currentTrip.inspector_on_board) {
                inspBlock.innerHTML = `
                <div class="d-flex align-items-center gap-1">
                    <span class="ops-inspector-badge on-board">● On Board</span>
                    <strong class="ops-val-compact">${escapeHtml(currentTrip.inspector_name)}</strong>
                </div>
            `;
                if (inspSubEl) {
                    const checkInTime = currentTrip.inspector_assigned_at ? formatClockTime(currentTrip.inspector_assigned_at) : '';
                    inspSubEl.textContent = checkInTime ? `Checked-in ${checkInTime}` : 'Checked-in on trip';
                }
            } else if (currentTrip.inspector_id && currentTrip.inspector_status !== 'Not Assigned') {
                inspBlock.innerHTML = `
                <div class="d-flex align-items-center gap-1">
                    <span class="ops-inspector-badge assigned">○ ${escapeHtml(currentTrip.inspector_status)}</span>
                    <strong class="ops-val-compact">${escapeHtml(currentTrip.inspector_name)}</strong>
                </div>
            `;
                if (inspSubEl) inspSubEl.textContent = '';
            } else {
                inspBlock.innerHTML = `<span class="ops-val-compact text-muted">○ Not Assigned</span>`;
                if (inspSubEl) inspSubEl.textContent = '';
            }
        }

        const occRatioEl = document.getElementById('detail-occupancy-ratio');
        if (occRatioEl) occRatioEl.textContent = `${currentTrip.occupancy} / ${currentTrip.capacity}`;

        const passSubEl = document.getElementById('detail-passengers-sub');
        if (passSubEl) passSubEl.textContent = `${currentTrip.occupancy} boarded · ${currentTrip.droppedOff} dropped`;

        const occBar = document.getElementById('detail-occupancy-bar');
        if (occBar) {
            const percent = Math.min(100, Math.round((currentTrip.occupancy / (currentTrip.capacity || 1)) * 100));
            occBar.style.width = `${percent}%`;
        }

        const inspStatusEl = document.getElementById('detail-inspection-status');
        if (inspStatusEl) {
            if (currentTrip.inspection_status === 'Completed') {
                inspStatusEl.className = 'ops-val-compact text-success';
                inspStatusEl.innerHTML = `<i class="bi bi-check-circle-fill text-success me-1"></i>Completed (${escapeHtml(currentTrip.inspection_result || 'Passed')})`;
            } else if (currentTrip.inspection_status === 'Findings Submitted') {
                inspStatusEl.className = 'ops-val-compact text-warning';
                inspStatusEl.innerHTML = '<i class="bi bi-exclamation-triangle text-warning me-1"></i>Findings Submitted';
            } else if (currentTrip.inspection_status === 'In Progress') {
                inspStatusEl.className = 'ops-val-compact text-primary';
                inspStatusEl.innerHTML = '<i class="bi bi-clock-history text-primary me-1"></i>In Progress';
            } else {
                inspStatusEl.className = 'ops-val-compact text-muted';
                inspStatusEl.textContent = 'Not Started';
            }
        }

        const payStatusEl = document.getElementById('detail-payment-status');
        if (payStatusEl) {
            if (currentTrip.has_discrepancy) {
                payStatusEl.className = 'ops-val-compact text-danger';
                payStatusEl.innerHTML = '<i class="bi bi-exclamation-circle text-danger me-1"></i>Discrepancy Found';
            } else if (currentTrip.unpaid_passengers > 0) {
                payStatusEl.className = 'ops-val-compact text-warning';
                payStatusEl.innerHTML = `<i class="bi bi-cash-stack text-warning me-1"></i>${currentTrip.unpaid_passengers} Unpaid`;
            } else {
                payStatusEl.className = 'ops-val-compact text-success';
                payStatusEl.innerHTML = '<i class="bi bi-check-circle text-success me-1"></i>Paid';
            }
        }
    }

    function setFleetTab(tab) {
        currentFleetTab = tab;
        document.querySelectorAll('.ops-tab-btn').forEach(btn => btn.classList.remove('active'));

        const tabId = tab === 'active' ? 'tab-active-trips' : (tab === 'all' ? 'tab-all-buses' : 'tab-upcoming-trips');
        const activeBtn = document.getElementById(tabId);
        if (activeBtn) activeBtn.classList.add('active');

        const statusFilter = document.getElementById('fleet-status-filter');
        if (statusFilter) {
            if (tab === 'active' && statusFilter.value === 'Scheduled') {
                statusFilter.value = 'all';
            } else if (tab === 'upcoming' && statusFilter.value === 'Departed') {
                statusFilter.value = 'all';
            }
        }

        filterFleetTable();
    }

    function handleStatusFilterChange(statusVal) {
        if (statusVal === 'Departed') {
            currentFleetTab = 'active';
        } else if (statusVal === 'Scheduled') {
            currentFleetTab = 'upcoming';
        } else {
            currentFleetTab = 'all';
        }

        document.querySelectorAll('.ops-tab-btn').forEach(btn => btn.classList.remove('active'));
        const tabId = currentFleetTab === 'active' ? 'tab-active-trips' : (currentFleetTab === 'all' ? 'tab-all-buses' : 'tab-upcoming-trips');
        const activeBtn = document.getElementById(tabId);
        if (activeBtn) activeBtn.classList.add('active');

        filterFleetTable();
    }

    function handleFleetSearchInput(input) {
        const clearBtn = document.getElementById('fleet-search-clear');
        if (clearBtn) {
            clearBtn.style.display = input.value.trim() ? 'flex' : 'none';
        }
        filterFleetTable();
    }

    function clearFleetSearch() {
        const input = document.getElementById('fleet-search-input');
        const clearBtn = document.getElementById('fleet-search-clear');
        if (input) {
            input.value = '';
            input.focus();
        }
        if (clearBtn) clearBtn.style.display = 'none';
        filterFleetTable();
    }

    function resetFleetFilters() {
        const searchInput = document.getElementById('fleet-search-input');
        const routeFilter = document.getElementById('fleet-route-filter');
        const statusFilter = document.getElementById('fleet-status-filter');
        const inspectorFilter = document.getElementById('fleet-inspector-filter');
        const clearBtn = document.getElementById('fleet-search-clear');

        if (searchInput) searchInput.value = '';
        if (clearBtn) clearBtn.style.display = 'none';
        if (routeFilter) routeFilter.value = 'all';
        if (statusFilter) statusFilter.value = 'all';
        if (inspectorFilter) inspectorFilter.value = 'all';

        currentFleetTab = 'active';
        document.querySelectorAll('.ops-tab-btn').forEach(btn => btn.classList.remove('active'));
        const activeBtn = document.getElementById('tab-active-trips');
        if (activeBtn) activeBtn.classList.add('active');

        filterFleetTable();
    }

    function populateRouteFilterOptions() {
        const routeSelect = document.getElementById('fleet-route-filter');
        if (!routeSelect) return;

        const uniqueRoutes = new Set();
        trips.forEach(t => {
            if (t.route && typeof t.route === 'string') {
                const clean = t.route.trim();
                if (clean && clean.toLowerCase() !== 'all') {
                    uniqueRoutes.add(clean);
                }
            }
        });

        if (uniqueRoutes.size === 0 && MAP_CONFIG && MAP_CONFIG.defaultRoute) {
            uniqueRoutes.add(MAP_CONFIG.defaultRoute);
        }

        const currentVal = routeSelect.value || 'all';

        let html = `<option value="all">All Routes (${uniqueRoutes.size})</option>`;
        Array.from(uniqueRoutes).sort().forEach(r => {
            const displayText = r.replace(/\bto\b/gi, '→').replace(/\s+/g, ' ');
            const isSelected = (r === currentVal) ? 'selected' : '';
            html += `<option value="${escapeHtml(r)}" ${isSelected}>${escapeHtml(displayText)}</option>`;
        });

        routeSelect.innerHTML = html;

        if (currentVal !== 'all' && !uniqueRoutes.has(currentVal)) {
            routeSelect.value = 'all';
        }
    }

    function filterFleetTable() {
        const searchVal = (document.getElementById('fleet-search-input')?.value || '').toLowerCase().trim();
        const routeVal = document.getElementById('fleet-route-filter')?.value || 'all';
        const statusVal = document.getElementById('fleet-status-filter')?.value || 'all';
        const inspectorVal = document.getElementById('fleet-inspector-filter')?.value || 'all';

        const isFiltered = Boolean(searchVal || routeVal !== 'all' || statusVal !== 'all' || inspectorVal !== 'all');
        const resetBtn = document.getElementById('fleet-filter-reset');
        if (resetBtn) {
            resetBtn.classList.toggle('active', isFiltered);
        }
        const searchClear = document.getElementById('fleet-search-clear');
        if (searchClear) {
            searchClear.style.display = searchVal ? 'flex' : 'none';
        }

        const activeCount = trips.filter(t => t.status === 'Departed').length;
        const allCount = trips.length;
        const upcomingCount = trips.filter(t => t.status === 'Scheduled').length;

        const bActive = document.getElementById('badge-active-count');
        if (bActive) bActive.textContent = activeCount;
        const bAll = document.getElementById('badge-all-count');
        if (bAll) bAll.textContent = allCount;
        const bUp = document.getElementById('badge-upcoming-count');
        if (bUp) bUp.textContent = upcomingCount;

        const filtered = trips.filter(trip => {
            if (statusVal === 'all') {
                if (currentFleetTab === 'active' && trip.status !== 'Departed') return false;
                if (currentFleetTab === 'upcoming' && trip.status !== 'Scheduled') return false;
            } else {
                if (trip.status !== statusVal) return false;
            }

            if (routeVal !== 'all') {
                const tRoute = (trip.route || '').toLowerCase().trim();
                const fRoute = routeVal.toLowerCase().trim();
                const normT = tRoute.replace(/[^a-z0-9]/g, '');
                const normF = fRoute.replace(/[^a-z0-9]/g, '');
                if (tRoute !== fRoute && !normT.includes(normF) && !normF.includes(normT)) {
                    return false;
                }
            }

            const hasInspector = Boolean(
            (trip.inspector_id && trip.inspector_id !== 0 && trip.inspector_id !== '0') ||
            (trip.inspector_name && trip.inspector_name !== 'Not Assigned' && trip.inspector_name !== 'None' && trip.inspector_name !== 'N/A' && trip.inspector_name !== '') ||
            trip.inspector_on_board
            );
            if (inspectorVal === 'assigned' && !hasInspector) return false;
            if (inspectorVal === 'unassigned' && hasInspector) return false;

            if (searchVal) {
                const rawTokens = [
                trip.id || '',
                trip.plateNumber || '',
                trip.route || '',
                trip.driverName || '',
                trip.inspector_name || ''
                ].join(' ').toLowerCase();

                const normRaw = rawTokens.replace(/[^a-z0-9]/g, '');
                const normSearch = searchVal.replace(/[^a-z0-9]/g, '');

                const terms = searchVal.split(/\s+/).filter(Boolean);
                const matchesTerms = terms.every(term => rawTokens.includes(term));
                const matchesNorm = normSearch.length >= 2 && normRaw.includes(normSearch);

                if (!matchesTerms && !matchesNorm) return false;
            }

            return true;
        });

        renderFleetTableRows(filtered);

        const fleetActivities = getUnifiedFleetActivities(latestRecentActivity, filtered);
        renderRecentActivity(fleetActivities);
        highlightSelectedTableRow();
        updateRecentActivityHighlight();
        syncFleetActivityHeights();
    }

    function renderFleetTableRows(tripList) {
        const tbody = document.getElementById('fleet-table-body');
        if (!tbody) return;

        if (!tripList || tripList.length === 0) {
            tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-4 text-muted">
                    <div style="font-size:12px;">No operating buses found</div>
                </td>
            </tr>
        `;
            return;
        }

        let html = '';
        tripList.forEach(trip => {
            const isSelected = (trip.id === focusedBusId);
            const percent = Math.min(100, Math.round((trip.occupancy / (trip.capacity || 1)) * 100));
            const statusBadgeClass = trip.status === 'Departed' ? 'in-progress' : 'scheduled';
            const statusLabel = trip.status === 'Departed' ? '● In Progress' : '○ Scheduled';

            const hasDistinctPlate = trip.plateNumber && trip.plateNumber !== 'N/A' && trip.plateNumber.toLowerCase() !== trip.id.toLowerCase();

            html += `
            <tr class="${isSelected ? 'selected-row' : ''}" data-bus-id="${escapeHtml(trip.id)}" onclick="selectBus('${trip.id}')">
                <td>
                    <div class="ops-val-compact" style="color:#295738; font-weight:600;">${escapeHtml(trip.id)}</div>
                    ${hasDistinctPlate ? `<div class="ops-meta-sub" style="color:#4b5563; font-size:10px; font-weight:500;">Plate: ${escapeHtml(trip.plateNumber)}</div>` : ''}
                    <div class="ops-meta-sub">${escapeHtml(trip.route)}</div>
                </td>
                <td>
                    <div class="ops-val-compact" style="font-size:12px;">${escapeHtml(trip.driverName)}</div>
                    <div class="ops-meta-sub" style="color:${trip.inspector_on_board ? '#2e7d32' : '#8a9a8a'};">
                        Inspector: ${trip.inspector_on_board ? '● ' : '○ '}${escapeHtml(trip.inspector_name)}
                    </div>
                </td>
                <td>
                    <span class="ops-status-badge ${statusBadgeClass}">${statusLabel}</span>
                </td>
                <td>
                    <div class="ops-val-compact" style="font-size:11px;">${trip.occupancy} / ${trip.capacity}</div>
                    <div class="progress ops-progress-bar-xs mt-1" style="width:50px;">
                        <div class="progress-bar" style="width:${percent}%;"></div>
                    </div>
                </td>
                <td>
                    <div class="ops-val-compact" style="font-size:12px;">
                        <span class="ops-gps-dot ${trip.gpsBadgeClass}">●</span> ${formatSpeed(trip)}
                    </div>
                    <div class="ops-meta-sub" style="font-size:10px;">${trip.time}</div>
                </td>
                <td style="text-align: right;">
                    <button
                        type="button"
                        class="ops-btn-details"
                        onclick="event.stopPropagation(); openBusModal('${escapeHtml(trip.id)}', '${escapeHtml(trip.tripId || '')}', ${trip.latitude || 0}, ${trip.longitude || 0})"
                        title="View details for ${escapeHtml(trip.id)}"
                    >
                        <i class="bi bi-eye-fill"></i>
                        <span>Details</span>
                    </button>
                </td>
            </tr>
        `;
        });

        tbody.innerHTML = html;
    }

    function highlightSelectedTableRow() {
        const rows = document.querySelectorAll('#fleet-table-body tr');
        rows.forEach(r => {
            if (r.dataset.busId === focusedBusId) {
                r.classList.add('selected-row');
            } else {
                r.classList.remove('selected-row');
            }
        });
    }

    function getUnifiedFleetActivities(rawActivities, fleetTrips) {
        const list = [];
        const seenKeys = new Set();
        const visibleBusIds = new Set((fleetTrips || []).map(t => String(t.id).trim().toUpperCase()));

        if (Array.isArray(rawActivities)) {
            rawActivities.forEach(act => {
                const bId = String(act.bus_id || '').trim().toUpperCase();
                if (visibleBusIds.size > 0 && bId && !visibleBusIds.has(bId)) {
                    return;
                }
                const key = `${act.type}_${bId}_${act.time}_${act.title}`;
                if (!seenKeys.has(key)) {
                    seenKeys.add(key);
                    list.push({
                        time: act.time || 'Live',
                        type: act.type || 'boarding',
                        title: act.title || 'Trip Event',
                        description: act.description || '',
                        bus_id: act.bus_id || '',
                        actor: act.actor || ''
                    });
                }
            });
        }

        if (Array.isArray(fleetTrips)) {
            fleetTrips.forEach(trip => {
                const bId = trip.id;
                const bIdUpper = String(bId).trim().toUpperCase();

                if (trip.inspector_on_board || (trip.inspector_name && trip.inspector_name !== 'Not Assigned')) {
                    const inspKey = `inspector_${bIdUpper}_${trip.inspector_name}`;
                    if (!seenKeys.has(inspKey)) {
                        seenKeys.add(inspKey);
                        list.push({
                            time: trip.inspector_assigned_at || trip.updatedAtRaw || trip.time || 'Today',
                            type: 'inspector',
                            title: trip.inspector_on_board ? 'Inspector Checked In' : 'Inspector Assigned',
                            description: `${trip.inspector_name} · ${trip.inspector_on_board ? 'Checked-In on board' : 'Status: ' + trip.inspector_status} (${bId})`,
                            bus_id: bId,
                            actor: trip.inspector_name
                        });
                    }
                }

                if (trip.status === 'Departed') {
                    const depKey = `departure_${bIdUpper}_${trip.departureTime || trip.route}`;
                    if (!seenKeys.has(depKey)) {
                        seenKeys.add(depKey);
                        list.push({
                            time: trip.departureTime || trip.updatedAtRaw || trip.time || 'Today',
                            type: 'departure',
                            title: 'Trip In Progress',
                            description: `${bId} departed on ${trip.route} (Driver: ${trip.driverName})`,
                            bus_id: bId,
                            actor: trip.driverName
                        });
                    }
                } else if (trip.status === 'Scheduled') {
                    const schKey = `scheduled_${bIdUpper}_${trip.departureTime || trip.route}`;
                    if (!seenKeys.has(schKey)) {
                        seenKeys.add(schKey);
                        list.push({
                            time: trip.departureTime || 'Upcoming',
                            type: 'departure',
                            title: 'Scheduled Departure',
                            description: `${bId} scheduled for ${trip.route} (Driver: ${trip.driverName})`,
                            bus_id: bId,
                            actor: trip.driverName
                        });
                    }
                }

                if (trip.occupancy > 0) {
                    const passKey = `passengers_${bIdUpper}_${trip.occupancy}`;
                    if (!seenKeys.has(passKey)) {
                        seenKeys.add(passKey);
                        list.push({
                            time: trip.updatedAtRaw || trip.time || 'Live',
                            type: 'boarding',
                            title: `${trip.occupancy} Passengers Onboard`,
                            description: `${bId} occupancy at ${Math.round((trip.occupancy / (trip.capacity || 1)) * 100)}% capacity · ${trip.payment_status}`,
                            bus_id: bId,
                            actor: trip.driverName
                        });
                    }
                }

                if (Number(trip.speed) > 0 && Number(trip.speed) <= GPS_MAX_SPEED_KMH) {
                    const transitKey = `transit_${bIdUpper}_${trip.time}`;
                    if (!seenKeys.has(transitKey)) {
                        seenKeys.add(transitKey);
                        list.push({
                            time: trip.time || 'Live',
                            type: 'transit',
                            title: 'Bus In Transit',
                            description: `${bId} cruising at ${formatSpeed(trip)} on ${trip.route}`,
                            bus_id: bId,
                            actor: trip.driverName
                        });
                    }
                }

                if (trip.inspection_status === 'Findings Submitted' || (trip.inspection_result && trip.inspection_result !== 'Passed' && trip.inspection_result !== 'None' && trip.inspection_result !== 'Pending')) {
                    const findKey = `finding_${bIdUpper}_${trip.inspection_result}`;
                    if (!seenKeys.has(findKey)) {
                        seenKeys.add(findKey);
                        list.push({
                            time: trip.updatedAtRaw || trip.time || 'Today',
                            type: 'inspection',
                            title: 'Inspection Finding',
                            description: `${bId}: ${trip.inspection_result} (Inspector: ${trip.inspector_name})`,
                            bus_id: bId,
                            actor: trip.inspector_name
                        });
                    }
                }
            });
        }

        list.sort((a, b) => {
            const parseT = (t) => {
                if (!t || t === 'Live' || t === 'Upcoming' || t === 'Today') return Date.now();
                const d = Date.parse(t);
                return isNaN(d) ? 0 : d;
            };
            return parseT(b.time) - parseT(a.time);
        });

        return list;
    }

    function renderRecentActivity(activities) {
        const container = document.getElementById('recent-activity-timeline');
        if (!container) return;

        if (!activities || activities.length === 0) {
            container.innerHTML = `
            <div class="text-center py-4 text-muted">
                <div style="font-size:12px; font-weight:500;">No activity for selected fleet</div>
                <div style="font-size:11px; margin-top:2px;">Events update automatically with fleet operations.</div>
            </div>
        `;
            const bCount = document.getElementById('activity-count-badge');
            if (bCount) bCount.textContent = '0 events';
            const syncText = document.getElementById('activity-fleet-sync-text');
            if (syncText) syncText.textContent = 'No matching fleet buses';
            syncFleetActivityHeights();
            return;
        }

        let html = '';
        activities.forEach((act, idx) => {
            const type = act.type || 'boarding';
            const formattedTime = formatClockTime(act.time);
            const timeAgo = formatTimeAgo(act.time);
            const timeDisplay = (timeAgo && timeAgo !== formattedTime) ? `${formattedTime} · ${timeAgo}` : formattedTime;
            const isLast = (idx === activities.length - 1);
            const isSelectedBus = (focusedBusId && act.bus_id === focusedBusId);
            const busClick = act.bus_id ? `onclick="selectBus('${act.bus_id}')" style="cursor:pointer;" title="Focus bus ${act.bus_id}"` : '';

            html += `
            <div class="ops-timeline-item ${isSelectedBus ? 'active-bus-activity' : ''}" data-bus-id="${escapeHtml(act.bus_id || '')}" ${busClick}>
                <div class="ops-timeline-node ${type}"></div>
                ${!isLast ? '<div class="ops-timeline-line"></div>' : ''}
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="ops-timeline-time">${escapeHtml(timeDisplay)}</span>
                    ${act.bus_id ? `
                        <span class="badge ${isSelectedBus ? 'bg-success text-white' : 'bg-light text-dark border'}" style="font-size:9px; font-weight:600; padding:2px 5px;">
                            ${escapeHtml(act.bus_id)}
                        </span>
                    ` : ''}
                </div>
                <div class="ops-timeline-title">${escapeHtml(act.title || 'Trip Event')}</div>
                <div class="ops-timeline-desc">${escapeHtml(act.description || '')}</div>
            </div>
        `;
        });

        container.innerHTML = html;

        const bCount = document.getElementById('activity-count-badge');
        if (bCount) bCount.textContent = `${activities.length} event${activities.length === 1 ? '' : 's'}`;

        const syncText = document.getElementById('activity-fleet-sync-text');
        if (syncText) {
            if (focusedBusId) {
                syncText.textContent = `Focused on ${focusedBusId}`;
            } else {
                const visibleBusCount = document.querySelectorAll('#fleet-table-body tr[data-bus-id]').length;
                syncText.textContent = `Synchronized with ${visibleBusCount} bus${visibleBusCount === 1 ? '' : 'es'}`;
            }
        }

        syncFleetActivityHeights();
    }

    function findNearestStopIndex(trip) {
        const stops = getValidRouteStops();
        if (!trip || !trip.hasGps || stops.length === 0) return -1;

        const proj = projectOnRoute(stops, trip.latitude, trip.longitude);
        if (!proj) return -1;
        if (proj.distanceKm > GPS_ROUTE_TOLERANCE_KM) {
            if (LIVE_MAP_DEBUG) {
                console.warn('[LiveMap] GPS rejected for ' + (trip.id || 'bus') + ': ' + proj.distanceKm.toFixed(2) + ' km from route');
            }
            return -1;
        }

        let index = 0;
        let cumulative = 0;
        for (let i = 0; i < stops.length - 1; i++) {
            const segKm = getDistanceKm(
                parseFloat(stops[i].latitude), parseFloat(stops[i].longitude),
                parseFloat(stops[i + 1].latitude), parseFloat(stops[i + 1].longitude)
            );
            if (cumulative + segKm <= proj.offsetKm + 0.05) {
                index = i + 1;
                cumulative += segKm;
            } else {
                break;
            }
        }
        return index;
    }

    function calculateNextStopAndProgress(trip) {
        const stops = getValidRouteStops();
        const totalStops = stops.length;

        let totalCorridorKm = 0;
        for (let i = 0; i < totalStops - 1; i++) {
            totalCorridorKm += getDistanceKm(
            parseFloat(stops[i].latitude), parseFloat(stops[i].longitude),
            parseFloat(stops[i+1].latitude), parseFloat(stops[i+1].longitude)
            );
        }
        if (totalCorridorKm <= 0) totalCorridorKm = 25.0;

        if (!trip || !trip.hasGps || totalStops === 0) {
            return {
                percent: 0,
                traveledKm: '0.0',
                remainingKm: totalCorridorKm.toFixed(1),
                nextStopName: totalStops ? stops[0].stop_name : 'Terminal',
                nextStopMeta: 'GPS unavailable'
            };
        }

        const nearestIdx = findNearestStopIndex(trip);
        if (nearestIdx < 0) {
            return {
                percent: 0,
                traveledKm: '0.0',
                remainingKm: totalCorridorKm.toFixed(1),
                nextStopName: totalStops ? stops[0].stop_name : 'Terminal',
                nextStopMeta: 'Invalid GPS'
            };
        }
        const distToNearest = getDistanceKm(trip.latitude, trip.longitude, parseFloat(stops[nearestIdx].latitude), parseFloat(stops[nearestIdx].longitude));

        let nextIdx = nearestIdx;
        if (distToNearest < 0.35 && nearestIdx < totalStops - 1) {
            nextIdx = nearestIdx + 1;
        }

        const nextStop = stops[nextIdx] || stops[totalStops - 1];
        const distToNext = getDistanceKm(trip.latitude, trip.longitude, parseFloat(nextStop.latitude), parseFloat(nextStop.longitude));

        let traveledKm = 0;
        for (let i = 0; i < nearestIdx; i++) {
            traveledKm += getDistanceKm(
            parseFloat(stops[i].latitude), parseFloat(stops[i].longitude),
            parseFloat(stops[i+1].latitude), parseFloat(stops[i+1].longitude)
            );
        }
        traveledKm = Math.min(totalCorridorKm, Math.max(0, traveledKm));
        const remainingKm = Math.max(0, totalCorridorKm - traveledKm);
        const percent = Math.min(100, Math.max(0, Math.round((traveledKm / totalCorridorKm) * 100)));

        let nextStopMeta = 'In transit';
        if (distToNext > 0.1) {
            nextStopMeta = `~${distToNext.toFixed(1)} km · In transit`;
        }

        return {
            percent,
            traveledKm: traveledKm.toFixed(1),
            remainingKm: remainingKm.toFixed(1),
            nextStopName: nextStop.stop_name,
            nextStopMeta
        };
    }

    function renderTripOverview() {
        const section = document.getElementById('trip-overview-section');
        if (!section) return;

        const currentTrip = trips.find(t => t.id === focusedBusId) || trips[0];

        const busTitleEl = document.getElementById('overview-bus-id');
        const routeEl = document.getElementById('overview-route-name');

        if (!currentTrip) {
            if (busTitleEl) busTitleEl.textContent = 'No Active Bus';
            if (routeEl) routeEl.textContent = MAP_CONFIG.defaultRoute;
            return;
        }

        if (busTitleEl) busTitleEl.textContent = currentTrip.id;
        if (routeEl) routeEl.textContent = currentTrip.route;

        const prog = calculateNextStopAndProgress(currentTrip);

        const progPercentEl = document.getElementById('overview-progress-percent');
        if (progPercentEl) progPercentEl.textContent = `${prog.percent}%`;

        const progBar = document.getElementById('overview-progress-bar');
        if (progBar) progBar.style.width = `${prog.percent}%`;

        const traveledEl = document.getElementById('overview-traveled-dist');
        if (traveledEl) traveledEl.textContent = `${prog.traveledKm} km traveled`;

        const remainingEl = document.getElementById('overview-remaining-dist');
        if (remainingEl) remainingEl.textContent = `${prog.remainingKm} km remaining`;

        const boardStat = document.getElementById('overview-boarded-stat');
        if (boardStat) boardStat.textContent = currentTrip.occupancy;

        const dropStat = document.getElementById('overview-dropped-stat');
        if (dropStat) dropStat.textContent = currentTrip.droppedOff;

        const occStat = document.getElementById('overview-occupancy-stat');
        if (occStat) occStat.textContent = `${currentTrip.occupancy} / ${currentTrip.capacity}`;

        const nextStopNameEl = document.getElementById('overview-next-stop-name');
        if (nextStopNameEl) nextStopNameEl.textContent = prog.nextStopName;

        const nextStopMetaEl = document.getElementById('overview-next-stop-meta');
        if (nextStopMetaEl) nextStopMetaEl.textContent = prog.nextStopMeta;

        const inspSumEl = document.getElementById('overview-inspection-summary');
        if (inspSumEl) {
            if (currentTrip.inspection_status === 'Completed') {
                inspSumEl.className = 'text-success';
                inspSumEl.textContent = `Completed (${currentTrip.inspection_result || 'Passed'})`;
            } else if (currentTrip.inspection_status === 'Findings Submitted') {
                inspSumEl.className = 'text-warning';
                inspSumEl.textContent = 'Findings Submitted';
            } else if (currentTrip.inspection_status === 'In Progress') {
                inspSumEl.className = 'text-primary';
                inspSumEl.textContent = 'In Progress';
            } else {
                inspSumEl.className = 'text-muted';
                inspSumEl.textContent = 'Not Started';
            }
        }

        const paySumEl = document.getElementById('overview-payment-summary');
        if (paySumEl) {
            if (currentTrip.has_discrepancy) {
                paySumEl.className = 'text-danger';
                paySumEl.textContent = 'Discrepancy Found';
            } else if (currentTrip.unpaid_passengers > 0) {
                paySumEl.className = 'text-warning';
                paySumEl.textContent = `${currentTrip.unpaid_passengers} Unpaid`;
            } else {
                paySumEl.className = 'text-success';
                paySumEl.textContent = 'Paid';
            }
        }
    }

    function selectBus(busId) {
        if (!busId) return;
        focusedBusId = busId;

        if (dashboardMap) {
            dashboardMap.focusBus(busId);
            if (currentRouteStops.length > 0) {
                const focusedTrip = trips.find(t => t.id === focusedBusId);
                const currentStopIdx = focusedTrip ? findNearestStopIndex(focusedTrip) : -1;
                dashboardMap.setRoute(currentRouteName, currentRouteStops, currentStopIdx);
            }
        }

        const sel = document.getElementById('floating-bus-selector');
        if (sel && sel.value !== busId) {
            sel.value = busId;
        }

        renderFloatingBusCard();
        renderLiveBusDetails();
        highlightSelectedTableRow();
        renderTripOverview();
        updateRecentActivityHighlight();
        scrollToBusTableRow(busId);
    }

    function viewAllBuses() {
        focusedBusId = null;
        if (dashboardMap) {
            dashboardMap.showAllBuses();
            if (currentRouteStops.length > 0) {
                dashboardMap.setRoute(currentRouteName, currentRouteStops, -1);
            }
        }
        renderFloatingBusCard();
        renderLiveBusDetails();
        highlightSelectedTableRow();
        renderTripOverview();
        updateRecentActivityHighlight();
    }

    function updateRecentActivityHighlight() {
        const items = document.querySelectorAll('#recent-activity-timeline .ops-timeline-item');
        let firstActiveItem = null;

        items.forEach(item => {
            const itemBusId = item.dataset.busId;
            const badge = item.querySelector('.badge');
            if (focusedBusId && itemBusId === focusedBusId) {
                item.classList.add('active-bus-activity');
                if (badge) {
                    badge.className = 'badge bg-success text-white';
                }
                if (!firstActiveItem) firstActiveItem = item;
            } else {
                item.classList.remove('active-bus-activity');
                if (badge) {
                    badge.className = 'badge bg-light text-dark border';
                }
            }
        });

        if (firstActiveItem) {
            firstActiveItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        const syncText = document.getElementById('activity-fleet-sync-text');
        if (syncText) {
            if (focusedBusId) {
                syncText.textContent = `Focused on ${focusedBusId}`;
            } else {
                const visibleBusCount = document.querySelectorAll('#fleet-table-body tr[data-bus-id]').length;
                syncText.textContent = `Synchronized with ${visibleBusCount} bus${visibleBusCount === 1 ? '' : 'es'}`;
            }
        }
    }

    function scrollToBusTableRow(busId) {
        const row = document.querySelector(`#fleet-table-body tr[data-bus-id="${busId}"]`);
        if (row) {
            row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function syncFleetActivityHeights() {
        if (window.innerWidth >= 1200) {
            const fleetCard = document.querySelector('.ops-fleet-card');
            const actCard = document.querySelector('.ops-activity-card');
            if (fleetCard && actCard) {
                fleetCard.style.height = '';
                actCard.style.height = '';
                const targetHeight = Math.max(fleetCard.offsetHeight, actCard.offsetHeight, 460);
                fleetCard.style.height = targetHeight + 'px';
                actCard.style.height = targetHeight + 'px';
            }
        } else {
            const fleetCard = document.querySelector('.ops-fleet-card');
            const actCard = document.querySelector('.ops-activity-card');
            if (fleetCard) fleetCard.style.height = '';
            if (actCard) actCard.style.height = '';
        }
    }

    function centerOnFocusedBus() {
        const targetBus = trips.find(t => t.id === focusedBusId) || trips[0];
        if (targetBus && targetBus.hasGps && dashboardMap) {
            dashboardMap.panTo(targetBus.latitude, targetBus.longitude, 16);
        }
    }

    function openCurrentBusModal() {
        const targetBus = trips.find(t => t.id === focusedBusId) || trips[0];
        if (targetBus) {
            openBusModal(targetBus.id, targetBus.tripId, targetBus.latitude, targetBus.longitude);
        }
    }

    function setMapMode(mode) {
        currentMapMode = mode;
        const btnLive = document.getElementById('btn-mode-live');
        const btnRoute = document.getElementById('btn-mode-route');

        if (btnLive) btnLive.classList.toggle('active', mode === 'live');
        if (btnRoute) btnRoute.classList.toggle('active', mode === 'route');

        if (mode === 'route') {
            if (dashboardMap) {
                dashboardMap.showAllBuses();
            }
        } else {
            centerOnFocusedBus();
        }
    }

    window.selectBus = selectBus;
    window.viewAllBuses = viewAllBuses;
    window.centerOnFocusedBus = centerOnFocusedBus;
    window.openCurrentBusModal = openCurrentBusModal;
    window.setMapMode = setMapMode;
    window.setFleetTab = setFleetTab;
    window.filterFleetTable = filterFleetTable;

    async function loadBusPassengers(busId, tripId) {
        try {
            let url = '/admin/api/TripDashboard/getBusPassengers.php?';
            if (tripId) {
                url += 'trip_id=' + encodeURIComponent(tripId);
            } else {
                url += 'bus_id=' + encodeURIComponent(busId || '');
            }

            const response = await fetch(url);
            const result = await response.json();
            return (result && result.success) ? result.data : null;
        } catch (error) {
            console.error('Error loading passengers:', error);
            return null;
        }
    }

    function openBusModal(busId, tripId, lat, lng) {
        const modal = document.getElementById('busPassengerModal');
        if (!modal) return;

        modal.querySelector('.modal-body').innerHTML = `
        <div style="text-align:center;padding:30px;color:#6b7280;">
            <div class="spinner-border spinner-border-sm" style="color:#295738;" role="status"></div>
            <div style="margin-top:8px;font-size:12px;font-weight:500;">Loading passenger data...</div>
        </div>
    `;

        const bsModal = bootstrap.Modal.getOrCreateInstance(modal);
        bsModal.show();

        modal._busLat = lat;
        modal._busLng = lng;

        loadBusPassengers(busId, tripId).then(data => {
            if (!data) {
                modal.querySelector('.modal-body').innerHTML = `
                <div style="text-align:center;padding:30px;color:#e74c3c;font-size:12px;font-weight:500;">Failed to load passenger data.</div>
            `;
                return;
            }

            if (!data.trip && (!data.passengers || data.passengers.length === 0)) {
                modal.querySelector('.modal-body').innerHTML = `
                <div style="text-align:center;padding:30px;">
                    <div style="font-size:32px;margin-bottom:8px;color:#295738;"><i class="bi bi-bus-front"></i></div>
                    <div style="font-weight:600;color:#295738;font-size:14px;">No active trip found</div>
                    <div style="font-size:12px;color:#6b7280;margin-top:4px;">Bus ${escapeHtml(busId)} has no current departed trip.</div>
                </div>
            `;
                return;
            }

            renderBusModalContent(modal, data, busId);
        });
    }

    function renderBusModalContent(modal, data, busId) {
        const stats = data.stats || {};
        const passengers = data.passengers || [];
        const boarded = passengers.filter(p => !p.is_dropped_off);
        const droppedOff = passengers.filter(p => p.is_dropped_off);

        const payHtml = Object.entries(stats.payment_breakdown || {})
        .map(([k, v]) => `<span style="background:#f9f4da;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:500;color:#295738;">${escapeHtml(k)}: ${v}</span>`)
        .join(' ');

        const bpHtml = Object.entries(stats.boarding_points || {})
        .sort((a, b) => b[1] - a[1])
        .map(([k, v]) => `<div style="font-size:11px;color:#555;display:flex;justify-content:space-between;padding:1px 0;"><span>${escapeHtml(k)}</span><strong>${v}</strong></div>`)
        .join('');

        const boardedHtml = boarded.length
        ? boarded.map(p => `
            <div style="background:#fff;border:1px solid #e5e0a4;border-radius:8px;padding:8px 10px;margin-bottom:4px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <strong style="font-size:12px;color:#295738;">${escapeHtml(p.passenger_name)}</strong>
                    <span style="background:${p.payment_method === 'gcash' || p.payment_method === 'hybrid' ? '#e8f5e9' : '#fff3e0'};padding:1px 6px;border-radius:4px;font-size:10px;font-weight:600;color:#295738;">${escapeHtml(p.payment_method || 'cash')}</span>
                </div>
                <div style="display:flex;gap:12px;margin-top:4px;font-size:11px;color:#6b7280;flex-wrap:wrap;">
                    <span><i class="bi bi-geo-alt-fill" style="color:#295738;font-size:10px;"></i> ${escapeHtml(p.boarding_point || '-')}</span>
                    <span><i class="bi bi-flag-fill" style="color:#e67e22;font-size:10px;"></i> ${escapeHtml(p.destination || '-')}</span>
                    <span><i class="bi bi-cash-coin" style="color:#295738;font-size:10px;"></i> ₱${(p.fare || 0).toFixed(2)}</span>
                    <span><i class="bi bi-clock" style="color:#6b7280;font-size:10px;"></i> ${p.boarding_time ? formatClockTime(p.boarding_time) : '-'}</span>
                </div>
            </div>
        `).join('')
        : '<div style="text-align:center;padding:16px;color:#999;font-size:12px;">No passengers currently boarded</div>';

        const droppedHtml = droppedOff.length
        ? droppedOff.map(p => `
            <div style="background:#f9f9f9;border:1px solid #ddd;border-radius:8px;padding:8px 10px;margin-bottom:4px;opacity:0.8;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <strong style="font-size:12px;color:#555;">${escapeHtml(p.passenger_name)}</strong>
                    <span style="font-size:10px;color:#999;">${escapeHtml(p.destination || '-')}</span>
                </div>
                <div style="display:flex;gap:12px;margin-top:4px;font-size:10px;color:#999;flex-wrap:wrap;">
                    <span>${escapeHtml(p.boarding_point || '-')}</span>
                    <span>₱${(p.fare || 0).toFixed(2)}</span>
                    <span>Dropped ${p.dropoff_time ? formatClockTime(p.dropoff_time) : ''}</span>
                </div>
            </div>
        `).join('')
        : '<div style="text-align:center;padding:14px;color:#999;font-size:12px;">No dropped-off passengers</div>';

        const lat = modal._busLat;
        const lng = modal._busLng;
        const hasCoords = lat && lng && !isNaN(lat) && !isNaN(lng);

        modal._modalBusData = { busId, lat, lng, data };

        modal.querySelector('.modal-body').innerHTML = `
        <div style="margin-bottom:12px;padding:10px 14px;background:#fef8f3;border:1.5px solid #cdd9b5;border-radius:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <div>
                    <strong style="font-size:15px;color:#295738;">Bus ${escapeHtml(busId)}</strong>
                    <span style="font-size:11px;color:#6b7280;margin-left:6px;">${escapeHtml(data.trip ? data.trip.route_name : '-')}</span>
                </div>
                <div style="background:#295738;color:#fff;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:600;">
                    ${escapeHtml(data.trip ? data.trip.status : 'Unknown')}
                </div>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <div style="flex:1;min-width:70px;background:#fff;border-radius:6px;padding:6px;text-align:center;border:1px solid #e8e6d9;">
                    <div style="font-size:16px;font-weight:700;color:#295738;">${stats.boarded || 0}</div>
                    <div style="font-size:10px;color:#6b7280;">Boarded</div>
                </div>
                <div style="flex:1;min-width:70px;background:#fff;border-radius:6px;padding:6px;text-align:center;border:1px solid #e8e6d9;">
                    <div style="font-size:16px;font-weight:700;color:#e67e22;">${stats.dropped_off || 0}</div>
                    <div style="font-size:10px;color:#6b7280;">Dropped</div>
                </div>
                <div style="flex:1;min-width:70px;background:#fff;border-radius:6px;padding:6px;text-align:center;border:1px solid #e8e6d9;">
                    <div style="font-size:16px;font-weight:700;color:#295738;">₱${(stats.total_revenue || 0).toFixed(2)}</div>
                    <div style="font-size:10px;color:#6b7280;">Revenue</div>
                </div>
                <div style="flex:1;min-width:70px;background:#fff;border-radius:6px;padding:6px;text-align:center;border:1px solid #e8e6d9;">
                    <div style="font-size:16px;font-weight:700;color:#295738;">${stats.total || 0}</div>
                    <div style="font-size:10px;color:#6b7280;">Total Pax</div>
                </div>
            </div>

            <div style="margin-top:8px;display:flex;gap:4px;flex-wrap:wrap;">
                ${payHtml}
            </div>
        </div>

        ${hasCoords ? `
        <div style="margin-bottom:12px;border:1.5px solid #cdd9b5;border-radius:10px;overflow:hidden;">
            <div id="modalMap" style="height:170px;width:100%;"></div>
        </div>` : ''}

        <ul class="nav nav-tabs" style="border-bottom:1.5px solid #cdd9b5;margin-bottom:10px;">
            <li class="nav-item">
                <a class="nav-link active" style="font-size:12px;font-weight:600;color:#295738;" data-bs-toggle="tab" href="#boardedTab">Boarded (${boarded.length})</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" style="font-size:12px;font-weight:500;color:#6b7280;" data-bs-toggle="tab" href="#droppedTab">Dropped (${droppedOff.length})</a>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="boardedTab">${boardedHtml}</div>
            <div class="tab-pane fade" id="droppedTab">${droppedHtml}</div>
        </div>
    `;

        if (hasCoords) {
            const checkModalVisible = function () {
                const modalEl = document.getElementById('busPassengerModal');
                if (modalEl && modalEl.classList.contains('show')) {
                    if (activeProvider === 'google' && !googleMapsBlacklisted && typeof google !== 'undefined' && google.maps) {
                        initModalMapGoogle(busId, lat, lng, data);
                    } else {
                        initModalMapLeaflet(busId, lat, lng, data);
                    }
                } else {
                    setTimeout(checkModalVisible, 100);
                }
            };
            setTimeout(checkModalVisible, 250);
        }

        const closeHandler = function () {
            if (modal._trackInterval) {
                clearInterval(modal._trackInterval);
                modal._trackInterval = null;
            }
            if (modal._leafletModalMap) {
                try { modal._leafletModalMap.remove(); } catch (e) {}
                modal._leafletModalMap = null;
            }
            modal._modalBusData = null;
            modal.removeEventListener('hidden.bs.modal', closeHandler);
        };
        modal.addEventListener('hidden.bs.modal', closeHandler);
    }

    function initModalMapGoogle(busId, lat, lng, data) {
        const modal = document.getElementById('busPassengerModal');
        const container = document.getElementById('modalMap');
        if (!container || !modal) return;

        const pos = { lat, lng };
        const map = new google.maps.Map(container, {
            center: pos,
            zoom: 16,
            streetViewControl: false,
            mapTypeControl: false,
            fullscreenControl: false,
            styles: [
            { elementType: 'geometry', stylers: [{ color: '#fef8f3' }] },
            { elementType: 'labels.text.fill', stylers: [{ color: '#2e4836' }] },
            { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#cdd9b5' }] }
            ]
        });

        const marker = new google.maps.Marker({
            position: pos,
            map: map,
            title: 'Bus ' + busId,
            optimized: false,
            icon: {
                url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(getModalBusSvg()),
                scaledSize: new google.maps.Size(40, 36),
                anchor: new google.maps.Point(20, 28)
            }
        });

        const bpInfo = new google.maps.InfoWindow();
        const bpCoords = data.boarding_point_coords || {};
        const bpStats = (data.stats && data.stats.boarding_points) || {};
        Object.keys(bpCoords).forEach(function (name) {
            const c = bpCoords[name];
            const count = bpStats[name] || 0;
            const bpMarker = new google.maps.Marker({
                position: { lat: c.lat, lng: c.lng },
                map: map,
                title: name,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 6,
                    fillColor: '#e67e22',
                    fillOpacity: 1,
                    strokeColor: '#fff',
                    strokeWeight: 1.5
                }
            });
            bpMarker.addListener('click', function () {
                bpInfo.setContent('<div style="font-family:Poppins,sans-serif;padding:4px;font-size:11px;font-weight:600;color:#295738;">' + escapeHtml(name) + ' (' + count + ')</div>');
                bpInfo.open(map, bpMarker);
            });
        });

        google.maps.event.addListenerOnce(map, 'idle', function () {
            google.maps.event.trigger(map, 'resize');
        });

        if (modal._trackInterval) clearInterval(modal._trackInterval);
        modal._trackInterval = setInterval(function () {
            const live = trips.find(t => t.id === busId);
            if (live && live.latitude && live.longitude) {
                const p = { lat: live.latitude, lng: live.longitude };
                marker.setPosition(p);
                map.panTo(p);
            }
        }, 2500);
    }

    function initModalMapLeaflet(busId, lat, lng, data) {
        const modal = document.getElementById('busPassengerModal');
        const container = document.getElementById('modalMap');
        if (!container || !modal) return;

        if (container._leaflet_id) delete container._leaflet_id;
        if (modal._leafletModalMap) {
            try { modal._leafletModalMap.remove(); } catch (e) {}
            modal._leafletModalMap = null;
        }
        container.innerHTML = '';

        const modalMap = L.map(container, {
            center: [lat, lng],
            zoom: 16,
            zoomControl: false,
            attributionControl: true
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            subdomains: ['a', 'b', 'c'],
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'
        }).addTo(modalMap);

        const busIcon = L.divIcon({
            className: 'leaflet-bus-icon',
            html: getModalBusSvg(),
            iconSize: [40, 36],
            iconAnchor: [20, 28]
        });

        const marker = L.marker([lat, lng], { icon: busIcon, zIndexOffset: 1000 }).addTo(modalMap);

        const bpCoords = data.boarding_point_coords || {};
        const bpStats = (data.stats && data.stats.boarding_points) || {};
        Object.keys(bpCoords).forEach(function (name) {
            const c = bpCoords[name];
            const count = bpStats[name] || 0;
            const bpMarker = L.circleMarker([c.lat, c.lng], {
                radius: 6,
                fillColor: '#e67e22',
                fillOpacity: 1,
                color: '#fff',
                weight: 1.5
            }).addTo(modalMap);

            bpMarker.bindPopup(
            '<div style="font-family:Poppins,sans-serif;padding:4px;font-size:11px;font-weight:600;color:#295738;">' +
            escapeHtml(name) + ' (' + count + ')</div>'
            );
        });

        setTimeout(() => {
            if (modalMap) modalMap.invalidateSize();
        }, 150);

        modal._leafletModalMap = modalMap;

        if (modal._trackInterval) clearInterval(modal._trackInterval);
        modal._trackInterval = setInterval(function () {
            const live = trips.find(t => t.id === busId);
            if (live && live.latitude && live.longitude) {
                const p = [live.latitude, live.longitude];
                marker.setLatLng(p);
                modalMap.panTo(p);
            }
        }, 2500);
    }

    function handleOpenTripParam() {
        const openTripParam = new URLSearchParams(window.location.search).get('open_trip');
        if (openTripParam) {
            const matchedTrip = trips.find(t => String(t.tripId) === String(openTripParam));
            if (matchedTrip) {
                selectBus(matchedTrip.id);
                openBusModal(
                matchedTrip.id,
                matchedTrip.tripId,
                matchedTrip.latitude,
                matchedTrip.longitude
                );
            }
        }
    }

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        initDashboardMap();
    } else {
        document.addEventListener('DOMContentLoaded', initDashboardMap);
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>