<?php declare(strict_types=1);

/**
 * Bus-position matching checks for the One-Time GPS boarding flow.
 *
 * Covers the point-to-segment nearest match (five required bus positions,
 * the Mataas na Kahoy / Gen. Ricarte anomaly, exact-distance ties, distance
 * cap, invalid input), the display-order-based remaining destinations, and
 * the route_stops reconciliation fallback for stops the reference misses.
 */

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

require_once __DIR__ . '/../services/BusBoardingService.php';

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

/**
 * The direction's reference stops as an ordered fare-stop list (display_order
 * follows the reference order), which is the shape the matcher consumes.
 */
function orderedStopsFromDirection(string $directionKey): array
{
    $stops = [];
    $order = 0;

    foreach (PASSENGER_DESTINATION_ROUTE_SEGMENTS[$directionKey] as $entry) {
        $order++;
        $stops[] = [
            'fare_id' => $order,
            'name' => (string) $entry['name'],
            'display_order' => $order,
            'route_stop_id' => null,
            'km_marker' => null,
            'fallback_lat' => null,
            'fallback_lng' => null,
        ];
    }

    return $stops;
}

$forwardStops = orderedStopsFromDirection('cabanatuan-rizal');

// --- Five required positions ------------------------------------------------

$positions = [
    ['Cabanatuan terminal', 15.472229, 120.969183, 0, 'Cabanatuan Central Terminal'],
    ['Pinagpanaan', 15.548218, 120.933455, 5, 'Pinagpanaan'],
    ['Homestead 1', 15.566468, 120.953142, 7, 'Homestead 1'],
    ['Del Pilar', 15.689167, 121.078555, 18, 'Del Pilar'],
    ['Rizal terminal', 15.705381, 121.099504, 19, 'Rizal/Pob Sur Terminal'],
];

foreach ($positions as [$label, $lat, $lng, $expectedIndex, $expectedName]) {
    $match = passengerMatchBusPositionToOrderedStops($forwardStops, 'cabanatuan-rizal', $lat, $lng);

    check($match !== null, "{$label} matches a route segment");
    check(
        $match['index'] === $expectedIndex,
        "{$label} matched index {$match['index']}, expected {$expectedIndex}"
    );
    check(
        strpos((string) $forwardStops[$match['index']]['name'], $expectedName) !== false,
        "{$label} matched the expected stop ({$forwardStops[$match['index']]['name']})"
    );
}

// --- Remaining destinations follow display order ----------------------------

$pinagpanaan = passengerMatchBusPositionToOrderedStops(
    $forwardStops,
    'cabanatuan-rizal',
    15.548218,
    120.933455
);
$destinations = array_slice($forwardStops, $pinagpanaan['index'] + 1);

check(count($forwardStops) === 20, 'Forward route carries all 20 stops');
check(count($destinations) === 14, 'Pinagpanaan leaves 14 destinations ahead');
check(
    $destinations[0]['name'] === 'Baluga/Sto. Niño, Talavera',
    'The first destination after Pinagpanaan is the next stop in order'
);
check(
    !in_array('Pinagpanaan, Talavera', array_column($destinations, 'name'), true),
    'The boarding stop itself is not offered as a destination'
);
check(
    !in_array('Valdefuente', array_column($destinations, 'name'), true),
    'Stops behind the boarding point are not offered'
);

$terminal = passengerMatchBusPositionToOrderedStops($forwardStops, 'cabanatuan-rizal', 15.705381, 121.099504);
check(count(array_slice($forwardStops, $terminal['index'] + 1)) === 0, 'The Rizal terminal leaves no destinations');

// --- Mataas na Kahoy / Gen. Ricarte anomaly ---------------------------------

$anomaly = passengerMatchBusPositionToOrderedStops($forwardStops, 'cabanatuan-rizal', 15.622267, 121.001518);
check($anomaly !== null, 'The Mataas na Kahoy midpoint matches a segment');
check($anomaly['index'] === 11, 'The Mataas na Kahoy midpoint stays on its own segment, not Gen. Ricarte');

