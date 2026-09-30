<?php

declare(strict_types=1);

$configuredBasePath = getenv('APP_BASE_PATH');
$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$detectedBasePath = $scriptDirectory === '/' ? '' : rtrim($scriptDirectory, '/');

return [
    'app_name' => 'Homebase',
    'base_path' => $configuredBasePath !== false ? rtrim($configuredBasePath, '/') : $detectedBasePath,
];
