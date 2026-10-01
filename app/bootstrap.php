<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';
date_default_timezone_set($config['timezone']);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/slots.php';
require_once __DIR__ . '/icons.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($config['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => ($config['base_path'] ?: '') . '/',
    ]);
    session_start();
}

migrate();

set_exception_handler(static function (Throwable $exception): void {
    error_log($exception->__toString());
    $api = str_starts_with(request_path(), '/api') || str_starts_with(request_path(), '/mcp');
    http_response_code(500);
    if ($api) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Interne fout.']);
        return;
    }
    $message = 'Er ging iets mis. Probeer het later opnieuw.';
    require __DIR__ . '/views/error.php';
});
