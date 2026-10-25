<?php

function jsonResponse($success, $message = "", $data = []) {

    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ]);

    exit;
}