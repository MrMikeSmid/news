<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/controllers/PublicController.php';
require_once dirname(__DIR__) . '/app/controllers/AdminController.php';

$path = request_path();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!database_is_ready() && !str_starts_with($path, '/setup')) {
    render('simple', [
        'title' => 'Installatie nodig',
        'message' => 'De website is nog niet geïnstalleerd. Open setup.php om de database en het eerste beheeraccount aan te maken.',
    ]);
    exit;
}

$routes = [
    'GET' => [
        '/' => fn () => home(),
        '/interesse' => fn () => form_page('interest'),
        '/vrijwilligers' => fn () => form_page('volunteer'),
        '/sponsoren' => fn () => form_page('sponsor'),
        '/idee' => fn () => form_page('idea'),
        '/privacy' => fn () => render('privacy', ['title' => 'Privacy']),
        '/contact' => fn () => render('contact', ['title' => 'Contact']),
        '/bedankt' => fn () => render('thanks', ['title' => 'Bedankt!']),
        '/admin/login' => fn () => admin_login(),
        '/admin' => fn () => admin_dashboard(),
    ],
    'POST' => [
        '/interesse' => fn () => submit_interest(),
        '/vrijwilligers' => fn () => submit_volunteer(),
        '/sponsoren' => fn () => submit_sponsor(),
        '/idee' => fn () => submit_idea(),
        '/admin/login' => fn () => admin_login(),
        '/admin/logout' => function (): void {
            require_admin();
            verify_csrf();
            logout_admin();
            redirect('admin/login');
        },
    ],
];

if (isset($routes[$method][$path])) {
    $routes[$method][$path]();
    exit;
}

if ($method === 'GET' && preg_match('#^/admin/submissions/(interest|volunteers|sponsors|ideas)$#', $path, $matches)) {
    admin_submissions($matches[1]);
    exit;
}
if ($method === 'GET' && preg_match('#^/admin/export/(interest|volunteers|sponsors|ideas)$#', $path, $matches)) {
    admin_export($matches[1]);
}
if ($method === 'POST' && preg_match('#^/admin/sponsors/(\d+)$#', $path, $matches)) {
    admin_sponsor_update((int) $matches[1]);
    exit;
}

abort(404, 'De pagina die je zoekt bestaat niet.');
