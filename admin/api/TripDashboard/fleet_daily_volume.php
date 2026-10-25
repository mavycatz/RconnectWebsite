<?php

declare(strict_types=1);

/**
 * Fleet daily volume, bus allocation, and passenger load bands shared by the
 * data generator page.
 *
 * Pure functions only: the day-type-adjusted fleet-wide trip target that
 * replaces the old per-bus trip default, the deterministic allocation of that
 * target across the available buses, and the seat-scaled load band used for
 * capacity-aware passenger generation. Keeping the rules here makes them
 * testable without booting the application or touching the database.
 */

if (!defined('FLEET_DAILY_TRIPS_MIN')) {
    define('FLEET_DAILY_TRIPS_MIN', 13);
}
if (!defined('FLEET_DAILY_TRIPS_MAX')) {
    define('FLEET_DAILY_TRIPS_MAX', 18);
}
if (!defined('FLEET_DAILY_TRIPS_CEILING')) {
    define('FLEET_DAILY_TRIPS_CEILING', FLEET_DAILY_TRIPS_MAX);
}
if (!defined('FLEET_DAILY_TRIPS_PER_BUS_CAP')) {
    define('FLEET_DAILY_TRIPS_PER_BUS_CAP', 4);
}
if (!defined('FLEET_LOAD_SEAT_SHARE_MIN')) {
    define('FLEET_LOAD_SEAT_SHARE_MIN', 0.30);
}
if (!defined('FLEET_LOAD_SEAT_SHARE_MAX')) {
    define('FLEET_LOAD_SEAT_SHARE_MAX', 0.70);
}
if (!defined('FLEET_FALLBACK_SEATS')) {
    define('FLEET_FALLBACK_SEATS', 50);
}
if (!defined('FLEET_DEMAND_SCENARIOS')) {
    define('FLEET_DEMAND_SCENARIOS', ['low' => 0.6, 'normal' => 1.0, 'high' => 1.4]);
}
if (!defined('FLEET_DAY_PROFILE_ANCHORS')) {
    /* Position of each day profile inside the 13-18 band: weekdays and Fridays
       bias to the upper half, Saturday to the middle, Sunday and holidays to
       the lower half. */
    define('FLEET_DAY_PROFILE_ANCHORS', [
        'weekday'  => 0.75,
        'friday'   => 0.75,
        'saturday' => 0.50,
        'sunday'   => 0.25,
        'holiday'  => 0.15,
    ]);
}

if (!function_exists('fleetStableUnit')) {
    /**
     * Stable 0-1 value for a key, independent of the RNG seed, so fleet
     * selection and base targets stay reproducible across runs.
     */
    function fleetStableUnit(string $key): float
    {
        return (crc32($key) % 100000) / 100000.0;
    }
}

if (!function_exists('fleetScenarioMultiplier')) {
    /**
     * Demand multiplier for a named scenario. Unknown scenarios fall back to
     * the normal preset.
     */
    function fleetScenarioMultiplier(string $scenario): float
    {
        $key = strtolower(trim($scenario));
        return FLEET_DEMAND_SCENARIOS[$key] ?? FLEET_DEMAND_SCENARIOS['normal'];
    }
}

if (!function_exists('fleetDayProfile')) {
    /**
     * Service profile for a Y-m-d day from its weekday alone: friday,
     * saturday, sunday, or weekday. Holiday detection stays with the caller.
     */
    function fleetDayProfile(string $day): string
    {
        $n = (int)date('N', strtotime($day));
        return match ($n) {
            7 => 'sunday',
            6 => 'saturday',
            5 => 'friday',
            default => 'weekday',
        };
    }
}

if (!function_exists('fleetDayProfileAnchor')) {
    /**
     * Band position (0-1) for a day profile: weekdays/Fridays sit high,
     * Saturday in the middle, Sunday and holidays low. Unknown profiles stay
     * neutral.
     */
    function fleetDayProfileAnchor(string $profile): float
    {
        $key = strtolower(trim($profile));
        return FLEET_DAY_PROFILE_ANCHORS[$key] ?? 0.5;
    }
}

if (!function_exists('fleetDemandAnchor')) {
    /**
     * Band position (0-1) for a demand multiplier: the low preset (0.6) maps
     * to 0, normal (1.0) to 0.5, high (1.4) to 1.0, with everything between
     * interpreted proportionally. Invalid multipliers behave as normal demand.
     */
    function fleetDemandAnchor(float $multiplier): float
    {
        if (!is_finite($multiplier) || $multiplier <= 0) {
            $multiplier = 1.0;
        }
        return max(0.0, min(1.0, ($multiplier - 0.6) / 0.8));
    }
}

