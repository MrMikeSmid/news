<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/reports.php';

foreach (['Auth', 'Dashboard', 'Api', 'Settings', 'MailSettings', 'CalendarSettings', 'NewsSettings', 'Pwa'] as $controller) {
    $file = dirname(__DIR__) . '/app/controllers/' . $controller . 'Controller.php';
    if (is_file($file)) {
        require_once $file;
    }
}

handle_view_key();

$path = request_path();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($path === '/mcp') {
    require_once dirname(__DIR__) . '/app/mcp.php';
    mcp_handle();
    exit;
}

$routes = [
    'GET' => [
        '/' => fn () => dashboard_page(),
        '/login' => fn () => login_page(),
        '/setup' => fn () => setup_page(),
        '/recover' => fn () => recover_page(),
        '/settings' => fn () => settings_page(),
        '/settings/mail' => fn () => mail_settings_page(),
        '/settings/calendar' => fn () => calendar_settings_page(),
        '/settings/news' => fn () => news_settings_page(),
        '/manifest.json' => fn () => pwa_manifest(),
        '/sw.js' => fn () => pwa_service_worker(),
    ],
    'POST' => [
        '/login' => fn () => login_page(),
        '/setup' => fn () => setup_page(),
        '/recover' => fn () => recover_page(),
        '/logout' => fn () => logout(),
        '/settings/password' => fn () => settings_password_update(),
        '/settings/mail' => fn () => mail_account_save(null),
        '/settings/calendar' => fn () => calendar_account_save(null),
        '/settings/news' => fn () => news_source_save(null),
        '/api/sections' => fn () => api_sections(),
    ],
];

if (isset($routes[$method][$path])) {
    $routes[$method][$path]();
    exit;
}

$dynamic = [
    ['GET', '#^/rapport/(\d{4}-\d{2}-\d{2})/(morning|evening)$#', fn (string $d, string $s) => dashboard_page($d, $s)],
    ['POST', '#^/settings/regenerate/(view|agent)$#', fn (string $w) => settings_regenerate($w)],
    ['POST', '#^/settings/mail/(\d+)$#', fn (string $id) => mail_account_save((int) $id)],
    ['POST', '#^/settings/mail/(\d+)/(delete|toggle|test)$#', fn (string $id, string $a) => mail_account_action((int) $id, $a)],
    ['POST', '#^/settings/calendar/(\d+)$#', fn (string $id) => calendar_account_save((int) $id)],
    ['POST', '#^/settings/calendar/(\d+)/(delete|toggle|test)$#', fn (string $id, string $a) => calendar_account_action((int) $id, $a)],
    ['POST', '#^/settings/news/(\d+)$#', fn (string $id) => news_source_save((int) $id)],
    ['POST', '#^/settings/news/(\d+)/(delete|toggle)$#', fn (string $id, string $a) => news_source_action((int) $id, $a)],
];

foreach ($dynamic as [$verb, $pattern, $handler]) {
    if ($method === $verb && preg_match($pattern, $path, $m)) {
        $handler(...array_slice($m, 1));
        exit;
    }
}

abort(404, 'De pagina die je zoekt bestaat niet.');
