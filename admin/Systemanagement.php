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
$currentPage = basename($_SERVER['PHP_SELF']);
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
       
       
        <div class="d-flex justify-content-between align-items-center mb-3">

            <div style="
        font-weight:700;
        font-size:1.3rem;
        font-family:'Poppins', sans-serif;
        color:#295738;">
      Real Time System Logging
            </div>
        </div>
     
        <div class="mt-4"
     style="background:#ffffff;
            border:1px solid #e5e0a4;
            border-radius:1rem;
            padding:20px;">

            <div style="
        background:#f8f9f5;
        border:1px solid #e5e0a4;
        border-radius:12px;
        padding:15px;
        height:320px;
        overflow-y:auto;
        font-family:monospace;
        font-size:0.85rem;
        line-height:1.6;
      ">

                <div>
                    <span style="color:#dc3545; font-weight:700;">[ERROR]</span>
                    <span style="color:#6c757d;">[2026-01-20 08:14:32]</span>
      QR SCAN ERROR!
                </div>
                <div style="margin-left:15px;">
      Invalid QR code scanned during active trip on Route A.
                </div>
                <div style="margin-left:15px;">
      The QR code has already expired and is no longer valid.
                </div>
                <div style="margin-left:15px; margin-bottom:12px;">
      Scan attempt was rejected and logged.
                </div>


                <div>
                    <span style="color:#198754; font-weight:700;">[SUCCESS]</span>
                    <span style="color:#6c757d;">[2026-01-20 08:16:05]</span>
      QR Scan Successful – BUS-2341
                </div>
                <div style="margin-left:15px; margin-bottom:12px;">
      Passenger category: Regular | Route: Cabanatuan-Rizal
                </div>


                <div>
                    <span style="color:#0d6efd; font-weight:700;">[MONITOR]</span>
                    <span style="color:#6c757d;">[2026-01-20 08:18:44]</span>
      GPS Update Received
                </div>
                <div style="margin-left:15px; margin-bottom:12px;">
      BUS-8765 location updated near Homestead 1
                </div>


                <div>
                    <span style="color:#d4a017; font-weight:700;">[WARNING]</span>
                    <span style="color:#6c757d;">[2026-01-20 08:21:09]</span>
      Low Fuel Alert
                </div>
                <div style="margin-left:15px; margin-bottom:12px;">
      BUS-4421 fuel level dropped below 25%
                </div>


                <div>
                    <span style="color:#6f42c1; font-weight:700;">[SYSTEM]</span>
                    <span style="color:#6c757d;">[2026-01-20 08:25:11]</span>
      Admin Access Log
                </div>
                <div style="margin-left:15px;">
      User "superadmin" accessed Trip Dashboard
                </div>

            </div>

        </div>

        <div class="d-flex justify-content-start my-3" style="width:100%;">
            <div style="position:relative; width:80%;">

                <i class="bi bi-search"
       style="
          position:absolute;
          left:15px;
          top:50%;
          transform:translateY(-50%);
          color:#295738;
          font-size:0.95rem;
       "></i>

                    <input type="text"
           placeholder="Search by Errors, User, Monitor"
           class="form-control"
           style="
              width:100%;
              background:#faf9e5;
              border:1px solid #e5e0a4;
              border-radius:30px;
              padding:10px 18px 10px 40px;
              font-size:0.95rem;
              outline:none;
           ">
                </div>
            </div>
            <div class="mt-4 d-flex flex-column gap-3">

                <div style="
        background:rgba(220, 53, 69, 0.08);
        border-left:5px solid #dc3545;
        padding:14px 18px;
        border-radius:0.6rem;">
    
                    <div style="font-weight:700; color:#dc3545;">
      QR SCAN ERROR!
                    </div>

                    <div style="margin:4px 0;">
      Invalid QR code scanned during active trip on Route A.
      The QR code has already expired and is no longer valid.
      Scan attempt was rejected and logged.
                    </div>

                    <div style="color:#6c757d; font-size:0.85rem;">
      [2026-01-20 08:14:32]
                    </div>
                </div>


                <div style="
        background:rgba(111, 66, 193, 0.08);
        border-left:5px solid #6f42c1;
        padding:14px 18px;
        border-radius:0.6rem;">
    
                    <div style="font-weight:700; color:#6f42c1;">
      USER ACCESS LOG
                    </div>

                    <div style="margin:4px 0;">
      Admin user "superadmin" logged in and accessed Trip Dashboard.
                    </div>

                    <div style="color:#6c757d; font-size:0.85rem;">
      [2026-01-20 08:16:11]
                    </div>
                </div>


                <div style="
        background:rgba(13, 110, 253, 0.08);
        border-left:5px solid #0d6efd;
        padding:14px 18px;
        border-radius:0.6rem;">
    
                    <div style="font-weight:700; color:#0d6efd;">
      GPS MONITOR UPDATE
                    </div>

                    <div style="margin:4px 0;">
      Real-time GPS signal received from BUS-8765.
      Location updated near Homestead 1.
                    </div>

                    <div style="color:#6c757d; font-size:0.85rem;">
      [2026-01-20 08:18:44]
                    </div>
                </div>


                <div style="
        background:rgba(212, 160, 23, 0.10);
        border-left:5px solid #d4a017;
        padding:14px 18px;
        border-radius:0.6rem;">
    
                    <div style="font-weight:700; color:#d4a017;">
      LOW FUEL WARNING
                    </div>

                    <div style="margin:4px 0;">
      BUS-4421 fuel level dropped below 25%.
      Fleet supervisor has been notified.
                    </div>

                    <div style="color:#6c757d; font-size:0.85rem;">
      [2026-01-20 08:21:09]
                    </div>
                </div>


                <div style="
        background:rgba(25, 135, 84, 0.08);
        border-left:5px solid #198754;
        padding:14px 18px;
        border-radius:0.6rem;">
    
                    <div style="font-weight:700; color:#198754;">
      QR SCAN SUCCESS
                    </div>

                    <div style="margin:4px 0;">
      Passenger QR validated successfully on BUS-2341.
      Trip continues without interruption.
                    </div>

                    <div style="color:#6c757d; font-size:0.85rem;">
      [2026-01-20 08:23:17]
                    </div>
                </div>

            </div>