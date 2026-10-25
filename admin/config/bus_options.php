<?php

/**
 * Configurable dropdown options for the Bus Management forms.
 *
 * Operational data (existing bus records, routes, drivers, inspectors) is
 * always loaded from the database — this file only holds the enumerations
 * that are configuration anyway. Add or change values here; the Add/Edit
 * forms pick them up automatically.
 */

return [
    'bus_types' => ['HINO Bus', 'Higer Bus'],

    /*
     * Fleet capacity standard per bus type. The Add/Edit form pre-fills the
     * seats/standing defaults when the type is chosen and the server enforces
     * the ranges:
     *   - HINO Bus  : 23-24 seats, total (seats + standing) up to 35.
     *   - Higer Bus : 26 seats,    total (seats + standing) up to 40.
     * Types without a rule are unrestricted (0-200 / 0-100).
     */
    'bus_type_capacities' => [
        'HINO Bus' => [
            'seats_default' => 24,
            'standing_default' => 11,
            'seats_min' => 23,
            'seats_max' => 24,
            'total_max' => 35,
        ],
        'Higer Bus' => [
            'seats_default' => 26,
            'standing_default' => 14,
            'seats_min' => 26,
            'seats_max' => 26,
            'total_max' => 40,
        ],
    ],

    'manufacturers' => [
        'Yutong', 'Hino', 'Isuzu', 'Mitsubishi Fuso', 'Hyundai',
        'Daewoo', 'King Long', 'Golden Dragon', 'Nissan', 'Toyota',
    ],

    'fuel_types' => ['Diesel', 'Gasoline', 'Electric', 'Hybrid'],

    'transmissions' => ['Automatic', 'Manual'],

    'air_conditioning' => ['Air-Conditioned', 'Non-Air-Conditioned'],

    'ownership' => ['Cooperative-Owned', 'Operator-Owned', 'Leased'],

    'operational_statuses' => ['Available', 'On Trip', 'Under Maintenance', 'Out of Service'],

    // How far back the Year Model dropdown is generated, from the current year.
    'year_range' => 40,
];
