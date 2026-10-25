<?php require __DIR__ . '/assets/layout/navbaradmin.php'; ?>

<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<div class="container-fluid p-0">

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white p-3">
                <h6>Total Trips</h6>
                <h3>45</h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-primary text-white p-3">
                <h6>Ongoing Trips</h6>
                <h3>12</h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark p-3">
                <h6>Total Buses</h6>
                <h3>28</h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white p-3">
                <h6>Revenue</h6>
                <h3>₱120,500</h3>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div id="map" style="height:500px; width:100%; border:1px solid #ddd;"></div>
        </div>

        <div class="col-md-4">
            <div id="bus-panel"></div>
        </div>
    </div>
</div>

<script>
    let map = L.map('map', {
        center: [15.60, 121.0],
        zoom: 11,
        minZoom: 9,
        maxZoom: 15
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let busIcon = L.icon({
        iconUrl: 'icons/bus.png',
        iconSize: [48, 48],
        iconAnchor: [24, 48],
        popupAnchor: [0, -48]
    });

    let routeCoordinates = [
    [15.6812, 121.0692],
    [15.6602, 121.0414],
    [15.6978, 121.0906],
    [15.6500, 121.0500],
    [15.7100, 121.1000],
    [15.5174, 120.9393],
    [15.5193, 120.9564],
    [15.5093, 120.9610],
    [15.5017, 120.9841],
    [15.4461, 120.9210],
    [15.4902, 120.9665]
    ];

    let routeLine = L.polyline(routeCoordinates, { color: 'red', weight: 5, opacity: 0.8 }).addTo(map);
    map.fitBounds(routeLine.getBounds().pad(0.1));

    let markers = {};
    let busProgress = {};

    let trips = [
    {
        id: 'BUS-4321',
        driver: 'Mark Antonio',
        route: 'Rizal → Cabanatuan',
        speed: 28,
        occupancy: 25,
        capacity: 28,
        bus_number: 'BUS-4321',
        time: '',
        updated: ''
    },
    {
        id: 'BUS-5678',
        driver: 'Anna Reyes',
        route: 'Rizal → Cabanatuan',
        speed: 32,
        occupancy: 18,
        capacity: 28,
        bus_number: 'BUS-5678',
        time: '',
        updated: ''
    }
    ];

    trips.forEach(trip => {
        markers[trip.id] = L.marker(routeCoordinates[0], { icon: busIcon }).addTo(map);
        busProgress[trip.id] = 0;
    });

    function animateBuses() {
        trips.forEach(trip => {
            let index = busProgress[trip.id];
            let nextIndex = (index + 1) % routeCoordinates.length;

            let start = routeCoordinates[index];
            let end = routeCoordinates[nextIndex];

            let step = 0.02;
            let lat = start[0] + (end[0] - start[0]) * step;
            let lng = start[1] + (end[1] - start[1]) * step;

            markers[trip.id].setLatLng([lat, lng]);

            busProgress[trip.id] += step;
            if (busProgress[trip.id] >= 1) {
                busProgress[trip.id] = 0;
                routeCoordinates.push(routeCoordinates.shift());
            }

            trip.time = new Date().toLocaleTimeString();
            trip.updated = 'Just now';
        });

        updatePanel();
        requestAnimationFrame(animateBuses);
    }

    function updatePanel() {
        const panel = document.getElementById('bus-panel');
        panel.innerHTML = '';

        trips.forEach(trip => {
            const percent = Math.round((trip.occupancy / trip.capacity) * 100);
            const card = document.createElement('div');
            card.className = 'card mb-3 p-2';
            card.innerHTML = `
            <h6>${trip.driver}</h6>
            <p><strong>Route:</strong> ${trip.route}</p>
            <p><strong>Speed:</strong> ${trip.speed} km/h</p>
            <p><strong>Bus ID:</strong> ${trip.bus_number}</p>
            <p><strong>Time:</strong> ${trip.time}</p>
            <p><strong>Updated:</strong> ${trip.updated}</p>
            <div class="progress">
                <div class="progress-bar" role="progressbar" style="width: ${percent}%;">
                    ${trip.occupancy}/${trip.capacity} (${percent}%)
                </div>
            </div>
        `;
            panel.appendChild(card);
        });
    }

    animateBuses();
</script>
