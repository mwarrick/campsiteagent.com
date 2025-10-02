<?php

namespace CampsiteAgent\Repositories;

use CampsiteAgent\Infrastructure\Database;
use PDO;

class LoginTokenRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function create(int $userId, string $type, int $ttlMinutes = 30): string
    {
        $token = bin2hex(random_bytes(24));
        $expiresAt = date('Y-m-d H:i:s', time() + ($ttlMinutes * 60));
        $sql = 'INSERT INTO login_tokens (user_id, token, type, expires_at) VALUES (:user_id, :token, :type, :expires_at)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':token' => $token,
            ':type' => $type,
            ':expires_at' => $expiresAt,
        ]);
        return $token;
    }

    public function consume(string $token, string $expectedType): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM login_tokens WHERE token = :token AND type = :type AND used_at IS NULL AND expires_at > NOW() FOR UPDATE');
            $stmt->execute([':token' => $token, ':type' => $expectedType]);
            $row = $stmt->fetch();
            if (!$row) {
                $this->pdo->rollBack();
                return null;
            }
            $upd = $this->pdo->prepare('UPDATE login_tokens SET used_at = NOW() WHERE id = :id');
            $upd->execute([':id' => $row['id']]);
            $this->pdo->commit();
            return $row;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
