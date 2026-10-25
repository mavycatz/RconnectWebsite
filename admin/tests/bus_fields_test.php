<?php declare(strict_types=1);

/**
 * BusFields checks: additive column detection, insert/update fragments, and
 * value normalization, all against a stub PDO. No database is required.
 */

require_once __DIR__ . '/../services/Bus/BusFields.php';

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

class BusFieldsStubPdo extends PDO
{
    /** @var string[] */
    private array $columns;
    private bool $tablePresent;
    public array $alters = [];

    public function __construct(array $columns, bool $tablePresent = true)
    {
        $this->columns = $columns;
        $this->tablePresent = $tablePresent;
    }

    #[\ReturnTypeWillChange]
    public function query($statement, ...$args)
    {
        $sql = trim((string)$statement);
        if (stripos($sql, 'SHOW TABLES LIKE') === 0) {
            return new BusFieldsStubStatement($this->tablePresent ? 'buses' : false);
        }

        return new BusFieldsStubStatement(false);
    }

    #[\ReturnTypeWillChange]
    public function prepare($statement, $options = null)
    {
        $sql = (string)$statement;
        if (stripos($sql, 'information_schema.COLUMNS') !== false) {
            return new BusFieldsStubStatement(null, $this->columns);
        }
        if (stripos($sql, 'SHOW COLUMNS FROM') === 0) {
            return new BusFieldsStubStatement(array_map(fn ($c) => ['Field' => $c], $this->columns));
        }

        return new BusFieldsStubStatement(false);
    }

    #[\ReturnTypeWillChange]
    public function exec($statement)
    {
        $sql = trim((string)$statement);
        if (stripos($sql, 'ALTER TABLE') === 0) {
            $this->alters[] = $sql;
        }

        return 0;
    }

    #[\ReturnTypeWillChange]
    public function quote($string, $type = PDO::PARAM_STR)
    {
        return "'" . $string . "'";
    }
}

class BusFieldsStubStatement
{
    private $value;
    private ?array $columns;

    public function __construct($value, ?array $columns = null)
    {
        $this->value = $value;
        $this->columns = $columns;
    }

    public function fetchColumn()
    {
        return $this->value;
    }

    public function execute($params = null)
    {
        if ($this->columns !== null && is_array($params) && count($params) >= 2) {
            $this->value = in_array((string)$params[1], $this->columns, true) ? 1 : 0;
        }

        return true;
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

/* ------------------- fragment builders with missing columns ------------------- */

$partial = new BusFieldsStubPdo([
    'bus_id', 'plate_number', 'unit_number', 'registration_month', 'registration_expiry',
    'assigned_inspector_id', 'updated_at',
]);

[$cols, $placeholders, $params] = BusFields::extraInsert($partial, [
    'unit_number' => '  1201  ',
    'manufacturer' => 'Yutong',
    'registration_month' => '  February  ',
    'registration_expiry' => '2027-05-01',
    'assigned_inspector_id' => '7',
    'operational_status' => 'Available',
]);

check(strpos($cols, '`unit_number`') !== false, 'Existing column is included');
check(strpos($cols, '`registration_month`') !== false, 'Registration month is included on insert');
check(strpos($cols, '`manufacturer`') === false, 'Missing column is skipped on insert');
check(strpos($cols, '`operational_status`') === false, 'Missing column is skipped on insert (2)');
check(substr($placeholders, -7) === ', NOW()', 'updated_at is appended');
check($params[':unit_number'] === '1201', 'Text values are trimmed');
check($params[':registration_month'] === 'February', 'Registration month values are trimmed');
check($params[':registration_expiry'] === '2027-05-01', 'Valid dates are kept');
check($params[':assigned_inspector_id'] === 7, 'Integer references are cast');

[$set, $setParams] = BusFields::extraUpdate($partial, [
    'unit_number' => ' 12 ',
    'manufacturer' => 'Yutong',
    'registration_month' => '   ',
    'registration_expiry' => 'not-a-date',
    'created_by' => 'someone',
]);

check(strpos($set, '`unit_number` = :unit_number') !== false, 'Supplied existing column is written');
check(strpos($set, '`registration_month` = :registration_month') !== false, 'Registration month is written on update');
check(strpos($set, '`manufacturer`') === false, 'Missing column is skipped on update');
check(strpos($set, '`created_by`') === false, 'created_by is never updated');
check(strpos($set, '`updated_at` = NOW()') !== false, 'updated_at is always refreshed');
check($setParams[':unit_number'] === '12', 'Update values are trimmed');
check($setParams[':registration_month'] === null, 'Empty registration months normalize to null');
check($setParams[':registration_expiry'] === null, 'Invalid dates normalize to null');

[$setAll, $setAllParams] = BusFields::extraUpdate($partial, []);
check($setAll === ', `updated_at` = NOW()', 'Update without supplied fields only refreshes updated_at');
check($setAllParams === [], 'Update without supplied fields binds nothing');

check(BusFields::normalizeDate('2026-02-30') === null, 'Impossible calendar dates are rejected');
check(BusFields::normalizeDate('2026-02-28') === '2026-02-28', 'Real dates are accepted');
check(BusFields::normalizeDate('') === null, 'Empty dates normalize to null');

/* --------------------------- ensureColumns behavior --------------------------- */

$fresh = new BusFieldsStubPdo(['bus_id', 'plate_number']);
BusFields::ensureColumns($fresh);
check(count($fresh->alters) === count(BusFields::TYPED_COLUMNS), 'Every missing column is added once');
check(strpos($fresh->alters[0], '`unit_number`') !== false, 'First ALTER targets the first column');
BusFields::ensureColumns($fresh);
check(count($fresh->alters) === count(BusFields::TYPED_COLUMNS), 'The static guard prevents duplicate ALTERs');

// The static guard means the second stub's ensureColumns call is a no-op, so
// the table-missing path is verified directly through tableExists().
$noTable = new BusFieldsStubPdo([], false);
check(BusFields::tableExists($noTable, 'buses') === false, 'Missing tables are detected');
check(BusFields::tableExists($fresh, 'buses') === true, 'Existing tables are detected');

echo "$checks bus fields checks passed.\n";
