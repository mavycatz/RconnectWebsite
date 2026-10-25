<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/init.php';
require_once __DIR__ . '/controllers/OnlinePaymentVerificationController.php';

use Finance\Controllers\OnlinePaymentVerificationController;

$action = $_GET['action'] ?? 'index';
$controller = new OnlinePaymentVerificationController();

switch ($action) {
    case 'verify':
        $controller->verify();
        break;
    case 'screenshot':
        $controller->getScreenshot();
        break;
    case 'export_pdf':
        $controller->exportPdf();
        break;
    case 'export_excel':
        $controller->exportExcel();
        break;
    default:
        $controller->index();
        break;
}
