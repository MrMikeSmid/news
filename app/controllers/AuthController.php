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
