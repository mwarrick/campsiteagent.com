#!/usr/bin/env php
<?php

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Services\AuthService;

function usage(): void {
    fwrite(STDERR, "Usage: php bin/send-login.php email@example.com\n");
}

$email = $argv[1] ?? null;
if (!$email) {
    usage();
    exit(1);
}

$auth = new AuthService();
$ok = $auth->sendLoginEmail($email);

if ($ok) {
    echo "Login email sent to {$email}\n";
    exit(0);
}

fwrite(STDERR, "Failed to send login email to {$email} (user must be verified)\n");
exit(2);
