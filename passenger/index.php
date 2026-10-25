<?php

require_once __DIR__ . '/bootstrap.php';

passenger_install_error_handler();

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'OPTIONS') {
    http_response_code(405);
    exit;
}

passenger_enforce_https();

$route = (string) ($_GET['route'] ?? 'login');

passenger_mobile_gate();

passenger_send_security_headers();
passenger_reject_foreign_origin();
passenger_session_start();
passenger_ensure_schema_once();

$routeAliases = [
    'home' => 'homepage',
];

if (isset($routeAliases[$route])) {
    $route = $routeAliases[$route];
}

if ($route === 'login/back') {
    $_GET['back'] = '1';
    $route = 'login';
} elseif ($route === 'login/resend') {
    $_GET['resend'] = '1';
    $route = 'login';
}

if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    passenger_require_csrf();
}

$oneTimeFencedRoutes = ['homepage', 'trips', 'trips/rejected', 'profile', 'profile/edit-modal'];
if (!empty($_SESSION['is_one_time']) && in_array($route, $oneTimeFencedRoutes, true)) {
    header('Location: ' . passenger_url('one-time/register'));
    exit;
}

$publicRoutes = [
    'login',
    'forgot-password',
    'send-reset-otp',
    'verify-reset-otp',
    'verify-reset-otp-process',
    'resend-reset-otp',
    'new-password',
    'update-password',
    'passengers/create',
    'passengers/store',
    'passengers/verify-otp',
    'passengers/resend-otp',
    'passengers/id-types',
    'one-time/landing',
    'one-time/trip',
    'one-time/register',
    'one-time/payment-method',
    'one-time/fare',
    'one-time/continuation',
    'one-time/continue',
    'token-login',
    'logout',
    'default',
];

$protectedRoutes = [
    'account/restriction',
    'account/appeal',
    'account/notifications/read',
    'auto-board',
    'scan/store',
    'companions',
    'companions/create',
    'companions/delete',
    'companions/attach',
    'companions/attached',
    'companions/party',
    'trip/active',
    'trip/select-destination',
    'trip/route-map',
    'trip/dropoff-point',
    'trip/continuation',
    'trip/continue',
    'trip/confirm-receipt',
    'trip/payment-method',
    'trip/location',
    'trip/bus-location',
    'trip/save-state',
    'trip/restore-state',
    'trip/clear-state',
    'trip/modal',
    'homepage',
    'trips',
    'trips/rejected',
    'profile',
    'profile/edit-modal',
    'profile/update',
    'profile/default-payment-method',
    'profile/language-modal',
    'profile/trusted-device-modal',
    'profile/trusted-device/revoke',
    'calculate-fare',
    'api/destinations',
    'api/resolve-stop',
    'api/calculate-fare',
    'api/active-trips',
    'qr/mark-seen',
    'payment/proof',
];

$isPaymongoRoute = strpos($route, 'paymongo/') === 0;

// Entry pages that must always render, even when a stale or restricted
// session exists. Without this, a leftover session makes "Create Account"
// bounce back to login.
$alwaysOpenRoutes = [
    'login', 'logout', 'default', 'token-login',
    'register', 'register/otp', 'register/scan-id',
    'passengers/create', 'passengers/store', 'passengers/verify-otp',
    'passengers/resend-otp', 'passengers/id-types',
    'forgot-password', 'send-reset-otp', 'verify-reset-otp',
    'verify-reset-otp-process', 'resend-reset-otp', 'new-password', 'update-password',
    // The One-Time flow is login-free by design: a stale session must never
    // redirect these landing/registration pages to the login screen.
    'one-time/landing', 'one-time/trip', 'one-time/register',
    'one-time/fare', 'one-time/payment-method',
    'one-time/continuation', 'one-time/continue',
];

// Restricted accounts are fenced away from app flows (protected routes and
// one-time boarding), while registration and recovery pages stay reachable.
if (!in_array($route, $alwaysOpenRoutes, true)
    && !$isPaymongoRoute
    && (!empty($_SESSION['passenger_id']) || in_array($route, $protectedRoutes, true))) {
    passenger_require_auth(passenger_is_api_request());
}

