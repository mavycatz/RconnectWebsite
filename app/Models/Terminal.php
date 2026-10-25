<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class Terminal
{
    protected $table = 'terminals';
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::get(); // Use the get() method from App\Core\Database
    }

    public function getActive(): array
    {
        $stmt = $this->db->query(
            "SELECT id, terminal_name FROM terminals WHERE status='Active' ORDER BY terminal_name"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
