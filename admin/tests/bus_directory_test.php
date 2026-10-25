<?php declare(strict_types=1);

/**
 * BusDirectoryModel checks: the one lifecycle rule (stored status only,
 * operational status kept separate), expiry states, and the generated SQL for
 * every filter combination (safeCol on all comparisons) against a stub PDO.
 * No database is required.
 */

if (!isset($_SERVER['DOCUMENT_ROOT']) || $_SERVER['DOCUMENT_ROOT'] === '') {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
}

require_once __DIR__ . '/../models/Bus/BusDirectoryModel.php';

use Admin\Models\Bus\BusDirectoryModel;

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

/* ---------------------------- lifecycle status ---------------------------- */

check(BusDirectoryModel::classify(['raw_status' => 'Archived', 'operational_status' => '']) === 'archived', 'Archived status classifies as archived');
check(BusDirectoryModel::classify(['raw_status' => 'Archived', 'operational_status' => 'On Trip']) === 'archived', 'Archived is never operationally active');
check(BusDirectoryModel::classify(['raw_status' => 'Active', 'operational_status' => '']) === 'active', 'Active with no operational state classifies as active');
check(BusDirectoryModel::classify(['raw_status' => 'Active', 'operational_status' => null]) === 'active', 'NULL operational state classifies as active');
check(BusDirectoryModel::classify(['raw_status' => 'Active', 'operational_status' => 'Under Maintenance']) === 'active', 'Under Maintenance never downgrades Active');
check(BusDirectoryModel::classify(['raw_status' => 'Active', 'operational_status' => 'Out of Service']) === 'active', 'Out of Service never downgrades Active');
check(BusDirectoryModel::classify(['raw_status' => 'Active', 'operational_status' => 'On Trip']) === 'active', 'On Trip never downgrades Active');
check(BusDirectoryModel::classify(['raw_status' => 'Active', 'operational_status' => 'Available']) === 'active', 'Available never becomes its own status');
check(BusDirectoryModel::classify(['raw_status' => 'Active', 'operational_status' => 'Not Available']) === 'active', 'Unknown operational values never downgrade Active');
check(BusDirectoryModel::classify(['raw_status' => 'Inactive', 'operational_status' => 'Available']) === 'inactive', 'Inactive lifecycle classifies');
check(BusDirectoryModel::classify(['raw_status' => 'Maintenance', 'operational_status' => '']) === 'maintenance', 'Maintenance lifecycle value classifies');
check(BusDirectoryModel::classify(['raw_status' => 'active', 'operational_status' => 'Weird']) === 'active', 'Lifecycle comparison is case-insensitive');
check(BusDirectoryModel::classify(['raw_status' => 'Pending QR', 'operational_status' => 'On Trip']) === 'pending_qr', 'Legacy stored values keep their own class');
check(BusDirectoryModel::classify(['raw_status' => '', 'operational_status' => '']) === 'inactive', 'Missing status falls back to inactive');

check(BusDirectoryModel::labelFor('active', 'Active') === 'Active', 'Configured labels are used for known classes');
check(BusDirectoryModel::labelFor('maintenance', 'Maintenance') === 'Maintenance', 'Maintenance label is readable');
check(BusDirectoryModel::labelFor('pending_qr', 'Pending QR') === 'Pending QR', 'Legacy labels display the stored value');
check(BusDirectoryModel::labelFor('inactive', '') === 'Inactive', 'Empty stored values label as Inactive');

check(BusDirectoryModel::operationalClass(['operational_status' => 'Under Maintenance']) === 'under_maintenance', 'Operational state is normalized separately');
check(BusDirectoryModel::operationalClass(['operational_status' => 'Weird']) === 'other', 'Unknown operational values keep an other class');
check(BusDirectoryModel::operationalClass(['operational_status' => '']) === '', 'Unset operational state has no class');

