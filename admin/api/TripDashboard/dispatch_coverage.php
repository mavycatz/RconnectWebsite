<?php

declare(strict_types=1);

/**
 * Daily dispatch coverage shared by the data generator page.
 *
 * Pure functions only: eligibility filters for the real bus fleet and driver
 * roster, the per-day coverage target (19 existing records), the lineup and
 * terminal-assignment selection that always keeps the day's trip participants
 * and rotates the remainder deterministically, queue-position ordering, the
 * achieved/limited labels used by the run summary, and the per-date validation
 * helpers checked before a generated batch is committed. Keeping the rules
 * here makes them testable without booting the application or touching the
 * database.
 */

require_once __DIR__ . '/fleet_daily_volume.php';
require_once __DIR__ . '/fleet_scope_profile.php';

if (!defined('DISPATCH_COVERAGE_BUS_TARGET')) {
    define('DISPATCH_COVERAGE_BUS_TARGET', 19);
}
if (!defined('DISPATCH_COVERAGE_DRIVER_TARGET')) {
    define('DISPATCH_COVERAGE_DRIVER_TARGET', 19);
}
if (!defined('DISPATCH_COVERAGE_BLOCKED_BUS_STATUSES')) {
    define('DISPATCH_COVERAGE_BLOCKED_BUS_STATUSES', [
        'inactive',
        'maintenance',
        'archived',
        'retired',
        'out of service',
        'out-of-service',
        'out_of_service',
    ]);
}
if (!defined('DISPATCH_COVERAGE_BLOCKED_OPERATIONAL')) {
    define('DISPATCH_COVERAGE_BLOCKED_OPERATIONAL', ['under maintenance', 'out of service']);
}
if (!defined('DISPATCH_COVERAGE_BLOCKED_DRIVER_STATUSES')) {
    define('DISPATCH_COVERAGE_BLOCKED_DRIVER_STATUSES', ['inactive', 'archived']);
}
if (!defined('DISPATCH_COVERAGE_BLOCKED_DRIVER_AVAILABILITY')) {
    define('DISPATCH_COVERAGE_BLOCKED_DRIVER_AVAILABILITY', ['on leave', 'unavailable']);
}

if (!function_exists('dispatchCoverageBusIdValid')) {
    /**
     * A dispatchable bus identifier: existing BUS- ids only, case-insensitive
     * so legacy Bus-1xxx rows and official BUS-2xxx rows both qualify.
     */
    function dispatchCoverageBusIdValid(string $busId): bool
    {
        return preg_match('/^BUS-/i', trim($busId)) === 1;
    }
}

if (!function_exists('dispatchCoverageBusEligible')) {
    /**
     * Whether a fleet row may operate (trips and coverage): a non-empty bus id
     * whose lifecycle status and, when present, operational status are not
     * blocked. The BUS- identifier rule for dispatch-lineup writes is applied
     * by dispatchCoverageEligibleBuses().
     */
    function dispatchCoverageBusEligible(array $bus): bool
    {
        $busId = trim((string)($bus['bus_id'] ?? ''));
        if ($busId === '') {
            return false;
        }
        $status = strtolower(trim((string)($bus['status'] ?? '')));
        if ($status !== '' && in_array($status, DISPATCH_COVERAGE_BLOCKED_BUS_STATUSES, true)) {
            return false;
        }
        $operational = strtolower(trim((string)($bus['operational_status'] ?? '')));
        if ($operational !== '' && in_array($operational, DISPATCH_COVERAGE_BLOCKED_OPERATIONAL, true)) {
            return false;
        }
        return true;
    }
}

if (!function_exists('dispatchCoverageEligibleBuses')) {
    /**
     * Eligible existing bus ids from fleet rows, unique and sorted so
     * selection stays deterministic. On window days only the configured
     * Sep-Oct 2026 allow-list qualifies.
     */
    function dispatchCoverageEligibleBuses(array $busRows, bool $windowDay = false): array
    {
        $ids = [];
        foreach ($busRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $busId = trim((string)($row['bus_id'] ?? ''));
            if ($busId === '' || isset($ids[$busId])) {
                continue;
            }
            if (!dispatchCoverageBusEligible($row)) {
                continue;
            }
            if (!dispatchCoverageBusIdValid($busId)) {
                continue;   // only official BUS- ids can be dispatched
            }
            if ($windowDay && !fleetScopeAllowsBus($busId)) {
                continue;
            }
            $ids[$busId] = true;
        }
        $out = array_keys($ids);
        sort($out, SORT_STRING);
        return $out;
    }
}

