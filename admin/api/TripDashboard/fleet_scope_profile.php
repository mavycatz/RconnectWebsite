<?php

declare(strict_types=1);

/**
 * Fleet-scope profile shared by the data generator page.
 *
 * Pure functions only: the configured date window, the bus allow-list, and
 * the reduced trip/passenger volumes applied to generated and simulated
 * operations on window days. Keeping the profile here makes it testable
 * without booting the application or touching the database.
 */

if (!defined('FLEET_SCOPE_WINDOW_START')) {
    define('FLEET_SCOPE_WINDOW_START', '2026-09-01');
}
if (!defined('FLEET_SCOPE_WINDOW_END')) {
    define('FLEET_SCOPE_WINDOW_END', '2026-10-31');
}
if (!defined('FLEET_SCOPE_BUSES')) {
    /* Built-in demo fleet for the window. The generator page can override
       this with the exact bus IDs a deployment actually has (see
       fleetScopeSetBusList); missing buses are simply never operated. */
    define('FLEET_SCOPE_BUSES', [
        'BUS-2001',
        'BUS-2004',
        'BUS-2017',
        'BUS-2105',
        'BUS-2106',
        'BUS-2208',
        'BUS-2209',
        'BUS-2210',
    ]);
}
if (!defined('FLEET_SCOPE_TRIPS_PER_BUS')) {
    define('FLEET_SCOPE_TRIPS_PER_BUS', 2);
}
if (!defined('FLEET_SCOPE_MAX_BUSES_PER_DAY')) {
    /* At FLEET_SCOPE_TRIPS_PER_BUS trips each, this many buses keep a window
       day inside the generator's 13-18 daily trip band. */
    define('FLEET_SCOPE_MAX_BUSES_PER_DAY', 9);
}
if (!defined('FLEET_SCOPE_MIN_BUSES_PER_DAY')) {
    /* Fewest operating buses for one window day: at FLEET_SCOPE_TRIPS_PER_BUS
       trips each the day still reaches the generator's 13-18 daily trip band. */
    define('FLEET_SCOPE_MIN_BUSES_PER_DAY', 7);
}
if (!defined('FLEET_SCOPE_DAY_PROFILE_ANCHORS')) {
    /* Where each day profile sits inside the daily bus-count span: weekdays
       and Fridays run fuller, Saturday stays in the middle, Sundays and
       holidays run lightest. */
    define('FLEET_SCOPE_DAY_PROFILE_ANCHORS', [
        'weekday'  => 0.75,
        'friday'   => 0.75,
        'saturday' => 0.50,
        'sunday'   => 0.25,
        'holiday'  => 0.15,
    ]);
}
if (!defined('FLEET_SCOPE_BUS_COUNT_JITTER')) {
    /* How far the stable per-day value may move a day's bus count away from
       its profile anchor, in anchor units (1.0 spans the whole min-max
       range), so daily totals vary instead of repeating the anchor count. */
    define('FLEET_SCOPE_BUS_COUNT_JITTER', 1.0);
}
if (!defined('FLEET_SCOPE_PAX_MIN')) {
    define('FLEET_SCOPE_PAX_MIN', 5);
}
if (!defined('FLEET_SCOPE_PAX_MAX')) {
    define('FLEET_SCOPE_PAX_MAX', 15);
}
if (!defined('FLEET_SCOPE_SPACE_MAX')) {
    define('FLEET_SCOPE_SPACE_MAX', 15);
}

if (!function_exists('fleetScopeDayActive')) {
    /**
     * True when the given Y-m-d day lies inside the configured window
     * (inclusive of both endpoints).
     */
    function fleetScopeDayActive(string $day): bool
    {
        $day = trim($day);
        if ($day === '') {
            return false;
        }
        return $day >= FLEET_SCOPE_WINDOW_START && $day <= FLEET_SCOPE_WINDOW_END;
    }
}

if (!function_exists('fleetScopeSetBusList')) {
    /**
     * Override the window's bus allow-list for this request with the exact
     * bus IDs the deployment actually has (comma/space separated string or
     * array). An empty value restores the built-in list. Buses not present
     * in the fleet are simply never operated.
     */
    function fleetScopeSetBusList($list): void
    {
        if (is_string($list)) {
            $list = preg_split('/[,\s]+/', $list) ?: [];
        }
        $out = [];
        foreach ((array)$list as $id) {
            $id = trim((string)$id);
            if ($id !== '') {
                $out[$id] = true;
            }
        }
        $GLOBALS['fleetScopeBusOverride'] = $out ? array_keys($out) : null;
    }
}

