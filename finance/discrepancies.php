<?php

error_reporting(E_ALL);

ini_set('display_errors', 1);

require_once __DIR__ . '/../app/init.php';

require_once __DIR__ . '/controllers/DiscrepancyController.php';

use Finance\Controllers\DiscrepancyController;

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
    new DiscrepancyController();

/**
 * =========================
 * ROUTES
 * =========================
 */
switch ($action) {

    // PAGE
    case 'index':
        $controller->index();
        break;

    // JSON: filtered case list + metrics
    case 'list':
        $controller->list();
        break;

    // JSON: case details workspace
    case 'view':
        $controller->view();
        break;

    // JSON: lifecycle actions
    case 'assign':
        $controller->assign();
        break;

    case 'status':
        $controller->status();
        break;

    case 'reclassify':
        $controller->reclassify();
        break;

    case 'findings':
        $controller->findings();
        break;

    // Investigation submission and manager approval workflow
    case 'submit_findings':
        $controller->submitFindings();
        break;

    case 'manager_review':
        $controller->managerReview();
        break;

    case 'manager_decision':
        $controller->managerDecision();
        break;

    case 'evidence':
        $controller->evidence();
        break;

    // JSON: resolution ledger
    case 'ledger_propose':
        $controller->ledgerPropose();
        break;

    case 'approval_decide':
        $controller->approvalDecide();
        break;

    case 'ledger_post':
        $controller->ledgerPost();
        break;

    case 'ledger_void':
        $controller->ledgerVoid();
        break;

    // JSON: authorization rules
    case 'rules_get':
        $controller->rulesGet();
        break;

    case 'rules_save':
        $controller->rulesSave();
        break;

    // JSON: import existing remittances into cases (manager-only)
    case 'backfill':
        $controller->backfill();
        break;

    // JSON: assignable finance users for reviewer assignment
    case 'finance_users':
        $controller->financeUsers();
        break;

    // AUDIT TRAIL (READ ONLY)
    case 'audit':
        $controller->auditTrail();
        break;

    // REPORTS
    case 'print':
        $controller->print();
        break;

    case 'export_pdf':
        $controller->exportPdf();
        break;

    case 'export_excel':
        $controller->exportExcel();
        break;

    // DEFAULT
    default:
        $controller->index();
        break;
}
