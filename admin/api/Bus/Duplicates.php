<?php declare(strict_types=1);

/**
 * Plate-number duplicate detection for the Bus Management forms.
 *
 * Read-only. Returns the existing buses whose plate matches the submitted
 * one (normalized), excluding the bus being edited. Nothing is merged or
 * changed here.
 *
 *   GET/POST ?plate_number=ABC%201234[&exclude_ref=12]
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/Bus/BusDuplicates.php';

use Admin\Services\Bus\BusDuplicates;
use App\Core\Database;

rmg_actor();

header('Cache-Control: no-store');

$input = rmg_input();
$plate = trim((string)($input['plate_number'] ?? $input['plate'] ?? ''));
$excludeRef = (int)($input['exclude_ref'] ?? 0);

if ($plate === '') {
    echo json_encode(['success' => true, 'duplicate' => null, 'duplicates' => []]);
    exit;
}

try {
    $db = Database::get();
} catch (Throwable $e) {
    rmg_fail(500, 'Duplicate check is unavailable. Please try again.');
}

$service = new BusDuplicates($db);
$duplicates = $service->find($plate, $excludeRef > 0 ? $excludeRef : null);
$first = $duplicates[0] ?? null;

echo json_encode([
    'success' => true,
    'duplicate' => $first,
    'duplicates' => $duplicates,
    'message' => $first !== null
        ? 'Plate number ' . $first['plate'] . ' is already used by bus ' . ($first['bus_id'] !== '' ? $first['bus_id'] : '#' . $first['ref']) . '.'
        : null,
]);
