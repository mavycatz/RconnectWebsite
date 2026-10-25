<?php
require_once __DIR__ . "/../config/secret.php";
function generateToken($id) {
    $secret = DISPATCHER_TOKEN_SECRET;
    $payload = ["id" => $id, "time" => time()];
    $token = base64_encode(json_encode($payload));
    $signature = hash_hmac("sha256", $token, $secret);
    return $token . "." . $signature;
}
function verifyToken($token) {
    $secret = DISPATCHER_TOKEN_SECRET;
    $parts = explode(".", $token);
    if (count($parts) != 2) return false;
    $payload = $parts[0];
    $signature = $parts[1];
    $validSignature = hash_hmac("sha256", $payload, $secret);
    if (!hash_equals($validSignature, $signature)) return false;
    $data = json_decode(base64_decode($payload), true);
    if (!$data || !isset($data['time'])) return false;
    if (time() - $data['time'] > 172800) return false;
    return $data;
}
