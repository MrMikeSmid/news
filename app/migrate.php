<?php

declare(strict_types=1);

function run_migrations(): void
{
    global $config;

    $schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
    db()->exec($schema);

    $count = (int) db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ($count === 0) {
        $statement = db()->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:username, :hash)');
        $statement->execute([
            'username' => $config['default_admin_username'],
            'hash' => password_hash($config['default_admin_password'], PASSWORD_DEFAULT),
        ]);
    }

    $defaults = [
        'site_name' => 'Mijn Homebase',
        'api_endpoint_url' => '',
        'api_key' => '',
        'cron_token' => bin2hex(random_bytes(16)),
    ];
    foreach ($defaults as $key => $value) {
        $exists = db()->prepare('SELECT 1 FROM settings WHERE key = :key');
        $exists->execute(['key' => $key]);
        if (!$exists->fetchColumn()) {
            $insert = db()->prepare('INSERT INTO settings (key, value) VALUES (:key, :value)');
            $insert->execute(['key' => $key, 'value' => $value]);
        }
    }

    if (!is_dir($config['uploads_path'])) {
        mkdir($config['uploads_path'], 0775, true);
    }
}