// --- Return direction --------------------------------------------------------

$reverseStops = orderedStopsFromDirection('rizal-cabanatuan');
check(count($reverseStops) === 21, 'The reverse reference carries the origin segments plus the 19 route stops');

$reverseOrigin = passengerMatchBusPositionToOrderedStops($reverseStops, 'rizal-cabanatuan', 15.705381, 121.099504);
check($reverseOrigin !== null && $reverseOrigin['index'] === 0, 'A return trip at the Rizal terminal matches the reverse origin stop');

$reverseDelPilar = passengerMatchBusPositionToOrderedStops($reverseStops, 'rizal-cabanatuan', 15.689167, 121.078555);
check($reverseDelPilar !== null && $reverseDelPilar['index'] === 1, 'A return trip at Del Pilar matches the reverse Del Pilar stop');

$reverseMatch = passengerMatchBusPositionToOrderedStops($reverseStops, 'rizal-cabanatuan', 15.688171, 121.078333);
check($reverseMatch !== null && $reverseMatch['index'] === 2, 'A return trip near Aglipay matches the reverse Aglipay stop');

$reverseTerminal = passengerMatchBusPositionToOrderedStops($reverseStops, 'rizal-cabanatuan', 15.472336, 120.969126);
check(
    $reverseTerminal !== null && $reverseTerminal['index'] === count($reverseStops) - 1,
    'A return trip at the Cabanatuan terminal matches the last reverse stop'
);

// A fare list that starts at Aglipay (no terminal/Del Pilar stop of its own,
// the live shape) must still match a bus at the Rizal origin through the
// leading origin segments, instead of failing with BUS_OFF_ROUTE.
$liveShapeStops = array_slice($reverseStops, 2);
$liveOrigin = passengerMatchBusPositionToOrderedStops($liveShapeStops, 'rizal-cabanatuan', 15.705381, 121.099504);
check($liveOrigin !== null, 'A live-shaped return list still matches a bus at the Rizal terminal');
check($liveOrigin['index'] === 0, 'The Rizal origin position is attributed to the first stop (Aglipay)');
check($liveOrigin['distance_km'] < 0.05, 'The Rizal terminal matches the leading origin coverage at the terminal');

$liveDelPilar = passengerMatchBusPositionToOrderedStops($liveShapeStops, 'rizal-cabanatuan', 15.692, 121.082);
check($liveDelPilar !== null && $liveDelPilar['index'] === 0, 'A bus on the origin stretch is attributed to the first stop');
check($liveDelPilar['distance_km'] < 1.0, 'The origin stretch matches within the accepted distance');

// --- Exact-distance tie and fallback segments -------------------------------

$tieStops = [
    [
        'fare_id' => 1,
        'name' => 'Alpha Stop 000',
        'display_order' => 1,
        'route_stop_id' => null,
        'km_marker' => null,
        'fallback_lat' => 15.5,
        'fallback_lng' => 121.484375,
    ],
    [
        'fare_id' => 2,
        'name' => 'Beta Stop 111',
        'display_order' => 2,
        'route_stop_id' => null,
        'km_marker' => null,
        'fallback_lat' => 15.5,
        'fallback_lng' => 121.515625,
    ],
];

$tie = passengerMatchBusPositionToOrderedStops($tieStops, 'cabanatuan-rizal', 15.5, 121.5);
check($tie !== null, 'An equidistant position still matches');
check($tie['index'] === 0, 'An exact distance tie keeps the earlier stop in display order');
check($tie['segment_source'] === 'route_stops', 'The fallback coordinate is used when the reference does not cover a stop');

// --- Off-route and invalid input rejection ----------------------------------

