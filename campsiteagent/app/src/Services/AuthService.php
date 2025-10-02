<?php

namespace CampsiteAgent\Services;

use CampsiteAgent\Repositories\UserRepository;
use CampsiteAgent\Repositories\LoginTokenRepository;

class AuthService
{
    private UserRepository $users;
    private LoginTokenRepository $tokens;
    private NotificationService $notifications;
    private string $appUrl;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->tokens = new LoginTokenRepository();
        $this->notifications = new NotificationService();
        $this->appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
    }

    public function sendVerificationEmail(string $email, string $firstName = '', string $lastName = ''): bool
    {
        $user = $this->users->findByEmail($email);
        if (!$user) {
            $userId = $this->users->create($firstName, $lastName, $email);
            $user = [ 'id' => $userId, 'first_name' => $firstName, 'last_name' => $lastName, 'email' => $email ];
        }
        $token = $this->tokens->create((int)$user['id'], 'verify', 60);
        $verifyUrl = $this->appUrl . '/api/auth/verify?token=' . urlencode($token);
        return $this->notifications->sendVerification($user['email'], $verifyUrl, $user['first_name'] ?? '');
    }

    public function sendLoginEmail(string $email): bool
    {
        $user = $this->users->findByEmail($email);
        if (!$user || empty($user['verified_at'])) {
            return false; // must be registered and verified
        }
        $token = $this->tokens->create((int)$user['id'], 'login', 30);
        $loginUrl = $this->appUrl . '/api/auth/callback?token=' . urlencode($token);
        return $this->notifications->sendLoginLink($user['email'], $loginUrl, $user['first_name'] ?? '');
    }
}
