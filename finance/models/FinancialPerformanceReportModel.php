<?php
declare(strict_types=1);
namespace Finance\Models;

use PDO;

final class FinancialPerformanceReportModel
{
    private static ?bool $available = null;
    public static function initialize(PDO $db): void
    {
        $db->exec('CREATE TABLE IF NOT EXISTS financial_performance_snapshots (
            report_id INT NOT NULL PRIMARY KEY,
            snapshot_json LONGTEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        self::$available = true;
    }

    public static function save(PDO $db, int $reportId, array $data): void
    {
        $stmt = $db->prepare('INSERT INTO financial_performance_snapshots (report_id, snapshot_json) VALUES (?, ?)');
        $stmt->execute([$reportId, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
    }

    public static function get(PDO $db, int $reportId): ?array
    {
        if (self::$available === null) {
            $exists = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $exists->execute(['financial_performance_snapshots']);
            self::$available = (int) $exists->fetchColumn() > 0;
        }
        if (!self::$available) return null;
        $stmt = $db->prepare('SELECT snapshot_json FROM financial_performance_snapshots WHERE report_id = ?');
        $stmt->execute([$reportId]);
        $json = $stmt->fetchColumn();
        return $json === false ? null : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
