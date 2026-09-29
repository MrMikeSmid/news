<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/migrate.php';
require_once __DIR__ . '/views/partials/icons.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($config['session_name']);
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => ($config['base_path'] ?: '') . '/',
    ]);
    session_start();
}

if (!database_is_ready() || schema_version() < SCHEMA_VERSION) {
    run_migrations();
}

set_exception_handler(static function (Throwable $exception): void {
    error_log($exception->__toString());
    http_response_code(500);
    $message = 'Er ging iets mis. Probeer het later opnieuw.';
    require __DIR__ . '/views/error.php';
});
