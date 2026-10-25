<?php
$busId = $_GET["bus_id"] ?? null;
$seatNumber = $_GET["seat_number"] ?? null;
$token = $_GET["token"] ?? null;

if (empty($token) && (empty($busId) || empty($seatNumber))) {
    header("Location: /");
    exit();
}