if (!function_exists('dispatchCoverageDriverEligible')) {
    /**
     * Whether a driver row may be assigned: an existing driver id whose
     * account status is not inactive/archived and whose availability, when the
     * column exists, is not On Leave/Unavailable. Missing columns stay
     * eligible so the check is schema-adaptive.
     */
    function dispatchCoverageDriverEligible(array $driver): bool
    {
        $driverId = trim((string)($driver['driver_id'] ?? ''));
        if ($driverId === '') {
            return false;
        }
        $status = strtolower(trim((string)($driver['status'] ?? '')));
        if ($status !== '' && in_array($status, DISPATCH_COVERAGE_BLOCKED_DRIVER_STATUSES, true)) {
            return false;
        }
        $availability = strtolower(trim((string)($driver['availability'] ?? '')));
        if ($availability !== '' && in_array($availability, DISPATCH_COVERAGE_BLOCKED_DRIVER_AVAILABILITY, true)) {
            return false;
        }
        return true;
    }
}

if (!function_exists('dispatchCoverageEligibleDrivers')) {
    /**
     * Eligible existing driver ids from roster rows, unique and sorted.
     */
    function dispatchCoverageEligibleDrivers(array $driverRows): array
    {
        $ids = [];
        foreach ($driverRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $driverId = trim((string)($row['driver_id'] ?? ''));
            if ($driverId === '' || isset($ids[$driverId])) {
                continue;
            }
            if (!dispatchCoverageDriverEligible($row)) {
                continue;
            }
            $ids[$driverId] = true;
        }
        $out = array_keys($ids);
        sort($out, SORT_STRING);
        return $out;
    }
}

if (!function_exists('dispatchCoverageTarget')) {
    /**
     * Daily coverage target for buses or drivers: the configured target,
     * capped by how many eligible records actually exist. Never fabricated and
     * never above the configured target.
     */
    function dispatchCoverageTarget(int $eligibleCount, int $target = DISPATCH_COVERAGE_BUS_TARGET): int
    {
        return max(0, min(max(0, $target), max(0, $eligibleCount)));
    }
}

if (!function_exists('dispatchCoverageSelection')) {
    /**
     * The day's coverage selection: every required id (the day's operating
     * trip participants) is kept, and the remainder is filled from the
     * eligible pool by a stable per-day rotation until the target is reached.
     * The result is ordered by a per-day stable rank so queue positions rotate
     * naturally across dates while staying deterministic for one date.
     */
    function dispatchCoverageSelection(array $eligibleIds, array $requiredIds, string $day, int $target): array
    {
        $eligible = [];
        foreach ($eligibleIds as $id) {
            $id = trim((string)$id);
            if ($id !== '') {
                $eligible[$id] = true;
            }
        }
        if (!$eligible || $target <= 0) {
            return [];
        }

        $required = [];
        foreach ($requiredIds as $id) {
            $id = trim((string)$id);
            if ($id !== '' && isset($eligible[$id])) {
                $required[$id] = true;
            }
        }

        $fillIds = array_keys(array_diff_key($eligible, $required));
        usort($fillIds, static function (string $a, string $b) use ($day): int {
            $ka = fleetStableUnit('coverage-fill-' . $day . '-' . $a);
            $kb = fleetStableUnit('coverage-fill-' . $day . '-' . $b);
            return $ka === $kb ? strcmp($a, $b) : ($ka <=> $kb);
        });

        $selected = array_keys($required);
        foreach ($fillIds as $id) {
            if (count($selected) >= $target) {
                break;
            }
            $selected[] = $id;
        }

        usort($selected, static function (string $a, string $b) use ($day): int {
            $ka = fleetStableUnit('coverage-order-' . $day . '-' . $a);
            $kb = fleetStableUnit('coverage-order-' . $day . '-' . $b);
            return $ka === $kb ? strcmp($a, $b) : ($ka <=> $kb);
        });

        return $selected;
    }
}

if (!function_exists('dispatchCoverageGapLabel')) {
    /**
     * Coverage status for the run summary: "achieved" when the target was
     * reached, otherwise "limited:<reason-key> (N of M)" naming why.
     */
    function dispatchCoverageGapLabel(int $achieved, int $target, string $reasonKey): string
    {
        if ($target <= 0 || $achieved >= $target) {
            return 'achieved';
        }
        $reasonKey = trim($reasonKey) !== '' ? trim($reasonKey) : 'eligibility';
        return 'limited:' . $reasonKey . ' (' . max(0, $achieved) . ' of ' . $target . ')';
    }
}

