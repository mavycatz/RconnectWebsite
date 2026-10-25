<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';

$svcCandidates = [
    $_SERVER['DOCUMENT_ROOT'] . '/app/services/DriverPerformanceService.php',
    $_SERVER['DOCUMENT_ROOT'] . '/app/Services/DriverPerformanceService.php'
];
$svcPath = '';

foreach ($svcCandidates as $candidate) {
    if (file_exists($candidate)) {
        $svcPath = $candidate;
        break;
    }
}

if ($svcPath === '') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'DriverPerformanceService.php is missing under app/services/ - upload the new file there.',
        'path'    => $svcCandidates[0]
    ]);
    exit;
}

require_once $svcPath;

use App\Services\DriverPerformanceService;

header('Content-Type: application/json');

try {
    $filters = [
        'month'    => $_GET['month'] ?? '',
        'year'     => $_GET['year'] ?? '',
        'fromDate' => $_GET['fromDate'] ?? '',
        'toDate'   => $_GET['toDate'] ?? ''
    ];

    $data = (new DriverPerformanceService())->getDriverPerformance($filters, [
        'orderBy'  => 'income',
        'orderDir' => 'DESC'
    ]);

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}