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

function selected(string $key, string $value): string
{
    return (($_POST[$key] ?? '') === $value) ? ' selected' : '';
}

function checked(string $key, string $value = '1'): string
{
    $actual = $_POST[$key] ?? null;
    return ($actual === $value || (is_array($actual) && in_array($value, $actual, true))) ? ' checked' : '';
}

function validate_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function clean(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function render(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/views/' . $view . '.php';
    $content = (string) ob_get_clean();
    require __DIR__ . '/views/layout.php';
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

function submission_counts(): array
{
    if (!database_is_ready()) {
        return ['interest' => 0, 'teams' => 0, 'volunteers' => 0, 'sponsors' => 0, 'ideas' => 0];
    }
    $pdo = db();
    return [
        'interest' => (int) $pdo->query("SELECT COUNT(*) FROM interest_submissions WHERE participation_choice != 'no'")->fetchColumn(),
        'teams' => (int) $pdo->query("SELECT COUNT(*) FROM interest_submissions WHERE participation_choice = 'team'")->fetchColumn(),
        'volunteers' => (int) $pdo->query('SELECT COUNT(*) FROM volunteer_submissions')->fetchColumn(),
        'sponsors' => (int) $pdo->query('SELECT COUNT(*) FROM sponsor_submissions')->fetchColumn(),
        'ideas' => (int) $pdo->query('SELECT COUNT(*) FROM idea_submissions')->fetchColumn(),
    ];
}

function abort(int $status, string $message): never
{
    http_response_code($status);
    render('simple', ['title' => 'Oeps', 'message' => $message]);
    exit;
}