check(
    passengerMatchBusPositionToOrderedStops($forwardStops, 'cabanatuan-rizal', 14.0, 119.0) === null,
    'A position far off the route is rejected'
);
check(
    passengerMatchBusPositionToOrderedStops($forwardStops, 'cabanatuan-rizal', 15.5487, 120.933455, 0.001) === null,
    'A position beyond the distance cap is rejected even on the route'
);
check(
    passengerMatchBusPositionToOrderedStops($forwardStops, 'cabanatuan-rizal', null, null) === null,
    'Invalid coordinates never match'
);
check(
    passengerMatchBusPositionToOrderedStops($forwardStops, 'no-such-direction', 15.5, 121.0) === null,
    'An unknown direction never matches'
);

// --- route_stops reconciliation (stops the reference does not cover) --------

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("
    CREATE TABLE route_stops (
        id INTEGER PRIMARY KEY,
        route_name TEXT,
        stop_name TEXT,
        sequence_order INT,
        km_marker INT,
        fare_pricing_id INT,
        latitude REAL,
        longitude REAL
    );
    INSERT INTO route_stops (id, route_name, stop_name, sequence_order, km_marker, fare_pricing_id, latitude, longitude)
    VALUES
        (501, 'Cabanatuan to Rizal Route', 'Mystery Stop 42', 4, 42, 999, 15.560000, 120.970000),
        (502, 'Cabanatuan to Rizal Route', 'Pinagpanaan, Talavera', 5, 8, 5, 15.548218, 120.933455),
        (503, 'Rizal to Cabanatuan Route', 'Mystery Stop 42', 4, 42, 999, 15.560000, 120.970000)
");

$fallbacks = passengerBusRouteStopFallbacks($db, 'Cabanatuan-Rizal');
check(count($fallbacks['by_fare']) === 2, 'Only the trip route\'s route_stops rows are reconciled');
check(isset($fallbacks['by_fare'][999]), 'The stop is keyed by fare_pricing_id');
check(
    isset($fallbacks['by_name']['mystery stop 42']),
    'The stop is keyed by normalized name'
);

$fareStops = [
    ['id' => 999, 'destination' => 'Mystery Stop 42', 'display_order' => 4],
    ['id' => 5, 'destination' => 'Pinagpanaan, Talavera', 'display_order' => 5],
];
$ordered = passengerBusOrderedStopsWithFallbacks($fareStops, $fallbacks);

check($ordered[0]['route_stop_id'] === 501, 'The fare id bridge supplies the route_stops id');
check($ordered[0]['km_marker'] === 42, 'The km marker is carried for the boarding record');
check($ordered[0]['fallback_lat'] === 15.56, 'The route_stops coordinate is carried as the fallback');
check($ordered[1]['route_stop_id'] === 502, 'A reference-covered stop still resolves its route_stops row');

$mystery = passengerMatchBusPositionToOrderedStops($ordered, 'cabanatuan-rizal', 15.56, 120.97);
check($mystery !== null, 'A stop absent from the reference still matches');
check($mystery['index'] === 0, 'The absent stop matches through its own route_stops coordinate');
check($mystery['segment_source'] === 'route_stops', 'The match is marked as coming from route_stops');

$byNameOnly = passengerBusOrderedStopsWithFallbacks(
    [['id' => 0, 'destination' => 'Mystery Stop 42', 'display_order' => 4]],
    $fallbacks
);
check($byNameOnly[0]['route_stop_id'] === 501, 'Name reconciliation resolves when the fare id bridge is absent');

$missing = passengerBusOrderedStopsWithFallbacks(
    [['id' => 12345, 'destination' => 'Nowhere Stop 999', 'display_order' => 9]],
    $fallbacks
);
check($missing[0]['fallback_lat'] === null, 'A stop with neither bridge nor reference keeps no coordinates');
check(
    passengerMatchBusPositionToOrderedStops($missing, 'cabanatuan-rizal', 15.5, 121.0) === null,
    'A stop without a usable segment is skipped rather than guessed'
);

echo "{$checks} bus boarding match checks passed.\n";