switch ($route) {
    case 'account/restriction':
    case 'account/appeal':
    case 'account/notifications/read':
        require __DIR__ . '/controllers/Account/RestrictionController.php';
        PassengerRestrictionController::handle($route);
    break;

    case 'login':
        require __DIR__ . '/controllers/Login_Account/LoginController.php';
        $controller = new LoginController();
        $controller->login();
    break;

    case 'forgot-password':
        require __DIR__ . '/controllers/ForgotPassword/ForgotPasswordController.php';
        $controller = new ForgotPasswordController();
        $controller->index();
    break;

    case 'send-reset-otp':
        require __DIR__ . '/controllers/ForgotPassword/ForgotPasswordController.php';
        $controller = new ForgotPasswordController();
        $controller->sendOTP();
    break;

    case 'verify-reset-otp':
        require __DIR__ . '/controllers/ForgotPassword/ForgotPasswordController.php';
        $controller = new ForgotPasswordController();
        $controller->verifyOTPPage();
    break;

    case 'verify-reset-otp-process':
        require __DIR__ . '/controllers/ForgotPassword/ForgotPasswordController.php';
        $controller = new ForgotPasswordController();
        $controller->verifyOTP();
    break;

    case 'resend-reset-otp':
        require __DIR__ . '/controllers/ForgotPassword/ForgotPasswordController.php';
        $controller = new ForgotPasswordController();
        $controller->resendOTP();
    break;

    case 'new-password':
        require __DIR__ . '/controllers/ForgotPassword/ForgotPasswordController.php';
        $controller = new ForgotPasswordController();
        $controller->newPasswordPage();
    break;

    case 'update-password':
        require __DIR__ . '/controllers/ForgotPassword/ForgotPasswordController.php';
        $controller = new ForgotPasswordController();
        $controller->updatePassword();
    break;

    case 'passengers/create':
    case 'register':
        if ($route === 'register' && $method === 'POST') {
            require __DIR__ . '/controllers/Create_Account/PassengerController.php';
            $controller = new PassengerController();
            $controller->store();
            break;
        }
        require __DIR__ . '/views/Create_Account/createaccount.php';
    break;

    case 'register/otp':
        require __DIR__ . '/views/Create_Account/otp.php';
    break;

    case 'register/scan-id':
        require __DIR__ . '/controllers/Create_Account/scanID.php';
    break;

    case 'passengers/store':
        require __DIR__ . '/controllers/Create_Account/PassengerController.php';
        $controller = new PassengerController();
        $controller->store();
    break;

    case 'passengers/verify-otp':
        require __DIR__ . '/controllers/Create_Account/VerifyOTPController.php';
        $controller = new VerifyOTPController();
        $controller->verify();
    break;

    case 'passengers/resend-otp':
        require __DIR__ . '/controllers/Create_Account/PassengerController.php';
        $controller = new PassengerController();
        $controller->resendOtp();
    break;

    case 'passengers/id-types':
        require __DIR__ . '/controllers/Create_Account/IdTypeController.php';
    break;

    case 'auto-board':
        require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/controllers/PassengerHome/AutoBoardController.php';
        $controller = new AutoBoardController();
        $controller->index();
    break;

    case 'scan/store':
        require __DIR__ . '/controllers/PassengerHome/ScanController.php';
    break;

    case 'companions':
    case 'companions/create':
    case 'companions/delete':
    case 'companions/attach':
    case 'companions/attached':
    case 'companions/party':
        require __DIR__ . '/controllers/PassengerHome/CompanionController.php';
        CompanionController::handle($route);
    break;

    case 'trip/active':
        require __DIR__ . '/controllers/PassengerHome/ActiveTripController.php';
    break;

    case 'trip/select-destination':
        require __DIR__ . '/controllers/PassengerHome/SelectDestinationController.php';
    break;

    case 'trip/route-map':
        require __DIR__ . '/controllers/PassengerHome/RouteMapController.php';
    break;

    case 'trip/dropoff-point':
        require __DIR__ . '/controllers/PassengerHome/DropoffPointController.php';
    break;

    case 'trip/continuation':
        require __DIR__ . '/controllers/PassengerHome/ContinuationController.php';
        $controller = new ContinuationController();
        $controller->status();
    break;

    case 'trip/continue':
        require __DIR__ . '/controllers/PassengerHome/ContinuationController.php';
        $controller = new ContinuationController();
        $controller->create();
    break;

    case 'trip/confirm-receipt':
        require __DIR__ . '/controllers/PassengerHome/ConfirmReceiptController.php';
    break;

    case 'trip/payment-method':
        require __DIR__ . '/controllers/PassengerHome/PaymentMethodController.php';
    break;

    case 'trip/location':
        require __DIR__ . '/controllers/PassengerHome/TripLocationController.php';
    break;

    case 'trip/bus-location':
        require __DIR__ . '/controllers/PassengerHome/BusLocationController.php';
    break;

    case 'one-time/landing':
        require __DIR__ . '/controllers/PassengerHome/OneTimeController.php';
        $controller = new OneTimeController();
        $controller->landing();
    break;

    case 'one-time/trip':
        require __DIR__ . '/controllers/PassengerHome/OneTimeController.php';
        $controller = new OneTimeController();
        $controller->trip();
    break;

    case 'one-time/register':
        require __DIR__ . '/controllers/PassengerHome/OneTimeController.php';
        $controller = new OneTimeController();
        $controller->register();
    break;

    case 'one-time/payment-method':
        require __DIR__ . '/controllers/PassengerHome/OneTimeController.php';
        $controller = new OneTimeController();
        $controller->paymentMethod();
    break;

    case 'one-time/fare':
        require __DIR__ . '/controllers/PassengerHome/OneTimeController.php';
        $controller = new OneTimeController();
        $controller->fare();
    break;

    case 'one-time/continuation':
        require __DIR__ . '/controllers/PassengerHome/OneTimeController.php';
        $controller = new OneTimeController();
        $controller->continuation();
    break;

    case 'one-time/continue':
        require __DIR__ . '/controllers/PassengerHome/OneTimeController.php';
        $controller = new OneTimeController();
        $controller->continueJourney();
    break;

    case 'token-login':
        header('Content-Type: application/json');
        $tokenStr = $_POST['token'] ?? '';
        if (empty($tokenStr)) {
            echo json_encode(['success' => false]);
            exit;
        }
        require_once $_SERVER['DOCUMENT_ROOT'] . '/app/auth/RememberAuth.php';
        $ok = \App\Auth\RememberAuth::loginFromTokenString($tokenStr);
        echo json_encode(['success' => $ok]);
        exit;

    case 'homepage':
        require __DIR__ . '/views/Homepage/Index.php';
    break;

    case 'calculate-fare':
        header('Content-Type: application/json');

        require_once $_SERVER['DOCUMENT_ROOT'] . '/fare-engine/utils/calculateFare.php';

        $data = json_decode(file_get_contents('php://input'), true);

        try {
            $tripNo = $data['trip_no'] ?? null;

            if (!$tripNo) {
                echo json_encode(['success' => false, 'message' => 'trip_no is required']);
                exit;
            }

            $result = calculateFare(
                $tripNo,
                $data['from'] ?? '',
                $data['to'] ?? '',
                $data['type'] ?? 'regular',
                $data['boarding_id'] ?? null,
                $data['destination_id'] ?? null
            );

            echo json_encode([
                'success' => ($result['fare'] ?? 0) > 0,
                'fare' => $result['fare'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            error_log('Passenger calculate-fare failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Unable to calculate the fare right now.']);
        }

        exit;

    case 'trips':
        require __DIR__ . '/views/TripDashboad/triphistory.php';
    break;

    case 'trips/rejected':
        $_GET['rejected_only'] = '1';
        require __DIR__ . '/views/TripDashboad/triphistory.php';
    break;

    case 'trip/modal':
        require __DIR__ . '/views/TripDashboad/trip_modal.php';
    break;

    case 'profile':
        if (!isset($_SESSION['passenger_id'])) {
            passenger_deny_auth(false);
        }

        require __DIR__ . '/controllers/ProfilePage/ProfileController.php';
        $controller = new ProfileController();
        $user = $controller->getProfile($_SESSION['passenger_id']);
        $outstanding = $controller->getOutstandingBalance($_SESSION['passenger_id']);

        require __DIR__ . '/views/ProfilePage/index.php';
    break;

    case 'profile/edit-modal':
        if (!isset($_SESSION['passenger_id'])) {
            echo 'Unauthorized';
            exit;
        }

        require __DIR__ . '/controllers/ProfilePage/ProfileController.php';
        $controller = new ProfileController();
        $user = $controller->getProfile($_SESSION['passenger_id']);

        require __DIR__ . '/views/ProfilePage/EditProfileModal.php';
    break;

    case 'profile/update':
        header('Content-Type: application/json');

        if (!isset($_SESSION['passenger_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'SESSION LOST']);
            exit;
        }

        require __DIR__ . '/controllers/ProfilePage/ProfileController.php';
        $controller = new ProfileController();
        $controller->update($_SESSION['passenger_id']);

        exit;

    case 'profile/default-payment-method':
        require __DIR__ . '/controllers/ProfilePage/DefaultPaymentMethodController.php';
    break;

    case 'profile/language-modal':
        if (!isset($_SESSION['passenger_id'])) {
            passenger_deny_auth(false);
        }

        require __DIR__ . '/views/ProfilePage/LanguageModal.php';
        exit;

    case 'profile/trusted-device-modal':
        if (!isset($_SESSION['passenger_id'])) {
            passenger_deny_auth(false);
        }

        require __DIR__ . '/views/ProfilePage/TrustedDeviceModal.php';
        exit;

    case 'profile/trusted-device/revoke':
        if (!isset($_SESSION['passenger_id'])) {
            passenger_deny_auth(false);
        }

        require __DIR__ . '/controllers/ProfilePage/ProfileController.php';
        $controller = new ProfileController();
        $controller->revokeTrustedDevice((int) $_SESSION['passenger_id']);

        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool) ($params['secure'] ?? false),
                (bool) ($params['httponly'] ?? true)
            );
            session_destroy();
        }

        header('Location: ' . passenger_url('login'));
        exit;

    case 'forgot-password/modal':
        require __DIR__ . '/views/ProfilePage/ForgotPasswordModal.php';
    break;

    case 'forgot-password/send':
        require __DIR__ . '/controllers/Auth/ForgotPasswordController.php';
        (new ForgotPasswordController())->sendCode();
    break;

    case 'forgot-password/verify':
        require __DIR__ . '/controllers/Auth/ForgotPasswordController.php';
        (new ForgotPasswordController())->verify();
    break;

    case 'forgot-password/update':
        require __DIR__ . '/controllers/Auth/ForgotPasswordController.php';
        (new ForgotPasswordController())->update();
    break;

    case 'logout':
        if (isset($_SESSION['passenger_id'])) {
            $logoutPassengerId = (int) $_SESSION['passenger_id'];

            try {
                $logoutConn = \App\Core\Database::get();
                $logoutHash = hash('sha256', (string) session_id());
                $logoutConn->prepare("
                    UPDATE passenger_sessions
                    SET revoked_at = NOW(), revoked_reason = 'logout'
                    WHERE session_hash = ? AND revoked_at IS NULL
                ")->execute([$logoutHash]);
            } catch (\Throwable $e) {
                error_log('Passenger logout session revoke failed: ' . $e->getMessage());
            }

            /* Revoke only this browser's trusted-device token row, matched by
               the selector in the remember cookie; other devices' tokens for
               the same passenger stay valid. */
            try {
                $rememberCookie = (string) ($_COOKIE['remember_passenger'] ?? '');
                $rememberParts = $rememberCookie !== '' ? explode(':', $rememberCookie) : [];
                $rememberSelector = trim((string) ($rememberParts[0] ?? ''));

                if ($rememberSelector !== '') {
                    if (!isset($logoutConn) || !$logoutConn) {
                        $logoutConn = \App\Core\Database::get();
                    }

                    $logoutConn->prepare("
                        UPDATE passenger_remember_tokens
                        SET revoked_at = NOW()
                        WHERE selector = ? AND passenger_id = ? AND revoked_at IS NULL
                    ")->execute([$rememberSelector, $logoutPassengerId]);
                }
            } catch (\Throwable $e) {
                // Deployments without the trusted-device table skip this quietly.
            }
        }

        /* The remember cookie is HttpOnly: only the server can remove it. */
        $rememberSecure = function_exists('passenger_is_https')
            ? passenger_is_https()
            : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('remember_passenger', '', time() - 42000, '/', '', $rememberSecure, true);

        $_SESSION = [];

        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', (bool) ($params['secure'] ?? false), (bool) ($params['httponly'] ?? true));

        session_destroy();

        header('Location: ' . passenger_url('login'));
        exit;

    case 'payment/proof':
        require __DIR__ . '/controllers/PaymentProof/PaymentProofController.php';
        $controller = new PaymentProofController();
        $controller->handle();
    break;

    case 'api/destinations':
        $_GET['action'] = 'destinations';
        require __DIR__ . '/controllers/Api/DestinationController.php';
    break;

    case 'api/resolve-stop':
        $_GET['action'] = 'resolve-stop';
        require __DIR__ . '/controllers/Api/DestinationController.php';
    break;

    case 'api/calculate-fare':
        $_GET['action'] = 'calculate-fare';
        require __DIR__ . '/controllers/Api/DestinationController.php';
    break;

    case 'api/active-trips':
        $_GET['action'] = 'active-trips';
        require __DIR__ . '/controllers/Api/DestinationController.php';
    break;

    case 'trip/save-state':
        $_GET['action'] = 'save';
        require __DIR__ . '/controllers/PassengerHome/TripStateController.php';
    break;

    case 'trip/restore-state':
        $_GET['action'] = 'restore';
        require __DIR__ . '/controllers/PassengerHome/TripStateController.php';
    break;

    case 'trip/clear-state':
        $_GET['action'] = 'clear';
        require __DIR__ . '/controllers/PassengerHome/TripStateController.php';
    break;

    case 'paymongo/checkout':
        require __DIR__ . '/controllers/PaymentMongo/CheckoutController.php';
        $controller = new CheckoutController();
        $controller->checkout();
    break;

    case 'paymongo/status':
        require __DIR__ . '/controllers/PaymentMongo/CheckoutController.php';
        $controller = new CheckoutController();
        $controller->status();
    break;

    case 'paymongo/qr-image':
        require __DIR__ . '/controllers/PaymentMongo/CheckoutController.php';
        $controller = new CheckoutController();
        $controller->qrImage();
    break;

    case 'paymongo/reconcile':
        require __DIR__ . '/controllers/PaymentMongo/CheckoutController.php';
        $controller = new CheckoutController();
        $controller->reconcile();
    break;

    case 'paymongo/modal':
        require __DIR__ . '/views/payment_modal.php';
    break;

    case 'qr/mark-seen':
        $qrControllerFile = null;
        foreach ([
            __DIR__ . '/controllers/PassengerQR/PassengerQRController.php',
            __DIR__ . '/controllers/PassengerQr/PassengerQrController.php',
        ] as $qrCandidate) {
            if (is_file($qrCandidate)) {
                $qrControllerFile = $qrCandidate;
                break;
            }
        }

        if ($qrControllerFile !== null) {
            require $qrControllerFile;
            $controller = new PassengerQrController();
            $controller->markSeen();
        } else {
            header('Content-Type: application/json');
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'QR service not ready yet.']);
        }
    break;

    default:
        passenger_render_themed_error(
            'Page Not Found',
            'The page you are looking for does not exist or has been moved.',
            'Return to Home',
            passenger_url('home'),
            404
        );
    break;
}
