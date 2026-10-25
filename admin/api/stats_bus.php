<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Bootstrap.php';

use App\Auth\Session;
use App\Auth\Auth;
use App\Core\Database;

Session::start();

header('Content-Type: application/json');

if (!Auth::check() || !in_array(Auth::user()['role'], ['admin', 'super_admin'])) {
    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

try {

    $db = Database::get();

    // Total buses
    $total = $db->query("
        SELECT COUNT(*)
        FROM buses
        WHERE status <> 'Archived'
    ")->fetchColumn();

    // QR Generated
    $withQr = $db->query("
        SELECT COUNT(*)
        FROM buses
        WHERE qr_status = 'Generated'
        AND status <> 'Archived'
    ")->fetchColumn();

    // Pending QR
    $pendingQr = $db->query("
        SELECT COUNT(*)
        FROM buses
        WHERE status <> 'Archived'
        AND (
            qr_status IS NULL
            OR qr_status = ''
            OR qr_status = 'Pending'
        )
    ")->fetchColumn();

    // Without QR
    $withoutQr = $db->query("
        SELECT COUNT(*)
        FROM buses
        WHERE status <> 'Archived'
        AND (
            qr_code IS NULL
            OR qr_code = ''
        )
        AND (
            qr_status IS NULL
            OR qr_status = ''
        )
    ")->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => [
            'total'       => (int)$total,
            'with_qr'     => (int)$withQr,
            'without_qr'  => (int)$withoutQr,
            'pending_qr'  => (int)$pendingQr
        ]
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}