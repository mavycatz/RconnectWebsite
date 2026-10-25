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
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">


        <style>
            html, body {
                background-color: #eceed9 !important;
            }
            body {
                margin: 0;
                display: flex;
                min-height: 100vh;
            }
            * {
                font-family: 'Poppins', sans-serif;
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
            .pro-modal {
                border-radius: 18px;
                overflow: hidden;
                border: none;
                box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            }

            .pro-header {
                background: linear-gradient(90deg, #b8e405, #005a15);
                color: white;
                padding: 20px;
                border: none;
            }

            .pro-section {
                background: #f8faf4;
                padding: 20px;
                border-radius: 14px;
                margin-bottom: 20px;
                border: 1px solid #e3ecd9;
            }

            .section-title {
                font-weight: 600;
                font-size: 0.9rem;
                text-transform: uppercase;
                color: #295738;
                margin-bottom: 15px;
            }

            .pro-input {
                border-radius: 10px;
                border: 1px solid #d5e2c8;
                transition: 0.2s;
            }

            .pro-input:focus {
                border-color: #005a15;
                box-shadow: 0 0 0 0.2rem rgba(0, 90, 21, 0.15);
            }

            .pro-footer {
                background: #f1f5eb;
                border: none;
            }

            .pro-submit {
                background: linear-gradient(90deg, #b8e405, #005a15);
                border: none;
                color: white;
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
      Drivers
            </div>
        </div>
        <div class="row g-3 mb-4">

            <div class="col-md-3">
                <div class="card"
         style="background:#faf9e5;
                border:1px solid #e5e0a4;
                border-radius:10px;
                padding:14px;">

                    <div class="text-center"
           style="font-size:0.75rem;
                  font-weight:600;
                  text-transform:uppercase;
                  letter-spacing:0.4px;
                  color:#295738;">
        Total Drivers
                    </div>

                    <div class="d-flex align-items-center justify-content-between mt-2">
                        <i class="bi bi-people-fill"
           style="font-size:2.4rem; color:#295738;"></i>

                            <div style="font-size:1.7rem;
                    font-weight:700;
                    color:#295738;">
          24
                            </div>
                        </div>

                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card"
         style="background:#faf9e5;
                border:1px solid #e5e0a4;
                border-radius:10px;
                padding:14px;">

                        <div class="text-center"
           style="font-size:0.75rem;
                  font-weight:600;
                  text-transform:uppercase;
                  letter-spacing:0.4px;
                  color:#295738;">
        Active Drivers
                        </div>

                        <div class="d-flex align-items-center justify-content-between mt-2">
                            <i class="bi bi-person-check-fill"
           style="font-size:2.4rem; color:#295738;"></i>

                                <div style="font-size:1.7rem;
                    font-weight:700;
                    color:#295738;">
          20
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card"
         style="background:#faf9e5;
                border:1px solid #e5e0a4;
                border-radius:10px;
                padding:14px;">

                            <div class="text-center"
           style="font-size:0.75rem;
                  font-weight:600;
                  text-transform:uppercase;
                  letter-spacing:0.4px;
                  color:#295738;">
        Inactive Drivers
                            </div>

                            <div class="d-flex align-items-center justify-content-between mt-2">
                                <i class="bi bi-person-dash-fill"
           style="font-size:2.4rem; color:#295738;"></i>

                                    <div style="font-size:1.7rem;
                    font-weight:700;
                    color:#295738;">
          3
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card"
         style="background:#faf9e5;
                border:1px solid #e5e0a4;
                border-radius:10px;
                padding:14px;">

                                <div class="text-center"
           style="font-size:0.75rem;
                  font-weight:600;
                  text-transform:uppercase;
                  letter-spacing:0.4px;
                  color:#295738;">
        Pending Drivers
                                </div>

                                <div class="d-flex align-items-center justify-content-between mt-2">
                                    <i class="bi bi-person-plus-fill"
           style="font-size:2.4rem; color:#295738;"></i>

                                        <div style="font-size:1.7rem;
                    font-weight:700;
                    color:#295738;">
          1
                                        </div>
                                    </div>

                                </div>
                            </div>

                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">All Drivers</h5>
                            <button
    class="btn text-white"
    style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%);border:none;"
    data-bs-toggle="modal"
    data-bs-target="#addDriverModal">
    Add New Driver
                            </button>
                        </div>
                        <div style="width:100%; overflow:hidden;">

                            <div style="
        display:flex;
        gap:16px;
        overflow-x:auto;
        padding-bottom:16px;
        -webkit-overflow-scrolling: touch;
        scroll-behavior: smooth;
      ">

                                <style>
                                    div::-webkit-scrollbar { display: none; }
                                </style>

                                <div style="flex:0 0 auto; width:320px; background:#f8f6ea; border:1px solid #e5e0a4; border-radius:.8rem; box-shadow:0 3px 8px rgba(0,0,0,.12); padding:16px;">
                                    <div style="display:flex; gap:12px;">
                                        <img src="../assets/images/admin/driversinfo/drivericon.png"
             style="width:64px; height:64px; border-radius:50%; object-fit:cover;">

                                        <div style="flex:1;">
                                            <h6 style="margin-bottom:6px; font-weight:600;">
            Jomar Bautista
                                                <small style="color:#6c757d; font-weight:400;">ID: RPM1234</small>
                                            </h6>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:700;">Status:</span>
                                                <span style="color:#198754;"> Active</span>
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:700;">License:</span>
            12345678910 | Exp: June 18, 2029
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:700;">License Type:</span>
            Professional
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:700;">Experience:</span>
            5 Years
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:700;">Assigned Bus(es):</span>
            BUS-123 & BUS-456
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:700;">Routes:</span>
            Cabanatuan – Rizal
                                            </p>

                                            <p style="margin:0 0 8px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:700;">Email:</span>
            jomarbautista@gmail.com | Contact: 09123456789
                                            </p>

                                            <div style="display:flex; justify-content:flex-end; gap:8px;">
                                                <button class="btn btn-sm text-white"
              style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%);border:none;">
              Edit
                                                </button>
                                                <button class="btn btn-sm text-white"
              style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%);border:none;">
              Archive
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div style="flex:0 0 auto; width:320px; background:#f8f6ea; border:1px solid #e5e0a4; border-radius:.8rem; box-shadow:0 3px 8px rgba(0,0,0,.12); padding:16px;">
                                    <div style="display:flex; gap:12px;">
                                        <img src="../assets/images/admin/driversinfo/drivericon.png"
             style="width:64px; height:64px; border-radius:50%; object-fit:cover;">

                                        <div style="flex:1;">
                                            <h6 style="margin-bottom:6px; font-weight:600;">
            Nelmar Panganiban
                                                <small style="color:#6c757d; font-weight:400;">ID: RPM456</small>
                                            </h6>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:700;">Status:</span>
                                                <span style="color:#198754;"> Active</span>
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:700;">License:</span>
            2233445566 | Exp: May 12, 2030
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:700;">License Type:</span>
            Non-Professional
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:700;">Experience:</span>
            3 Years
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:700;">Assigned Bus(es):</span>
            BUS-123
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:700;">Routes:</span>
            Cabanatuan – Rizal
                                            </p>

                                            <p style="margin:0 0 8px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:700;">Email:</span>
            nelmar@gmail.com | Contact: 09112223344
                                            </p>

                                            <div style="display:flex; justify-content:flex-end; gap:8px;">
                                                <button class="btn btn-sm text-white"
              style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%);border:none;">
              Edit
                                                </button>
                                                <button class="btn btn-sm text-white"
              style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%);border:none;">
              Archive
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div style="flex:0 0 auto; width:320px; background:#f8f6ea; border:1px solid #e5e0a4; border-radius:.8rem; box-shadow:0 3px 8px rgba(0,0,0,.12); padding:16px;">
                                    <div style="display:flex; gap:12px;">
                                        <img src="../assets/images/admin/driversinfo/drivericon.png"
             style="width:64px; height:64px; border-radius:50%; object-fit:cover;">

                                        <div style="flex:1;">
                                            <h6 style="margin-bottom:6px; font-weight:600;">
            Manuel Torres
                                                <small style="color:#6c757d; font-weight:400;">ID: RPM789</small>
                                            </h6>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:700;">Status:</span>
                                                <span style="color:#198754;"> Active</span>
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:700;">License:</span>
            3344556677 | Exp: Dec 12, 2030
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:700;">License Type:</span>
            Professional
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:700;">Experience:</span>
            8 Years
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:700;">Assigned Bus(es):</span>
            BUS-789
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:700;">Routes:</span>
            Rizal – Cabanatuan
                                            </p>

                                            <p style="margin:0 0 8px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:700;">Email:</span>
            manuel@gmail.com | Contact: 09223334455
                                            </p>

                                            <div style="display:flex; justify-content:flex-end; gap:8px;">
                                                <button class="btn btn-sm text-white"
              style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%);border:none;">
              Edit
                                                </button>
                                                <button class="btn btn-sm text-white"
              style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%);border:none;">
              Archive
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                        <div class="modal fade" id="addDriverModal" tabindex="-1">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content pro-modal">

                                    <div class="modal-header pro-header">
                                        <div>
                                            <h5 class="modal-title mb-0">Add New Driver</h5>
                                            <small class="text-white-50">Register a new operator into the transport system</small>
                                        </div>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>

                                    <div class="modal-body">

                                        <form>
                                            <div class="container-fluid">

                                                <div class="pro-section">
                                                    <h6 class="section-title">Driver Identity</h6>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Driver Name</label>
                                                            <input type="text" class="form-control pro-input" placeholder="Full legal name">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Driver ID</label>
                                                            <input type="text" class="form-control pro-input" placeholder="System ID">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Contact Number</label>
                                                            <input type="text" class="form-control pro-input">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Email Address</label>
                                                            <input type="email" class="form-control pro-input">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="pro-section">
                                                    <h6 class="section-title">License & Compliance</h6>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">License Number</label>
                                                            <input type="text" class="form-control pro-input">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Expiration Date</label>
                                                            <input type="date" class="form-control pro-input">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">License Type</label>
                                                            <select class="form-select pro-input">
                                                                <option>Select Type</option>
                                                                <option>Professional</option>
                                                                <option>Non-Professional</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Driving Experience (Years)</label>
                                                            <input type="number" class="form-control pro-input">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="pro-section">
                                                    <h6 class="section-title">Operational Assignment</h6>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Assigned Bus(es)</label>
                                                            <input type="text" class="form-control pro-input">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Assigned Route(s)</label>
                                                            <input type="text" class="form-control pro-input">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="pro-section">
                                                    <h6 class="section-title">Documents</h6>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Profile Picture</label>
                                                            <input type="file" class="form-control pro-input">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">License ID</label>
                                                            <input type="file" class="form-control pro-input">
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label">Additional Notes</label>
                  <textarea class="form-control pro-input" rows="3"></textarea>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </form>

                                    </div>

                                    <div class="modal-footer pro-footer">
                                        <button class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
          Cancel
                                        </button>
                                        <button class="btn pro-submit rounded-pill px-4">
          Add Driver
                                        </button>
                                    </div>

                                </div>
                            </div>
                        </div>
                        <div class="modal fade" id="addShiftModal" tabindex="-1">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content pro-modal">

                                    <div class="modal-header pro-header">
                                        <div>
                                            <h5 class="modal-title mb-0">Add New Shift</h5>
                                            <small class="text-white-50">Create and assign a new operational shift</small>
                                        </div>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>

                                    <div class="modal-body">
                                        <form id="shiftForm">
                                            <div class="container-fluid">

                                                <div class="pro-section">
                                                    <h6 class="section-title">Shift Information</h6>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Shift Name</label>
                                                            <input type="text" class="form-control pro-input" placeholder="e.g. Morning Shift" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Time Schedule</label>
                                                            <input type="text" class="form-control pro-input" placeholder="e.g. 6:00 AM - 2:00 PM" required>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="pro-section">
                                                    <h6 class="section-title">Assigned Vehicle</h6>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Vehicle</label>
                                                            <input type="text" class="form-control pro-input" placeholder="Enter bus number" required>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="pro-section">
                                                    <h6 class="section-title">Assigned Drivers</h6>
                                                    <div class="row g-3">
                                                        <div class="col-md-4">
                                                            <label class="form-label">Primary Driver</label>
                                                            <input type="text" class="form-control pro-input">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Backup Driver</label>
                                                            <input type="text" class="form-control pro-input">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Relief Driver</label>
                                                            <input type="text" class="form-control pro-input">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="pro-section">
                                                    <h6 class="section-title">Additional Notes</h6>
                                                    <div class="row g-3">
                                                        <div class="col-12">
                  <textarea class="form-control pro-input" rows="3" placeholder="Enter shift remarks or special instructions"></textarea>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </form>
                                    </div>

                                    <div class="modal-footer pro-footer">
                                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
          Cancel
                                        </button>
                                        <button type="button" class="btn pro-submit rounded-pill px-4" id="saveShiftBtn">
          Add Shift
                                        </button>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <div class="modal fade" id="addTerminalModal" tabindex="-1">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content pro-modal">

                                    <div class="modal-header pro-header">
                                        <div>
                                            <h5 class="modal-title mb-0">Add New Terminal</h5>
                                            <small class="text-white-50">
            Register and configure a new transport terminal
                                            </small>
                                        </div>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>

                                    <div class="modal-body">
                                        <form id="terminalForm">
                                            <div class="container-fluid">

                                                <div class="pro-section">
                                                    <h6 class="section-title">Terminal Information</h6>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Terminal Name</label>
                                                            <input type="text" class="form-control pro-input" placeholder="Enter terminal name" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Location</label>
                                                            <input type="text" class="form-control pro-input" placeholder="City / Area / Landmark">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="pro-section">
                                                    <h6 class="section-title">Route & Vehicle Assignment</h6>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Route</label>
                                                            <input type="text" class="form-control pro-input" placeholder="Assigned route" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Primary Vehicle</label>
                                                            <input type="text" class="form-control pro-input" placeholder="Assigned bus number" required>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="pro-section">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <h6 class="section-title mb-0">Assigned Bus(es)</h6>
                                                        <button type="button" 
                        class="btn btn-outline-success rounded-circle"
                        style="width:36px; height:36px; padding:0;">
                                                            <i class="bi bi-plus-lg"></i>
                                                        </button>
                                                    </div>

                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <input type="text" class="form-control pro-input" placeholder="Enter bus number">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <input type="text" class="form-control pro-input" placeholder="Enter bus number">
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </form>
                                    </div>

                                    <div class="modal-footer pro-footer">
                                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
          Cancel
                                        </button>
                                        <button type="button" class="btn pro-submit rounded-pill px-4">
          Add Terminal
                                        </button>
                                    </div>

                                </div>
                            </div>
                        </div>
                        <div class="modal fade" id="addShiftModal" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Add New Shift</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="shiftForm">
                                            <div class="row g-3">
                                                <div class="col-md-3"><input type="text" class="form-control" placeholder="Shift Name" required></div>
                                                <div class="col-md-3"><input type="text" class="form-control" placeholder="Time" required></div>
                                                <div class="col-md-2"><input type="text" class="form-control" placeholder="Vehicle" required></div>
                                                <div class="col-md-2"><input type="text" class="form-control" placeholder="Driver 1"></div>
                                                <div class="col-md-2"><input type="text" class="form-control" placeholder="Driver 2"></div>
                                                <div class="col-md-2"><input type="text" class="form-control" placeholder="Driver 3"></div>
                                                <div class="col-md-12"><input type="text" class="form-control" placeholder="Notes"></div>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-success" id="saveShiftBtn">Add Shift</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <h5 class="mt-5 mb-3">Schedule and Assignment Management</h5>

                        <div class="card p-2 mb-3" style="background-color:#fffbe6; border:1px solid #e5e0a4; border-radius:0.8rem; font-size:.95rem;">
                            <p style="margin:0;">
    Manage driver shifts and terminal assignments efficiently. 
    Use the buttons to add new shifts or terminals. Keep schedules updated for smooth operations.
                            </p>
                        </div>

                        <div class="row g-3 d-flex align-items-stretch">

                            <div class="col-lg-6 col-12 d-flex">
                                <div class="card p-3 flex-fill" style="background-color:#faf9e5; border:1px solid #e5e0a4; border-radius:1rem;">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">Shift Schedule</h6>
                                        <button class="btn btn-sm text-white"
                style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); border:none;"
                data-bs-toggle="modal" data-bs-target="#addShiftModal">
          + Add Shift
                                        </button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover text-center" style="font-size:.9rem;">
                                            <thead style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); color:white;">
                                                <tr>
                                                    <th>Shift</th>
                                                    <th>Time</th>
                                                    <th>Vehicle</th>
                                                    <th>Driver 1</th>
                                                    <th>Driver 2</th>
                                                    <th>Notes</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="shiftTableBody">
                                                <tr>
                                                    <td>Day</td>
                                                    <td>6:00 AM - 2:00 PM</td>
                                                    <td>Bus-101</td>
                                                    <td>Manuel Torres</td>
                                                    <td>Ramon Bautista</td>
                                                    <td>High passenger volume</td>
                                                    <td><button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); border:none;">Edit</button></td>
                                                </tr>
                                                <tr>
                                                    <td>Night</td>
                                                    <td>10:00 PM - 6:00 AM</td>
                                                    <td>Bus-102</td>
                                                    <td>Nestor Villanuevo</td>
                                                    <td>Albert Reyes</td>
                                                    <td>Security patrol</td>
                                                    <td><button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); border:none;">Edit</button></td>
                                                </tr>
                                                <tr>
                                                    <td>Rotating</td>
                                                    <td>6:00 AM - 2:00 PM / 12:00 AM - 10:00 PM</td>
                                                    <td>Bus-103</td>
                                                    <td>John Mercado</td>
                                                    <td>Fedoras Santos</td>
                                                    <td>Alternate schedule</td>
                                                    <td><button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); border:none;">Edit</button></td>
                                                </tr>
                                                <tr>
                                                    <td>Day</td>
                                                    <td>7:00 AM - 3:00 PM</td>
                                                    <td>Bus-104</td>
                                                    <td>Michael Tan</td>
                                                    <td>Jason Cruz</td>
                                                    <td>Morning rush</td>
                                                    <td><button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); border:none;">Edit</button></td>
                                                </tr>
                                                <tr>
                                                    <td>Night</td>
                                                    <td>11:00 PM - 7:00 AM</td>
                                                    <td>Bus-105</td>
                                                    <td>Elena Reyes</td>
                                                    <td>Victor Lim</td>
                                                    <td>Late night maintenance</td>
                                                    <td><button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); border:none;">Edit</button></td>
                                                </tr>
                                                <tr>
                                                    <td>Day</td>
                                                    <td>5:30 AM - 1:30 PM</td>
                                                    <td>Bus-106</td>
                                                    <td>Grace Santos</td>
                                                    <td>Daniel Ortega</td>
                                                    <td>Express route</td>
                                                    <td><button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); border:none;">Edit</button></td>
                                                </tr>
                                                <tr>
                                                    <td>Rotating</td>
                                                    <td>2:00 PM - 10:00 PM / 12:00 AM - 8:00 AM</td>
                                                    <td>Bus-107</td>
                                                    <td>Henry Cruz</td>
                                                    <td>Fedoras Santos</td>
                                                    <td>Alternate shifts</td>
                                                    <td><button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); border:none;">Edit</button></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6 col-12 d-flex">
                                <div class="card p-3 flex-fill" style="background-color:#faf9e5; border:1px solid #e5e0a4; border-radius:1rem;">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">Terminal Assignments</h6>
                                        <button class="btn btn-sm text-white"
                style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); border:none;"
                data-bs-toggle="modal" data-bs-target="#addTerminalModal">
          + Add Terminal
                                        </button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover text-center" style="font-size:.9rem;">
                                            <thead style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); color:white;">
                                                <tr>
                                                    <th>Terminal</th>
                                                    <th>Route</th>
                                                    <th>Vehicle</th>
                                                    <th>Driver 1</th>
                                                    <th>Driver 2</th>
                                                    <th>Location</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="terminalTableBody">
                                                <tr>
                                                    <td>Terminal A</td>
                                                    <td>Route 1</td>
                                                    <td>Bus-101</td>
                                                    <td>Manuel Torres</td>
                                                    <td>Ramon Bautista</td>
                                                    <td>Central Hub</td>
                                                    <td><button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); border:none;">Edit</button></td>
                                                </tr>
                                                <tr>
                                                    <td>Terminal B</td>
                                                    <td>Route 2</td>
                                                    <td>Bus-102</td>
                                                    <td>Nestor Villanuevo</td>
                                                    <td>Albert Reyes</td>
                                                    <td>North Side</td>
                                                    <td><button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); border:none;">Edit</button></td>
                                                </tr>
                                                <tr>
                                                    <td>Terminal C</td>
                                                    <td>Route 3</td>
                                                    <td>Bus-103</td>
                                                    <td>John Mercado</td>
                                                    <td>Fedoras Santos</td>
                                                    <td>South Terminal</td>
                                                    <td><button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); border:none;">Edit</button></td>
                                                </tr>
                                                <tr>
                                                    <td>Terminal D</td>
                                                    <td>Route 4</td>
                                                    <td>Bus-104</td>
                                                    <td>Michael Tan</td>
                                                    <td>Jason Cruz</td>
                                                    <td>East Terminal</td>
                                                    <td><button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); border:none;">Edit</button></td>
                                                </tr>
                                                <tr>
                                                    <td>Terminal E</td>
                                                    <td>Route 5</td>
                                                    <td>Bus-105</td>
                                                    <td>Elena Reyes</td>
                                                    <td>Victor Lim</td>
                                                    <td>West Side</td>
                                                    <td><button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); border:none;">Edit</button></td>
                                                </tr>
                                                <tr>
                                                    <td>Terminal F</td>
                                                    <td>Route 6</td>
                                                    <td>Bus-106</td>
                                                    <td>Grace Santos</td>
                                                    <td>Daniel Ortega</td>
                                                    <td>North Hub</td>
                                                    <td><button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); border:none;">Edit</button></td>
                                                </tr>
                                                <tr>
                                                    <td>Terminal G</td>
                                                    <td>Route 7</td>
                                                    <td>Bus-107</td>
                                                    <td>Henry Cruz</td>
                                                    <td>Fedoras Santos</td>
                                                    <td>South Hub</td>
                                                    <td><button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); border:none;">Edit</button></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="modal fade" id="addShiftModal" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Add New Shift</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="shiftForm">
                                            <div class="row g-3">
                                                <div class="col-md-3"><input type="text" class="form-control" placeholder="Shift Name" required></div>
                                                <div class="col-md-3"><input type="text" class="form-control" placeholder="Time" required></div>
                                                <div class="col-md-2"><input type="text" class="form-control" placeholder="Vehicle" required></div>
                                                <div class="col-md-2"><input type="text" class="form-control" placeholder="Driver 1"></div>
                                                <div class="col-md-2"><input type="text" class="form-control" placeholder="Driver 2"></div>
                                                <div class="col-md-2"><input type="text" class="form-control" placeholder="Driver 3"></div>
                                                <div class="col-md-12"><input type="text" class="form-control" placeholder="Notes"></div>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-success" id="saveShiftBtn">Add Shift</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal fade" id="addTerminalModal" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Add New Terminal</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="terminalForm">
                                            <div class="row g-3">
                                                <div class="col-md-2"><input type="text" class="form-control" placeholder="Terminal Name" required></div>
                                                <div class="col-md-2"><input type="text" class="form-control" placeholder="Route" required></div>
                                                <div class="col-md-2"><input type="text" class="form-control" placeholder="Vehicle" required></div>
                                                <div class="col-md-2"><input type="text" class="form-control" placeholder="Driver 1"></div>
                                                <div class="col-md-2"><input type="text" class="form-control" placeholder="Driver 2"></div>
                                                <div class="col-md-2"><input type="text" class="form-control" placeholder="Driver 3"></div>
                                                <div class="col-md-12"><input type="text" class="form-control" placeholder="Location"></div>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-success" id="saveTerminalBtn">Add Terminal</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="container my-5 p-4" style="background:#faf9e5; border-radius:1rem; box-shadow:0 4px 12px rgba(0,0,0,0.1);">

                            <h5 class="mb-4">Access Control</h5>

                            <div class="table-responsive">
                                <table class="table table-bordered text-center align-middle" style="border-color:#e5e0a4;">
                                    <thead style="background-color:#f5f4e6;">
                                        <tr>
                                            <th>Module</th>
                                            <th>View</th>
                                            <th>Add</th>
                                            <th>Edit</th>
                                            <th>Delete</th>
                                        </tr>
                                    </thead>
                                    <tbody id="accessTable">
                                        <tr>
                                            <td>Dashboard</td>
                                            <td><button class="perm-btn active" data-module="Dashboard" data-perm="view">✔</button></td>
                                            <td><button class="perm-btn" data-module="Dashboard" data-perm="add">✖</button></td>
                                            <td><button class="perm-btn" data-module="Dashboard" data-perm="edit">✖</button></td>
                                            <td><button class="perm-btn" data-module="Dashboard" data-perm="delete">✖</button></td>
                                        </tr>
                                        <tr>
                                            <td>Trip Schedule</td>
                                            <td><button class="perm-btn active" data-module="Trip Schedule" data-perm="view">✔</button></td>
                                            <td><button class="perm-btn" data-module="Trip Schedule" data-perm="add">✖</button></td>
                                            <td><button class="perm-btn" data-module="Trip Schedule" data-perm="edit">✖</button></td>
                                            <td><button class="perm-btn" data-module="Trip Schedule" data-perm="delete">✖</button></td>
                                        </tr>
                                        <tr>
                                            <td>Trip Logs</td>
                                            <td><button class="perm-btn active" data-module="Trip Logs" data-perm="view">✔</button></td>
                                            <td><button class="perm-btn active" data-module="Trip Logs" data-perm="add">✔</button></td>
                                            <td><button class="perm-btn active" data-module="Trip Logs" data-perm="edit">✔</button></td>
                                            <td><button class="perm-btn" data-module="Trip Logs" data-perm="delete">✖</button></td>
                                        </tr>
                                        <tr>
                                            <td>Vehicle Status</td>
                                            <td><button class="perm-btn active" data-module="Vehicle Status" data-perm="view">✔</button></td>
                                            <td><button class="perm-btn active" data-module="Vehicle Status" data-perm="add">✔</button></td>
                                            <td><button class="perm-btn active" data-module="Vehicle Status" data-perm="edit">✔</button></td>
                                            <td><button class="perm-btn" data-module="Vehicle Status" data-perm="delete">✖</button></td>
                                        </tr>
                                        <tr>
                                            <td>Reports</td>
                                            <td><button class="perm-btn active" data-module="Reports" data-perm="view">✔</button></td>
                                            <td><button class="perm-btn" data-module="Reports" data-perm="add">✖</button></td>
                                            <td><button class="perm-btn" data-module="Reports" data-perm="edit">✖</button></td>
                                            <td><button class="perm-btn" data-module="Reports" data-perm="delete">✖</button></td>
                                        </tr>
                                        <tr>
                                            <td>User Management</td>
                                            <td><button class="perm-btn" data-module="User Management" data-perm="view">✖</button></td>
                                            <td><button class="perm-btn" data-module="User Management" data-perm="add">✖</button></td>
                                            <td><button class="perm-btn" data-module="User Management" data-perm="edit">✖</button></td>
                                            <td><button class="perm-btn" data-module="User Management" data-perm="delete">✖</button></td>
                                        </tr>
                                        <tr>
                                            <td>System Settings</td>
                                            <td><button class="perm-btn" data-module="System Settings" data-perm="view">✖</button></td>
                                            <td><button class="perm-btn" data-module="System Settings" data-perm="add">✖</button></td>
                                            <td><button class="perm-btn" data-module="System Settings" data-perm="edit">✖</button></td>
                                            <td><button class="perm-btn" data-module="System Settings" data-perm="delete">✖</button></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-3 d-flex gap-2">
                                <button id="undoBtn" class="btn btn-secondary">Undo</button>
                                <button id="redoBtn" class="btn btn-secondary">Redo</button>
                                <button id="saveBtn" class="btn btn-success">Save Changes</button>
                            </div>

                        </div>

                        <style>
                            .perm-btn {
                                width: 40px;
                                height: 40px;
                                border-radius: 50%;
                                border: none;
                                font-weight: bold;
                                cursor: pointer;
                                transition: all 0.2s;
                                background: #f0f0f0;
                                color: #333;
                            }
                            .perm-btn.active {
                                background: linear-gradient(90deg, #b8e405 0%, #005a15 100%);
                                color: white;
                            }
                            .perm-btn:hover {
                                transform: scale(1.1);
                            }
                            .table td, .table th {
                                vertical-align: middle;
                            }
                        </style>

                        <script>
    
                            const shiftTableBody = document.getElementById('shiftTableBody');
                            const terminalTableBody = document.getElementById('terminalTableBody');

                            document.getElementById('saveShiftBtn').addEventListener('click', () => {
                                const form = document.getElementById('shiftForm');
                                const data = Array.from(form.querySelectorAll('input')).map(i => i.value || '-');
                                const tr = document.createElement('tr');
                                tr.innerHTML = `
    <td>${data[0]}</td>
    <td>${data[1]}</td>
    <td>${data[2]}</td>
    <td>${data[3]}</td>
    <td>${data[4]}</td>
    <td>${data[5]}</td>
    <td>${data[6]}</td>
    <td>
      <button class="btn btn-sm btn-warning undoBtn">Undo</button>
      <button class="btn btn-sm btn-danger deleteBtn">Delete</button>
    </td>
  `;
                                shiftTableBody.appendChild(tr);
                                bootstrap.Modal.getInstance(document.getElementById('addShiftModal')).hide();
                                form.reset();
                            });

                            document.getElementById('saveTerminalBtn').addEventListener('click', () => {
                                const form = document.getElementById('terminalForm');
                                const data = Array.from(form.querySelectorAll('input')).map(i => i.value || '-');
                                const tr = document.createElement('tr');
                                tr.innerHTML = `
    <td>${data[0]}</td>
    <td>${data[1]}</td>
    <td>${data[2]}</td>
    <td>${data[3]}</td>
    <td>${data[4]}</td>
    <td>${data[5]}</td>
    <td>${data[6]}</td>
    <td>
      <button class="btn btn-sm btn-warning undoBtn">Undo</button>
      <button class="btn btn-sm btn-danger deleteBtn">Delete</button>
    </td>
  `;
                                terminalTableBody.appendChild(tr);
                                bootstrap.Modal.getInstance(document.getElementById('addTerminalModal')).hide();
                                form.reset();
                            });

                            document.addEventListener('click', function (e) {
                                if (e.target.classList.contains('undoBtn')) {
                                    e.target.closest('tr').remove();
                                }
                                if (e.target.classList.contains('deleteBtn')) {
                                    e.target.closest('tr').remove();
                                }
                            });

                            const accessTable = document.getElementById("accessTable");
                            let undoStack = [];
                            let redoStack = [];

                            accessTable.addEventListener("click", function (e) {
                                if (e.target.classList.contains("perm-btn")) {
                                    const btn = e.target;
                                    const prev = btn.classList.contains("active");
                                    undoStack.push({ btn, prev });
                                    redoStack = [];
                                    btn.classList.toggle("active");
                                    btn.textContent = btn.classList.contains("active") ? "✔" : "✖";
                                }
                            });

                            document.getElementById("undoBtn").addEventListener("click", () => {
                                if (undoStack.length) {
                                    const last = undoStack.pop();
                                    last.btn.classList.toggle("active", last.prev);
                                    last.btn.textContent = last.prev ? "✔" : "✖";
                                    redoStack.push(last);
                                }
                            });

                            document.getElementById("redoBtn").addEventListener("click", () => {
                                if (redoStack.length) {
                                    const last = redoStack.pop();
                                    last.btn.classList.toggle("active", !last.prev);
                                    last.btn.textContent = last.btn.classList.contains("active") ? "✔" : "✖";
                                    undoStack.push(last);
                                }
                            });

                            document.getElementById("saveBtn").addEventListener("click", () => {
                                const permissions = {};
                                accessTable.querySelectorAll("tr").forEach(row => {
                                    const module = row.cells[0].textContent;
                                    permissions[module] = {};
                                    ["view", "add", "edit", "delete"].forEach((perm, i) => {
                                        const btn = row.cells[i+1].querySelector("button");
                                        permissions[module][perm] = btn.classList.contains("active");
                                    });
                                });
                                console.log("Saved Permissions:", permissions);
                                alert("Permissions saved! Check console for details.");
                            });
  
                            const shiftTableBody = document.getElementById('shiftTableBody');
                            const terminalTableBody = document.getElementById('terminalTableBody');

                            document.getElementById('saveShiftBtn').addEventListener('click', () => {
                                const form = document.getElementById('shiftForm');
                                const data = Array.from(form.querySelectorAll('input')).map(i => i.value || '-');
                                const tr = document.createElement('tr');
                                tr.innerHTML = `
    <td>${data[0]}</td>
    <td>${data[1]}</td>
    <td>${data[2]}</td>
    <td>${data[3]}</td>
    <td>${data[4]}</td>
    <td>${data[5]}</td>
    <td>${data[6]}</td>
    <td>
      <button class="btn btn-sm btn-warning undoBtn">Undo</button>
      <button class="btn btn-sm btn-danger deleteBtn">Delete</button>
    </td>
  `;
                                shiftTableBody.appendChild(tr);
                                bootstrap.Modal.getInstance(document.getElementById('addShiftModal')).hide();
                                form.reset();
                            });

                            document.getElementById('saveTerminalBtn').addEventListener('click', () => {
                                const form = document.getElementById('terminalForm');
                                const data = Array.from(form.querySelectorAll('input')).map(i => i.value || '-');
                                const tr = document.createElement('tr');
                                tr.innerHTML = `
    <td>${data[0]}</td>
    <td>${data[1]}</td>
    <td>${data[2]}</td>
    <td>${data[3]}</td>
    <td>${data[4]}</td>
    <td>${data[5]}</td>
    <td>${data[6]}</td>
    <td>
      <button class="btn btn-sm btn-warning undoBtn">Undo</button>
      <button class="btn btn-sm btn-danger deleteBtn">Delete</button>
    </td>
  `;
                                terminalTableBody.appendChild(tr);
                                bootstrap.Modal.getInstance(document.getElementById('addTerminalModal')).hide();
                                form.reset();
                            });

                            document.addEventListener('click', function (e) {
                                if (e.target.classList.contains('undoBtn')) {
                                    e.target.closest('tr').remove();
                                }
                                if (e.target.classList.contains('deleteBtn')) {
                                    e.target.closest('tr').remove();
                                }
                            });

                        </script>
                        <script>
                            const TIMEOUT = 900000;
                            let logoutTimer;

                            function resetTimer() {
                                clearTimeout(logoutTimer);
                                logoutTimer = setTimeout(() => {
                                    window.location.href = "/public/login/index.php?expired=1";
                                }, TIMEOUT);
                            }

                            window.onload = resetTimer;
                            document.onmousemove = resetTimer;
                            document.onkeydown = resetTimer;
                            document.onclick = resetTimer;
                            document.onscroll = resetTimer;
                        </script>
