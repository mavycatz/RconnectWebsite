<?php declare(strict_types=1);

/**
 * QR panel data for one bus.
 *
 * Returns the bus identity, the active QR credential (payload URL, status,
 * creation/replacement dates, stored image), the credential history, and the
 * legacy mirror values, so the profile QR section can render without
 * generating anything.
 *
 *   GET ?bus_ref=12   (internal id preferred)
 *   GET ?bus_id=BUS-004
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/Bus/BusQrService.php';

use Admin\Services\Bus\BusQrService;
use App\Core\Database;

rmg_actor();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$busRef = trim((string)($_GET['bus_ref'] ?? ''));
$busId = trim((string)($_GET['bus_id'] ?? ''));

if ($busRef === '' && $busId === '') {
    rmg_fail(422, 'Missing bus reference.');
}

try {
    $db = Database::get();
} catch (Throwable $e) {
    rmg_fail(500, 'QR information is unavailable. Please try again.');
}

try {
    $columns = ['id', 'bus_id', 'plate_number', 'status'];
    $optional = ['unit_number', 'operational_status', 'qr_status', 'qr_code', 'qr_data'];
    foreach ($optional as $column) {
        try {
            $db->query('SELECT `' . $column . '` FROM buses LIMIT 1');
            $columns[] = $column;
        } catch (Throwable $e) {
        }
    }
    $select = implode(', ', array_map(static fn ($c) => '`' . $c . '`', $columns));

    if ($busRef !== '' && ctype_digit($busRef)) {
        $stmt = $db->prepare('SELECT ' . $select . ' FROM buses WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$busRef]);
    } else {
        $stmt = $db->prepare('SELECT ' . $select . ' FROM buses WHERE bus_id = ? LIMIT 1');
        $stmt->execute([$busId]);
    }
    $bus = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    rmg_fail(500, 'Could not load the bus record.');
}

if (!$bus) {
    rmg_fail(404, 'Bus not found.');
}

BusQrService::ensureSchema($db);
$qr = new BusQrService($db);
$internalRef = (int)$bus['id'];
$active = $qr->activeFor($internalRef);
$history = $qr->historyFor($internalRef, 10);

echo json_encode([
    'success' => true,
    'bus' => [
        'ref' => $internalRef,
        'bus_id' => (string)($bus['bus_id'] ?? ''),
        'plate' => (string)($bus['plate_number'] ?? ''),
        'unit_number' => (string)($bus['unit_number'] ?? ''),
        'status' => (string)($bus['status'] ?? ''),
        'operational_status' => (string)($bus['operational_status'] ?? ''),
    ],
    'qr' => $active === null ? null : [
        'token' => (string)$active['token'],
        'payload_url' => (string)$active['payload_url'],
        'status' => (string)$active['status'],
        'created_at' => (string)($active['created_at'] ?? ''),
        'replaced_at' => $active['replaced_at'] ?? null,
        'image_path' => (string)($active['image_path'] ?? ''),
    ],
    'needs_preparation' => $active === null,
    'history' => array_map(static function (array $row): array {
        return [
            'status' => (string)($row['status'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? ''),
            'replaced_at' => $row['replaced_at'] ?? null,
            'created_by' => (string)($row['created_by'] ?? ''),
            'reason' => (string)($row['reason'] ?? ''),
        ];
    }, $history),
    'legacy' => [
        'qr_status' => (string)($bus['qr_status'] ?? ''),
        'qr_code' => (string)($bus['qr_code'] ?? ''),
        'qr_data' => (string)($bus['qr_data'] ?? ''),
    ],
]);
