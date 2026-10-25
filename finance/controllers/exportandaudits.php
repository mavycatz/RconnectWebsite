<?php

namespace Finance\Controllers;

require_once __DIR__ . '/../models/ExportAuditModel.php';
require_once __DIR__ . '/../../app/auth/Auth.php';

use Finance\Models\ExportAuditModel;
use App\Auth\Auth;

class ExportandAuditsController
{
    public function index()
    {
        $model = new ExportAuditModel();
        $model->initTables();

        $sessionUser = Auth::user();
        $currentUser = $sessionUser['username'] ?? $sessionUser['full_name'] ?? $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'System';
        $currentRole = $sessionUser['role'] ?? $_SESSION['user']['role'] ?? 'Staff';

        require __DIR__ . '/../views/exportandaudits/index.php';
    }
}