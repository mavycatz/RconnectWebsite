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
        <title>Admin Dashboard</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/chart.js">        </script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">


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
                transition: margin-left 0.3s ease;
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

            .admin-scroll {
                display: flex;
                gap: 16px;
                overflow-x: auto;
                padding-bottom: 16px;
                -webkit-overflow-scrolling: touch;
                scroll-behavior: smooth;
            }

            .admin-scroll::-webkit-scrollbar { display: none; }

            .admin-card {
                flex: 0 0 auto;
                width: 320px;
                background: #f1f3f8;
                border: 1px solid #c5cbe3;
                border-radius: .8rem;
                box-shadow: 0 3px 8px rgba(0, 0, 0, .12);
                padding: 16px;
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
      Admin
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
        Total Admin
                    </div>

                    <div class="d-flex align-items-center justify-content-between mt-2">
                        <i class="bi bi-person-badge-fill"
           style="font-size:2.4rem; color:#295738;"></i>

                            <div style="font-size:1.7rem;
                    font-weight:700;
                    color:#295738;">
          10
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
        Active Admin
                        </div>

                        <div class="d-flex align-items-center justify-content-between mt-2">
                            <i class="bi bi-person-check-fill"
           style="font-size:2.4rem; color:#295738;"></i>

                                <div style="font-size:1.7rem;
                    font-weight:700;
                    color:#295738;">
          7
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
        Inactive Admin
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
        Pending Admin
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
                            <h6 style="
    font-size:1rem;
    font-family:'Poppins', sans-serif;
    font-weight:700;
    color:#295738;
    letter-spacing:0.5px;
    margin-bottom:1rem;
    margin-top: 1.5rem;
">
    All Admin
                            </h6>
                            <button
  class="btn text-white"
  style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%);
         border:none;
         border-radius:20px;
         padding:6px 16px;
         font-weight:600;"
  data-bs-toggle="modal"
  data-bs-target="#addAdminModal">
  Add New Admin
                            </button>
                        </div>
                        <div style="width:100%; overflow:hidden;">
                            <div class="admin-scroll">

                                <div class="admin-card">
                                    <div style="display:flex; gap:12px;">
                                        <img src="../assets/images/admin/driversinfo/drivericon.png" 
             style="width:64px; height:64px; border-radius:50%; object-fit:cover;">
                                        <div style="flex:1; overflow:hidden;">
                                            <h6 style="margin-bottom:6px; font-weight:600;">
            Gabby Salazar <small style="color:#6c757d; font-weight:400;">ID: RPM134</small>
                                            </h6>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="font-weight:700; color:#295738;">Status:</span>
                                                <span style="color:#198754;"> Active</span>
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="font-weight:700; color:#295738;">Position:</span> Admin
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="font-weight:700; color:#295738;">Assigned Area:</span> Rizal
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="font-weight:700; color:#295738;">Shift Status:</span> On Leave
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="font-weight:700; color:#295738;">Access Level:</span> 4
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="font-weight:700; color:#295738;">Shift Schedule:</span>
            Monday, Wednesday, Friday, Sunday
                                            </p>

                                            <p style="margin:0 0 8px; font-size:0.85rem;">
                                                <span style="font-weight:700; color:#295738;">Contact:</span>
            09123456789 | Email: gabbysalazar@gmail.com
                                            </p>

                                            <div style="display:flex; justify-content:flex-end; gap:8px;">
                                                <button class="btn btn-sm text-white"
              style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%);
                     border:none;
                     border-radius:18px;
                     padding:4px 14px;
                     font-weight:500;">
              Edit
                                                </button>

                                                <button class="btn btn-sm text-white"
              style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%);
                     border:none;
                     border-radius:18px;
                     padding:4px 14px;
                     font-weight:500;">
              Archive
                                                </button>
                                            </div>

                                        </div>
                                    </div>
                                </div>

                                <div class="admin-card">
                                    <div style="display:flex; gap:12px;">
                                        <img src="../assets/images/admin/driversinfo/drivericon.png" 
             style="width:64px; height:64px; border-radius:50%; object-fit:cover;">
                                        <div style="flex:1; overflow:hidden;">
                                            <h6 style="margin-bottom:6px; font-weight:600;">
            Teddy Salazar <small style="color:#6c757d; font-weight:400;">ID: RPM112</small>
                                            </h6>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="font-weight:700; color:#295738;">Status:</span>
                                                <span style="color:#198754;"> Active</span>
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="font-weight:700; color:#295738;">Position:</span> Admin
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="font-weight:700; color:#295738;">Assigned Area:</span> Rizal
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="font-weight:700; color:#295738;">Shift Status:</span> Duty
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="font-weight:700; color:#295738;">Access Level:</span> 4
                                            </p>

                                            <p style="margin:0 0 4px; font-size:0.85rem;">
                                                <span style="font-weight:700; color:#295738;">Shift Schedule:</span>
            Monday, Wednesday, Friday, Sunday
                                            </p>

                                            <p style="margin:0 0 8px; font-size:0.85rem;">
                                                <span style="font-weight:700; color:#295738;">Contact:</span>
            09123456789 | Email: teddysalazar@gmail.com
                                            </p>

                                            <div style="display:flex; justify-content:flex-end; gap:8px;">
                                                <button class="btn btn-sm text-white"
              style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%);
                     border:none;
                     border-radius:18px;
                     padding:4px 14px;
                     font-weight:500;">
              Edit
                                                </button>

                                                <button class="btn btn-sm text-white"
              style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%);
                     border:none;
                     border-radius:18px;
                     padding:4px 14px;
                     font-weight:500;">
              Archive
                                                </button>
                                            </div>

                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <h6 style="
    font-size:1rem;
    font-family:'Poppins', sans-serif;
    font-weight:700;
    color:#295738;
    letter-spacing:0.5px;
    margin-bottom:1rem;
    margin-top: 1.5rem;
