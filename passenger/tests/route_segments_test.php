<?php declare(strict_types=1);

/**
 * Route-segment reference checks for the One-Time GPS boarding flow.
 *
 * The centralized coordinate reference already lives in
 * PASSENGER_DESTINATION_ROUTE_SEGMENTS (DestinationRouteGeometry.php), shared
 * with the destination-alert geometry. These checks pin it so the boarding
 * matcher cannot silently drift: every stop name resolves in order in its
 * direction, the reverse direction resolves its own north-to-south sequence,
 * the five required bus positions project onto the expected segment, and
 * unknown names or directions return null.
 */

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

require_once __DIR__ . '/../services/DestinationRouteGeometry.php';

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

$forwardNames = [
    'Cabanatuan Central Terminal → Daang Sarile',
    'Valdefuente',
    'Mayapyap',
    'Caalibangbangan',
    'San Miguel Munti, Talavera',
    'Pinagpanaan, Talavera',
    'Baluga/Sto. Niño, Talavera',
    'Homestead 1, Talavera',
    'Homestead 2, Talavera',
    'San Ricardo/Casile, Talavera',
    'Gen. Luna, Llanera',
    'Mataas na Kahoy, Llanera',
    'General Ricarte, Llanera',
    'Brgy. Mabini, Llanera, Nueva Ecija',
    'Inanama, Llanera',
    'Bicos, Rizal',
    'Mangahan, Rizal',
    'Aglipay, Rizal',
    'Del Pilar, Rizal',
    'Rizal/Pob Sur Terminal',
];

$forward = PASSENGER_DESTINATION_ROUTE_SEGMENTS['cabanatuan-rizal'];
check(count($forward) === 20, 'Forward direction carries all 20 supplied segments');

foreach ($forwardNames as $index => $name) {
    check(($forward[$index]['name'] ?? '') === $name, "Forward segment {$index} is named {$name}");
    check(
        passengerDestinationRouteSegmentIndexByName('cabanatuan-rizal', $name) === $index,
        "Forward name resolves in order: {$name}"
    );
    check(
        passengerDestinationRouteAnchorByName('cabanatuan-rizal', $name) !== null,
        "Forward anchor resolves: {$name}"
    );
}

// Pin the supplied start/end coordinates for representative rows.
check($forward[0]['start'] === [15.472229, 120.969183], 'Cabanatuan terminal segment start pinned');
check($forward[0]['end'] === [15.506043, 120.969857], 'Cabanatuan terminal segment end pinned');
check($forward[5]['start'] === [15.544254, 120.932203], 'Pinagpanaan segment pinned');
check($forward[5]['end'] === [15.555177, 120.940799], 'Pinagpanaan segment end pinned');
check($forward[12]['start'] === [15.633678, 121.014894], 'General Ricarte segment pinned');
check($forward[19]['point'] === [15.705381, 121.099504], 'Rizal terminal point pinned');

$reverse = PASSENGER_DESTINATION_ROUTE_SEGMENTS['rizal-cabanatuan'];
check(count($reverse) === 21, 'Reverse direction carries its 19 route segments plus the Rizal origin');
check(($reverse[0]['name'] ?? '') === 'Rizal/Pob Sur Terminal', 'Reverse direction starts at the Rizal terminal origin');
check(($reverse[1]['name'] ?? '') === 'Del Pilar, Rizal', 'Reverse direction continues through Del Pilar');
check(($reverse[2]['name'] ?? '') === 'Aglipay, Rizal', 'Reverse direction reaches Aglipay as its first fare stop');
check(
    ($reverse[count($reverse) - 1]['name'] ?? '') === 'Cabanatuan Central Terminal',
    'Reverse direction ends at the Cabanatuan terminal'
);
check($reverse[0]['start'] === [15.705381, 121.099504], 'Reverse origin segment starts at the Rizal terminal point');
check($reverse[0]['end'] === [15.698276, 121.090763], 'Reverse origin segment reaches the Del Pilar start');
check($reverse[1]['start'] === [15.698276, 121.090763], 'Reverse Del Pilar segment starts at the Rizal-side anchor');
check($reverse[1]['end'] === [15.688399, 121.078317], 'Reverse Del Pilar segment ends toward Aglipay');

foreach ($reverse as $index => $entry) {
    check(
        passengerDestinationRouteSegmentIndexByName('rizal-cabanatuan', (string) $entry['name']) === $index,
        "Reverse name resolves in order: {$entry['name']}"
    );
}

// The five required bus positions project onto their expected segments.
$positions = [
    ['Cabanatuan terminal', 'cabanatuan-rizal', 15.472229, 120.969183, 'Cabanatuan Central Terminal'],
    ['Pinagpanaan', 'cabanatuan-rizal', 15.548218, 120.933455, 'Pinagpanaan'],
    ['Homestead 1', 'cabanatuan-rizal', 15.566468, 120.953142, 'Homestead 1'],
    ['Del Pilar', 'cabanatuan-rizal', 15.689167, 121.078555, 'Del Pilar'],
    ['Rizal terminal', 'cabanatuan-rizal', 15.705381, 121.099504, 'Rizal/Pob Sur Terminal'],
    ['Rizal terminal (return)', 'rizal-cabanatuan', 15.705381, 121.099504, 'Rizal/Pob Sur Terminal'],
    ['Del Pilar (return)', 'rizal-cabanatuan', 15.689167, 121.078555, 'Del Pilar'],
    ['Aglipay (return)', 'rizal-cabanatuan', 15.688171, 121.078333, 'Aglipay'],
];

foreach ($positions as [$label, $direction, $lat, $lng, $expectedSegment]) {
    $projection = passengerDestinationRouteProjection($direction, $lat, $lng);
    check($projection !== null, "{$label} projects onto the route");
    check(
        strpos((string) $projection['segment_name'], $expectedSegment) !== false,
        "{$label} projects onto the {$expectedSegment} segment (got {$projection['segment_name']})"
    );
    check($projection['offset_m'] <= 250.0, "{$label} projects within 250 m of the route");
}

// Unknown names and directions never resolve.
check(
    passengerDestinationRouteSegmentIndexByName('cabanatuan-rizal', 'Nowhere Stop 999') === null,
    'Unknown forward name resolves to null'
);
check(
    passengerDestinationRouteSegmentIndexByName('rizal-cabanatuan', 'Nowhere Stop 999') === null,
    'Unknown reverse name resolves to null'
);
check(
    passengerDestinationRouteSegmentIndexByName('no-such-direction', 'Valdefuente') === null,
    'Unknown direction resolves to null'
);
check(
    passengerDestinationRouteAnchorByName('cabanatuan-rizal', 'Nowhere Stop 999') === null,
    'Unknown anchor resolves to null'
);

echo "{$checks} route segment checks passed.\n";
