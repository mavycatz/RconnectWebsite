<?php

/**
 * Fallback coordinates and shared settings for the R-CONNECT shared GPS route
 * simulation.
 *
 * The simulation's movement source is the stored `route_stops` sequence for the
 * resolved direction (ordered by `sequence_order`), so the simulated bus follows
 * the same stops the driver app draws. On installs whose `route_stops` holds the
 * fare-matrix layout (`fare_pricing_id`, `stop_name`, `km_marker`,
 * `display_order`) the ordered stops come from `fare_pricing` + its
 * `route_stops` rows and their coordinates are projected onto the corridor
 * below. This file is also consulted when a direction has fewer than two stored
 * waypoints, and it holds the shared settings (tick interval, default speed,
 * default stop radius, and the simulated passenger-demand ranges).
 *
 * The fallback waypoints mirror the corridor:
 *   cabanatuan-rizal  (Cabanatuan City -> Rizal)
 *   rizal-cabanatuan  (Rizal -> Cabanatuan City)
 *
 * Each waypoint is [latitude, longitude, speed_kmh]. The speed applies to the
 * segment that ENDS at that waypoint; the first waypoint's speed is ignored.
 */

return array(
    'tick_interval_ms' => 2000,
    'default_speed_kmh' => 40,
    'default_stop_radius_m' => 150,

    /* Selectable simulation speed tiers: the multiplier is applied to every
       waypoint speed (and the default speed) for sessions whose
       `speed_preset` names the tier. The console's custom tier uses the
       session's own `speed_multiplier`, clamped to the bounds below. */
    'speed_presets' => array(
        'slow' => 0.5,
        'normal' => 1.0,
        'fast' => 1.5,
    ),
    'speed_multiplier_min' => 0.25,
    'speed_multiplier_max' => 3.0,

    /* Simulated waiting passengers generated per upcoming stop, by demand. */
    'passenger_demand' => array(
        'off' => array('min' => 0, 'max' => 0),
        'low' => array('min' => 0, 'max' => 2),
        'normal' => array('min' => 1, 'max' => 4),
        'high' => array('min' => 3, 'max' => 8),
    ),
    /* Extra passengers at the route's first stop (terminal crowd). */
    'passenger_origin_boost' => 2,
    /* Seat capacity when the bus record carries no usable seat count. */
    'passenger_capacity_default' => 50,

    'directions' => array(

        'cabanatuan-rizal' => array(
            'label' => 'Cabanatuan to Rizal',
            'fare_route_name' => 'Cabanatuan-Rizal',
            'route_stops_name' => 'Cabanatuan to Rizal Route',
            'waypoints' => array(
                array(15.47756100, 120.96733400, 40),
                array(15.48382100, 120.96757800, 45),
                array(15.49283400, 120.97564900, 45),
                array(15.50280000, 120.97719900, 45),
                array(15.50671800, 120.96696800, 45),
                array(15.51324700, 120.95683400, 45),
                array(15.51845600, 120.95351600, 45),
                array(15.52165100, 120.93741400, 45),
                array(15.52947300, 120.93436400, 45),
                array(15.54221700, 120.93251000, 50),
                array(15.54821800, 120.93345500, 50),
                array(15.55888200, 120.94484300, 50),
                array(15.57030500, 120.95723200, 50),
                array(15.57886300, 120.96651600, 50),
                array(15.59191700, 120.97174700, 50),
                array(15.59807100, 120.97403800, 50),
                array(15.61096500, 120.98872600, 50),
                array(15.62386900, 121.00341100, 50),
                array(15.65412400, 121.03627700, 50),
                array(15.69179800, 121.08306000, 45),
                array(15.70526900, 121.09934400, 40),
            ),
        ),

        'rizal-cabanatuan' => array(
            'label' => 'Rizal to Cabanatuan',
            'fare_route_name' => 'Rizal-Cabanatuan',
            'route_stops_name' => 'Rizal to Cabanatuan Route',
            'waypoints' => array(
                array(15.70526900, 121.09934400, 40),
                array(15.69179800, 121.08306000, 45),
                array(15.65412400, 121.03627700, 50),
                array(15.62386900, 121.00341100, 50),
                array(15.61096500, 120.98872600, 50),
                array(15.59807100, 120.97403800, 50),
                array(15.59191700, 120.97174700, 50),
                array(15.57886300, 120.96651600, 50),
                array(15.57030500, 120.95723200, 50),
                array(15.55888200, 120.94484300, 50),
                array(15.54821800, 120.93345500, 50),
                array(15.54221700, 120.93251000, 50),
                array(15.52947300, 120.93436400, 45),
                array(15.52165100, 120.93741400, 45),
                array(15.51845600, 120.95351600, 45),
                array(15.51324700, 120.95683400, 45),
                array(15.50671800, 120.96696800, 45),
                array(15.50280000, 120.97719900, 45),
                array(15.49283400, 120.97564900, 45),
                array(15.48382100, 120.96757800, 45),
                array(15.47756100, 120.96733400, 40),
            ),
        ),

    ),
);
