<?php
declare(strict_types = 1);

/*
 * Consolidated into the unified Role Management page.
 * Keeps old bookmarks and notification deep links working.
 */

$params = ['role' => 'admin'];
if (!empty($_GET['open_profile'])) {
    $params['open_profile'] = (string)$_GET['open_profile'];
}
if (!empty($_GET['search'])) {
    $params['search'] = (string)$_GET['search'];
}

header('Location: /admin/role-management?' . http_build_query($params), true, 302);
exit;
