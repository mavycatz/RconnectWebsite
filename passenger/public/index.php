<?php

$route = $_GET['route'] ?? '';

// ROUTES
if($route === 'passengers/create'){
    require $_SERVER['DOCUMENT_ROOT'].'/passenger/views/Create_Account/createaccount.php';
    exit;
}

if($route === 'passengers/store'){
    require $_SERVER['DOCUMENT_ROOT'].'/passenger/controllers/Create_Account/PassengerController.php';
    $controller = new PassengerController();
    $controller->store();
    exit;
}

if($route === 'passengers/id-types'){
    require $_SERVER['DOCUMENT_ROOT'].'/passenger/controllers/Create_Account/IdTypeController.php';
    exit;
}

if($route === 'passengers/resend-otp'){
    require $_SERVER['DOCUMENT_ROOT'].'/passenger/controllers/Create_Account/PassengerController.php';
    $controller = new PassengerController();
    $controller->resendOtp();
    exit;
}

if($route === 'passengers/verify-otp'){
    require $_SERVER['DOCUMENT_ROOT'].'/passenger/controllers/Create_Account/VerifyOTPController.php';
    $controller = new VerifyOTPController();
    $controller->verify();
    exit;
}

echo "404 Not Found";