<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$path = request_path();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($path !== '/') {
    abort(404, 'De pagina die je zoekt bestaat niet.');
}
if ($method !== 'GET' && $method !== 'HEAD') {
    header('Allow: GET, HEAD');
    abort(405, 'Deze aanvraag wordt niet ondersteund.');
}

render('home', ['title' => 'Start']);
