<?php

declare(strict_types=1);

function setup_page(): void
{
    if (user_count() > 0) {
        redirect('login');
    }
    $error = null;
    if (is_post()) {
        verify_csrf();
        $username = clean('username');
        $password = (string) ($_POST['password'] ?? '');
        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            $error = 'Gebruik een gebruikersnaam van minimaal 3 tekens zonder spaties.';
        } elseif (strlen($password) < 10) {
            $error = 'Kies een wachtwoord van minimaal 10 tekens.';
        } elseif ($password !== (string) ($_POST['password_confirmation'] ?? '')) {
            $error = 'De wachtwoorden komen niet overeen.';
        } else {
            db()->prepare('INSERT INTO users (username, password_hash) VALUES (:u, :h)')->execute(['u' => $username, 'h' => password_hash($password, PASSWORD_DEFAULT)]);
            attempt_login($username, $password);
            flash('success', 'Account aangemaakt. Zet hieronder je sleutel-link en koppelingen klaar.');
            redirect('settings');
        }
    }
    render('setup', ['title' => 'Instellen', 'error' => $error], 'auth_layout');
}

function login_page(): void
{
    if (user_count() === 0) {
        redirect('setup');
    }
    if (admin_user()) {
        redirect('settings');
    }
    $error = null;
    if (is_post()) {
        verify_csrf();
        if (attempt_login(clean('username'), (string) ($_POST['password'] ?? ''))) {
            redirect('settings');
        }
        $error = 'Onjuiste gebruikersnaam of wachtwoord.';
    }
    render('login', ['title' => 'Inloggen', 'error' => $error], 'auth_layout');
}

function logout(): void
{
    verify_csrf();
    unset($_SESSION['user_id']);
    session_regenerate_id(true);
    redirect('/');
}

/** Tijdelijke noodroute: met een eenmalige herstelcode een nieuw wachtwoord instellen. */
function recover_page(): void
{
    global $config;
    $hash = (string) ($config['recovery_code_hash'] ?? '');
    if ($hash === '') {
        abort(404, 'De pagina die je zoekt bestaat niet.');
    }
    if (user_count() === 0) {
        redirect('setup');
    }

    $error = null;
    if (is_post()) {
        verify_csrf();
        $password = (string) ($_POST['password'] ?? '');
        if (!hash_equals($hash, hash('sha256', trim((string) ($_POST['code'] ?? ''))))) {
            usleep(800000);
            $error = 'Die herstelcode klopt niet.';
        } elseif (strlen($password) < 10) {
            $error = 'Kies een wachtwoord van minimaal 10 tekens.';
        } elseif ($password !== (string) ($_POST['password_confirmation'] ?? '')) {
            $error = 'De wachtwoorden komen niet overeen.';
        } else {
            $user = db()->query('SELECT id, username FROM users ORDER BY id LIMIT 1')->fetch();
            db()->prepare('UPDATE users SET password_hash = :h WHERE id = :id')->execute(['h' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
            attempt_login($user['username'], $password);
            flash('success', 'Wachtwoord ingesteld. Je gebruikersnaam is "' . $user['username'] . '".');
            redirect('settings');
        }
    }
    render('recover', ['title' => 'Wachtwoord herstellen', 'error' => $error], 'auth_layout');
}
