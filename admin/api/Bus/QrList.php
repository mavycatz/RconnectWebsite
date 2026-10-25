<?php declare(strict_types=1);

/**
 * QR payload list for the bulk printable sheet.
 *
 * Returns every bus that has an active QR credential with the payload the QR
 * image should encode, labelled with its Bus ID and unit number. Admin /
 * super_admin only; read-only.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/Bus/BusQrService.php';

use Admin\Services\Bus\BusQrService;
use App\Core\Database;

rmg_actor();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $db = Database::get();
} catch (Throwable $e) {
    rmg_fail(500, 'QR information is unavailable. Please try again.');
}

try {
    BusQrService::ensureSchema($db);

    $hasUnit = false;
    try {
        $db->query('SELECT unit_number FROM buses LIMIT 1');
        $hasUnit = true;
    } catch (Throwable $e) {
        $hasUnit = false;
    }

    $sql = 'SELECT b.id AS ref, b.bus_id AS bus_id, COALESCE(b.plate_number, \'\') AS plate,'
        . ($hasUnit ? ' COALESCE(b.unit_number, \'\')' : ' \'\'') . ' AS unit_number,'
        . ' c.token AS token, c.payload_url AS payload_url, c.created_at AS created_at,'
        . ' c.image_path AS image_path'
        . ' FROM buses b'
        . ' INNER JOIN ' . BusQrService::TABLE . ' c'
        . ' ON c.bus_ref = b.id AND c.status = ' . $db->quote(BusQrService::STATUS_ACTIVE)
        . ' WHERE b.bus_id IS NOT NULL AND LENGTH(b.bus_id) > 0'
        . ' ORDER BY b.bus_id ASC';

    $stmt = $db->query($sql);
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    $buses = [];
    foreach ($rows as $row) {
        $buses[] = [
            'ref' => (int)($row['ref'] ?? 0),
            'bus_id' => (string)($row['bus_id'] ?? ''),
            'plate' => (string)($row['plate'] ?? ''),
            'unit_number' => (string)($row['unit_number'] ?? ''),
            'token' => (string)($row['token'] ?? ''),
            'payload_url' => (string)($row['payload_url'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? ''),
            'image_path' => (string)($row['image_path'] ?? ''),
        ];
    }

    // Buses still missing a credential, so the sheet can note them.
    $missingStmt = $db->query(
        'SELECT b.bus_id FROM buses b'
        . ' LEFT JOIN ' . BusQrService::TABLE . ' c'
        . ' ON c.bus_ref = b.id AND c.status = ' . $db->quote(BusQrService::STATUS_ACTIVE)
        . ' WHERE b.bus_id IS NOT NULL AND LENGTH(b.bus_id) > 0'
        . ' AND c.credential_id IS NULL ORDER BY b.bus_id ASC'
    );
    $missing = $missingStmt ? $missingStmt->fetchAll(PDO::FETCH_COLUMN) : [];

    echo json_encode([
        'success' => true,
        'buses' => $buses,
        'missing' => array_map('strval', $missing),
        'total' => count($buses),
    ]);
} catch (Throwable $e) {
    error_log('QrList failed: ' . $e->getMessage());
    rmg_fail(500, 'Could not load the bus QR list. Please try again.');
}
