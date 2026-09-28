<?php

declare(strict_types=1);

function login_page(): void
{
    if (admin_user()) {
        redirect('wall');
    }

    $error = null;

    if (is_post()) {
        verify_csrf();
        $username = clean('username');
        $password = (string) ($_POST['password'] ?? '');

        if (attempt_login($username, $password)) {
            redirect('wall');
        }
        $error = 'Onjuiste gebruikersnaam of wachtwoord.';
    }

    http_response_code($error ? 401 : 200);
    render('login', ['title' => 'Inloggen', 'error' => $error]);
}

function logout(): void
{
    require_admin();
    verify_csrf();
    logout_admin();
    redirect('login');
}
