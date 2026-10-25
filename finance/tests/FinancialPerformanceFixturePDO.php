<?php
declare(strict_types=1);

final class FinancialPerformanceFixturePDO extends PDO
{
    public array $queries = [];
    private array $tables;

    public function __construct(array $tables) { $this->tables = $tables; }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->queries[] = $query;
        return new FinancialPerformanceFixtureStatement(function (array $params) use ($query): array {
            if (str_contains($query, 'information_schema.COLUMNS')) {
                $table = $params[0];
                return array_map(static fn($col) => ['COLUMN_NAME' => $col], $this->tables[$table]['columns'] ?? []);
            }
            if (str_contains($query, 'FROM bus_trips')) {
                return array_map(static fn($r) => ['day' => $r['trip_date']] + $r,
                    array_values(array_filter($this->tables['bus_trips']['rows'] ?? [], static fn($r) => $r['trip_date'] >= $params[0] && $r['trip_date'] < $params[1])));
            }
            if (preg_match('/FROM `([a-z_]+)` r/', $query, $match)) {
                $table = $match[1];
                if (!str_contains($query, '>= :from') || !str_contains($query, '< :until')) throw new RuntimeException('Source query lost inclusive/exclusive bounds.');
                $rows = [];
                foreach ($this->tables[$table]['rows'] ?? [] as $r) {
                    $date = $r['boarding_time'] ?? $r['trip_date'] ?? $r['expense_date'] ?? $r['created_at'];
                    if ($date < $params[':from'] || $date >= $params[':until']) continue;
                    if ($table === 'passenger_trips' && (strtolower($r['payment_status']) !== 'paid' || ($r['archived_status'] ?? '') === 'yes')) continue;
                    if ($table === 'fuel_logs' && ($r['liters'] ?? 0) <= 0) continue;
                    $rows[] = ['report_date' => $date, 'report_amount' => $r['fare'] ?? $r['amount'] ?? $r['total_cost'] ?? $r['cost'] ?? $r['actual_cash'], 'source_id' => $r['history_id'] ?? $r['id'] ?? $r['refuel_id'] ?? ''] + $r;
                }
                return $rows;
            }
            throw new RuntimeException('Unrecognized fixture query: ' . $query);
        });
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if (!preg_match('/FROM `(drivers|buses)`/', $query, $match)) throw new RuntimeException('Unrecognized directory query.');
        $table = $match[1];
        $rows = array_map(static fn($r) => ['code' => $r[$table === 'drivers' ? 'driver_id' : 'bus_id'], 'label' => $r[$table === 'drivers' ? 'full_name' : 'plate_number'], 'id' => $r['id']], $this->tables[$table]['rows']);
        $stmt = new FinancialPerformanceFixtureStatement(static fn() => $rows);
        $stmt->execute();
        return $stmt;
    }
}

final class FinancialPerformanceFixtureStatement extends PDOStatement
{
    private \Closure $load;
    private array $rows = [];
    private int $cursor = 0;
    public function __construct(\Closure $load) { $this->load = $load; }
    public function execute(?array $params = null): bool { $this->rows = ($this->load)($params ?? []); $this->cursor = 0; return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return $this->rows[$this->cursor++] ?? false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $mode === PDO::FETCH_COLUMN ? array_map(static fn($r) => array_values($r)[0], $this->rows) : $this->rows; }
}
