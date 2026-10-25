<?php declare(strict_types=1);

/**
 * Possible duplicate detection for the Add User form.
 *
 * Read-only. Checks the submitted email, username and contact number against
 * every account source and returns matches so the administrator can review
 * before creating the account. Nothing is merged or changed here.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';

use App\Core\Database;

rmg_actor();

header('Cache-Control: no-store');

$input = rmg_input();

$role = strtolower(trim((string)($input['role'] ?? '')));
$email = strtolower(trim((string)($input['email'] ?? '')));
$username = strtolower(trim((string)($input['username'] ?? '')));
$contact = preg_replace('/\D+/', '', (string)($input['contact'] ?? ''));

if ($email === '' && $username === '' && strlen($contact) < 7) {
    echo json_encode(['success' => true, 'duplicates' => []]);
    exit;
}

try {
    $db = Database::get();
} catch (Throwable $e) {
    rmg_fail(500, 'Duplicate check is unavailable. Please try again.');
}

/**
 * Digits-only comparison using the last 10 digits.
 */
function rmg_digits_match(string $a, string $b): bool
{
    $a = substr($a, -10);
    $b = substr($b, -10);

    return $a !== '' && $b !== '' && $a === $b;
}

function rmg_table_exists(PDO $db, string $table): bool
{
    try {
        $stmt = $db->query('SHOW TABLES LIKE ' . $db->quote($table));

        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

$duplicates = [];

/**
 * @param array<string,array> $matches keyed by "role:ref"
 */
function rmg_add_duplicate(array &$matches, string $role, string $ref, string $name, string $userId, string $matched): void
{
    $key = $role . ':' . $ref;
    if (!isset($matches[$key])) {
        $matches[$key] = [
            'role' => $role,
            'role_label' => ucwords(str_replace('_', ' ', $role)),
            'name' => $name,
            'user_id' => $userId,
            'matched' => [],
        ];
    }
    if (!in_array($matched, $matches[$key]['matched'], true)) {
        $matches[$key]['matched'][] = $matched;
    }
}

/* ------------------------------- users ------------------------------- */

if (rmg_table_exists($db, 'users')) {
    try {
        $where = [];
        $params = [];
        if ($email !== '') {
            $where[] = 'LOWER(CONVERT(COALESCE(email, \'\') USING utf8mb4) COLLATE utf8mb4_general_ci) = ?';
            $params[] = $email;
        }
        if ($username !== '') {
            $where[] = 'LOWER(CONVERT(COALESCE(username, \'\') USING utf8mb4) COLLATE utf8mb4_general_ci) = ?';
            $params[] = $username;
        }
        if (strlen($contact) >= 7) {
            $where[] = '(CONVERT(COALESCE(contact_no, \'\') USING utf8mb4) COLLATE utf8mb4_general_ci LIKE ?'
                . ' OR CONVERT(COALESCE(phone, \'\') USING utf8mb4) COLLATE utf8mb4_general_ci LIKE ?)';
            $tail = '%' . substr($contact, -7) . '%';
            $params[] = $tail;
            $params[] = $tail;
        }

        if ($where) {
            $stmt = $db->prepare(
                "SELECT id, COALESCE(NULLIF(full_name,''), NULLIF(username,''), CONCAT('User #', id)) AS name,"
                . " COALESCE(employee_id, CONCAT('USR-', id)) AS user_id, role, email, username,"
                . " COALESCE(contact_no, phone, '') AS contact"
                . ' FROM users WHERE ' . implode(' OR ', $where) . ' LIMIT 25'
            );
            $stmt->execute($params);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $ref = (string)($row['id'] ?? '');
                if ($email !== '' && strtolower((string)($row['email'] ?? '')) === $email) {
                    rmg_add_duplicate($duplicates, (string)($row['role'] ?? 'user'), $ref, (string)$row['name'], (string)$row['user_id'], 'email');
                }
                if ($username !== '' && strtolower((string)($row['username'] ?? '')) === $username) {
                    rmg_add_duplicate($duplicates, (string)($row['role'] ?? 'user'), $ref, (string)$row['name'], (string)$row['user_id'], 'username');
                }
                if (strlen($contact) >= 7 && rmg_digits_match($contact, (string)($row['contact'] ?? ''))) {
                    rmg_add_duplicate($duplicates, (string)($row['role'] ?? 'user'), $ref, (string)$row['name'], (string)$row['user_id'], 'contact');
                }
            }
        }
    } catch (Throwable $e) {
        // A missing column must not break the check.
    }
}

/* ------------------------------ drivers ------------------------------ */

if (rmg_table_exists($db, 'drivers')) {
    try {
        $where = [];
        $params = [];
        if ($email !== '') {
            $where[] = 'LOWER(CONVERT(COALESCE(email, \'\') USING utf8mb4) COLLATE utf8mb4_general_ci) = ?';
            $params[] = $email;
        }
        if (strlen($contact) >= 7) {
            $where[] = 'CONVERT(COALESCE(contact_number, \'\') USING utf8mb4) COLLATE utf8mb4_general_ci LIKE ?';
            $params[] = '%' . substr($contact, -7) . '%';
        }

        if ($where) {
            $stmt = $db->prepare(
                "SELECT id, COALESCE(NULLIF(full_name,''), CONCAT('Driver #', id)) AS name,"
                . " COALESCE(driver_id, CONCAT('DRV-', id)) AS user_id, email,"
                . " COALESCE(contact_number, '') AS contact"
                . ' FROM drivers WHERE ' . implode(' OR ', $where) . ' LIMIT 25'
            );
            $stmt->execute($params);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $ref = (string)($row['id'] ?? '');
                if ($email !== '' && strtolower((string)($row['email'] ?? '')) === $email) {
                    rmg_add_duplicate($duplicates, 'driver', $ref, (string)$row['name'], (string)$row['user_id'], 'email');
                }
                if (strlen($contact) >= 7 && rmg_digits_match($contact, (string)($row['contact'] ?? ''))) {
                    rmg_add_duplicate($duplicates, 'driver', $ref, (string)$row['name'], (string)$row['user_id'], 'contact');
                }
            }
        }
    } catch (Throwable $e) {
    }
}

