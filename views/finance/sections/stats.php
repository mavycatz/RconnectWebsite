 <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
 <style>
     .finance-stats-section,
     .finance-stats-section h1,
     .finance-stats-section h2,
     .finance-stats-section div,
     .finance-stats-section i,
     .finance-stats-section p {
         font-family: 'Poppins', sans-serif;
     }
     .finance-stat-card {
         background: #fffdf8;
         border: 1px solid #e5e0a4;
         border-radius: 17px;
         padding: 1.1rem 1.25rem;
         height: 100%;
         box-shadow: 0 3px 10px rgba(0, 0, 0, .04);
         position: relative;
         overflow: hidden;
     }
     .finance-stat-card .accent-line {
         position: absolute;
         top: 0; left: 0;
         width: 4px;
         height: 100%;
         background: #a5d6a7;
         border-radius: 17px 0 0 17px;
     }
     .finance-stat-card .stat-icon {
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
     .finance-stat-card .stat-icon i {
         font-size: 1.15rem;
         color: #295738;
     }
     .finance-stat-card .stat-label {
         font-size: .72rem;
         font-weight: 600;
         color: #8a9a8a;
         letter-spacing: .7px;
         text-transform: uppercase;
         margin-bottom: 4px;
     }
     .finance-stat-card .stat-value {
         margin: 0;
         font-weight: 800;
         font-size: 2rem;
         color: #295738;
         line-height: 1.1;
     }
     .finance-stat-card .stat-sub {
         color: #a8b3a8;
         font-size: .68rem;
         margin-top: 4px;
     }
 </style>

 <div class="finance-stats-section">
     <div class="d-flex justify-content-between align-items-center mb-3">
         <div>
             <h1 style="font-weight:700; font-size:1.35rem; color:#295738; margin:0 0 2px;">
        Finance Management
             </h1>
             <p style="font-weight:400; font-size:0.8rem; color:#8a9a8a; margin:0;">
        Manage financial records, fare collections, revenue, and finance operations.
             </p>
         </div>
     </div>

     <div class="row g-4 mb-4">

         <div class="col-lg-3 col-md-6 col-12">
             <div class="finance-stat-card">
                 <div class="accent-line"></div>
                 <div class="d-flex justify-content-between align-items-center">
                     <div>
                         <div class="stat-label">Total Employees</div>
                         <h2 id="totalEmployees" class="stat-value">0</h2>
                         <div class="stat-sub">Registered employees</div>
                     </div>
                     <div class="stat-icon">
                         <i class="bi bi-people-fill"></i>
                     </div>
                 </div>
             </div>
         </div>

         <div class="col-lg-3 col-md-6 col-12">
             <div class="finance-stat-card">
                 <div class="accent-line"></div>
                 <div class="d-flex justify-content-between align-items-center">
                     <div>
                         <div class="stat-label">Active Employees</div>
                         <h2 id="activeEmployees" class="stat-value">0</h2>
                         <div class="stat-sub">Currently active</div>
                     </div>
                     <div class="stat-icon">
                         <i class="bi bi-person-check-fill"></i>
                     </div>
                 </div>
             </div>
         </div>

         <div class="col-lg-3 col-md-6 col-12">
             <div class="finance-stat-card">
                 <div class="accent-line"></div>
                 <div class="d-flex justify-content-between align-items-center">
                     <div>
                         <div class="stat-label">Inactive Employees</div>
                         <h2 id="inactiveEmployees" class="stat-value">0</h2>
                         <div class="stat-sub">Not currently active</div>
                     </div>
                     <div class="stat-icon">
                         <i class="bi bi-person-dash-fill"></i>
                     </div>
                 </div>
             </div>
         </div>

         <div class="col-lg-3 col-md-6 col-12">
             <div class="finance-stat-card">
                 <div class="accent-line"></div>
                 <div class="d-flex justify-content-between align-items-center">
                     <div>
                         <div class="stat-label">Pending Employees</div>
                         <h2 id="pendingEmployees" class="stat-value">0</h2>
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
         window.onload = function () { loadFinanceStats(); };
         async function loadFinanceStats() {
             try {
                 const response = await fetch('/admin/api/Finance/getFinanceStats.php?action=getStats');
                 const rawResponse = await response.text();
                 let result;
                 try { result = JSON.parse(rawResponse); } catch (jsonError) { console.error('Invalid JSON:', rawResponse); return; }
                 if (!result.success) { console.error(result.message); return; }
                 const el = (id) => document.getElementById(id);
                 if (el('totalEmployees')) el('totalEmployees').textContent = result.data.total_employees ?? 0;
                 if (el('activeEmployees')) el('activeEmployees').textContent = result.data.active_employees ?? 0;
                 if (el('inactiveEmployees')) el('inactiveEmployees').textContent = result.data.inactive_employees ?? 0;
                 if (el('pendingEmployees')) el('pendingEmployees').textContent = result.data.pending_employees ?? 0;
             } catch (error) {
                 console.error('Failed to load finance statistics:', error);
             }
         }
     </script>
 </div>
