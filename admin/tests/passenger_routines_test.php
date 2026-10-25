<?php declare(strict_types=1);

/**
 * Daily-routine rule checks: cohort sizing/density, deterministic cohort and
 * attendance rolls, and the regularity/weekday attendance model.
 * Pure functions only - no database is required.
 */

require_once __DIR__ . '/../api/TripDashboard/passenger_routines.php';

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

/* ---------------------------- occupations ---------------------------- */

check(routineIsCommuteOccupation('worker') === true, 'Workers commute');
check(routineIsCommuteOccupation('student') === true, 'Students commute');
check(routineIsCommuteOccupation('vendor') === false, 'Vendors are not out-and-back commuters');
check(routineIsCommuteOccupation('senior') === false, 'Seniors are not commuters');
check(routineIsCommuteOccupation('commuter') === false, 'General commuters follow their own windows');

/* ------------------------------ cohort ------------------------------ */

check(routineCohortTarget(0, 5000) === 0, 'No seats means no cohort');
check(routineCohortTarget(120, 5000) === 86, '120 seats over a 5000 pool gives an 86-rider cohort');
check(routineCohortTarget(120, 50) === 50, 'Cohort never exceeds the pool');
check(routineCohortTarget(10, 5000) === ROUTINE_COHORT_MIN, 'Tiny days still keep the minimum cohort floor');

$density = routineCohortDensity(120, 5000);
check($density > 0.0 && $density < 0.1, 'Cohort density is a small share of a large pool');
check(routineCohortDensity(120, 50) === 1.0, 'A pool smaller than the target means everyone is in the cohort');
check(routineCohortDensity(0, 5000) === 0.0, 'No seats means no cohort density');

check(routineInCohort('Maria Santos', 0.0) === false, 'Zero density admits nobody');
check(routineInCohort('Maria Santos', 1.0) === true, 'Full density admits everybody');
check(
    routineInCohort('Juan Dela Cruz', 0.5) === routineInCohort('Juan Dela Cruz', 0.5),
    'Cohort membership is deterministic'
);

/* ---------------------------- attendance ---------------------------- */

$regularWeekday = routineAttendanceProbability(0.9, 'worker', 'weekday');
$regularWeekend = routineAttendanceProbability(0.9, 'worker', 'weekend');
$occasional = routineAttendanceProbability(0.2, 'worker', 'weekday');
$vendorWeekend = routineAttendanceProbability(0.9, 'vendor', 'weekend');

check(abs($regularWeekday - 0.925) < 0.0001, 'Regular weekday attendance is 0.925');
check(abs($regularWeekend - 0.23125) < 0.0001, 'Worker weekends are damped to a quarter');
check(abs($occasional - 0.40) < 0.0001, 'Occasional weekday attendance is 0.40');
check(abs($vendorWeekend - 0.925) < 0.0001, 'Non-commute occupations keep weekday odds on weekends');
check(routineAttendanceProbability(2.0, 'worker', 'weekday') <= 0.97, 'Attendance is capped');
check(routineAttendanceProbability(-1.0, 'worker', 'weekday') >= 0.05, 'Attendance has a floor');

$weekdayHits = 0;
$occasionalHits = 0;
for ($i = 0; $i < 400; $i++) {
    $name = 'Profile ' . $i;
    if (routineAttends($name, '2026-09-07', 0.9, 'worker', 'weekday')) { $weekdayHits++; }
    if (routineAttends($name, '2026-09-07', 0.2, 'worker', 'weekday')) { $occasionalHits++; }
}
check($weekdayHits > 330 && $weekdayHits < 400, 'Regular band attends most weekdays (sampled)');
check($occasionalHits > 100 && $occasionalHits < 220, 'Occasional band attends sparsely (sampled)');
check($weekdayHits > $occasionalHits, 'Regular band attends more than occasional band');

$studentWeekend = 0;
$studentWeekday = 0;
for ($i = 0; $i < 400; $i++) {
    $name = 'Student ' . $i;
    if (routineAttends($name, '2026-09-06', 0.85, 'student', 'weekend')) { $studentWeekend++; }
    if (routineAttends($name, '2026-09-07', 0.85, 'student', 'weekday')) { $studentWeekday++; }
}
check($studentWeekday > 300, 'Students attend most weekdays (sampled)');
check($studentWeekend < 130, 'Students rarely attend weekends (sampled)');

$twice = routineAttends('Repeat Check', '2026-09-07', 0.6, 'vendor', 'weekday');
check($twice === routineAttends('Repeat Check', '2026-09-07', 0.6, 'vendor', 'weekday'), 'Attendance is deterministic per name and day');

echo "$checks passenger-routine checks passed.\n";