$options = BusDirectoryModel::statusOptions();
check(array_keys($options) === ['active', 'inactive', 'maintenance', 'archived'], 'Filter options expose every lifecycle class once');
check($options['maintenance'] === 'Maintenance', 'Filter labels are readable');

/* ------------------------------ expiry states ------------------------------ */

check(BusDirectoryModel::expiryState('') === '', 'Missing dates have no state');
check(BusDirectoryModel::expiryState('not-a-date') === '', 'Invalid dates have no state');
check(BusDirectoryModel::expiryState(date('Y-m-d', strtotime('-1 day'))) === 'expired', 'Past dates are expired');
check(BusDirectoryModel::expiryState(date('Y-m-d', strtotime('+10 days'))) === 'expiring', 'Near dates are expiring');
check(BusDirectoryModel::expiryState(date('Y-m-d', strtotime('+120 days'))) === 'ok', 'Far dates are ok');

/* -------------------------------- stub PDO -------------------------------- */

class BusDirStubStatement
{
    private $single;
    private array $rows;
    private bool $column;

    public function __construct($single, array $rows = [], bool $column = false)
    {
        $this->single = $single;
        $this->rows = $rows;
        $this->column = $column;
    }

    public function execute($params = null)
    {
        return true;
    }

    public function fetchColumn()
    {
        return $this->single;
    }

    public function fetch($mode = null)
    {
        return $this->rows ? $this->rows[0] : false;
    }

    public function fetchAll($mode = null)
    {
        return $this->rows;
    }
}

class BusDirStubPdo extends PDO
{
    private array $tables;
    public array $statements = [];

    public function __construct(array $tables)
    {
        $this->tables = $tables;
    }

    #[\ReturnTypeWillChange]
    public function query($statement, ...$args)
    {
        $sql = trim((string)$statement);
        if (stripos($sql, 'SHOW TABLES LIKE') === 0) {
            $table = trim(trim(substr($sql, strlen('SHOW TABLES LIKE'))), "'\"");
            return new BusDirStubStatement(isset($this->tables[$table]) ? $table : false);
        }
        if (stripos($sql, 'SHOW COLUMNS FROM') === 0) {
            $table = trim(trim(substr($sql, strlen('SHOW COLUMNS FROM'))), "` \t");
            $fields = array_map(fn ($c) => ['Field' => $c], array_keys($this->tables[$table] ?? []));
            return new BusDirStubStatement(false, $fields);
        }

        return new BusDirStubStatement(false);
    }

    #[\ReturnTypeWillChange]
    public function prepare($statement, $options = null)
    {
        $sql = (string)$statement;
        $this->statements[] = $sql;

        if (stripos($sql, 'SUM(CASE') !== false) {
            return new BusDirStubStatement(false, [[
                'active' => 5, 'inactive' => 1, 'maintenance' => 1,
                'archived' => 2, 'other' => 1,
            ]]);
        }

        return new BusDirStubStatement(false, [[
            'ref' => 1, 'bus_id' => 'BUS-001', 'plate' => 'ABC 123', 'unit_number' => '1201',
            'bus_type' => 'Coach', 'raw_status' => 'Active', 'operational_status' => 'On Trip',
            'from_terminal_id' => 3, 'to_terminal_id' => 7, 'accessible_features' => 'Priority Seats',
            'registration_month' => 'February',
            'registration_expiry' => date('Y-m-d', strtotime('+10 days')),
            'insurance_expiry' => date('Y-m-d', strtotime('-2 days')),
            'next_inspection_date' => '',
        ], [
            'ref' => 2, 'bus_id' => 'BUS-002', 'plate' => 'XYZ 789', 'unit_number' => '',
            'bus_type' => 'Mini Bus', 'raw_status' => 'Archived', 'operational_status' => '',
            'from_terminal_id' => null, 'to_terminal_id' => null, 'accessible_features' => '',
            'registration_month' => '',
            'registration_expiry' => '', 'insurance_expiry' => '', 'next_inspection_date' => '',
        ]]);
    }

