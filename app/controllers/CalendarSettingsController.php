<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/calendar/service.php';

function calendar_settings_page(): void
{
    require_admin();
    render('settings/calendar', ['title' => 'Agenda', 'tabs' => settings_nav('calendar'), 'accounts' => calendar_accounts(false)]);
}

function calendar_account_save(?int $id): void
{
    require_admin();
    verify_csrf();

    $existing = null;
    if ($id !== null) {
        $statement = db()->prepare('SELECT * FROM calendar_accounts WHERE id = :id');
        $statement->execute(['id' => $id]);
        $existing = $statement->fetch();
        if (!$existing) {
            abort(404, 'Account niet gevonden.');
        }
    }

    $label = mb_substr(clean('label'), 0, 80);
    $serverUrl = rtrim(clean('server_url') ?: 'https://caldav.icloud.com', '/');
    $username = clean('username');
    $password = (string) ($_POST['password'] ?? '');

    $error = null;
    if ($label === '' || $username === '') {
        $error = 'Vul naam en gebruikersnaam in.';
    } elseif (!preg_match('#^https?://#i', $serverUrl) || !filter_var($serverUrl, FILTER_VALIDATE_URL)) {
        $error = 'De server-URL moet met https:// beginnen.';
    } elseif ($existing === null && $password === '') {
        $error = 'Vul een (app-specifiek) wachtwoord in.';
    }
    if ($error) {
        flash('error', $error);
        redirect('settings/calendar');
    }

    $values = [
        'label' => $label, 'server_url' => $serverUrl, 'username' => $username,
        'verify_cert' => empty($_POST['accept_all_certs']) ? 1 : 0,
        'calendar_filter' => clean('calendar_filter') ?: null,
    ];
    if ($existing === null) {
        db()->prepare('INSERT INTO calendar_accounts (label, server_url, verify_cert, username, password_enc, calendar_filter)
            VALUES (:label, :server_url, :verify_cert, :username, :password_enc, :calendar_filter)')->execute($values + ['password_enc' => encrypt_secret($password)]);
        flash('success', 'Agenda toegevoegd. Test de verbinding om te controleren of het werkt.');
    } else {
        db()->prepare('UPDATE calendar_accounts SET label=:label, server_url=:server_url, verify_cert=:verify_cert, username=:username,
            password_enc=:password_enc, calendar_filter=:calendar_filter WHERE id=:id')->execute($values + [
            'password_enc' => $password !== '' ? encrypt_secret($password) : $existing['password_enc'], 'id' => $id,
        ]);
        flash('success', 'Agenda bijgewerkt.');
    }
    redirect('settings/calendar');
}

function calendar_account_action(int $id, string $action): void
{
    require_admin();
    verify_csrf();
    $statement = db()->prepare('SELECT * FROM calendar_accounts WHERE id = :id');
    $statement->execute(['id' => $id]);
    $account = $statement->fetch();
    if (!$account) {
        abort(404, 'Account niet gevonden.');
    }

    if ($action === 'delete') {
        db()->prepare('DELETE FROM calendar_accounts WHERE id = :id')->execute(['id' => $id]);
        flash('success', 'Agenda-account verwijderd.');
    } elseif ($action === 'toggle') {
        db()->prepare('UPDATE calendar_accounts SET is_active = 1 - is_active WHERE id = :id')->execute(['id' => $id]);
    } else {
        try {
            flash('success', $account['label'] . ': ' . calendar_test_account($account));
        } catch (Throwable $e) {
            flash('error', $account['label'] . ': ' . $e->getMessage());
        }
    }
    redirect('settings/calendar');
}
