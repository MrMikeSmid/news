<?php

declare(strict_types=1);

$configuredBasePath = getenv('APP_BASE_PATH');
$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$detectedBasePath = $scriptDirectory === '/' ? '' : rtrim($scriptDirectory, '/');

return [
    'app_name' => 'Dagstart',
    'timezone' => 'Europe/Amsterdam',
    'base_path' => $configuredBasePath !== false ? rtrim($configuredBasePath, '/') : $detectedBasePath,
    'database_path' => getenv('DAGSTART_DB') ?: dirname(__DIR__) . '/storage/database.sqlite',
    'key_path' => getenv('DAGSTART_KEY') ?: dirname(__DIR__) . '/storage/app.key',
    'session_name' => 'dagstart_session',
    'view_cookie' => 'dagstart_key',
    'slots' => ['morning' => '07:45', 'evening' => '21:00'],
    'kinds' => ['calendar' => 'Agenda', 'tasks' => 'Taken', 'mail' => 'E-mail', 'news' => 'Nieuws'],
];
