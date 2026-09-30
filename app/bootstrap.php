<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/mail/accounts.php';
require_once __DIR__ . '/mail/service.php';
require_once __DIR__ . '/mail/pop3.php';
if (is_file(dirname(__DIR__) . '/vendor/autoload.php')) require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/views/partials/icons.php';

set_exception_handler(static function (Throwable $exception): void {
    error_log('Homebase mail: ' . get_class($exception));
    if(PHP_SAPI==='cli')exit(1);
    http_response_code(500);
    $message = 'Er ging iets mis. Probeer het later opnieuw.';
    require __DIR__ . '/views/error.php';
});
