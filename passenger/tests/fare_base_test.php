<?php declare(strict_types=1);

/**
 * api/calculate-fare base vs discounted fare checks.
 *
 * The shared segment-fare helper must report the undiscounted base fare from
 * the regular column while the charged fare reflects the passenger discount.
 */

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

require_once __DIR__ . '/../../fare-engine/utils/FareCalculator.php';

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

$boarding = ['destination' => 'Stop A', 'display_order' => 1, 'regular_fare' => 0, 'student_fare' => 0, 'special_fare' => 0];
$destination = ['destination' => 'Stop C', 'display_order' => 3, 'regular_fare' => 30, 'student_fare' => 24, 'special_fare' => 36];

$base = FareCalculator::segmentFare($boarding, $destination, 'regular_fare');
$discounted = FareCalculator::segmentFare($boarding, $destination, 'student_fare');

check($base === 30.0, 'base_fare is the standard fare');
check($discounted === 24.0, 'fare is the discounted fare');
check($discounted < $base, 'discounted fare is less than the standard fare');

$intermediate = ['destination' => 'Stop B', 'display_order' => 2, 'regular_fare' => 5, 'student_fare' => 4, 'special_fare' => 6];
$segmentBase = FareCalculator::segmentFare($intermediate, $destination, 'regular_fare');
$segmentDisc = FareCalculator::segmentFare($intermediate, $destination, 'student_fare');

check($segmentBase === 25.0, 'mid-route base fare is the difference of standard fares');
check($segmentDisc === 20.0, 'mid-route discounted fare is the difference of discounted fares');

$sameStopBase = FareCalculator::segmentFare($destination, $destination, 'regular_fare');
check($sameStopBase === 15.0, 'same-stop base fare falls back to the minimum fare');

// --- calculateFare terminal-origin rule -------------------------------------
// The rule must return the destination fare but still respect the minimum
// fare, so an unpopulated/zero fare column never surfaces as an unavailable
// (zero) fare in the passenger flow.
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/fare-engine/utils/calculateFare.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec("
    CREATE TABLE bus_trips (id INTEGER PRIMARY KEY, route_name TEXT);
    INSERT INTO bus_trips (id, route_name) VALUES (1, 'Test-Route');
    CREATE TABLE fare_pricing (id INTEGER PRIMARY KEY, route_name TEXT, destination TEXT, display_order INT,
        regular_fare REAL, student_fare REAL, special_fare REAL);
    INSERT INTO fare_pricing (id, route_name, destination, display_order, regular_fare, student_fare, special_fare) VALUES
        (1, 'Test-Route', 'Origin Stop', 1, 0, 0, 0),
        (2, 'Test-Route', 'Mid Stop', 2, 5, 4, 0),
        (3, 'Test-Route', 'Far Stop', 3, 30, 24, 0);
");

(new ReflectionProperty(App\Core\Database::class, 'instance'))->setValue(null, $db);

$terminalRegular = calculateFare(1, 'Origin Stop', 'Far Stop', 'regular');
check((float) $terminalRegular['fare'] === 30.0, 'terminal-origin fare is the destination fare');

$terminalSpecial = calculateFare(1, 'Origin Stop', 'Far Stop', 'special');
check((float) $terminalSpecial['fare'] === 15.0, 'terminal-origin fare floors a zero destination fare at the minimum');

$midRegular = calculateFare(1, 'Mid Stop', 'Far Stop', 'regular');
check((float) $midRegular['fare'] === 25.0, 'mid-route fare is the destination minus the boarding fare');

echo "$checks fare base checks passed.\n";
