<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/services/PassengerAccountService.php';

final class PassengerRestrictionController
{
    public static function handle(string $route): void
    {
        $db = passenger_db();
        if (!$db) { http_response_code(503); echo 'Account information is temporarily unavailable.'; return; }
        $id = (int)$_SESSION['passenger_id'];
        $account = PassengerAccountService::state($db, $id);
        if ($route === 'account/appeal') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); return; }
            if (!passenger_rate_limit('account_appeal', (string)$id, 5, 3600, 3600)) { http_response_code(429); echo 'Please wait before submitting another appeal.'; return; }
            try {
                PassengerAccountService::appeal($db, $id, (string)($_POST['message'] ?? ''));
                $_SESSION['account_appeal_notice'] = 'Your appeal has been submitted to the cooperative administrator.';
            } catch (DomainException | InvalidArgumentException $e) { $_SESSION['account_appeal_notice'] = $e->getMessage(); }
            header('Location: ' . passenger_url('account/restriction')); return;
        }
        if ($route === 'account/notifications/read') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); return; }
            /* Dismissal is best-effort: a missing or unreadable notification
               table must not turn the redirect into a 500. */
            try {
                $db->prepare('UPDATE passenger_account_notifications SET read_at = NOW() WHERE id = ? AND passenger_id = ?')->execute([(int)($_POST['notification_id'] ?? 0), $id]);
            } catch (\Throwable $e) {
                error_log('Passenger account notification dismiss failed: ' . $e->getMessage());
            }
            header('Location: ' . passenger_url($account['effective_account_status'] === 'ACTIVE' ? 'home' : 'account/restriction')); return;
        }
        if ($account['effective_account_status'] === 'ACTIVE') { header('Location: ' . passenger_url('home')); return; }
        $stmt = $db->prepare('SELECT message, status, review_note, created_at FROM passenger_account_appeals WHERE passenger_id = ? ORDER BY id DESC LIMIT 10'); $stmt->execute([$id]);
        $appeals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        require dirname(__DIR__, 2) . '/views/account-restriction.php';
    }
}
