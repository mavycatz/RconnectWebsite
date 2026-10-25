<?php
declare(strict_types = 1);

require_once __DIR__ . '/../app/Core/Bootstrap.php';

use App\auth\Session;
use App\auth\Auth;

Session::start();

if (!Auth::check()) {
    header('Location: /public/login/index.php');
    exit;
}

$user = Auth::user();
if (!in_array($user['role'], ['admin', 'super_admin'])) {
    header('Location: /admin/');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Trip Dashboard</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/chart.js">        </script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

        <style>
            html, body {
                background-color: #eceed9 !important;
            }
            body {
                margin: 0;
                display: flex;
                min-height: 100vh;
            }

            .main-content {
                flex-grow: 1;
                padding: 20px;
                margin-left: 0;
                margin-top: 60px;
                transition: margin-left 0.3s ease;
            }

            .main-content.sidebar-collapsed {
                margin-left: 0;
            }

            canvas {
                display: block;
                width: 100%;
            }

            .header {
                position: fixed;
                top: 0;
                left: 220px;
                right: 0;
                height: 60px;
                background: #fff;
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0 20px;
                border-bottom: 1px solid rgba(0, 0, 0, .1);
                transition: left 0.3s ease;
                z-index: 1001;
            }

            .header.sidebar-collapsed {
                left: 0;
            }

            .header-left input {
                width: 700px;
            }

            .icon-container {
                width: 40px;
                height: 40px;
                border: 2px solid #86858f;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .icon {
                color: #295738;
                font-size: 20px;
            }

            #map {
                position: relative;
                z-index: 1;
            }

            .hamburger {
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                width: 30px;
                height: 22px;
                background: none;
                border: none;
                cursor: pointer;
                padding: 0;
            }

            .hamburger span {
                display: block;
                height: 3px;
                width: 100%;
                background-color: #6f6d75;
                border-radius: 2px;
            }
            #live-dashboard {
                padding: 25px;
            }

            .live-card {
                background: #fffdf3;
                border: 2px solid #e7e3af;
                border-radius: 22px;
                padding: 25px;
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.05);
            }

            .live-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }

            .live-title {
                font-weight: 700;
                font-size: 22px;
                color: #2f5d3a;
            }

            .live-section-title {
                font-weight: 600;
                margin-bottom: 10px;
                color: #2f5d3a;
            }

            .passenger-box {
                background: #f9f8e4;
                border-radius: 12px;
                padding: 10px 12px;
                font-size: 13px;
                margin-bottom: 6px;
                display: flex;
                justify-content: space-between;
            }

            .passenger-paid {
                color: #2e7d32;
                font-weight: 600;
            }

            .passenger-unpaid {
                color: #c62828;
                font-weight: 600;
            }

            .seat-grid {
                display: grid;
                grid-template-columns: repeat(7, 1fr);
                gap: 10px;
                margin-top: 10px;
            }

            .seat-box {
                padding: 10px;
                text-align: center;
                border-radius: 12px;
                font-size: 13px;
                font-weight: 600;
                transition: 0.2s ease;
            }

            .seat-paid {
                background: #448141;
                color: white;
            }

            .seat-unpaid {
                background: #c94f4f;
                color: white;
            }

            .seat-empty {
                background: #e0e0e0;
                color: #555;
            }

            .live-legend {
                margin-top: 15px;
                font-size: 13px;
            }

            .live-btn {
                background: #e0d769;
                border: 1px solid #b5ad55;
                border-radius: 20px;
                font-weight: 600;
                padding: 6px 14px;
            }
        </style>
    </head>
    <body>

<?php require __DIR__ . '/assets/layout/navbaradmin.php'; ?>

