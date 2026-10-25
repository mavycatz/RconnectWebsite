<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Bus Dashboard</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/chart.js">        </script>
        <link rel="icon" href="data:," type="image/x-icon">
        <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

        <style>
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                width: 220px;
                background: #f8f6ea;
                color: #333;
                display: flex;
                flex-direction: column;
                box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
                z-index: 999;
                overflow-y: auto;
            }
            .sidebar.collapsed { transform: translateX(-220px); }

            .nav-link {
                position: relative;
                color: #333;
                font-weight: 500;
                transition: background 0.25s ease, color 0.25s ease;
                border-radius: 0.5rem;
                padding: 6px 10px;
                margin-bottom: 3px;
            }
            .nav-link:hover { background: #f5f4e6; color: #000; }

            .nav-link.active {
                background: linear-gradient(to right, #198754 3%, #ffe082 3% 100%);
                color: #000;
                font-weight: 600;
                padding-right: 30px;
                border-radius: 0.6rem;
                animation: bookmarkIn 0.45s cubic-bezier(.4, 0, .2, 1);
            }
            .nav-link.active::after {
                content: '';
                position: absolute;
                top: 50%;
                right: 5px;
                width: 10px;
                height: 10px;
                border-left: 10px solid #198754;
                border-top: 6px solid transparent;
                border-bottom: 6px solid transparent;
                transform: translateY(-50%) scale(0);
                animation: arrowIn 0.35s ease-out 0.25s forwards;
            }

            @keyframes bookmarkIn {
                from { background-position: -120% 0; }
                to { background-position: 0 0; }
            }
            @keyframes arrowIn {
                0% { transform: translateY(-50%) scale(0); }
                70% { transform: translateY(-50%) scale(1.15); }
                100% { transform: translateY(-50%) scale(1); }
            }

            .sidebar .section-title {
                font-size: .75rem;
                font-weight: 600;
                text-transform: uppercase;
                color: #666;
                margin: 8px 0 4px 10px;
                letter-spacing: .5px;
            }
            .sidebar .collapse-inner a { padding-left: 25px; font-size: .85rem; }
            .sidebar hr { border-color: rgba(0, 0, 0, .1); margin: 10px 0; }

            .sidebar-header { background-color: #e3ebbc; border-radius: 10px; }
            .icon-container i {
                font-size: 20px;
                color: #86858f;
                transition: color 0.3s;
                cursor: pointer;
            }

            .icon-container i:hover {
                color: #198754;
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
                z-index: 998;
                transition: all 0.4s ease;
            }
            .header.sidebar-collapsed { left: 0; width: 100%; }

            .icon-container {
                width: 40px;
                height: 40px;
                border: 2px solid #86858f;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .main-content {
                margin-left: 220px;
                margin-top: 60px;
                padding: 15px;
                transition: all 0.4s ease;
            }
            .main-content.sidebar-collapsed { margin-left: 0; }

            @media(max-width:992px) {
                .sidebar { transform: translateX(-220px); }
                .sidebar.show { transform: translateX(0); }
                .header { left: 0; }
                .main-content { margin-left: 0; margin-top: 120px; }
            }
        </style>
    </head>
    <body>

        <div class="sidebar" id="sidebar">
            <div class="sidebar-header p-3 mb-4">
                <div class="d-flex align-items-center mb-1">
                    <img src="../assets/images/passenger/logoR.png" style="width:40px;height:40px;margin-right:10px;">
                    <div class="fw-bold" style="font-size:1.5rem;font-family:'League Spartan',sans-serif;">R-Connect</div>
                </div>
                <div style="font-family:'Poppins',sans-serif;font-size:.6rem;">Transportation Management System</div>
            </div>

            <ul class="nav nav-pills flex-column mb-auto">
                <<li class="section-title">Main</li>
                <li class="nav-item">
                    <a href="/admin/views/dashboard/index.php"
       class="nav-link <?php echo ($currentPage=='index.php')?'active':''; ?>">
       Dashboard
</a>
</li>
<hr>

<li class="section-title">Monitoring</li>
<li class="nav-item"><a href="Tripdashboard.php" class="nav-link <?php echo ($currentPage=='Tripdashboard.php')?'active':''; ?>">Trips</a></li>
<li class="nav-item"><a href="Logging.php" class="nav-link <?php echo ($currentPage=='Logging.php')?'active':''; ?>">Logging</a></li>
<hr>

<li class="section-title">Settings</li>
<li class="nav-item">
    <a class="nav-link d-flex justify-content-between align-items-center <?php echo ($currentPage=='DriversInfo.php'||$currentPage=='Inspectorinfo.php'||$currentPage=='financeinfo.php'||$currentPage=='Admininfo.php')?'active':''; ?>"
               data-bs-toggle="collapse"
               href="#roleMgmtCollapse"
               aria-expanded="<?php echo ($currentPage=='DriversInfo.php'||$currentPage=='Inspectorinfo.php'||$currentPage=='financeinfo.php'||$currentPage=='Admininfo.php')?'true':'false'; ?>">
                Role Management
<i class="bi bi-chevron-down"></i>
</a>
<div class="collapse <?php echo ($currentPage=='DriversInfo.php'||$currentPage=='Inspectorinfo.php'||$currentPage=='financeinfo.php'||$currentPage=='Admininfo.php')?'show':''; ?>" id="roleMgmtCollapse">
<ul class="nav flex-column collapse-inner ps-3">
    <li><a href="DriversInfo.php" class="nav-link <?php echo ($currentPage=='DriversInfo.php')?'active':''; ?>">Drivers</a></li>
<li><a href="Inspectorinfo.php" class="nav-link <?php echo ($currentPage=='Inspectorinfo.php')?'active':''; ?>">Inspectors</a></li>
<li><a href="financeinfo.php" class="nav-link <?php echo ($currentPage=='financeinfo.php')?'active':''; ?>">Financial Department</a></li>
<li><a href="Admininfo.php" class="nav-link <?php echo ($currentPage=='Admininfo.php')?'active':''; ?>">Admin</a></li>
</ul>
</div>
</li>

<li class="nav-item"><a href="busmanagement.php" class="nav-link <?php echo ($currentPage=='busmanagement.php')?'active':''; ?>">Bus Management</a></li>
<li class="nav-item"><a href="Systemanagement.php" class="nav-link <?php echo ($currentPage=='Systemanagement.php')?'active':''; ?>">System Management</a></li>
</ul>
</div>

<div class="header d-flex justify-content-between align-items-center px-3">
    <div class="d-flex align-items-center gap-2">
        <button class="hamburger" id="sidebarToggler">
            <span></span><span></span><span></span>
        </button>
        <input type="text" class="form-control" placeholder="Search..." style="width:700px;">
    </div>
    <div class="d-flex gap-3">
        <div class="icon-container">
            <i class="bi bi-bell"></i>
        </div>
        <div class="icon-container">
            <i class="bi bi-person"></i>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('sidebar')
        const header = document.querySelector('.header')
        const main = document.querySelector('.main-content')
        document.getElementById('sidebarToggler').onclick = () => {
            sidebar.classList.toggle('collapsed')
            header.classList.toggle('sidebar-collapsed')
            if (main)main.classList.toggle('sidebar-collapsed')
        }
    })
</script>

</body>
</html>