    #[\ReturnTypeWillChange]
    public function quote($string, $type = PDO::PARAM_STR)
    {
        return "'" . $string . "'";
    }
}

$stub = new BusDirStubPdo([
    'buses' => array_fill_keys([
        'id', 'bus_id', 'plate_number', 'unit_number', 'bus_type', 'seats', 'standing_capacity',
        'manufacturer', 'model', 'year_model', 'engine_number', 'chassis_number', 'fuel_type',
        'transmission_type', 'air_conditioning', 'vehicle_ownership', 'route_name', 'terminal',
        'schedule', 'driver', 'assigned_inspector_id', 'operational_status', 'registration_month',
        'registration_expiry', 'accessible_features',
        'insurance_expiry', 'inspection_date', 'next_inspection_date', 'status', 'qr_status',
        'qr_code', 'uploaded_image', 'created_at', 'updated_at', 'created_by', 'from_terminal_id',
        'to_terminal_id',
    ], true),
    'drivers' => array_fill_keys(['id', 'driver_id', 'full_name'], true),
    'inspectors' => array_fill_keys(['inspector_pk', 'inspector_name'], true),
]);

$model = new BusDirectoryModel($stub);

/* ------------------------------ generated SQL ------------------------------ */

[$sql, $params] = $model->buildQuery([]);
check(strpos($sql, 'FROM buses b') !== false, 'Base query reads the buses table');
check(strpos($sql, 'LIMIT 5000') !== false, 'Base query is capped');
check(strpos($sql, 'COLLATE utf8mb4_general_ci') !== false, 'Collation is explicit somewhere');
check(strpos($sql, 'b.registration_month AS registration_month') !== false, 'Registration month is selected when present');
check(substr_count($sql, '?') === count($params), 'Placeholders match params (no filters)');

[$sql, $params] = $model->buildQuery(['search' => 'ABC']);
check(strpos($sql, 'LIKE ?') !== false, 'Search uses LIKE');
check(substr_count($sql, '?') === count($params), 'Placeholders match params (search)');
check(substr_count($sql, 'COLLATE utf8mb4_general_ci') >= 8, 'Every searchable column is collation-wrapped');

foreach ([
    ['active', 'Active'],
    ['inactive', 'Inactive'],
    ['maintenance', 'Maintenance'],
    ['archived', 'Archived'],
] as $lifecycle) {
    [$sql, $params] = $model->buildQuery(['status' => $lifecycle[0]]);
    check(
        strpos($sql, "CONVERT(b.status USING utf8mb4) COLLATE utf8mb4_general_ci = '" . $lifecycle[1] . "'") !== false,
        'Status filter embeds the ' . $lifecycle[0] . ' lifecycle condition'
    );
    check(strpos($sql, 'LIKE') === false && strpos($sql, 'operational_status =') === false, 'Status filter never reads operational values for ' . $lifecycle[0]);
}

[$sql, $params] = $model->buildQuery(['status' => 'maintenance']);
check(strpos($sql, "'Under Maintenance'") === false, 'Operational label never appears as a status filter');

/* Row class membership matches the SQL bucket for the same stored value. */
$membership = [
    ['row' => ['raw_status' => 'Active', 'operational_status' => 'Not Available'], 'class' => 'active', 'stored' => 'Active'],
    ['row' => ['raw_status' => 'Inactive', 'operational_status' => ''], 'class' => 'inactive', 'stored' => 'Inactive'],
    ['row' => ['raw_status' => 'Maintenance', 'operational_status' => 'On Trip'], 'class' => 'maintenance', 'stored' => 'Maintenance'],
    ['row' => ['raw_status' => 'Archived', 'operational_status' => ''], 'class' => 'archived', 'stored' => 'Archived'],
];
foreach ($membership as $sample) {
    check(BusDirectoryModel::classify($sample['row']) === $sample['class'], 'PHP class matches the sample row for ' . $sample['stored']);
    [$sql, $params] = $model->buildQuery(['status' => $sample['class']]);
    check(strpos($sql, "= '" . $sample['stored'] . "'") !== false, 'SQL bucket matches the PHP class for ' . $sample['stored']);
}

