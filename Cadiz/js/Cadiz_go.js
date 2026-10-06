(function () {
    var toast = document.getElementById('toastMsg'), toastTimer = null;
    function showToast(msg, duration) {
        toast.textContent = msg;
        toast.setAttribute('role', 'status');
        toast.style.opacity = '1';
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toast.style.opacity = '0'; }, duration || 2500);
    }
    function ratingLabel(p) {
        if (!p || !p.rating || p.rating <= 0) return 'No ratings yet';
        var filled = Math.round(p.rating);
        var stars = Array(5).fill('☆');
        for (var i = 0; i < filled; i++) stars[i] = '★';
        return stars.join('');
    }

    // ── Places hidden from the guide ──
    // These come from the landmarks database (landmarks_api.php), not from
    // this file, so they're filtered out here rather than deleted server-side.
    var HIDDEN_PLACE_NAMES = ['patag beach', 'aluyan beach', 'san diego cathedral', 'hilantagaan island view'];
    function isHiddenPlace(name) {
        var n = (name || '').trim().toLowerCase();
        return HIDDEN_PLACE_NAMES.indexOf(n) !== -1;
    }
    function filterHiddenLandmarks(landmarks) {
        return (landmarks || []).filter(function (lm) { return !isHiddenPlace(lm.name); });
    }
    var drawer = document.getElementById('navDrawer'), scrim = document.getElementById('navScrim');
    function setDrawer(open) {
        if (!drawer) return;
        drawer.classList.toggle('open', open);
        scrim.classList.toggle('open', open);
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.querySelectorAll('.menu-toggle').forEach(function (b) {
            b.setAttribute('aria-expanded', open ? 'true' : 'false');
            b.classList.toggle('active', open);
        });
        if (open) { var first = drawer.querySelector('.drawer-item'); if (first) first.focus(); }
    }
    document.querySelectorAll('.menu-toggle').forEach(function (b) { b.addEventListener('click', function () { setDrawer(!drawer.classList.contains('open')); }); });
    if (scrim) scrim.addEventListener('click', function () { setDrawer(false); });
    var screens = {
        home: document.getElementById('home-screen'),
        map: document.getElementById('map-screen'),
        profile: document.getElementById('profile-screen'),
        category: document.getElementById('category-screen')
    };
    function switchScreen(id) {
        Object.values(screens).forEach(function (s) {
            if (s) { s.classList.add('hidden'); s.classList.remove('active'); }
        });
        if (screens[id]) {
            screens[id].classList.remove('hidden');
            setTimeout(function () { screens[id].classList.add('active'); }, 20);
            if (id === 'map') setTimeout(function () { initMap(); setTimeout(getUserLocation, 400); }, 150);
        }
        try { sessionStorage.setItem('cadizgo_current_screen', id); } catch (e) {}
        setDrawer(false);
        document.querySelectorAll('.drawer-item').forEach(function (n) {
            n.classList.toggle('active', n.dataset.screen === id);
        });
    }
    function restoreSavedScreen() {
        try {
            var savedScreen = sessionStorage.getItem('cadizgo_current_screen');
            if (savedScreen && screens[savedScreen]) {
                switchScreen(savedScreen);
                return;
            }
        } catch (e) {}
        switchScreen('home');
    }
    document.querySelectorAll('.drawer-item').forEach(function (btn) {
        btn.addEventListener('click', function () { switchScreen(btn.dataset.screen); });
    });
    document.getElementById('back-to-home')?.addEventListener('click', function () { switchScreen('home'); });
    document.getElementById('back-from-category')?.addEventListener('click', function () { switchScreen('home'); });
    document.getElementById('seeAllMap')?.addEventListener('click', function (e) { e.preventDefault(); switchScreen('map'); });
    restoreSavedScreen();
    document.getElementById('notification-btn')?.addEventListener('click', function () { showToast('View your device location on the map.'); });
    document.getElementById('voiceMock')?.addEventListener('click', function () { showToast('Voice search is not available yet.'); });

    var attractionsMap = {};
    var currentModalId = null;
    var imageModal = document.getElementById('imageModal');
    function showImageModal(id) {
        var p = attractionsMap[id];
        if (!p) return;
        var image = document.getElementById('modalImage');
        if (image) {
            var imageUrl = safeLandmarkImage(p.image);
            image.style.backgroundImage = imageUrl ? 'url("' + imageUrl + '")' : '';
            image.style.display = imageUrl ? 'block' : 'none';
        }
        document.getElementById('modalTitle').textContent = p.name;
        document.getElementById('modalStars').textContent = ratingLabel(p);
        document.getElementById('modalDesc').innerText = p.desc;
        document.getElementById('modalDistance').textContent = p.distance + ' from Cadiz City Plaza';
        currentModalId = id;
        imageModal.classList.add('active');
    }
    document.getElementById('closeImageModal')?.addEventListener('click', function () { imageModal?.classList.remove('active'); });
    imageModal?.addEventListener('click', function (e) { if (e.target === imageModal) imageModal?.classList.remove('active'); });

    var map = null;
    var userLatLng = { lat: 10.9504, lng: 123.3081 };
    window.__cadizgoUserLatLng = userLatLng;
    var gpsDotMarker = null;
    var gpsAccuracyCircle = null;
    var gpsWatchId = null;
    var gpsLocated = false;
    var gpsStatusBar = document.getElementById('gpsStatusBar');
    var gpsStatusText = document.getElementById('gpsStatusText');

    var activeRouting = null, currentAnimatedLine = null;
    function enableMapDrag() {
        if (map && map.dragging) {
            map.dragging.enable();
        }
    }
    function stopAnimation() {
        if (currentAnimatedLine && map) {
            map.removeLayer(currentAnimatedLine);
            currentAnimatedLine = null;
        }
    }

    function createRoutePolyline(coords) {
        if (!coords || coords.length < 2) return null;

        // Layer 1: Outer glowing blue aura
        var glowLine = L.polyline(coords, {
            color: '#0043a8',
            weight: 10,
            opacity: 0.7,
            lineCap: 'round',
            lineJoin: 'round',
            className: 'animated-route-glow'
        });

        // Layer 2: Core solid electric blue line
        var mainLine = L.polyline(coords, {
            color: '#1d4ed8',
            weight: 6,
            opacity: 0.95,
            lineCap: 'round',
            lineJoin: 'round'
        });

        // Layer 3: Moving cyan marching dash overlay
        var dashLine = L.polyline(coords, {
            color: '#38bdf8',
            weight: 4,
            opacity: 0.95,
            lineCap: 'round',
            lineJoin: 'round',
            className: 'animated-route-flow'
        });

        // Layer 4: High-speed inner white core dash
        var coreLine = L.polyline(coords, {
            color: '#ffffff',
            weight: 2,
            opacity: 0.9,
            lineCap: 'round',
            lineJoin: 'round',
            className: 'animated-route-core'
        });

        var routeGroup = L.featureGroup([glowLine, mainLine, dashLine, coreLine]).addTo(map);

        return routeGroup;
    }
    var activeRouteStateKey = 'cadizgo_active_route_state';
    function saveRouteState(start, dest, name) {
        try {
            sessionStorage.setItem(activeRouteStateKey, JSON.stringify({
                start: { lat: Number(start.lat), lng: Number(start.lng) },
                dest: { lat: Number(dest.lat), lng: Number(dest.lng) },
                name: String(name || 'Destination')
            }));
        } catch (err) { }
    }
    function clearSavedRouteState() {
        try { sessionStorage.removeItem(activeRouteStateKey); } catch (err) { }
    }
    function setClearRouteButtonVisible(visible) {
        var button = document.getElementById('clear-route-btn');
        if (button) button.style.display = visible ? 'block' : 'none';
    }
    function restoreSavedRouteState() {
        if (!map) return;
        try {
            var raw = sessionStorage.getItem(activeRouteStateKey);
            if (!raw) return;
            var state = JSON.parse(raw);
            if (!state || !state.start || !state.dest) return;
            if (Number.isFinite(state.start.lat) && Number.isFinite(state.start.lng)
                && Number.isFinite(state.dest.lat) && Number.isFinite(state.dest.lng)) {
                showAnimatedRoute(state.start, state.dest, state.name || 'Destination');
            }
        } catch (err) { }
    }
    function clearRoute() {
        if (activeRouting && map) { map.removeControl(activeRouting); activeRouting = null; }
        stopAnimation();
        clearSavedRouteState();
        enableMapDrag();
        setClearRouteButtonVisible(false);
        showToast('Route cleared.');
    }
    function showAnimatedRoute(start, dest, name) {
        if (!map) { initMap(); setTimeout(function () { showAnimatedRoute(start, dest, name); }, 200); return; }
        clearRoute();
        activeRouting = L.Routing.control({
            waypoints: [L.latLng(start.lat, start.lng), L.latLng(dest.lat, dest.lng)],
            routeWhileDragging: true,
            lineOptions: { styles: [{ color: 'transparent', weight: 0 }] },
            show: false,
            addWaypoints: false,
            fitSelectedRoutes: false,
            draggableWaypoints: true
        }).addTo(map);
        activeRouting.on('routesfound', function (e) {
            var route = e.routes[0];
            if (route && route.coordinates) {
                stopAnimation();
                var latlngs = route.coordinates.map(function (c) { return L.latLng(c.lat, c.lng); });
                currentAnimatedLine = createRoutePolyline(latlngs);
                var waypoints = activeRouting.getWaypoints();
                var routeStart = waypoints[0].latLng;
                var routeDest = waypoints[waypoints.length - 1].latLng;
                saveRouteState(routeStart, routeDest, name);
                setClearRouteButtonVisible(true);
                showToast('Route to ' + name + ': ' + (route.summary.totalDistance / 1000).toFixed(1) + ' km');
            } else {
                showToast('No road route to ' + name);
            }
        });
        activeRouting.on('routingerror', function () {
            showToast('Cannot calculate route to ' + name);
            setClearRouteButtonVisible(false);
            enableMapDrag();
        });
    }
    function navigateToDest(lat, lng, name) {
        if (!map) { initMap(); setTimeout(function () { navigateToDest(lat, lng, name); }, 200); return; }
        showAnimatedRoute(userLatLng, { lat: lat, lng: lng }, name);
    }
    window.navigateToDest = navigateToDest;

    /* ── GPS Blue Dot (Google Maps style) ─────────────────────────────── */
    function showGpsStatus(msg, autohide) {
        if (!gpsStatusBar) return;
        if (gpsStatusText) gpsStatusText.textContent = msg;
        gpsStatusBar.style.display = 'block';
        if (autohide) setTimeout(function () { gpsStatusBar.style.display = 'none'; }, 3000);
    }

    function placeGpsDot(latlng, accuracy) {
        if (!map) return;
        var ll = L.latLng(latlng);

        if (gpsAccuracyCircle) { map.removeLayer(gpsAccuracyCircle); gpsAccuracyCircle = null; }
        if (gpsDotMarker) { map.removeLayer(gpsDotMarker); gpsDotMarker = null; }

        if (accuracy && accuracy < 5000) {
            gpsAccuracyCircle = L.circle(ll, {
                radius: accuracy,
                color: '#4285f4',
                fillColor: '#4285f4',
                fillOpacity: 0.10,
                weight: 1.5,
                opacity: 0.5
            }).addTo(map);
        }

        var currentDeviceHeading = window.__currentDeviceHeading || null;

        function updateHeadingConeUI(headingDeg) {
            if (headingDeg === null || headingDeg === undefined || isNaN(headingDeg)) return;
            window.__currentDeviceHeading = headingDeg;
            var coneEl = document.getElementById('gpsHeadingCone');
            if (coneEl) {
                coneEl.style.transform = 'rotate(' + Math.round(headingDeg) + 'deg)';
                coneEl.style.display = 'block';
            }
        }

        if (!window.__headingListenerAttached) {
            window.__headingListenerAttached = true;
            if ('ondeviceorientationabsolute' in window) {
                window.addEventListener('deviceorientationabsolute', function (e) {
                    if (e.alpha !== null) updateHeadingConeUI(360 - e.alpha);
                }, true);
            } else if ('ondeviceorientation' in window) {
                window.addEventListener('deviceorientation', function (e) {
                    if (e.webkitCompassHeading) {
                        updateHeadingConeUI(e.webkitCompassHeading);
                    } else if (e.alpha !== null) {
                        updateHeadingConeUI(360 - e.alpha);
                    }
                }, true);
            }
        }

        var dev = (window.getDeviceDetails ? window.getDeviceDetails() : (window.__cadizDeviceDetails || { name: 'Device', os: 'Mobile' }));

        var headingTransform = (currentDeviceHeading !== null) ? 'style="transform:rotate(' + Math.round(currentDeviceHeading) + 'deg);display:block;"' : 'style="display:none;"';

        var dotHtml = '<div class="gps-dot-wrapper" title="' + dev.name + '">'
            + '<div class="gps-heading-cone" id="gpsHeadingCone" ' + headingTransform + '></div>'
            + '<div class="gps-accuracy-ring"></div>'
            + '<div class="gps-dot-halo"></div>'
            + '<div class="gps-dot-core"></div>'
            + '<div class="gps-dot-device-badge"></div>'
            + '</div>';

        gpsDotMarker = L.marker(ll, {
            icon: L.divIcon({
                html: dotHtml,
                iconSize: [52, 52],
                iconAnchor: [26, 26],
                className: ''
            }),
            zIndexOffset: 2000
        }).addTo(map);

        var coordEl = document.getElementById('coordText');
        if (coordEl) {
            coordEl.textContent = ll.lat.toFixed(5) + ', ' + ll.lng.toFixed(5);
        }

        var userNameStr = (window.userFullName && window.userFullName !== 'Guest') ? window.userFullName : 'Traveler';
        var defaultArea = 'Cadiz City (' + ll.lat.toFixed(4) + ', ' + ll.lng.toFixed(4) + ')';

        var popupHtml = '<div class="gps-device-popup text-center p-2" style="min-width:210px;">'
            + '<div class="d-flex align-items-center justify-content-center gap-2 mb-1" style="font-weight:700;font-size:0.95rem;color:#1a5fb4;">'
            + ''
            + '<span>' + dev.name + '</span>'
            + '</div>'
            + '<div class="small text-muted mb-1">' + userNameStr + ' &bull; ' + dev.os + '</div>'
            + '<div class="small text-dark fw-bold mb-2" id="gpsAreaDisplay"><span id="gpsAreaText">' + defaultArea + '</span></div>'
            + '<div class="badge badge-light border text-dark mb-2 py-1 px-2 d-inline-block" style="font-weight:500;">'
            + 'Accuracy: ' + (accuracy ? Math.round(accuracy) + ' m' : 'GPS Active')
            + '</div>'
            + '<div><button onclick="window.__cadizRecenterUser()" class="btn btn-sm btn-primary w-100 mt-1">Centre on my position</button></div>'
            + '</div>';

        gpsDotMarker.bindPopup(popupHtml);

        fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + ll.lat + '&lon=' + ll.lng + '&zoom=16')
            .then(function (r) { return r.json(); })
            .then(function (geo) {
                if (geo && geo.display_name) {
                    var parts = geo.display_name.split(',');
                    var areaStr = parts.slice(0, 3).join(',').trim();
                    var el = document.getElementById('gpsAreaText');
                    if (el) el.textContent = areaStr;
                    showGpsStatus(dev.name + ' located in ' + areaStr, true);
                }
            })
            .catch(function (err) { });
    }

    window.__cadizRecenterUser = function () {
        if (map && userLatLng) {
            map.flyTo([userLatLng.lat, userLatLng.lng], 17.5, { animate: true, duration: 1.0 });
            if (gpsDotMarker) gpsDotMarker.openPopup();
        }
    };

    // ── Battery & Dead Device Location Tracking ──────────────────────────────
    var deviceBatteryLevel = (typeof window.lastBatteryLevel !== 'undefined' && window.lastBatteryLevel !== null) ? window.lastBatteryLevel : null;
    var deviceBatteryStatus = (typeof window.lastDeviceStatus !== 'undefined' && window.lastDeviceStatus) ? window.lastDeviceStatus : 'online';

    function initBatteryMonitor() {
        if ('getBattery' in navigator) {
            navigator.getBattery().then(function (battery) {
                function updateBatteryInfo() {
                    var levelPct = Math.round(battery.level * 100);
                    deviceBatteryLevel = levelPct;
                    window.__deviceBatteryLevel = levelPct;

                    if (!battery.charging && levelPct <= 15) {
                        deviceBatteryStatus = 'low_battery';
                    } else if (!battery.charging && levelPct <= 5) {
                        deviceBatteryStatus = 'critical_battery';
                    } else {
                        deviceBatteryStatus = battery.charging ? 'charging' : 'online';
                    }

                    var statusEl = document.getElementById('gpsStatusText');
                    if (statusEl && gpsLocated) {
                        var dev = (window.getDeviceDetails ? window.getDeviceDetails() : (window.__cadizDeviceDetails || { name: 'Device' }));
                        statusEl.textContent = dev.name + ' located (' + levelPct + '%)';
                    }

                    if (levelPct <= 5 && userLatLng) {
                        sendLocationToServer(userLatLng.lat, userLatLng.lng, null, levelPct, 'critical_battery');
                    }
                }

                updateBatteryInfo();
                battery.addEventListener('levelchange', updateBatteryInfo);
                battery.addEventListener('chargingchange', updateBatteryInfo);
            }).catch(function (err) { });
        }
    }
    initBatteryMonitor();

    // ── Save last location before device dies or disconnects ──
    function saveLastPingBeforeUnload() {
        if (!gpsLocated || !userLatLng) return;
        var dev = (window.getDeviceDetails ? window.getDeviceDetails() : (window.__cadizDeviceDetails || { name: 'Device' }));
        var bat = deviceBatteryLevel;
        var stat = (bat !== null && bat <= 10) ? 'low_battery' : 'offline';

        var payloadStr = JSON.stringify({
            action: 'track_device_location',
            lat: userLatLng.lat,
            lng: userLatLng.lng,
            accuracy: null,
            device_info: dev.name + ' (' + (dev.os || '') + ')',
            battery_level: bat,
            status: stat
        });

        try {
            if (navigator.sendBeacon) {
                var blob = new Blob([payloadStr], { type: 'application/json' });
                navigator.sendBeacon('tracker_location.php?action=update', blob);
            } else {
                fetch('tracker_location.php?action=update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: payloadStr,
                    keepalive: true
                });
            }
        } catch (e) { }
    }
    window.addEventListener('beforeunload', saveLastPingBeforeUnload);
    window.addEventListener('pagehide', saveLastPingBeforeUnload);
    window.addEventListener('offline', function () {
        showToast('Device offline — saving last known location');
        saveLastPingBeforeUnload();
    });

    // ── Background Location Broadcasting (every 15s to server) ──────────
    var locationBroadcastInterval = null;
    var locationBroadcastActive = false;

    function sendLocationToServer(lat, lng, accuracy, batLevel, statusStr) {
        var loggedIn = (typeof window.isLoggedIn !== 'undefined') ? window.isLoggedIn : false;
        if (!loggedIn) return;

        var dev = (window.getDeviceDetails ? window.getDeviceDetails() : (window.__cadizDeviceDetails || { name: 'Device' }));
        var deviceInfo = (typeof window.__deviceInfo !== 'undefined') ? window.__deviceInfo : dev.name;
        var bat = (batLevel !== undefined && batLevel !== null) ? batLevel : deviceBatteryLevel;
        var stat = statusStr || deviceBatteryStatus || 'online';

        // Cache last ping locally for instant recovery if offline
        try {
            localStorage.setItem('cadizgo_last_known_ping', JSON.stringify({
                user_id: window.userId || null,
                lat: lat,
                lng: lng,
                accuracy: accuracy,
                battery_level: bat,
                status: stat,
                timestamp: new Date().toISOString(),
                device: deviceInfo
            }));
        } catch (e) { }

        fetch('tracker_location.php?action=update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                lat: lat,
                lng: lng,
                accuracy: accuracy || null,
                device_info: String(deviceInfo).substring(0, 200),
                battery_level: bat,
                status: stat
            })
        })
            .then(function (r) {
                if (!r.ok) throw new Error('Location save failed (' + r.status + ')');
                return r.json();
            })
            .then(function (data) {
                if (!data.success) throw new Error(data.message || 'Location save failed');
            })
            .catch(function (err) {
                console.error('Could not save device location:', err);
                showGpsStatus('GPS is active, but the last location could not be saved.', true);
            });
    }

    function startLocationBroadcast(lat, lng, accuracy) {
        var loggedIn = (typeof window.isLoggedIn !== 'undefined') ? window.isLoggedIn : false;
        if (!locationBroadcastActive && loggedIn) {
            sendLocationToServer(lat, lng, accuracy);
            locationBroadcastInterval = setInterval(function () {
                sendLocationToServer(userLatLng.lat, userLatLng.lng, null);
            }, 15000);
            locationBroadcastActive = true;
        }
    }

    function stopLocationBroadcast() {
        if (locationBroadcastInterval) {
            clearInterval(locationBroadcastInterval);
            locationBroadcastInterval = null;
        }
        locationBroadcastActive = false;
    }

    window.__updateMapUserLocation = function (lat, lng, accuracy) {
        userLatLng.lat = lat;
        userLatLng.lng = lng;
        window.__cadizgoUserLatLng.lat = lat;
        window.__cadizgoUserLatLng.lng = lng;
        if (map) {
            placeGpsDot([lat, lng], accuracy || 50);
        }
        var loggedIn = (typeof window.isLoggedIn !== 'undefined') ? window.isLoggedIn : false;
        if (loggedIn) {
            sendLocationToServer(lat, lng, accuracy);
        }
    };

    function startLiveGPS() {
        var dev = (window.getDeviceDetails ? window.getDeviceDetails() : (window.__cadizDeviceDetails || { name: 'Device' }));
        if (!('geolocation' in navigator)) {
            var message = window.isSecureContext === false
                ? 'Location access requires HTTPS (or localhost).'
                : 'Location services are not available in this browser.';
            showGpsStatus(message, true);
            showToast(message);
            return;
        }
        if (gpsWatchId !== null) return;
        showGpsStatus('Acquiring ' + dev.name + ' location…');

        gpsWatchId = navigator.geolocation.watchPosition(
            function (pos) {
                var lat = pos.coords.latitude;
                var lng = pos.coords.longitude;
                var acc = pos.coords.accuracy;
                var heading = pos.coords.heading;

                if (heading !== null && heading !== undefined && !isNaN(heading)) {
                    if (window.__currentDeviceHeading === undefined || window.__currentDeviceHeading === null) {
                        window.__currentDeviceHeading = heading;
                    }
                }

                userLatLng.lat = lat;
                userLatLng.lng = lng;
                window.__cadizgoUserLatLng.lat = lat;
                window.__cadizgoUserLatLng.lng = lng;
                startLocationBroadcast(lat, lng, acc);
                if (map) {
                    placeGpsDot([lat, lng], acc);
                    if (!gpsLocated) {
                        map.flyTo([lat, lng], 17.5, { animate: true, duration: 1.2 });
                        if (gpsDotMarker) gpsDotMarker.openPopup();
                        showGpsStatus('Located ' + dev.name + ' · ' + Math.round(acc) + ' m accuracy', true);
                        showToast(dev.name + ' located on the map.');
                        gpsLocated = true;
                    }
                }
            },
            function (err) {
                console.warn('GPS error:', err.message);
                var message = 'Could not get ' + dev.name + ' location.';
                if (err.code === 1) {
                    message = 'Allow location access in your browser to locate this device.';
                } else if (!window.isSecureContext) {
                    message = 'Location access requires HTTPS (or localhost).';
                } else if (err.code === 3) {
                    message = 'Location request timed out. Check GPS and try again.';
                } else if (err.code === 2) {
                    message = 'Location is unavailable. Check GPS and try again.';
                }
                showGpsStatus(message, true);
                showToast(message);
            },
            { enableHighAccuracy: true, maximumAge: 1000, timeout: 15000 }
        );
    }

    function getUserLocation() {
        if (!map) return;
        var dev = (window.getDeviceDetails ? window.getDeviceDetails() : (window.__cadizDeviceDetails || { name: 'Device' }));

        if (gpsLocated) {
            map.flyTo([userLatLng.lat, userLatLng.lng], 17.5, { animate: true, duration: 1.0 });
            if (gpsDotMarker) gpsDotMarker.openPopup();
            showGpsStatus('Centred on your ' + dev.name, true);
            showToast('Centred on ' + dev.name);
            return;
        }

        startLiveGPS();
    }

    function initMap() {
        if (map) return;
        map = L.map('map', {
            zoomControl: false,
            dragging: true,
            touchZoom: true,
            doubleClickZoom: true,
            scrollWheelZoom: true,
            zoomAnimation: true,
            fadeAnimation: true,
            attributionControl: true
        }).setView([10.9504, 123.3081], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors' }).addTo(map);
        map.dragging.enable();
        map.touchZoom.enable();
        map.doubleClickZoom.enable();
        map.scrollWheelZoom.enable();

        var coordTextEl = document.getElementById('coordText');
        function updateCoordDisplay() {
            var c = map.getCenter();
            if (coordTextEl) coordTextEl.textContent = c.lat.toFixed(5) + ', ' + c.lng.toFixed(5);
        }
        map.on('move', updateCoordDisplay);
        updateCoordDisplay();

        // Default zoom control removed in favor of custom buttons

        var zoomInBtn = document.getElementById('zoom-in');
        if (zoomInBtn) {
            zoomInBtn.addEventListener('click', function () {
                map.zoomIn();
            });
        }

        var zoomOutBtn = document.getElementById('zoom-out');
        if (zoomOutBtn) {
            zoomOutBtn.addEventListener('click', function () {
                map.zoomOut();
            });
        }

        map.on('click', function (e) {
            var lat = e.latlng.lat, lng = e.latlng.lng;
            showToast(lat.toFixed(5) + ', ' + lng.toFixed(5));
        });

        var locateBtn = document.getElementById('locate-me');
        if (locateBtn) {
            locateBtn.addEventListener('click', function () {
                getUserLocation();
            });
        }

        // ── Dead / Disconnected Device Tracking Handler ─────────────────
        var deadDeviceMarker = null;

        function showDeadDeviceMarkerOnMap(lat, lng, batLevel, timeStr, devName, addressStr) {
            if (!map) return;
            if (deadDeviceMarker) { map.removeLayer(deadDeviceMarker); deadDeviceMarker = null; }

            var dev = (window.getDeviceDetails ? window.getDeviceDetails() : (window.__cadizDeviceDetails || { name: 'Device' }));
            var dName = devName || dev.name;
            var batText = (batLevel !== null && batLevel !== undefined) ? (Math.round(batLevel) + '% Battery') : 'Powered off';

            var deadHtml = '<div class="dead-device-marker-pin" title="Last Known Position of ' + dName + '">'
                + '<div class="dead-device-pulse-ring"></div>'
                + '<div class="dead-device-pin-core">'
                + ''
                + '</div>'
                + '<div class="dead-device-badge">Last seen</div>'
                + '</div>';

            deadDeviceMarker = L.marker([lat, lng], {
                icon: L.divIcon({
                    html: deadHtml,
                    iconSize: [48, 48],
                    iconAnchor: [24, 24],
                    className: ''
                }),
                zIndexOffset: 1900
            }).addTo(map);

            var popupHtml = '<div class="dead-device-popup text-center p-2" style="min-width:220px;">'
                + '<div class="fw-bold text-danger mb-1" style="font-size:0.95rem;">'
                + 'Last known position'
                + '</div>'
                + '<div class="small fw-semibold text-dark mb-1">' + dName + '</div>'
                + '<div class="badge badge-danger mb-2 py-1 px-2">' + batText + '</div>'
                + '<div class="small text-muted mb-1">' + (timeStr || 'Last Active') + '</div>'
                + '<div class="small text-dark fw-bold mb-2">' + (addressStr || (lat.toFixed(4) + ', ' + lng.toFixed(4))) + '</div>'
                + '<div><button onclick="window.navigateToDest(' + lat + ',' + lng + ',\'Last Spot of ' + dName.replace(/'/g, "\\'") + '\')" class="btn btn-sm btn-outline-danger w-100 mt-1">Show route to last position</button></div>'
                + '</div>';

            deadDeviceMarker.bindPopup(popupHtml);
            map.flyTo([lat, lng], 17, { animate: true, duration: 1.2 });
            setTimeout(function () { if (deadDeviceMarker) deadDeviceMarker.openPopup(); }, 1200);
        }

        function locateDeadDevice() {
            var dev = (window.getDeviceDetails ? window.getDeviceDetails() : (window.__cadizDeviceDetails || { name: 'Device' }));
            var modal = document.getElementById('deadDeviceModal');
            showToast('Fetching last known location…');

            function readLocalLocation() {
                try {
                    var local = localStorage.getItem('cadizgo_last_known_ping');
                    var location = local ? JSON.parse(local) : null;
                    return location && Number(location.user_id) === Number(window.userId)
                        ? location : null;
                } catch (err) {
                    console.warn('Could not read saved device location:', err);
                    return null;
                }
            }

            function hasValidCoordinates(location) {
                var lat = Number(location && location.lat);
                var lng = Number(location && location.lng);
                return Number.isFinite(lat) && Number.isFinite(lng)
                    && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
            }

            function showLastKnownLocation(locData) {
                if (!hasValidCoordinates(locData)) {
                    showToast('No saved GPS location yet. Open the map on the phone and allow location access.');
                    return;
                }

                var lat = Number(locData.lat);
                var lng = Number(locData.lng);
                var bat = locData.battery_level !== null && locData.battery_level !== undefined
                    ? Math.round(locData.battery_level) + '%' : 'Unknown';
                var timeStr = locData.created_at || locData.timestamp || 'Time unavailable';

                var nameEl = document.getElementById('deadDeviceName');
                if (nameEl) nameEl.textContent = dev.name;
                var batEl = document.getElementById('deadDeviceBattery');
                if (batEl) batEl.textContent = bat;
                var timeEl = document.getElementById('deadDeviceTime');
                if (timeEl) timeEl.textContent = timeStr;
                var coordsEl = document.getElementById('deadDeviceCoords');
                if (coordsEl) coordsEl.textContent = lat.toFixed(5) + ', ' + lng.toFixed(5);

                var coordsAddress = lat.toFixed(4) + ', ' + lng.toFixed(4);
                var addressEl = document.getElementById('deadDeviceAddress');
                if (addressEl) addressEl.textContent = coordsAddress;
                window.__currentDeadDeviceSpot = { lat: lat, lng: lng, bat: locData.battery_level, time: timeStr, name: dev.name, addr: coordsAddress };
                if (modal) modal.classList.add('active');

                fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng + '&zoom=16')
                    .then(function (r) { return r.json(); })
                    .then(function (geo) {
                        if (geo && geo.display_name) {
                            var addr = geo.display_name.split(',').slice(0, 3).join(', ');
                            if (addressEl) addressEl.textContent = addr;
                            window.__currentDeadDeviceSpot.addr = addr;
                        }
                    })
                    .catch(function (err) {
                        console.warn('Could not resolve last known location address:', err);
                    });
            }

            fetch('tracker_location.php?action=get_last_known_location')
                .then(function (r) {
                    if (!r.ok) throw new Error('Location request failed (' + r.status + ')');
                    return r.json();
                })
                .then(function (res) {
                    var locData = res.success && res.location ? res.location : readLocalLocation();
                    if (!res.success || !res.location) {
                        showToast('Using this phone’s saved last location; it may be out of date.');
                    }
                    showLastKnownLocation(locData);
                })
                .catch(function (err) {
                    console.warn('Could not fetch last known location:', err);
                    var locData = readLocalLocation();
                    if (hasValidCoordinates(locData)) {
                        showToast('Server unavailable. Showing this phone’s saved last location; it may be out of date.');
                    } else {
                        showToast('Could not fetch a saved location. Open the map on the phone and allow location access.');
                    }
                    showLastKnownLocation(locData);
                });
        }

        var locateDeadBtn = document.getElementById('locate-dead-device');
        if (locateDeadBtn) {
            locateDeadBtn.addEventListener('click', function () {
                locateDeadDevice();
            });
        }

        var closeDeadModalBtn = document.getElementById('closeDeadDeviceModal');
        if (closeDeadModalBtn) {
            closeDeadModalBtn.addEventListener('click', function () {
                document.getElementById('deadDeviceModal')?.classList.remove('active');
            });
        }

        var focusDeadBtn = document.getElementById('focusDeadDeviceBtn');
        if (focusDeadBtn) {
            focusDeadBtn.addEventListener('click', function () {
                document.getElementById('deadDeviceModal')?.classList.remove('active');
                if (window.__currentDeadDeviceSpot) {
                    var spot = window.__currentDeadDeviceSpot;
                    showDeadDeviceMarkerOnMap(spot.lat, spot.lng, spot.bat, spot.time, spot.name, spot.addr);
                    showToast('Map centred on the last known location.');
                }
            });
        }
        document.getElementById('map-layers').addEventListener('click', function () {
            showToast('Drag to pan, pinch or scroll to zoom. Select a landmark to get a route.');
        });

        // ── Clear Route Button ──
        var clearRouteBtn = document.getElementById('clear-route-btn');
        if (clearRouteBtn) {
            clearRouteBtn.addEventListener('click', function () {
                clearRoute();
                clearRouteBtn.style.display = 'none';
            });
        }

        // Load landmark markers from DB
        var typeColors = {
            historical: '#ea4335',
            beach: '#3aa0ff',
            beaches: '#3aa0ff',
            food: '#ffb020',
            shopping: '#9a5cf6',
            hotel: '#2dc26b'
        };

        fetch('landmarks_api.php?action=list')
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    filterHiddenLandmarks(data.landmarks).forEach(function (p) {
                        var col = typeColors[p.category] || '#1a5fb4';

                        var marker = L.marker([p.lat, p.lng], {
                            icon: L.divIcon({
                                html: '<div class="landmark-marker" style="background:' + col + '"></div>',
                                iconSize: [22, 22],
                                popupAnchor: [0, -10]
                            })
                        }).addTo(map);

                        var photoUrl = safeLandmarkImage(p.photo);
                        var photoHtml = photoUrl
                            ? '<img class="landmark-popup-photo" src="' + escapeHtml(photoUrl) + '" alt="' + escapeHtml(p.name) + '" loading="lazy">'
                            : '';
                        marker.bindPopup('<div class="text-center">' + photoHtml + '<b>' + escapeHtml(p.name) + '</b><br>' + escapeHtml(p.description || '') + '<br><button onclick="window.navigateToDest(' + p.lat + ', ' + p.lng + ', ' + escapeHtml(JSON.stringify(p.name)) + ')" class="btn btn-primary btn-sm mt-2 w-100">Animated Route</button></div>');
                    });
                }
            })
            .catch(function (err) {
                console.error('Error loading landmarks:', err);
            });

        startLiveGPS();
        restoreSavedRouteState();
    }

    // ===== Category Cards =====
    var categoryDetails = {
        historical: { title: 'Historical', subtitle: 'Landmarks and heritage' },
        food: { title: 'Food & Drink', subtitle: 'Restaurants and cafes' },
        beaches: { title: 'Beaches', subtitle: 'Coast and islands' },
        shopping: { title: 'Shopping', subtitle: 'Markets and malls' },
        hotel: { title: 'Hotel', subtitle: 'Hotels and stays' }
    };
    var categoryRequestId = 0;

    function loadCategoryPlaces(category, container, title) {
        var requestId = ++categoryRequestId;
        container.innerHTML = '<div class="empty-state" role="status">Loading places…</div>';

        fetch('landmarks_api.php?action=list')
            .then(function (res) {
                if (!res.ok) throw new Error('Could not load landmarks.');
                return res.json();
            })
            .then(function (data) {
                if (requestId !== categoryRequestId) return;
                if (!data.success) throw new Error(data.message || 'Could not load landmarks.');

                var places = filterHiddenLandmarks(data.landmarks).filter(function (place) {
                    return String(place.category || '').toLowerCase() === category;
                });
                if (!places.length) {
                    container.innerHTML = '<div class="empty-state"><strong>No places yet</strong>There are no listed places in ' + escapeHtml(title) + ' yet.</div>';
                    return;
                }

                var plazaLat = 10.9504, plazaLng = 123.3081;
                function distanceFromPlaza(place) {
                    var radians = Math.PI / 180;
                    var dLat = (place.lat - plazaLat) * radians;
                    var dLng = (place.lng - plazaLng) * radians;
                    var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                        Math.cos(plazaLat * radians) * Math.cos(place.lat * radians) *
                        Math.sin(dLng / 2) * Math.sin(dLng / 2);
                    var distance = 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                    return distance < 1 ? Math.round(distance * 1000) + ' m' : distance.toFixed(1) + ' km';
                }

                container.innerHTML = places.map(function (place) {
                    var photoUrl = safeLandmarkImage(place.photo);
                    var photoHtml = photoUrl
                        ? '<img class="category-place-img" src="' + escapeHtml(photoUrl) + '" alt="' + escapeHtml(place.name) + '" loading="lazy">'
                        : '';
                    return '<div class="category-place-card">' + photoHtml + '<div class="category-place-info">' +
                        '<h5 class="text-primary mb-1">' + escapeHtml(place.name) + ' <span class="distance-text">' + distanceFromPlaza(place) + '</span></h5>' +
                        '<p class="small text-muted mb-2">' + escapeHtml(place.description || '') + '</p>' +
                        '<button type="button" class="btn-go-map" data-lat="' + Number(place.lat) + '" data-lng="' + Number(place.lng) + '" data-name="' + escapeHtml(place.name) + '">Show route</button>' +
                        '</div></div>';
                }).join('');

                container.querySelectorAll('.btn-go-map').forEach(function (btn) {
                    btn.addEventListener('click', function (e) {
                        e.stopPropagation();
                        var lat = parseFloat(btn.dataset.lat);
                        var lng = parseFloat(btn.dataset.lng);
                        var name = btn.dataset.name;
                        switchScreen('map');
                        setTimeout(function () { navigateToDest(lat, lng, name); }, 200);
                    });
                });
            })
            .catch(function (err) {
                if (requestId !== categoryRequestId) return;
                console.error('Error loading category places:', err);
                container.innerHTML = '<div class="empty-state"><strong>Places could not be loaded</strong>Please try again in a moment.</div>';
                showToast('Could not load places for this category.');
            });
    }

    document.querySelectorAll('.category-card').forEach(function (card) {
        card.addEventListener('click', function () {
            var cat = card.dataset.category;
            var data = categoryDetails[cat];
            if (!data) return;
            document.getElementById('detail-title').innerText = data.title;
            document.getElementById('detail-description').innerText = data.subtitle;
            document.getElementById('category-title').innerText = data.title;
            document.getElementById('category-subtitle').innerText = data.subtitle;
            var container = document.getElementById('category-places');
            loadCategoryPlaces(cat, container, data.title);
            switchScreen('category');
        });
    });

    // ===== Rating Modal =====
    var ratingModal = document.getElementById('ratingModal');
    var ratingStars = document.querySelectorAll('#ratingStarsContainer .rating-option');
    var currentRatingPlace = '';
    var selectedRating = 0;
    function updateRatingUI() {
        ratingStars.forEach(function (star, idx) {
            star.classList.toggle('selected', idx + 1 === selectedRating);
            star.setAttribute('aria-checked', idx + 1 === selectedRating ? 'true' : 'false');
        });
    }
    ratingStars.forEach(function (star) {
        star.addEventListener('mouseenter', function () {
            var val = parseInt(this.dataset.val);
            ratingStars.forEach(function (s, i) {
                if (i < val) s.classList.add('hover');
                else s.classList.remove('hover');
            });
        });
        star.addEventListener('mouseleave', function () {
            ratingStars.forEach(function (s) { s.classList.remove('hover'); });
            updateRatingUI();
        });
        star.addEventListener('click', function () {
            selectedRating = parseInt(this.dataset.val);
            updateRatingUI();
        });
    });
    function openRating(placeName) {
        currentRatingPlace = placeName;
        selectedRating = 0;
        document.getElementById('ratingPlaceLabel').innerText = placeName;
        document.getElementById('ratingComment').value = '';
        updateRatingUI();
        ratingModal.classList.add('active');
    }
    function closeRating() {
        ratingModal.classList.remove('active');
    }
    document.getElementById('ratingCancel').addEventListener('click', closeRating);
    document.getElementById('ratingSubmit').addEventListener('click', function () {
        if (selectedRating === 0) { showToast('Select a star rating.'); return; }
        var commentVal = document.getElementById('ratingComment').value.trim();
        var profileNameEl = document.querySelector('#profile-screen h5');
        var userName = profileNameEl ? profileNameEl.innerText.trim() : 'Alex Johnson';
        fetch('submit_rating.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                place_name: currentRatingPlace,
                rating: selectedRating,
                comment: commentVal,
                user_name: userName
            })
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    showToast('Rating submitted for ' + currentRatingPlace + '.');
                    closeRating();
                } else {
                    showToast('Error: ' + (data.message || 'Could not submit rating.'));
                }
            })
            .catch(function (err) {
                console.error(err);
                showToast('Network error submitting rating.');
            });
    });
    document.querySelectorAll('.feedback-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            openRating(btn.dataset.name);
        });
    });
    document.getElementById('modalRateBtn').addEventListener('click', function () {
        var title = document.getElementById('modalTitle').innerText;
        openRating(title);
        imageModal.classList.remove('active');
    });
    document.getElementById('modalRouteBtn').addEventListener('click', function () {
        if (currentModalId && attractionsMap[currentModalId]) {
            var p = attractionsMap[currentModalId];
            imageModal.classList.remove('active');
            switchScreen('map');
            setTimeout(function () { navigateToDest(p.lat, p.lng, p.name); }, 300);
        }
    });

    // ===== Draggable Chatbot =====
    var draggable = document.getElementById('draggableChatbot');
    var isDragging = false, startX, startY, initialLeft, initialBottom;
    function getComputedPosition() {
        var rect = draggable.getBoundingClientRect();
        var parentRect = draggable.parentElement.getBoundingClientRect();
        return { left: rect.left - parentRect.left, bottom: parentRect.bottom - rect.bottom };
    }
    draggable.addEventListener('mousedown', function (e) {
        if (e.target.closest('.chatbot-window')) return;
        isDragging = true;
        startX = e.clientX;
        startY = e.clientY;
        var pos = getComputedPosition();
        initialLeft = pos.left;
        initialBottom = pos.bottom;
        draggable.style.cursor = 'grabbing';
        e.preventDefault();
    });
    window.addEventListener('mousemove', function (e) {
        if (!isDragging) return;
        var dx = e.clientX - startX;
        var dy = startY - e.clientY;
        var newLeft = initialLeft + dx;
        var newBottom = initialBottom + dy;
        var maxLeft = draggable.parentElement.clientWidth - draggable.offsetWidth;
        newLeft = Math.min(Math.max(0, newLeft), maxLeft);
        newBottom = Math.min(Math.max(10, newBottom), draggable.parentElement.clientHeight - 60);
        draggable.style.left = newLeft + 'px';
        draggable.style.bottom = newBottom + 'px';
        draggable.style.right = 'auto';
    });
    window.addEventListener('mouseup', function () {
        isDragging = false;
        draggable.style.cursor = 'grab';
    });
    draggable.style.position = 'absolute';
    draggable.style.bottom = '24px';
    draggable.style.right = '20px';
    draggable.style.left = 'auto';
    draggable.style.cursor = 'grab';

    var chatWin = document.getElementById('chatbotWindow');
    var chatIcon = document.getElementById('chatbotIcon');
    var chatClose = document.getElementById('chatbotClose');
    var chatInput = document.getElementById('chatbotInput');
    var chatSend = document.getElementById('chatbotSend');
    var chatMsgDiv = document.getElementById('chatbotMessages');
    var isLoggedIn = (typeof window.isLoggedIn !== 'undefined') ? window.isLoggedIn : false;
    var currentUserName = (typeof window.userFullName !== 'undefined' && window.userFullName) ? window.userFullName : (typeof window.userUsername !== 'undefined' ? window.userUsername : 'You');

    chatIcon.addEventListener('click', function () {
        chatWin.classList.toggle('active');
        if (chatWin.classList.contains('active') && isLoggedIn) {
            loadFullChatHistory();
            startChatPolling();
        }
    });
    chatClose.addEventListener('click', function () { chatWin.classList.remove('active'); });

    var lastChatId = 0;
    function addMsg(text, isUser, timestamp) {
        var d = document.createElement('div');
        d.className = 'chat-message ' + (isUser ? 'user' : 'bot');
        var senderName = isUser ? currentUserName : 'CADIZGO Assistant';
        var timeStr = timestamp ? ('<span style="font-size:10px;opacity:0.6;display:block;margin-top:3px;">' + formatTime(timestamp) + '</span>') : '';
        d.innerHTML = '<div style="font-size:10px;font-weight:600;color:#8a95a8;margin-bottom:2px;text-align:' + (isUser ? 'right' : 'left') + ';">' + escapeHtml(senderName) + '</div><div class="message-bubble">' + escapeHtml(text) + timeStr + '</div>';
        chatMsgDiv.appendChild(d);
        chatMsgDiv.scrollTop = chatMsgDiv.scrollHeight;
        return d;
    }
    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function safeLandmarkImage(value) {
        var image = String(value == null ? '' : value).trim();
        if (/^data:image\/(?:png|jpe?g|gif|webp|avif);base64,[A-Za-z0-9+/]+={0,2}$/i.test(image)) {
            return image;
        }
        try {
            var url = new URL(image, window.location.href);
            if (url.protocol === 'https:' || url.protocol === 'http:') return url.href;
        } catch (err) {
            return '';
        }
        return '';
    }
    function formatTime(ts) {
        var d = new Date(ts.replace(' ', 'T'));
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function sendChat() {
        var t = chatInput.value.trim();
        if (!t) return;
        chatInput.value = '';

        if (!isLoggedIn) {
            addMsg(t, true);
            setTimeout(function () {
                addMsg('Please sign in to message the CADIZGO team.', false);
            }, 500);
            return;
        }

        var sentEl = addMsg(t, true, new Date().toISOString());
        var stat = document.createElement('div'); stat.className = 'msg-status'; stat.textContent = 'Sending'; sentEl.appendChild(stat);

        fetch('chat_api.php?action=send', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: t })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    lastChatId = Math.max(lastChatId, data.id);
                    stat.textContent = 'Sent to the CADIZGO team';
                } else {
                    stat.textContent = 'Not sent. Please try again.'; stat.classList.add('error');
                }
            })
            .catch(function () { stat.textContent = 'Not sent. Check your connection.'; stat.classList.add('error'); });
    }


    // ===== Assistant quick help =====
    var FAQ = {
        route: ['How do I get a route?', 'Open the map and select a landmark, then choose Show route. The route starts from your live position and can be cleared with Clear route.'],
        rate: ['How do I rate a place?', 'Select Rate on a place card, choose a score from 1 (poor) to 5 (excellent), add an optional comment and submit.'],
        locate: ['Where am I on the map?', 'Open the map and select Locate. Your position appears as a blue marker with an accuracy circle. Allow location access in your browser if asked.'],
        device: ['How do I find my device?', 'On the map, select Last seen to view the last position recorded for your device, with its battery level and the time it was recorded.']
    };
    var typingEl = null;
    function showTyping(on) {
        if (typingEl) { typingEl.remove(); typingEl = null; }
        if (!on) return;
        typingEl = document.createElement('div');
        typingEl.className = 'chat-message bot typing';
        typingEl.innerHTML = '<div class="message-bubble"><span></span><span></span><span></span></div>';
        chatMsgDiv.appendChild(typingEl);
        chatMsgDiv.scrollTop = chatMsgDiv.scrollHeight;
    }
    document.querySelectorAll('.chat-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var key = chip.dataset.faq;
            if (key === 'team') {
                addMsg('Type your message below and the CADIZGO team will reply here.', false, new Date().toISOString());
                chatInput.focus();
                return;
            }
            var f = FAQ[key];
            if (!f) return;
            addMsg(f[0], true, new Date().toISOString());
            showTyping(true);
            setTimeout(function () { showTyping(false); addMsg(f[1], false, new Date().toISOString()); }, 700);
        });
    });
    chatSend.addEventListener('click', sendChat);
    chatInput.addEventListener('keypress', function (e) { if (e.key === 'Enter') sendChat(); });

    var pollInterval = null;
    function startChatPolling() {
        if (pollInterval || !isLoggedIn) return;
        fetchNewMessages();
        pollInterval = setInterval(fetchNewMessages, 3000);
    }
    function fetchNewMessages() {
        fetch('chat_api.php?action=poll&after=' + lastChatId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success || !data.messages.length) return;
                data.messages.forEach(function (m) {
                    if (m.id > lastChatId) {
                        if (m.sender === 'admin') {
                            addMsg(m.message, false, m.created_at);
                        }
                        lastChatId = m.id;
                    }
                });
            })
            .catch(function () { });
    }

    function loadFullChatHistory() {
        if (!isLoggedIn) return;
        fetch('chat_api.php?action=full_history')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) return;
                chatMsgDiv.innerHTML = '<div class="chat-message bot"><div class="message-bubble">Welcome to the CADIZGO Assistant. Choose a topic below for instant help, or type a message and the CADIZGO team will reply here.</div></div>';
                lastChatId = data.latest_id || 0;
                if (data.messages && data.messages.length) {
                    data.messages.forEach(function (m) {
                        if (m.sender === 'user') {
                            addMsg(m.message, true, m.created_at);
                        } else {
                            addMsg(m.message, false, m.created_at);
                        }
                    });
                }
                chatMsgDiv.scrollTop = chatMsgDiv.scrollHeight;
            })
            .catch(function () { });
    }

    var bgUnreadCount = 0;
    var bgLastId = 0;
    var badgeEl = document.getElementById('chatbotUnreadBadge');

    function pollBackground() {
        if (!isLoggedIn) return;
        fetch('chat_api.php?action=poll&after=' + bgLastId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success || !data.messages.length) return;
                data.messages.forEach(function (m) {
                    if (m.id > bgLastId && m.sender === 'admin') {
                        bgLastId = m.id;
                        var isChatOpen = chatWin && chatWin.classList.contains('active');
                        if (!isChatOpen) {
                            bgUnreadCount++;
                            if (badgeEl) {
                                badgeEl.style.display = 'flex';
                                badgeEl.textContent = bgUnreadCount;
                                badgeEl.style.transform = 'scale(1.4)';
                                setTimeout(function () { badgeEl.style.transform = 'scale(1)'; }, 300);
                            }
                            showAdminNotification(m.user_name, m.message);
                        }
                    }
                });
            })
            .catch(function () { });
    }

    var notificationTimeout = null;
    function showAdminNotification(userName, message) {
        var existing = document.querySelector('.admin-notification-popup');
        if (existing) existing.remove();

        var popup = document.createElement('div');
        popup.className = 'admin-notification-popup';
        popup.innerHTML = '<div class="popup-avatar"></div>' +
            '<div class="popup-content">' +
            '<div class="popup-title">Admin replied to you</div>' +
            '<div class="popup-text">' + escapeHtml(message.substring(0, 60)) + '</div>' +
            '</div>' +
            '<button class="popup-close" onclick="this.parentElement.remove()">&times;</button>';

        document.body.appendChild(popup);

        popup.addEventListener('click', function (e) {
            if (e.target.classList.contains('popup-close')) return;
            popup.remove();
            if (chatWin) {
                chatWin.classList.add('active');
                bgUnreadCount = 0;
                if (badgeEl) {
                    badgeEl.style.display = 'none';
                    badgeEl.textContent = '0';
                }
                if (isLoggedIn) {
                    lastChatId = 0;
                    chatMsgDiv.innerHTML = '<div class="chat-message bot"><div class="message-bubble">Welcome to the CADIZGO Assistant. Choose a topic below for instant help, or type a message and the CADIZGO team will reply here.</div></div>';
                    startChatPolling();
                }
            }
        });

        if (notificationTimeout) clearTimeout(notificationTimeout);
        notificationTimeout = setTimeout(function () {
            if (popup.parentElement) {
                popup.style.opacity = '0';
                popup.style.transform = 'translateY(10px)';
                popup.style.transition = 'all 0.3s ease';
                setTimeout(function () { if (popup.parentElement) popup.remove(); }, 350);
            }
        }, 6000);
    }

    setInterval(pollBackground, 4000);
    setTimeout(pollBackground, 1000);

    chatIcon.addEventListener('click', function () {
        if (chatWin.classList.contains('active')) return;
        bgUnreadCount = 0;
        if (badgeEl) {
            badgeEl.style.display = 'none';
            badgeEl.textContent = '0';
        }
        var existing = document.querySelector('.admin-notification-popup');
        if (existing) existing.remove();
    });

    // ===== Tags & Search (show location info, no navigation) =====
    document.querySelectorAll('.tag').forEach(function (t) {
        t.addEventListener('click', function () {
            var val = t.innerText;
            showToast(val + ' — find it on the map');
            switchScreen('map');
        });
    });
    document.getElementById('homeSearch').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            var q = e.target.value.toLowerCase();
            showToast('Search for "' + q + '" — view on map');
            switchScreen('map');
        }
    });
    document.getElementById('planDayBtn').addEventListener('click', function () { if (!chatWin.classList.contains('active')) chatIcon.click(); chatInput.focus(); });
    document.getElementById('findFoodBtn').addEventListener('click', function () { switchScreen('map'); });

    // ===== Auth Form Toggling =====
    var goToRegister = document.getElementById('go-to-register');
    var goToLogin = document.getElementById('go-to-login');
    var loginCard = document.getElementById('login-card');
    var registerCard = document.getElementById('register-card');

    if (goToRegister && goToLogin && loginCard && registerCard) {
        goToRegister.addEventListener('click', function (e) {
            e.preventDefault();
            loginCard.classList.add('hidden');
            registerCard.classList.remove('hidden');
        });
        goToLogin.addEventListener('click', function (e) {
            e.preventDefault();
            registerCard.classList.add('hidden');
            loginCard.classList.remove('hidden');
        });
    }

    // ===== Login Submission =====
    var loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var username = loginForm.username.value.trim();
            var password = loginForm.password.value;
            var deviceInfo = (typeof window.__deviceInfo !== 'undefined') ? window.__deviceInfo : navigator.userAgent || 'Unknown';

            var payload = {
                username: username,
                password: password,
                device_info: deviceInfo.substring(0, 200)
            };

            if ('geolocation' in navigator) {
                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        payload.lat = pos.coords.latitude;
                        payload.lng = pos.coords.longitude;
                        payload.accuracy = pos.coords.accuracy;
                        userLatLng.lat = pos.coords.latitude;
                        userLatLng.lng = pos.coords.longitude;
                        window.__cadizgoUserLatLng.lat = pos.coords.latitude;
                        window.__cadizgoUserLatLng.lng = pos.coords.longitude;
                        sendLoginRequest(payload);
                    },
                    function () {
                        sendLoginRequest(payload);
                    },
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            } else {
                sendLoginRequest(payload);
            }
        });

        function sendLoginRequest(payload) {
            fetch('login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.success) {
                        showToast('Signed in. Location saved.');
                        isLoggedIn = true;
                        if (payload.lat && payload.lng) {
                            startLocationBroadcast(payload.lat, payload.lng, payload.accuracy);
                        }
                        setTimeout(function () { location.reload(); }, 1200);
                    } else {
                        showToast(data.message || 'Login failed.');
                    }
                })
                .catch(function (err) {
                    console.error(err);
                    showToast('Network error during login.');
                });
        }
    }

    // ===== Register Submission =====
    var registerForm = document.getElementById('register-form');
    if (registerForm) {
        registerForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fullName = registerForm.full_name.value.trim();
            var username = registerForm.username.value.trim();
            var password = registerForm.password.value;
            var locationVal = registerForm.location.value.trim();

            fetch('register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    full_name: fullName,
                    username: username,
                    password: password,
                    location: locationVal
                })
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.success) {
                        showToast('Registration complete.');
                        setTimeout(function () { location.reload(); }, 800);
                    } else {
                        showToast(data.message || 'Registration failed.');
                    }
                })
                .catch(function (err) {
                    console.error(err);
                    showToast('Network error during registration.');
                });
        });
    }

    // ===== Send Feedback Panel =====
    (function () {
        var fbStars = document.querySelectorAll('.fb-star');
        var selectedFbRating = 0;

        if (!fbStars.length) return;

        function updateFbStarUI(hovered) {
            var val = hovered !== undefined ? hovered : selectedFbRating;
            fbStars.forEach(function (s) {
                var sv = parseInt(s.getAttribute('data-val'));
                if (sv <= val) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                    s.style.transform = 'scale(1.15)';
                } else {
                    s.classList.remove('fas');
                    s.classList.add('far');
                    s.style.transform = 'scale(1)';
                }
            });
        }

        fbStars.forEach(function (star) {
            star.addEventListener('mouseenter', function () {
                updateFbStarUI(parseInt(this.getAttribute('data-val')));
            });
            star.addEventListener('mouseleave', function () {
                updateFbStarUI();
            });
            star.addEventListener('click', function () {
                selectedFbRating = parseInt(this.getAttribute('data-val'));
                updateFbStarUI();
            });
        });

        var sendFeedbackBtn = document.getElementById('sendFeedbackBtn');
        if (sendFeedbackBtn) {
            sendFeedbackBtn.addEventListener('click', function () {
                var message = document.getElementById('feedbackMessage').value.trim();
                var category = document.getElementById('feedbackCategory').value;

                if (selectedFbRating === 0) {
                    showToast('Please select a star rating first.');
                    return;
                }
                if (!message) {
                    showToast('Please write your feedback message.');
                    return;
                }

                sendFeedbackBtn.disabled = true;
                sendFeedbackBtn.innerHTML = 'Sending';

                fetch('submit_feedback.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        rating: selectedFbRating,
                        message: message,
                        category: category
                    })
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data.success) {
                            showToast('Feedback sent. Thank you.');
                            document.getElementById('feedbackMessage').value = '';
                            selectedFbRating = 0;
                            updateFbStarUI();
                        } else {
                            showToast(data.message || 'Could not send feedback.');
                        }
                    })
                    .catch(function (err) {
                        console.error(err);
                        showToast('Network error. Please try again.');
                    })
                    .finally(function () {
                        sendFeedbackBtn.disabled = false;
                        sendFeedbackBtn.innerHTML = 'Submit feedback';
                    });
            });
        }
    })();

    // ===== Popular Attractions (load from DB) =====
    function loadPopularAttractions() {
        var container = document.getElementById('popularAttractionsContainer');
        if (!container) return;

        fetch('landmarks_api.php?action=list')
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success || !data.landmarks.length) return;
                data.landmarks = filterHiddenLandmarks(data.landmarks);
                if (!data.landmarks.length) return;
                container.innerHTML = '';

                var plazaLat = 10.9504, plazaLng = 123.3081;

                function calcDistance(lat1, lng1, lat2, lng2) {
                    var R = 6371;
                    var dLat = (lat2 - lat1) * Math.PI / 180;
                    var dLng = (lng2 - lng1) * Math.PI / 180;
                    var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                        Math.sin(dLng / 2) * Math.sin(dLng / 2);
                    var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                    return R * c;
                }

                data.landmarks.forEach(function (lm) {
                    var dist = calcDistance(plazaLat, plazaLng, lm.lat, lm.lng);
                    var distStr = dist < 1 ? Math.round(dist * 1000) + ' m' : dist.toFixed(1) + ' km';
                    attractionsMap[lm.id] = {
                        id: lm.id,
                        name: lm.name,
                        desc: lm.description || '',
                        image: lm.photo || '',
                        lat: lm.lat,
                        lng: lm.lng,
                        rating: parseFloat(lm.rating) || 0,
                        reviews: lm.reviews || 0,
                        distance: distStr
                    };
                });

                container.innerHTML = data.landmarks.map(function (lm) {
                    var p = attractionsMap[lm.id];
                    if (!p) return '';
                    var photoUrl = safeLandmarkImage(p.image);
                    var photoHtml = photoUrl
                        ? '<img class="attraction-image" src="' + escapeHtml(photoUrl) + '" alt="' + escapeHtml(p.name) + '" loading="lazy">'
                        : '';

                    return '<div class="attraction-card" data-id="' + lm.id + '">' +
                        photoHtml +
                        '<div class="attraction-content">' +
                        '<div class="attraction-rating"><span class="small text-muted">' + ratingLabel(p) + '</span>' +
                        '</div>' +
                        '<h6 class="mb-1">' + escapeHtml(p.name) + '</h6>' +
                        '<small class="text-muted d-block mb-2">' + p.distance + ' from Cadiz City Plaza</small>' +
                        '<div class="d-flex gap-2">' +
                        '<button class="details-btn" data-id="' + lm.id + '">Details</button>' +
                        '<button class="feedback-btn flex-grow-1" data-name="' + p.name.replace(/'/g, "\\'") + '">Rate</button>' +
                        '</div>' +
                        '</div>' +
                        '</div>';
                }).join('');

                container.querySelectorAll('.attraction-card').forEach(function (card) {
                    card.addEventListener('click', function () {
                        var id = parseInt(this.dataset.id);
                        if (attractionsMap[id]) showImageModal(id);
                    });
                });

                container.querySelectorAll('.details-btn').forEach(function (btn) {
                    btn.addEventListener('click', function (e) {
                        e.stopPropagation();
                        var id = parseInt(this.dataset.id);
                        if (attractionsMap[id]) showImageModal(id);
                    });
                });

                container.querySelectorAll('.feedback-btn').forEach(function (btn) {
                    btn.addEventListener('click', function (e) {
                        e.stopPropagation();
                        openRating(this.dataset.name);
                    });
                });
            })
            .catch(function (err) {
                console.error('Error loading popular attractions:', err);
            });
    }

    loadPopularAttractions();


    // ===== Search across loaded places =====
    function findPlace(q) {
        q = (q || '').trim().toLowerCase();
        if (!q) return null;
        var ids = Object.keys(attractionsMap);
        for (var i = 0; i < ids.length; i++) { if (attractionsMap[ids[i]].name.toLowerCase().indexOf(q) !== -1) return attractionsMap[ids[i]]; }
        return null;
    }
    var mapSearch = document.getElementById('map-search-input');
    if (mapSearch) mapSearch.addEventListener('keypress', function (e) {
        if (e.key !== 'Enter') return;
        var m = findPlace(e.target.value);
        if (m && map) { map.flyTo([m.lat, m.lng], 17, { duration: 1 }); } else { showToast('No matching place found.'); }
    });
    var homeSearchEl = document.getElementById('homeSearch');
    if (homeSearchEl) homeSearchEl.addEventListener('keypress', function (e) {
        if (e.key !== 'Enter') return;
        var m = findPlace(e.target.value);
        if (m) { showImageModal(m.id); }
    });
    // ===== Keyboard: Escape closes overlays =====
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        setDrawer(false);
        document.querySelectorAll('.image-modal.active, .rating-modal.active').forEach(function (m) { m.classList.remove('active'); });
        if (chatWin) chatWin.classList.remove('active');
    });
    document.querySelectorAll('.category-card').forEach(function (c) { c.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); c.click(); } }); });
    var voiceBtn = document.getElementById('voiceMock');
    if (voiceBtn) voiceBtn.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); voiceBtn.click(); } });

    showToast('Welcome to CADIZGO.');
})();