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
    $pdo->exec('PRAGMA busy_timeout = 5000');

    return $pdo;
}

function migrate(): void
{
    db()->exec((string) file_get_contents(dirname(__DIR__) . '/database/schema.sql'));

    foreach (['view_key', 'agent_token'] as $key) {
        if (setting($key) === '') {
            set_setting($key, bin2hex(random_bytes(20)));
        }
    }
}

function setting(string $key, string $default = ''): string
{
    $statement = db()->prepare('SELECT value FROM settings WHERE key = :key');
    $statement->execute(['key' => $key]);
    $value = $statement->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function set_setting(string $key, string $value): void
{
    db()->prepare('INSERT INTO settings (key, value) VALUES (:key, :value)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value')->execute(['key' => $key, 'value' => $value]);
}
