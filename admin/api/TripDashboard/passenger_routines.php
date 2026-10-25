<?php

declare(strict_types=1);

/**
 * Daily-routine rules for window-day passenger generation.
 *
 * Pure functions only: the rider cohort sizing, the deterministic cohort
 * membership roll, and the attendance probability that turns a profile's
 * regularity band into how often its owner rides. Keeping the rules here
 * makes them unit-testable without booting the application or the database.
 */

if (!defined('ROUTINE_WEEKEND_COMMUTE_FACTOR')) {
    define('ROUTINE_WEEKEND_COMMUTE_FACTOR', 0.25);
}
if (!defined('ROUTINE_COHORT_SHARE')) {
    define('ROUTINE_COHORT_SHARE', 0.70);   // share of the cohort attending a typical day
}
if (!defined('ROUTINE_COHORT_MIN')) {
    define('ROUTINE_COHORT_MIN', 40);
}
if (!defined('ROUTINE_COHORT_MAX')) {
    define('ROUTINE_COHORT_MAX', 400);
}

if (!function_exists('routineRoll')) {
    /**
     * Stable 0..1 roll for a key, identical across runs. Kept local to the
     * routine rules so cohort and attendance choices stay reproducible.
     */
    function routineRoll(string $key): float
    {
        return (crc32($key) % 100000) / 100000.0;
    }
}

if (!function_exists('routineIsCommuteOccupation')) {
    /**
     * Workers and students are the commuters whose routines are out-and-back.
     */
    function routineIsCommuteOccupation(string $occupation): bool
    {
        return $occupation === 'worker' || $occupation === 'student';
    }
}

if (!function_exists('routineCohortTarget')) {
    /**
     * Size of the stable rider cohort: roughly two rides per attending rider
     * fills the window day's seats at the expected attendance share.
     */
    function routineCohortTarget(int $dailySeats, int $poolSize): int
    {
        if ($dailySeats <= 0 || $poolSize <= 0) {
            return 0;
        }
        $target = (int)ceil($dailySeats / 2 / ROUTINE_COHORT_SHARE);
        $target = max(ROUTINE_COHORT_MIN, min(ROUTINE_COHORT_MAX, $target));
        return min($target, $poolSize);
    }
}

if (!function_exists('routineCohortDensity')) {
    function routineCohortDensity(int $dailySeats, int $poolSize): float
    {
        if ($poolSize <= 0) {
            return 0.0;
        }
        $target = routineCohortTarget($dailySeats, $poolSize);
        if ($target <= 0) {
            return 0.0;
        }
        if ($target >= $poolSize) {
            return 1.0;
        }
        return $target / $poolSize;
    }
}

if (!function_exists('routineInCohort')) {
    function routineInCohort(string $name, float $density): bool
    {
        if ($density <= 0.0) {
            return false;
        }
        return $density >= 1.0 || routineRoll('routine-cohort-' . $name) < $density;
    }
}

if (!function_exists('routineAttendanceProbability')) {
    /**
     * Attendance odds for one window day: base 0.25 + 0.75 * regularity so
     * regular profiles ride most days and occasional profiles sparsely, with
     * worker/student commutes damped on weekends and holidays.
     */
    function routineAttendanceProbability(float $regularity, string $occupation, string $dayType): float
    {
        $regularity = max(0.0, min(1.0, $regularity));
        $p = 0.25 + 0.75 * $regularity;
        if (routineIsCommuteOccupation($occupation) && $dayType !== 'weekday') {
            $p *= ROUTINE_WEEKEND_COMMUTE_FACTOR;
        }
        return max(0.05, min(0.97, $p));
    }
}

if (!function_exists('routineAttends')) {
    function routineAttends(string $name, string $day, float $regularity, string $occupation, string $dayType): bool
    {
        return routineRoll('routine-attend-' . $name . '-' . $day)
            < routineAttendanceProbability($regularity, $occupation, $dayType);
    }
}
