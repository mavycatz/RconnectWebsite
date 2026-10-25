<?php declare(strict_types=1);

/**
 * BusRegistrationValidator checks: every validation branch, plus date
 * normalization. No database or session is required.
 */

if (!isset($_SERVER['DOCUMENT_ROOT']) || $_SERVER['DOCUMENT_ROOT'] === '') {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
}

require_once __DIR__ . '/../services/Bus/BusFields.php';
require_once __DIR__ . '/../services/Bus/BusRegistrationValidator.php';

use Admin\Services\Bus\BusRegistrationValidator;
use Admin\Services\Bus\BusFields;

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

$config = require $_SERVER['DOCUMENT_ROOT'] . '/admin/config/bus_options.php';

$valid = [
    'plate_number' => 'ABC 1234',
    'bus_type' => 'Coach',
    'status' => 'Active',
    'operational_status' => 'Available',
    'seats' => '49',
    'standing_capacity' => '10',
    'registration_expiry' => '2027-05-01',
    'insurance_expiry' => '',
    'inspection_date' => '',
    'next_inspection_date' => '2026-11-15',
    'assigned_inspector_id' => '3',
    'year_model' => '2023',
];

check(BusRegistrationValidator::validate($valid, $config) === null, 'A complete payload validates');

check(
    BusRegistrationValidator::validate(array_merge($valid, ['plate_number' => '']), $config) === 'Plate number is required.',
    'Missing plate is rejected'
);
check(
    BusRegistrationValidator::validate(array_merge($valid, ['plate_number' => str_repeat('A', 21)]), $config) === 'Plate number is too long.',
    'Overlong plate is rejected'
);
check(
    BusRegistrationValidator::validate(array_merge($valid, ['bus_type' => str_repeat('B', 51)]), $config) === 'Select a valid bus type.',
    'Overlong bus type is rejected'
);
check(
    BusRegistrationValidator::validate(array_merge($valid, ['status' => 'Archived']), $config) === 'Select a valid bus status.',
    'Archived cannot be set through the add form'
);
check(
    BusRegistrationValidator::validate(array_merge($valid, ['status' => '']), $config) === 'Select a valid bus status.',
    'Empty status is rejected'
);
check(
    BusRegistrationValidator::validate(array_merge($valid, ['operational_status' => 'Sleeping']), $config) === 'Select a valid operational status.',
    'Unknown operational status is rejected'
);
check(
    BusRegistrationValidator::validate(array_merge($valid, ['operational_status' => '']), $config) === null,
    'Empty operational status is allowed'
);
check(
    BusRegistrationValidator::validate(array_merge($valid, ['seats' => '201']), $config) === 'Seat capacity must be between 0 and 200.',
    'Seat capacity is range-checked'
);
check(
    BusRegistrationValidator::validate(array_merge($valid, ['seats' => '-1']), $config) === 'Seat capacity must be between 0 and 200.',
    'Negative seat capacity is rejected'
);
check(
    BusRegistrationValidator::validate(array_merge($valid, ['standing_capacity' => '101']), $config) === 'Standing capacity must be between 0 and 100.',
    'Standing capacity is range-checked'
);

/* ---------------------- per-type capacity standard (fleet) ---------------------- */

$hino = array_merge($valid, ['bus_type' => 'HINO Bus', 'seats' => '24', 'standing_capacity' => '11']);
check(BusRegistrationValidator::validate($hino, $config) === null, 'A HINO Bus at the fleet standard validates');
check(
    BusRegistrationValidator::validate(array_merge($valid, ['bus_type' => 'HINO Bus', 'seats' => '23', 'standing_capacity' => '12']), $config) === null,
    'The lower HINO seat count with more standing also validates'
);
check(
    BusRegistrationValidator::validate(array_merge($hino, ['seats' => '22']), $config) === 'HINO Bus seating capacity must be between 23 and 24.',
    'HINO seats below the standard are rejected'
);
check(
    BusRegistrationValidator::validate(array_merge($hino, ['seats' => '25']), $config) === 'HINO Bus seating capacity must be between 23 and 24.',
    'HINO seats above the standard are rejected'
);
check(
    BusRegistrationValidator::validate(array_merge($hino, ['standing_capacity' => '12']), $config) === 'HINO Bus total capacity (seats + standing) must not exceed 35.',
    'HINO total capacity is capped'
);