if (!function_exists('fleetScopeEffectiveBuses')) {
    /**
     * Window bus allow-list in force for this request: the page-provided
     * override when present, otherwise the built-in demo list.
     */
    function fleetScopeEffectiveBuses(): array
    {
        $override = $GLOBALS['fleetScopeBusOverride'] ?? null;
        return is_array($override) && $override ? $override : FLEET_SCOPE_BUSES;
    }
}

if (!function_exists('fleetScopeAllowsBus')) {
    /**
     * True when the bus id (case/whitespace-insensitive) is on the
     * configured allow-list.
     */
    function fleetScopeAllowsBus(string $busId): bool
    {
        $busId = strtoupper(trim($busId));
        if ($busId === '') {
            return false;
        }
        foreach (fleetScopeEffectiveBuses() as $allowed) {
            if (strtoupper(trim((string)$allowed)) === $busId) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('fleetScopeDayBusBudget')) {
    /**
     * Operating bus count for one window day: a stable per-day value inside
     * [FLEET_SCOPE_MIN_BUSES_PER_DAY, min(FLEET_SCOPE_MAX_BUSES_PER_DAY,
     * count(allowed))], biased by the day profile so weekdays and Fridays run
     * fuller and Sundays/holidays run lightest. The value moves from day to
     * day, so window days no longer all produce the same trip total.
     */
    function fleetScopeDayBusBudget(array $allowedIds, string $day, ?string $profile = null): int
    {
        $ids = [];
        foreach ($allowedIds as $id) {
            $id = trim((string)$id);
            if ($id !== '') {
                $ids[$id] = true;
            }
        }
        $available = count($ids);
        if ($available <= 0) {
            return 0;
        }

        $max = min(FLEET_SCOPE_MAX_BUSES_PER_DAY, $available);
        $min = min(FLEET_SCOPE_MIN_BUSES_PER_DAY, $max);

        $profile = strtolower(trim((string)$profile));
        if ($profile === '') {
            $profile = match ((int)date('N', strtotime($day))) {
                7 => 'sunday',
                6 => 'saturday',
                5 => 'friday',
                default => 'weekday',
            };
        }
        $anchor = FLEET_SCOPE_DAY_PROFILE_ANCHORS[$profile] ?? 0.5;
        $hash = (crc32('window-buses-' . $day) % 100000) / 100000.0;

        /* The stable per-day value moves the profile anchor across the span,
           so a weekday still lands on either side of its anchor and weekends
           stay below weekdays on average. */
        $position = $anchor + ($hash - 0.5) * FLEET_SCOPE_BUS_COUNT_JITTER;
        $position = max(0.0, min(1.0, $position));

        return $min + (int)round($position * ($max - $min));
    }
}

if (!function_exists('fleetScopeDayBuses')) {
    /**
     * The allow-listed buses that operate on one window day: a stable per-day
     * budget of FLEET_SCOPE_MIN_BUSES_PER_DAY..FLEET_SCOPE_MAX_BUSES_PER_DAY
     * buses (an explicit cap when given), chosen by a stable per-day rotation
     * so every allowed bus still operates across a period. Returns the chosen
     * ids sorted.
     */
    function fleetScopeDayBuses(array $allowedIds, string $day, ?int $maxBuses = null): array
    {
        $ids = [];
        foreach ($allowedIds as $id) {
            $id = trim((string)$id);
            if ($id !== '') {
                $ids[$id] = true;
            }
        }
        $ids = array_keys($ids);
        if ($maxBuses === null) {
            $maxBuses = fleetScopeDayBusBudget($ids, $day);
        }
        if ($maxBuses > 0 && count($ids) > $maxBuses) {
            usort($ids, static function (string $a, string $b) use ($day): int {
                $ka = (crc32('window-bus-' . $day . '-' . $a) % 100000) / 100000.0;
                $kb = (crc32('window-bus-' . $day . '-' . $b) % 100000) / 100000.0;
                return $ka === $kb ? strcmp($a, $b) : ($ka <=> $kb);
            });
            $ids = array_slice($ids, 0, $maxBuses);
        }
        sort($ids, SORT_STRING);
        return $ids;
    }
}

if (!function_exists('fleetScopeBoundsForDay')) {
    /**
     * Effective passenger/space bounds for a generated day:
     * [min passengers, max passengers, max occupied spaces] on window days,
     * or null when the default bands apply.
     */
    function fleetScopeBoundsForDay(string $day): ?array
    {
        if (!fleetScopeDayActive($day)) {
            return null;
        }
        return [FLEET_SCOPE_PAX_MIN, FLEET_SCOPE_PAX_MAX, FLEET_SCOPE_SPACE_MAX];
    }
}
