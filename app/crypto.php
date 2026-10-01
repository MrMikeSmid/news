<?php

declare(strict_types=1);

function app_key(): string
{
    global $config;
    $path = $config['key_path'];
    if (!is_file($path)) {
        file_put_contents($path, base64_encode(random_bytes(32)));
        @chmod($path, 0600);
    }
    return base64_decode((string) file_get_contents($path), true) ?: '';
}

function encrypt_secret(string $plain): string
{
    $iv = random_bytes(12);
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new RuntimeException('Versleutelen mislukt.');
    }
    return base64_encode($iv . $tag . $cipher);
}

function decrypt_secret(string $encoded): string
{
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < 29) {
        return '';
    }
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? '' : $plain;
}
