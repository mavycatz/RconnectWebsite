<style>
    .route-table-section h5 {
        font-size: 1.2rem;
        font-weight: 700;
        color: #2e4836;
    }
    .route-table-section .route-card {
        background: #faf9e5;
        border: 1px solid #e5e0a4;
        border-radius: 1rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        overflow: hidden;
    }
    .route-table-section .route-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.88rem;
        background: #fff;
    }
    .route-table-section .route-table thead th {
        background: #2e7d32;
        color: #fff;
        padding: 10px 12px;
        font-weight: 600;
        text-align: left;
        white-space: nowrap;
        font-size: 0.8rem;
        letter-spacing: 0.02em;
    }
    .route-table-section .route-table tbody tr {
        border-bottom: 1px solid #f0ebd8;
        transition: background 0.12s;
    }
    .route-table-section .route-table tbody tr:hover {
        background: #f5f0df;
    }
    .route-table-section .route-table tbody td {
        padding: 10px 12px;
        vertical-align: middle;
    }
    .route-table-section .badge-route {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .route-table-section .badge-active {
        background: #e8f5e9;
        color: #2e7d32;
    }
    .route-table-section .badge-inactive {
        background: #fbe9e7;
        color: #c62828;
    }
    .route-table-section .badge-problem {
        display: inline-block;
        margin-left: 6px;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        background: #fbe7e4;
        color: #a32018;
    }
    .route-table-section .problem-link {
        display: inline-block;
        margin-top: 4px;
        font-size: 0.72rem;
        font-weight: 600;
        color: #a32018;
        text-decoration: none;
    }
    .route-table-section .problem-link:hover {
        text-decoration: underline;
    }
    @media(max-width:768px) {
        .route-table-section .route-table { font-size: 0.75rem; }
        .route-table-section .route-table thead th,
        .route-table-section .route-table tbody td { padding: 6px 8px; }
    }
</style>

<div class="route-table-section">
    <h5 class="mb-3"><i class="bi bi-arrow-left-right me-2" style="color:#2e7d32;"></i>Route Assignment</h5>

    <div class="route-card">
        <div class="table-responsive">
            <table class="route-table">
                <thead>
                    <tr>
                        <th>Bus ID</th>
                        <th>Route Name</th>
                        <th>Terminal (From — To)</th>
                        <th>Driver</th>
                        <th>Schedule</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
        <?php if (!empty($buses)): ?>
            <?php foreach ($buses as $bus): ?>
            <tr>
                <td><strong><?= htmlspecialchars($bus['bus_id']) ?></strong></td>
<td><?= htmlspecialchars($bus['route_name']) ?></td>
<td><?= htmlspecialchars($bus['terminal']) ?></td>
<td><?= htmlspecialchars($bus['driver']) ?></td>
<td><?= htmlspecialchars($bus['schedule']) ?></td>
                <td>
    <span class="badge-route <?= (strtolower($bus['status'] ?? '') === 'active') ? 'badge-active' : 'badge-inactive' ?>">
                        <?= htmlspecialchars($bus['status']) ?>
                    </span>
                    <?php if (!empty($bus['open_problem_id'])): ?>
                    <span class="badge-problem">Problem Reported</span>
                    <a class="problem-link" href="/admin/inspectors/?monitoring=dispatcher">
                        <i class="bi bi-cone-striped"></i> View Problem
                    </a>
                    <?php endif; ?>
                    </td>
                    </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" style="text-align:center;padding:2rem;color:#888;">
                    <i class="bi bi-inbox" style="font-size:1.5rem;display:block;margin-bottom:0.5rem;"></i>
                    No route assignments found.
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
    </table>
    </div>
    </div>
    </div>
