#!/usr/bin/env php
<?php

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Services\AuthService;

function usage(): void {
    fwrite(STDERR, "Usage: php bin/send-verification.php email@example.com [FirstName] [LastName]\n");
}

$email = $argv[1] ?? null;
$first = $argv[2] ?? '';
$last = $argv[3] ?? '';

if (!$email) {
    usage();
    exit(1);
}

$auth = new AuthService();
$ok = $auth->sendVerificationEmail($email, $first, $last);

if ($ok) {
    echo "Verification email sent to {$email}\n";
    exit(0);
}

fwrite(STDERR, "Failed to send verification email to {$email}\n");
exit(2);
