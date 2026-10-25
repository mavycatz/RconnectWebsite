<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Bus;

class BusController {
    private Bus $model;

    public function __construct() {
        $this->model = new Bus();
    }

    public function getAllBuses(): array {
        return $this->model->fetchAll();
    }

    public function addBus(array $data): bool {
        return $this->model->insert($data);
    }

    public function editBus(int $id, array $data): bool {
        return $this->model->update($id, $data);
    }
}