$higer = array_merge($valid, ['bus_type' => 'Higer Bus', 'seats' => '26', 'standing_capacity' => '14']);
check(BusRegistrationValidator::validate($higer, $config) === null, 'A Higer Bus at the fleet standard validates');
check(
    BusRegistrationValidator::validate(array_merge($higer, ['seats' => '25']), $config) === 'Higer Bus seating capacity must be 26.',
    'Higer seats must be exactly 26'
);
check(
    BusRegistrationValidator::validate(array_merge($higer, ['standing_capacity' => '15']), $config) === 'Higer Bus total capacity (seats + standing) must not exceed 40.',
    'Higer total capacity is capped'
);
check(
    BusRegistrationValidator::validate(array_merge($higer, ['bus_type' => 'higer bus']), $config) === null,
    'The capacity rule matches the type case-insensitively'
);
check(BusRegistrationValidator::validate($valid, $config) === null, 'Types without a capacity rule stay unrestricted');

$rule = BusRegistrationValidator::capacityRule($config, 'HINO Bus');
check(is_array($rule) && $rule['seats_default'] === 24 && $rule['total_max'] === 35, 'The rule exposes the configured defaults');
check(BusRegistrationValidator::capacityRule($config, 'Coach') === null, 'Unknown types have no rule');

$defaults = BusRegistrationValidator::capacityDefaults($config, 'HINO Bus');
check($defaults === ['seats' => 24, 'standing' => 11], 'The capacity defaults expose the configured seats and standing');
$higerDefaults = BusRegistrationValidator::capacityDefaults($config, 'higer bus');
check($higerDefaults === ['seats' => 26, 'standing' => 14], 'Defaults match the type case-insensitively');
check(BusRegistrationValidator::capacityDefaults($config, 'Coach') === null, 'Types without a rule have no defaults');
check(
    BusRegistrationValidator::validate(array_merge($valid, ['registration_expiry' => '2027-02-30']), $config) === 'Registration expiration is not a valid date.',
    'Impossible dates are rejected'
);
check(
    BusRegistrationValidator::validate(array_merge($valid, ['next_inspection_date' => 'soon']), $config) === 'Next inspection date is not a valid date.',
    'Malformed dates are rejected with their label'
);
check(
    BusRegistrationValidator::validate(array_merge($valid, ['assigned_inspector_id' => 'abc']), $config) === 'Select a valid inspector.',
    'Non-numeric inspector references are rejected'
);
check(
    BusRegistrationValidator::validate(array_merge($valid, ['year_model' => '23']), $config) === 'Year model must be a four-digit year.',
    'Short year models are rejected'
);
check(
    BusRegistrationValidator::validate(array_merge($valid, ['year_model' => '']), $config) === null,
    'Empty year model is allowed'
);

$normalized = BusRegistrationValidator::normalizedDates(array_merge($valid, ['insurance_expiry' => '2026-12-31', 'inspection_date' => 'not-a-date']));
check($normalized['registration_expiry'] === '2027-05-01', 'Valid dates are kept');
check($normalized['insurance_expiry'] === '2026-12-31', 'Second valid date is kept');
check($normalized['inspection_date'] === null, 'Invalid dates normalize to null');
check($normalized['next_inspection_date'] === '2026-11-15', 'All date fields are covered');

check(BusFields::normalizeDate('2026-02-28') === '2026-02-28', 'Underlying date normalization is shared with BusFields');

echo "$checks bus registration validator checks passed.\n";
