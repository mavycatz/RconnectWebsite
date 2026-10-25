<?php declare(strict_types=1);

/**
 * Dropdown option data for the Bus Management Add/Edit forms.
 *
 * Bus-specific lists come from existing bus records merged with the
 * configurable enumerations in admin/config/bus_options.php. Routes and
 * terminals are served by the existing RoleManagement Options endpoint;
 * drivers reuse ScheduleApi's active-driver list. Inspectors are exposed
 * here because no existing endpoint returns active inspectors with their
 * primary key.
 *
 *   GET ?type=lists          enum configuration (types, fuel, transmission, ...)
 *   GET ?type=types          bus types (records + config)
 *   GET ?type=manufacturers  manufacturers (records + config)
 *   GET ?type=models         models, optionally ?manufacturer=
 *   GET ?type=years          year models (records + generated range)
 *   GET ?type=inspectors     active inspectors with their primary key
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';

use App\Core\Database;

rmg_actor();

header('Cache-Control: no-store');

$type = strtolower(trim((string)($_GET['type'] ?? '')));

function bus_options_config(): array
{
    static $config = null;

    if ($config === null) {
        $loaded = @require $_SERVER['DOCUMENT_ROOT'] . '/admin/config/bus_options.php';
        $config = is_array($loaded) ? $loaded : [];
    }

    return $config;
}

function bus_options_driver(PDO $db): string
{
    try {
        return (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable $e) {
        return 'mysql';
    }
}

function bus_options_table_exists(PDO $db, string $table): bool
{
    try {
        if (bus_options_driver($db) === 'sqlite') {
            $stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
            $stmt->execute([$table]);

            return (bool)$stmt->fetchColumn();
        }

        $stmt = $db->query('SHOW TABLES LIKE ' . $db->quote($table));

        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function bus_options_has_column(PDO $db, string $table, string $column): bool
{
    try {
        if (bus_options_driver($db) === 'sqlite') {
            $stmt = $db->query('PRAGMA table_info(`' . $table . '`)');
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (strtolower((string)($row['name'] ?? '')) === strtolower($column)) {
                    return true;
                }
            }

            return false;
        }

        $stmt = $db->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (strtolower((string)($row['Field'] ?? '')) === strtolower($column)) {
                return true;
            }
        }

        return false;
    } catch (Throwable $e) {
        return false;
    }
}

function bus_options_rows(PDO $db, string $sql, array $params = []): array
{
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function bus_options_safe_col(string $expr): string
{
    return 'CONVERT(' . $expr . ' USING utf8mb4) COLLATE utf8mb4_general_ci';
}

/**
 * Distinct existing values of an additive bus column (schema-tolerant).
 */
function bus_options_distinct(PDO $db, string $column): array
{
    if (!bus_options_table_exists($db, 'buses') || !bus_options_has_column($db, 'buses', $column)) {
        return [];
    }

    $rows = bus_options_rows(
        $db,
        'SELECT DISTINCT `' . $column . '` AS value FROM buses'
        . ' WHERE `' . $column . '` IS NOT NULL AND LENGTH(`' . $column . '`) > 0'
        . ' ORDER BY `' . $column . '` ASC'
    );

    return array_values(array_filter(array_map(
        static fn ($row) => trim((string)($row['value'] ?? '')),
        $rows
    ), static fn ($value) => $value !== ''));
}

/**
 * Merge existing-record values with the configured list, keeping order:
 * records first (real data), then configured values not already present.
 */
function bus_options_merge(array $existing, array $configured): array
{
    $merged = [];
    foreach (array_merge($existing, $configured) as $value) {
        $value = trim((string)$value);
        if ($value === '' || in_array($value, $merged, true)) {
            continue;
        }
        $merged[] = $value;
    }

    return $merged;
}

function bus_options_options(array $values): array
{
    return array_map(static fn ($value) => ['id' => $value, 'label' => $value], $values);
}

try {
    $db = Database::get();
} catch (Throwable $e) {
    rmg_fail(500, 'Bus options are unavailable. Please try again.');
}

$config = bus_options_config();

