<?php

declare(strict_types=1);

function settings_page(): void
{
    require_admin();
    render('settings', [
        'title' => 'Instellingen',
        'siteName' => setting('site_name'),
        'apiEndpoint' => setting('api_endpoint_url'),
        'apiKey' => setting('api_key'),
        'cronToken' => setting('cron_token'),
    ]);
}

function settings_update(): void
{
    require_admin();
    verify_csrf();

    $siteName = clean('site_name');
    $apiEndpoint = clean('api_endpoint_url');
    $apiKey = clean('api_key');

    if ($siteName === '') {
        flash('error', 'Geef je homebase een naam.');
        redirect('settings');
    }
    if ($apiEndpoint !== '' && !filter_var($apiEndpoint, FILTER_VALIDATE_URL)) {
        flash('error', 'Het API-endpoint is geen geldige URL.');
        redirect('settings');
    }

    set_setting('site_name', mb_substr($siteName, 0, 120));
    set_setting('api_endpoint_url', $apiEndpoint);
    set_setting('api_key', $apiKey);

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

function settings_regenerate_cron_token(): void
{
    require_admin();
    verify_csrf();
    set_setting('cron_token', bin2hex(random_bytes(16)));
    flash('success', 'Nieuwe cron-token gegenereerd.');
    redirect('settings');
}
