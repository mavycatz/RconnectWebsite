<?php declare(strict_types=1);

/**
 * Recent audit entries for one account, shown in the Role Management detail
 * panel so it is visible who changed an account and why.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';

rmg_actor();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$role = strtolower(trim((string)($_GET['role'] ?? '')));
$ref = trim((string)($_GET['ref'] ?? ''));

$modules = [
    'driver' => 'Drivers',
    'inspector' => 'Inspectors',
    'dispatcher' => 'Inspectors',
    'finance_officer' => 'Finance',
    'finance_manager' => 'Finance',
    'admin' => 'Admin',
    'super_admin' => 'Admin',
];

if (!isset($modules[$role]) || $ref === '') {
    rmg_fail(400, 'A valid account reference is required.');
}

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
    $db = \App\Core\Database::get();
    $stmt = $db->prepare("
        SELECT action, description, username, user_role, status, created_at
        FROM audit_trail
        WHERE module = ? AND record_id IN (?, ?)
        ORDER BY id DESC
        LIMIT 8
    ");
    require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/SecurityCodeService.php';
    $source = \Admin\Services\RoleManagement\SecurityCodeService::targetLabel($role);
    if ($source === 'super_admin') { $source = 'admin'; }
    $stmt->execute([$modules[$role], $ref, $source . ':' . $ref]);

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    error_log('RoleManagement history failed: ' . $e->getMessage());
    rmg_fail(500, 'Could not load the change history.');
}