">
    Role Designation
                        </h6>
                        <div class="row g-4 align-items-stretch">

                            <div class="col-md-6 d-flex">
                                <div class="card w-100"
         style="background:#faf9e5;
                border:1px solid #e5e0a4;
                border-radius:14px;
                padding:22px;">

                                    <h6 style="color:#295738;
                 font-weight:700;
                 letter-spacing:.5px;
                 margin-bottom:18px;">
                                        <i class="bi bi-person-badge-fill me-2" style="color:#295738;"></i>
        Financial Roles
                                    </h6>

                                    <div style="font-size:0.9rem; line-height:1.7;">

                                        <div style="margin-bottom:12px;">
                                            <span style="font-weight:700; color:#295738;">Finance Admin:</span>
          Full access to financial records, reports, and audits.
                                        </div>

                                        <div style="margin-bottom:12px;">
                                            <span style="font-weight:700; color:#295738;">Accounting Staff:</span>
          Encode and generate financial reports.
                                        </div>

                                        <div style="margin-bottom:12px;">
                                            <span style="font-weight:700; color:#295738;">Audit Officer:</span>
          View-only access for auditing purposes.
                                        </div>

                                        <div>
                                            <span style="font-weight:700; color:#295738;">Cashier:</span>
          Manage payment and transaction records.
                                        </div>

                                    </div>
                                </div>
                            </div>


                            <div class="col-md-6 d-flex">
                                <div class="card w-100"
         style="background:#faf9e5;
                border:1px solid #e5e0a4;
                border-radius:14px;
                padding:22px;">

                                    <div class="table-responsive"
           style="border:1px solid #e5e0a4;
                  border-radius:12px;
                  overflow:hidden;">

                                        <table class="table mb-0 text-center"
               style="font-size:0.85rem;
                      margin:0;">

                                            <thead>

                                                <tr style="background:#295738; color:white;">
                                                    <th colspan="5"
                  class="text-start"
                  style="font-weight:700;
                         letter-spacing:.5px;
                         padding:12px 16px;">
                                                        <i class="bi bi-shield-lock-fill me-2"></i>
                Access Control
                                                    </th>
                                                </tr>

                                                <tr style="background:#e9f3dc; color:#295738;">
                                                    <th class="text-start" style="border:1px solid #e5e0a4;">Module</th>
                                                    <th style="border:1px solid #e5e0a4;">V</th>
                                                    <th style="border:1px solid #e5e0a4;">A</th>
                                                    <th style="border:1px solid #e5e0a4;">E</th>
                                                    <th style="border:1px solid #e5e0a4;">D</th>
                                                </tr>

                                            </thead>

                                            <tbody style="background:#ffffff;">

                                                <tr>
                                                    <td class="text-start fw-semibold"
                  style="color:#295738; border:1px solid #e5e0a4;">
                Dashboard
                                                    </td>
                                                    <td style="border:1px solid #e5e0a4;">
                                                        <i class="bi bi-check-lg" style="color:#295738;"></i>
                                                    </td>
                                                    <td style="border:1px solid #e5e0a4;">
                                                        <i class="bi bi-check-lg" style="color:#295738;"></i>
                                                    </td>
                                                    <td style="border:1px solid #e5e0a4;">-</td>
                                                    <td style="border:1px solid #e5e0a4;">-</td>
                                                </tr>

                                                <tr style="background:#f4f9ef;">
                                                    <td class="text-start fw-semibold"
                  style="color:#295738; border:1px solid #e5e0a4;">
                Users
                                                    </td>
                                                    <td style="border:1px solid #e5e0a4;"><i class="bi bi-check-lg" style="color:#295738;"></i></td>
                                                    <td style="border:1px solid #e5e0a4;"><i class="bi bi-check-lg" style="color:#295738;"></i></td>
                                                    <td style="border:1px solid #e5e0a4;"><i class="bi bi-check-lg" style="color:#295738;"></i></td>
                                                    <td style="border:1px solid #e5e0a4;"><i class="bi bi-check-lg" style="color:#295738;"></i></td>
                                                </tr>

                                                <tr>
                                                    <td class="text-start fw-semibold"
                  style="color:#295738; border:1px solid #e5e0a4;">
                Trips
                                                    </td>
                                                    <td style="border:1px solid #e5e0a4;"><i class="bi bi-check-lg" style="color:#295738;"></i></td>
                                                    <td style="border:1px solid #e5e0a4;"><i class="bi bi-check-lg" style="color:#295738;"></i></td>
                                                    <td style="border:1px solid #e5e0a4;"><i class="bi bi-check-lg" style="color:#295738;"></i></td>
                                                    <td style="border:1px solid #e5e0a4;"><i class="bi bi-check-lg" style="color:#295738;"></i></td>
                                                </tr>

                                                <tr style="background:#f4f9ef;">
                                                    <td class="text-start fw-semibold"
                  style="color:#295738; border:1px solid #e5e0a4;">
                Routes
                                                    </td>
                                                    <td style="border:1px solid #e5e0a4;"><i class="bi bi-check-lg" style="color:#295738;"></i></td>
                                                    <td style="border:1px solid #e5e0a4;">-</td>
                                                    <td style="border:1px solid #e5e0a4;"><i class="bi bi-check-lg" style="color:#295738;"></i></td>
                                                    <td style="border:1px solid #e5e0a4;"><i class="bi bi-check-lg" style="color:#295738;"></i></td>
                                                </tr>

                                            </tbody>

                                        </table>

                                    </div>
                                </div>
                            </div>

                        </div>

                        <h6 style="
    font-size:1rem;
    font-family:'Poppins', sans-serif;
    font-weight:700;
    color:#295738;
    letter-spacing:0.5px;
    margin-bottom:1rem;
    margin-top: 1.5rem;
