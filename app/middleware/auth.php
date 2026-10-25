<?php
require_once __DIR__ . '/../auth/Session.php';
require_once __DIR__ . '/../auth/Auth.php';

Session::start();

if (!Auth::check()) {
    header('Location: /public/login/index.php');
    exit;
}

function requireRole($role)
{
    if (Auth::role() !== $role) {
        if (Auth::role() === 'admin') {
            header('Location: /admin/index.php');
        } else {
            header('Location: /user/index.php');
        }
        exit;
    }
}
