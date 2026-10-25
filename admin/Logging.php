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

            <div class="container-fluid mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">

                    <div style="
        font-weight:700;
        font-size:1.3rem;
        font-family:'Poppins', sans-serif;
        color:#295738;">
      System Logging
                    </div>
                </div>
                <div class="row g-3 mb-4">

                    <div class="col-md-3">
                        <div class="card p-3 log-card" style="background-color:#faece7; border:1px solid #cd8d81; border-radius:0.5rem; min-height:220px;">
                            <h6><i class="bi bi-exclamation-octagon-fill"></i> Errors</h6>
                            <ul class="log-list mb-2" data-full='[
        "Engine overheat detected",
        "GPS signal lost",
        "Brake system alert",
        "Fuel sensor malfunction",
        "High engine temperature",
        "Delayed trip detected",
        "Weak internet connection"
      ]'>
                                <li>Engine overheat detected</li>
                                <li>GPS signal lost</li>
                                <li>Brake system alert</li>
                                <li>Fuel sensor malfunction</li>
                                <li>High engine temperature</li>
                            </ul>
                            <a href="#" class="text-decoration-none see-more">See More</a>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card p-3 log-card" style="background-color:#f5f4e6; border:1px solid #96af80; border-radius:0.5rem; min-height:220px;">
                            <h6><i class="bi bi-check-circle-fill"></i> Success</h6>
                            <ul class="log-list mb-2" data-full='[
        "User login successful",
        "Trip data saved",
        "Report exported (PDF)",
        "System backup completed",
        "Driver assigned to trip",
        "Schedule adjusted",
        "New route added"
      ]'>
                                <li>User login successful</li>
                                <li>Trip data saved</li>
                                <li>Report exported (PDF)</li>
                                <li>System backup completed</li>
                                <li>Driver assigned to trip</li>
                            </ul>
                            <a href="#" class="text-decoration-none see-more">See More</a>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card p-3 log-card" style="background-color:#f9f2da; border:1px solid #e3c468; border-radius:0.5rem; min-height:220px;">
                            <h6><i class="bi bi-exclamation-triangle-fill"></i> Warnings</h6>
                            <ul class="log-list mb-2" data-full='[
        "Low fuel level (15%)",
        "Delayed trip detected",
        "Weak internet connection",
        "Schedule adjusted",
        "New route added"
      ]'>
                                <li>Low fuel level (15%)</li>
                                <li>Delayed trip detected</li>
                                <li>Weak internet connection</li>
                                <li>Schedule adjusted</li>
                                <li>New route added</li>
                            </ul>
                            <a href="#" class="text-decoration-none see-more">See More</a>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card p-3 log-card" style="background-color:#efeeee; border:1px solid #b2bec9; border-radius:0.5rem; min-height:220px;">
                            <h6><i class="bi bi-info-circle-fill"></i> Info</h6>
                            <ul class="log-list mb-2" data-full='[
        "System update applied",
        "New route added",
        "Driver assigned to trip",
        "Schedule adjusted",
        "Report generated"
      ]'>
                                <li>System update applied</li>
                                <li>New route added</li>
                                <li>Driver assigned to trip</li>
                                <li>Schedule adjusted</li>
                                <li>Report generated</li>
                            </ul>
                            <a href="#" class="text-decoration-none see-more">See More</a>
                        </div>
                    </div>

                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <input type="text" 
         id="logSearch" 
         class="form-control" 
         placeholder="Search by Errors, Message, Users or others"
         style="background-color:#faf9e5; 
                border:1px solid #e5e0a4; 
                border-radius:50px; 
                padding:0.6rem 1rem;">
                    </div>
                    <div class="col-md-6">
                        <select id="logTypeFilter" class="form-select" 
          style="background-color:#faf9e5; 
                 border:1px solid #e5e0a4; 
                 border-radius:50px; 
                 padding:0.55rem 1rem;">
                            <option value="">All Types</option>
                            <option value="Error">Errors</option>
                            <option value="Success">Success</option>
                            <option value="Warning">Warnings</option>
                            <option value="Info">Info</option>
                        </select>
                    </div>

                </div>
                <div class="row mt-4 g-3">

                    <div class="col-12">
                        <div class="card p-3" style="background-color:#f8f6ea; border:1px solid #e5e0a4;">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>BUS NUMBER: BUS-2341</strong>
                                <small>2026-01-20 09:45:23</small>
                            </div>
                            <p><strong>Logged by:</strong> Aurchie Rodriguez</p>
                            <p><strong>Status:</strong> <span class="text-danger">Error</span></p>
                            <p><strong>Reason:</strong> Engine temperature exceeded normal range (TEMPERATURE: 105°C)</p>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card p-3" style="background-color:#f8f6ea; border:1px solid #e5e0a4;">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>BUS NUMBER: BUS-2342</strong>
                                <small>2026-01-20 10:12:45</small>
                            </div>
                            <p><strong>Logged by:</strong> John Doe</p>
                            <p><strong>Status:</strong> <span class="text-warning">Warning</span></p>
                            <p><strong>Reason:</strong> Low fuel level (15%)</p>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card p-3" style="background-color:#f8f6ea; border:1px solid #e5e0a4;">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>BUS NUMBER: BUS-2343</strong>
                                <small>2026-01-20 11:05:10</small>
                            </div>
                            <p><strong>Logged by:</strong> Jane Smith</p>
                            <p><strong>Status:</strong> <span class="text-danger">Error</span></p>
                            <p><strong>Reason:</strong> Brake system alert</p>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card p-3" style="background-color:#f8f6ea; border:1px solid #e5e0a4;">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>BUS NUMBER: BUS-2344</strong>
                                <small>2026-01-20 11:45:52</small>
                            </div>
                            <p><strong>Logged by:</strong> Miguel Santos</p>
                            <p><strong>Status:</strong> <span class="text-warning">Warning</span></p>
                            <p><strong>Reason:</strong> Weak internet connection detected</p>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card p-3" style="background-color:#f8f6ea; border:1px solid #e5e0a4;">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>BUS NUMBER: BUS-2345</strong>
                                <small>2026-01-20 12:30:15</small>
                            </div>
                            <p><strong>Logged by:</strong> Carla Diaz</p>
                            <p><strong>Status:</strong> <span class="text-danger">Error</span></p>
                            <p><strong>Reason:</strong> High engine temperature (TEMPERATURE: 102°C)</p>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card p-3" style="background-color:#f8f6ea; border:1px solid #e5e0a4;">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>BUS NUMBER: BUS-2346</strong>
                                <small>2026-01-20 13:05:40</small>
                            </div>
                            <p><strong>Logged by:</strong> Leo Ramirez</p>
                            <p><strong>Status:</strong> <span class="text-info">Info</span></p>
                            <p><strong>Reason:</strong> System update applied successfully</p>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card p-3" style="background-color:#f8f6ea; border:1px solid #e5e0a4;">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>BUS NUMBER: BUS-2347</strong>
                                <small>2026-01-20 13:45:22</small>
                            </div>
                            <p><strong>Logged by:</strong> Anna Cruz</p>
                            <p><strong>Status:</strong> <span class="text-warning">Warning</span></p>
                            <p><strong>Reason:</strong> Low fuel level (10%)</p>
                        </div>
                    </div>

                </div>

            </div>
