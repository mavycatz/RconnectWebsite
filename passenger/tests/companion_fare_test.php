<?php declare(strict_types=1);

/**
 * Companion fare rules and party breakdown checks.
 *
 * Uses an in-memory SQLite database for the baggage fare lookup; no live
 * database or session is required.
 */

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

require_once __DIR__ . '/../services/Companion/CompanionService.php';

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

// --- 1.1 Companion type to fare rule mapping -------------------------------

check(CompanionService::fareKeyForType('child') === 'student', 'Child rides on the student/child discount');
check(CompanionService::fareKeyForType('senior') === 'senior', 'Senior rides on the senior discount');
check(CompanionService::fareKeyForType('other') === 'regular', 'Other/Regular rides on the standard fare');
check(CompanionService::fareKeyForType('CHILD ') === 'student', 'Type matching is case/whitespace insensitive');
check(CompanionService::fareKeyForType('senior', '') === 'senior', 'Senior discount does not require an ID');
check(CompanionService::fareKeyForType('pwd') === 'pwd', 'PWD uses the existing PWD fare rule');
check(CompanionService::discountLabelForType('pwd') === 'PWD', 'PWD discount stored correctly');
check(CompanionService::companionTypeLabel('pwd') === 'PWD', 'PWD declared label retained');

check(CompanionService::discountLabelForType('child') === 'Student', 'Child label stored as Student');
check(CompanionService::discountLabelForType('senior') === 'Senior', 'Senior label stored as Senior');
check(CompanionService::discountLabelForType('other') === 'Regular', 'Other label stored as Regular');

check(CompanionService::companionTypeLabel('child') === 'Child', 'Child type label');
check(CompanionService::companionTypeLabel('senior') === 'Senior Citizen', 'Senior type label');
check(CompanionService::companionTypeLabel('other') === 'Regular', 'Regular type label');

// --- 1.2 / 1.3 Party breakdown ---------------------------------------------

$conn = new PDO('sqlite::memory:');
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$primary = [
    'id' => 1,
    'passenger_id' => 10,
    'passenger_name' => 'Main Rider',
    'discount_type' => 'Regular',
    'boarding_point' => 'Stop A',
    'destination' => 'Stop C',
    'fare' => 50.0,
    'base_fare' => 50.0,
    'discount_amount' => 0.0,
];
$companions = [
    [
        'id' => 2,
        'passenger_id' => 11,
        'passenger_name' => 'Kid Rider',
        'discount_type' => 'Student',
        'boarding_point' => 'Stop A',
        'destination' => 'Stop C',
        'fare' => 40.0,
        'base_fare' => 50.0,
        'discount_amount' => 10.0,
    ],
    [
        'id' => 3,
        'passenger_id' => 12,
        'passenger_name' => 'Lola Rider',
        'discount_type' => 'Senior',
        'boarding_point' => 'Stop A',
        'destination' => 'Stop C',
        'fare' => 40.0,
        'base_fare' => 50.0,
        'discount_amount' => 10.0,
    ],
];
$extras = [['label' => 'Baggage', 'amount' => 30.0, 'owner' => 'Main Rider']];

$breakdown = CompanionService::partyBreakdown($conn, $primary, $companions, $extras);

check(count($breakdown['lines']) === 3, 'Breakdown has the primary plus each companion');
check($breakdown['lines'][0]['role'] === 'primary', 'First line is the primary passenger');
check($breakdown['lines'][1]['type'] === 'student', 'Child companion line reports the student fare');
check($breakdown['lines'][2]['type'] === 'senior', 'Senior companion line reports the senior fare');

check($breakdown['primary_fare'] === 50.0, 'Primary fare captured');
check($breakdown['companions_fare'] === 80.0, 'Companions fare is the sum of their final fares');
check($breakdown['extra_charges_total'] === 30.0, 'Extra charge summed exactly once');
check($breakdown['party_total'] === 160.0, 'Party total = primary + companions + extras');

