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

    public function getAllUsers(): array
    {
        $stmt = $this->pdo->prepare('SELECT id, first_name, last_name, email, role, active, verified_at, created_at FROM users ORDER BY created_at DESC');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function updateUserStatus(int $userId, bool $active): bool
    {
        // First check if user exists
        $user = $this->findById($userId);
        if (!$user) {
            return false;
        }
        
        $stmt = $this->pdo->prepare('UPDATE users SET active = :active WHERE id = :id');
        $stmt->execute([':active' => $active ? 1 : 0, ':id' => $userId]);
        
        // Return true if user exists, regardless of whether rows were actually updated
        // (MySQL won't update if the values are the same)
        return true;
    }

    public function updateUserRole(int $userId, string $role): bool
    {
        // First check if user exists
        $user = $this->findById($userId);
        if (!$user) {
            return false;
        }
        
        $stmt = $this->pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
        $stmt->execute([':role' => $role, ':id' => $userId]);
        
        // Return true if user exists, regardless of whether rows were actually updated
        // (MySQL won't update if the values are the same)
        return true;
    }

    public function findById(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateUserNames(int $userId, string $firstName, string $lastName): bool
    {
        // First check if user exists
        $user = $this->findById($userId);
        if (!$user) {
            return false;
        }
        
        $stmt = $this->pdo->prepare('UPDATE users SET first_name = :first_name, last_name = :last_name WHERE id = :id');
        $stmt->execute([
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':id' => $userId
        ]);
        
        // Return true if user exists, regardless of whether rows were actually updated
        // (MySQL won't update if the values are the same)
        return true;
    }
}
