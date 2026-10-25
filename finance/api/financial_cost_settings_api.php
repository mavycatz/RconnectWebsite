<?php

declare(strict_types=1);

/**
 * Financial Cost Settings — save and verification endpoint.
 *
 * POST only (verify_status also accepts GET). Requires an authenticated
 * finance/admin session (enforced by validateFinanceRequest + the service
 * role check) and a valid CSRF token.
 *
 * Body (form data):
 *   action      — preview | send_otp | verify_password | verify_otp | verify_status | save
 *   settings    — JSON object keyed by cost_key (preview/save)
 *   fuel_prices — JSON object keyed by fuel_prices id (preview/save)
 *   reason      — required audit reason (save)
 *   password    — account password (verify_password)
 *   otp         — email verification code (verify_otp)
 *
 * Passwords and codes are compared against stored hashes on the server and
 * are never stored in plain text or written to logs.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/init.php';
require_once __DIR__ . '/../middleware/validate_request.php';
require_once __DIR__ . '/../services/FinancialCostSettingsService.php';

use Finance\Services\FinancialCostSettingsService;

validateFinanceRequest('standard', true, true);

$action = strtolower(trim((string) ($_POST['action'] ?? $_GET['action'] ?? 'save')));
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$logFailure = in_array($action, ['save', 'send_otp', 'verify_password', 'verify_otp'], true);

function fcsFail(\Throwable $e, int $status = 422, bool $log = true): void
{
    if ($log) {
        FinancialCostSettingsService::logFailedAttempt($e->getMessage());
    }
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

function fcsFuelPrices(): array
{
    $raw = $_POST['fuel_prices'] ?? '';
    $prices = is_string($raw) ? json_decode($raw, true) : $raw;

    if ($prices === null || $prices === '') {
        return [];
    }

    if (!is_array($prices)) {
        throw new \InvalidArgumentException('Invalid fuel prices payload.');
    }

    return $prices;
}

try {
    if ($action === 'verify_status' && !$isPost) {
        echo json_encode(['success' => true, 'state' => FinancialCostSettingsService::verificationState()]);
        exit;
    }

    if (!$isPost) {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    switch ($action) {
        case 'preview':
            $rawSettings = $_POST['settings'] ?? '';
            $rows = is_string($rawSettings) ? json_decode($rawSettings, true) : $rawSettings;
            if (!is_array($rows)) {
                throw new \InvalidArgumentException('Invalid settings payload.');
            }

            $changes = FinancialCostSettingsService::preview($rows, fcsFuelPrices());
            echo json_encode([
                'success' => true,
                'changed' => array_keys($changes),
                'changes' => array_values($changes),
                'message' => $changes
                    ? count($changes) . ' change' . (count($changes) === 1 ? '' : 's') . ' will be applied.'
                    : 'No changes were made.',
            ]);
            exit;

        case 'send_otp':
            $result = FinancialCostSettingsService::sendOtp();
            echo json_encode([
                'success' => true,
                'message' => 'A verification code was sent to ' . $result['email_masked'] . '.',
                'email_masked' => $result['email_masked'],
                'state' => FinancialCostSettingsService::verificationState(),
            ]);
            exit;

        case 'verify_password':
            $ok = FinancialCostSettingsService::verifyPassword((string) ($_POST['password'] ?? ''));
            echo json_encode([
                'success' => $ok,
                'message' => $ok ? 'Account password verified.' : 'Incorrect account password.',
                'state' => FinancialCostSettingsService::verificationState(),
            ]);
            exit;

        case 'verify_otp':
            $ok = FinancialCostSettingsService::verifyOtp((string) ($_POST['otp'] ?? ''));
            echo json_encode([
                'success' => $ok,
                'message' => $ok ? 'Email verification code confirmed.' : 'Incorrect verification code.',
                'state' => FinancialCostSettingsService::verificationState(),
            ]);
            exit;

        case 'save':
        default:
            $rawSettings = $_POST['settings'] ?? '';
            $rows = is_string($rawSettings) ? json_decode($rawSettings, true) : $rawSettings;

            if (!is_array($rows)) {
                throw new \InvalidArgumentException('Invalid settings payload.');
            }

            $reason = trim((string) ($_POST['reason'] ?? ''));
            $changed = FinancialCostSettingsService::save($rows, $reason, fcsFuelPrices());

            echo json_encode([
                'success' => true,
                'message' => $changed
                    ? count($changed) . ' change' . (count($changed) === 1 ? '' : 's') . ' saved successfully.'
                    : 'No changes were made.',
                'changed' => array_keys($changed),
                'settings' => FinancialCostSettingsService::getSettings(),
                'fuel_prices' => FinancialCostSettingsService::getFuelPrices(),
                'state' => FinancialCostSettingsService::verificationState(),
            ]);
            exit;
    }
} catch (\InvalidArgumentException $e) {
    fcsFail($e, 422, $logFailure);
} catch (\RuntimeException $e) {
    fcsFail($e, 403, $logFailure);
} catch (\Throwable $e) {
    fcsFail(new \RuntimeException('Unable to process the request right now.'), 500, $logFailure);
}
