<?php

error_reporting(E_ALL);

ini_set('display_errors', 1);

require_once __DIR__ . '/../app/init.php';

require_once __DIR__ . '/controllers/RemittanceController.php';

use Finance\Controllers\RemittanceController;

$action =
    $_GET['action'] ?? 'index';

$controller =
    new RemittanceController();

switch ($action) {

    // =========================
    // INDEX
    // =========================
    case 'index':

        $controller->index();

        break;

    // =========================
    // CONFIRM
    // =========================
    case 'confirm':

        $controller->confirm();

        break;

    // =========================
    // DRIVER TRIPS (READ-ONLY)
    // =========================
    case 'driver_trips':

        $controller->driverTrips();

        break;

    // =========================
    // DOWNLOAD REPORT
    // =========================
    case 'download_report':

        $controller->downloadReport();

        break;

    // =========================
    // SAVE CODE
    // =========================
    case 'save_code':

        $controller->saveCode();

        break;

    // =========================
    // FINANCE CODE STATUS
    // =========================
    case 'finance_code_status':

        $controller->financeCodeStatus();

        break;

    // =========================
    // SEND IDENTITY OTP
    // =========================
    case 'send_code_otp':

        $controller->sendCodeOtp();

        break;

    // =========================
    // VERIFY IDENTITY
    // =========================
    case 'verify_code_identity':

        $controller->verifyCodeIdentity();

        break;

    // =========================
    // AUDIT TRAIL
    // =========================
    case 'audit':

        $controller->auditTrail();

        break;

    // =========================
    // DEFAULT
    // =========================
    default:

        $controller->index();

        break;
}
?>