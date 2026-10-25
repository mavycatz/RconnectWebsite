<?php

namespace Finance\Controllers;

class DocumentationController
{
    public function index()
    {
        $page = $_GET['page'] ?? null;
        $allowed = ['finance-guide','audit-policy','audit-template','monthly-summary','data-protection','audit-records'];
        $token = $GLOBALS['finance_token'] ?? '';

        if ($page && in_array($page, $allowed, true)) {
            require __DIR__ . '/../views/documentation/detail.php';
        } else {
            require __DIR__ . '/../views/documentation/index.php';
        }
    }
}