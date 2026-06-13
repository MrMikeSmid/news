<?php

declare(strict_types=1);

return [
    'app_name' => 'Sportweek ’t Zand',
    'base_path' => getenv('APP_BASE_PATH') ?: '',
    'database_path' => dirname(__DIR__) . '/storage/database.sqlite',
    'session_name' => 'sportweek_session',
    'contact_email' => 'info@sportweektzand.nl',
    'socials' => [
        'Facebook' => '',
        'Instagram' => '',
        'TikTok' => '',
    ],
];
