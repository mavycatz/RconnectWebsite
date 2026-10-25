<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/init.php';
require_once __DIR__ . '/../middleware/validate_request.php';

use App\Auth\Auth;
use App\Core\Database;

validateFinanceRequest('standard', true, true);

$db = Database::get();

$sessionUser = Auth::user();
$userId = $sessionUser['id'] ?? 0;

if (!$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(\PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$fields = [];
$params = [];

$textFields = ['username', 'email', 'first_name', 'last_name', 'department', 'phone', 'address', 'bio'];
foreach ($textFields as $f) {
    if (isset($_POST[$f])) {
        $fields[] = "`$f` = :$f";
        $params[":$f"] = trim($_POST[$f]);
    }
}

if (!empty($_POST['password'])) {
    $fields[] = "`password` = :password";
    $params[':password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
}

if (!empty($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {

    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo   = finfo_open(FILEINFO_MIME_TYPE);
    $mime    = finfo_file($finfo, $_FILES['profile_photo']['tmp_name']);
    finfo_close($finfo);

    if (in_array($mime, $allowed, true) && $_FILES['profile_photo']['size'] <= 2 * 1024 * 1024) {

        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            default      => 'jpg',
        };

        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/profile/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (!empty($user['profile_photo'])) {
            $oldPath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $user['profile_photo'];
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        $fileName = 'profile_' . $userId . '_' . time() . '.' . $ext;
        $dest     = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $dest)) {
            $fields[] = "`profile_photo` = :profile_photo";
            $params[':profile_photo'] = 'profile/' . $fileName;
        }
    }
}

if (!empty($fields)) {
    $params[':id'] = $userId;
    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
}

if (isset($params[':username'])) {
    $_SESSION['user']['username'] = $params[':username'];
}

header('Content-Type: text/html; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/services/AuditService.php';
\AuditService::log('Profile', 'Update', 'Updated profile from finance', null, null, null, 'Success');

$_SESSION['success'] = 'Profile updated successfully.';
$tk = $_GET['token'] ?? $_SESSION['admin_access_token'] ?? $_COOKIE['admin_token'] ?? '';
header('Location: /finance/profile/' . $tk);
exit;
