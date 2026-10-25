<?php

if (!function_exists('require_driver_auth')) {

    /**
     * Verifies that a request carries a valid login token for the given
     * driver_id. Exits with a 401 JSON response on any failure.
     *
     * @param mysqli $conn     Already-open database connection.
     * @param string $driverId Driver ID value submitted by the client.
     * @param string $token    Login token value submitted by the client.
     * @return string The authenticated driver ID (same value as $driverId).
     */
    function require_driver_auth($conn, $driverId, $token)
    {
        if (!is_string($driverId) || $driverId === '' || !is_string($token) || $token === '') {
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "Unauthorized"]);
            exit;
        }

        $stmt = $conn->prepare("SELECT token FROM drivers WHERE driver_id = ? LIMIT 1");
        if (!$stmt) {
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "Unauthorized"]);
            exit;
        }

        $stmt->bind_param("s", $driverId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || empty($row['token']) || !hash_equals((string)$row['token'], $token)) {
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "Unauthorized"]);
            exit;
        }

        return $driverId;
    }
}