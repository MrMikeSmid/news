<?php

declare(strict_types=1);

function start_mail_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('homebase_mail');
    session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => url()]);
    session_start();
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: DENY');
}

function owner_exists(): bool { return (bool) query('SELECT id FROM mail_admin WHERE id=1')->fetchColumn(); }
function owner_logged_in(): bool {
    if(empty($_SESSION['mail_owner']) || (int)($_SESSION['mail_login_time']??0) < time()-12*3600) return false;
    return owner_exists();
}
function redirect_to(string $path): never { header('Location: ' . url($path), true, 303); exit; }
function require_owner(): void
{
    if (!owner_exists()) redirect_to('setup');
    if (!owner_logged_in()) { if(($_SERVER['REQUEST_METHOD']??'GET')==='GET' && (str_contains($_SERVER['HTTP_ACCEPT']??'','text/html') || request_path()==='/oauth/authorize')) $_SESSION['return_path'] = request_path().(!empty($_SERVER['QUERY_STRING'])?'?'.$_SERVER['QUERY_STRING']:''); redirect_to('login'); }
}
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">'; }
function check_csrf(): void
{
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals(csrf(), $_POST['csrf'])) abort(419, 'Je sessie is verlopen. Vernieuw de pagina en probeer opnieuw.');
}
function flash_message(string $message, bool $error = false): void { $_SESSION['flash'] = ['message' => $message, 'error' => $error]; }
function pull_flash(): ?array { $value = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $value; }
function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) throw new InvalidArgumentException('Ongeldige invoer.');
    return trim($value);
}
function rate_limit(string $key, int $limit = 10, int $window = 900): void
{
    $key = hash('sha256', $key);
    query('INSERT INTO mail_login_attempts(key,attempts,reset_at) VALUES(?,1,?) ON CONFLICT(key) DO UPDATE SET attempts=CASE WHEN reset_at<? THEN 1 ELSE attempts+1 END, reset_at=CASE WHEN reset_at<? THEN excluded.reset_at ELSE reset_at END', [$key, time()+$window, time(), time()]);
    if ((int) query('SELECT attempts FROM mail_login_attempts WHERE key=?', [$key])->fetchColumn() > $limit) throw new RuntimeException('Te veel pogingen. Probeer het over een kwartier opnieuw.');
}

function public_base_url(): string
{
    $value = rtrim(getenv('APP_URL') ?: mail_setting('public_url'), '/');
    if ($value === '') throw new RuntimeException('Stel eerst het publieke HTTPS-webadres in bij Chatkoppeling.');
    return $value;
}
function valid_public_url(string $value): string
{
    $parts = parse_url($value);
    if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || (isset($parts['user']) || isset($parts['pass'])) || isset($parts['query']) || isset($parts['fragment'])) throw new InvalidArgumentException('Gebruik het volledige HTTPS-webadres van de app, zonder query of fragment.');
    return rtrim($value, '/');
}