if (!function_exists('fleetDailyTarget')) {
    /**
     * Fleet-wide completed trip target for one operating day, always inside
     * the 13-18 band.
     *
     * A configured target (daily_trips_target) is a clamped center: the day
     * profile and the demand multiplier move it by at most one trip each and
     * the result is clamped back into the band. Without a configured target a
     * stable per-day value is blended with the day-profile and demand bias so
     * days vary inside the band (weekdays heaviest, weekends/holidays
     * lighter).
     *
     * Options: daily_trips_target (int, 0 = automatic), day_profile
     * (weekday|friday|saturday|sunday|holiday), demand_multiplier (float).
     */
    function fleetDailyTarget(string $day, array $opts = []): int
    {
        $min = FLEET_DAILY_TRIPS_MIN;
        $max = FLEET_DAILY_TRIPS_MAX;
        $span = max(1, $max - $min);

        $profile = trim((string)($opts['day_profile'] ?? ''));
        if ($profile === '') {
            $profile = fleetDayProfile($day);
        }
        $profileAnchor = fleetDayProfileAnchor($profile);
        $demandAnchor = fleetDemandAnchor((float)($opts['demand_multiplier'] ?? 1.0));

        $configured = (int)($opts['daily_trips_target'] ?? 0);
        if ($configured > 0) {
            $center = max($min, min($max, $configured));
            $shift = 0;
            if ($profileAnchor <= 0.25) {
                $shift--;   // Sundays and holidays are lighter
            }
            if ($demandAnchor < 0.35) {
                $shift--;   // low demand is lighter
            } elseif ($demandAnchor > 0.65) {
                $shift++;   // high demand is heavier
            }
            return max($min, min($max, $center + $shift));
        }

        $position = 0.55 * $profileAnchor
            + 0.30 * $demandAnchor
            + 0.15 * fleetStableUnit('fleet-base-' . $day);
        $position = max(0.0, min(1.0, $position));

        return $min + (int)round($position * $span);
    }
}

if (!function_exists('fleetAllocateTrips')) {
    /**
     * Distribute a fleet-wide trip target across bus ids.
     *
     * Buses are ranked by a stable per-day key, so the same day always picks
     * the same units. Every ranked bus takes its first trip before any bus
     * takes a second, and no bus may exceed the per-bus cap (the hard
     * FLEET_DAILY_TRIPS_PER_BUS_CAP bound, tightened by max_trips_per_bus when
     * configured). Returns bus id => trip count for the buses that run.
     */
    function fleetAllocateTrips(array $busIds, string $day, int $target, array $opts = []): array
    {
        $ids = [];
        foreach ($busIds as $id) {
            $id = trim((string)$id);
            if ($id !== '') {
                $ids[$id] = true;
            }
        }
        $ids = array_keys($ids);
        if (!$ids || $target <= 0) {
            return [];
        }

        $cap = (int)($opts['max_trips_per_bus'] ?? 0);
        if ($cap <= 0) {
            $cap = FLEET_DAILY_TRIPS_PER_BUS_CAP;
        }
        $cap = max(1, min(FLEET_DAILY_TRIPS_PER_BUS_CAP, $cap));

        usort($ids, static function (string $a, string $b) use ($day): int {
            $ka = fleetStableUnit('fleet-bus-' . $day . '-' . $a);
            $kb = fleetStableUnit('fleet-bus-' . $day . '-' . $b);
            if ($ka === $kb) {
                return strcmp($a, $b);
            }
            return $ka <=> $kb;
        });

        $allocation = [];
        $remaining = $target;
        while ($remaining > 0) {
            $placed = false;
            foreach ($ids as $id) {
                if ($remaining <= 0) {
                    break;
                }
                $current = $allocation[$id] ?? 0;
                if ($current >= $cap) {
                    continue;
                }
                $allocation[$id] = $current + 1;
                $remaining--;
                $placed = true;
            }
            if (!$placed) {
                break;   // the fleet's per-bus cap cannot absorb the target
            }
        }
        return $allocation;
    }
}

if (!function_exists('fleetNormalizeSeats')) {
    /**
     * Seat capacity for load math: the real value when positive, otherwise the
     * fallback (sample) bus capacity.
     */
    function fleetNormalizeSeats($seats): int
    {
        $value = (int)$seats;
        return $value > 0 ? $value : FLEET_FALLBACK_SEATS;
    }
}

if (!function_exists('loadBoundsForSeats')) {
    /**
     * Capacity-aware passenger band [min, max] for a bus: 30%-70% of its
     * seats (50 seats -> [15, 35]), clamped so the maximum never exceeds the
     * bus's capacity. Unknown/zero capacity falls back to the sample bus.
     */
    function loadBoundsForSeats($seats): array
    {
        $capacity = fleetNormalizeSeats($seats);
        $min = max(1, (int)ceil($capacity * FLEET_LOAD_SEAT_SHARE_MIN));
        $max = (int)round($capacity * FLEET_LOAD_SEAT_SHARE_MAX);
        $max = max($min, min($capacity, $max));
        return [$min, $max];
    }
}
