<?php declare(strict_types=1);

namespace Admin\Services\Dispatch;

require_once __DIR__ . '/../Bus/BusAssignmentService.php';
require_once __DIR__ . '/../Bus/BusFields.php';
require_once __DIR__ . '/../../../finance/models/AuditTrailModel.php';

use Admin\Services\Bus\BusAssignmentService;
use Admin\Services\Bus\BusFields;
use DateTimeImmutable;
use Finance\Models\AuditTrailModel;
use PDO;
use Throwable;

/**
 * Admin-owned dispatch queue: the official bus order for an operating date.
 *
 * The published order in `bus_dispatch_queue` is the authoritative planned
 * order the dispatcher's queue sort follows (dispatcher_queue_sort_buses).
 * This service only ever writes the queue table and the standing bus-driver
 * assignment - it never creates bus_trips rows or touches trip history.
 *
 * Eligible buses are existing `BUS-` identifiers that are not archived or
 * inactive. Buses under maintenance / out of service may stay listed but are
 * blocked from the queue, from assignment, and from publishing.
 */
final class DispatchQueueService
{
    public const TABLE = 'bus_dispatch_queue';
    public const ACTIVE_TRIP_STATUSES = ['Scheduled', 'Departed', 'Interrupted'];
    public const COMPLETED_TRIP_STATUSES = ['Completed'];
    public const BLOCKED_OPERATIONAL = ['Under Maintenance', 'Out of Service'];
    public const BLOCKED_DRIVER_AVAILABILITY = ['On Leave', 'Unavailable'];

    /** Availability values an admin may set from the dispatch queue page. */
    public const ADMIN_SETTABLE_OPERATIONAL = ['Available', 'Under Maintenance', 'Out of Service'];

    /** Error text for any write attempted on a past operating date. */
    public const HISTORY_READ_ONLY_ERROR = 'This operating date is from history and its queue is read-only; only the current date can be changed.';

    /** Error text for a publish attempted on a past operating date. */
    public const HISTORY_PUBLISH_ERROR = 'This operating date is from history and its queue cannot be published; only the current date can be published.';

    /** Error text for any queue change attempted on a published operating date. */
    public const PUBLISHED_LOCK_ERROR = 'This operating date\'s queue has been published and is locked; it can no longer be edited. Choose another operating date to make changes.';

    /** Per-operating-date driver terminal assignments table. */
    public const DRIVER_TERMINAL_TABLE = 'driver_terminal_assignments';

    /** @var \WeakMap|null per-connection schema guard, keyed by the live PDO */
    private static ?\WeakMap $ensured = null;

    private PDO $db;
    private AuditTrailModel $audit;

    public function __construct(PDO $db, ?AuditTrailModel $audit = null)
    {
        $this->db = $db;
        $this->audit = $audit ?? new AuditTrailModel();

        if (defined('APP_TIMEZONE') && date_default_timezone_get() !== APP_TIMEZONE) {
            date_default_timezone_set(APP_TIMEZONE);
        } elseif (!defined('APP_TIMEZONE') && date_default_timezone_get() !== 'Asia/Manila') {
            date_default_timezone_set('Asia/Manila');
        }
    }

    public function ensureSchema(): void
    {
        /* Keyed by the connection object itself (WeakMap), so a reused object
           id after a connection is freed can never skip the schema pass. */
        self::$ensured ??= new \WeakMap();
        if (isset(self::$ensured[$this->db])) {
            return;
        }
        self::$ensured[$this->db] = true;

        $this->db->exec('CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
            operating_date DATE NOT NULL,
            bus_id VARCHAR(20) NOT NULL,
            queue_position INT NOT NULL,
            published_at DATETIME NULL,
            published_by VARCHAR(120) NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (operating_date, bus_id)
        )');

        /* Deployments upgraded from an earlier queue build can have this table
           without the publication columns; add any missing one so saving and
           publishing keep working instead of failing on the INSERT/UPDATE. */
        $required = [
            'queue_position' => 'INT NOT NULL DEFAULT 0',
            'published_at' => 'DATETIME NULL',
            'published_by' => 'VARCHAR(120) NULL',
            'created_at' => 'DATETIME NULL',
            'updated_at' => 'DATETIME NULL',
            'terminal_id' => 'INT NULL',
        ];
        foreach ($required as $column => $definition) {
            try {
                $this->db->exec('ALTER TABLE ' . self::TABLE . ' ADD COLUMN IF NOT EXISTS `' . $column . '` ' . $definition);
            } catch (Throwable $e) {
                try {
                    $this->db->exec('ALTER TABLE ' . self::TABLE . ' ADD COLUMN `' . $column . '` ' . $definition);
                } catch (Throwable $e2) {
                    // Column already exists, or this driver cannot alter it.
                }
            }
        }

        /* Per-operating-date driver terminal assignments: drivers are assigned
           independently of buses, so their queue entry needs its own record. */
        try {
            $this->db->exec('CREATE TABLE IF NOT EXISTS ' . self::DRIVER_TERMINAL_TABLE . ' (
                operating_date DATE NOT NULL,
                driver_id VARCHAR(20) NOT NULL,
                terminal_id INT NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY (operating_date, driver_id)
            )');
        } catch (Throwable $e) {
            // Table already exists, or this driver cannot create it.
        }
    }

    /* ------------------------------ pure rules ------------------------------ */

    /**
     * A dispatchable bus identifier: existing BUS- ids only (case-insensitive
     * so legacy Bus-1xxx rows and official BUS-2xxx rows both qualify).
     */
    public static function busIdIsValid(string $busId): bool
    {
        return preg_match('/^BUS-/i', trim($busId)) === 1;
    }

    /**
     * Whether a bus lifecycle/operational state may be queued or published.
     *
     * @return array{eligible:bool,reason:string}
     */
    public static function busEligibility(array $bus): array
    {
        $busId = trim((string)($bus['bus_id'] ?? ''));
        if ($busId === '' || !self::busIdIsValid($busId)) {
            return ['eligible' => false, 'reason' => 'Not a valid BUS- identifier.'];
        }
        $status = trim((string)($bus['status'] ?? ''));
        if ($status !== '' && in_array(strtolower($status), ['archived', 'inactive'], true)) {
            return ['eligible' => false, 'reason' => 'Bus is ' . strtolower($status) . ' and cannot be dispatched.'];
        }
        $operational = trim((string)($bus['operational_status'] ?? ''));
        if ($operational !== '') {
            foreach (self::BLOCKED_OPERATIONAL as $blocked) {
                if (strcasecmp($operational, $blocked) === 0) {
                    return ['eligible' => false, 'reason' => 'Bus is "' . $operational . '" and cannot be dispatched.'];
                }
            }
        }

        return ['eligible' => true, 'reason' => ''];
    }

    /**
     * Driver availability gate (on top of the active-status check).
     *
     * @return array{eligible:bool,reason:string}
     */
    public static function driverEligibility(?array $driver): array
    {
        if ($driver === null || trim((string)($driver['driver_id'] ?? '')) === '') {
            return ['eligible' => false, 'reason' => 'Select an eligible active driver.'];
        }
        $status = trim((string)($driver['status'] ?? ''));
        if ($status !== '' && strcasecmp($status, 'Active') !== 0) {
            return ['eligible' => false, 'reason' => 'Driver account is not active.'];
        }
        $availability = trim((string)($driver['availability'] ?? ''));
        if ($availability !== '') {
            foreach (self::BLOCKED_DRIVER_AVAILABILITY as $blocked) {
                if (strcasecmp($availability, $blocked) === 0) {
                    return ['eligible' => false, 'reason' => 'Driver is "' . $availability . '".'];
                }
            }
        }

        return ['eligible' => true, 'reason' => ''];
    }

    public static function normalizeDate(string $date): ?string
    {
        $date = trim($date);
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return ($parsed !== false && $parsed->format('Y-m-d') === $date) ? $date : null;
    }

    /** Whether the operating date is before today and therefore read-only history. */
    public static function isHistoryDate(string $date): bool
    {
        $date = self::normalizeDate($date);

        return $date !== null && $date < date('Y-m-d');
    }

