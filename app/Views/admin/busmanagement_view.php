<?php
declare(strict_types=1);

use App\Models\Terminal;
use App\Controllers\BusController;
use App\Models\Driver;

$terminalModel = new Terminal();
$terminals = $terminalModel->getActive();

$busController = new BusController();
$buses = $busController->getAllBuses();

$driverModel = new Driver();
$availableDrivers = $driverModel->getAvailableDrivers();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
html, body {
    background-color: #eceed9 !important;
}

/* Scope EVERYTHING inside page-scope */
.page-scope {
    font-family: 'Poppins', sans-serif;
}

.page-scope * {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

/* Layout */
.page-scope .main-content {
    margin-left: 220px;
    padding: 20px;
    width: calc(100% - 220px);
}

/* Scroll Cards */
.page-scope .scroll-cards {
    display: flex;
    gap: 1rem;
    overflow-x: auto;
    padding: 1rem 0;
    scroll-snap-type: x mandatory;
}

.page-scope .scroll-cards::-webkit-scrollbar {
    height: 8px;
}

.page-scope .scroll-cards::-webkit-scrollbar-thumb {
    background: #b8e405;
    border-radius: 4px;
}

.page-scope .scroll-cards::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.page-scope .scroll-cards .card {
    flex: 0 0 auto;
    width: 280px;
    background: #faf9e5;
    border: 1px solid #e5e0a4;
    border-radius: 12px;
    padding: 1rem;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 380px;
    scroll-snap-align: start;
}

/* Tables */
.page-scope .table {
    width: 100%;
    margin-bottom: 2rem;
}

.page-scope .table th,
.page-scope .table td {
    vertical-align: middle;
}

/* Buttons */
.page-scope .btn i {
    margin-right: .5rem;
}

/* Responsive */
@media (max-width: 768px) {
    .page-scope .main-content {
        margin-left: 0;
        padding: 15px;
        width: 100%;
    }
}
</style>
</head>
<body>

<!-- Sidebar -->
<?php require $_SERVER['DOCUMENT_ROOT'] . '/admin/assets/layout/navbaradmin.php'; ?>
<!-- Main Content -->
<div class="page-scope">
<div class="main-content">
        <div
        style="
            background:#fef8f3;
            border:1px solid #aaa8b1;
            border-radius:24px;
            padding:24px;
        "
    >
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; width:100%;">
  <div class="d-flex justify-content-between align-items-center mb-3">

    <div style="
        font-weight:700;
        font-size:1.3rem;
        font-family:'Poppins', sans-serif;
        color:#295738;">
      Bus QR Management
    </div>
  </div>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addBusModal" style="display:flex; align-items:center;">
        <i class="bi bi-plus-circle me-1"></i> Add New Bus
    </button>
</div>

<!-- Stats Cards -->
<div class="d-flex flex-wrap justify-content-center gap-3" style="width: 100%; margin-bottom: 2rem;">

    <!-- Total Buses -->
    <div style="display: flex; align-items: center; background: #faf9e5; border: 1px solid #e5e0a4; border-radius: 12px; padding: 1rem; min-width: 220px; min-height: 100px; flex: 1 1 22%;">
        <i class="bi bi-bus-front-fill" style="font-size: 3.5rem; color: #295738; margin-right: 1rem;"></i>
        <div>
            <h6 style="margin-bottom: .25rem; font-weight: 600; color: #295738;">Total Buses</h6>
            <h3 id="totalBuses" style="margin: 0; color: #000;">0</h3>
        </div>
    </div>

    <!-- Active Trips -->
    <div style="display: flex; align-items: center; background: #faf9e5; border: 1px solid #e5e0a4; border-radius: 12px; padding: 1rem; min-width: 220px; min-height: 100px; flex: 1 1 22%;">
        <i class="bi bi-geo-alt-fill" style="font-size: 3.5rem; color: #295738; margin-right: 1rem;"></i>
        <div>
            <h6 style="margin-bottom: .25rem; font-weight: 600; color: #295738;">Active Trips</h6>
            <h3 id="withQR" style="margin: 0; color: #000;">0</h3>
        </div>
    </div>

    <!-- Passengers Today -->
    <div style="display: flex; align-items: center; background: #faf9e5; border: 1px solid #e5e0a4; border-radius: 12px; padding: 1rem; min-width: 220px; min-height: 100px; flex: 1 1 22%;">
        <i class="bi bi-people-fill" style="font-size: 3.5rem; color: #295738; margin-right: 1rem;"></i>
        <div>
            <h6 style="margin-bottom: .25rem; font-weight: 600; color: #295738;">Passengers Today</h6>
            <h3 id="withoutQR" style="margin: 0; color: #000;">0</h3>
        </div>
    </div>

    <!-- Revenue Today -->
    <div style="display: flex; align-items: center; background: #faf9e5; border: 1px solid #e5e0a4; border-radius: 12px; padding: 1rem; min-width: 220px; min-height: 100px; flex: 1 1 22%;">
        <i class="bi bi-cash-coin" style="font-size: 3.5rem; color: #295738; margin-right: 1rem;"></i>
        <div>
            <h6 style="margin-bottom: .25rem; font-weight: 600; color: #295738;">Revenue Today</h6>
            <h3 id="pendingQR" style="margin: 0; color: #000;">0</h3>
        </div>
    </div>

</div>

<h2 class="mb-2" style="color:#295738; font-size:1.5rem; font-weight:700;">QR Generation</h2>

<!-- Include QR Modal -->
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/modals/add_bus_modal.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/modals/edit_bus_modal.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/modals/qr_generation_modal.php';
?>

<!-- Scrollable Cards Container -->
<div class="scroll-cards">
<?php foreach ($buses as $bus): ?>
    <div class="card">
        <div style="background:linear-gradient(90deg,#b8e405 0%,#295738 100%); padding:0.5rem 1rem; border-radius:8px; color:#fff; font-weight:600; text-align:center; font-size:1rem; margin-bottom:0.75rem;">
            <?= $bus['bus_id'] ?>
        </div>
        <div style="flex-grow:1; display:flex; flex-direction:column; justify-content:space-between; font-size:0.875rem;">
            <p><strong>Plate Number:</strong> <?= $bus['plate_number'] ?></p>
            <p><strong>Type:</strong> <?= $bus['bus_type'] ?></p>
            <p><strong>QR Code:</strong> <?= $bus['qr_code'] ?></p>
            <p><strong>Total Seats:</strong> <?= $bus['seats'] ?></p>
            <p><strong>Standing Capacity:</strong> <?= $bus['standing_capacity'] ?></p>
            <p><strong>Accessible:</strong> <?= $bus['accessible_features'] ?></p>
            <p><strong>Status:</strong> <?= $bus['status'] ?></p>
        </div>
        <div style="text-align:center; margin-top:0.5rem;">
            <i class="bi bi-qr-code-scan" style="font-size:2rem; color:#295738;"></i>
        </div>
<div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-bottom:0.5rem;">
    <!-- QR Button -->
    <button class="btn btn-success" 
        style="flex:1; height:45px; font-size:0.85rem;"
        onclick='openQRModal(<?= json_encode($bus["bus_id"]) ?>)'
        <i class="bi bi-qr-code-scan"></i> <?= $bus["qr_code"] === "Generated" ? "QR Generated" : "Generate QR" ?>
    </button>

    <!-- Edit Button -->
    <button class="btn btn-success" 
        style="flex:1; height:45px; font-size:0.85rem; background:#7fc97f; border:none;">
        <i class="bi bi-pencil-square"></i> Edit
    </button>

    <!-- Archive Button -->
    <button class="btn btn-success" 
        style="flex:1; height:45px; font-size:0.85rem; background:#4daf4a; border:none;">
        <i class="bi bi-archive"></i> Archive
    </button>
</div>

    </div>
<?php endforeach; ?>
</div>
<!-- Route Assignment Table -->
<h5 class="mb-3">Route Assignment</h5>
<table class="table table-striped table-bordered" style="border-color:#d4e6a5;">
    <thead style="background:#e6f0c4; color:#2f4f2f;">
        <tr>
            <th>Bus ID</th>
            <th>Route Name</th>
            <th>Terminal (From-To)</th>
            <th>Driver</th>
            <th>Schedule</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody style="background:#f9fbe7;">
        <?php foreach ($buses as $bus): ?>
        <tr>
            <td><?= $bus['bus_id'] ?></td>
            <td><?= $bus['route_name'] ?></td>
            <td><?= $bus['terminal'] ?></td>
            <td><?= $bus['driver'] ?></td>
            <td><?= $bus['schedule'] ?></td>
            <td><?= $bus['status'] ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- QR Management Table -->
<h5 class="mb-3">QR Management</h5>
<table class="table table-striped table-bordered" style="border-color:#d4e6a5;">
    <thead style="background:#e6f0c4; color:#2f4f2f;">
        <tr>
            <th>Bus ID</th>
            <th>Plate Number</th>
            <th>Seats</th>
            <th>Driver</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody style="background:#f9fbe7;">
        <?php foreach ($buses as $bus): ?>
        <tr>
            <td><?= $bus['bus_id'] ?></td>
            <td><?= $bus['plate_number'] ?></td>
            <td><?= $bus['seats'] ?></td>
            <td><?= $bus['driver'] ?></td>
            <td><?= $bus['status'] ?></td>
            <td class="text-center" style="white-space:nowrap;">
                <a href="#" style="color:#7fc97f;" title="View QR"><i class="bi bi-qr-code"></i></a>
                <a href="#" style="color:#a3d9a5;" title="Edit QR" data-bs-toggle="modal" data-bs-target="#editBusModal"><i class="bi bi-pencil-square"></i></a>
                <a href="#" style="color:#cfe6a6;" title="Add QR"><i class="bi bi-plus-square"></i></a>
                <a href="#" style="color:#f4a261;" title="Delete QR"><i class="bi bi-trash"></i></a>
                <a href="#" style="color:#b8e405;" title="Print QR"><i class="bi bi-printer"></i></a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/admin/assets/js/qr_modal.js"></script>

<script>
async function loadBusStats() {
    try {
        const res = await fetch('/admin/api/stats_bus.php');
        const resData = await res.json();

        // ✅ HANDLE RESPONSE FORMAT
        if (!resData.success) {
            console.error(resData.message || 'Failed to load stats');
            return;
        }

        const data = resData.data;

        // ✅ UPDATED MAPPING (NO UI CHANGE)
        document.getElementById('totalBuses').textContent = data.total ?? 0;

        // 🔥 Replace old QR stats with real data
        document.getElementById('withQR').textContent    = data.active_trips ?? 0;
        document.getElementById('withoutQR').textContent = data.passengers ?? 0;
        document.getElementById('pendingQR').textContent = data.revenue ?? 0;

    } catch(err) {
        console.error('Error fetching bus stats:', err);
    }
}

// Load stats on page load
document.addEventListener('DOMContentLoaded', loadBusStats);
</script>

<script src="/admin/assets/js/qr_modal.js"></script>
</body>
</html>