/* ----------------------------- inspectors ----------------------------- */

if (rmg_table_exists($db, 'inspectors')) {
    try {
        $where = [];
        $params = [];
        if ($email !== '') {
            $where[] = 'LOWER(CONVERT(COALESCE(i.email, \'\') USING utf8mb4) COLLATE utf8mb4_general_ci) = ?';
            $params[] = $email;
        }
        if (strlen($contact) >= 7) {
            $where[] = 'CONVERT(COALESCE(i.contact_number, \'\') USING utf8mb4) COLLATE utf8mb4_general_ci LIKE ?';
            $params[] = '%' . substr($contact, -7) . '%';
        }

        if ($where) {
            $stmt = $db->prepare(
                "SELECT i.inspector_pk AS ref, COALESCE(NULLIF(i.inspector_name,''), CONCAT('Inspector #', i.inspector_pk)) AS name,"
                . " COALESCE(ia.inspector_id, CONCAT('INS-', i.inspector_pk)) AS user_id, i.email,"
                . " COALESCE(i.contact_number, '') AS contact, COALESCE(ia.role, 'inspector') AS role"
                . ' FROM inspectors i LEFT JOIN inspector_accounts ia ON ia.account_id = i.account_id'
                . ' WHERE ' . implode(' OR ', $where) . ' LIMIT 25'
            );
            $stmt->execute($params);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $ref = (string)($row['ref'] ?? '');
                $roleKey = strtolower((string)($row['role'] ?? 'inspector')) === 'dispatcher' ? 'dispatcher' : 'inspector';
                if ($email !== '' && strtolower((string)($row['email'] ?? '')) === $email) {
                    rmg_add_duplicate($duplicates, $roleKey, $ref, (string)$row['name'], (string)$row['user_id'], 'email');
                }
                if (strlen($contact) >= 7 && rmg_digits_match($contact, (string)($row['contact'] ?? ''))) {
                    rmg_add_duplicate($duplicates, $roleKey, $ref, (string)$row['name'], (string)$row['user_id'], 'contact');
                }
            }
        }
    } catch (Throwable $e) {
    }
}

echo json_encode([
    'success' => true,
    'duplicates' => array_slice(array_values($duplicates), 0, 10),
]);
