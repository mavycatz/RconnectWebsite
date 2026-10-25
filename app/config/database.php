<?php

/**
 * Single source of truth for the application's clock.
 *
 * Every timestamp the app writes or compares is Philippine wall-clock time.
 * Driver GPS rows are written by mobile/driver/updateLocation.php on a +08:00
 * session, and App\Core\Database pins its own session to the same zone, so
 * created_at and NOW() share one basis and their difference is real elapsed
 * time rather than a timezone artefact.
 */
defined('APP_TIMEZONE') || define('APP_TIMEZONE', 'Asia/Manila');
defined('APP_UTC_OFFSET') || define('APP_UTC_OFFSET', '+08:00');

return [
    'host'     => 'localhost',
    'db'       => 'u292726748_rizalcapstone',
    'user'     => 'u292726748_rconnectmavs15',
    'pass'     => 'MavyAbrenica#15',
    'charset'  => 'utf8mb4',
    'timezone' => APP_TIMEZONE,
];