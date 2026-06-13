<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    global $config;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $directory = dirname($config['database_path']);
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $config['database_path'], null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    return $pdo;
}

function database_is_ready(): bool
{
    try {
        $query = db()->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
        return (bool) $query->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}