[$sql, $params] = $model->buildQuery(['bus_type' => 'Coach', 'route' => 'Cebu - Danao', 'driver' => 'Juan']);
check(substr_count($sql, '?') === count($params), 'Placeholders match params (combined filters)');
check(count($params) === 4, 'Combined filters bind one value each');
check(strpos($sql, 'CONVERT(b.bus_type') !== false && strpos($sql, 'CONVERT(b.route_name') !== false, 'Type and route filters are collation-wrapped');

[$sql, $params] = $model->buildQuery(['registered_from' => '2026-01-01', 'registered_to' => '2026-12-31', 'sort' => 'newest']);
check(substr_count($sql, '?') === count($params) && count($params) === 2, 'Date range binds both ends');
check(strpos($sql, 'ORDER BY') !== false, 'Sorting is applied');
check(strpos($sql, 'CASE WHEN b.created_at IS NULL') !== false, 'Newest-first sorting handles missing timestamps');

[$sql, $params] = $model->buildQuery(['sort' => 'plate_desc']);
check(strpos($sql, 'ORDER BY CONVERT(b.plate_number') !== false && strpos($sql, 'DESC') !== false, 'Plate sorting is collation-wrapped');

/* --------------------- request-shape filter validation --------------------- */

check(!isset(BusDirectoryModel::statusOptions()['available']), 'Operational values are not offered as status filters');
check(!isset(BusDirectoryModel::statusOptions()['on_trip']), 'Operational values are not offered as status filters (2)');
[$sql, $params] = $model->buildQuery(['status' => 'available']);
check(strpos($sql, "= 'Available'") === false, 'Operational filter values never reach the SQL conditions');
check(strpos($sql, 'NOT (') !== false, 'Unknown status filters fall back to the default archived-excluding listing');
$result = $model->query(['status' => 'available']);
check($result['rows'][0]['classification'] === 'active', 'Unknown status filters do not break row loading');

/* ---------------------- inspector name schema tolerance ---------------------- */

[$sql, $params] = $model->buildQuery([]);
check(strpos($sql, 'LEFT JOIN inspectors insp ON insp.inspector_pk = b.assigned_inspector_id') !== false, 'Inspector join keys on the inspector primary key');
check(strpos($sql, 'CONVERT(insp.inspector_name USING utf8mb4) COLLATE utf8mb4_general_ci AS inspector_name') !== false, 'Canonical inspector_name is selected when present');
check(strpos($sql, 'insp.full_name') === false, 'Inspector full_name is never selected when inspector_name exists');

$fullNameStub = new BusDirStubPdo([
    'buses' => array_fill_keys(['id', 'assigned_inspector_id'], true),
    'inspectors' => array_fill_keys(['inspector_pk', 'full_name'], true),
]);
[$sql, $params] = (new BusDirectoryModel($fullNameStub))->buildQuery([]);
check(strpos($sql, 'CONVERT(insp.full_name USING utf8mb4) COLLATE utf8mb4_general_ci AS inspector_name') !== false, 'Inspector full_name is used when the canonical column is absent');

$splitNameStub = new BusDirStubPdo([
    'buses' => array_fill_keys(['id', 'assigned_inspector_id'], true),
    'inspectors' => array_fill_keys(['inspector_pk', 'first_name', 'last_name'], true),
]);
[$sql, $params] = (new BusDirectoryModel($splitNameStub))->buildQuery([]);
check(strpos($sql, 'CONCAT_WS(" ", insp.first_name, insp.last_name) AS inspector_name') !== false, 'Split inspector names are composed when needed');

