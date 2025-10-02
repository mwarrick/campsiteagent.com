<?php

namespace CampsiteAgent\Services;

use Google\Client as GoogleClient;
use Google\Service\Gmail as GmailService;
use Google\Service\Gmail\Message as GmailMessage;

class GmailApiService
{
    private GoogleClient $client;
    private GmailService $gmail;
    private string $fromAddress;
    private string $fromName;

    public function __construct()
    {
        $this->fromAddress = getenv('MAIL_FROM') ?: 'no-reply@campsiteagent.com';
        $this->fromName = getenv('MAIL_FROM_NAME') ?: 'Campsite Agent';

        $this->client = new GoogleClient();
        $this->client->setApplicationName('Campsite Agent');
        $this->client->setScopes([GmailService::GMAIL_SEND]);
        $this->client->setAuthConfig(getenv('GOOGLE_CREDENTIALS_JSON') ?: '');
        $this->client->setAccessType('offline');

        $tokenPath = getenv('GOOGLE_TOKEN_JSON') ?: '';
        if ($tokenPath && file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $this->client->setAccessToken($accessToken);
        }

        if ($this->client->isAccessTokenExpired()) {
            if ($this->client->getRefreshToken()) {
                $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            } else {
                // For CLI auth flow: open consent URL
                $authUrl = $this->client->createAuthUrl();
                fwrite(STDERR, "Open this link in your browser to authorize:\n{$authUrl}\nEnter verification code: ");
                $authCode = trim(fgets(STDIN));
                $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);
                $this->client->setAccessToken($accessToken);
            }
            if ($tokenPath) {
                if (!file_exists(dirname($tokenPath))) {
                    mkdir(dirname($tokenPath), 0700, true);
                }
                file_put_contents($tokenPath, json_encode($this->client->getAccessToken()));
            }
        }

        $this->gmail = new GmailService($this->client);
    }

    public function send(string $toEmail, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        $raw = $this->createRawMime($toEmail, $subject, $htmlBody, $textBody);
        $message = new GmailMessage();
        $message->setRaw($raw);
        $this->gmail->users_messages->send('me', $message);
        return true;
    }

    private function createRawMime(string $toEmail, string $subject, string $htmlBody, ?string $textBody = null): string
    {
        $boundary = uniqid('np');
        $headers = [];
        $headers[] = 'From: ' . $this->formatAddress($this->fromAddress, $this->fromName);
        $headers[] = 'To: ' . $toEmail;
        $headers[] = 'Subject: ' . $subject;
        $headers[] = 'MIME-Version: 1.0';

        if ($textBody !== null) {
            $headers[] = 'Content-Type: multipart/alternative; boundary=' . $boundary;
            $mime = '';
            $mime .= "--{$boundary}\r\n";
            $mime .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
            $mime .= $textBody . "\r\n";
            $mime .= "--{$boundary}\r\n";
            $mime .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $mime .= $htmlBody . "\r\n";
            $mime .= "--{$boundary}--";
            $body = $mime;
        } else {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $body = $htmlBody;
        }

        $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        return rtrim(strtr(base64_encode($message), '+/', '-_'), '='); // base64url
    }

    private function formatAddress(string $email, string $name): string
    {
        $safeName = addcslashes($name, '"');
        return sprintf('"%s" <%s>', $safeName, $email);
    }
}
