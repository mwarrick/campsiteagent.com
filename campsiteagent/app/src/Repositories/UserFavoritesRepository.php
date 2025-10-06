<?php

namespace CampsiteAgent\Repositories;

use CampsiteAgent\Infrastructure\Database;
use PDO;

class UserFavoritesRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function listFavoriteSiteIds(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT site_id FROM user_favorites WHERE user_id = :uid');
        $stmt->execute([':uid' => $userId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'site_id'));
    }

    public function isFavorite(int $userId, int $siteId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM user_favorites WHERE user_id = :uid AND site_id = :sid LIMIT 1');
        $stmt->execute([':uid' => $userId, ':sid' => $siteId]);
        return (bool)$stmt->fetchColumn();
    }

    public function toggleFavorite(int $userId, int $siteId): bool
    {
        if ($this->isFavorite($userId, $siteId)) {
            $stmt = $this->pdo->prepare('DELETE FROM user_favorites WHERE user_id = :uid AND site_id = :sid');
            $stmt->execute([':uid' => $userId, ':sid' => $siteId]);
            return false;
        }
        $stmt = $this->pdo->prepare('INSERT INTO user_favorites (user_id, site_id) VALUES (:uid, :sid)');
        $stmt->execute([':uid' => $userId, ':sid' => $siteId]);
        return true;
    }
}


