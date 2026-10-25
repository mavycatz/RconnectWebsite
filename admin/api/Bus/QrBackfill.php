<?php declare(strict_types=1);

/**
 * Bulk QR preparation for existing buses.
 *
 *   GET  ?dry_run=1   list buses without an active QR credential
 *   POST              create the missing credentials (idempotent)
 *
 * Valid credentials are preserved; repeats find nothing left to create.
 * Admin/super_admin only.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/Bus/BusQrService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/Bus/BusQrBackfillService.php';

use Admin\Services\Bus\BusQrBackfillService;
use App\Core\Database;

$actor = rmg_actor();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $db = Database::get();
} catch (Throwable $e) {
    rmg_fail(500, 'QR preparation is unavailable. Please try again.');
}

$origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$dryRun = !empty($_GET['dry_run']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$dryRun) {
    rmg_fail(405, 'QR preparation must be submitted with POST.');
}

try {
    $service = new BusQrBackfillService($db);

    if ($dryRun) {
        $missing = $service->missing();
        echo json_encode([
            'success' => true,
            'dry_run' => true,
            'missing' => count($missing),
            'buses' => $missing,
        ]);
        exit;
    }

    $result = $service->run(
        $origin,
        (string)($actor['user']['username'] ?? ''),
        false
    );

    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/models/AuditTrailModel.php';
        $audit = new \Finance\Models\AuditTrailModel();
        $audit->log(
            'Bus',
            'QrBackfill',
            'Prepared QR credentials for ' . (int)$result['created'] . ' bus(es)',
            isset($actor['user']['id']) ? (int)$actor['user']['id'] : null,
            $actor['user']['username'] ?? null,
            $actor['role'],
            null,
            null,
            ['created' => (int)$result['created'], 'missing_before' => (int)$result['missing_before']],
            'Success'
        );
    } catch (Throwable $e) {
        error_log('QrBackfill audit failed: ' . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'dry_run' => false,
        'created' => (int)$result['created'],
        'missing_before' => (int)$result['missing_before'],
        'missing_after' => (int)$result['missing_after'],
        'buses' => $result['buses'],
        'message' => (int)$result['created'] > 0
            ? 'Prepared ' . (int)$result['created'] . ' QR code(s).'
            : 'Every registered bus already has a QR code.',
    ]);
} catch (Throwable $e) {
    error_log('QrBackfill failed: ' . $e->getMessage());
    rmg_fail(500, 'Could not prepare the bus QR codes. Please try again.');
}
