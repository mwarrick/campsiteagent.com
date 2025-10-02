<?php

namespace CampsiteAgent\Repositories;

use CampsiteAgent\Infrastructure\Database;
use PDO;

class EmailLogRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function log(string $userEmail, string $subject, string $bodyPreview, string $status, ?string $error = null, array $metadata = []): void
    {
        $sql = 'INSERT INTO notifications (user_email, subject, body_preview, status, error, sent_at, created_at, metadata_json) VALUES (:user_email, :subject, :body_preview, :status, :error, :sent_at, NOW(), :metadata_json)';
        $stmt = $this->pdo->prepare($sql);
        $sentAt = $status === 'sent' ? date('Y-m-d H:i:s') : null;
        $meta = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt->execute([
            ':user_email' => $userEmail,
            ':subject' => $subject,
            ':body_preview' => mb_substr($bodyPreview, 0, 500),
            ':status' => $status,
            ':error' => $error,
            ':sent_at' => $sentAt,
            ':metadata_json' => $meta,
        ]);
    }
}
