#!/usr/bin/env php
<?php

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Repositories\UserRepository;

function usage(): void {
    fwrite(STDERR, "Usage: php bin/promote-admin.php email@example.com\n");
}

$email = $argv[1] ?? null;
if (!$email) {
    usage();
    exit(1);
}

$repo = new UserRepository();
$user = $repo->findByEmail($email);
if (!$user) {
    fwrite(STDERR, "User not found: {$email}\n");
    exit(2);
}

$ok = $repo->setRoleByEmail($email, 'admin');
if ($ok) {
    echo "Promoted {$email} to admin\n";
    exit(0);
}

fwrite(STDERR, "Failed to update role for {$email}\n");
exit(3);
