<?php

declare(strict_types=1);

const SCHEMA_VERSION = 2;

function schema_version(): int
{
    try {
        $value = db()->query("SELECT value FROM settings WHERE key = 'schema_version'")->fetchColumn();
        return $value === false ? 0 : (int) $value;
    } catch (Throwable) {
        return 0;
    }
}

function run_migrations(): void
{
    global $config;

    db()->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)');

    if (schema_version() < SCHEMA_VERSION) {
        db()->exec('DROP TABLE IF EXISTS post_channels');
        db()->exec('DROP TABLE IF EXISTS posts');
        db()->exec('DROP TABLE IF EXISTS sources');
        db()->exec('DROP TABLE IF EXISTS blogs');
    }

    db()->exec(file_get_contents(dirname(__DIR__) . '/database/schema.sql'));

    $count = (int) db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ($count === 0) {
        $statement = db()->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:username, :hash)');
        $statement->execute([
            'username' => $config['default_admin_username'],
            'hash' => password_hash($config['default_admin_password'], PASSWORD_DEFAULT),
        ]);
    }

    if (setting('site_name') === '') {
        set_setting('site_name', 'Publish Hub');
    }
    set_setting('schema_version', (string) SCHEMA_VERSION);

    if (!is_dir($config['uploads_path'])) {
        mkdir($config['uploads_path'], 0775, true);
    }
}
