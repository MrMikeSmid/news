<?php

declare(strict_types=1);

function user_count(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
}

function admin_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $statement = db()->prepare('SELECT id, username FROM users WHERE id = :id');
    $statement->execute(['id' => (int) $_SESSION['user_id']]);
    return $statement->fetch() ?: null;
}

function require_admin(): array
{
    $user = admin_user();
    if (!$user) {
        flash('error', 'Log in om je instellingen te openen.');
        redirect('login');
    }
    return $user;
}

/** Wie de sleutel-link heeft (of is ingelogd) mag de verslagen lezen. */
function can_view(): bool
{
    global $config;
    if (admin_user()) {
        return true;
    }
    $key = setting('view_key');
    $cookie = $_COOKIE[$config['view_cookie']] ?? '';
    return $key !== '' && is_string($cookie) && hash_equals($key, $cookie);
}

/** Verwerkt ?k=SLEUTEL: zet een langlevende cookie en stuurt door naar een schone URL. */
function handle_view_key(): void
{
    global $config;
    $given = $_GET['k'] ?? null;
    if (!is_string($given) || $given === '') {
        return;
    }
    $key = setting('view_key');
    if ($key !== '' && hash_equals($key, $given)) {
        setcookie($config['view_cookie'], $key, [
            'expires' => time() + 400 * 86400,
            'path' => ($config['base_path'] ?: '') . '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[$config['view_cookie']] = $key;
        $query = $_GET;
        unset($query['k']);
        redirect(request_path() . ($query ? '?' . http_build_query($query) : ''));
    }
}

function attempt_login(string $username, string $password): bool
{
    $statement = db()->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
    $statement->execute(['username' => $username]);
    $user = $statement->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        usleep(400000);
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    return true;
}

function agent_authorized(): bool
{
    $expected = setting('agent_token');
    if ($expected === '') {
        return false;
    }
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $given = '';
    if (preg_match('/^Bearer\s+(.+)$/i', (string) $header, $m)) {
        $given = trim($m[1]);
    } elseif (!empty($_SERVER['HTTP_X_AGENT_TOKEN'])) {
        $given = (string) $_SERVER['HTTP_X_AGENT_TOKEN'];
    } elseif (isset($_GET['token']) && is_string($_GET['token'])) {
        $given = $_GET['token'];
    }
    return $given !== '' && hash_equals($expected, $given);
}
