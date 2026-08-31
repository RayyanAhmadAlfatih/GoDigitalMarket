<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

function db_configured(): bool
{
    return trim((string)($_ENV['DB_HOST'] ?? '')) !== ''
        && trim((string)($_ENV['DB_NAME'] ?? '')) !== ''
        && trim((string)($_ENV['DB_USER'] ?? '')) !== '';
}

function db(): ?PDO
{
    static $pdo = null;
    static $failed = false;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if ($failed || !db_configured()) {
        return null;
    }

    try {
        $host = trim((string)$_ENV['DB_HOST']);
        $name = trim((string)$_ENV['DB_NAME']);
        $port = trim((string)($_ENV['DB_PORT'] ?? '3306'));
        $charset = trim((string)($_ENV['DB_CHARSET'] ?? 'utf8mb4'));

        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset={$charset}",
            (string)$_ENV['DB_USER'],
            (string)($_ENV['DB_PASS'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return $pdo;
    } catch (Throwable $e) {
        $failed = true;
        return null;
    }
}

function db_available(): bool
{
    return db() instanceof PDO;
}
