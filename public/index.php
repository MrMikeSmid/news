<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/fetcher.php';
require_once dirname(__DIR__) . '/app/controllers/AuthController.php';
require_once dirname(__DIR__) . '/app/controllers/WallController.php';
require_once dirname(__DIR__) . '/app/controllers/SourceController.php';
require_once dirname(__DIR__) . '/app/controllers/BlogController.php';
require_once dirname(__DIR__) . '/app/controllers/SettingsController.php';
require_once dirname(__DIR__) . '/app/controllers/MediaController.php';

$path = request_path();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$routes = [
    'GET' => [
        '/' => fn () => redirect(admin_user() ? 'wall' : 'login'),
        '/login' => fn () => login_page(),
        '/wall' => fn () => wall_page(),
        '/sources' => fn () => sources_page(),
        '/blogs' => fn () => blogs_page(),
        '/blogs/new' => fn () => blog_new(),
        '/settings' => fn () => settings_page(),
        '/cron/fetch' => fn () => cron_fetch(),
    ],
    'POST' => [
        '/login' => fn () => login_page(),
        '/logout' => fn () => logout(),
        '/wall/post' => fn () => create_manual_post(),
        '/sources' => fn () => create_source(),
        '/sources/refresh-all' => fn () => refresh_all_sources(),
        '/blogs' => fn () => blog_create(),
        '/settings' => fn () => settings_update(),
        '/settings/password' => fn () => settings_password_update(),
        '/settings/cron-token' => fn () => settings_regenerate_cron_token(),
    ],
];

if (isset($routes[$method][$path])) {
    $routes[$method][$path]();
    exit;
}

if ($method === 'GET' && preg_match('#^/media/([A-Za-z0-9._-]+)$#', $path, $m)) {
    serve_media($m[1]);
    exit;
}
if ($method === 'GET' && preg_match('#^/blogs/(\d+)/edit$#', $path, $m)) {
    blog_edit((int) $m[1]);
    exit;
}
if ($method === 'POST' && preg_match('#^/blogs/(\d+)$#', $path, $m)) {
    blog_update((int) $m[1]);
    exit;
}
if ($method === 'POST' && preg_match('#^/blogs/(\d+)/delete$#', $path, $m)) {
    blog_delete((int) $m[1]);
    exit;
}
if ($method === 'POST' && preg_match('#^/blogs/(\d+)/send$#', $path, $m)) {
    blog_send((int) $m[1]);
    exit;
}
if ($method === 'POST' && preg_match('#^/posts/(\d+)/delete$#', $path, $m)) {
    delete_post((int) $m[1]);
    exit;
}
if ($method === 'POST' && preg_match('#^/sources/(\d+)/toggle$#', $path, $m)) {
    toggle_source((int) $m[1]);
    exit;
}
if ($method === 'POST' && preg_match('#^/sources/(\d+)/delete$#', $path, $m)) {
    delete_source((int) $m[1]);
    exit;
}
if ($method === 'POST' && preg_match('#^/sources/(\d+)/refresh$#', $path, $m)) {
    refresh_source((int) $m[1]);
    exit;
}

abort(404, 'De pagina die je zoekt bestaat niet.');
