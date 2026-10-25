 <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
 <style>
     .admin-stats-section,
     .admin-stats-section h1,
     .admin-stats-section h2,
     .admin-stats-section div,
     .admin-stats-section i,
     .admin-stats-section p {
         font-family: 'Poppins', sans-serif;
     }
     .admin-stat-card {
         background: #fffdf8;
         border: 1px solid #e5e0a4;
         border-radius: 17px;
         padding: 1.1rem 1.25rem;
         height: 100%;
         box-shadow: 0 3px 10px rgba(0, 0, 0, .04);
         position: relative;
         overflow: hidden;
     }
     .admin-stat-card .accent-line {
         position: absolute;
         top: 0; left: 0;
         width: 4px;
         height: 100%;
         background: #a5d6a7;
         border-radius: 17px 0 0 17px;
     }
     .admin-stat-card .stat-icon {
         width: 44px;
         height: 44px;
         border-radius: 12px;
         background: #e9f2e9;
         display: flex;
         align-items: center;
         justify-content: center;
         flex-shrink: 0;
         margin-left: 16px;
     }
     .admin-stat-card .stat-icon i {
         font-size: 1.15rem;
         color: #295738;
     }
     .admin-stat-card .stat-label {
         font-size: .72rem;
         font-weight: 600;
         color: #8a9a8a;
         letter-spacing: .7px;
         text-transform: uppercase;
         margin-bottom: 4px;
     }
     .admin-stat-card .stat-value {
         margin: 0;
         font-weight: 800;
         font-size: 2rem;
         color: #295738;
         line-height: 1.1;
     }
     .admin-stat-card .stat-sub {
         color: #a8b3a8;
         font-size: .68rem;
         margin-top: 4px;
     }
 </style>

 <div class="admin-stats-section">
     <div class="d-flex justify-content-between align-items-center mb-3">
         <div>
             <h1 style="font-weight:700; font-size:1.35rem; color:#295738; margin:0 0 2px;">
        Admin Management
             </h1>
             <p style="font-weight:400; font-size:0.8rem; color:#8a9a8a; margin:0;">
        Manage administrator accounts, permissions, and secure system access.
             </p>
         </div>
     </div>

     <div class="row g-4 mb-4">

         <div class="col-lg-3 col-md-6 col-12">
             <div class="admin-stat-card">
                 <div class="accent-line"></div>
                 <div class="d-flex justify-content-between align-items-center">
                     <div>
                         <div class="stat-label">Total Admin</div>
                         <h2 id="totalAdmins" class="stat-value">0</h2>
                         <div class="stat-sub">Registered administrators</div>
                     </div>
                     <div class="stat-icon">
                         <i class="bi bi-person-badge-fill"></i>
                     </div>
                 </div>
             </div>
         </div>

         <div class="col-lg-3 col-md-6 col-12">
             <div class="admin-stat-card">
                 <div class="accent-line"></div>
                 <div class="d-flex justify-content-between align-items-center">
                     <div>
                         <div class="stat-label">Active Admin</div>
                         <h2 id="activeAdmins" class="stat-value">0</h2>
                         <div class="stat-sub">Currently active</div>
                     </div>
                     <div class="stat-icon">
                         <i class="bi bi-person-check-fill"></i>
                     </div>
                 </div>
             </div>
         </div>

         <div class="col-lg-3 col-md-6 col-12">
             <div class="admin-stat-card">
                 <div class="accent-line"></div>
                 <div class="d-flex justify-content-between align-items-center">
                     <div>
                         <div class="stat-label">Inactive Admin</div>
                         <h2 id="inactiveAdmins" class="stat-value">0</h2>
                         <div class="stat-sub">Not currently active</div>
                     </div>
                     <div class="stat-icon">
                         <i class="bi bi-person-dash-fill"></i>
                     </div>
                 </div>
             </div>
         </div>

         <div class="col-lg-3 col-md-6 col-12">
             <div class="admin-stat-card">
                 <div class="accent-line"></div>
                 <div class="d-flex justify-content-between align-items-center">
                     <div>
                         <div class="stat-label">Pending Admin</div>
                         <h2 id="pendingAdmins" class="stat-value">0</h2>
                         <div class="stat-sub">Awaiting approval</div>
                     </div>
                     <div class="stat-icon">
                         <i class="bi bi-person-plus-fill"></i>
                     </div>
                 </div>
             </div>
         </div>

     </div>

     <script>
         window.onload = function () { loadAdminStats(); };
         async function loadAdminStats() {
             try {
                 const response = await fetch('/admin/api/Admin/AdminStats.php?action=getAdminStats');
                 const rawResponse = await response.text();
                 let result;
                 try { result = JSON.parse(rawResponse); } catch (jsonError) { console.error('Invalid JSON:', rawResponse); return; }
                 if (!result.success) { console.error(result.message); return; }
                 const el = (id) => document.getElementById(id);
                 if (el('totalAdmins')) el('totalAdmins').textContent = result.data.total_admins ?? 0;
                 if (el('activeAdmins')) el('activeAdmins').textContent = result.data.active_admins ?? 0;
                 if (el('inactiveAdmins')) el('inactiveAdmins').textContent = result.data.inactive_admins ?? 0;
                 if (el('pendingAdmins')) el('pendingAdmins').textContent = result.data.pending_admins ?? 0;
             } catch (error) {
                 console.error('Failed to load admin statistics:', error);
             }
         }
     </script>
 </div>
