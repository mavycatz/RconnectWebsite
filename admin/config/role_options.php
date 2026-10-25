<?php

/**
 * Configurable dropdown options for the Role Management forms.
 *
 * Operational data (buses, routes, terminals, supervisors, departments) is
 * always loaded from the database — this file only holds the lists that are
 * genuinely configuration: enums, positions, and fallback departments.
 *
 * Add or change values here; the Add/Edit forms pick them up automatically.
 */

return [
    'suffixes' => ['', 'Jr.', 'Sr.', 'II', 'III', 'IV'],

    'sex' => ['', 'Male', 'Female', 'Prefer not to say'],

    'employment_types' => ['Regular', 'Probationary', 'Contractual', 'Part-Time'],

    'employment_statuses' => ['Active', 'On Leave', 'Inactive'],

    'availability' => ['Available', 'On Duty', 'On Leave', 'Unavailable'],

    'work_shifts' => ['Morning', 'Afternoon', 'Evening', 'Rotating'],

    // Keep in step with the existing driver license_type values.
    'license_classifications' => ['Professional', 'Non-Professional'],

    'finance_positions' => ['Finance Officer', 'Accountant', 'Cashier', 'Finance Staff'],

    'admin_positions' => ['Administrator', 'Operations Manager', 'HR Administrator', 'System Administrator'],

    // Used when the database has no distinct departments yet.
    'departments' => ['Administration', 'Finance', 'Operations', 'Inspections'],
];
