<?php

error_reporting(E_ALL);

ini_set('display_errors', 1);

require_once __DIR__ . '/../app/init.php';

require_once __DIR__ . '/controllers/HistoryController.php';

use Finance\Controllers\HistoryController;

/**
 * =========================
 * ACTION
 * =========================
 */
$action =
    $_GET['action'] ?? 'index';

/**
 * =========================
 * CONTROLLER
 * =========================
 */
$controller =
    new HistoryController();

/**
 * =========================
 * ROUTES
 * =========================
 */
switch ($action) {

    // =========================
    // HISTORY PAGE
    // =========================
    case 'index':

        $controller->index();

        break;

    // =========================
    // VIEW DETAILS
    // =========================
    case 'view':

        $controller->view();

        break;

    // =========================
    // REQUEST PRINT OTP
    // =========================
    case 'request_otp':

        $controller->requestOtp();

        break;

    // =========================
    // PRINT RECEIPT
    // =========================
    case 'print':

        $controller->print();

        break;

    // =========================
    // EXPORT PDF
    // =========================
    case 'export_pdf':

        $controller->exportPdf();

        break;

    // =========================
    // EXPORT EXCEL
    // =========================
    case 'export_excel':

        $controller->exportExcel();

        break;

    // =========================
    // DEFAULT
    // =========================
    default:

        $controller->index();

        break;
}
?>