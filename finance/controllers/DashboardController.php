<?php
namespace Finance\Controllers;

use Finance\Services\DashboardService;
use App\Auth\Auth;

class DashboardController
{
    public function index()
    {
        if (!in_array(Auth::role(), ['finance_manager', 'finance_officer'])) {
            header('Location: /public/login/index.php');
            exit;
        }
        require __DIR__ . '/../views/dashboard/index.php';
    }

    public function data()
    {
        if (!in_array(Auth::role(), ['finance_manager', 'finance_officer'])) {
            header('Location: /public/login/index.php');
            exit;
        }

        $service = new DashboardService();
        $data = $service->getDashboardData();

        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}