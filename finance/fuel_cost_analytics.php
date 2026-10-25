<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/init.php';
require_once __DIR__ . '/controllers/FuelCostAnalyticsController.php';

use Finance\Controllers\FuelCostAnalyticsController;

$action = $_GET['action'] ?? 'index';
$controller = new FuelCostAnalyticsController();

switch ($action) {
    case 'export_pdf':
        $controller->exportPdf();
        break;
    case 'export_excel':
        $controller->exportExcel();
        break;
    case 'request_otp':
        $controller->requestOtp();
        break;
    case 'verify_otp':
        $controller->verifyOtp();
        break;
    default:
        $controller->index();
        break;
}
?>
<script>
window.addEventListener("pageshow", function (event) {
    if (event.persisted || window.performance.getEntriesByType("navigation")[0].type === "back_forward") {
        window.location.reload();
    }
});
</script>