">
System Configuration & Master Data
                        </h6>
                        <div class="container-fluid mt-4">

                            <div class="p-4"
       style="background:#faf9e5;
              border-radius:1rem;">

                                <div class="row g-4">

                                    <div class="col-md-4 d-flex flex-column gap-3">

                                        <div class="p-3"
       style="background:#faf9e5;
              border-radius:0.8rem;">

                                            <div style="margin-bottom:0.6rem;">
                                                <div style="font-weight:700;
                  color:#295738;
                  font-size:0.95rem;
                  font-family:'Poppins', sans-serif;">
        Bus Types and Capacity
                                                </div>
                                                <div style="width:30%;
                  height:2px;
                  background:#e5e0a4;
                  margin-top:4px;"></div>
                                                </div>

                                                <table class="table mb-0" style="background:#fff;">
                                                    <thead>
                                                        <tr style="color:#295738;">
                                                            <th style="font-weight:700; padding:6px 10px;">Vehicle</th>
                                                            <th style="font-weight:700; padding:6px 10px;">Capacity</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td style="padding:6px 10px;">Mini Bus</td>
                                                            <td style="padding:6px 10px;">28</td>
                                                        </tr>
                                                        <tr>
                                                            <td style="padding:6px 10px;">Coach</td>
                                                            <td style="padding:6px 10px;">30</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>


                                            <div class="p-3"
       style="background:#faf9e5;
              border-radius:0.8rem;">

                                                <div style="margin-bottom:0.6rem;">
                                                    <div style="font-weight:700;
                  color:#295738;
                  font-size:0.95rem;
                  font-family:'Poppins', sans-serif;">
        Fuel Price Configuration
                                                    </div>
                                                    <div style="width:30%;
                  height:2px;
                  background:#e5e0a4;
                  margin-top:4px;"></div>
                                                    </div>

                                                    <table class="table mb-0" style="background:#fff;">
                                                        <thead>
                                                            <tr style="color:#295738;">
                                                                <th style="font-weight:700; padding:6px 10px;">Fuel Type</th>
                                                                <th style="font-weight:700; padding:6px 10px;">Price</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td style="padding:6px 10px;">Diesel</td>
                                                                <td style="padding:6px 10px;">₱65.50</td>
                                                            </tr>
                                                            <tr>
                                                                <td style="padding:6px 10px;">Gasoline</td>
                                                                <td style="padding:6px 10px;">₱70.20</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>

                                            </div>


                                            <div class="col-md-8">

                                                <div class="p-4"
       style="background:#ffffff;
              border:1px solid #e5e0a4;
              border-radius:1rem;">

                                                    <h6 style="font-weight:700;
               font-size:1rem;
               font-family:'Poppins', sans-serif;
               color:#295738;
               margin-bottom:1.5rem;">
      Violation Categories and Severity
                                                    </h6>

                                                    <div class="d-flex">

                                                        <div class="flex-fill pe-4">

                                                            <div style="margin-bottom:1.5rem;">
                                                                <div style="font-weight:700; color:#295738; margin-bottom:8px;">
            Fare Violation
                                                                </div>
                                                                <ol style="padding-left:18px; margin:0;">
                                                                    <li>No ticket / fare evasion</li>
                                                                    <li>Incorrect fare charged</li>
                                                                </ol>
                                                            </div>

                                                            <div>
                                                                <div style="font-weight:700; color:#295738; margin-bottom:8px;">
            Safety Violation
                                                                </div>
                                                                <ol style="padding-left:18px; margin:0;">
                                                                    <li>Overloading passengers</li>
                                                                    <li>Standing in restricted areas</li>
                                                                    <li>Blocking exits</li>
                                                                </ol>
                                                            </div>

                                                        </div>

                                                        <div style="width:1px;
                  background:#e5e0a4;
                  margin:0 24px;"></div>

                                                            <div class="flex-fill ps-4">

                                                                <div style="margin-bottom:1.5rem;">
                                                                    <div style="font-weight:700; color:#295738; margin-bottom:8px;">
            Vehicle Condition
                                                                    </div>
                                                                    <ol style="padding-left:18px; margin:0;">
                                                                        <li>Poor vehicle maintenance</li>
                                                                        <li>Broken safety equipment</li>
                                                                    </ol>
                                                                </div>

                                                                <div>
                                                                    <div style="font-weight:700; color:#295738; margin-bottom:8px;">
            Operational Violation
                                                                    </div>
                                                                    <ol style="padding-left:18px; margin:0;">
                                                                        <li>Unauthorized stop</li>
                                                                        <li>Route deviation</li>
                                                                        <li>Schedule non-compliance</li>
                                                                    </ol>
                                                                </div>

                                                            </div>

                                                        </div>

                                                    </div>

                                                </div>

                                            </div>
                                        </div>
                                    </div>

                                    <div class="container my-3">

                                        <div class="d-flex justify-content-between align-items-center"
       style="margin-top:1.5rem; margin-bottom:0.8rem;">

                                            <h6 style="
        font-size:1rem;
        font-family:'Poppins', sans-serif;
        font-weight:700;
        color:#295738;
        letter-spacing:0.5px;
        margin:0;
    ">
      Routes and Terminal / Fare Pricing (Cabanatuan-Rizal)
                                            </h6>

                                            <div style="display:flex; gap:10px;">

                                                <button class="btn btn-sm text-white"
              style="background:#295738;
                     border:none;
                     border-radius:20px;
                     padding:6px 16px;
                     font-weight:500;">
        Add
                                                </button>

                                                <button class="btn btn-sm text-white"
              style="background:#6c757d;
                     border:none;
                     border-radius:20px;
                     padding:6px 16px;
                     font-weight:500;">
        Delete
                                                </button>

                                                <button class="btn btn-sm text-white"
              style="background:#4f772d;
                     border:none;
                     border-radius:20px;
                     padding:6px 16px;
                     font-weight:500;">
        Print
                                                </button>

                                            </div>
                                        </div>


                                        <div class="card p-3 mb-4"
       style="background:#faf9e5;
              border:1px solid #e5e0a4;
              border-radius:0.8rem;">

                                            <div class="table-responsive">
                                                <table class="table table-bordered mb-0 text-center"
             style="background:#fff;">

                                                    <thead style="background:#d4e5c1; color:#295738;">
                                                        <tr>
                                                            <th>Destination</th>
                                                            <th>Regular</th>
                                                            <th>Student/Senior</th>
                                                            <th>Special</th>
                                                        </tr>
                                                    </thead>

                                                    <tbody>
                                                        <tr><td>Central Terminal–Daang Sarile</td><td>15.00</td><td>15.00</td><td>15.00</td></tr>
                                                        <tr><td>Valdefuente</td><td>16.00</td><td>15.00</td><td>18.00</td></tr>
                                                        <tr><td>Mayapyap</td><td>21.00</td><td>16.00</td><td>20.00</td></tr>
                                                        <tr><td>Caalibangbangan</td><td>25.00</td><td>20.00</td><td>22.00</td></tr>
                                                        <tr><td>San Miguel Munti</td><td>30.00</td><td>24.00</td><td>25.00</td></tr>
                                                        <tr><td>Pinagpanaan</td><td>34.00</td><td>27.00</td><td>29.00</td></tr>
                                                        <tr><td>Baluga/Sto. Niño</td><td>38.00</td><td>31.00</td><td>33.00</td></tr>
                                                        <tr><td>Homestead 1</td><td>45.00</td><td>36.00</td><td>39.00</td></tr>
                                                        <tr><td>Homestead 2</td><td>47.00</td><td>38.00</td><td>41.00</td></tr>
                                                        <tr><td>San Ricardo/Casile</td><td>50.00</td><td>40.00</td><td>43.00</td></tr>
                                                        <tr><td>Luna</td><td>60.00</td><td>50.00</td><td>51.00</td></tr>
                                                        <tr><td>Mataas na Kahoy</td><td>62.00</td><td>51.00</td><td>53.00</td></tr>
                                                        <tr><td>Ricarte</td><td>65.00</td><td>53.00</td><td>55.00</td></tr>
                                                        <tr><td>Mabini</td><td>67.00</td><td>54.00</td><td>57.00</td></tr>
                                                        <tr><td>Inanama</td><td>69.00</td><td>56.00</td><td>59.00</td></tr>
                                                        <tr><td>Bicos</td><td>71.00</td><td>57.00</td><td>60.00</td></tr>
                                                        <tr><td>Mangahan</td><td>76.00</td><td>61.00</td><td>65.00</td></tr>
                                                        <tr><td>Aglipay</td><td>80.00</td><td>65.00</td><td>68.00</td></tr>
                                                        <tr><td>Del Pilar</td><td>82.00</td><td>66.00</td><td>70.00</td></tr>
                                                        <tr><td>Rizal/Pob Sur Terminal</td><td>91.00</td><td>73.00</td><td>78.00</td></tr>
                                                    </tbody>

                                                </table>
                                            </div>
                                        </div>

                                    </div>

                                    <div class="d-flex justify-content-between align-items-center"
       style="margin-top:1.5rem; margin-bottom:0.8rem;">

                                        <h6 style="
        font-size:1rem;
        font-family:'Poppins', sans-serif;
        font-weight:700;
        color:#295738;
        letter-spacing:0.5px;
        margin:0;
    ">
