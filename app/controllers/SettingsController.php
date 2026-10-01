<?php

declare(strict_types=1);

function settings_nav(string $active): string
{
    $tabs = ['' => 'Toegang', 'mail' => 'E-mail', 'calendar' => 'Agenda', 'news' => 'Nieuws'];
    $html = '<div class="range-toggle tabs" role="group" aria-label="Instellingen">';
    foreach ($tabs as $slug => $label) {
        $html .= '<a class="' . ($slug === $active ? 'active' : '') . '" href="' . e(url('settings' . ($slug ? '/' . $slug : ''))) . '">' . e($label) . '</a>';
    }
    return $html . '</div>';
}

function settings_page(): void
{
    require_admin();
    render('settings/access', [
        'title' => 'Instellingen',
        'tabs' => settings_nav(''),
        'viewLink' => absolute_url('?k=' . setting('view_key')),
        'agentToken' => setting('agent_token'),
        'mcpUrl' => absolute_url('mcp'),
        'apiUrl' => absolute_url('api/sections'),
    ]);
}

function settings_regenerate(string $which): void
{
    require_admin();
    verify_csrf();
    if ($which === 'view') {
        set_setting('view_key', bin2hex(random_bytes(20)));
        flash('success', 'Nieuwe sleutel-link gemaakt. Oude links werken niet meer.');
    } else {
        set_setting('agent_token', bin2hex(random_bytes(20)));
        flash('success', 'Nieuwe agent-token gemaakt. Pas hem aan in je MCP-koppeling en geplande taken.');
    }
    redirect('settings');
}

function settings_password_update(): void
{
    require_admin();
    verify_csrf();
    $user = admin_user();

    $statement = db()->prepare('SELECT password_hash FROM users WHERE id = :id');
    $statement->execute(['id' => $user['id']]);
    $hash = (string) $statement->fetchColumn();

    $new = (string) ($_POST['new_password'] ?? '');
    if (!password_verify((string) ($_POST['current_password'] ?? ''), $hash)) {
        flash('error', 'Huidig wachtwoord klopt niet.');
    } elseif (strlen($new) < 10) {
        flash('error', 'Nieuw wachtwoord moet minimaal 10 tekens zijn.');
    } elseif ($new !== (string) ($_POST['new_password_confirmation'] ?? '')) {
        flash('error', 'De wachtwoorden komen niet overeen.');
    } else {
        db()->prepare('UPDATE users SET password_hash = :h WHERE id = :id')->execute(['h' => password_hash($new, PASSWORD_DEFAULT), 'id' => $user['id']]);
        flash('success', 'Wachtwoord gewijzigd.');
    }
    redirect('settings');
}