if (!function_exists('dispatchCoverageBusReasonKey')) {
    /**
     * Why bus coverage can fall short: the Sep-Oct 2026 window profile or the
     * eligible fleet size.
     */
    function dispatchCoverageBusReasonKey(bool $windowDay): string
    {
        return $windowDay ? 'window' : 'fleet';
    }
}

if (!function_exists('dispatchCoverageDriverReasonKey')) {
    /**
     * Why driver coverage can fall short: no terminals configured or the
     * eligible roster size.
     */
    function dispatchCoverageDriverReasonKey(bool $terminalsConfigured): string
    {
        return $terminalsConfigured ? 'roster' : 'terminals';
    }
}

if (!defined('DISPATCH_COVERAGE_QUEUE_COLUMNS')) {
    define('DISPATCH_COVERAGE_QUEUE_COLUMNS', [
        'operating_date',
        'bus_id',
        'queue_position',
        'published_at',
        'published_by',
        'terminal_id',
    ]);
}
if (!defined('DISPATCH_COVERAGE_ASSIGNMENT_COLUMNS')) {
    define('DISPATCH_COVERAGE_ASSIGNMENT_COLUMNS', [
        'operating_date',
        'driver_id',
        'terminal_id',
    ]);
}

if (!function_exists('dispatchCoverageSchemaUsable')) {
    /**
     * Whether the dispatch tables can carry historical coverage rows: every
     * required column is present. A null/absent column list means the table is
     * missing and unusable; the missing names are returned so the run report
     * can state the limitation.
     */
    function dispatchCoverageSchemaUsable(?array $queueColumns, ?array $assignmentColumns): array
    {
        $missingFor = static function (?array $columns, array $required): array {
            $have = [];
            foreach ((array)$columns as $column) {
                $column = strtolower(trim((string)$column));
                if ($column !== '') {
                    $have[$column] = true;
                }
            }
            $missing = [];
            foreach ($required as $column) {
                if (!isset($have[$column])) {
                    $missing[] = $column;
                }
            }
            return $missing;
        };

        $queueMissing = $missingFor($queueColumns, DISPATCH_COVERAGE_QUEUE_COLUMNS);
        $assignmentMissing = $missingFor($assignmentColumns, DISPATCH_COVERAGE_ASSIGNMENT_COLUMNS);

        return [
            'queue' => !$queueMissing,
            'terminals' => !$assignmentMissing,
            'missing' => [
                'queue' => $queueMissing,
                'terminals' => $assignmentMissing,
            ],
        ];
    }
}

