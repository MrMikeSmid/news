<?php

declare(strict_types=1);

function admin_user(): ?array
{
    if (empty($_SESSION['admin_id']) || !database_is_ready()) {
        return null;
    }
    $statement = db()->prepare('SELECT id, username, created_at FROM admin_users WHERE id = :id');
    $statement->execute(['id' => (int) $_SESSION['admin_id']]);
    return $statement->fetch() ?: null;
}

function require_admin(): array
{
    $user = admin_user();
    if (!$user) {
        flash('error', 'Log eerst in om het beheer te openen.');
        redirect('admin/login');
    }
    return $user;
}

function attempt_login(string $username, string $password): bool
{
    $statement = db()->prepare('SELECT * FROM admin_users WHERE username = :username LIMIT 1');
    $statement->execute(['username' => $username]);
    $user = $statement->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $user['id'];
    return true;
}

function logout_admin(): void
{
    unset($_SESSION['admin_id']);
    session_regenerate_id(true);
}