Routes and Terminal / Fare Pricing (Rizal-Cabanatuan)
                                        </h6>

                                        <div style="display:flex; gap:10px;">

                                            <button class="btn btn-sm text-white"
              style="background:#295738;
                     border:none;
                     border-radius:20px;
                     padding:6px 16px;
                     font-weight:500;">
        Add
                                            </button>

                                            <button class="btn btn-sm text-white"
              style="background:#6c757d;
                     border:none;
                     border-radius:20px;
                     padding:6px 16px;
                     font-weight:500;">
        Delete
                                            </button>

                                            <button class="btn btn-sm text-white"
              style="background:#4f772d;
                     border:none;
                     border-radius:20px;
                     padding:6px 16px;
                     font-weight:500;">
        Print
                                            </button>

                                        </div>
                                    </div>

                                    <div class="card p-3" style="background:#faf9e5; border:1px solid #e5e0a4; border-radius:0.8rem;">
                                        <div class="table-responsive">
                                            <table class="table table-bordered mb-0 text-center" style="background:#fff;">
                                                <thead style="background:#d4e5c1; color:#295738;">
                                                    <tr>
                                                        <th>Destination</th>
                                                        <th>Regular</th>
                                                        <th>Student/Senior</th>
                                                        <th>Special</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr><td>Aglipay</td><td>16.00</td><td>15.00</td><td>15.00</td></tr>
                                                    <tr><td>Mangahan</td><td>21.00</td><td>16.00</td><td>18.00</td></tr>
                                                    <tr><td>Bicos</td><td>25.00</td><td>20.00</td><td>22.00</td></tr>
                                                    <tr><td>Inanama</td><td>27.00</td><td>22.00</td><td>23.00</td></tr>
                                                    <tr><td>Mabini</td><td>30.00</td><td>24.00</td><td>25.00</td></tr>
                                                    <tr><td>Ricarte</td><td>32.00</td><td>25.00</td><td>27.00</td></tr>
                                                    <tr><td>Mataas Kanto</td><td>34.00</td><td>27.00</td><td>29.00</td></tr>
                                                    <tr><td>Luna</td><td>36.00</td><td>29.00</td><td>31.00</td></tr>
                                                    <tr><td>Casile & San Ricardo</td><td>47.00</td><td>38.00</td><td>41.00</td></tr>
                                                    <tr><td>Homestead II</td><td>50.00</td><td>40.00</td><td>43.00</td></tr>
                                                    <tr><td>Homestead I</td><td>52.00</td><td>42.00</td><td>44.00</td></tr>
                                                    <tr><td>Baluga & Sto. Niño</td><td>58.00</td><td>47.00</td><td>49.00</td></tr>
                                                    <tr><td>Pinaagpanaan</td><td>62.00</td><td>51.00</td><td>53.00</td></tr>
                                                    <tr><td>San Miguel Munti</td><td>67.00</td><td>54.00</td><td>57.00</td></tr>
                                                    <tr><td>Caalibangbangan</td><td>71.00</td><td>57.00</td><td>60.00</td></tr>
                                                    <tr><td>Mayapyap</td><td>76.00</td><td>61.00</td><td>65.00</td></tr>
                                                    <tr><td>Valdefuente & Daan Sarile</td><td>80.00</td><td>65.00</td><td>68.00</td></tr>
                                                    <tr><td>Bitas</td><td>89.00</td><td>72.00</td><td>75.00</td></tr>
                                                    <tr><td>Cabanatuan Central Terminal</td><td>91.00</td><td>73.00</td><td>78.00</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                            </div>
  
  
                        </div>

                    </div>
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
                    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">                    </script>
                </body>
            </html>
