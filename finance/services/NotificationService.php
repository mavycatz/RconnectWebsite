<?php

namespace Finance\Services;

use App\Models\Notification;

class NotificationService
{
    public static function create(
        int $userId,
        string $title,
        string $description = '',
        string $type = 'info'
    ): int {
        $model = new Notification();
        return $model->create($userId, $title, $description, $type);
    }
}
