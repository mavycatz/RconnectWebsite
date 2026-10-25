<?php
if (!empty($_SESSION['passenger_id']) && isset($conn) && $conn instanceof PDO) {
    /* Notifications are an optional widget: the storage read must never take
       down the page hosting it. A missing or unreadable table degrades to
       "no notifications"; the account-storage readiness check heals a missing
       table on the next request. */
    $notifications = [];
    try {
        $noticeStmt = $conn->prepare('SELECT id, message FROM passenger_account_notifications WHERE passenger_id = ? AND read_at IS NULL ORDER BY id DESC LIMIT 3');
        $noticeStmt->execute([(int)$_SESSION['passenger_id']]);
        $notifications = $noticeStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        error_log('Passenger account notifications read failed: ' . $e->getMessage());
        $notifications = [];
    }

    if ($notifications) {
        if (!empty($passengerNotificationAlert)) {
?>
<div id="passengerAccountAlert" role="alertdialog" aria-modal="true" aria-labelledby="passengerAccountAlertTitle" style="position:fixed;inset:0;z-index:12000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45);padding:20px;">
    <div style="width:100%;max-width:400px;max-height:80vh;overflow:auto;background:#fff;border-radius:18px;padding:22px;color:#24513c;box-shadow:0 12px 40px rgba(0,0,0,.2);">
        <h3 id="passengerAccountAlertTitle" style="font-size:18px;margin:0 0 14px;">Account Update</h3>
        <?php foreach ($notifications as $notice):
            $noticeMessage = str_replace(['Your discount document was automatically verified. ', 'Your document was automatically verified. '], '', (string) $notice['message']); ?>
        <div style="padding:10px 0;border-bottom:1px solid #eef2ec;">
            <p><?= htmlspecialchars($noticeMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <form method="post" action="<?= htmlspecialchars(passenger_url('account/notifications/read'), ENT_QUOTES) ?>">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(passenger_csrf_token(), ENT_QUOTES) ?>">
                <input type="hidden" name="notification_id" value="<?= (int) $notice['id'] ?>">
                <button type="submit" style="border:0;border-radius:10px;background:#47613b;color:#fff;padding:10px 20px;">Got it</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php
        } else {
        foreach ($notifications as $notice) {
?>
<div role="status" style="margin:12px 16px;padding:14px;background:#eef6ee;border:1px solid #cdd9b5;border-radius:12px;color:#295738;">
<?= htmlspecialchars($notice['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
<form method="post" action="<?= htmlspecialchars(passenger_url('account/notifications/read'), ENT_QUOTES) ?>"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(passenger_csrf_token(), ENT_QUOTES) ?>"><input type="hidden" name="notification_id" value="<?= (int)$notice['id'] ?>"><button type="submit" style="margin-top:8px;border:1px solid #cdd9b5;border-radius:8px;background:#fff;color:#295738;">Dismiss</button></form>
</div>
<?php } } } } ?>
