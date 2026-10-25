<?php

/**
 * Live duplicate check used by the admin Create/Edit User forms.
 *
 * Protected by the admin authorization guard: only an authenticated
 * administrator can ask. Table and column names are fixed by the type map
 * below and every lookup uses a prepared statement, so no caller-supplied
 * identifier ever reaches SQL.
 */

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/api/Admin/_auth_guard.php';

use App\Core\Database;

admin_api_actor_role();

$input = [];
$raw = file_get_contents('php://input');

if (is_string($raw) && $raw !== '') {
    $decoded = json_decode($raw, true);

    if (is_array($decoded)) {
        $input = $decoded;
    }
}

$type = trim((string) ($input['type'] ?? ''));
$value = trim((string) ($input['value'] ?? ''));
$excludeRaw = $input['exclude_id'] ?? null;
$excludeId = is_string($excludeRaw) && ctype_digit($excludeRaw)
    ? (int) $excludeRaw
    : (is_int($excludeRaw) && $excludeRaw > 0 ? $excludeRaw : null);

$map = [
    'user_username' => ['users', 'username', 'id', 'Username is already taken.'],
    'user_email' => ['users', 'email', 'id', 'Email is already registered.'],
    'driver_email' => ['drivers', 'email', 'id', 'Email is already registered to another driver.'],
    'driver_license' => ['drivers', 'license_number', 'id', 'License number is already in use.'],
    'inspector_email' => ['inspectors', 'email', 'inspector_pk', 'Email is already registered to another inspector.'],
    'inspector_id' => ['inspector_accounts', 'inspector_id', 'account_id', 'Inspector ID is already in use.'],
];

if (!isset($map[$type])) {
    admin_api_fail(400, 'Unsupported check.');
}

if ($value === '' || strlen($value) > 150) {
    admin_api_fail(400, 'Invalid value.');
}

[$table, $column, $idColumn, $duplicateMessage] = $map[$type];

$isDuplicate = false;

try {
    $conn = Database::get();

    $sql = 'SELECT 1 FROM `' . $table . '` WHERE LOWER(`' . $column . '`) = LOWER(?)';
    $params = [$value];

    if ($excludeId !== null) {
        $sql .= ' AND `' . $idColumn . '` <> ?';
        $params[] = $excludeId;
    }

    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $isDuplicate = (bool) $stmt->fetchColumn();
} catch (Throwable $e) {
    error_log('User duplicate check failed: ' . $e->getMessage());
    admin_api_fail(500, 'Unable to verify availability right now.');
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode([
    'success' => true,
    'available' => !$isDuplicate,
    'message' => $isDuplicate ? $duplicateMessage : '',
]);
