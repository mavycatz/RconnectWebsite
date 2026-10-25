<?php
namespace Admin\Controllers;

use App\Core\Database;
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';

class BusSeatTokens {

    public function getSeatByToken(string $token): ?array {
        $conn = Database::get();
        $stmt = $conn->prepare("
            SELECT bs.bus_id, bs.seat_number
            FROM bus_seat_tokens bst
            JOIN bus_seats bs ON bst.bus_seat_id = bs.id
            WHERE bst.token = :token
            AND (bst.expires_at IS NULL OR bst.expires_at > NOW())
            LIMIT 1
        ");
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}