if (!function_exists('dispatchCoverageSummary')) {
    /**
     * Builds the run summary's coverage block from the per-date coverage
     * information collected during generation and the per-date ride totals
     * (date => ['passengers' => int, 'fares' => float]). Returns the per-date
     * entries plus an aggregate block; trip, driver, and bus totals stay
     * separate and are never forced to match.
     */
    function dispatchCoverageSummary(
        array $coverageDays,
        array $dailyRides = [],
        int $busTarget = DISPATCH_COVERAGE_BUS_TARGET,
        int $driverTarget = DISPATCH_COVERAGE_DRIVER_TARGET,
        bool $terminalsConfigured = false
    ): array {
        $daily = [];
        $union = ['buses' => [], 'drivers' => [], 'lineup' => [], 'terminal' => []];
        $totals = [
            'dates' => 0,
            'trips' => 0,
            'passengers' => 0,
            'fares' => 0.0,
            'drivers_on_trips' => 0,
            'buses_on_trips' => 0,
            'lineup_buses' => 0,
            'terminal_drivers' => 0,
            'bus_targets_achieved' => 0,
            'driver_targets_achieved' => 0,
            'limitations' => [],
        ];

        ksort($coverageDays, SORT_STRING);
        foreach ($coverageDays as $day => $coverage) {
            $day = (string)$day;
            $coverage = (array)$coverage;
            $rides = (array)($dailyRides[$day] ?? []);
            $tripBuses = array_values(array_unique(array_map('strval', (array)($coverage['trip_buses'] ?? []))));
            $tripDrivers = array_values(array_unique(array_map('strval', (array)($coverage['trip_drivers'] ?? []))));
            $lineup = array_values(array_unique(array_map('strval', (array)($coverage['lineup_buses'] ?? []))));
            $assigned = array_values(array_unique(array_map('strval', (array)($coverage['assigned_drivers'] ?? []))));
            $windowDay = !empty($coverage['window_day']);
            $busLabel = dispatchCoverageGapLabel(count($lineup), $busTarget, dispatchCoverageBusReasonKey($windowDay));
            $driverLabel = dispatchCoverageGapLabel(count($assigned), $driverTarget, dispatchCoverageDriverReasonKey($terminalsConfigured));

            $entry = [
                'date' => $day,
                'window_day' => $windowDay,
                'trips' => (int)($coverage['trips'] ?? 0),
                'drivers_on_trips' => count($tripDrivers),
                'buses_on_trips' => count($tripBuses),
                'lineup_buses' => count($lineup),
                'terminal_drivers' => count($assigned),
                'passengers' => (int)($rides['passengers'] ?? 0),
                'fares' => round((float)($rides['fares'] ?? 0), 2),
                'queue_source' => (string)($coverage['queue_source'] ?? ''),
                'assign_source' => (string)($coverage['assign_source'] ?? ''),
                'bus_coverage' => $busLabel,
                'driver_coverage' => $driverLabel,
            ];
            $daily[] = $entry;

            $totals['dates']++;
            $totals['trips'] += $entry['trips'];
            $totals['passengers'] += $entry['passengers'];
            $totals['fares'] = round($totals['fares'] + $entry['fares'], 2);
            if ($busLabel === 'achieved') {
                $totals['bus_targets_achieved']++;
            }
            if ($driverLabel === 'achieved') {
                $totals['driver_targets_achieved']++;
            }
            foreach ($tripBuses as $id) {
                $union['buses'][$id] = true;
            }
            foreach ($tripDrivers as $id) {
                $union['drivers'][$id] = true;
            }
            foreach ($lineup as $id) {
                $union['lineup'][$id] = true;
            }
            foreach ($assigned as $id) {
                $union['terminal'][$id] = true;
            }
            foreach ([$busLabel, $driverLabel] as $label) {
                if (strpos($label, 'limited:') === 0) {
                    $totals['limitations'][$label] = (int)($totals['limitations'][$label] ?? 0) + 1;
                }
            }
        }

        $totals['drivers_on_trips'] = count($union['drivers']);
        $totals['buses_on_trips'] = count($union['buses']);
        $totals['lineup_buses'] = count($union['lineup']);
        $totals['terminal_drivers'] = count($union['terminal']);

        return ['daily' => $daily, 'totals' => $totals];
    }
}

if (!function_exists('dispatchCoverageWrittenPairs')) {
    /**
     * Parses a Queue Published audit payload for the coverage pairs the
     * generator actually wrote: ['buses' => [day => [bus_id => true]],
     * 'drivers' => [day => [driver_id => true]]]. Legacy payloads without the
     * written lists yield nothing, so cleanup never guesses or deletes rows it
     * cannot attribute.
     */
    function dispatchCoverageWrittenPairs(array $payload): array
    {
        $buses = [];
        $drivers = [];
        $day = trim((string)($payload['operating_date'] ?? ''));
        if ($day === '') {
            return ['buses' => $buses, 'drivers' => $drivers];
        }
        foreach ((array)($payload['written_buses'] ?? []) as $busId) {
            $busId = trim((string)$busId);
            if ($busId !== '') {
                $buses[$day][$busId] = true;
            }
        }
        foreach ((array)($payload['written_drivers'] ?? []) as $driverId) {
            $driverId = trim((string)$driverId);
            if ($driverId !== '') {
                $drivers[$day][$driverId] = true;
            }
        }
        return ['buses' => $buses, 'drivers' => $drivers];
    }
}

if (!function_exists('dispatchCoverageDriverReadyTimes')) {
    /**
     * Latest "ready for the next trip" time per driver from a day's trips:
     * each trip's departure plus duration plus turnaround. Used when a
     * replacement driver takes over another bus's remaining trips, so a
     * driver still driving or turning around at the handover time is never
     * selected.
     */
    function dispatchCoverageDriverReadyTimes(array $trips): array
    {
        $ready = [];
        foreach ($trips as $trip) {
            $trip = (array)$trip;
            $did = trim((string)($trip['drv']['driver_id'] ?? ''));
            if ($did === '' || !isset($trip['sec'])) {
                continue;
            }
            $end = (int)$trip['sec']
                + (int)round((float)($trip['durationMin'] ?? 0) * 60)
                + (int)($trip['turnSec'] ?? 0);
            $ready[$did] = max($ready[$did] ?? 0, $end);
        }
        return $ready;
    }
}

