<?php
require_once __DIR__ . '/../config/database.php';

class User
{
    private $db;

    public function __construct() {
        $this->db = Database::connect(); // Use your Database class
    }

    // Find user by username
    public function findByUsername($username)
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
        $stmt->execute(['username' => $username]);
        return $stmt->fetch();
    }

    // Verify user password
    public function verifyPassword($username, $password)
    {
        $user = $this->findByUsername($username);
        if (!$user) return false;

        if (password_verify($password, $user['password'])) {
            return $user; 
        }

        return false;
    }
}
