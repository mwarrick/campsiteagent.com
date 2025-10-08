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

    /**
     * Returns true if a Daily Digest was already sent to this email today.
     * We detect digest by subject containing "Daily Digest" and sent_at date is today.
     */
    public function hasSentDailyDigestToday(string $userEmail): bool
    {
        $sql = 'SELECT COUNT(1) AS cnt
                FROM notifications
                WHERE user_email = :email
                  AND status = "sent"
                  AND sent_at IS NOT NULL
                  AND DATE(sent_at) = CURDATE()
                  AND subject LIKE :subjectLike';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':email' => $userEmail,
            ':subjectLike' => '%Daily Digest%'
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return isset($row['cnt']) && (int)$row['cnt'] > 0;
    }
}