$el = $breakdown['lines'][0]['base_fare'] + $breakdown['lines'][1]['base_fare'] + $breakdown['lines'][2]['base_fare'];
$el -= ($breakdown['lines'][0]['discount_amount'] + $breakdown['lines'][1]['discount_amount'] + $breakdown['lines'][2]['discount_amount']);
check(round($el + $breakdown['extra_charges_total'], 2) === $breakdown['party_total'], 'Total equals the sum of its parts');
check($breakdown['lines'][0]['extra_charges'][0]['amount'] === 30.0, 'Extra attached to its owner line');

// Eligible companions never pay the full fare and never receive a double discount.
foreach ([$breakdown['lines'][1], $breakdown['lines'][2]] as $line) {
    check($line['final_fare'] < $line['base_fare'], 'Eligible companion pays less than the standard fare');
    check(round($line['base_fare'] - $line['final_fare'], 2) === $line['discount_amount'], 'Exactly one discount applied');
}

// A regular companion is never discounted.
$regularOnly = CompanionService::partyBreakdown($conn, $primary, [[
    'id' => 4,
    'passenger_id' => 13,
    'passenger_name' => 'Plain Rider',
    'discount_type' => 'Regular',
    'fare' => 50.0,
    'base_fare' => 50.0,
    'discount_amount' => 0.0,
]], []);
check($regularOnly['lines'][1]['discount_amount'] === 0.0, 'Regular companion receives no discount');
check($regularOnly['party_total'] === 100.0, 'Regular party total is undiscounted');

// --- 1.3 Baggage extra via the existing pricing rules ----------------------

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE fare_pricing (
    id INTEGER PRIMARY KEY,
    destination TEXT,
    display_order INTEGER,
    regular_fare REAL,
    student_fare REAL,
    special_fare REAL,
    route_name TEXT
)");
$db->exec("INSERT INTO fare_pricing (id, destination, display_order, regular_fare, student_fare, special_fare, route_name)
    VALUES (1, 'Stop A', 1, 0, 0, 0, 'Route X'),
           (2, 'Stop B', 2, 20, 16, 24, 'Route X'),
           (3, 'Stop C', 3, 30, 24, 36, 'Route X')");

$baggage = CompanionService::priceBaggageExtra($db, 'Route X', 'Stop A', 'Stop C', 2, 'regular');
check($baggage === 60.0, 'Baggage extra reuses the existing pricing rules (30 x 2 spaces)');

$sameStop = CompanionService::priceBaggageExtra($db, 'Route X', 'Stop A', 'Stop A', 1, 'regular');
check($sameStop === 15.0, 'Same-stop baggage uses the minimum fare');

$unknown = CompanionService::priceBaggageExtra($db, 'Route X', 'Nowhere', 'Stop C', 1, 'regular');
check($unknown === null, 'Unresolvable extra returns null instead of fabricating a fare');

// --- 2.1 API payload shape -------------------------------------------------

$payload = CompanionService::companionsPayload([[
    'id' => 2,
    'passenger_id' => 11,
    'passenger_name' => 'Kid Rider',
    'passenger_type' => 'Child',
    'discount_type' => 'Student',
    'fare' => 40.0,
    'base_fare' => 50.0,
    'discount_amount' => 10.0,
]]);
check(count($payload) === 1, 'Payload maps each companion row');
check($payload[0]['companion_type'] === 'Child', 'Payload keeps the historical companion type label');
check($payload[0]['type_label'] === 'Student', 'Payload carries the discounted type label');
check($payload[0]['base_fare'] === 50.0 && $payload[0]['discount_amount'] === 10.0 && $payload[0]['fare'] === 40.0, 'Payload carries fare detail');

foreach (['lines', 'primary_fare', 'companions_fare', 'extra_charges_total', 'party_total'] as $key) {
    check(array_key_exists($key, $breakdown), "Breakdown exposes '$key'");
}

echo "$checks companion fare checks passed.\n";
