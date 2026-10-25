<?php declare(strict_types=1);
namespace Admin\Services\RoleManagement;
require_once __DIR__ . '/ArchiveMetadata.php';
require_once __DIR__ . '/SecurityCodeService.php';
require_once __DIR__ . '/../Bus/BusFields.php';
require_once __DIR__ . '/../Bus/BusUpdatePayload.php';
require_once __DIR__ . '/../Bus/BusRegistrationValidator.php';
require_once __DIR__ . '/../Bus/BusAssignmentService.php';
use Admin\Services\Bus\BusAssignmentService;
use Admin\Services\Bus\BusFields;
use Admin\Services\Bus\BusRegistrationValidator;
use Admin\Services\Bus\BusUpdatePayload;
use PDO;

/** Shared implementation for the existing guarded role/bus archive endpoints. */
final class ArchiveLifecycle
{
    public static function source(string $role): string { return SecurityCodeService::targetLabel($role); }

    public static function transition(PDO $db, array $actor, string $role, string $ref, string $action, string $reason, ?array $payload = null): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) { throw new \InvalidArgumentException('A reason of at least 5 characters is required.'); }
        if (!in_array($action, ['archive', 'restore'], true) || !ctype_digit($ref) || (int) $ref <= 0) { throw new \InvalidArgumentException('Invalid archive/restore request.'); }
        if (!SecurityCodeService::actionAllowed((string) $actor['role'], $action, SecurityCodeService::targetLabel($role))) { throw new \RuntimeException('You are not authorized to manage this record.'); }
        $source = self::source($role);
        $table = match ($source) { 'bus' => 'buses', 'driver' => 'drivers', 'inspector' => 'inspector_accounts', 'finance', 'admin', 'super_admin' => 'users', default => throw new \InvalidArgumentException('Unsupported archive source.') };
        if ($source === 'super_admin') { $source = 'admin'; }
        // Check audit readiness before any status write. Reuse existing storage.
        try { $db->query('SELECT id, previous_values, new_values, created_at FROM audit_trail LIMIT 1'); }
        catch (\Throwable $e) {
            throw new \RuntimeException('Existing audit history is unavailable. Restore the audit_trail schema before archiving or restoring records.', 0, $e);
        }
        self::ensureArchivedStatus($db, $table);
        $own = !$db->inTransaction();
        if ($own) { $db->beginTransaction(); }
        try {
            $lookup = $source === 'inspector'
                ? 'SELECT ia.* FROM inspector_accounts ia JOIN inspectors i ON i.account_id = ia.account_id WHERE i.inspector_pk = ?'
                : "SELECT * FROM $table WHERE id = ?";
            if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') { $lookup .= ' FOR UPDATE'; }
            $stmt = $db->prepare($lookup); $stmt->execute([(int) $ref]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) { throw new \RuntimeException('Record not found.'); }
            if ($table === 'users') {
                $actualRole = str_replace(' ', '_', strtolower((string) ($row['role'] ?? '')));
                $expected = $source === 'finance' ? ['finance_officer', 'finance_manager'] : ['admin', 'super_admin'];
                if (!in_array($actualRole, $expected, true)) { throw new \RuntimeException('Record does not belong to this management page.'); }
                if (in_array($actualRole, ['admin', 'super_admin'], true) && strtolower((string) $actor['role']) !== 'super_admin') { throw new \RuntimeException('Only a Super Admin may manage administrator accounts.'); }
                if ($action === 'archive' && (int) $row['id'] === (int) $actor['id']) { throw new \RuntimeException('You cannot archive your own account.'); }
                if ($action === 'archive' && $actualRole === 'super_admin') {
                    $sql = "SELECT id FROM users WHERE REPLACE(LOWER(role),' ','_')='super_admin' AND LOWER(status) IN ('active','approved')";
                    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') { $sql .= ' FOR UPDATE'; }
                    $activeSuperAdmins = $db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
                    if (count(array_filter($activeSuperAdmins, static fn($id): bool => (int) $id !== (int) $row['id'])) === 0) {
                        throw new \RuntimeException('The last active Super Admin cannot be archived.');
                    }
                }
            }
            $metadata = ArchiveMetadata::details($db, $source, $ref, $source === 'bus' ? (string) $row['bus_id'] : null);
            $archived = strtolower((string) ($row['status'] ?? '')) === 'archived' || $metadata !== null;
            $explicitReactivation = $source !== 'bus' && $action === 'restore' && !$archived
                && in_array(strtolower((string) ($row['status'] ?? '')), ['inactive', 'disabled', 'deactivated'], true);
            if (($action === 'archive' && $archived) || ($action === 'restore' && !$archived && !$explicitReactivation)) {
                if ($own) { $db->commit(); }
                return ['success' => true, 'status' => 'success', 'unchanged' => true, 'message' => 'Record is already ' . ($action === 'archive' ? 'archived.' : 'restored.')];
            }
            if ($source === 'bus' && $action === 'restore' && is_array($payload)) {
                self::applyBusRestoreDetails($db, $row, $payload, $actor);
            }
            $before = ['status' => $row['status'] ?? null];
            if (array_key_exists('is_active', $row)) { $before['is_active'] = $row['is_active']; }
            if ($source === 'bus') { $before['operational_status'] = $row['operational_status'] ?? null; }
            $prior = $metadata['previous'] ?? [];
            $status = $action === 'archive' ? 'Archived' : ($prior['status'] ?? ($source === 'driver' || $source === 'bus' ? 'Active' : 'active'));
            if ($action === 'restore' && strtolower((string) $status) === 'archived') { $status = $source === 'bus' || $source === 'driver' ? 'Active' : 'active'; }
            $after = ['status' => $status];
            $set = 'status = ?'; $params = [$status];
            if (array_key_exists('is_active', $row)) {
                $after['is_active'] = $action === 'archive' ? 0 : ($prior['is_active'] ?? 1);
                $set .= ', is_active = ?'; $params[] = $after['is_active'];
            }
            $pk = $source === 'inspector' ? 'account_id' : 'id';
            $params[] = $row[$pk];
            $stmt = $db->prepare("UPDATE $table SET $set WHERE $pk = ?"); $stmt->execute($params);
            ArchiveMetadata::record($db, $source, $ref, $action, $reason, $actor, $before, $after);
            if ($own) { $db->commit(); }
            return ['success' => true, 'status' => 'success', 'message' => ucfirst($source) . ' ' . ($action === 'archive' ? 'archived.' : 'restored.'), 'ref' => $ref];
        } catch (\Throwable $e) {
            if ($own && $db->inTransaction()) { $db->rollBack(); }
            throw $e;
        }
    }

    /**
     * Apply the bus detail fields supplied with a restore.
     *
     * Only the fields the request actually supplies are written, using the
     * shared preserve-on-absent payload rules and the schema-tolerant bus
     * writer. Lifecycle status, operational status, QR data, and every field
     * the request does not provide are left untouched. Runs inside the
     * caller's restore transaction so a failure commits nothing.
     *
     * @param array<string,mixed> $row     current bus row
     * @param array<string,mixed> $payload full restore request payload
     * @param array<string,mixed> $actor   acting admin
     */
    private static function applyBusRestoreDetails(PDO $db, array $row, array $payload, array $actor): void
    {
        $detailKeys = [
            'plate_number', 'unit_number', 'bus_type', 'manufacturer', 'model', 'year_model',
            'seats', 'standing_capacity', 'engine_number', 'chassis_number', 'fuel_type',
            'transmission_type', 'air_conditioning', 'vehicle_ownership', 'driver',
            'assigned_inspector_id', 'schedule', 'from_terminal_id', 'to_terminal_id',
            'accessible_features', 'registration_month', 'registration_expiry',
            'insurance_expiry', 'inspection_date', 'next_inspection_date',
        ];

        $detail = [];
        foreach ($detailKeys as $key) {
            if (array_key_exists($key, $payload)) {
                $value = $payload[$key];
                $detail[$key] = is_scalar($value) || $value === null ? (string)($value ?? '') : '';
            }
        }
        if ($detail === []) {
            return;
        }

        $config = @require __DIR__ . '/../../config/bus_options.php';
        $config = is_array($config) ? $config : [];

        $built = BusUpdatePayload::build($row, $detail, $config);

        $validationInput = $detail;
        if (!array_key_exists('plate_number', $validationInput)) {
            $validationInput['plate_number'] = (string)($row['plate_number'] ?? '');
        }
        if (!array_key_exists('bus_type', $validationInput)) {
            $validationInput['bus_type'] = (string)($row['bus_type'] ?? '');
        }
        $validationInput['seats'] = (int)$built['seats'];
        $validationInput['standing_capacity'] = (int)$built['standing_capacity'];
        $validationError = BusRegistrationValidator::validate($validationInput, $config);
        if ($validationError !== null) {
            throw new \InvalidArgumentException($validationError);
        }

        $busId = (string)($row['bus_id'] ?? '');
        $assignments = new BusAssignmentService($db);

        if (array_key_exists('driver', $detail)) {
            $driver = (string)$built['driver'];
            $previous = trim((string)($row['driver'] ?? ''));
            if (strcasecmp($previous, 'Unassigned') === 0) {
                $previous = '';
            }
            if ($driver !== '' && $driver !== $previous) {
                $assignment = $assignments->assign($busId, $driver, false, (string)($actor['username'] ?? ''));
                if (!$assignment['success']) {
                    throw new \RuntimeException($assignment['error'] ?? 'The driver assignment could not be saved.');
                }
            } elseif ($driver === '' && $previous !== '') {
                $assignments->unassign($busId);
            }
        }

        if (array_key_exists('assigned_inspector_id', $detail)) {
            $inspector = $built['inspector'];
            $error = $assignments->validateInspector($inspector !== null && $inspector > 0 ? (int)$inspector : null);
            if ($error !== null) {
                throw new \InvalidArgumentException($error);
            }
        }

        foreach (['from_terminal_id', 'to_terminal_id'] as $terminalKey) {
            if (array_key_exists($terminalKey, $detail)) {
                $error = $assignments->validateTerminal($built[$terminalKey]);
                if ($error !== null) {
                    throw new \InvalidArgumentException($error);
                }
            }
        }

        $data = array_intersect_key($built['data'], $detail);
        $postedDates = array_intersect(array_keys(BusRegistrationValidator::DATE_FIELDS), array_keys($detail));
        if ($postedDates !== []) {
            $dates = BusRegistrationValidator::normalizedDates($detail);
            foreach (array_keys($dates) as $field) {
                if (!array_key_exists($field, $detail)) {
                    unset($dates[$field]);
                }
            }
            $data = array_merge($data, $dates);
        }

        BusFields::updateProvided($db, 'id', $row['id'], $data);
    }

    public static function ensureArchivedStatus(PDO $db, string $table): void
    {
        if (!in_array($table, ['users', 'drivers', 'inspector_accounts', 'buses'], true)) { throw new \InvalidArgumentException('Invalid archive table.'); }
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') { return; }
        $column = $db->query("SHOW COLUMNS FROM $table LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
        $type = (string) ($column['Type'] ?? '');
        if (!str_starts_with(strtolower($type), 'enum(') || stripos($type, "'archived'") !== false) { return; }
        if ($db->inTransaction()) { throw new \RuntimeException('Archive status schema must be upgraded before a transaction.'); }
        // Preserve every accepted value/default/nullability, add only Archived.
        $type = substr($type, 0, -1) . ",'Archived')";
        $null = ($column['Null'] ?? '') === 'YES' ? ' NULL' : ' NOT NULL';
        $default = $column['Default'] === null ? (($column['Null'] ?? '') === 'YES' ? ' DEFAULT NULL' : '') : ' DEFAULT ' . $db->quote((string) $column['Default']);
        $db->exec("ALTER TABLE $table MODIFY status $type$null$default");
    }
}