    /**
     * Whether the operating date's queue has been published. A published
     * date's queue is locked: saving/reordering, driver assignment, terminal
     * assignment, overrides, and availability changes are rejected for it.
     */
    public function isPublishedDate(string $date): bool
    {
        $this->ensureSchema();
        $date = self::normalizeDate($date);
        if ($date === null) {
            return false;
        }
        try {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE operating_date = ? AND published_at IS NOT NULL');
            $stmt->execute([$date]);

            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    /* ------------------------------- read model ------------------------------- */

    /**
     * Full page payload for an operating date.
     *
     * @return array<string,mixed>
     */
    public function queueData(string $date): array
    {
        $this->ensureSchema();
        $date = self::normalizeDate($date) ?? date('Y-m-d');

        $buses = $this->busRows();
        $queueRows = $this->queueRows($date);
        $assignmentMaps = $this->assignmentMaps();
        $assignments = $assignmentMaps['byBus'];
        $driverBindings = $assignmentMaps['byDriver'];
        $drivers = $this->driverMap();
        $tripInfo = $this->tripInfo($date, array_keys($buses));
        $problems = $this->problemInfo(array_keys($buses));
        $terminals = $this->terminalMap();
        $driverBindingRows = $this->driverBindingRows();
        $driverTerminals = $this->driverTerminalMap($date, $driverBindingRows);

        $publishedAt = null;
        $publishedBy = null;
        foreach ($queueRows as $row) {
            if (!empty($row['published_at'])) {
                $publishedAt = (string)$row['published_at'];
                $publishedBy = (string)($row['published_by'] ?? '');
                break;
            }
        }

        /* Drivers with an active assignment are unavailable for other buses
           until their trip completes and the arrival is confirmed. */
        $publishedNow = $publishedAt !== null;
        $queuedBusIds = $this->queuedBusIdMap($date, $buses, $problems, $publishedNow);
        $occupiedDrivers = $this->occupiedDriverMap($date, $driverBindingRows, $queuedBusIds, $publishedNow);

        $entries = [];
        $unavailable = [];
        $queuedIds = [];
        if ($publishedAt !== null) {
            /* Once published, the published bus order and queue_position values
               remain fixed and must never change automatically. All published
               buses remain in $entries in their published order and position. */
            foreach ($queueRows as $row) {
                $busId = (string)$row['bus_id'];
                $queuedIds[$busId] = true;
                $item = $this->decorateBus(
                    $busId,
                    $buses[$busId] ?? null,
                    (int)$row['queue_position'],
                    $assignments,
                    $drivers,
                    $tripInfo,
                    $problems,
                    $terminals,
                    self::toIntOrNull($row['terminal_id'] ?? null),
                    $driverTerminals
                );
                $item['persisted'] = true;
                $entries[] = $item;
            }
            usort($entries, static fn (array $a, array $b): int => $a['queue_position'] <=> $b['queue_position']);

            /* Buses in the fleet that are not part of the published queue go to unavailable */
            foreach ($buses as $busId => $bus) {
                if (isset($queuedIds[$busId])) {
                    continue;
                }
                $item = $this->decorateBus($busId, $bus, null, $assignments, $drivers, $tripInfo, $problems, $terminals, null, $driverTerminals);
                $item['persisted'] = false;
                $item['queue_position'] = null;
                $unavailable[] = $item;
            }
        } else {
            /* Draft mode: eligible saved rows stay, ineligible move to unavailable,
               unlisted eligible buses are appended, and positions 1..N are calculated. */
            foreach ($queueRows as $row) {
                $busId = (string)$row['bus_id'];
                $queuedIds[$busId] = true;
                $item = $this->decorateBus(
                    $busId,
                    $buses[$busId] ?? null,
                    (int)$row['queue_position'],
                    $assignments,
                    $drivers,
                    $tripInfo,
                    $problems,
                    $terminals,
                    self::toIntOrNull($row['terminal_id'] ?? null),
                    $driverTerminals
                );
                $item['persisted'] = true;
                if ($item['eligible']) {
                    $entries[] = $item;
                } else {
                    $item['queue_position'] = null;
                    $unavailable[] = $item;
                }
            }
            usort($entries, static fn (array $a, array $b): int => $a['queue_position'] <=> $b['queue_position']);

            $append = [];
            foreach ($buses as $busId => $bus) {
                if (isset($queuedIds[$busId])) {
                    continue;
                }
                $item = $this->decorateBus($busId, $bus, null, $assignments, $drivers, $tripInfo, $problems, $terminals, null, $driverTerminals);
                $item['persisted'] = false;
                if ($item['eligible']) {
                    $append[] = $item;
                } else {
                    $unavailable[] = $item;
                }
            }
            usort($append, fn (array $a, array $b): int => $this->scheduleSortKey($a) <=> $this->scheduleSortKey($b));
            foreach ($append as $item) {
                $entries[] = $item;
            }
            foreach ($entries as $index => &$entry) {
                $entry['queue_position'] = $index + 1;
            }
            unset($entry);
        }
        usort($unavailable, static fn (array $a, array $b): int => strcmp((string)$a['bus_id'], (string)$b['bus_id']));

        $maintenanceCount = count($unavailable);
        foreach ($entries as $entry) {
            if (!$entry['eligible']) {
                $maintenanceCount++;
            }
        }
        $summary = [
            'total' => count($buses),
            'queued' => count($entries),
            'assigned' => 0,
            'unassigned' => 0,
            'on_trip' => 0,
            'maintenance' => $maintenanceCount,
        ];
        foreach ($buses as $busId => $bus) {
            $trip = $tripInfo[$busId] ?? ['active_trip' => null];
            if ($trip['active_trip'] !== null) {
                $summary['on_trip']++;
            }
        }
        foreach ($entries as $entry) {
            if (!empty($entry['assigned_driver']['driver_id'])) {
                $summary['assigned']++;
            } else {
                $summary['unassigned']++;
            }
        }

        /* Terminal-based queue: expose every configured terminal so the page
           can organize the queue by terminal and assign entries to any of them. */
        $terminalOptions = [];
        foreach ($terminals as $terminal) {
            $name = $terminal['name'] !== '' ? $terminal['name'] : ('Terminal #' . $terminal['id']);
            $terminalOptions[] = ['id' => $terminal['id'], 'name' => $name];
        }
        usort($terminalOptions, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return [
            'operating_date' => $date,
            'is_history' => self::isHistoryDate($date),
            'published' => $publishedAt !== null
                ? ['at' => $publishedAt, 'by' => $publishedBy]
                : null,
            'entries' => $entries,
            'unavailable' => $unavailable,
            'terminals' => array_values($terminalOptions),
            'drivers' => $this->driverOptions($driverBindings, $drivers, $driverTerminals, $terminals, $occupiedDrivers),
            'summary' => $summary,
        ];
    }

    /**
     * Persist a draft order (positions 1..n). Any existing publication for the
     * date is invalidated because the official order changed.
     *
     * @param string[] $busIds
     * @param array{id?:int,username?:string,role?:string} $actor
     * @return array{success:bool,error:?string,data:?array}
     */
    public function saveDraft(string $date, array $busIds, array $actor): array
    {
        $this->ensureSchema();
        $date = self::normalizeDate($date);
        if ($date === null) {
            return ['success' => false, 'error' => 'Select a valid operating date.', 'data' => null];
        }
        if (self::isHistoryDate($date)) {
            return ['success' => false, 'error' => self::HISTORY_READ_ONLY_ERROR, 'data' => null];
        }
        if ($this->isPublishedDate($date)) {
            return ['success' => false, 'error' => self::PUBLISHED_LOCK_ERROR, 'data' => null];
        }

        $clean = [];
        foreach ($busIds as $busId) {
            $busId = trim((string)$busId);
            if ($busId === '' || isset($clean[$busId])) {
                continue;
            }
            if (!self::busIdIsValid($busId)) {
                return ['success' => false, 'error' => 'Only buses with BUS- identifiers can be queued.', 'data' => null];
            }
            $clean[$busId] = true;
        }
        $busIds = array_keys($clean);

        $busRows = $this->busRows();
        $problems = $this->problemInfo($busIds);
        foreach ($busIds as $busId) {
            $bus = $busRows[$busId] ?? null;
            if ($bus === null) {
                return ['success' => false, 'error' => 'Bus ' . $busId . ' does not exist.', 'data' => null];
            }
            $eligibility = self::busEligibility($bus);
            if (!$eligibility['eligible']) {
                return ['success' => false, 'error' => 'Bus ' . $busId . ': ' . $eligibility['reason'], 'data' => null];
            }
            if (isset($problems[$busId])) {
                return ['success' => false, 'error' => 'Bus ' . $busId . ': ' . self::problemReason($problems[$busId]), 'data' => null];
            }
        }

        $previous = [];
        foreach ($this->queueRows($date) as $row) {
            $previous[(string)$row['bus_id']] = (int)$row['queue_position'];
        }

        $this->db->beginTransaction();
        try {
            $this->writeOrder($date, $busIds);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return ['success' => false, 'error' => 'The dispatch queue could not be saved.', 'data' => null];
        }

        $this->record($actor, 'DispatchQueue', 'Queue Updated', sprintf(
            'Dispatch queue for %s updated (%d bus%s, draft).',
            $date,
            count($busIds),
            count($busIds) === 1 ? '' : 'es'
        ), $date, $previous, ['order' => $busIds]);

        return ['success' => true, 'error' => null, 'data' => $this->queueData($date)];
    }

    /**
     * Publish the saved order for the date as the official dispatch order.
     *
     * @param array{id?:int,username?:string,role?:string} $actor
     * @return array{success:bool,error:?string,blockers:string[],data:?array}
     */
    public function publish(string $date, array $actor): array
    {
        $this->ensureSchema();
        $date = self::normalizeDate($date);
        if ($date === null) {
            return ['success' => false, 'error' => 'Select a valid operating date.', 'blockers' => [], 'data' => null];
        }
        if (self::isHistoryDate($date)) {
            return ['success' => false, 'error' => self::HISTORY_PUBLISH_ERROR, 'blockers' => [], 'data' => null];
        }

        $rows = $this->queueRows($date);
        if ($rows === []) {
            if ($date === date('Y-m-d')) {
                $queueData = $this->queueData($date);
                $eligibleBusIds = [];
                foreach ($queueData['entries'] as $entry) {
                    if (!empty($entry['eligible']) && !empty($entry['bus_id'])) {
                        $eligibleBusIds[] = (string)$entry['bus_id'];
                    }
                }
                if ($eligibleBusIds !== []) {
                    $this->db->beginTransaction();
                    try {
                        $this->writeOrder($date, $eligibleBusIds);
                        $this->db->commit();
                        $rows = $this->queueRows($date);
                    } catch (Throwable $e) {
                        if ($this->db->inTransaction()) {
                            $this->db->rollBack();
                        }
                    }
                }
            }
        }
        if ($rows === []) {
            return ['success' => false, 'error' => 'There is no saved queue for this date to publish.', 'blockers' => [], 'data' => null];
        }

        $buses = $this->busRows();
        $problems = $this->problemInfo(array_map(static fn (array $row): string => (string)$row['bus_id'], $rows));
        $blockers = [];
        foreach ($rows as $row) {
            $busId = (string)$row['bus_id'];
            $bus = $buses[$busId] ?? null;
            if ($bus === null) {
                $blockers[] = 'Bus ' . $busId . ' no longer exists.';
                continue;
            }
            $eligibility = self::busEligibility($bus);
            if (!$eligibility['eligible']) {
                $blockers[] = 'Bus ' . $busId . ': ' . $eligibility['reason'];
                continue;
            }
            if (isset($problems[$busId])) {
                $blockers[] = 'Bus ' . $busId . ': ' . self::problemReason($problems[$busId]);
            }
        }
        if ($blockers !== []) {
            return ['success' => false, 'error' => 'The queue cannot be published while buses are unavailable.', 'blockers' => $blockers, 'data' => null];
        }

        /* Publishing makes the date's routing official. A bus without an
           explicit terminal enters at its configured home terminal, and the
           driver bound to a bus follows the bus's terminal for the operating
           date, so the dispatcher never refuses a driver that is right there
           with the bus. Every resolved change is recorded in the audit trail. */
        $sync = $this->syncTerminalsAtPublish($date, $rows);
        if (!empty($sync['error'])) {
            return ['success' => false, 'error' => (string)$sync['error'], 'blockers' => [], 'data' => null];
        }
        if ($sync['bus_defaults'] > 0 || $sync['driver_syncs'] > 0) {
            $this->record($actor, 'DispatchQueue', 'Terminal Assignments Synchronized', sprintf(
                'Publishing %s defaulted %d bus terminal(s) from the configured home terminal and synced %d driver terminal(s) with their bus.',
                $date,
                $sync['bus_defaults'],
                $sync['driver_syncs']
            ), $date, null, [
                'bus_terminals_defaulted' => $sync['bus_defaults'],
                'driver_terminals_synced' => $sync['driver_syncs'],
            ]);
        }

        $actorName = trim((string)($actor['username'] ?? ''));
        $now = date('Y-m-d H:i:s');
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('UPDATE ' . self::TABLE . ' SET published_at = ?, published_by = ?, updated_at = ? WHERE operating_date = ?');
            $stmt->execute([$now, $actorName, $now, $date]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return ['success' => false, 'error' => 'The dispatch queue could not be published.', 'blockers' => [], 'data' => null];
        }

        $this->record($actor, 'DispatchQueue', 'Queue Published', sprintf(
            'Official dispatch order published for %s (%d bus%s).',
            $date,
            count($rows),
            count($rows) === 1 ? '' : 'es'
        ), $date, null, ['published_at' => $now, 'count' => count($rows)]);

        return ['success' => true, 'error' => null, 'blockers' => [], 'data' => $this->queueData($date)];
    }

    /**
     * Move a queued bus to an explicit position (manual override). The date's
     * publication is invalidated until the queue is published again.
     *
     * @param array{id?:int,username?:string,role?:string} $actor
     * @return array{success:bool,error:?string,data:?array}
     */
    public function overridePosition(string $date, string $busId, int $position, string $reason, array $actor): array
    {
        $this->ensureSchema();
        $date = self::normalizeDate($date);
        if ($date === null) {
            return ['success' => false, 'error' => 'Select a valid operating date.', 'data' => null];
        }
        if (self::isHistoryDate($date)) {
            return ['success' => false, 'error' => self::HISTORY_READ_ONLY_ERROR, 'data' => null];
        }
        $busId = trim($busId);
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            return ['success' => false, 'error' => 'A reason for this override is required (at least 5 characters).', 'data' => null];
        }

        $rows = $this->queueRows($date);
        $order = [];
        $currentPosition = null;
        foreach ($rows as $row) {
            $order[] = (string)$row['bus_id'];
            if ((string)$row['bus_id'] === $busId) {
                $currentPosition = (int)$row['queue_position'];
            }
        }
        if ($currentPosition === null) {
            return ['success' => false, 'error' => 'Bus ' . $busId . ' is not part of this date\'s queue.', 'data' => null];
        }
        if ($this->isPublishedDate($date)) {
            return ['success' => false, 'error' => self::PUBLISHED_LOCK_ERROR, 'data' => null];
        }

        $position = max(1, min(count($order), $position));
        $order = array_values(array_filter($order, static fn (string $id): bool => $id !== $busId));
        array_splice($order, $position - 1, 0, [$busId]);

        $this->db->beginTransaction();
        try {
            $this->writeOrder($date, $order);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return ['success' => false, 'error' => 'The queue override could not be saved.', 'data' => null];
        }

        $this->record($actor, 'DispatchQueue', 'Queue Override', sprintf(
            'Queue override for %s: Bus %s moved from position #%d to #%d. Reason: %s',
            $date,
            $busId,
            $currentPosition,
            $position,
            $reason
        ), $date, ['position' => $currentPosition], ['bus_id' => $busId, 'position' => $position, 'reason' => $reason]);

        return ['success' => true, 'error' => null, 'data' => $this->queueData($date)];
    }

    /**
     * Assign (or move) the standing driver of a bus. Blocks unavailable
     * drivers, unavailable buses, and buses with an active trip on the date.
     *
     * @param array{id?:int,username?:string,role?:string} $actor
     * @return array{success:bool,error:?string,conflict_bus:?string,moved_from:?string,data:?array}
     */
    public function assignDriver(string $busId, string $date, string $driverId, bool $allowMove, array $actor): array
    {
        $this->ensureSchema();
        $busId = trim($busId);
        $driverId = trim($driverId);
        $date = self::normalizeDate($date) ?? date('Y-m-d');
        if (self::isHistoryDate($date)) {
            return ['success' => false, 'error' => self::HISTORY_READ_ONLY_ERROR, 'conflict_bus' => null, 'moved_from' => null, 'data' => null];
        }
        $published = $this->isPublishedDate($date);
        if ($published) {
            return ['success' => false, 'error' => self::PUBLISHED_LOCK_ERROR, 'conflict_bus' => null, 'moved_from' => null, 'data' => null];
        }
        $actorName = trim((string)($actor['username'] ?? ''));

        $buses = $this->busRows();
        $bus = $buses[$busId] ?? null;
        if ($bus === null) {
            return ['success' => false, 'error' => 'Select an existing bus.', 'conflict_bus' => null, 'moved_from' => null, 'data' => null];
        }
        $busEligibility = self::busEligibility($bus);
        if (!$busEligibility['eligible']) {
            return ['success' => false, 'error' => $busEligibility['reason'], 'conflict_bus' => null, 'moved_from' => null, 'data' => null];
        }
        $problems = $this->problemInfo([$busId]);
        if (isset($problems[$busId])) {
            return ['success' => false, 'error' => self::problemReason($problems[$busId]) . ' Resolve the report before changing its driver.', 'conflict_bus' => null, 'moved_from' => null, 'data' => null];
        }
        $tripInfo = $this->tripInfo($date, [$busId]);
        if (($tripInfo[$busId]['active_trip'] ?? null) !== null) {
            return ['success' => false, 'error' => 'Bus ' . $busId . ' has an active trip and its driver cannot be changed now.', 'conflict_bus' => null, 'moved_from' => null, 'data' => null];
        }

        $drivers = $this->driverMap();
        $driver = $drivers[$driverId] ?? ($drivers[strtolower($driverId)] ?? null);
        $driverEligibility = self::driverEligibility($driver);
        if (!$driverEligibility['eligible']) {
            return ['success' => false, 'error' => $driverEligibility['reason'], 'conflict_bus' => null, 'moved_from' => null, 'data' => null];
        }

        /* From here on the driver is identified by their real database id, so a
           display name or formatted driver code can never be written into the
           assignment records. */
        $canonicalDriverId = ($driver !== null && trim((string)($driver['driver_id'] ?? '')) !== '')
            ? trim((string)$driver['driver_id'])
            : $driverId;
        $driverId = $canonicalDriverId;
        $bindingRows = $this->driverBindingRows();

        /* A driver assigned to another terminal for the date cannot take a bus
           at this terminal: the driver must enter this terminal's queue first. */
        $driverTerminalId = (int)($this->driverTerminalMap($date, $bindingRows)[$canonicalDriverId] ?? 0);
        if ($driverTerminalId > 0) {
            $busTerminalId = $this->effectiveBusTerminalId($date, $busId, $tripInfo, $bus);
            if ($busTerminalId > 0 && $driverTerminalId !== $busTerminalId) {
                $terminalNames = $this->terminalMap();
                $driverTerminalName = (($terminalNames[$driverTerminalId]['name'] ?? '') !== '')
                    ? $terminalNames[$driverTerminalId]['name']
                    : ('Terminal #' . $driverTerminalId);
                return [
                    'success' => false,
                    'error' => 'Driver ' . $canonicalDriverId . ' is assigned to "' . $driverTerminalName . '" for this operating date, not to the bus\'s terminal. Assign the driver to this terminal first.',
                    'conflict_bus' => null,
                    'moved_from' => null,
                    'data' => null,
                ];
            }
        }

        /* A driver with an active assignment cannot take another bus: they stay
           unavailable until their trip completes and the arrival at the
           receiving terminal is confirmed. The page hides them; this enforces
           it for every request, including simultaneous ones. */
        $queuedBusIds = $this->queuedBusIdMap($date, $buses, $this->problemInfo(array_keys($buses)), $published);
        $occupancy = $this->occupiedDriverMap($date, $bindingRows, $queuedBusIds, $published);
        if (isset($occupancy[$canonicalDriverId]) && strcasecmp((string)$occupancy[$canonicalDriverId]['bus_id'], $busId) !== 0) {
            $assignedBus = (string)$occupancy[$canonicalDriverId]['bus_id'];

            return [
                'success' => false,
                'error' => 'Driver ' . $canonicalDriverId . ' is assigned to bus ' . $assignedBus . ' and stays unavailable until that trip completes and its arrival at the receiving terminal is confirmed.',
                'conflict_bus' => null,
                'moved_from' => null,
                'data' => null,
            ];
        }

        $assignmentsBefore = $this->assignmentMaps();
        $previousDriver = trim((string)($assignmentsBefore['byBus'][$busId] ?? ''));
        if ($previousDriver === '') {
            $legacy = trim((string)($bus['driver'] ?? ''));
            if ($legacy !== '' && strcasecmp($legacy, 'Unassigned') !== 0 && strcasecmp($legacy, 'TBD') !== 0) {
                $previousDriver = $legacy;
            }
        }

        $assignments = new BusAssignmentService($this->db);
        $result = $assignments->assign($busId, $driverId, $allowMove, $actorName);
        if (($result['success'] ?? false) !== true) {
            return [
                'success' => false,
                'error' => (string)($result['error'] ?? 'The driver assignment could not be saved.'),
                'conflict_bus' => isset($result['conflict_bus']) ? (string)$result['conflict_bus'] : null,
                'moved_from' => null,
                'data' => null,
            ];
        }

        $this->record(
            $actor,
            'Bus',
            $previousDriver === '' ? 'Driver Assigned' : 'Driver Reassigned',
            sprintf(
                'Dispatch queue: driver %s %s to bus %s for %s.%s',
                $driverId,
                $previousDriver === '' ? 'assigned' : 'reassigned',
                $busId,
                $date,
                isset($result['moved_from']) && $result['moved_from'] !== null ? ' Moved from bus ' . $result['moved_from'] . '.' : ''
            ),
            $busId,
            ['driver' => $previousDriver],
            ['driver_id' => $driverId, 'moved_from' => $result['moved_from'] ?? null, 'operating_date' => $date]
        );

        return [
            'success' => true,
            'error' => null,
            'conflict_bus' => null,
            'moved_from' => isset($result['moved_from']) ? $result['moved_from'] : null,
            'data' => $this->queueData($date),
        ];
    }

    /**
     * Assign a bus - and optionally a driver - to a terminal for an operating
     * date. The bus assignment is the queue entry: the bus appears in that
     * terminal's queue and may only be dispatched from there. A bus or driver
     * already on an active trip keeps its origin terminal until the arrival is
     * confirmed. Writes only the per-date queue/assignment records - it never
     * creates or changes bus_trips or completed dispatch history.
     *
     * @param array{id?:int,username?:string,role?:string} $actor
     * @param int|null $driverTerminalId optional independent driver terminal;
     *        when omitted the driver is assigned to the bus's terminal
     * @return array{success:bool,error:?string,data:?array}
     */
    public function assignTerminal(string $date, string $busId, int $terminalId, ?string $driverId, array $actor, ?int $driverTerminalId = null): array
    {
        $this->ensureSchema();
        $date = self::normalizeDate($date);
        if ($date === null) {
            return ['success' => false, 'error' => 'Select a valid operating date.', 'data' => null];
        }
        if (self::isHistoryDate($date)) {
            return ['success' => false, 'error' => self::HISTORY_READ_ONLY_ERROR, 'data' => null];
        }
        $busId = trim($busId);
        if (!self::busIdIsValid($busId)) {
            return ['success' => false, 'error' => 'Select a valid bus.', 'data' => null];
        }
        $terminalCheck = $this->terminalEligibility($terminalId);
        if (!$terminalCheck['valid']) {
            return ['success' => false, 'error' => $terminalCheck['reason'], 'data' => null];
        }
        if ($this->isPublishedDate($date)) {
            return ['success' => false, 'error' => self::PUBLISHED_LOCK_ERROR, 'data' => null];
        }

        $buses = $this->busRows();
        $bus = $buses[$busId] ?? null;
        if ($bus === null) {
            return ['success' => false, 'error' => 'Bus ' . $busId . ' does not exist or is not dispatchable.', 'data' => null];
        }
        $busEligibility = self::busEligibility($bus);
        if (!$busEligibility['eligible']) {
            return ['success' => false, 'error' => $busEligibility['reason'], 'data' => null];
        }
        $problems = $this->problemInfo([$busId]);
        if (isset($problems[$busId])) {
            return ['success' => false, 'error' => self::problemReason($problems[$busId]), 'data' => null];
        }
        $tripInfo = $this->tripInfo($date, [$busId]);
        if (($tripInfo[$busId]['active_trip'] ?? null) !== null) {
            return ['success' => false, 'error' => 'Bus ' . $busId . ' has an active trip; its terminal cannot be changed until the arrival is confirmed.', 'data' => null];
        }

        $driverId = $driverId !== null ? trim($driverId) : '';
        $resolvedDriverTerminalId = $driverTerminalId !== null && $driverTerminalId > 0 ? $driverTerminalId : $terminalId;
        if ($driverId !== '') {
            $driverTerminalCheck = $this->terminalEligibility($resolvedDriverTerminalId);
            if (!$driverTerminalCheck['valid']) {
                return ['success' => false, 'error' => 'Driver terminal: ' . $driverTerminalCheck['reason'], 'data' => null];
            }
            $drivers = $this->driverMap();
            $driver = $drivers[$driverId] ?? ($drivers[strtolower($driverId)] ?? null);
            $driverEligibility = self::driverEligibility($driver);
            if (!$driverEligibility['eligible']) {
                return ['success' => false, 'error' => $driverEligibility['reason'], 'data' => null];
            }
            if ($driver !== null && !empty($driver['driver_id'])) {
                $driverId = (string)$driver['driver_id'];
            }
            if ($this->driverHasActiveTrip($date, $driverId)) {
                return ['success' => false, 'error' => 'Driver ' . $driverId . ' has an active trip; their terminal cannot be changed until the arrival is confirmed.', 'data' => null];
            }
        }

        $terminalName = $this->terminalMap()[$terminalId]['name'] ?? ('Terminal #' . $terminalId);
        $previous = [
            'bus_terminal_id' => $this->queueTerminalId($date, $busId),
            'driver_terminal_id' => $driverId !== '' ? ($this->driverTerminalAssignments($date)[$driverId] ?? null) : null,
        ];

        $this->db->beginTransaction();
        try {
            $this->upsertBusTerminalAssignment($date, $busId, $terminalId);
            if ($driverId !== '') {
                $this->upsertDriverTerminalAssignment($date, $driverId, $resolvedDriverTerminalId);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return ['success' => false, 'error' => 'The terminal assignment could not be saved.', 'data' => null];
        }

        $this->record($actor, 'DispatchQueue', 'Terminal Assigned', sprintf(
            'Dispatch queue: bus %s%s assigned to terminal "%s" for %s.',
            $busId,
            $driverId !== '' ? ' and driver ' . $driverId : '',
            $terminalName,
            $date
        ), $date, $previous, [
            'bus_id' => $busId,
            'terminal_id' => $terminalId,
            'driver_id' => $driverId !== '' ? $driverId : null,
            'driver_terminal_id' => $driverId !== '' ? $resolvedDriverTerminalId : null,
        ]);

        return ['success' => true, 'error' => null, 'data' => $this->queueData($date)];
    }

    /**
     * Mark a bus available / under maintenance / out of service from the
     * dispatch queue page, with a recorded reason. This is the admin action
     * that removes a bus from the queue until it is available again; open
     * driver problem reports stay the dispatcher's to resolve.
     *
     * @param array{id?:int,username?:string,role?:string} $actor
     * @return array{success:bool,error:?string,unchanged:bool,data:?array}
     */
    public function setOperationalStatus(string $busId, string $status, string $reason, string $date, array $actor): array
    {
        $this->ensureSchema();
        $busId = trim($busId);
        $status = trim($status);
        $reason = trim($reason);
        $date = self::normalizeDate($date) ?? date('Y-m-d');
        if (self::isHistoryDate($date)) {
            return ['success' => false, 'error' => self::HISTORY_READ_ONLY_ERROR, 'unchanged' => false, 'data' => null];
        }
        if ($this->isPublishedDate($date)) {
            return ['success' => false, 'error' => self::PUBLISHED_LOCK_ERROR, 'unchanged' => false, 'data' => null];
        }

        if (!self::busIdIsValid($busId)) {
            return ['success' => false, 'error' => 'Select a valid bus.', 'unchanged' => false, 'data' => null];
        }
        if (!in_array($status, self::ADMIN_SETTABLE_OPERATIONAL, true)) {
            return ['success' => false, 'error' => 'Select a valid availability status.', 'unchanged' => false, 'data' => null];
        }
        if (mb_strlen($reason) < 5) {
            return ['success' => false, 'error' => 'A reason for this change is required (at least 5 characters).', 'unchanged' => false, 'data' => null];
        }

        try {
            BusFields::ensureColumns($this->db);
            $stmt = $this->db->prepare('SELECT bus_id, status, operational_status FROM buses WHERE bus_id = ?');
            $stmt->execute([$busId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'The bus availability could not be changed.', 'unchanged' => false, 'data' => null];
        }
        if (!is_array($row)) {
            return ['success' => false, 'error' => 'Bus ' . $busId . ' does not exist.', 'unchanged' => false, 'data' => null];
        }

        $previous = trim((string)($row['operational_status'] ?? ''));
        if (strcasecmp($previous, $status) === 0) {
            return ['success' => true, 'error' => null, 'unchanged' => true, 'data' => $this->queueData($date)];
        }

        $this->db->beginTransaction();
        try {
            $update = $this->db->prepare('UPDATE buses SET operational_status = ? WHERE bus_id = ?');
            $update->execute([$status, $busId]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return ['success' => false, 'error' => 'The bus availability could not be changed.', 'unchanged' => false, 'data' => null];
        }

        $action = $status === 'Available' ? 'Marked Available' : 'Marked Unavailable';
        $this->record(
            $actor,
            'Bus',
            $action,
            sprintf(
                'Dispatch queue: bus %s marked "%s" for %s. Reason: %s',
                $busId,
                $status,
                $date,
                $reason
            ),
            $busId,
            ['operational_status' => $previous],
            ['operational_status' => $status, 'reason' => $reason, 'operating_date' => $date]
        );

        return ['success' => true, 'error' => null, 'unchanged' => false, 'data' => $this->queueData($date)];
    }

    /**
     * Queue history for one operating date: the audit activity recorded by
     * this page (updates, publications, overrides - and driver assignments are
     * on each bus's own history) plus the most recently published orders.
     *
     * @return array{operating_date:string,activity:array<int,array<string,mixed>>,published_dates:array<int,array<string,mixed>>}
     */
    public function history(string $date, int $limit = 25): array
    {
        $this->ensureSchema();
        $date = self::normalizeDate($date) ?? date('Y-m-d');
        $limit = max(1, min(50, $limit));

        $activity = [];
        try {
            $stmt = $this->db->prepare(
                'SELECT action, description, username, created_at FROM audit_trail'
                . ' WHERE record_id = ? AND module = ?'
                . ' ORDER BY created_at DESC, id DESC LIMIT ' . $limit
            );
            $stmt->execute([$date, 'DispatchQueue']);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $rows = [];
        }
        foreach ($rows as $row) {
            $activity[] = [
                'action' => (string)($row['action'] ?? ''),
                'description' => (string)($row['description'] ?? ''),
                'username' => (string)($row['username'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
            ];
        }

        $publishedDates = [];
        try {
            $stmt = $this->db->prepare(
                'SELECT operating_date, MAX(published_at) AS published_at, MAX(published_by) AS published_by, COUNT(*) AS buses'
                . ' FROM ' . self::TABLE
                . ' WHERE published_at IS NOT NULL'
                . ' GROUP BY operating_date ORDER BY operating_date DESC LIMIT 10'
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $rows = [];
        }
        foreach ($rows as $row) {
            $publishedDates[] = [
                'operating_date' => (string)($row['operating_date'] ?? ''),
                'published_at' => (string)($row['published_at'] ?? ''),
                'published_by' => (string)($row['published_by'] ?? ''),
                'buses' => (int)($row['buses'] ?? 0),
            ];
        }

        return [
            'operating_date' => $date,
            'activity' => $activity,
            'published_dates' => $publishedDates,
        ];
    }

    /* -------------------------------- internals -------------------------------- */

    /**
     * Replace the saved order for the date with the given bus list.
     *
     * Per-date terminal assignments survive a reorder: each bus keeps the
     * terminal it already entered, and a newly queued bus starts without an
     * assignment until it enters a terminal queue.
     *
     * @param string[] $order
     */
    private function writeOrder(string $date, array $order): void
    {
        $now = date('Y-m-d H:i:s');
        $hasTerminal = $this->hasColumn(self::TABLE, 'terminal_id');
        $priorTerminals = [];
        if ($hasTerminal) {
            foreach ($this->queueRows($date) as $row) {
                $priorTerminals[(string)$row['bus_id']] = self::toIntOrNull($row['terminal_id'] ?? null);
            }
        }

        $stmt = $this->db->prepare('DELETE FROM ' . self::TABLE . ' WHERE operating_date = ?');
        $stmt->execute([$date]);

        if ($order === []) {
            return;
        }

        $insert = $hasTerminal
            ? $this->db->prepare(
                'INSERT INTO ' . self::TABLE
                . ' (operating_date, bus_id, queue_position, published_at, published_by, created_at, updated_at, terminal_id)'
                . ' VALUES (?, ?, ?, NULL, NULL, ?, ?, ?)'
            )
            : $this->db->prepare(
                'INSERT INTO ' . self::TABLE
                . ' (operating_date, bus_id, queue_position, published_at, published_by, created_at, updated_at)'
                . ' VALUES (?, ?, ?, NULL, NULL, ?, ?)'
            );
        $position = 1;
        foreach ($order as $busId) {
            if ($hasTerminal) {
                $insert->execute([$date, $busId, $position++, $now, $now, $priorTerminals[$busId] ?? null]);
            } else {
                $insert->execute([$date, $busId, $position++, $now, $now]);
            }
        }
    }

    /**
     * Whether a terminal id refers to a configured terminal. Installations
     * without a terminals directory cannot accept assignments.
     *
     * @return array{valid:bool,reason:string}
     */
    private function terminalEligibility(int $terminalId): array
    {
        $terminals = $this->terminalMap();
        if ($terminals === []) {
            return ['valid' => false, 'reason' => 'Terminals are not configured for this installation.'];
        }
        if ($terminalId <= 0 || !isset($terminals[$terminalId])) {
            return ['valid' => false, 'reason' => 'Select a valid terminal.'];
        }

        return ['valid' => true, 'reason' => ''];
    }

    /**
     * Per-operating-date driver terminal assignments (driver_id => terminal_id).
     *
     * @return array<string,int>
     */
    private function driverTerminalAssignments(string $date): array
    {
        $map = [];
        if (!$this->tableExists(self::DRIVER_TERMINAL_TABLE)) {
            return $map;
        }
        try {
            $stmt = $this->db->prepare('SELECT driver_id, terminal_id FROM ' . self::DRIVER_TERMINAL_TABLE . ' WHERE operating_date = ?');
            $stmt->execute([$date]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return $map;
        }
        foreach ($rows as $row) {
            $driverId = trim((string)($row['driver_id'] ?? ''));
            $terminalId = self::toIntOrNull($row['terminal_id'] ?? null);
            if ($driverId !== '' && $terminalId !== null && $terminalId > 0) {
                $map[$driverId] = $terminalId;
            }
        }

        return $map;
    }

    /**
     * The terminal a driver is currently at for the operating date: the
     * per-date terminal assignment when one exists, otherwise the arrival
     * terminal of the driver's most recent completed trip today (the confirmed
     * arrival record), so a released driver is offered again only at the
     * receiving terminal where their bus arrived.
     *
     * @param array<string,array<int,array{bus_id:string,at:?string}>> $bindingRows
     * @return array<string,int> driver_id => terminal_id
     */
    private function driverTerminalMap(string $date, array $bindingRows): array
    {
        $map = $this->driverTerminalAssignments($date);
        if (!$this->tableExists('bus_trips')
            || !$this->hasColumn('bus_trips', 'driver_id')
            || !$this->hasColumn('bus_trips', 'to_terminal_id')) {
            return $map;
        }
        $driverIds = [];
        foreach (array_keys($bindingRows) as $driverId) {
            $driverId = trim((string)$driverId);
            if ($driverId !== '' && !isset($map[$driverId])) {
                $driverIds[] = $driverId;
            }
        }
        if ($driverIds === []) {
            return $map;
        }
        try {
            $placeholders = implode(',', array_fill(0, count($driverIds), '?'));
            $sql = 'SELECT driver_id, to_terminal_id, id FROM bus_trips WHERE status = ? AND driver_id IN (' . $placeholders . ')';
            $params = array_merge(['Completed'], $driverIds);
            if ($this->hasColumn('bus_trips', 'trip_date')) {
                $sql .= ' AND trip_date = ?';
                $params[] = $date;
            }
            $sql .= ' ORDER BY id ASC';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return $map;
        }
        foreach ($rows as $row) {
            $driverId = trim((string)($row['driver_id'] ?? ''));
            $terminalId = self::toIntOrNull($row['to_terminal_id'] ?? null);
            if ($driverId !== '' && $terminalId !== null && $terminalId > 0) {
                // Ordered by id: the latest completed trip's arrival wins.
                $map[$driverId] = $terminalId;
            }
        }

        return $map;
    }

    /** Whether the driver already has an active trip on the operating date. */
    private function driverHasActiveTrip(string $date, string $driverId): bool
    {
        if ($driverId === '' || !$this->tableExists('bus_trips') || !$this->hasColumn('bus_trips', 'driver_id')) {
            return false;
        }
        try {
            if ($this->hasColumn('bus_trips', 'trip_date')) {
                $stmt = $this->db->prepare(
                    'SELECT id FROM bus_trips WHERE driver_id = ? AND trip_date = ? AND status IN (?, ?, ?) LIMIT 1'
                );
                $stmt->execute(array_merge([$driverId, $date], self::ACTIVE_TRIP_STATUSES));
            } else {
                $stmt = $this->db->prepare(
                    'SELECT id FROM bus_trips WHERE driver_id = ? AND status IN (?, ?, ?) LIMIT 1'
                );
                $stmt->execute(array_merge([$driverId], self::ACTIVE_TRIP_STATUSES));
            }

            return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Bus ids that form the operating date's dispatch queue: the persisted
     * rows for the date plus, while the date is still a draft, every eligible
     * fleet bus the page auto-lists.
     *
     * @param array<string,array<string,mixed>> $buses
     * @param array<string,array<string,mixed>> $problems
     * @return array<string,bool>
     */
    private function queuedBusIdMap(string $date, array $buses, array $problems, bool $published): array
    {
        $ids = [];
        foreach ($this->queueRows($date) as $row) {
            $busId = trim((string)($row['bus_id'] ?? ''));
            if ($busId === '') {
                continue;
            }
            if (!$published) {
                $bus = $buses[$busId] ?? null;
                if ($bus === null || isset($problems[$busId]) || !self::busEligibility($bus)['eligible']) {
                    // Draft mode lists an unavailable persisted bus outside the queue.
                    continue;
                }
            }
            $ids[$busId] = true;
        }
        if (!$published) {
            foreach ($buses as $busId => $bus) {
                if (!isset($problems[$busId]) && self::busEligibility($bus)['eligible']) {
                    $ids[(string)$busId] = true;
                }
            }
        }

        return $ids;
    }

    /**
     * Drivers with an active assignment for the operating date: on an active
     * trip today, or assigned to a queue bus during the operating day (the
     * binding was written today) without a completed trip today driven by
     * them. Bindings carried over from earlier days are not today's
     * assignments and do not hide the driver, so every day starts with the
     * eligible drivers available. A completed trip with a confirmed arrival
     * releases the driver again, at the receiving terminal.
     *
     * @param array<string,array<int,array{bus_id:string,at:?string}>> $bindingRows driver_id => bindings
     * @param array<string,bool> $queuedBusIds
     * @param bool $published when the operating date's queue is published,
     *        its assigned drivers stay unavailable regardless of when the
     *        binding was written
     * @return array<string,array{bus_id:?string,reason:string}>
     */
    private function occupiedDriverMap(string $date, array $bindingRows, array $queuedBusIds, bool $published = false): array
    {
        $occupied = [];
        if (!$this->tableExists('bus_trips')
            || !$this->hasColumn('bus_trips', 'driver_id')
            || !$this->hasColumn('bus_trips', 'bus_id')) {
            return $occupied;
        }

        $activeByDriver = [];
        $completedBusesByDriver = [];
        try {
            $sql = "SELECT driver_id, bus_id, status FROM bus_trips WHERE driver_id IS NOT NULL AND driver_id <> ''";
            $params = [];
            if ($this->hasColumn('bus_trips', 'trip_date')) {
                $sql .= ' AND trip_date = ?';
                $params[] = $date;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return $occupied;
        }

        foreach ($rows as $row) {
            $driverId = trim((string)($row['driver_id'] ?? ''));
            $busId = trim((string)($row['bus_id'] ?? ''));
            $status = trim((string)($row['status'] ?? ''));
            if ($driverId === '' || $busId === '') {
                continue;
            }
            if (in_array($status, self::ACTIVE_TRIP_STATUSES, true)) {
                $activeByDriver[$driverId] = $busId;
            } elseif (in_array($status, self::COMPLETED_TRIP_STATUSES, true)) {
                $completedBusesByDriver[$driverId][$busId] = true;
            }
        }

        foreach ($activeByDriver as $driverId => $busId) {
            $occupied[$driverId] = [
                'bus_id' => $busId,
                'reason' => 'Driver is on an active trip today.',
            ];
        }

        foreach ($bindingRows as $driverId => $bindings) {
            $driverId = trim((string)$driverId);
            if ($driverId === '' || isset($occupied[$driverId])) {
                continue;
            }
            foreach ((array)$bindings as $binding) {
                $boundBusId = trim((string)($binding['bus_id'] ?? ''));
                if ($boundBusId === '' || !isset($queuedBusIds[$boundBusId])) {
                    continue;
                }
                if (isset($completedBusesByDriver[$driverId][$boundBusId])) {
                    continue;
                }
                if (!self::assignmentIsCurrent($binding['at'] ?? null, $date) && !$published) {
                    continue;
                }
                $occupied[$driverId] = [
                    'bus_id' => $boundBusId,
                    'reason' => 'Driver was assigned to bus ' . $boundBusId . ' for this operating date and stays unavailable until that trip completes and the arrival at the receiving terminal is confirmed.',
                ];
                break;
            }
        }

        return $occupied;
    }

    /**
     * Assignment rows per driver with their timestamps, ordered by id so the
     * earliest binding is examined first. The timestamp column is
     * schema-adaptive (created_at/assigned_at); without one every binding
     * counts as current.
     *
     * @return array<string,array<int,array{bus_id:string,at:?string}>>
     */
    private function driverBindingRows(): array
    {
        $map = [];
        try {
            $stmt = $this->db->query('SELECT * FROM driver_bus_assignment ORDER BY id ASC');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return $map;
        }
        foreach ($rows as $row) {
            $driverId = trim((string)($row['driver_id'] ?? ''));
            $busId = trim((string)($row['bus_id'] ?? ''));
            if ($driverId === '' || $busId === '') {
                continue;
            }
            $assignedAt = null;
            foreach (['created_at', 'assigned_at'] as $column) {
                if (array_key_exists($column, $row) && $row[$column] !== null && trim((string)$row[$column]) !== '') {
                    $assignedAt = trim((string)$row[$column]);
                    break;
                }
            }
            $map[$driverId][] = ['bus_id' => $busId, 'at' => $assignedAt];
        }

        return $map;
    }

    /**
     * Whether a binding is an assignment for the viewed operating date:
     * written on that date or later, or (for a future operating date) already
     * written today, so pre-assignments made in advance still hide the
     * driver. Bindings carried over from earlier days do not. Unknown
     * timestamps count as current so the stricter behavior is kept when the
     * schema cannot say when the assignment was made.
     */
    private static function assignmentIsCurrent(?string $assignedAt, string $date): bool
    {
        if ($assignedAt === null || trim($assignedAt) === '') {
            return true;
        }
        $normalized = self::normalizeDate(substr(trim($assignedAt), 0, 10));
        if ($normalized === null) {
            return true;
        }
        $today = date('Y-m-d');
        $threshold = $date < $today ? $date : $today;

        return $normalized >= $threshold;
    }

    /** The bus's saved terminal assignment for the date, if any. */
    private function queueTerminalId(string $date, string $busId): ?int
    {
        if (!$this->hasColumn(self::TABLE, 'terminal_id')) {
            return null;
        }
        foreach ($this->queueRows($date) as $row) {
            if ((string)$row['bus_id'] === $busId) {
                return self::toIntOrNull($row['terminal_id'] ?? null);
            }
        }

        return null;
    }

    /**
     * Where the bus actually is for the operating date: the active trip's
     * origin, otherwise the latest confirmed arrival today, otherwise its
     * operating-date terminal assignment, otherwise its configured origin
     * (prior-date arrivals are persisted on the bus record by the dispatcher
     * arrival synchronization).
     */
    private function effectiveBusTerminalId(string $date, string $busId, array $tripInfo, ?array $bus = null): int
    {
        $trip = $tripInfo[$busId] ?? ['active_trip' => null, 'latest_completed' => null];
        $active = $trip['active_trip'] ?? null;
        if ($active !== null) {
            $from = self::toIntOrNull($active['from_terminal_id'] ?? null);
            if ($from !== null && $from > 0) {
                return $from;
            }
        }
        $completed = $trip['latest_completed'] ?? null;
        if ($completed !== null) {
            $to = self::toIntOrNull($completed['to_terminal_id'] ?? null);
            if ($to !== null && $to > 0) {
                return $to;
            }
        }
        $assigned = $this->queueTerminalId($date, $busId);
        if ($assigned !== null && $assigned > 0) {
            return $assigned;
        }
        if ($bus === null) {
            $buses = $this->busRows();
            $bus = $buses[$busId] ?? null;
        }

        return (int)self::toIntOrNull($bus['from_terminal_id'] ?? null);
    }

    /**
     * The route's configured receiving terminal for the direction: the
     * counterpart of the current terminal within the bus's configured pair.
     */
    private static function receivingTerminalId(int $currentId, ?int $configuredOriginId, ?int $configuredDestinationId): int
    {
        if ($currentId > 0) {
            if ($configuredOriginId === $currentId
                && $configuredDestinationId !== null && $configuredDestinationId > 0 && $configuredDestinationId !== $currentId) {
                return $configuredDestinationId;
            }
            if ($configuredDestinationId === $currentId
                && $configuredOriginId !== null && $configuredOriginId > 0 && $configuredOriginId !== $currentId) {
                return $configuredOriginId;
            }
        }
        if ($configuredDestinationId !== null && $configuredDestinationId > 0 && $configuredDestinationId !== $currentId) {
            return $configuredDestinationId;
        }

        return 0;
    }

    /**
     * First word of a terminal name, matching the dispatcher's default-route
     * naming (Rizal Central Terminal -> "Rizal").
     */
    private static function shortTerminalName(string $name): string
    {
        $parts = explode(' ', trim($name));

        return trim((string)($parts[0] ?? ''));
    }

    /**
     * Route name worth displaying: '' for the TBD/Unassigned placeholders and
     * for id-pair garbage such as "2 - 1"; anything else passes through.
     */
    private static function sanitizeRouteName(?string $route): string
    {
        $route = trim((string)$route);
        if ($route === '' || strcasecmp($route, 'TBD') === 0 || strcasecmp($route, 'Unassigned') === 0) {
            return '';
        }
        if (preg_match('/^\d+\s*-\s*\d+$/', $route)) {
            return '';   // terminal ids, not a route name
        }

        return $route;
    }

    /**
     * The opposite terminal of the configured Rizal <-> Cabanatuan pair, by
     * terminal name (the dispatcher's pairing rule). 0 when either side of
     * the pair is missing or the terminal is not part of it.
     */
    private function oppositeTerminalFor(int $terminalId): int
    {
        if ($terminalId <= 0) {
            return 0;
        }

        $rizal = 0;
        $cabanatuan = 0;
        foreach ($this->terminalMap() as $id => $terminal) {
            $name = strtolower(trim((string)($terminal['name'] ?? '')));
            if ($name === '') {
                continue;
            }
            if ($rizal === 0 && (strpos($name, 'rizal') !== false || strpos($name, 'poblacion') !== false)) {
                $rizal = (int)$id;
            }
            if ($cabanatuan === 0 && strpos($name, 'cabanatuan') !== false) {
                $cabanatuan = (int)$id;
            }
        }

        if ($rizal > 0 && $cabanatuan > 0) {
            if ($terminalId === $rizal) {
                return $cabanatuan;
            }
            if ($terminalId === $cabanatuan) {
                return $rizal;
            }
        }

        return 0;
    }

    /**
     * A route actually recorded for the from -> to direction: the latest trip
     * on that direction first, then any trip on the pair. Read-only - nothing
     * is written; '' when the direction has no recorded route.
     */
    private function recordedRouteForDirection(int $fromTerminalId, int $toTerminalId): string
    {
        if ($fromTerminalId <= 0 || $toTerminalId <= 0 || $fromTerminalId === $toTerminalId || !$this->tableExists('bus_trips')) {
            return '';
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT route_name FROM bus_trips
                 WHERE from_terminal_id = ? AND to_terminal_id = ?
                   AND route_name IS NOT NULL AND TRIM(route_name) <> ''
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$fromTerminalId, $toTerminalId]);

            return self::sanitizeRouteName((string)$stmt->fetchColumn());
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * Publishing is the moment the date's routing becomes official: every
     * queued bus carries a terminal (an unassigned bus defaults to its
     * configured home terminal when that terminal is valid), and the driver
     * bound to a bus follows the bus's terminal for the operating date.
     * Route names are never written to bus records here - the dispatcher
     * chooses the route when dispatching.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array{error:?string,bus_defaults:int,driver_syncs:int}
     */
    private function syncTerminalsAtPublish(string $date, array $rows): array
    {
        $result = ['error' => null, 'bus_defaults' => 0, 'driver_syncs' => 0];
        $buses = $this->busRows();
        $assignments = $this->assignmentMaps();
        $driverTerminals = $this->driverTerminalAssignments($date);

        $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $busId = trim((string)($row['bus_id'] ?? ''));
                if ($busId === '') {
                    continue;
                }

                $terminalId = (int)($row['terminal_id'] ?? 0);
                if ($terminalId <= 0) {
                    $bus = $buses[$busId] ?? null;
                    $configured = $bus !== null ? (int)($bus['from_terminal_id'] ?? 0) : 0;
                    if ($configured > 0 && $this->terminalEligibility($configured)['valid']) {
                        $this->upsertBusTerminalAssignment($date, $busId, $configured);
                        $terminalId = $configured;
                        $result['bus_defaults']++;
                    }
                }
                if ($terminalId <= 0) {
                    continue;   // still unassigned: the dispatcher keeps blocking it
                }

                $driverId = (string)($assignments['byBus'][$busId] ?? '');
                if ($driverId === '') {
                    continue;
                }
                if ((int)($driverTerminals[$driverId] ?? 0) === $terminalId) {
                    continue;
                }

                $this->upsertDriverTerminalAssignment($date, $driverId, $terminalId);
                $driverTerminals[$driverId] = $terminalId;
                $result['driver_syncs']++;
            }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Dispatch publication terminal sync failed: ' . $e->getMessage());

            return ['error' => 'The terminal assignments could not be synchronized for publishing.', 'bus_defaults' => 0, 'driver_syncs' => 0];
        }

        return $result;
    }

    /** Store the bus's terminal assignment, entering the terminal queue when needed. */
    private function upsertBusTerminalAssignment(string $date, string $busId, int $terminalId): void
    {
        if (!$this->hasColumn(self::TABLE, 'terminal_id')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $update = $this->db->prepare(
            'UPDATE ' . self::TABLE . ' SET terminal_id = ?, updated_at = ? WHERE operating_date = ? AND bus_id = ?'
        );
        $update->execute([$terminalId, $now, $date, $busId]);
        if ($update->rowCount() > 0) {
            return;
        }
        $check = $this->db->prepare('SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE operating_date = ? AND bus_id = ?');
        $check->execute([$date, $busId]);
        if ((int)$check->fetchColumn() > 0) {
            return;
        }

        /* Entering the terminal queue for the first time: join at the end of
           the date-wide master order without disturbing saved positions. */
        $positionStmt = $this->db->prepare('SELECT COALESCE(MAX(queue_position), 0) + 1 FROM ' . self::TABLE . ' WHERE operating_date = ?');
        $positionStmt->execute([$date]);
        $position = max(1, (int)$positionStmt->fetchColumn());
        $insert = $this->db->prepare(
            'INSERT INTO ' . self::TABLE
            . ' (operating_date, bus_id, queue_position, published_at, published_by, created_at, updated_at, terminal_id)'
            . ' VALUES (?, ?, ?, NULL, NULL, ?, ?, ?)'
        );
        $insert->execute([$date, $busId, $position, $now, $now, $terminalId]);
    }

    /** Store the driver's operating-date terminal assignment. */
    private function upsertDriverTerminalAssignment(string $date, string $driverId, int $terminalId): void
    {
        if (!$this->tableExists(self::DRIVER_TERMINAL_TABLE)) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $update = $this->db->prepare(
            'UPDATE ' . self::DRIVER_TERMINAL_TABLE . ' SET terminal_id = ?, updated_at = ? WHERE operating_date = ? AND driver_id = ?'
        );
        $update->execute([$terminalId, $now, $date, $driverId]);
        if ($update->rowCount() > 0) {
            return;
        }
        $check = $this->db->prepare('SELECT COUNT(*) FROM ' . self::DRIVER_TERMINAL_TABLE . ' WHERE operating_date = ? AND driver_id = ?');
        $check->execute([$date, $driverId]);
        if ((int)$check->fetchColumn() > 0) {
            return;
        }
        $insert = $this->db->prepare(
            'INSERT INTO ' . self::DRIVER_TERMINAL_TABLE . ' (operating_date, driver_id, terminal_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?)'
        );
        $insert->execute([$date, $driverId, $terminalId, $now, $now]);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,mixed> $actor
     * @param array<string,mixed>|null $previousValues
     * @param array<string,mixed>|null $newValues
     */
    private function record(array $actor, string $module, string $action, string $description, ?string $recordId, ?array $previousValues, ?array $newValues): void
    {
        try {
            $this->audit->log(
                $module,
                $action,
                $description,
                isset($actor['id']) ? (int)$actor['id'] : null,
                trim((string)($actor['username'] ?? '')) ?: null,
                trim((string)($actor['role'] ?? '')) ?: null,
                $recordId,
                $previousValues,
                $newValues,
                'Success',
                $this->db
            );
        } catch (Throwable $e) {
            // Audit must never break the operation.
        }
    }

    /**
     * Rows for a date, position-ordered.
     *
     * @return array<int,array<string,mixed>>
     */
    private function queueRows(string $date): array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM ' . self::TABLE . ' WHERE operating_date = ? ORDER BY queue_position ASC');
            $stmt->execute([$date]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }

        return $rows;
    }

    /**
     * @return array<string,array<string,mixed>> bus_id => row
     */
    private function busRows(): array
    {
        $buses = [];
        try {
            $rows = $this->db->query('SELECT * FROM buses')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
        foreach ($rows as $row) {
            $busId = trim((string)($row['bus_id'] ?? ''));
            if ($busId === '' || !self::busIdIsValid($busId)) {
                continue;
            }
            $status = trim((string)($row['status'] ?? ''));
            if ($status !== '' && in_array(strtolower($status), ['archived', 'inactive'], true)) {
                continue;
            }
            $buses[$busId] = $row;
        }

        return $buses;
    }

    /**
     * Buses without a saved position follow the dispatcher's legacy order:
     * leading schedule time first (unparseable last), then bus id.
     *
     * @param array<string,mixed> $item
     * @return array{0:int,1:string}
     */
    private function scheduleSortKey(array $item): array
    {
        $minutes = PHP_INT_MAX;
        if (preg_match('/^\s*(\d{1,2}):(\d{2})/', (string)($item['schedule'] ?? ''), $match) === 1) {
            $hours = (int)$match[1];
            $mins = (int)$match[2];
            if ($hours >= 0 && $hours <= 23 && $mins >= 0 && $mins <= 59) {
                $minutes = ($hours * 60) + $mins;
            }
        }

        return [$minutes, strtoupper((string)($item['bus_id'] ?? ''))];
    }

    /**
     * Canonical assignment maps built from the existing dispatch assignment
     * records: each driver is bound to at most one bus and each bus to at most
     * one driver (the earliest binding row wins), so legacy duplicate rows can
     * never display a driver on several queues at once.
     *
     * @return array{byBus:array<string,string>,byDriver:array<string,string[]>}
     */
    private function assignmentMaps(): array
    {
        $byBus = [];
        $byDriver = [];
        try {
            $rows = $this->db->query('SELECT bus_id, driver_id FROM driver_bus_assignment ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $rows = [];
        }
        $busTaken = [];
        foreach ($rows as $row) {
            $busId = trim((string)($row['bus_id'] ?? ''));
            $driverId = trim((string)($row['driver_id'] ?? ''));
            if ($busId === '' || $driverId === '') {
                continue;
            }
            if (isset($busTaken[$busId]) || isset($byDriver[$driverId])) {
                continue;
            }
            $byBus[$busId] = $driverId;
            $byDriver[$driverId] = [$busId];
            $busTaken[$busId] = true;
        }

        return ['byBus' => $byBus, 'byDriver' => $byDriver];
    }

    /**
     * @return array<string,array<string,mixed>> driver_id => row
     */
    private function driverMap(): array
    {
        $drivers = [];
        try {
            $rows = $this->db->query('SELECT * FROM drivers')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
        foreach ($rows as $row) {
            $driverId = trim((string)($row['driver_id'] ?? ''));
            if ($driverId !== '' && !isset($drivers[$driverId])) {
                $drivers[$driverId] = $row;
            }
            $name = strtolower(trim((string)($row['full_name'] ?? '')));
            if ($name !== '' && !isset($drivers[$name])) {
                $drivers[$name] = $row;
            }
        }

        return $drivers;
    }

    /**
     * Trip state per bus for the operating date.
     *
     * @param string[] $busIds
     * @return array<string,array{active_trip:?array,latest_completed:?array,latest_trip:?array,trips_today:int,completed:int}>
     */
    private function tripInfo(string $date, array $busIds): array
    {
        $empty = ['active_trip' => null, 'latest_completed' => null, 'latest_trip' => null, 'trips_today' => 0, 'completed' => 0];
        $info = [];
        foreach ($busIds as $busId) {
            $info[(string)$busId] = $empty;
        }
        if ($busIds === [] || !$this->tableExists('bus_trips')) {
            return $info;
        }

        $columns = ['id', 'bus_id', 'status', 'trip_number', 'departure_time', 'arrival_time'];
        foreach (['from_terminal_id', 'to_terminal_id', 'driver_id', 'route_name'] as $candidate) {
            if ($this->hasColumn('bus_trips', $candidate)) {
                $columns[] = $candidate;
            }
        }
        $hasTripDate = $this->hasColumn('bus_trips', 'trip_date');

        try {
            $placeholders = implode(',', array_fill(0, count($busIds), '?'));
            $sql = 'SELECT ' . implode(', ', $columns) . ' FROM bus_trips WHERE ';
            $params = [];
            if ($hasTripDate) {
                $sql .= 'trip_date = ? AND ';
                $params[] = $date;
            }
            $sql .= 'bus_id IN (' . $placeholders . ') ORDER BY id ASC';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($params, array_values($busIds)));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return $info;
        }

        foreach ($rows as $row) {
            $busId = (string)($row['bus_id'] ?? '');
            if (!isset($info[$busId])) {
                continue;
            }
            $status = trim((string)($row['status'] ?? ''));
            $info[$busId]['trips_today']++;
            $info[$busId]['latest_trip'] = $row;
            if (in_array($status, self::ACTIVE_TRIP_STATUSES, true)) {
                if ($info[$busId]['active_trip'] === null) {
                    $info[$busId]['active_trip'] = $row;
                }
            } elseif (in_array($status, self::COMPLETED_TRIP_STATUSES, true)) {
                $info[$busId]['completed']++;
                $info[$busId]['latest_completed'] = $row;
            }
        }

        return $info;
    }

    private function driverName(): string
    {
        try {
            return (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable $e) {
            return 'mysql';
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            if ($this->driverName() === 'sqlite') {
                $stmt = $this->db->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
                $stmt->execute([$table]);

                return (bool)$stmt->fetchColumn();
            }

            return (bool)$this->db->query('SHOW TABLES LIKE ' . $this->db->quote($table))->fetchColumn();
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
     * Terminal directory (id => name/code). Schema-adaptive - installations
     * without a terminals table simply get no terminal labels.
     *
     * @return array<int,array{id:int,name:string,code:string}>
     */
    private function terminalMap(): array
    {
        $map = [];
        if (!$this->tableExists('terminals') || !$this->hasColumn('terminals', 'id')) {
            return $map;
        }
        $nameExpr = $this->hasColumn('terminals', 'terminal_name') ? 'terminal_name' : "''";
        $codeExpr = $this->hasColumn('terminals', 'terminal_code') ? 'terminal_code' : "''";
        try {
            $rows = $this->db->query(
                "SELECT id, $nameExpr AS terminal_name, $codeExpr AS terminal_code FROM terminals"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return $map;
        }
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $map[$id] = [
                'id' => $id,
                'name' => trim((string)($row['terminal_name'] ?? '')),
                'code' => trim((string)($row['terminal_code'] ?? '')),
            ];
        }

        return $map;
    }

    private static function toIntOrNull($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int)$value : null;
    }

    /**
     * Open bus problem reports (waiting/acknowledged) per bus: a bus with an
     * unresolved breakdown is not dispatchable until the dispatcher resolves
     * the report. Schema-adaptive - installations without the table get [].
     *
     * @param string[] $busIds
     * @return array<string,array<string,mixed>> bus_id => problem row
     */
    private function problemInfo(array $busIds): array
    {
        $busIds = array_values(array_filter(array_map('strval', $busIds), static fn (string $id): bool => $id !== ''));
        if ($busIds === []) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($busIds), '?'));
            $stmt = $this->db->prepare(
                'SELECT id, bus_id, problem_type, problem_detail, status, created_at FROM bus_problem_reports'
                . " WHERE status IN ('waiting', 'acknowledged') AND bus_id IN (" . $placeholders . ')'
                . ' ORDER BY id ASC'
            );
            $stmt->execute($busIds);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }

        $problems = [];
        foreach ($rows as $row) {
            $busId = trim((string)($row['bus_id'] ?? ''));
            if ($busId === '' || isset($problems[$busId])) {
                continue;
            }
            $problems[$busId] = [
                'id' => (int)($row['id'] ?? 0),
                'problem_type' => trim((string)($row['problem_type'] ?? '')),
                'problem_detail' => trim((string)($row['problem_detail'] ?? '')),
                'status' => trim((string)($row['status'] ?? '')),
                'created_at' => trim((string)($row['created_at'] ?? '')),
            ];
        }

        return $problems;
    }

    private static function problemReason(array $problem): string
    {
        $type = trim((string)($problem['problem_type'] ?? ''));
        $status = trim((string)($problem['status'] ?? ''));
        $label = $type !== '' ? $type : 'bus problem';
        $state = $status === 'acknowledged' ? 'acknowledged, not yet resolved' : 'waiting for the dispatcher';

        return 'Open problem report: ' . $label . ' (' . $state . ').';
    }

    /**
     * @param array<string,mixed>|null $bus
     * @param array<string,string> $assignments
     * @param array<string,array<string,mixed>> $drivers
     * @param array<string,array<string,mixed>> $tripInfo
     * @param array<string,array<string,mixed>> $problems
     * @param array<int,array<string,mixed>> $terminals
     * @param array<string,int> $driverTerminals driver_id => terminal_id
     * @return array<string,mixed>
     */
    private function decorateBus(string $busId, ?array $bus, ?int $position, array $assignments, array $drivers, array $tripInfo, array $problems = [], array $terminals = [], ?int $assignedTerminalId = null, array $driverTerminals = []): array
    {
        if ($bus === null) {
            $bus = [];
            $eligibility = ['eligible' => false, 'reason' => 'Bus is archived, inactive, or no longer exists.'];
        } else {
            $eligibility = self::busEligibility($bus);
        }
        $problem = $problems[$busId] ?? null;
        if ($problem !== null) {
            $eligibility = ['eligible' => false, 'reason' => self::problemReason($problem)];
        }
        $trip = $tripInfo[$busId] ?? ['active_trip' => null, 'latest_completed' => null, 'latest_trip' => null, 'trips_today' => 0, 'completed' => 0];
        $driverId = $assignments[$busId] ?? '';
        $driverSource = 'assignment';
        if ($driverId === '') {
            $legacy = trim((string)($bus['driver'] ?? ''));
            if ($legacy !== '' && strcasecmp($legacy, 'Unassigned') !== 0 && strcasecmp($legacy, 'TBD') !== 0) {
                $driverId = $legacy;
                $driverSource = 'bus';
            }
        }
        $driver = null;
        if ($driverId !== '') {
            $driver = $drivers[$driverId] ?? ($drivers[strtolower($driverId)] ?? null);
            if ($driver !== null && !empty($driver['driver_id'])) {
                $driverId = (string)$driver['driver_id'];
            }
        }
        $driverEligibility = $driverId !== ''
            ? self::driverEligibility($driver)
            : ['eligible' => true, 'reason' => 'No driver assigned yet.'];

        $activeTrip = $trip['active_trip'];
        $lastCompleted = $trip['latest_completed'] ?? null;
        $operational = trim((string)($bus['operational_status'] ?? ''));
        if ($activeTrip !== null) {
            $tripStatus = 'On Trip';
        } elseif ($trip['completed'] > 0) {
            $tripStatus = 'Completed ' . $trip['completed'] . ' trip' . ($trip['completed'] === 1 ? '' : 's');
        } else {
            $tripStatus = 'Idle';
        }

        /* Both terminals tracked as departure/arrival: the bus's configured
           origin/destination plus where it actually is right now (parked at
           its origin, en route on an active trip, or at the last arrival). */
        $terminalName = static function (?int $terminalId) use ($terminals): string {
            return ($terminalId !== null && isset($terminals[$terminalId])) ? (string)$terminals[$terminalId]['name'] : '';
        };
        $originId = self::toIntOrNull($bus['from_terminal_id'] ?? null);
        $destinationId = self::toIntOrNull($bus['to_terminal_id'] ?? null);
        $originName = $terminalName($originId);
        $destinationName = $terminalName($destinationId);
        $currentId = 0;
        $currentName = '';
        $currentLabel = '';
        if ($activeTrip !== null) {
            $tripFromId = self::toIntOrNull($activeTrip['from_terminal_id'] ?? null) ?? $originId;
            $tripToId = self::toIntOrNull($activeTrip['to_terminal_id'] ?? null) ?? $destinationId;
            $fromName = $terminalName($tripFromId);
            $toName = $terminalName($tripToId);
            $statusWord = (string)($activeTrip['status'] ?? '') === 'Interrupted' ? 'Interrupted en route' : 'En route';
            if ($tripFromId !== null && $tripFromId > 0) {
                $currentId = $tripFromId;
            }
            if ($fromName !== '' || $toName !== '') {
                $currentLabel = $statusWord . ': ' . ($fromName !== '' ? $fromName : '—') . ' → ' . ($toName !== '' ? $toName : '—');
            }
        } elseif ($lastCompleted !== null) {
            $arrivalId = self::toIntOrNull($lastCompleted['to_terminal_id'] ?? null) ?? $destinationId;
            $arrivalName = $terminalName($arrivalId);
            if ($arrivalId !== null && $arrivalId > 0) {
                $currentId = $arrivalId;
            }
            if ($arrivalName !== '') {
                $currentName = $arrivalName;
                $currentLabel = 'At ' . $arrivalName;
            }
        } elseif ($assignedTerminalId !== null && $assignedTerminalId > 0) {
            $currentId = $assignedTerminalId;
            $assignedName = $terminalName($assignedTerminalId);
            if ($assignedName !== '') {
                $currentName = $assignedName;
                $currentLabel = 'At ' . $assignedName;
            }
        } elseif ($originId !== null && $originId > 0) {
            $currentId = $originId;
            if ($originName !== '') {
                $currentName = $originName;
                $currentLabel = 'At ' . $originName;
            }
        }

        /* The dispatch origin is where the bus actually is right now; the
           receiving terminal is the opposite of the Rizal <-> Cabanatuan pair
           for that origin (the active trip's destination while one is
           running). The pair lookup keeps the direction correct even when a
           bus record's configured terminals are missing or identical. */
        $dispatchOriginId = $currentId > 0 ? $currentId : (int)($originId ?? 0);
        $dispatchOriginName = $terminalName($dispatchOriginId > 0 ? $dispatchOriginId : null);
        $receivingId = 0;
        if ($activeTrip !== null) {
            $receivingId = self::toIntOrNull($activeTrip['to_terminal_id'] ?? null) ?? 0;
        }
        if ($receivingId <= 0 || $receivingId === $dispatchOriginId) {
            $pairOpposite = $this->oppositeTerminalFor($dispatchOriginId);
            if ($pairOpposite > 0) {
                $receivingId = $pairOpposite;
            } elseif ($receivingId <= 0) {
                $receivingId = self::receivingTerminalId($dispatchOriginId, $originId, $destinationId);
            }
        }
        if ($receivingId === $dispatchOriginId) {
            $receivingId = 0;   // a terminal is never its own destination
        }
        $receivingName = $terminalName($receivingId > 0 ? $receivingId : null);

        /* The driver's terminals are shown independently of the bus: the
           operating-date assignment when one exists, otherwise the bus's. */
        $driverTerminalId = $driverId !== '' ? (int)($driverTerminals[$driverId] ?? 0) : 0;
        $driverTerminalName = $terminalName($driverTerminalId > 0 ? $driverTerminalId : null);
        $driverCurrentId = $driverTerminalId > 0 ? $driverTerminalId : $currentId;
        $driverCurrentName = $driverTerminalId > 0 ? $driverTerminalName : $currentName;
        $driverReceivingId = $driverCurrentId > 0 ? self::receivingTerminalId($driverCurrentId, $originId, $destinationId) : 0;
        $driverReceivingName = $terminalName($driverReceivingId > 0 ? $driverReceivingId : null);

        /* Route shown for the row: the active trip's route while on route;
           otherwise the route actually recorded for this direction, then the
           terminal-pair label. The stored bus route is never trusted for
           display - it may be a placeholder, a stale direction, or garbage
           ("2 - 1"). The dispatcher chooses the actual route at dispatch. */
        $routeName = trim((string)($activeTrip['route_name'] ?? ''));
        $routeName = self::sanitizeRouteName($routeName);
        if ($routeName === '' && $dispatchOriginId > 0 && $receivingId > 0) {
            $routeName = $this->recordedRouteForDirection($dispatchOriginId, $receivingId);
        }
        if ($routeName === '') {
            $from = self::shortTerminalName($dispatchOriginName !== '' ? $dispatchOriginName : $originName);
            $to = self::shortTerminalName($receivingName !== '' ? $receivingName : $destinationName);
            if ($from !== '' && $to !== '' && strcasecmp($from, $to) !== 0) {
                $routeName = $from . '-' . $to;
            }
        }

        $actualTrip = $activeTrip ?? $lastCompleted ?? ($trip['latest_trip'] ?? null);
        $actualTripNumber = ($actualTrip !== null && isset($actualTrip['trip_number']) && $actualTrip['trip_number'] !== '')
            ? $actualTrip['trip_number']
            : null;

        if ($activeTrip !== null) {
            $dispatchStatus = (string)($activeTrip['status'] ?? 'Departed');
        } elseif ($lastCompleted !== null || $trip['completed'] > 0) {
            $dispatchStatus = 'Completed';
        } else {
            $dispatchStatus = 'Idle';
        }

        return [
            'bus_id' => $busId,
            'plate_number' => (string)($bus['plate_number'] ?? ''),
            'bus_type' => (string)($bus['bus_type'] ?? ''),
            'schedule' => (string)($bus['schedule'] ?? ''),
            'queue_position' => $position,
            'operational_status' => $operational,
            'status' => (string)($bus['status'] ?? ''),
            'eligible' => $eligibility['eligible'],
            'eligibility_reason' => $eligibility['reason'],
            'problem' => $problem,
            'assigned_driver' => [
                'driver_id' => $driverId !== '' ? $driverId : null,
                'full_name' => $driver !== null ? (string)($driver['full_name'] ?? $driverId) : ($driverId !== '' ? $driverId : null),
                'status' => $driver !== null ? (string)($driver['status'] ?? '') : '',
                'availability' => $driver !== null ? (string)($driver['availability'] ?? '') : '',
                'eligible' => $driverEligibility['eligible'],
                'reason' => $driverEligibility['reason'],
                'source' => $driverId !== '' ? $driverSource : 'none',
                'terminal_id' => $driverTerminalId > 0 ? $driverTerminalId : null,
                'terminal' => $driverTerminalName !== '' ? $driverTerminalName : null,
                'current_terminal_id' => $driverCurrentId > 0 ? $driverCurrentId : null,
                'current_terminal' => $driverCurrentName !== '' ? $driverCurrentName : null,
                'receiving_terminal_id' => $driverReceivingId > 0 ? $driverReceivingId : null,
                'receiving_terminal' => $driverReceivingName !== '' ? $driverReceivingName : null,
            ],
            'trip_status' => $tripStatus,
            'dispatch_status' => $dispatchStatus,
            'actual_trip_number' => $actualTripNumber,
            'route_name' => $routeName !== '' ? $routeName : null,
            'route' => $routeName !== '' ? $routeName : null,
            'origin_terminal_id' => $originId,
            'origin_terminal' => $originName !== '' ? $originName : null,
            'destination_terminal_id' => $destinationId,
            'destination_terminal' => $destinationName !== '' ? $destinationName : null,
            'terminal_id' => $assignedTerminalId !== null && $assignedTerminalId > 0 ? $assignedTerminalId : null,
            'terminal' => ($assignedTerminalId !== null && $assignedTerminalId > 0 && $terminalName($assignedTerminalId) !== '') ? $terminalName($assignedTerminalId) : null,
            'effective_terminal_id' => $currentId > 0 ? $currentId : null,
            'effective_terminal' => $currentName !== '' ? $currentName : null,
            'dispatch_origin_terminal_id' => $dispatchOriginId > 0 ? $dispatchOriginId : null,
            'dispatch_origin_terminal' => $dispatchOriginName !== '' ? $dispatchOriginName : null,
            'receiving_terminal_id' => $receivingId > 0 ? $receivingId : null,
            'receiving_terminal' => $receivingName !== '' ? $receivingName : null,
            'current_terminal' => $currentName !== '' ? $currentName : null,
            'current_terminal_id' => $currentId > 0 ? $currentId : null,
            'current_location' => $currentLabel !== '' ? $currentLabel : null,
            'active_trip' => $trip['active_trip'] !== null ? [
                'id' => (int)($trip['active_trip']['id'] ?? 0),
                'trip_number' => $trip['active_trip']['trip_number'] ?? null,
                'status' => (string)($trip['active_trip']['status'] ?? ''),
                'departure_time' => $trip['active_trip']['departure_time'] ?? null,
                'route_name' => $trip['active_trip']['route_name'] ?? null,
                'driver_id' => $trip['active_trip']['driver_id'] ?? null,
            ] : null,
            'trips_today' => (int)$trip['trips_today'],
        ];
    }

    /**
     * Selectable drivers with eligibility, every bus they are bound to, and
     * their operating-date terminal assignment so the page can scope the
     * selector to the bus's terminal. Drivers with an active assignment are
     * flagged `occupied` and are removed from the available list until their
     * trip completes and the arrival is confirmed.
     *
     * @param array<string,string[]> $driverBindings driver_id => bus_ids
     * @param array<string,array<string,mixed>> $drivers
     * @param array<string,int> $driverTerminals driver_id => terminal_id
     * @param array<int,array<string,mixed>> $terminals
     * @param array<string,array{bus_id:?string,reason:string}> $occupied
     * @return array<int,array<string,mixed>>
     */
    private function driverOptions(array $driverBindings, array $drivers, array $driverTerminals = [], array $terminals = [], array $occupied = []): array
    {
        $terminalName = static function (?int $terminalId) use ($terminals): string {
            return ($terminalId !== null && $terminalId > 0 && isset($terminals[$terminalId])) ? (string)$terminals[$terminalId]['name'] : '';
        };
        $options = [];
        $seen = [];
        foreach ($drivers as $driverId => $driver) {
            $canonicalId = trim((string)($driver['driver_id'] ?? '')) !== '' ? trim((string)$driver['driver_id']) : (string)$driverId;
            /* Only the canonical entry (keyed by the driver's real database id)
               becomes an option; the name-keyed lookup entries are internal
               and must never reach the dropdown as a selectable value. */
            if (isset($seen[$canonicalId]) || (string)$driverId !== $canonicalId) {
                continue;
            }
            $seen[$canonicalId] = true;
            $eligibility = self::driverEligibility($driver);
            $bindings = $driverBindings[$canonicalId] ?? ($driverBindings[$driverId] ?? []);
            $terminalId = (int)($driverTerminals[$canonicalId] ?? 0);
            $occupancy = $occupied[$canonicalId] ?? ($occupied[$driverId] ?? null);
            $options[] = [
                'driver_id' => $canonicalId,
                'full_name' => (string)($driver['full_name'] ?? $canonicalId),
                'status' => (string)($driver['status'] ?? ''),
                'availability' => (string)($driver['availability'] ?? ''),
                'eligible' => $eligibility['eligible'],
                'reason' => $eligibility['reason'],
                'assigned_bus' => $bindings[0] ?? null,
                'assigned_buses' => array_values($bindings),
                'terminal_id' => $terminalId > 0 ? $terminalId : null,
                'terminal' => $terminalId > 0 ? ($terminalName($terminalId) ?: null) : null,
                'occupied' => $occupancy !== null,
                'occupied_bus' => $occupancy['bus_id'] ?? null,
                'occupied_reason' => $occupancy['reason'] ?? null,
            ];
        }
        usort($options, static function (array $a, array $b): int {
            if ($a['eligible'] !== $b['eligible']) {
                return $a['eligible'] ? -1 : 1;
            }

            return strcasecmp((string)$a['full_name'], (string)$b['full_name']);
        });

        return $options;
    }
}
