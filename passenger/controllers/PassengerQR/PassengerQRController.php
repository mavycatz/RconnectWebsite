<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PassengerQRSchema.php';

use App\Core\Database;

class PassengerQrController {

    private $conn;

    public function __construct(){
        $this->conn = Database::get();
    }

    /**
     * Persists the passenger's first-login QR intro acknowledgment
     * (personal_qr_seen = 1) so the intro never shows again on later logins.
     */
    public function markSeen(){
        header('Content-Type: application/json');

        if (!isset($_SESSION['passenger_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        try {
            ensurePassengerQrSchema($this->conn);

            $stmt = $this->conn->prepare("UPDATE passengers SET personal_qr_seen = 1 WHERE id = ?");
            $ok = $stmt->execute([$_SESSION['passenger_id']]);

            echo json_encode(['success' => $ok]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save']);
        }
        exit;
    }
}