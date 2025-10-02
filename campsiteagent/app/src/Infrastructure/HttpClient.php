<?php

namespace CampsiteAgent\Infrastructure;

class HttpClient
{
    private int $timeoutSec;
    private int $maxRetries;
    private string $userAgent;

    public function __construct(?string $userAgentOverride = null)
    {
        $this->timeoutSec = (int)(getenv('RC_TIMEOUT') ?: 15);
        $this->maxRetries = (int)(getenv('RC_MAX_RETRIES') ?: 3);
        $envUa = getenv('RC_USER_AGENT') ?: null;
        $this->userAgent = $userAgentOverride ?? $envUa ?? 'CampsiteAgent/1.0 (+http://campsiteagent.com)';
    }

    public function get(string $url, array $headers = []): array
    {
        $attempt = 0;
        $lastError = null;
        while ($attempt < $this->maxRetries) {
            $attempt++;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeoutSec);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeoutSec);
            $allHeaders = array_merge([
                'Accept: application/json, */*;q=0.8',
                'User-Agent: ' . $this->userAgent,
            ], $this->normalizeHeaders($headers));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);

            $body = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($body === false || $status >= 500 || $status === 0) {
                $lastError = $err ?: ('HTTP ' . $status);
                usleep($this->backoffMicros($attempt));
                continue;
            }

            return [$status, $body];
        }
        throw new \RuntimeException('HTTP GET failed after retries: ' . ($lastError ?? 'unknown error'));
    }

    public function post(string $url, array $data = [], array $headers = []): array
    {
        $attempt = 0;
        $lastError = null;
        while ($attempt < $this->maxRetries) {
            $attempt++;
            $ch = curl_init();
            $jsonData = json_encode($data);
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeoutSec);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeoutSec);
            $allHeaders = array_merge([
                'Content-Type: application/json',
                'Accept: application/json, */*;q=0.8',
                'User-Agent: ' . $this->userAgent,
            ], $this->normalizeHeaders($headers));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);

            $body = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($body === false || $status >= 500 || $status === 0) {
                $lastError = $err ?: ('HTTP ' . $status);
                usleep($this->backoffMicros($attempt));
                continue;
            }

            return [$status, $body];
        }
        throw new \RuntimeException('HTTP POST failed after retries: ' . ($lastError ?? 'unknown error'));
    }

    private function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $k => $v) {
            if (is_int($k)) { $out[] = $v; continue; }
            $out[] = $k . ': ' . $v;
        }
        return $out;
    }

    private function backoffMicros(int $attempt): int
    {
        $base = 250000; // 250ms
        $max = 4000000; // 4s
        $delay = $base * (2 ** ($attempt - 1));
        return (int)min($delay, $max);
    }
}
