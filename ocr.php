<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$apiKey = "AIzaSyDjXc2-0MurAOuSbPmU3HH04uqpvRIRKhk";

if(isset($_FILES['id_image'])){

    $imagePath = $_FILES['id_image']['tmp_name'];

    if(!file_exists($imagePath)){
        die("Image not found");
    }

    $imageData = file_get_contents($imagePath);

    if(!$imageData){
        die("Failed to read image");
    }

    $base64 = base64_encode($imageData);

    $request = [
        "requests" => [
            [
                "image" => [
                    "content" => $base64
                ],
                "features" => [
                    [
                        "type" => "TEXT_DETECTION"
                    ]
                ]
            ]
        ]
    ];

    $json = json_encode($request);

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://vision.googleapis.com/v1/images:annotate?key=".$apiKey);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

    $response = curl_exec($ch);

    if(curl_errno($ch)){
        echo "CURL ERROR: " . curl_error($ch);
        exit;
    }

    curl_close($ch);

    echo "<pre>";
    print_r(json_decode($response, true));
    exit;
}