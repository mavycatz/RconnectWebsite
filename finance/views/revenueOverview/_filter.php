<?php if (!defined('APP_LOADED')) exit; ?>

<?php
$filterFrom  = $_GET['fromDate'] ?? '';
$filterTo    = $_GET['toDate'] ?? '';
$filterType  = $_GET['type'] ?? '';
$filterBus   = $_GET['bus'] ?? '';
$filterRoute = $_GET['route'] ?? '';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h3 class="fw-bold text-success mb-0">Revenue Overview</h3>

    <form id="revenueFilter" method="GET" class="d-flex align-items-center gap-2 flex-wrap">

        <select name="type" class="form-select form-select-sm" style="width:auto;">
            <option value="">All Time</option>
            <option value="today" <?= $filterType === 'today' ? 'selected' : '' ?>>Today</option>
            <option value="month" <?= $filterType === 'month' ? 'selected' : '' ?>>This Month</option>
            <option value="year" <?= $filterType === 'year' ? 'selected' : '' ?>>This Year</option>
        </select>

        <input type="date" name="fromDate" value="<?= htmlspecialchars($filterFrom) ?>"
               class="form-control form-control-sm" style="width:140px;" placeholder="From">

        <input type="date" name="toDate" value="<?= htmlspecialchars($filterTo) ?>"
               class="form-control form-control-sm" style="width:140px;" placeholder="To">

        <input type="text" name="bus" value="<?= htmlspecialchars($filterBus) ?>"
               class="form-control form-control-sm" style="width:100px;" placeholder="Bus #">

        <input type="text" name="route" value="<?= htmlspecialchars($filterRoute) ?>"
               class="form-control form-control-sm" style="width:150px;" placeholder="Route (e.g. City→Mall)">

        <?php if ($filterFrom || $filterTo || $filterType || $filterBus || $filterRoute): ?>
            <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" class="btn btn-outline-secondary btn-sm">Clear</a>
        <?php endif; ?>
    </form>
</div>

<script>
document.getElementById("revenueFilter").addEventListener("change", function () {
    this.submit();
});
document.querySelectorAll('#revenueFilter input[type="text"]').forEach(function (el) {
    el.addEventListener("input", function () {
        clearTimeout(this._timer);
        this._timer = setTimeout(function () {
            el.form.submit();
        }, 600);
    });
});
</script>