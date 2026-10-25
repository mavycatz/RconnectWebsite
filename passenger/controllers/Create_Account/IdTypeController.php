<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once dirname(__DIR__, 2) . '/services/PassengerValidator.php';
use App\Passenger\PassengerValidator;

// Only R-CONNECT supported discount ID types.
// Generic IDs (Driver's License, Passport, Barangay ID, Postal ID, UMID) have been completely removed.
$defaultTypes = [
    [
        'name' => 'student-id',
        'label' => 'Student ID',
        'fields_expected' => ['full_name', 'student_id', 'date_of_birth', 'course', 'school'],
        'requires_address' => false,
        'requires_id_number' => true,
    ],
    [
        'name' => 'senior-citizen-id',
        'label' => 'Senior Citizen ID',
        'fields_expected' => ['full_name', 'osc_id_number', 'date_of_birth', 'address'],
        'requires_address' => true,
        'requires_id_number' => true,
    ],
    [
        'name' => 'philsys-id',
        'label' => 'PhilSys / National ID',
        'fields_expected' => ['full_name', 'philsys_number', 'date_of_birth', 'address'],
        'requires_address' => true,
        'requires_id_number' => true,
    ],
    [
        'name' => 'pwd-id',
        'label' => 'PWD ID',
        'fields_expected' => ['full_name', 'pwd_id_number', 'date_of_birth', 'disability_type'],
        'requires_address' => false,
        'requires_id_number' => true,
    ],
    [
        'name' => 'teacher-id',
        'label' => 'Teacher / School ID',
        'fields_expected' => ['full_name', 'faculty_id_number', 'date_of_birth', 'school'],
        'requires_address' => false,
        'requires_id_number' => true,
    ],
    [
        'name' => 'prc-id',
        'label' => 'PRC ID',
        'fields_expected' => ['full_name', 'prc_license_number', 'date_of_birth', 'profession', 'expiry_date'],
        'requires_address' => false,
        'requires_id_number' => true,
    ],
    [
        'name' => 'coop-beneficiary-id',
        'label' => 'Cooperative Beneficiary ID',
        'fields_expected' => ['full_name', 'coop_id_number', 'date_of_birth', 'cooperative_name', 'address'],
        'requires_address' => true,
        'requires_id_number' => true,
    ],
];

$types = $defaultTypes;

try {
    $dbPath = $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
    if (file_exists($dbPath)) {
        require_once $dbPath;
        $db = App\Core\Database::get();

        $stmt = $db->query("SELECT COUNT(*) FROM id_types");
        $count = (int) $stmt->fetchColumn();

        if ($count > 0) {
            $stmt = $db->query("SELECT `name`, `label`, `fields_expected`, `requires_address`, `requires_id_number` FROM id_types WHERE is_active = 1");
            $dbTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $filtered = [];
            foreach ($dbTypes as $row) {
                // Strictly filter out any generic IDs not supported in R-CONNECT
                if (!array_key_exists($row['name'], PassengerValidator::SUPPORTED_ID_TYPES)) {
                    continue;
                }
                $row['fields_expected'] = json_decode($row['fields_expected'] ?? '[]', true) ?? [];
                $row['requires_address'] = (bool) ($row['requires_address'] ?? false);
                $row['requires_id_number'] = (bool) ($row['requires_id_number'] ?? true);
                $row['label'] = PassengerValidator::SUPPORTED_ID_TYPES[$row['name']] ?? $row['label'];
                $filtered[] = $row;
            }

            if (!empty($filtered)) {
                $types = $filtered;
            }
        }
    }
} catch (\Throwable $e) {
    // Fall back safely to $defaultTypes
    $types = $defaultTypes;
}

// Optional filter by passenger type context
$passengerTypeParam = trim((string) ($_GET['passenger_type'] ?? ''));
if ($passengerTypeParam !== '') {
    $normType = PassengerValidator::normalizePassengerType($passengerTypeParam);
    $allowedIds = PassengerValidator::PASSENGER_TYPE_ID_MAP[$normType] ?? null;

    if ($allowedIds !== null) {
        $types = array_values(array_filter($types, static function ($t) use ($allowedIds) {
            return in_array($t['name'], $allowedIds, true);
        }));
    }
}

echo json_encode(['success' => true, 'data' => $types]);
