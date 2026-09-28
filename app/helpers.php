<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    global $config;
    $base = rtrim((string) $config['base_path'], '/');
    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('public/assets/' . ltrim($path, '/'));
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function request_path(): string
{
    global $config;
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = rtrim((string) $config['base_path'], '/');
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base)) ?: '/';
    }
    return '/' . trim($path, '/');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function old(string $key, string $default = ''): string
{
    return e($_POST[$key] ?? $default);
}

function clean(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function setting(string $key, string $default = ''): string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $statement = db()->prepare('SELECT value FROM settings WHERE key = :key');
    $statement->execute(['key' => $key]);
    $value = $statement->fetchColumn();
    return $cache[$key] = ($value === false ? $default : (string) $value);
}

function set_setting(string $key, string $value): void
{
    $statement = db()->prepare('INSERT INTO settings (key, value) VALUES (:key, :value)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $statement->execute(['key' => $key, 'value' => $value]);
}

function render(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/views/' . $view . '.php';
    $content = (string) ob_get_clean();
    require __DIR__ . '/views/layout.php';
}

function render_partial(string $view, array $data = []): string
{
    extract($data, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/views/' . $view . '.php';
    return (string) ob_get_clean();
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $message = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $message;
}

function time_ago(string $datetime): string
{
    $timestamp = strtotime($datetime . ' UTC');
    if ($timestamp === false) {
        return $datetime;
    }
    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'zojuist';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . 'm geleden';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . 'u geleden';
    }
    if ($diff < 2592000) {
        return floor($diff / 86400) . 'd geleden';
    }
    return date('d-m-Y', $timestamp);
}

function slugify(string $text): string
{
    $text = trim($text);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'item';
}

function abort(int $status, string $message): never
{
    http_response_code($status);
    render('simple', ['title' => 'Oeps', 'message' => $message]);
    exit;
}