$noNameStub = new BusDirStubPdo([
    'buses' => array_fill_keys(['id', 'assigned_inspector_id'], true),
    'inspectors' => array_fill_keys(['inspector_pk'], true),
]);
[$sql, $params] = (new BusDirectoryModel($noNameStub))->buildQuery([]);
check(strpos($sql, 'LEFT JOIN inspectors') === false && strpos($sql, 'NULL AS inspector_name') !== false, 'No inspector name column means no join and a null name');

$noInspectorsStub = new BusDirStubPdo([
    'buses' => array_fill_keys(['id', 'assigned_inspector_id'], true),
]);
[$sql, $params] = (new BusDirectoryModel($noInspectorsStub))->buildQuery([]);
check(strpos($sql, 'LEFT JOIN inspectors') === false, 'Missing inspectors table skips the join');

/* ------------------------------ full query() ------------------------------ */

$result = $model->query(['search' => '', 'status' => '']);
check(count($result['rows']) === 2, 'Rows come back from the stub');
check($result['rows'][0]['classification'] === 'active', 'An Active row with On Trip operational state still classifies as active');
check($result['rows'][0]['operational_status'] === 'On Trip', 'Operational status is carried separately');
check($result['rows'][0]['operational_class'] === 'on_trip', 'Operational class is normalized separately');
check($result['rows'][0]['from_terminal_id'] === 3 && $result['rows'][0]['to_terminal_id'] === 7, 'Terminal ids pass through when the columns exist');
check($result['rows'][0]['accessible_features'] === 'Priority Seats', 'Accessible features pass through when the column exists');
check($result['rows'][0]['registration_month'] === 'February', 'Registration month passes through the read model');
check($result['rows'][1]['registration_month'] === '', 'Buses without a registration month carry an empty value');
check($result['rows'][0]['insurance_state'] === 'expired', 'Expiry states are computed per row');
check($result['rows'][1]['classification'] === 'archived', 'Archived rows classify correctly');
check($result['counts']['active'] === 5, 'Per-filter counts use the lifecycle sums');
check($result['counts']['other'] === 1, 'Legacy statuses count under other');
check($result['summary']['total'] === array_sum($result['counts']), 'Summary total equals the sum of every bucket');
check($result['errors'] === [], 'No errors are reported with complete schema');

/* Exact lifecycle-count query shape */
$countsSql = '';
foreach ($stub->statements as $statement) {
    if (stripos($statement, 'SUM(CASE') !== false) {
        $countsSql = $statement;
        break;
    }
}
check($countsSql !== '', 'The counts query is executed');
foreach (array_merge(array_keys(BusDirectoryModel::statusOptions()), ['other']) as $class) {
    check(strpos($countsSql, 'AS `' . $class . '`') !== false, "Counts include the $class bucket");
}
check(strpos($countsSql, 'operational_status') === false, 'Counts never bucket by operational status');

/* --------------------- reduced schema (missing columns) --------------------- */

$reducedStub = new BusDirStubPdo([
    'buses' => array_fill_keys(['id', 'bus_id', 'status'], true),
]);
[$sql, $params] = (new BusDirectoryModel($reducedStub))->buildQuery([]);
check(strpos($sql, 'NULL AS from_terminal_id') !== false, 'Missing terminal column degrades to null');
check(strpos($sql, 'NULL AS to_terminal_id') !== false, 'Missing destination terminal column degrades to null');
check(strpos($sql, "'' AS accessible_features") !== false, 'Missing accessible column degrades to empty text');
check(strpos($sql, 'COLLATE utf8mb4_general_ci') !== false, 'Reduced schema still collation-wraps comparisons');

$noStatusStub = new BusDirStubPdo([
    'buses' => array_fill_keys(['id', 'bus_id'], true),
]);
[$sql, $params] = (new BusDirectoryModel($noStatusStub))->buildQuery([]);
check(strpos($sql, "'active' AS raw_status") !== false, 'Missing status column falls back to active rows');
check(BusDirectoryModel::classify(['raw_status' => 'active', 'operational_status' => 'Anything']) === 'active', 'Fallback rows classify as active');

echo "$checks bus directory checks passed.\n";
