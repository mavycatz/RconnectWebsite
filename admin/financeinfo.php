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
        <title>Finance Dashboard</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/chart.js">        </script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
            .employee-scroll {
                display: flex;
                gap: 16px;
                overflow-x: auto;
                padding-bottom: 16px;
                -webkit-overflow-scrolling: touch;
                scroll-behavior: smooth;
            }
            .employee-scroll::-webkit-scrollbar { display: none; }
            .employee-card {
                flex: 0 0 auto;
                width: 320px;
                background: #f1f3f8;
                border: 1px solid #c5cbe3;
                border-radius: .8rem;
                box-shadow: 0 3px 8px rgba(0, 0, 0, .12);
                padding: 16px;
            }
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
            .perm-btn:hover { transform: scale(1.1); }
            .table td, .table th { vertical-align: middle; }
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
      Finance Department
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
        Total Employees
                    </div>

                    <div class="d-flex align-items-center justify-content-between mt-2">
                        <i class="bi bi-people-fill"
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
        Active Employees
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
        Inactive Employees
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
        Pending Employees
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
                            <h5 class="mb-0">All Employees</h5>
                            <button class="btn text-white" style="background:linear-gradient(90deg,#0d6efd 0%,#0a58ca 100%);" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
      Add New Employee
                            </button>
                        </div>

                        <div style="width:100%; overflow:hidden;">
                            <div class="employee-scroll">
                                <div class="employee-card">
                                    <div style="display:flex; gap:12px;">
                                        <img src="../assets/images/admin/finance/bobby.png" style="width:64px; height:64px; border-radius:50%; object-fit:cover;">
                                        <div style="flex:1; overflow:hidden;">
                                            <h6 style="margin-bottom:6px; font-weight:600;">
              Bobby Salazar <small style="color:#6c757d; font-weight:400;">ID: RPM134</small>
                                            </h6>
                                            <p style="margin:0 0 4px; font-size:0.85rem;">Status: <span style="color:#198754;">Active</span></p>
                                            <p style="margin:0 0 4px; font-size:0.85rem;">Position: Accountant</p>
                                            <p style="margin:0 0 4px; font-size:0.85rem;">Assigned Area: Rizal</p>
                                            <p style="margin:0 0 4px; font-size:0.85rem;">Shift Status: On Leave</p>
                                            <p style="margin:0 0 4px; font-size:0.85rem;">Access Level: 3</p>
                                            <p style="margin:0 0 4px; font-size:0.85rem;">Shift Schedule: Monday, Wednesday, Friday, Sunday</p>
                                            <p style="margin:0 0 4px; font-size:0.85rem;">Contact: 09123456789 | Email: bobbysalazar@gmail.com</p>
                                            <div style="display:flex; justify-content:flex-end; gap:8px;">
                                                <button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#0d6efd 0%,#0a58ca 100%);border:none;">Edit</button>
                                                <button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#ff4d4f 0%,#a80000 100%);border:none;">Archive</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="employee-card">
                                    <div style="display:flex; gap:12px;">
                                        <img src="../assets/images/admin/finance/alex.png" style="width:64px;height:64px;border-radius:50%;object-fit:cover;">
                                        <div style="flex:1;">
                                            <h6>Alex Salazar <small style="color:#6c757d; font-weight:400;">ID: RPM112</small></h6>
                                            <p style="margin:0 0 4px; font-size:0.85rem;">Status: <span style="color:#198754;">Active</span></p>
                                            <p style="margin:0 0 4px; font-size:0.85rem;">Position: Cashier</p>
                                            <p style="margin:0 0 4px; font-size:0.85rem;">Assigned Area: Rizal</p>
                                            <p style="margin:0 0 4px; font-size:0.85rem;">Shift Status: Duty</p>
                                            <p style="margin:0 0 4px; font-size:0.85rem;">Access Level: 2</p>
                                            <p style="margin:0 0 4px; font-size:0.85rem;">Shift Schedule: Monday, Wednesday, Friday, Sunday</p>
                                            <p style="margin:0 0 4px; font-size:0.85rem;">Contact: 09123456789 | Email: alexsalazar@gmail.com</p>
                                            <div style="display:flex; justify-content:flex-end; gap:8px;">
                                                <button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#0d6efd 0%,#0a58ca 100%);border:none;">Edit</button>
                                                <button class="btn btn-sm text-white" style="background:linear-gradient(90deg,#ff4d4f 0%,#a80000 100%);border:none;">Archive</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                        <h5 class="mt-5 mb-3" style="color:#295738;">Finance Roles & Security</h5>
                        <div class="row g-3">

                            <div class="col-md-6">
                                <div class="card p-3 h-100" style="background:#f5f4e6; border:1px solid #96af80; border-radius:0.8rem;">
                                    <h6 class="mb-3" style="color:#295738;">Financial Roles</h6>
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <div class="p-2 border rounded h-100" style="background:#fff;">
                                                <strong>Finance Officer</strong>
                                                <ol style="margin-left:1rem; font-size:.9rem;">
                                                    <li>Oversees revenue, expenses, & audits</li>
                                                    <li>Approves financial reports</li>
                                                    <li>Full system access</li>
                                                </ol>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-2 border rounded h-100" style="background:#fff;">
                                                <strong>Accountant</strong>
                                                <ol style="margin-left:1rem; font-size:.9rem;">
                                                    <li>Manages daily income & expense record</li>
                                                    <li>Generates monthly & annual reports</li>
                                                    <li>Limited admin access</li>
                                                </ol>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-2 border rounded h-100" style="background:#fff;">
                                                <strong>Cashier</strong>
                                                <ol style="margin-left:1rem; font-size:.9rem;">
                                                    <li>Handles fare collection</li>
                                                    <li>QR payment verification</li>
                                                    <li>No access to system settings</li>
                                                </ol>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-2 border rounded h-100" style="background:#fff;">
                                                <strong>Finance Manager</strong>
                                                <ol style="margin-left:1rem; font-size:.9rem;">
                                                    <li>Supervises finance staff</li>
                                                    <li>Reviews performance & compliance</li>
                                                    <li>Full access with approval rights</li>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card p-3 h-100" style="background:#f5f4e6; border:1px solid #96af80; border-radius:0.8rem; display:flex; flex-direction:column;">
                                    <h6 class="mb-3" style="color:#295738;"><i class="bi bi-shield-lock-fill me-2" style="font-size:1.5rem; color:#295738;"></i>Security Rules</h6>
                                    <ul style="margin-left:1rem; font-size:.95rem; flex-grow:1; display:flex; flex-direction:column; justify-content:space-between; gap:0.5rem;">
                                        <li><i class="bi bi-person-check-fill me-2" style="font-size:1.5rem; color:#295738;"></i>Authorized users only</li>
                                        <li><i class="bi bi-slash-circle me-2" style="font-size:1.5rem; color:#295738;"></i>Do not share accounts</li>
                                        <li><i class="bi bi-file-lock-fill me-2" style="font-size:1.5rem; color:#295738;"></i>Protect financial data</li>
                                        <li><i class="bi bi-pencil-square me-2" style="font-size:1.5rem; color:#295738;"></i>Verify entries before saving</li>
                                        <li><i class="bi bi-box-arrow-right me-2" style="font-size:1.5rem; color:#295738;"></i>Log out and report issues</li>
                                    </ul>
                                </div>
                            </div>



                        </div>


                        <h5 class="mt-5 mb-3">Access Control</h5>
                        <div class="table-responsive">
                            <table class="table table-striped text-center" id="accessTable">
                                <thead style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%); color:white;">
                                    <tr>
                                        <th>Module</th>
                                        <th>View</th>
                                        <th>Add</th>
                                        <th>Edit</th>
                                        <th>Delete</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Financial Records</td>
                                        <td><button class="perm-btn active">✔</button></td>
                                        <td><button class="perm-btn active">✔</button></td>
                                        <td><button class="perm-btn active">✔</button></td>
                                        <td><button class="perm-btn active">✔</button></td>
                                    </tr>
                                    <tr>
                                        <td>Payments</td>
                                        <td><button class="perm-btn active">✔</button></td>
                                        <td><button class="perm-btn active">✔</button></td>
                                        <td><button class="perm-btn active">✔</button></td>
                                        <td><button class="perm-btn active">✔</button></td>
                                    </tr>
                                    <tr>
                                        <td>Reports</td>
                                        <td><button class="perm-btn active">✔</button></td>
                                        <td><button class="perm-btn active">✔</button></td>
                                        <td><button class="perm-btn active">✔</button></td>
                                        <td><button class="perm-btn active">✔</button></td>
                                    </tr>
                                    <tr>
                                        <td>Trips (Fare View)</td>
                                        <td><button class="perm-btn active">✔</button></td>
                                        <td><button class="perm-btn active">✔</button></td>
                                        <td><button class="perm-btn active">✔</button></td>
                                        <td><button class="perm-btn active">✔</button></td>
                                    </tr>
                                    <tr>
                                        <td>Settings</td>
                                        <td><button class="perm-btn active">✔</button></td>
                                        <td><button class="perm-btn active">✔</button></td>
                                        <td><button class="perm-btn active">✔</button></td>
                                        <td><button class="perm-btn active">✔</button></td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="mt-3">
                                <button id="undoBtn" class="btn btn-warning btn-sm">Undo</button>
                                <button id="redoBtn" class="btn btn-info btn-sm">Redo</button>
                                <button id="saveBtn" class="btn btn-success btn-sm">Save Permissions</button>
                            </div>
                        </div>

                    </div>

                    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">                    </script>
                    <script>
                        const accessTable = document.getElementById("accessTable");
                        let undoStack = [], redoStack = [];

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

                </body>
            </html>
