<?php

namespace CampsiteAgent\Services;

use CampsiteAgent\Templates\EmailTemplates;
use CampsiteAgent\Repositories\EmailLogRepository;
use CampsiteAgent\Repositories\UserPreferencesRepository;
use CampsiteAgent\Repositories\LoginTokenRepository;

class NotificationService
{
    private GmailApiService $gmail;
    private EmailLogRepository $logs;
    private UserPreferencesRepository $preferences;
    private LoginTokenRepository $tokens;

    public function __construct()
    {
        $this->gmail = new GmailApiService();
        $this->logs = new EmailLogRepository();
        $this->preferences = new UserPreferencesRepository();
        $this->tokens = new LoginTokenRepository();
    }

    public function sendVerification(string $toEmail, string $verifyUrl, string $firstName = ''): bool
    {
        $subject = EmailTemplates::verificationSubject();
        $html = EmailTemplates::verificationHtml($verifyUrl, $firstName);
        $text = EmailTemplates::verificationText($verifyUrl, $firstName);
        return $this->sendAndLog($toEmail, $subject, $html, $text);
    }

    public function sendLoginLink(string $toEmail, string $loginUrl, string $firstName = ''): bool
    {
        $subject = EmailTemplates::loginSubject();
        $html = EmailTemplates::loginHtml($loginUrl, $firstName);
        $text = EmailTemplates::loginText($loginUrl, $firstName);
        return $this->sendAndLog($toEmail, $subject, $html, $text);
    }

    public function sendAvailabilityAlert(string $toEmail, string $parkName, string $dateRange, array $sites, array $favoriteSiteIds = [], ?int $userId = null): bool
    {
        $subject = EmailTemplates::alertSubject($parkName, $dateRange);
        
        // Generate disable URL if user ID is provided
        $disableUrl = '';
        if ($userId) {
            $token = $this->tokens->create($userId, 'disable_alerts', 24 * 60); // 24 hours TTL
            $baseUrl = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $disableUrl = "{$protocol}://{$baseUrl}/api/user/disable-alerts/{$token}";
        }
        
        $html = EmailTemplates::alertHtml($parkName, $dateRange, $sites, $favoriteSiteIds, $disableUrl);
        $text = EmailTemplates::alertText($parkName, $dateRange, $sites, $favoriteSiteIds, $disableUrl);
        return $this->sendAndLog($toEmail, $subject, $html, $text);
    }

    /**
     * Send availability alerts to all users with matching preferences
     * @param int $parkId Park ID
     * @param string $parkName Park name for email
     * @param array $sites Array of available sites with weekend_dates
     * @return array Results array with count of emails sent
     */
    public function sendAvailabilityAlertsToMatchingUsers(int $parkId, string $parkName, array $sites): array
    {
        $sent = 0;
        $failed = 0;
        $skipped = 0;
        
        // Extract all weekend dates from sites
        $allWeekendDates = [];
        foreach ($sites as $site) {
            if (isset($site['weekend_dates']) && is_array($site['weekend_dates'])) {
                foreach ($site['weekend_dates'] as $weekend) {
                    $key = $weekend['fri'] . '|' . $weekend['sat'];
                    if (!isset($allWeekendDates[$key])) {
                        $allWeekendDates[$key] = $weekend;
                    }
                }
            }
        }
        
        if (empty($allWeekendDates)) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'No weekend dates found'];
        }
        
        // Get earliest and latest dates for the date range string
        $allFridays = array_column($allWeekendDates, 'fri');
        $earliestDate = min($allFridays);
        $latestSaturday = max(array_column($allWeekendDates, 'sat'));
        $dateRangeStr = date('n/j', strtotime($earliestDate)) . '-' . date('n/j/Y', strtotime($latestSaturday));
        
        // For each weekend date, find matching users
        $usersToNotify = [];
        foreach ($allWeekendDates as $weekend) {
            $fridayDate = $weekend['fri'];
            $users = $this->preferences->getUsersForImmediateAlert($parkId, $fridayDate, true);
            
            foreach ($users as $user) {
                $userId = $user['id'];
                if (!isset($usersToNotify[$userId])) {
                    $usersToNotify[$userId] = [
                        'email' => $user['email'],
                        'first_name' => $user['first_name'] ?? '',
                        'sites' => []
                    ];
                }
            }
        }
        
        // If no users want this alert, skip
        if (empty($usersToNotify)) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => count($sites), 'message' => 'No users match preferences'];
        }
        
        // Send to each matching user
        foreach ($usersToNotify as $userId => $userData) {
            $success = $this->sendAvailabilityAlert(
                $userData['email'],
                $parkName,
                $dateRangeStr,
                $sites,
                $userData['favorite_site_ids'] ?? [],
                $userId
            );
            
            if ($success) {
                $sent++;
            } else {
                $failed++;
            }
        }
        
        return [
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'message' => "Sent to {$sent} user(s), {$failed} failed"
        ];
    }

    private function sendAndLog(string $toEmail, string $subject, string $html, string $text): bool
    {
        try {
            $ok = $this->gmail->send($toEmail, $subject, $html, $text);
            if ($ok) {
                $this->logs->log($toEmail, $subject, strip_tags($html), 'sent', null, [ 'transport' => 'gmail_api' ]);
                return true;
            }
            $this->logs->log($toEmail, $subject, strip_tags($html), 'failed', 'unknown failure', [ 'transport' => 'gmail_api' ]);
            return false;
        } catch (\Throwable $e) {
            $this->logs->log($toEmail, $subject, strip_tags($html), 'failed', $e->getMessage(), [ 'transport' => 'gmail_api' ]);
            return false;
        }
    }
}
