<?php

declare(strict_types=1);

$configuredBasePath = getenv('APP_BASE_PATH');
$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$detectedBasePath = $scriptDirectory === '/' ? '' : rtrim($scriptDirectory, '/');

return [
    'app_name' => 'Sportweek ’t Zand',
    'base_path' => $configuredBasePath !== false ? rtrim($configuredBasePath, '/') : $detectedBasePath,
    'database_path' => dirname(__DIR__) . '/storage/database.sqlite',
    'session_name' => 'sportweek_session',
    'contact_email' => 'info@sportweektzand.nl',
    'socials' => [
        'Facebook' => '',
        'Instagram' => '',
        'TikTok' => '',
    ],
];
