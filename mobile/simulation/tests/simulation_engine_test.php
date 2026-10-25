<?php

/**
 * CLI test for the simulation engine's pure logic. Requires no database:
 *
 *   php mobile/simulation/tests/simulation_engine_test.php
 *
 * Exits 0 when every assertion passes, 1 otherwise.
 */

require_once __DIR__ . '/../lib/simulation_engine.php';

$failures = 0;
$checks = 0;

function check($label, $condition)
{
    global $failures, $checks;
    $checks++;

    if ($condition) {
        echo "PASS  $label\n";
    } else {
        echo "FAIL  $label\n";
        $failures++;
    }
}

/* 1. Config-seeded waypoints are valid for both directions. */
$config = simulation_config();
check('config defines tick interval', isset($config['tick_interval_ms']) && (int) $config['tick_interval_ms'] > 0);
check('config defines default speed and radius', isset($config['default_speed_kmh'], $config['default_stop_radius_m']));

foreach (array('cabanatuan-rizal', 'rizal-cabanatuan') as $direction) {
    $waypoints = simulation_normalize_waypoints($direction);
    check("direction $direction has at least two waypoints", count($waypoints) >= 2);

    $valid = true;
    foreach ($waypoints as $waypoint) {
        if ($waypoint['lat'] < -90 || $waypoint['lat'] > 90 || $waypoint['lng'] < -180 || $waypoint['lng'] > 180 || $waypoint['speed_kmh'] <= 0) {
            $valid = false;
            break;
        }
    }
    check("direction $direction waypoints are in range with positive speeds", $valid);
}

/* 2. Route-name variants resolve to the same corridor. */
check('"Cabanatuan-Rizal" resolves cabanatuan-rizal', simulation_resolve_direction('Cabanatuan-Rizal') === 'cabanatuan-rizal');
check('"Cabanatuan to Rizal Route" resolves cabanatuan-rizal', simulation_resolve_direction('Cabanatuan to Rizal Route') === 'cabanatuan-rizal');
check('"Cabanatuan" resolves cabanatuan-rizal', simulation_resolve_direction('Cabanatuan') === 'cabanatuan-rizal');
check('"Rizal-Cabanatuan" resolves rizal-cabanatuan', simulation_resolve_direction('Rizal-Cabanatuan') === 'rizal-cabanatuan');
check('"Rizal to Cabanatuan Route" resolves rizal-cabanatuan', simulation_resolve_direction('Rizal to Cabanatuan Route') === 'rizal-cabanatuan');
check('unknown route resolves null', simulation_resolve_direction('Some Other Corridor') === null);

/* 3. Movement source: stored route_stops are primary; the configured mirror is
      the fallback when no stop rows are available (no DB in this test). */
$fallback = simulation_direction_waypoints(null, 'cabanatuan-rizal');
$configured = simulation_normalize_waypoints('cabanatuan-rizal');
check('without route_stops the fallback mirrors the config waypoints', count($fallback) === count($configured));
check(
    'fallback keeps the configured order',
    count($fallback) > 1 && abs($fallback[0]['lat'] - $configured[0]['lat']) < 1e-9
        && abs($fallback[count($fallback) - 1]['lng'] - $configured[count($configured) - 1]['lng']) < 1e-9
);
check('unknown direction yields no waypoints', count(simulation_direction_waypoints(null, 'some-other-corridor')) === 0);

/* 4. Movement plan: start, end, monotonicity, completion. */
$waypoints = simulation_normalize_waypoints('cabanatuan-rizal');
$plan = simulation_build_segments($waypoints, 40);
check('plan has one segment per gap', count($plan['segments']) === count($waypoints) - 1);
check('plan distance is positive', $plan['total_distance_m'] > 0);
check('plan duration is positive', $plan['total_duration_ms'] > 0);

$atStart = simulation_position_at($plan, $waypoints, 0);
check('t=0 is the first waypoint', abs($atStart['lat'] - $waypoints[0]['lat']) < 1e-9 && $atStart['progress_pct'] == 0.0);

$atEnd = simulation_position_at($plan, $waypoints, (int) ceil($plan['total_duration_ms']));
check('t=total is completed at the last waypoint', $atEnd['completed'] === true && abs($atEnd['lat'] - $waypoints[count($waypoints) - 1]['lat']) < 1e-9);

$previousDistance = -1.0;
$monotonic = true;
for ($step = 0; $step <= 20; $step++) {
    $elapsed = (int) ($plan['total_duration_ms'] * $step / 20);
    $position = simulation_position_at($plan, $waypoints, $elapsed);
    if ($position['distance_m'] < $previousDistance - 1e-6) {
        $monotonic = false;
        break;
    }
    $previousDistance = $position['distance_m'];
}
check('distance never decreases as elapsed grows', $monotonic);

