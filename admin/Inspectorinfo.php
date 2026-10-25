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
        <title>Inspectors Dashboard</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/chart.js">        </script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

        <style>
            html, body {
                background-color: #eceed9 !important;
            }
            body {
                margin: 0;
                display: flex;
                min-height: 100vh;
                font-family: 'Poppins', sans-serif;
            }
            .main-content {
                flex-grow: 1;
                padding: 20px;
                margin-left: 0;
                margin-top: 60px;
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
                z-index: 1001;
            }
            .header-left input { width: 700px; }
            .icon-container {
                width: 40px;
                height: 40px;
                border: 2px solid #86858f;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .icon { color: #295738; font-size: 20px; }
            #map { position: relative; z-index: 1; }
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
            .inspector-scroll {
                display: flex;
                gap: 16px;
                overflow-x: auto;
                padding-bottom: 16px;
                -webkit-overflow-scrolling: touch;
                scroll-behavior: smooth;
            }
            .inspector-scroll::-webkit-scrollbar { display: none; }
            .inspector-card {
                flex: 0 0 auto;
                width: 320px;
                background: #f1f3f8;
                border: 1px solid #c5cbe3;
                border-radius: .8rem;
                box-shadow: 0 3px 8px rgba(0, 0, 0, .12);
                padding: 16px;
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
            }

            .pro-input {
                border-radius: 10px;
                border: 1px solid #d5e2c8;
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
      Inspectors
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
        Total Inspectors
                    </div>

                    <div class="d-flex align-items-center justify-content-between mt-2">
                        <i class="bi bi-person-badge-fill"
           style="font-size:2.4rem; color:#295738;"></i>

                            <div style="font-size:1.7rem;
                    font-weight:700;
                    color:#295738;">
          12
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
        Active Inspectors
                        </div>

                        <div class="d-flex align-items-center justify-content-between mt-2">
                            <i class="bi bi-person-check-fill"
           style="font-size:2.4rem; color:#295738;"></i>

                                <div style="font-size:1.7rem;
                    font-weight:700;
                    color:#295738;">
          9
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
        Inactive Inspectors
                            </div>

                            <div class="d-flex align-items-center justify-content-between mt-2">
                                <i class="bi bi-person-dash-fill"
           style="font-size:2.4rem; color:#295738;"></i>

                                    <div style="font-size:1.7rem;
                    font-weight:700;
                    color:#295738;">
          2
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
        Pending Inspectors
                                </div>

                                <div class="d-flex align-items-center justify-content-between mt-2">
                                    <i class="bi bi-hourglass-split"
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
                            <h5 class="mb-0">All Inspectors</h5>
                            <button class="btn text-white"
        style="background:linear-gradient(90deg, #b8e405, #005a15);
               border:none;
               border-radius:25px;
               padding:8px 20px;
               font-weight:600;
               box-shadow:0 4px 10px rgba(0,90,21,0.25);"
        data-bs-toggle="modal"
        data-bs-target="#addInspectorModal">
  Add New Inspector
                            </button>
                        </div>

                        <div style="width:100%; overflow:hidden;">
                            <div class="inspector-scroll">

                                <div class="inspector-card">
                                    <div style="display:flex; gap:12px;">
                                        <img src="../assets/images/admin/driversinfo/drivericon.png"
             style="width:64px; height:64px; border-radius:50%; object-fit:cover;">

                                        <div style="flex:1; overflow:hidden;">
                                            <h6 style="margin-bottom:6px; font-weight:600;">
            Maria Santos 
                                                <small style="color:#6c757d; font-weight:400;">ID: INSP1001</small>
                                            </h6>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
            Status: <span style="color:#198754;">Active</span>
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:500;">Department:</span> Safety Inspection
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:500;">Experience:</span> 6 Years
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:500;">Assigned Routes:</span> Cabanatuan – Rizal
                                            </p>

                                            <p style="margin:0 0 8px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:500;">Email:</span> maria.santos@gmail.com 
            | Contact: 09123456789
                                            </p>

                                            <div style="display:flex; justify-content:flex-end; gap:8px;">
                                                <button class="btn btn-sm text-white"
                    style="background:linear-gradient(90deg, #b8e405, #005a15);
                           border:none;
                           border-radius:20px;
                           padding:5px 14px;
                           font-weight:500;">
              Edit
                                                </button>

                                                <button class="btn btn-sm text-white"
                    style="background:linear-gradient(90deg, #9edc00, #006b1a);
                           border:none;
                           border-radius:20px;
                           padding:5px 14px;
                           font-weight:500;">
              Archive
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="inspector-card">
                                    <div style="display:flex; gap:12px;">
                                        <img src="../assets/images/admin/driversinfo/drivericon.png"
             style="width:64px; height:64px; border-radius:50%; object-fit:cover;">

                                        <div style="flex:1;">
                                            <h6 style="margin-bottom:6px; font-weight:600;">
            Juan Dela Cruz 
                                                <small style="color:#6c757d; font-weight:400;">ID: INSP1002</small>
                                            </h6>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
            Status: <span style="color:#198754;">Active</span>
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:500;">Department:</span> Vehicle Inspection
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:500;">Experience:</span> 4 Years
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:500;">Assigned Routes:</span> Cabanatuan – Rizal
                                            </p>

                                            <p style="margin:0 0 8px; font-size:0.85rem;">
                                                <span style="color:#295738; font-weight:500;">Email:</span> juan.delacruz@gmail.com 
            | Contact: 09112223344
                                            </p>

                                            <div style="display:flex; justify-content:flex-end; gap:8px;">
                                                <button class="btn btn-sm text-white"
                    style="background:linear-gradient(90deg, #b8e405, #005a15);
                           border:none;
                           border-radius:20px;
                           padding:5px 14px;
                           font-weight:500;">
              Edit
                                                </button>

                                                <button class="btn btn-sm text-white"
                    style="background:linear-gradient(90deg, #9edc00, #006b1a);
                           border:none;
                           border-radius:20px;
                           padding:5px 14px;
                           font-weight:500;">
              Archive
                                                </button>
                                            </div>

                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                        <div class="modal fade" id="addInspectorModal" tabindex="-1">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content pro-modal">

                                    <div class="modal-header pro-header">
                                        <div>
                                            <h5 class="modal-title mb-0">Add New Inspector</h5>
                                            <small class="text-white-50">
            Register and manage inspection personnel
                                            </small>
                                        </div>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>

                                    <div class="modal-body">
                                        <form id="inspectorForm">
                                            <div class="container-fluid">

                                                <div class="pro-section">
                                                    <h6 class="section-title">Inspector Identity</h6>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Inspector Name</label>
                                                            <input type="text" class="form-control pro-input" placeholder="Full name" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Inspector ID</label>
                                                            <input type="text" class="form-control pro-input" placeholder="System ID" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Department</label>
                                                            <input type="text" class="form-control pro-input" placeholder="Inspection department" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Experience (Years)</label>
                                                            <input type="number" class="form-control pro-input" min="0" placeholder="Years of experience" required>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="pro-section">
                                                    <h6 class="section-title">Operational Assignment</h6>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Assigned Route(s)</label>
                                                            <input type="text" class="form-control pro-input" placeholder="Assigned route(s)" required>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="pro-section">
                                                    <h6 class="section-title">Contact Information</h6>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Contact Number</label>
                                                            <input type="text" class="form-control pro-input" placeholder="Phone number" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Email Address</label>
                                                            <input type="email" class="form-control pro-input" placeholder="Email address" required>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="pro-section">
                                                    <h6 class="section-title">Documents & Notes</h6>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Profile Picture</label>
                                                            <input type="file" class="form-control pro-input" accept="image/*">
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label">Additional Notes</label>
                  <textarea class="form-control pro-input" rows="3" placeholder="Additional remarks or notes"></textarea>
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
                                        <button type="button" class="btn pro-submit rounded-pill px-4" id="saveInspectorBtn">
          Add Inspector
                                        </button>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <h5 class="mt-5 mb-3">Inspector Schedule & Assignments</h5>
                        <div class="card p-3 mb-3" style="background-color:#fffbe6; border:1px solid #e5e0a4; border-radius:0.8rem; font-size:.95rem;">
                            <p style="margin:0;">Manage inspector assignments efficiently. Use the buttons below to add new inspectors or update schedules.</p>
                        </div>

                        <div class="card p-3 mb-3" style="background-color:#faf9e5; border:1px solid #e5e0a4; border-radius:1rem;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">Inspector List</h6>
                                <button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); border:none;" data-bs-toggle="modal" data-bs-target="#addInspectorModal">
        + Add Inspector
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover text-center" style="font-size:.9rem;">
                                    <thead style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); color:white;">
                                        <tr>
                                            <th>Inspector Name</th>
                                            <th>ID</th>
                                            <th>Department</th>
                                            <th>Experience</th>
                                            <th>Assigned Routes</th>
                                            <th>Contact</th>
                                            <th>Email</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="inspectorTableBody">
                                        <tr>
                                            <td>Maria Santos</td>
                                            <td>INSP1001</td>
                                            <td>Safety Inspection</td>
                                            <td>6 Years</td>
                                            <td>Cabanatuan – Rizal</td>
                                            <td>09123456789</td>
                                            <td>maria.santos@gmail.com</td>
                                            <td>
                                                <button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); border:none;">Edit</button>
                                                <button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#ff4d4f 0%,#a80000 100%); border:none;">Archive</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Juan Dela Cruz</td>
                                            <td>INSP1002</td>
                                            <td>Vehicle Inspection</td>
                                            <td>4 Years</td>
                                            <td>Cabanatuan – Rizal</td>
                                            <td>09112223344</td>
                                            <td>juan.delacruz@gmail.com</td>
                                            <td>
                                                <button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); border:none;">Edit</button>
                                                <button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#ff4d4f 0%,#a80000 100%); border:none;">Archive</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
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

                        function addRow(formId, tableBodyId, modalId) {
                            const form = document.getElementById(formId);
                            const tableBody = document.getElementById(tableBodyId);
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
                            tableBody.appendChild(tr);
                            bootstrap.Modal.getInstance(document.getElementById(modalId)).hide();
                            form.reset();
                        }

                        document.getElementById('saveShiftBtn').addEventListener('click', () => addRow('shiftForm', 'shiftTableBody', 'addShiftModal'));
                        document.getElementById('saveTerminalBtn').addEventListener('click', () => addRow('terminalForm', 'terminalTableBody', 'addTerminalModal'));

                        document.addEventListener('click', function (e) {
                            if (e.target.classList.contains('undoBtn') || e.target.classList.contains('deleteBtn')) {
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
                    </script>

                    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">                    </script>
                </body>
            </html>
