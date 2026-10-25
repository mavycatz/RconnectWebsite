<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../app/init.php';
require_once __DIR__ . '/controllers/FleetPerformanceController.php';

use Finance\Controllers\FleetPerformanceController;

(new FleetPerformanceController())->index();
?>
<script>
window.addEventListener("pageshow", function (event) {
    if (event.persisted || window.performance.getEntriesByType("navigation")[0].type === "back_forward") {
        window.location.reload();
    }
});
</script>