<div class="main-content" id="mainContent">
    <div
        style="
            background:#fef8f3;
            border:1px solid #aaa8b1;
            border-radius:24px;
            padding:24px;
        "
    >
        <div class="container-fluid p-0">

            <div style="display:flex; justify-content:space-between; align-items:center; font-family:'Poppins', sans-serif; margin-bottom:15px;">

                <div style="display:flex; flex-direction:column; align-items:flex-start;">
                    <div class="d-flex justify-content-between align-items-center">

                        <div style="
        font-weight:700;
        font-size:1.3rem;
        font-family:'Poppins', sans-serif;
        color:#295738;">
      Real Time Monitoring
                        </div>
                    </div>
                    <p style="font-weight:500; font-size:0.9rem; color:#7f8c8d; margin:0;">
            Overview of ongoing trips, bus routes, and performance metrics
                    </p>
                </div>

                <button onclick="viewAllBuses()" 
        style="background:transparent; 
               border:1px solid #b5ad55;
               color:#295738;
               border-radius:20px; 
               padding:6px 16px;
               font-weight:600; 
               height:38px;">
    Back to All Buses
                </button>

            </div>
            <div class="container-fluid mb-4">
                <div class="row">
                    <div class="col-12">
                        <div id="map" 
           style="height:500px; width:100%; border:2px solid #545454; border-radius:10px; margin-top:10px;">
                        </div>
                    </div>
                </div>
            </div>
            <div class="container-fluid mb-3">
                <div class="row g-3">

                    <div class="col-md-3">
                        <div class="p-3"
           style="background:#faf9e5; border:2px solid #e5e0a4; border-radius:14px; min-height:95px;">
        
                            <h6 class="fw-bold mb-2"
            style="font-family:'Poppins', sans-serif; font-size:1rem; color:#295738;">
            Today's Trip
                            </h6>

                            <div class="d-flex align-items-center">
                                <img src="/assets/images/admin/trips/TripToday.png"
               style="width:50px; height:50px; object-fit:contain; margin-right:12px;">

                                <div style="font-size:1.3rem; font-weight:600; color:#2c3e50; text-align:center;">
              5 of 20 completed
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="p-3"
           style="background:#faf9e5; border:2px solid #e5e0a4; border-radius:14px; min-height:95px;">
        
                            <h6 class="fw-bold mb-2"
            style="font-family:'Poppins', sans-serif; font-size:1rem; color:#295738;">
            Completed
                            </h6>

                            <div class="d-flex align-items-center">
                                <img src="/assets/images/admin/trips/TripCom.png"
               style="width:45px; height:45px; object-fit:contain; margin-right:12px;">

                                <div style="font-size:1.3rem; font-weight:600; color:#2c3e50; text-align:center;">
              5 trips finished today
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="p-3"
           style="background:#faf9e5; border:2px solid #e5e0a4; border-radius:14px; min-height:95px;">
        
                            <h6 class="fw-bold mb-2"
            style="font-family:'Poppins', sans-serif; font-size:1rem; color:#295738;">
            In Progress
                            </h6>

                            <div class="d-flex align-items-center">
                                <img src="/assets/images/admin/trips/TripinProg.png"
               style="width:45px; height:45px; object-fit:contain; margin-right:12px;">

                                <div style="font-size:1.3rem; font-weight:600; color:#2c3e50; text-align:center;">
              8 bus currently turning
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="p-3"
           style="background:#faf9e5; border:2px solid #e5e0a4; border-radius:14px; min-height:95px;">
        
                            <h6 class="fw-bold mb-2"
            style="font-family:'Poppins', sans-serif; font-size:1rem; color:#295738;">
            Scheduled
                            </h6>

                            <div class="d-flex align-items-center">
                                <img src="/assets/images/admin/trips/TripSched.png"
               style="width:50px; height:50px; object-fit:contain; margin-right:12px;">

                                <div style="font-size: 1.3rem; font-weight:600; color:#2c3e50; text-align:center;">
              7 upcoming trips
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <h3 style="color: #295738; font-weight: bold; font-size: 1rem; margin-bottom: 1rem;">
    Live Bus Monitoring
            </h3>
            <div class="container-fluid mb-4">
                <div class="row">
                    <div class="col-12">
                        <div id="bus-panel" class="d-flex flex-wrap gap-3"></div>
                    </div>
                </div>
            </div>


            <script src="https://unpkg.com/leaflet/dist/leaflet.js">            </script>
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
                    iconUrl: '../assets/images/admin/trips/front-of-bus.png',
                    iconSize: [32, 32],
                    iconAnchor: [16, 32],
                });

                let routeCoordinates = [
                [15.6602, 121.0414],
                [15.5488, 120.9335],
                [15.5369, 120.9330],
                [15.5868, 120.9771],
                [15.5686, 120.9554],
                [15.5767, 120.9644],
                [15.5601, 120.9461],
                [15.6489, 121.0320],
                [15.6371, 121.0190],
                [15.6293, 121.0126],
                [15.6306, 120.9914],
                [15.6003, 120.9765],
                [15.5174, 120.9393],
                [15.5193, 120.9564],
                [15.5126, 120.9571],
                [15.5017, 120.9841],
                [15.4947, 120.9655]
                ];

                const routeLocationNames = [
                "Bicos", "Mataas Kanto", "San Miguel na Munti", "Bagong Sikat",
                "Homestead I", "Homestead II", "Baluga", "Inanama", "Mabini",
                "General Ricarte", "General Luna", "Casile", "Caalibangbangan",
                "Mayapyap", "Valdefuente", "Bitas", "Cabanatuan Terminal"
                ];

                let routeLine = L.polyline(routeCoordinates, { color: 'red', weight: 3 }).addTo(map);
                map.fitBounds(routeLine.getBounds().pad(0.1));

                let markers = {};
                let busProgress = {};
                let currentViewBusId = null;

                let trips = [
                { id: 'BUS-2341', driver: 'Aurchie Rodriguez', locationIndex: 3, speed: 34, occupancy: 13, capacity: 28, fuel: 75, weather: "Sunny", time: new Date().toLocaleTimeString() },
                { id: 'BUS-8765', driver: 'Mark Antonio', locationIndex: 6, speed: 29, occupancy: 18, capacity: 28, fuel: 62, weather: "Cloudy", time: new Date().toLocaleTimeString() },
                { id: 'BUS-4421', driver: 'Anna Reyes', locationIndex: 9, speed: 31, occupancy: 20, capacity: 28, fuel: 85, weather: "Sunny", time: new Date().toLocaleTimeString() },
                { id: 'BUS-9902', driver: 'Jose Delgado', locationIndex: 12, speed: 27, occupancy: 11, capacity: 28, fuel: 54, weather: "Rainy", time: new Date().toLocaleTimeString() },
                ];

                let busPassengers = {
                    "BUS-2341": generatePassengers(28, 13),
                    "BUS-8765": generatePassengers(28, 18),
                    "BUS-4421": generatePassengers(28, 20),
                    "BUS-9902": generatePassengers(28, 11)
                };

                function generatePassengers(capacity, occupied) {
                    let seats = [];
                    for (let i = 1; i <= capacity; i++) {
                        if (i <= occupied) {
                            seats.push({
                                seat: i,
                                name: "Passenger " + i,
                                paid: Math.random() > 0.3
                            });
                        } else {
                            seats.push({
                                seat: i,
                                name: null,
                                paid: false
                            });
                        }
                    }
                    return seats;
                }

                trips.forEach(trip => {
                    let loc = routeCoordinates[trip.locationIndex];
                    markers[trip.id] = L.marker(loc, { icon: busIcon })
                    .addTo(map)
                    .bindTooltip(`${trip.id}`, { permanent:true, direction:'top' });
                    busProgress[trip.id] = trip.locationIndex;
                });

                function renderPanel() {
                    const panel = document.getElementById('bus-panel');
                    panel.innerHTML = '';

                    trips.forEach(trip => {

                        const seatPercent = Math.round(trip.occupancy / trip.capacity * 100);

                        const card = document.createElement('div');
                        card.className = 'card p-3 flex-grow-1 mb-3';
                        card.style.backgroundColor = '#faf9e5';
                        card.style.border = '1px solid #e7e3af';
                        card.style.borderRadius = '18px';
                        card.style.minWidth = '260px';

                        card.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <strong style="font-size:20px; color:#2f5d3a;">
                    ${trip.id}
                </strong>
                <span class="bus-time" style="font-size:16px; font-weight:600; color:#2f5d3a;">
                    ${trip.time}
                </span>
            </div>

            <div style="font-size:14px;">Driver: <strong>${trip.driver}</strong></div>
            <div style="font-size:14px;">Route: <strong>${routeLocationNames[trip.locationIndex]} → Cabanatuan</strong></div>
            <div style="font-size:14px;">Kilometer per hour: <strong>${trip.speed}</strong> km/h</div>
            <div style="font-size:14px; margin-bottom:8px;">Weather: <strong>${trip.weather}</strong></div>

            <div class="mb-2 d-flex align-items-center gap-2">
                <i class="bi bi-person-fill" style="color:#2f5d3a;"></i>
                <div class="progress flex-grow-1" style="height:14px; background:#eee275; border-radius:10px;">
                    <div class="progress-bar"
                         style="width:${seatPercent}%; background:#448141; border-radius:10px;">
                    </div>
                </div>
                <span style="font-size:12px; min-width:50px; text-align:right;">
                    ${trip.occupancy}/${trip.capacity}
                </span>
            </div>

            <div class="mb-2 d-flex align-items-center gap-2">
                <i class="bi bi-fuel-pump-fill" style="color:#2f5d3a;"></i>
                <div class="progress flex-grow-1" style="height:14px; background:#eee275; border-radius:10px;">
                    <div class="progress-bar"
                         style="width:${trip.fuel}%; background:#448141; border-radius:10px;">
                    </div>
                </div>
                <span style="font-size:12px; min-width:40px; text-align:right;">
                    ${trip.fuel}%
                </span>
            </div>

            <button class="btn btn-sm w-100 mt-2 view-live"
                    data-id="${trip.id}"
                    style="background:#e0d769; border:1px solid #b5ad55; border-radius:20px; font-weight:600;">
                See Live Status
            </button>
        `;

                        panel.appendChild(card);
                    });

                    document.querySelectorAll('.view-live').forEach(btn => {
                        btn.addEventListener('click', function () {
                            const id = this.getAttribute('data-id');
                            viewLiveStatus(id);
                        });
                    });
                }
                function viewLiveStatus(busId) {

                    currentViewBusId = busId;

                    trips.forEach(trip => {
                        if (trip.id === busId) {
                            markers[trip.id].addTo(map);
                            map.setView(markers[trip.id].getLatLng(), 14);
                        } else {
                            map.removeLayer(markers[trip.id]);
                        }
                    });

                    const panel = document.getElementById('bus-panel');
                    panel.style.display = 'none';

                    showLiveDashboard(busId);

                    document.getElementById('map').scrollIntoView({
                        behavior: 'smooth'
                    });
                }
                function showLiveDashboard(busId) {

                    let existing = document.getElementById('live-dashboard');
                    if (existing) existing.remove();

                    const container = document.createElement('div');
                    container.id = 'live-dashboard';

                    const passengers = busPassengers[busId];

                    const paid = passengers.filter(p => p.name && p.paid);
                    const unpaid = passengers.filter(p => p.name && !p.paid);

                    container.innerHTML = `
        <div class="live-card">

            <div class="live-header">
                <div class="live-title">
                    🚍 Live Status — ${busId}
                </div>
                <button onclick="closeLiveDashboard()" class="live-btn">
                    ← Back to All Buses
                </button>
            </div>

            <div class="row">

                <div class="col-md-6 mb-3">
                    <div class="live-section-title">
                        Paid Passengers (${paid.length})
                    </div>
                    ${paid.length === 0 ? `<div>No paid passengers</div>` :
                        paid.map(p => `
                            <div class="passenger-box">
                                <span>Seat ${p.seat} — ${p.name}</span>
                                <span class="passenger-paid">✔ Paid</span>
                            </div>
                        `).join('')
                    }
                </div>

                <div class="col-md-6 mb-3">
                    <div class="live-section-title">
                        Unpaid Passengers (${unpaid.length})
                    </div>
                    ${unpaid.length === 0 ? `<div>No unpaid passengers</div>` :
                        unpaid.map(p => `
                            <div class="passenger-box">
                                <span>Seat ${p.seat} — ${p.name}</span>
                                <span class="passenger-unpaid">✘ Unpaid</span>
                            </div>
                        `).join('')
                    }
                </div>

            </div>

            <hr>

            <div class="live-section-title">
                Seat Layout
            </div>

            <div class="seat-grid">
                ${passengers.map(p => `
                    <div class="seat-box 
                        ${p.name 
                            ? (p.paid ? 'seat-paid' : 'seat-unpaid') 
                            : 'seat-empty'}">
                        ${p.seat}
                    </div>
                `).join('')}
            </div>

            <div class="live-legend">
                <span style="color:#448141; font-weight:600;">■ Paid</span> |
                <span style="color:#c94f4f; font-weight:600;">■ Unpaid</span> |
                <span style="color:#777; font-weight:600;">■ Available</span>
            </div>

        </div>
    `;

                    document.getElementById('map').after(container);
                }
                function closeLiveDashboard() {

                    currentViewBusId = null;

                    trips.forEach(trip => {
                        markers[trip.id].addTo(map);
                    });

                    map.fitBounds(routeLine.getBounds().pad(0.1));

                    document.getElementById('bus-panel').style.display = 'flex';

                    const dashboard = document.getElementById('live-dashboard');
                    if (dashboard) dashboard.remove();
                }
                function viewAllBuses() {

                    currentViewBusId = null;

                    trips.forEach(trip => {
                        markers[trip.id].addTo(map);
                    });

                    map.fitBounds(routeLine.getBounds().pad(0.1));

                    document.getElementById('bus-panel').style.display = 'flex';
                }

                function animateBuses() {
                    trips.forEach(trip => {

                        if (currentViewBusId && trip.id !== currentViewBusId) return;

                        let index = Math.floor(busProgress[trip.id]);
                        let nextIndex = (index + 1) % routeCoordinates.length;

                        let start = routeCoordinates[index];
                        let end = routeCoordinates[nextIndex];

                        let step = busProgress[trip.id] % 1;

                        let lat = start[0] + (end[0] - start[0]) * step;
                        let lng = start[1] + (end[1] - start[1]) * step;

                        markers[trip.id].setLatLng([lat, lng]);

                        busProgress[trip.id] += 0.00008;
        
                        if (busProgress[trip.id] >= routeCoordinates.length)
                        busProgress[trip.id] = 0;

                        trip.time = new Date().toLocaleTimeString();

                        const card = document.querySelector(`.card:nth-child(${trips.indexOf(trip)+1})`);
                        if (card) {
                            const timeEl = card.querySelector('.bus-time');
                            if (timeEl) timeEl.innerText = trip.time;
                        }
                    });

                    requestAnimationFrame(animateBuses);
                }

                renderPanel();
                animateBuses();
            </script>


            <style>
                .bus-label {
                    font-weight: bold;
                    color: #448141;
                    font-size: 14px;
                }
            </style>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">            </script>
        </body>
    </html>
