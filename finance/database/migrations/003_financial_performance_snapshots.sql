CREATE TABLE IF NOT EXISTS financial_performance_snapshots (
    report_id INT NOT NULL PRIMARY KEY,
    snapshot_json LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