switch ($type) {
    case 'lists':
        echo json_encode([
            'success' => true,
            'type' => 'lists',
            'lists' => [
                'bus_types' => $config['bus_types'] ?? [],
                'bus_type_capacities' => $config['bus_type_capacities'] ?? [],
                'manufacturers' => $config['manufacturers'] ?? [],
                'fuel_types' => $config['fuel_types'] ?? [],
                'transmissions' => $config['transmissions'] ?? [],
                'air_conditioning' => $config['air_conditioning'] ?? [],
                'ownership' => $config['ownership'] ?? [],
                'operational_statuses' => $config['operational_statuses'] ?? [],
            ],
        ]);
        exit;

    case 'types':
        /* The fleet has exactly two bus types; the configured list is the only
           source so legacy record values cannot reintroduce retired types. */
        $values = array_values(array_filter(array_map(
            static fn ($value) => trim((string)$value),
            $config['bus_types'] ?? []
        ), static fn ($value) => $value !== ''));
        echo json_encode(['success' => true, 'type' => 'types', 'options' => bus_options_options($values)]);
        exit;

    case 'manufacturers':
        $values = bus_options_merge(bus_options_distinct($db, 'manufacturer'), $config['manufacturers'] ?? []);
        echo json_encode(['success' => true, 'type' => 'manufacturers', 'options' => bus_options_options($values)]);
        exit;

    case 'models':
        $manufacturer = trim((string)($_GET['manufacturer'] ?? ''));
        $values = bus_options_distinct($db, 'model');
        if ($manufacturer !== '' && bus_options_table_exists($db, 'buses') && bus_options_has_column($db, 'buses', 'manufacturer')) {
            $rows = bus_options_rows(
                $db,
                'SELECT DISTINCT `model` AS value FROM buses'
                . ' WHERE `model` IS NOT NULL AND LENGTH(`model`) > 0'
                . ' AND ' . bus_options_safe_col('`manufacturer`') . ' = ?'
                . ' ORDER BY `model` ASC',
                [$manufacturer]
            );
            $filtered = array_values(array_filter(array_map(
                static fn ($row) => trim((string)($row['value'] ?? '')),
                $rows
            ), static fn ($value) => $value !== ''));
            if ($filtered) {
                $values = $filtered;
            }
        }
        echo json_encode(['success' => true, 'type' => 'models', 'options' => bus_options_options(bus_options_merge($values, []))]);
        exit;

    case 'years':
        $existingNumeric = [];
        foreach (bus_options_distinct($db, 'year_model') as $value) {
            if (preg_match('/^\d{4}$/', $value)) {
                $existingNumeric[] = $value;
            }
        }
        $range = (int)($config['year_range'] ?? 40);
        $range = max(1, min(80, $range));
        $current = (int)date('Y');
        $years = $existingNumeric;
        for ($year = $current; $year >= $current - $range; $year--) {
            $years[] = (string)$year;
        }
        $years = array_values(array_unique(array_filter(
            $years,
            static fn ($value) => preg_match('/^\d{4}$/', (string)$value) === 1
        )));
        rsort($years);
        echo json_encode(['success' => true, 'type' => 'years', 'options' => bus_options_options($years)]);
        exit;

    case 'inspectors':
        $values = [];
        if (bus_options_table_exists($db, 'inspectors') && bus_options_table_exists($db, 'inspector_accounts')) {
            $where = '';
            if (bus_options_has_column($db, 'inspector_accounts', 'status')) {
                $where = ' AND ' . bus_options_safe_col('ia.status') . " IN ('active', 'approved')";
            }
            $rows = bus_options_rows(
                $db,
                'SELECT i.inspector_pk AS id,'
                . ' COALESCE(NULLIF(i.inspector_name, \'\'), CONCAT(\'Inspector #\', i.inspector_pk)) AS label'
                . ' FROM inspectors i'
                . ' INNER JOIN inspector_accounts ia ON ia.account_id = i.account_id'
                . ' WHERE 1 = 1' . $where
                . ' ORDER BY i.inspector_name ASC'
            );
            foreach ($rows as $row) {
                $id = trim((string)($row['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $values[] = ['id' => $id, 'label' => trim((string)($row['label'] ?? $id))];
            }
        }
        echo json_encode(['success' => true, 'type' => 'inspectors', 'options' => $values]);
        exit;

    default:
        rmg_fail(400, 'Unknown option type.');
}
