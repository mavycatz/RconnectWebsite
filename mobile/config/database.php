<?php

/**
 * The driver GPS writer's clock.
 *
 * Every timestamp this connection writes is Philippine wall-clock time. The
 * column is written explicitly by updateLocation.php so it does not depend on
 * the column default or the session's time_zone, and the session is pinned to
 * +08:00 so NOW() and CURRENT_TIMESTAMP agree with it. App\Core\Database pins
 * the same zone on the reader's connection, which is what lets the passenger
 * API difference a stored created_at against NOW() and get real elapsed time.
 */

defined('APP_TIMEZONE') || define('APP_TIMEZONE', 'Asia/Manila');
defined('APP_UTC_OFFSET') || define('APP_UTC_OFFSET', '+08:00');

// db_credentials.php refuses to load unless this gate constant is set, so a
// direct web request to that file still gets "No direct script access allowed".
// This file is its only consumer, so the gate is armed here rather than in
// every caller (dispatcher, driver app, inspector app all connect through here).
defined('APP_INIT') || define('APP_INIT', true);

date_default_timezone_set(APP_TIMEZONE);

class Database {

    private $conn;

    public function connect(){

        $config = require __DIR__ . "/db_credentials.php";

        $host = $config['host'];
        $db_name = $config['db_name'];
        $username = $config['username'];
        $password = $config['password'];

        try {

            $this->conn = new mysqli(
                $host,
                $username,
                $password,
                $db_name
            );

            if ($this->conn->connect_error) {
                throw new Exception("Database connection failed");
            }

            // Pin the session clock to Asia/Manila. Without this the writer
            // stamps rows in whatever zone the server is configured for, while
            // the reader assumes +08:00, and every live fix reads 8 hours old.
            //
            // The numeric offset is used rather than the zone name because a
            // MySQL without the timezone tables loaded rejects 'Asia/Manila'
            // with error 1298, and the two are exactly equivalent here: the
            // Philippines observes a constant UTC+8 with no daylight saving.
            $this->conn->query("SET time_zone = '" . APP_UTC_OFFSET . "'");

        } catch (\Throwable $e) {

            if (!headers_sent()) {
                header("Content-Type: application/json");
                http_response_code(500);
            }
            echo json_encode([
                "success" => false,
                "message" => "Database connection error: " . $e->getMessage()
            ]);
            exit;

        }

        return $this->conn;
    }
}