if (!function_exists('dispatchCoveragePublishedAt')) {
    /**
     * The day's queue publication timestamp: 04:45 Asia/Manila plus a stable
     * 0-15 minute jitter, the same morning publication time the generator's
     * Queue Published audit event claims.
     */
    function dispatchCoveragePublishedAt(string $day): string
    {
        $jitter = (int)round(fleetStableUnit('audit-publish-' . $day) * 900);
        return date('Y-m-d H:i:s', strtotime($day . ' 04:45:00') + $jitter);
    }
}

if (!function_exists('dispatchCoverageBusTerminalId')) {
    /**
     * The bus's configured terminal when it resolves to a known terminal id,
     * else null (the queue table allows a null terminal).
     */
    function dispatchCoverageBusTerminalId(array $busRow, array $terminalIds): ?int
    {
        $known = [];
        foreach ($terminalIds as $id) {
            $value = (int)$id;
            if ($value > 0) {
                $known[$value] = true;
            }
        }
        foreach (['from_terminal_id', 'to_terminal_id'] as $column) {
            if (!array_key_exists($column, $busRow)) {
                continue;
            }
            $value = (int)($busRow[$column] ?? 0);
            if ($value > 0 && isset($known[$value])) {
                return $value;
            }
        }
        return null;
    }
}

if (!function_exists('dispatchCoverageDriverTerminalId')) {
    /**
     * Deterministic terminal for a driver's daily assignment: a stable pick
     * from the configured terminals, or null when none exist.
     */
    function dispatchCoverageDriverTerminalId(array $terminalIds, string $day, string $driverId): ?int
    {
        $ids = [];
        foreach ($terminalIds as $id) {
            $value = (int)$id;
            if ($value > 0) {
                $ids[$value] = true;
            }
        }
        $ids = array_keys($ids);
        if (!$ids) {
            return null;
        }
        sort($ids, SORT_NUMERIC);
        $index = (int)floor(fleetStableUnit('coverage-terminal-' . $day . '-' . $driverId) * count($ids));
        $index = max(0, min(count($ids) - 1, $index));
        return $ids[$index];
    }
}

if (!function_exists('dispatchCoverageQueueRow')) {
    /**
     * Row values for one bus_dispatch_queue entry, following the table shape
     * created by the dispatch queue migration/service.
     */
    function dispatchCoverageQueueRow(string $day, string $busId, int $position, ?string $publishedBy, ?int $terminalId): array
    {
        $publishedAt = dispatchCoveragePublishedAt($day);
        return [
            'operating_date' => $day,
            'bus_id' => $busId,
            'queue_position' => $position,
            'published_at' => $publishedAt,
            'published_by' => $publishedBy,
            'terminal_id' => $terminalId,
            'created_at' => $publishedAt,
            'updated_at' => $publishedAt,
        ];
    }
}

