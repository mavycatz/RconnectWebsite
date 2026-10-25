<?php declare(strict_types=1);

namespace Admin\Services\Bus;

use PDO;
use Throwable;

/**
 * Bus assignment rules shared by the add/edit endpoints.
 *
 * Eligibility: only active, non-archived drivers and active inspectors are
 * assignable, and routes/terminals must already exist. The canonical record
 * lives in `driver_bus_assignment`; the legacy `buses.driver` and
 * `drivers.assigned_buses` columns are mirrored for readers that still use
 * them. Nothing in trip or finance tables is ever written here.
 */
final class BusAssignmentService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    private function driverName(): string
    {
        try {
            return (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable $e) {
            return 'mysql';
        }
    }

    /**
     * Collation-safe column expression: explicit on MySQL (prevents error
     * 1267 against legacy collations), plain on SQLite.
     */
    private function wrap(string $expr): string
    {
        return $this->driverName() === 'sqlite'
            ? $expr
            : 'CONVERT(' . $expr . ' USING utf8mb4) COLLATE utf8mb4_general_ci';
    }

    private function tableExists(string $table): bool
    {
        try {
            if ($this->driverName() === 'sqlite') {
                $stmt = $this->db->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
                $stmt->execute([$table]);

                return (bool)$stmt->fetchColumn();
            }

            $stmt = $this->db->query('SHOW TABLES LIKE ' . $this->db->quote($table));

            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            if ($this->driverName() === 'sqlite') {
                foreach ($this->db->query('PRAGMA table_info(`' . $table . '`)')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    if (strtolower((string)($row['name'] ?? '')) === strtolower($column)) {
                        return true;
                    }
                }

                return false;
            }

            foreach ($this->db->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (strtolower((string)($row['Field'] ?? '')) === strtolower($column)) {
                    return true;
                }
            }
        } catch (Throwable $e) {
        }

        return false;
    }

    /**
     * @return string|null error message or null when the driver is eligible
     */
    public function validateDriver(string $driverId): ?string
    {
        $driverId = trim($driverId);
        if ($driverId === '') {
            return null;
        }
        if (!$this->tableExists('drivers') || !$this->hasColumn('drivers', 'driver_id')) {
            return 'Select an eligible active driver.';
        }

        $sql = 'SELECT 1 FROM drivers WHERE driver_id = ?';
        if ($this->hasColumn('drivers', 'status')) {
            $sql .= ' AND ' . $this->wrap('status') . " = 'Active'";
        }
        $sql .= ' LIMIT 1';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$driverId]);

            return $stmt->fetchColumn() ? null : 'Select an eligible active driver.';
        } catch (Throwable $e) {
            return 'Select an eligible active driver.';
        }
    }

    /**
     * @return string|null error message or null when the route exists
     */
    public function validateRoute(string $route): ?string
    {
        $route = trim($route);
        if ($route === '') {
            return null;
        }

        $sources = [];
        foreach ([['fare_pricing', 'route_name'], ['route_stops', 'route_name']] as $source) {
            if ($this->tableExists($source[0]) && $this->hasColumn($source[0], $source[1])) {
                $sources[] = $source[0];
            }
        }
        if (!$sources) {
            return null; // Nothing to validate against on this installation.
        }

        foreach ($sources as $table) {
            try {
                $stmt = $this->db->prepare(
                    'SELECT 1 FROM ' . $table
                    . ' WHERE ' . $this->wrap('route_name') . ' = ? LIMIT 1'
                );
                $stmt->execute([$route]);
                if ($stmt->fetchColumn()) {
                    return null;
                }
            } catch (Throwable $e) {
                return null;
            }
        }

        return 'Select an existing route.';
    }

    /**
     * @return string|null error message or null when the terminal is valid
     */
    public function validateTerminal(?int $terminalId): ?string
    {
        if ($terminalId === null || $terminalId <= 0) {
            return null;
        }
        if (!$this->tableExists('terminals')) {
            return null;
        }

        try {
            $sql = 'SELECT 1 FROM terminals WHERE id = ?';
            if ($this->hasColumn('terminals', 'status')) {
                $sql .= ' AND ' . $this->wrap('status') . " = 'Active'";
            }
            $sql .= ' LIMIT 1';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$terminalId]);

            return $stmt->fetchColumn() ? null : 'Select a valid active terminal.';
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @return string|null error message or null when the inspector is valid
     */
    public function validateInspector(?int $inspectorRef): ?string
    {
        if ($inspectorRef === null || $inspectorRef <= 0) {
            return null;
        }
        if (!$this->tableExists('inspectors') || !$this->hasColumn('inspectors', 'inspector_pk')) {
            return 'Select a valid inspector.';
        }

        $joins = '';
        $whereActive = '';
        if ($this->tableExists('inspector_accounts') && $this->hasColumn('inspector_accounts', 'account_id')) {
            $joins = ' INNER JOIN inspector_accounts ia ON ia.account_id = i.account_id';
            if ($this->hasColumn('inspector_accounts', 'status')) {
                $whereActive = ' AND ' . $this->wrap('ia.status') . " IN ('active', 'approved')";
            }
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM inspectors i' . $joins
                . ' WHERE i.inspector_pk = ?' . $whereActive . ' LIMIT 1'
            );
            $stmt->execute([$inspectorRef]);

            return $stmt->fetchColumn() ? null : 'Select a valid inspector.';
        } catch (Throwable $e) {
            return 'Select a valid inspector.';
        }
    }

    /**
     * Assign a driver to a bus.
     *
     * A driver already bound to a different bus is a conflict unless
     * $allowMove is true, in which case the previous assignment is moved and
     * reported back for auditing.
     *
     * @return array{success:bool,error:?string,conflict_bus:?string,moved_from:?string}
     */
    public function assign(string $busId, string $driverId, bool $allowMove = false, ?string $actor = null): array
    {
        $busId = trim($busId);
        $driverId = trim($driverId);
        $result = ['success' => false, 'error' => null, 'conflict_bus' => null, 'moved_from' => null];

        if ($busId === '' || $driverId === '' || $driverId === 'Unassigned') {
            $result['error'] = 'Select an eligible active driver.';

            return $result;
        }

        $eligibility = $this->validateDriver($driverId);
        if ($eligibility !== null) {
            $result['error'] = $eligibility;

            return $result;
        }

        if (!$this->tableExists('driver_bus_assignment')) {
            // Legacy-only installation: mirror to the bus row directly.
            $this->mirrorLegacy($busId, $driverId);
            $result['success'] = true;

            return $result;
        }

        $started = false;
        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $started = true;
            }

            /* Lock the driver's binding row (MySQL: FOR UPDATE; SQLite
               serializes writers through the transaction) so two simultaneous
               requests cannot both bind the same driver. */
            $lock = $this->driverName() === 'sqlite' ? '' : ' FOR UPDATE';
            $existing = $this->db->prepare('SELECT bus_id FROM driver_bus_assignment WHERE driver_id = ?' . $lock);
            $existing->execute([$driverId]);
            $boundBus = trim((string)($existing->fetchColumn() ?: ''));

            if ($boundBus !== '' && $boundBus !== $busId && !$allowMove) {
                if ($started) {
                    $this->db->rollBack();
                }
                $result['conflict_bus'] = $boundBus;
                $result['error'] = 'This driver is already assigned to bus ' . $boundBus . '. Confirm to move the assignment.';

                return $result;
            }

            /* Lock the target bus's binding row too, so concurrent requests
               cannot leave two drivers bound to the same bus. */
            $busLock = $this->db->prepare('SELECT driver_id FROM driver_bus_assignment WHERE bus_id = ?' . $lock);
            $busLock->execute([$busId]);
            $busLock->fetchColumn();

            /* Remove any binding for this driver and this bus before inserting,
               so a concurrent double-assign cannot leave duplicate rows: the
               last committed request wins with a single binding. */
            $removeDriver = $this->db->prepare('DELETE FROM driver_bus_assignment WHERE driver_id = ?');
            $removeDriver->execute([$driverId]);
            if ($boundBus !== '' && $boundBus !== $busId) {
                $this->mirrorLegacy($boundBus, '', true);
                $result['moved_from'] = $boundBus;
            }

            $clear = $this->db->prepare('DELETE FROM driver_bus_assignment WHERE bus_id = ?');
            $clear->execute([$busId]);

            $insert = $this->db->prepare('INSERT INTO driver_bus_assignment (driver_id, bus_id) VALUES (?, ?)');
            $insert->execute([$driverId, $busId]);

            $this->mirrorLegacy($busId, $driverId);
            if ($started) {
                $this->db->commit();
            }
            $result['success'] = true;

            return $result;
        } catch (Throwable $e) {
            if ($started && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $result['error'] = 'The driver assignment could not be saved.';

            return $result;
        }
    }

    /**
     * Remove the bus's driver assignment and clear the legacy mirrors.
     */
    public function unassign(string $busId): void
    {
        $busId = trim($busId);
        if ($busId === '') {
            return;
        }

        $previous = '';
        if ($this->tableExists('driver_bus_assignment')) {
            try {
                $check = $this->db->prepare('SELECT driver_id FROM driver_bus_assignment WHERE bus_id = ? LIMIT 1');
                $check->execute([$busId]);
                $previous = trim((string)($check->fetchColumn() ?: ''));

                $delete = $this->db->prepare('DELETE FROM driver_bus_assignment WHERE bus_id = ?');
                $delete->execute([$busId]);
            } catch (Throwable $e) {
                // Best effort; the legacy mirror below still records the state.
            }
        }

        try {
            $stmt = $this->db->prepare("UPDATE buses SET driver = 'Unassigned' WHERE bus_id = ?");
            $stmt->execute([$busId]);
        } catch (Throwable $e) {
        }

        if ($previous !== '') {
            $this->mirrorLegacy('', $previous, true);
        }
    }

    /**
     * Keep the legacy columns that older readers use in step with the
     * canonical assignment. Never touches trip or finance tables.
     */
    private function mirrorLegacy(string $busId, string $driverId, bool $clearDriverRow = false): void
    {
        try {
            if ($busId !== '') {
                $stmt = $this->db->prepare('UPDATE buses SET driver = ? WHERE bus_id = ?');
                $stmt->execute([$driverId !== '' ? $driverId : 'Unassigned', $busId]);
            }
        } catch (Throwable $e) {
        }

        if ($driverId !== '') {
            try {
                $stmt = $this->db->prepare('UPDATE drivers SET assigned_buses = ? WHERE driver_id = ?');
                $stmt->execute([$clearDriverRow ? null : $busId, $driverId]);
            } catch (Throwable $e) {
            }
        }
    }
}
