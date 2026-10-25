<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/Controllers/DashboardController.php';

use App\Core\Database;
use Admin\Controllers\DashboardController;

header('Content-Type: application/json');

$db = Database::get();
$controller = new DashboardController($db);

$metric = $_GET['metric'] ?? '';

if ($metric === '') {
    echo json_encode(['success' => false, 'message' => 'Metric is required']);
    exit;
}

echo json_encode($controller->getStatDetail($metric));
