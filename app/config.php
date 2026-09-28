<?php

declare(strict_types=1);

$configuredBasePath = getenv('APP_BASE_PATH');
$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$detectedBasePath = $scriptDirectory === '/' ? '' : rtrim($scriptDirectory, '/');

return [
    'app_name' => 'Homebase',
    'base_path' => $configuredBasePath !== false ? rtrim($configuredBasePath, '/') : $detectedBasePath,
    'database_path' => dirname(__DIR__) . '/storage/database.sqlite',
    'uploads_path' => dirname(__DIR__) . '/storage/uploads',
    'session_name' => 'homebase_session',
    'default_admin_username' => 'admin',
    'default_admin_password' => 'admin123!',
    'max_upload_bytes' => 15 * 1024 * 1024,
    'allowed_image_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    'allowed_video_types' => ['mp4', 'webm', 'mov'],
];
