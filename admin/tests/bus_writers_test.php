<?php declare(strict_types=1);

/**
 * Bus writer checks: app\Models\Bus and admin\models\Bus\BusModel must attach
 * the same BusFields fragments for the same input, and must skip columns the
 * installation does not have.
 */

if (!isset($_SERVER['DOCUMENT_ROOT']) || $_SERVER['DOCUMENT_ROOT'] === '') {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
}

require_once __DIR__ . '/../services/Bus/BusFields.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Models/Bus.php';
require_once __DIR__ . '/../models/Bus/BusModel.php';

use Admin\Services\Bus\BusFields;

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

class BusWritersStatement
{
    public function execute($params = null)
    {
        return true;
    }

    public function fetchColumn()
    {
        return false;
    }

    public function fetchAll($mode = null)
    {
        return [];
    }

    public function fetch($mode = null)
    {
        return false;
    }
}

class BusWritersStubPdo extends PDO
{
    private array $tables;
    public array $prepared = [];

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
            return new class(isset($this->tables[$table])) extends BusWritersStatement {
                private $value;
                public function __construct($value) { $this->value = $value; }
                public function fetchColumn() { return $this->value ? 'x' : false; }
            };
        }
        if (stripos($sql, 'SHOW COLUMNS FROM') === 0) {
            $table = trim(trim(substr($sql, strlen('SHOW COLUMNS FROM'))), "` \t");
            $fields = array_map(fn ($c) => ['Field' => $c], array_keys($this->tables[$table] ?? []));
            return new class($fields) extends BusWritersStatement {
                private $rows;
                public function __construct($rows) { $this->rows = $rows; }
                public function fetchAll($mode = null) { return $this->rows; }
            };
        }

        return new BusWritersStatement();
    }

    #[\ReturnTypeWillChange]
    public function prepare($statement, $options = null)
    {
        $this->prepared[] = (string)$statement;

        if (stripos((string)$statement, 'information_schema.COLUMNS') !== false) {
            // Column existence stub: params are [table, column].
            return new class($this->tables) extends BusWritersStatement {
                private array $tables;
                private ?array $params = null;
                public function __construct(array $tables) { $this->tables = $tables; }
                public function execute($params = null) { $this->params = $params; return true; }
                public function fetchColumn()
                {
                    if (!is_array($this->params) || count($this->params) < 2) {
                        return 0;
                    }
                    $table = (string)$this->params[0];
                    $column = (string)$this->params[1];
                    return isset($this->tables[$table][$column]) ? 1 : 0;
                }
            };
        }

        return new BusWritersStatement();
    }

    #[\ReturnTypeWillChange]
    public function exec($statement)
    {
        return 0;
    }

    #[\ReturnTypeWillChange]
    public function quote($string, $type = PDO::PARAM_STR)
    {
        return "'" . $string . "'";
    }

    public function lastStatement(): string
    {
        return $this->prepared ? (string)end($this->prepared) : '';
    }
}

function busTable(array $extraColumns = []): array
{
    $base = [
        'id', 'bus_id', 'plate_number', 'bus_type', 'seats', 'standing_capacity',
        'accessible_features', 'driver', 'route_name', 'terminal', 'schedule', 'status', 'qr_code',
    ];

    return array_fill_keys(array_merge($base, $extraColumns), true);
}

function fullSchema(): array
{
    return busTable(array_keys(BusFields::TYPED_COLUMNS));
}

$payload = [
    'bus_id' => 'BUS-009',
    'plate_number' => 'ABC 1234',
    'bus_type' => 'Coach',
    'unit_number' => '1201',
    'manufacturer' => 'Yutong',
    'model' => 'ZK6122',
    'year_model' => '2023',
    'engine_number' => 'ENG-1',
    'chassis_number' => 'CHS-1',
    'fuel_type' => 'Diesel',
    'transmission_type' => 'Manual',
    'air_conditioning' => 'Air-Conditioned',
    'vehicle_ownership' => 'Cooperative-Owned',
    'assigned_inspector_id' => 3,
    'operational_status' => 'Available',
    'registration_expiry' => '2027-05-01',
    'created_by' => 'superadmin',
];

/* --------------------------- identical fragments --------------------------- */

$appStub = new BusWritersStubPdo(['buses' => fullSchema()]);
$appBus = new \App\Models\Bus($appStub);
$appBus->insert($payload);
$appInsertSql = $appStub->lastStatement();

$adminStub = new BusWritersStubPdo(['buses' => fullSchema()]);
$adminBus = new \BusModel($adminStub);
$adminBus->addBus($payload);
$adminInsertSql = $adminStub->lastStatement();

[$expectedCols, $expectedPlaceholders] = BusFields::extraInsert($appStub, $payload);
check($expectedCols !== '' && strpos($appInsertSql, $expectedCols) !== false, 'App model insert carries the shared extra columns');
check(strpos($adminInsertSql, $expectedCols) !== false, 'Admin model insert carries the same extra columns');
check(strpos($appInsertSql, $expectedPlaceholders) !== false && strpos($adminInsertSql, $expectedPlaceholders) !== false, 'Both inserts carry the same placeholders');

$appBus->update('BUS-009', $payload);
$appUpdateSql = $appStub->lastStatement();
$adminBus->editBus('BUS-009', $payload);
$adminUpdateSql = $adminStub->lastStatement();

[$expectedSet] = BusFields::extraUpdate($appStub, $payload);
check($expectedSet !== '' && strpos($appUpdateSql, $expectedSet) !== false, 'App model update carries the shared set fragment');
check(strpos($adminUpdateSql, $expectedSet) !== false, 'Admin model update carries the same set fragment');
check(strpos($appUpdateSql, '`created_by`') === false && strpos($adminUpdateSql, '`created_by`') === false, 'created_by is never rewritten by updates');
check(strpos($appUpdateSql, '`updated_at` = NOW()') !== false && strpos($adminUpdateSql, '`updated_at` = NOW()') !== false, 'Both updates refresh updated_at');

/* ---------------------------- missing columns skip ---------------------------- */

$partialStub = new BusWritersStubPdo(['buses' => busTable(['unit_number', 'updated_at'])]);
$partialBus = new \BusModel($partialStub);
$partialBus->addBus($payload);
$partialInsertSql = $partialStub->lastStatement();
check(strpos($partialInsertSql, '`unit_number`') !== false, 'Existing columns are still written');
check(strpos($partialInsertSql, '`manufacturer`') === false, 'Missing columns are skipped by the admin writer');
check(strpos($partialInsertSql, 'FROM buses') === false, 'The captured statement is an INSERT');

$partialBus->editBus('BUS-009', $payload);
$partialUpdateSql = $partialStub->lastStatement();
check(strpos($partialUpdateSql, '`manufacturer`') === false, 'Missing columns are skipped on update too');
check(strpos($partialUpdateSql, '`updated_at` = NOW()') !== false, 'updated_at still refreshes when present');

echo "$checks bus writer checks passed.\n";
