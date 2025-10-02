<?php

namespace CampsiteAgent\Repositories;

use CampsiteAgent\Infrastructure\Database;
use PDO;

class UserRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function create(string $firstName, string $lastName, string $email, string $role = 'user'): int
    {
        $sql = 'INSERT INTO users (first_name, last_name, email, role) VALUES (:first_name, :last_name, :email, :role)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':email' => mb_strtolower($email),
            ':role' => $role,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => mb_strtolower($email)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function markVerified(int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET verified_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $userId]);
    }

    public function setRoleByEmail(string $email, string $role): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET role = :role WHERE email = :email');
        $stmt->execute([':role' => $role, ':email' => mb_strtolower($email)]);
        return $stmt->rowCount() > 0;
    }
}
