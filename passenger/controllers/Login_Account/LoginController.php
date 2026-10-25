<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/MailerService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/models/Login_Account/loginmodel.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/auth/RememberAuth.php';

use App\Core\MailerService;

class LoginController
{
    public function login()
    {
        if (isset($_GET['back'])) {
            unset(
                $_SESSION['otp'],
                $_SESSION['otp_user'],
                $_SESSION['otp_created'],
                $_SESSION['otp_attempts'],
                $_SESSION['otp_resend_at']
            );

            header('Location: ' . passenger_url('login'));
            exit;
        }

        if (isset($_GET['resend'])) {
            $user = $_SESSION['otp_user'] ?? null;

            if (!$user || empty($user['email'])) {
                $_SESSION['error'] = passenger_t('login.session_expired', 'Session expired. Please login again.');
                header('Location: ' . passenger_url('login'));
                exit;
            }

            $lastResend = (int) ($_SESSION['otp_resend_at'] ?? 0);
            if (time() - $lastResend < 60) {
                $_SESSION['error'] = passenger_t('otp.resend_wait', 'Please wait before requesting another OTP.');
                header('Location: ' . passenger_url('login'));
                exit;
            }

            if (!passenger_rate_limit('login_otp_send', strtolower((string) $user['email']) . '|' . passenger_client_ip(), 5, 900, 900)) {
                $_SESSION['error'] = passenger_t('otp.too_many_requests', 'Too many OTP requests. Please try again later.');
                header('Location: ' . passenger_url('login'));
                exit;
            }

            $otp = random_int(100000, 999999);

            try {
                $mailer = new MailerService();
                $sent = $mailer->sendOtp($user['email'], $otp);

                if ($sent) {
                    $_SESSION['otp'] = $otp;
                    $_SESSION['otp_created'] = time();
                    $_SESSION['otp_attempts'] = 0;
                    $_SESSION['otp_resend_at'] = time();
                    $_SESSION['success'] = passenger_t('otp.resent', 'OTP resent to your email.');
                } else {
                    $_SESSION['error'] = passenger_t('otp.resend_failed', 'Failed to resend OTP.');
                }
            } catch (\Throwable $e) {
                error_log('Passenger login OTP resend failed: ' . $e->getMessage());
                $_SESSION['error'] = passenger_t('otp.send_unavailable', 'Unable to send the OTP right now. Please try again.');
            }

            header('Location: ' . passenger_url('login'));
            exit;
        }

        if (isset($_POST['login']) && empty($_POST['otp'])) {
            $login = trim((string) $_POST['login']);
            $password = (string) ($_POST['password'] ?? '');

            if ($login === '' || $password === '') {
                $_SESSION['error'] = passenger_t('login.fill_fields', 'Please fill all fields.');
                header('Location: ' . passenger_url('login'));
                exit;
            }

            if (mb_strlen($login) > 100 || strlen($password) > 72) {
                $_SESSION['error'] = passenger_t('login.invalid', 'Invalid email/phone or password.');
                header('Location: ' . passenger_url('login'));
                exit;
            }

            if (!passenger_rate_limit('login_ip', passenger_client_ip(), 20, 900, 900)) {
                $_SESSION['error'] = passenger_t('login.too_many_attempts', 'Too many login attempts from this device. Please try again later.');
                header('Location: ' . passenger_url('login'));
                exit;
            }

            /* A lookup failure is a server-side problem: answer with a friendly
               redirect instead of letting the exception become the generic
               service error page. */
            try {
                $model = new LoginModel();
                $user = $model->findPassenger($login);
            } catch (\Throwable $e) {
                error_log('Passenger login lookup failed: ' . $e->getMessage());
                $_SESSION['error'] = passenger_t('login.unavailable', 'We could not sign you in right now. Please try again.');
                header('Location: ' . passenger_url('login'));
                exit;
            }

            /* Credentials are verified before any account-state message: the
               archived/locked wording is only revealed once the supplied
               password proves the account belongs to the requester, so the
               response cannot be used to probe which accounts exist. */
            $passwordVerified = $user && password_verify($password, (string) ($user['password'] ?? ''));

            if ($passwordVerified && strcasecmp((string)($user['status'] ?? 'Active'), 'Archived') === 0) {
                try {
                    require_once $_SERVER['DOCUMENT_ROOT'] . '/app/audit/Audit.php';
                    \App\Audit\Audit::log('Login', 'Login', 'Blocked passenger login: inactive account', $user['id'], $login, 'passenger', null, 'Failed');
                } catch (\Throwable $e) {
                    error_log('Passenger login audit failed: ' . $e->getMessage());
                }

                $_SESSION['auth_alert'] = [
                    'variant' => 'info',
                    'title' => passenger_t('login.inactive_title', 'Your account is currently inactive.'),
                    'detail' => passenger_t('login.inactive_detail', 'Please contact the administrator to reactivate your account.'),
                    'lock_until' => 0,
                ];
                $_SESSION['error'] = passenger_t('login.inactive', 'Your account is currently inactive. Please contact the administrator.');

                header('Location: ' . passenger_url('login'));
                exit;
            }

            if ($passwordVerified) {
                $loginLock = $user['login_locked_until'] ?? null;
                if (!$loginLock && !in_array(strtoupper((string)($user['account_status'] ?? $user['status'] ?? '')), ['BLOCKED', 'SUSPENDED'], true)) $loginLock = $user['blocked_until'] ?? null;
                if (!empty($loginLock)) {
                    $lockTs = strtotime($loginLock);

                    if ($lockTs !== false && $lockTs > time()) {
                        try {
                            require_once $_SERVER['DOCUMENT_ROOT'] . '/app/audit/Audit.php';
                            \App\Audit\Audit::log('Login', 'Login', 'Blocked passenger login: temporarily locked', $user['id'], $login, 'passenger', null, 'Failed');
                        } catch (\Throwable $e) {
                            error_log('Passenger login audit failed: ' . $e->getMessage());
                        }

                        $_SESSION['auth_alert'] = [
                            'variant' => 'locked',
                            'title' => passenger_t('login.locked_title', 'Your account is temporarily locked.'),
                            'detail' => passenger_t('login.locked_detail', 'Try again in %s.'),
                            'lock_until' => (int) $lockTs,
                        ];
                        $_SESSION['error'] = passenger_t('login.locked', 'Too many unsuccessful login attempts. Your account has been temporarily locked for 15 minutes.');

                        header('Location: ' . passenger_url('login'));
                        exit;
                    }
                }

                try {
                    $model->resetAttempts((int) $user['id']);
                } catch (\Throwable $e) {
                    error_log('Passenger login resetAttempts: ' . $e->getMessage());
                }

                $trustedToken = trim((string) ($_POST['trusted_token'] ?? ''));
                if ($trustedToken === '' && !empty($_COOKIE['remember_passenger'])) {
                    $trustedToken = (string) $_COOKIE['remember_passenger'];
                }

                $trusted = $trustedToken !== ''
                    ? \App\Auth\RememberAuth::validatePassengerToken($trustedToken, (int) $user['id'])
                    : null;

                if ($trusted !== null) {
                    $_SESSION['passenger_id'] = $user['id'];
                    $_SESSION['passenger_name'] = $user['full_name'];
                    $_SESSION['passenger_code'] = $user['passenger_code'] ?? '';
                    unset($_SESSION['is_one_time']);

                    try {
                        $conn = \App\Core\Database::get();
                        \App\Passenger\PassengerSessionService::activateDevice($conn, (int) $user['id'], 'trusted_login');
                        \App\Passenger\RateLimiter::reset($conn, 'login_ip', passenger_client_ip());
                    } catch (\Throwable $e) {
                        error_log('Passenger trusted-device login activation failed: ' . $e->getMessage());
                    }

                    /* The remember-me token is a convenience: a storage failure
                       must never turn a successful login into an error page. */
                    $rotatedToken = '';
                    try {
                        $rotatedToken = \App\Auth\RememberAuth::createPassengerToken((int) $user['id']);
                        setcookie('remember_passenger', $rotatedToken, time() + 86400 * 365, '/', '', passenger_is_https(), true);
                    } catch (\Throwable $e) {
                        error_log('Passenger trusted-device token creation failed: ' . $e->getMessage());
                    }

                    $redirect = passenger_url('home');
                    echo '<!DOCTYPE html><html><head>'
                        . '<script src="/passenger/public/js/passenger-storage.js?v=20261016"></script>'
                        . '<script>try{if(window.PassengerStorage)window.PassengerStorage.reset();}catch(e){}'
                        . ($rotatedToken !== '' ? 'try{localStorage.setItem("remember_passenger",' . json_encode($rotatedToken) . ');}catch(e){}' : '')
                        . 'window.location.href=' . json_encode($redirect) . ';</script></head><body></body></html>';
                    exit;
                }

                if (empty($user['email'])) {
                    $_SESSION['error'] = passenger_t('login.no_email', 'User has no email registered.');
                    header('Location: ' . passenger_url('login'));
                    exit;
                }

                if (!passenger_rate_limit('login_otp_send', strtolower((string) $user['email']) . '|' . passenger_client_ip(), 5, 900, 900)) {
                    $_SESSION['error'] = passenger_t('otp.too_many_requests', 'Too many OTP requests. Please try again later.');
                    header('Location: ' . passenger_url('login'));
                    exit;
                }

                $otp = random_int(100000, 999999);

                try {
                    $mailer = new MailerService();
                    $sent = $mailer->sendOtp($user['email'], $otp);

                    if (!$sent) {
                        $_SESSION['error'] = passenger_t('otp.send_failed', 'Failed to send OTP.');
                        header('Location: ' . passenger_url('login'));
                        exit;
                    }
                } catch (\Throwable $e) {
                    error_log('Passenger login OTP send failed: ' . $e->getMessage());
                    $_SESSION['error'] = passenger_t('otp.send_unavailable', 'Unable to send the OTP right now. Please try again.');
                    header('Location: ' . passenger_url('login'));
                    exit;
                }

                $_SESSION['otp'] = $otp;
                $_SESSION['otp_user'] = $user;
                $_SESSION['otp_created'] = time();
                $_SESSION['otp_attempts'] = 0;
                $_SESSION['otp_resend_at'] = time();

                header('Location: ' . passenger_url('login'));
                exit;
            }

            try {
                require_once $_SERVER['DOCUMENT_ROOT'] . '/app/audit/Audit.php';
                \App\Audit\Audit::log('Login', 'Login', 'Failed passenger login: invalid credentials', $user ? $user['id'] : null, $login, 'passenger', null, 'Failed');
            } catch (\Throwable $e) {
                error_log('Passenger login audit failed: ' . $e->getMessage());
            }

            /* Uniform failure response: unknown accounts and wrong passwords
               return byte-identical text. The attempt counter and temporary
               lock still apply server-side for a known account, but they are
               never visible in the response (that would reveal existence). */
            if ($user) {
                $failedAttempts = (int) ($user['failed_login_attempts'] ?? 0) + 1;

                try {
                    $model->incrementAttempts((int) $user['id']);
                } catch (\Throwable $e) {
                    error_log('Passenger login incrementAttempts: ' . $e->getMessage());
                }

                if ($failedAttempts >= 3) {
                    $until = date('Y-m-d H:i:s', time() + (15 * 60));

                    try {
                        $model->lockTemporarily((int) $user['id'], $until);
                    } catch (\Throwable $e) {
                        error_log('Passenger login lockTemporarily: ' . $e->getMessage());
                    }
                }
            }

            $_SESSION['error'] = passenger_t('login.invalid', 'Invalid email/phone or password.');

            header('Location: ' . passenger_url('login'));
            exit;
        }

        if (isset($_POST['otp']) && !empty($_POST['otp'])) {
            $enteredOtp = preg_replace('/\D/', '', (string) $_POST['otp']);
            $sessionOtp = preg_replace('/\D/', '', (string) ($_SESSION['otp'] ?? ''));

            if (strlen($enteredOtp) !== 6) {
                $_SESSION['error'] = passenger_t('otp.invalid_format', 'Please enter a valid 6-digit verification code.');
                header('Location: ' . passenger_url('login'));
                exit;
            }

            if ($sessionOtp === '') {
                $_SESSION['error'] = passenger_t('otp.session_expired', 'Session expired.');
                header('Location: ' . passenger_url('login'));
                exit;
            }

            if (isset($_SESSION['otp_created']) && time() - (int) $_SESSION['otp_created'] > 300) {
                unset($_SESSION['otp'], $_SESSION['otp_user'], $_SESSION['otp_created'], $_SESSION['otp_attempts']);
                $_SESSION['error'] = passenger_t('otp.expired', 'OTP expired.');
                header('Location: ' . passenger_url('login'));
                exit;
            }

            $attempts = (int) ($_SESSION['otp_attempts'] ?? 0) + 1;

            if ($attempts > 5) {
                unset($_SESSION['otp'], $_SESSION['otp_attempts']);
                $_SESSION['error'] = passenger_t('otp.too_many_attempts', 'Too many incorrect attempts. Please request a new OTP.');
                header('Location: ' . passenger_url('login'));
                exit;
            }

            if (strlen($enteredOtp) === 6 && hash_equals($sessionOtp, $enteredOtp)) {
                $_SESSION['passenger_id'] = $_SESSION['otp_user']['id'];
                $_SESSION['passenger_name'] = $_SESSION['otp_user']['full_name'];
                $_SESSION['passenger_code'] = $_SESSION['otp_user']['passenger_code'] ?? '';

                unset($_SESSION['is_one_time']);

                unset(
                    $_SESSION['otp'],
                    $_SESSION['otp_user'],
                    $_SESSION['otp_created'],
                    $_SESSION['otp_attempts'],
                    $_SESSION['otp_resend_at']
                );

                try {
                    $conn = \App\Core\Database::get();
                    \App\Passenger\PassengerSessionService::activateDevice($conn, (int) $_SESSION['passenger_id'], 'login');
                    \App\Passenger\RateLimiter::reset($conn, 'login_ip', passenger_client_ip());
                } catch (\Throwable $e) {
                    error_log('Passenger login device activation failed: ' . $e->getMessage());
                }

                /* The remember-me token is a convenience: a storage failure
                   must never turn a successful OTP login into an error page. */
                $trustedToken = '';
                try {
                    $trustedToken = \App\Auth\RememberAuth::createPassengerToken((int) $_SESSION['passenger_id']);
                    setcookie('remember_passenger', $trustedToken, time() + 86400 * 365, '/', '', passenger_is_https(), true);
                } catch (\Throwable $e) {
                    error_log('Passenger trusted-device token creation failed: ' . $e->getMessage());
                }

                $redirect = passenger_url('home');
                echo '<!DOCTYPE html><html><head>'
                    . '<script src="/passenger/public/js/passenger-storage.js?v=20261016"></script>'
                    . '<script>try{if(window.PassengerStorage)window.PassengerStorage.reset();}catch(e){}'
                    . ($trustedToken !== '' ? 'try{localStorage.setItem("remember_passenger",' . json_encode($trustedToken) . ');}catch(e){}' : '')
                    . 'window.location.href=' . json_encode($redirect) . ';</script></head><body></body></html>';
                exit;
            }

            $_SESSION['otp_attempts'] = $attempts;
            $_SESSION['error'] = passenger_t('otp.invalid', 'Invalid OTP.');
            header('Location: ' . passenger_url('login'));
            exit;
        }

        require $_SERVER['DOCUMENT_ROOT'] . '/passenger/views/Login_Account/index.php';
    }
}
