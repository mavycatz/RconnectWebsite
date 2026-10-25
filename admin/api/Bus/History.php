<?php declare(strict_types=1);

/**
 * Recent activity for one bus, from the audit trail.
 *
 *   GET ?bus_id=BUS-004[&limit=20]
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';

use App\Core\Database;

rmg_actor();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$busId = trim((string)($_GET['bus_id'] ?? ''));
$limit = (int)($_GET['limit'] ?? 20);
$limit = max(1, min(50, $limit));

if ($busId === '') {
    rmg_fail(422, 'Missing bus reference.');
}

try {
    $db = Database::get();
} catch (Throwable $e) {
    rmg_fail(500, 'Activity history is unavailable. Please try again.');
}

try {
    $lookup = $db->prepare('SELECT id FROM buses WHERE bus_id = ?');
    $lookup->execute([$busId]);
    $ref = $lookup->fetchColumn();
    $stmt = $db->prepare(
        'SELECT action, description, username, created_at'
        . ' FROM audit_trail'
        . ' WHERE record_id IN (?, ?)'
        . " AND CONVERT(module USING utf8mb4) COLLATE utf8mb4_general_ci = 'Bus'"
        . ' ORDER BY created_at DESC LIMIT ' . $limit
    );
    $stmt->execute([$busId, $ref ? 'bus:' . $ref : $busId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $rows = [];
}

echo json_encode([
    'success' => true,
    'data' => array_map(static function (array $row): array {
        return [
            'action' => (string)($row['action'] ?? ''),
            'description' => (string)($row['description'] ?? ''),
            'username' => (string)($row['username'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? ''),
        ];
    }, $rows),
]);
