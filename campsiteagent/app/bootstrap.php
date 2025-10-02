<?php

require __DIR__ . '/vendor/autoload.php';

if (class_exists(\Dotenv\Dotenv::class)) {
    $dotenv = \Dotenv\Dotenv::createUnsafeImmutable(__DIR__);
    $dotenv->safeLoad();
}