$mid = simulation_position_at($plan, $waypoints, (int) ($plan['total_duration_ms'] / 2));
check('midpoint progress is near half', $mid['progress_pct'] > 40 && $mid['progress_pct'] < 60);
check('midpoint is not completed', $mid['completed'] === false);

/* 4b. Speed tiers: preset multipliers scale the plan duration and speed. */
$presets = simulation_speed_presets();
check('speed presets define slow, normal and fast', isset($presets['slow'], $presets['normal'], $presets['fast']));
check('normal speed preset is the neutral multiplier', (float) $presets['normal'] === 1.0);
check('slow is slower than normal', (float) $presets['slow'] < 1.0);
check('fast is faster than normal', (float) $presets['fast'] > 1.0);

check('unknown speed preset falls back to normal', simulation_normalize_speed_preset('ludicrous') === 'normal');
check('speed preset is case-insensitive', simulation_normalize_speed_preset('FAST') === 'fast');
check('custom is a known speed preset', simulation_normalize_speed_preset('custom') === 'custom');

check('custom multiplier clamps below the minimum', simulation_normalize_speed_multiplier(0.01) >= 0.25);
check('custom multiplier clamps above the maximum', simulation_normalize_speed_multiplier(99) <= 3.0);
check('invalid custom multiplier falls back to one', simulation_normalize_speed_multiplier('nonsense') === 1.0);

$halfPlan = simulation_build_segments($waypoints, 40, 0.5);
$doublePlan = simulation_build_segments($waypoints, 40, 2.0);
check(
    'a doubled speed multiplier halves the plan duration',
    abs($doublePlan['total_duration_ms'] - $plan['total_duration_ms'] / 2) < 1.0
);
check(
    'a halved speed multiplier doubles the plan duration',
    abs($halfPlan['total_duration_ms'] - $plan['total_duration_ms'] * 2) < 1.0
);
check(
    'segment speeds scale with the multiplier',
    abs($doublePlan['segments'][0]['speed_kmh'] - $plan['segments'][0]['speed_kmh'] * 2) < 0.01
);
check(
    'an invalid multiplier keeps the plan duration',
    abs(simulation_build_segments($waypoints, 40, 0)['total_duration_ms'] - $plan['total_duration_ms']) < 1.0
);

check(
    'a slow session reports the slow multiplier',
    simulation_session_speed_multiplier(array('speed_preset' => 'slow')) === (float) $presets['slow']
);
check(
    'a custom session uses its own multiplier',
    simulation_session_speed_multiplier(array('speed_preset' => 'custom', 'speed_multiplier' => 2.0)) === 2.0
);
check(
    'sessions from before the speed columns behave as normal',
    simulation_session_speed_multiplier(array()) === 1.0
);

/* 5. Clock semantics: running accrues, pause freezes, resume continues. */
check('running accrues paused plus current run', simulation_elapsed_ms('running', 1000, 5000, 1010) === 15000);
check('paused stays frozen', simulation_elapsed_ms('paused', 1000, 5000, 9999) === 5000);
check('idle has no elapsed movement', simulation_elapsed_ms('idle', null, 0, 9999) === 0);
check('completed keeps accumulated time', simulation_elapsed_ms('completed', 1000, 20000, 9999) === 20000);

/* 6. Stop context: within radius is current; next skips arrived stops. */
$stops = array(
    array('sequence_order' => 1, 'stop_name' => 'Terminal', 'latitude' => 15.4776, 'longitude' => 120.9673, 'radius_m' => 250.0),
    array('sequence_order' => 2, 'stop_name' => 'Midpoint', 'latitude' => 15.5200, 'longitude' => 120.9400, 'radius_m' => 100.0),
);
check('position inside radius reports the stop', simulation_current_stop($stops, 15.47761, 120.96731)['stop_name'] === 'Terminal');
check('position outside every radius reports none', simulation_current_stop($stops, 15.4000, 120.9000) === null);
check('next stop skips arrived sequences', simulation_next_stop($stops, array(1))['stop_name'] === 'Midpoint');
check('next stop is null once all arrived', simulation_next_stop($stops, array(1, 2)) === null);

/* 7. Stop names normalize like the passenger module and match by containment. */
check('stop names strip city/province noise', simulation_normalize_stop_name('Cabanatuan City Terminal') === 'cabanatuan terminal');
check('stop name matching is case/punctuation-insensitive', simulation_stop_name_matches('Sto. Domingo', 'sto domingo') === true);
check('unrelated stop names never match', simulation_stop_name_matches('Santo Domingo', 'Talavera') === false);

