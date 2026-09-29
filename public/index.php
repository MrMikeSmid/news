<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/platforms.php';
require_once dirname(__DIR__) . '/app/controllers/AuthController.php';
require_once dirname(__DIR__) . '/app/controllers/AppController.php';
require_once dirname(__DIR__) . '/app/controllers/PostController.php';
require_once dirname(__DIR__) . '/app/controllers/SettingsController.php';
require_once dirname(__DIR__) . '/app/controllers/MediaController.php';

$path = request_path();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$routes = [
    'GET' => [
        '/' => fn () => redirect(admin_user() ? 'apps' : 'login'),
        '/login' => fn () => login_page(),
        '/apps' => fn () => apps_page(),
        '/settings' => fn () => settings_page(),
    ],
    'POST' => [
        '/login' => fn () => login_page(),
        '/logout' => fn () => logout(),
        '/apps' => fn () => app_create(),
        '/settings' => fn () => settings_update(),
        '/settings/password' => fn () => settings_password_update(),
    ],
];

if (isset($routes[$method][$path])) {
    $routes[$method][$path]();
    exit;
}

$dynamic = [
    ['GET', '#^/media/([A-Za-z0-9._-]+)$#', fn (string $f) => serve_media($f)],
    ['GET', '#^/apps/(\d+)$#', fn (string $id) => app_show((int) $id)],
    ['POST', '#^/apps/(\d+)/delete$#', fn (string $id) => app_delete((int) $id)],
    ['POST', '#^/apps/(\d+)/channels$#', fn (string $id) => channel_create((int) $id)],
    ['GET', '#^/apps/(\d+)/posts/new$#', fn (string $id) => post_new((int) $id)],
    ['POST', '#^/apps/(\d+)/posts$#', fn (string $id) => post_create((int) $id)],
    ['POST', '#^/channels/(\d+)/toggle$#', fn (string $id) => channel_toggle((int) $id)],
    ['POST', '#^/channels/(\d+)/delete$#', fn (string $id) => channel_delete((int) $id)],
    ['GET', '#^/posts/(\d+)/edit$#', fn (string $id) => post_edit((int) $id)],
    ['POST', '#^/posts/(\d+)$#', fn (string $id) => post_update((int) $id)],
    ['POST', '#^/posts/(\d+)/delete$#', fn (string $id) => post_delete((int) $id)],
    ['POST', '#^/posts/(\d+)/publish$#', fn (string $id) => post_publish((int) $id)],
];

foreach ($dynamic as [$verb, $pattern, $handler]) {
    if ($method === $verb && preg_match($pattern, $path, $m)) {
        $handler($m[1]);
        exit;
    }
}

abort(404, 'De pagina die je zoekt bestaat niet.');
