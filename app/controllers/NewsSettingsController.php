<?php

declare(strict_types=1);

function news_settings_page(): void
{
    require_admin();
    render('settings/news', [
        'title' => 'Nieuws',
        'tabs' => settings_nav('news'),
        'sources' => db()->query('SELECT * FROM news_sources ORDER BY name')->fetchAll(),
    ]);
}

function news_source_save(?int $id): void
{
    require_admin();
    verify_csrf();

    $name = mb_substr(clean('name'), 0, 80);
    $url = clean('url');
    $notes = mb_substr(clean('notes'), 0, 500);

    if ($name === '' || !safe_url($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        flash('error', 'Vul een naam en een geldige http(s)-URL in.');
        redirect('settings/news');
    }

    if ($id === null) {
        db()->prepare('INSERT INTO news_sources (name, url, notes) VALUES (:n, :u, :notes)')->execute(['n' => $name, 'u' => $url, 'notes' => $notes ?: null]);
        flash('success', 'Bron toegevoegd.');
    } else {
        db()->prepare('UPDATE news_sources SET name = :n, url = :u, notes = :notes WHERE id = :id')->execute(['n' => $name, 'u' => $url, 'notes' => $notes ?: null, 'id' => $id]);
        flash('success', 'Bron bijgewerkt.');
    }
    redirect('settings/news');
}

function news_source_action(int $id, string $action): void
{
    require_admin();
    verify_csrf();
    if ($action === 'delete') {
        db()->prepare('DELETE FROM news_sources WHERE id = :id')->execute(['id' => $id]);
        flash('success', 'Bron verwijderd.');
    } else {
        db()->prepare('UPDATE news_sources SET is_active = 1 - is_active WHERE id = :id')->execute(['id' => $id]);
    }
    redirect('settings/news');
}
