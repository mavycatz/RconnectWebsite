<?php declare(strict_types=1);

/**
 * Dropdown option data for the Role Management Add/Edit forms.
 *
 * Operational data is always read from the database — buses, routes,
 * terminals, supervisors and departments are never hardcoded in the UI.
 * Static configuration lists come from admin/config/role_options.php.
 *
 *   GET ?type=lists       enum + position config
 *   GET ?type=buses       registered buses (non-archived)
 *   GET ?type=routes      distinct registered route names
 *   GET ?type=terminals   active terminals
 *   GET ?type=supervisors authorized users who can supervise
 *   GET ?type=departments merged departments from accounts + config
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/PersonnelFields.php';

use Admin\Services\RoleManagement\PersonnelFields;
use App\Core\Database;

rmg_actor();

header('Cache-Control: no-store');

$type = strtolower(trim((string)($_GET['type'] ?? '')));

try {
    $db = Database::get();
} catch (Throwable $e) {
    rmg_fail(500, 'Options are unavailable. Please try again.');
}

/**
 * Run a query and return rows, or [] when the table/column is missing.
 */
function rmg_options_rows(PDO $db, string $sql, array $params = []): array
{
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function rmg_options_text(array $value): string
{
    return trim((string)($value['label'] ?? ''));
}

switch ($type) {
    case 'lists':
        $config = PersonnelFields::options();

        echo json_encode([
            'success' => true,
            'type' => 'lists',
            'lists' => [
                'suffixes' => $config['suffixes'] ?? [],
                'sex' => $config['sex'] ?? [],
                'employment_types' => $config['employment_types'] ?? [],
                'employment_statuses' => $config['employment_statuses'] ?? [],
                'availability' => $config['availability'] ?? [],
                'work_shifts' => $config['work_shifts'] ?? [],
                'license_classifications' => $config['license_classifications'] ?? [],
                'finance_positions' => $config['finance_positions'] ?? [],
                'admin_positions' => $config['admin_positions'] ?? [],
            ],
        ]);
        exit;

    case 'buses':
        $rows = rmg_options_rows($db,
            "SELECT bus_id, plate_number FROM buses"
            . " WHERE COALESCE(CONVERT(status USING utf8mb4) COLLATE utf8mb4_general_ci, '') <> 'Archived'"
            . " ORDER BY bus_id ASC"
        );
        $options = [];
        foreach ($rows as $row) {
            $id = trim((string)($row['bus_id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $plate = trim((string)($row['plate_number'] ?? ''));
            $options[] = ['id' => $id, 'label' => $plate !== '' ? $id . ' — ' . $plate : $id];
        }
        echo json_encode(['success' => true, 'type' => 'buses', 'options' => $options]);
        exit;

    case 'routes':
        $names = [];
        foreach ([
            "SELECT DISTINCT route_name FROM fare_pricing WHERE route_name IS NOT NULL AND LENGTH(route_name) > 0",
            "SELECT DISTINCT route_name FROM route_stops WHERE route_name IS NOT NULL AND LENGTH(route_name) > 0",
        ] as $sql) {
            foreach (rmg_options_rows($db, $sql) as $row) {
                $name = trim((string)($row['route_name'] ?? ''));
                if ($name !== '') {
                    $names[$name] = true;
                }
            }
        }
        $names = array_keys($names);
        natcasesort($names);
        $options = [];
        foreach ($names as $name) {
            $options[] = ['id' => $name, 'label' => $name];
        }
        echo json_encode(['success' => true, 'type' => 'routes', 'options' => $options]);
        exit;

    case 'terminals':
        $rows = rmg_options_rows($db,
            "SELECT id, terminal_name, terminal_code FROM terminals"
            . " WHERE CONVERT(status USING utf8mb4) COLLATE utf8mb4_general_ci = 'Active'"
            . " ORDER BY terminal_name ASC"
        );
        $options = [];
        foreach ($rows as $row) {
            $id = trim((string)($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $name = trim((string)($row['terminal_name'] ?? ''));
            $code = trim((string)($row['terminal_code'] ?? ''));
            $options[] = ['id' => $id, 'label' => $name !== '' ? $name . ($code !== '' ? ' (' . $code . ')' : '') : $id];
        }
        echo json_encode(['success' => true, 'type' => 'terminals', 'options' => $options]);
        exit;

    case 'supervisors':
        $rows = rmg_options_rows($db,
            "SELECT id, full_name, username, role FROM users"
            . " WHERE role IN ('admin', 'super_admin', 'finance_manager')"
            . " AND (status IS NULL OR LENGTH(status) = 0 OR LOWER(CONVERT(status USING utf8mb4) COLLATE utf8mb4_general_ci) IN ('active', 'approved'))"
            . " ORDER BY full_name ASC"
        );
        $options = [];
        foreach ($rows as $row) {
            $id = trim((string)($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $name = trim((string)($row['full_name'] ?? ''));
            if ($name === '') {
                $name = trim((string)($row['username'] ?? ''));
            }
            $role = str_replace('_', ' ', (string)($row['role'] ?? ''));
            $options[] = ['id' => $id, 'label' => $name . ($role !== '' ? ' — ' . $role : '')];
        }
        echo json_encode(['success' => true, 'type' => 'supervisors', 'options' => $options]);
        exit;

    case 'departments':
        $config = PersonnelFields::options();
        $departments = [];

        foreach ($config['departments'] ?? [] as $name) {
            $name = trim((string)$name);
            if ($name !== '') {
                $departments[$name] = true;
            }
        }
        foreach ([
            "SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND LENGTH(department) > 0",
            "SELECT DISTINCT department FROM inspectors WHERE department IS NOT NULL AND LENGTH(department) > 0",
        ] as $sql) {
            foreach (rmg_options_rows($db, $sql) as $row) {
                $name = trim((string)($row['department'] ?? ''));
                if ($name !== '') {
                    $departments[$name] = true;
                }
            }
        }

        $names = array_keys($departments);
        natcasesort($names);
        $options = [];
        foreach ($names as $name) {
            $options[] = ['id' => $name, 'label' => $name];
        }
        echo json_encode(['success' => true, 'type' => 'departments', 'options' => $options]);
        exit;

    default:
        rmg_fail(400, 'Unknown option type.');
}
