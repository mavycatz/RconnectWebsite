<?php

function generateToken($id){

    $secret = "RCONNECT_SECRET_KEY";

    $payload = [
        "id"=>$id,
        "time"=>time()
    ];

    $token = base64_encode(json_encode($payload));

    $signature = hash_hmac("sha256", $token, $secret);

    return $token.".".$signature;
}

function verifyToken($token){

    $secret = "RCONNECT_SECRET_KEY";

    $parts = explode(".", $token);

    if(count($parts) != 2){
        return false;
    }

    $payload = $parts[0];
    $signature = $parts[1];

    $validSignature = hash_hmac("sha256", $payload, $secret);

    if(!hash_equals($validSignature,$signature)){
        return false;
    }

    return json_decode(base64_decode($payload), true);
}