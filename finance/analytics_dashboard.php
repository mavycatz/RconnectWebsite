<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../app/init.php';
require_once __DIR__ . '/controllers/AnalyticsDashboardController.php';

use Finance\Controllers\AnalyticsDashboardController;

(new AnalyticsDashboardController())->index();
?>
<script>
window.addEventListener("pageshow", function (event) {
    if (event.persisted || window.performance.getEntriesByType("navigation")[0].type === "back_forward") {
        window.location.reload();
    }
});
</script>