/* 8. Fare matrix resolution: fares come from fare_pricing, destinations only
      after the boarding stop, and unmatched pairs resolve to null (no guessed
      fare). */
$fareRoute = array(
    'stops' => array(
        array('stop_name' => 'Cabanatuan', 'sequence_order' => 1),
        array('stop_name' => 'Santo Domingo', 'sequence_order' => 2),
        array('stop_name' => 'Talavera', 'sequence_order' => 3),
        array('stop_name' => 'Rizal', 'sequence_order' => 4),
    ),
    'fares' => array(
        array('id' => 5, 'destination' => 'Cabanatuan', 'regular' => 15.0, 'student' => 12.0, 'special' => 10.5, 'boarding_stops' => array('Cabanatuan')),
        array('id' => 6, 'destination' => 'Santo Domingo', 'regular' => 15.0, 'student' => 12.0, 'special' => 10.5, 'boarding_stops' => array('Cabanatuan', 'Santo Domingo')),
        array('id' => 7, 'destination' => 'Talavera', 'regular' => 27.0, 'student' => 21.6, 'special' => 18.9, 'boarding_stops' => array('Cabanatuan', 'Santo Domingo', 'Talavera')),
        array('id' => 8, 'destination' => 'Rizal', 'regular' => 45.0, 'student' => 36.0, 'special' => 31.5, 'boarding_stops' => array('Cabanatuan', 'Santo Domingo', 'Talavera', 'Rizal')),
    ),
);

$resolved = simulation_resolve_passenger_fare($fareRoute, 'Cabanatuan', 'Santo Domingo');
check('fare resolves with the fare_pricing id and fares', $resolved !== null && $resolved['fare_pricing_id'] === 6 && $resolved['regular'] == 15.0 && $resolved['student'] == 12.0 && $resolved['special'] == 10.5);
check('fare resolution keeps route order', $resolved !== null && $resolved['boarding_sequence'] === 1 && $resolved['destination_sequence'] === 2);

$resolved = simulation_resolve_passenger_fare($fareRoute, 'Santo Domingo', 'Talavera');
check('mid-route boarding resolves its own fare row', $resolved !== null && $resolved['fare_pricing_id'] === 7 && $resolved['regular'] == 27.0);

$resolved = simulation_resolve_passenger_fare($fareRoute, 'Talavera', 'Rizal');
check('downstream destination resolves the full-route fare', $resolved !== null && $resolved['fare_pricing_id'] === 8 && $resolved['regular'] == 45.0);

check('a destination behind the boarding stop is refused', simulation_resolve_passenger_fare($fareRoute, 'Rizal', 'Cabanatuan') === null);
check('boarding and alighting at the same stop is refused', simulation_resolve_passenger_fare($fareRoute, 'Cabanatuan', 'Cabanatuan') === null);
check('an unknown stop never invents a fare', simulation_resolve_passenger_fare($fareRoute, 'Unknown', 'Rizal') === null);
check('a destination without a fare row never invents a fare', simulation_resolve_passenger_fare($fareRoute, 'Cabanatuan', 'Unknown') === null);

/* 9. Kilometer projection keeps the first stop at the corridor start and the
      last at its end. */
$corridor = simulation_normalize_waypoints('cabanatuan-rizal');
$projectedStart = simulation_project_km_onto_waypoints($corridor, 0.0, 25.0);
$projectedEnd = simulation_project_km_onto_waypoints($corridor, 25.0, 25.0);
$projectedMid = simulation_project_km_onto_waypoints($corridor, 12.5, 25.0);
check('km 0 projects to the corridor start', abs($projectedStart['lat'] - $corridor[0]['lat']) < 1e-9 && abs($projectedStart['lng'] - $corridor[0]['lng']) < 1e-9);
check('max km projects to the corridor end', abs($projectedEnd['lat'] - $corridor[count($corridor) - 1]['lat']) < 1e-9);
check('mid km projects between the ends', $projectedMid['lat'] > $corridor[0]['lat'] && $projectedMid['lat'] < $corridor[count($corridor) - 1]['lat']);

/* 10. Demand keys normalize safely. */
check('demand keys normalize', simulation_normalize_demand('HIGH') === 'high' && simulation_normalize_demand('off') === 'off');
check('unknown demand falls back to normal', simulation_normalize_demand('turbo') === 'normal');

echo "\n$checks checks, $failures failure(s)\n";
exit($failures === 0 ? 0 : 1);
