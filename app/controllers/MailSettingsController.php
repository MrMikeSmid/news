<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/mail/service.php';

function mail_settings_page(): void
{
    require_admin();
    render('settings/mail', ['title' => 'E-mail', 'tabs' => settings_nav('mail'), 'accounts' => mail_accounts(false)]);
}

function mail_account_save(?int $id): void
{
    require_admin();
    verify_csrf();

    $existing = null;
    if ($id !== null) {
        $statement = db()->prepare('SELECT * FROM mail_accounts WHERE id = :id');
        $statement->execute(['id' => $id]);
        $existing = $statement->fetch();
        if (!$existing) {
            abort(404, 'Account niet gevonden.');
        }
    }

    $protocol = clean('protocol') === 'pop3' ? 'pop3' : 'imap';
    $security = in_array(clean('security'), ['ssl', 'starttls', 'none'], true) ? clean('security') : 'ssl';
    $port = (int) clean('port');
    $label = mb_substr(clean('label'), 0, 80);
    $host = clean('host');
    $username = clean('username');
    $password = (string) ($_POST['password'] ?? '');

    $error = null;
    if ($label === '' || $host === '' || $username === '') {
        $error = 'Vul naam, server en gebruikersnaam in.';
    } elseif (!preg_match('/^[A-Za-z0-9.\-]+$/', $host)) {
        $error = 'De servernaam is ongeldig.';
    } elseif ($port < 1 || $port > 65535) {
        $error = 'De poort is ongeldig.';
    } elseif ($existing === null && $password === '') {
        $error = 'Vul een (app-specifiek) wachtwoord in.';
    }
    if ($error) {
        flash('error', $error);
        redirect('settings/mail');
    }

    $smtpHost = clean('smtp_host');
    $smtpPassword = (string) ($_POST['smtp_password'] ?? '');
    $values = [
        'label' => $label, 'protocol' => $protocol, 'host' => $host, 'port' => $port, 'security' => $security,
        'verify_cert' => empty($_POST['accept_all_certs']) ? 1 : 0,
        'username' => $username,
        'folder' => clean('folder') ?: 'INBOX',
        'smtp_host' => $smtpHost !== '' ? $smtpHost : null,
        'smtp_port' => $smtpHost !== '' ? ((int) clean('smtp_port') ?: 587) : null,
        'smtp_security' => $smtpHost !== '' && in_array(clean('smtp_security'), ['ssl', 'starttls', 'none'], true) ? clean('smtp_security') : null,
        'smtp_verify_cert' => empty($_POST['smtp_accept_all_certs']) ? 1 : 0,
        'smtp_username' => clean('smtp_username') ?: null,
    ];

    if ($existing === null) {
        db()->prepare('INSERT INTO mail_accounts (label, protocol, host, port, security, verify_cert, username, password_enc, folder,
            smtp_host, smtp_port, smtp_security, smtp_verify_cert, smtp_username, smtp_password_enc)
            VALUES (:label, :protocol, :host, :port, :security, :verify_cert, :username, :password_enc, :folder,
            :smtp_host, :smtp_port, :smtp_security, :smtp_verify_cert, :smtp_username, :smtp_password_enc)')->execute($values + [
            'password_enc' => encrypt_secret($password),
            'smtp_password_enc' => $smtpPassword !== '' ? encrypt_secret($smtpPassword) : null,
        ]);
        flash('success', 'Account toegevoegd. Test de verbinding om te controleren of het werkt.');
    } else {
        $values['password_enc'] = $password !== '' ? encrypt_secret($password) : $existing['password_enc'];
        $values['smtp_password_enc'] = $smtpPassword !== '' ? encrypt_secret($smtpPassword) : $existing['smtp_password_enc'];
        $values['id'] = $id;
        db()->prepare('UPDATE mail_accounts SET label=:label, protocol=:protocol, host=:host, port=:port, security=:security,
            verify_cert=:verify_cert, username=:username, password_enc=:password_enc, folder=:folder, smtp_host=:smtp_host,
            smtp_port=:smtp_port, smtp_security=:smtp_security, smtp_verify_cert=:smtp_verify_cert, smtp_username=:smtp_username,
            smtp_password_enc=:smtp_password_enc WHERE id=:id')->execute($values);
        flash('success', 'Account bijgewerkt.');
    }
    redirect('settings/mail');
}

function mail_account_action(int $id, string $action): void
{
    require_admin();
    verify_csrf();
    $statement = db()->prepare('SELECT * FROM mail_accounts WHERE id = :id');
    $statement->execute(['id' => $id]);
    $account = $statement->fetch();
    if (!$account) {
        abort(404, 'Account niet gevonden.');
    }

    if ($action === 'delete') {
        db()->prepare('DELETE FROM mail_accounts WHERE id = :id')->execute(['id' => $id]);
        flash('success', 'Account verwijderd.');
    } elseif ($action === 'toggle') {
        db()->prepare('UPDATE mail_accounts SET is_active = 1 - is_active WHERE id = :id')->execute(['id' => $id]);
    } else {
        try {
            flash('success', $account['label'] . ': ' . mail_test_account($account));
        } catch (Throwable $e) {
            flash('error', $account['label'] . ': ' . $e->getMessage());
        }
    }
    redirect('settings/mail');
}
