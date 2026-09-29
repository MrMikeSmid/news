<?php

declare(strict_types=1);

function settings_page(): void
{
    require_admin();
    render('settings', ['title' => 'Instellingen', 'siteName' => setting('site_name')]);
}

function settings_update(): void
{
    require_admin();
    verify_csrf();

    $siteName = clean('site_name');
    if ($siteName === '') {
        flash('error', 'Geef je hub een naam.');
        redirect('settings');
    }

    set_setting('site_name', mb_substr($siteName, 0, 120));
    flash('success', 'Instellingen opgeslagen.');
    redirect('settings');
}

function settings_password_update(): void
{
    require_admin();
    verify_csrf();
    $user = admin_user();

    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['new_password_confirmation'] ?? '');

    $statement = db()->prepare('SELECT password_hash FROM admin_users WHERE id = :id');
    $statement->execute(['id' => $user['id']]);
    $hash = $statement->fetchColumn();

    if (!$hash || !password_verify($current, $hash)) {
        flash('error', 'Huidig wachtwoord klopt niet.');
        redirect('settings');
    }
    if (strlen($new) < 8) {
        flash('error', 'Nieuw wachtwoord moet minimaal 8 tekens zijn.');
        redirect('settings');
    }
    if ($new !== $confirm) {
        flash('error', 'Wachtwoorden komen niet overeen.');
        redirect('settings');
    }

    db()->prepare('UPDATE admin_users SET password_hash = :hash WHERE id = :id')
        ->execute(['hash' => password_hash($new, PASSWORD_DEFAULT), 'id' => $user['id']]);

    flash('success', 'Wachtwoord gewijzigd.');
    redirect('settings');
}
