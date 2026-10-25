<?php
require_once __DIR__ . '/../models/PassengerModel.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/config/database.php';

class ReceiptController {
    private $conn;

    public function __construct() {
        $this->conn = (new Database())->connect();
    }

    public function show() {
        // Get parameters from URL
        $passenger_id = $_GET['id'] ?? null;
        $bus_id = $_GET['bus_id'] ?? null;
        $seat_number = $_GET['seat_number'] ?? null;

        if (!$passenger_id || !$bus_id || !$seat_number) {
            if (function_exists('passenger_render_themed_error')) {
                passenger_render_themed_error('Receipt Unavailable', 'Missing required receipt information. Please view your trip receipt from your Trips page.', 'Return to Trips', passenger_url('trips'), 400);
            } else {
                http_response_code(400);
                echo 'Missing required receipt information.';
                exit;
            }
        }

        $model = new PassengerModel($this->conn);
        $passenger = $model->getPassengerById($passenger_id);

        if (!$passenger) {
            if (function_exists('passenger_render_themed_error')) {
                passenger_render_themed_error('Passenger Not Found', 'The passenger record for this receipt could not be found.', 'Return to Trips', passenger_url('trips'), 404);
            } else {
                http_response_code(404);
                echo 'Passenger record not found.';
                exit;
            }
        }

        // Optional: validate that bus_id and seat_number match the passenger
        if ($passenger['bus_id'] !== $bus_id || $passenger['seat_number'] !== $seat_number) {
            if (function_exists('passenger_render_themed_error')) {
                passenger_render_themed_error('Receipt Mismatch', 'The trip details do not match the requested receipt.', 'Return to Trips', passenger_url('trips'), 400);
            } else {
                http_response_code(400);
                echo 'Receipt details mismatch.';
                exit;
            }
        }

        include __DIR__ . '/../views/receipt.php';
    }
}

// Direct access
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $controller = new ReceiptController();
    $controller->show();
}