if (!function_exists('dispatchCoverageTerminalAssignmentRow')) {
    /**
     * Row values for one driver_terminal_assignments entry.
     */
    function dispatchCoverageTerminalAssignmentRow(string $day, string $driverId, int $terminalId): array
    {
        $createdAt = dispatchCoveragePublishedAt($day);
        return [
            'operating_date' => $day,
            'driver_id' => $driverId,
            'terminal_id' => $terminalId,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }
}

if (!function_exists('dispatchCoverageTripCountErrors')) {
    /**
     * Per-date trip-volume checks: 13-18 for completed past operating days;
     * the partial current day is exempt from the floor but not the ceiling.
     */
    function dispatchCoverageTripCountErrors(bool $isPastDay, int $tripCount): array
    {
        $errors = [];
        if ($tripCount < 0) {
            $errors[] = 'negative trip count';
            return $errors;
        }
        if ($isPastDay) {
            if ($tripCount < FLEET_DAILY_TRIPS_MIN || $tripCount > FLEET_DAILY_TRIPS_MAX) {
                $errors[] = 'trip count ' . $tripCount . ' outside ' . FLEET_DAILY_TRIPS_MIN . '-' . FLEET_DAILY_TRIPS_MAX;
            }
            return $errors;
        }
        if ($tripCount > FLEET_DAILY_TRIPS_MAX) {
            $errors[] = 'trip count ' . $tripCount . ' above ' . FLEET_DAILY_TRIPS_MAX;
        }
        return $errors;
    }
}

if (!function_exists('dispatchCoverageParticipantErrors')) {
    /**
     * Every bus and driver that completed a trip on the date must appear in
     * the date's dispatch coverage records.
     */
    function dispatchCoverageParticipantErrors(array $tripBuses, array $tripDrivers, array $lineupBuses, array $assignedDrivers): array
    {
        $errors = [];
        $lineup = array_fill_keys(array_map('strval', $lineupBuses), true);
        $assigned = array_fill_keys(array_map('strval', $assignedDrivers), true);
        foreach (array_unique(array_map('strval', $tripBuses)) as $busId) {
            if ($busId !== '' && !isset($lineup[$busId])) {
                $errors[] = 'trip bus ' . $busId . ' missing from the dispatch lineup';
            }
        }
        foreach (array_unique(array_map('strval', $tripDrivers)) as $driverId) {
            if ($driverId !== '' && !isset($assigned[$driverId])) {
                $errors[] = 'trip driver ' . $driverId . ' missing from the terminal assignments';
            }
        }
        return $errors;
    }
}

if (!function_exists('dispatchCoverageQueuePositionErrors')) {
    /**
     * Queue positions must be exactly 1..N with no duplicates.
     */
    function dispatchCoverageQueuePositionErrors(array $positions): array
    {
        $numbers = [];
        foreach ($positions as $position) {
            $numbers[] = (int)$position;
        }
        sort($numbers);
        foreach ($numbers as $index => $number) {
            if ($number !== $index + 1) {
                return ['queue positions are not unique 1..N'];
            }
        }
        return [];
    }
}

if (!function_exists('dispatchCoverageChronologyErrors')) {
    /**
     * The publication timestamp must precede the day's first departure. Both
     * inputs are comparable date-time strings ("Y-m-d H:i:s").
     */
    function dispatchCoverageChronologyErrors(?string $publishedAt, ?string $firstDeparture): array
    {
        $publishedAt = $publishedAt === null ? '' : trim($publishedAt);
        $firstDeparture = $firstDeparture === null ? '' : trim($firstDeparture);
        if ($publishedAt === '' || $firstDeparture === '') {
            return ['dispatch publication timestamp or first departure missing'];
        }
        if (strtotime($publishedAt) === false || strtotime($firstDeparture) === false) {
            return ['dispatch publication timestamp or first departure is not a valid time'];
        }
        if (strtotime($publishedAt) >= strtotime($firstDeparture)) {
            return ['dispatch publication ' . $publishedAt . ' does not precede first departure ' . $firstDeparture];
        }
        return [];
    }
}

if (!function_exists('dispatchCoverageReferenceErrors')) {
    /**
     * Coverage records must reference existing records: lineup bus ids in the
     * known fleet, assigned driver ids in the known roster, and each assigned
     * terminal id in the known terminal list. $assignedTerminalsByDriver maps
     * driver id => terminal id (null/empty skips the terminal check).
     */
    function dispatchCoverageReferenceErrors(
        array $lineupBuses,
        array $knownBuses,
        array $assignedTerminalsByDriver,
        array $knownDrivers,
        array $knownTerminals
    ): array {
        $errors = [];
        $buses = array_fill_keys(array_map('strval', $knownBuses), true);
        $drivers = array_fill_keys(array_map('strval', $knownDrivers), true);
        $terminals = [];
        foreach ($knownTerminals as $id) {
            $terminals[(string)$id] = true;
        }
        foreach (array_unique(array_map('strval', $lineupBuses)) as $busId) {
            if ($busId !== '' && !isset($buses[$busId])) {
                $errors[] = 'unknown bus in dispatch lineup: ' . $busId;
            }
        }
        foreach ($assignedTerminalsByDriver as $driverId => $terminalId) {
            $driverId = (string)$driverId;
            if ($driverId !== '' && !isset($drivers[$driverId])) {
                $errors[] = 'unknown driver in terminal assignments: ' . $driverId;
                continue;
            }
            if ($terminalId !== null && trim((string)$terminalId) !== '' && !isset($terminals[(string)$terminalId])) {
                $errors[] = 'unknown terminal ' . (string)$terminalId . ' for driver ' . $driverId;
            }
        }
        return $errors;
    }
}
