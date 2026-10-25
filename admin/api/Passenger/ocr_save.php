<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';

use App\Auth\Auth;
use App\Core\Database;
use PDO;

if (!Auth::check() || !in_array(Auth::user()['role'] ?? '', ['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['passengers']) || !isset($input['trip'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data.']);
    exit;
}

$trip = $input['trip'];
$passengers = $input['passengers'];

if (empty($passengers)) {
    echo json_encode(['success' => false, 'message' => 'No passengers to save.']);
    exit;
}

try {
    $db = Database::get();

    // Ensure table exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS passenger_trip_history (
            history_id INT AUTO_INCREMENT PRIMARY KEY,
            passenger_name VARCHAR(255) NOT NULL,
            passenger_type VARCHAR(100) NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'passenger',
            student_id VARCHAR(100) DEFAULT NULL,
            boarding_point VARCHAR(255) NOT NULL,
            destination VARCHAR(255) NOT NULL,
            drivers_full_name VARCHAR(100) DEFAULT NULL,
            driver_id VARCHAR(20) DEFAULT NULL,
            trip_id VARCHAR(100) DEFAULT NULL,
            fare DECIMAL(10,2) DEFAULT 0.00,
            payment_method ENUM('cash','gcash','hybrid') DEFAULT 'cash',
            date_booked DATETIME DEFAULT CURRENT_TIMESTAMP,
            trip_date DATE DEFAULT NULL,
            archived_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            senior_pwd_id VARCHAR(100) DEFAULT NULL,
            beneficiary_id VARCHAR(100) DEFAULT NULL,
            coop_disc_id VARCHAR(100) DEFAULT NULL,
            remarks TEXT DEFAULT NULL,
            archived TINYINT(1) DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $db->exec("ALTER TABLE passenger_trip_history
        ADD COLUMN IF NOT EXISTS time_of_departure TIME NULL AFTER trip_date,
        ADD COLUMN IF NOT EXISTS time_of_arrival TIME NULL AFTER time_of_departure,
        ADD COLUMN IF NOT EXISTS type VARCHAR(20) NOT NULL DEFAULT 'passenger' AFTER passenger_type
    ");

    $stmt = $db->prepare("
        INSERT INTO passenger_trip_history
        (passenger_name, passenger_type, student_id, boarding_point, destination,
         drivers_full_name, driver_id, fare, payment_method, trip_date,
         senior_pwd_id, beneficiary_id, coop_disc_id, remarks,
         time_of_departure, time_of_arrival)
        VALUES
        (:passenger_name, :passenger_type, :student_id, :boarding_point, :destination,
         :drivers_full_name, :driver_id, :fare, :payment_method, :trip_date,
         :senior_pwd_id, :beneficiary_id, :coop_disc_id, :remarks,
         :time_of_departure, :time_of_arrival)
    ");

    $count = 0;
    foreach ($passengers as $p) {
        $stmt->execute([
            ':passenger_name' => $p['passenger_name'] ?? 'Unknown',
            ':passenger_type' => $p['passenger_type'] ?? 'Regular',
            ':student_id' => !empty($p['student_id']) ? $p['student_id'] : null,
            ':boarding_point' => $p['boarding_point'] ?? '',
            ':destination' => $p['destination'] ?? '',
            ':drivers_full_name' => $trip['drivers_full_name'] ?? null,
            ':driver_id' => $trip['driver_id'] ?? null,
            ':fare' => (float)($p['fare'] ?? 0),
            ':payment_method' => $trip['payment_method'] ?? 'cash',
            ':trip_date' => $trip['trip_date'] ?? date('Y-m-d'),
            ':senior_pwd_id' => !empty($p['senior_pwd_id']) ? $p['senior_pwd_id'] : null,
            ':beneficiary_id' => !empty($p['beneficiary_id']) ? $p['beneficiary_id'] : null,
            ':coop_disc_id' => !empty($p['coop_disc_id']) ? $p['coop_disc_id'] : null,
            ':remarks' => !empty($p['remarks']) ? $p['remarks'] : null,
            ':time_of_departure' => !empty($p['time_of_departure']) ? $p['time_of_departure'] : null,
            ':time_of_arrival' => !empty($p['time_of_arrival']) ? $p['time_of_arrival'] : null
        ]);
        $count++;
    }

    echo json_encode([
        'success' => true,
        'message' => "Successfully saved {$count} passenger record(s)."
    ]);

} catch (\Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
