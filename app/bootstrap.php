<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/views/partials/icons.php';

set_exception_handler(static function (Throwable $exception): void {
    error_log($exception->__toString());
    http_response_code(500);
    $message = 'Er ging iets mis. Probeer het later opnieuw.';
    require __DIR__ . '/views/error.php';
});
