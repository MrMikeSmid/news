<?php

declare(strict_types=1);

function sources_page(): void
{
    require_admin();

    $sources = db()->query('SELECT * FROM sources ORDER BY created_at DESC')->fetchAll();

    render('sources', [
        'title' => 'Bronnen',
        'sources' => $sources,
    ]);
}

function create_source(): void
{
    require_admin();
    verify_csrf();

    $name = clean('name');
    $type = clean('type') === 'manual' ? 'manual' : 'rss';
    $category = clean('category') ?: 'nieuws';
    $url = clean('url');
    $color = clean('color') ?: '#da7756';

    if ($name === '') {
        flash('error', 'Geef de bron een naam.');
        redirect('sources');
    }
    if ($type === 'rss' && !filter_var($url, FILTER_VALIDATE_URL)) {
        flash('error', 'Geef een geldige feed-URL op voor een RSS-bron.');
        redirect('sources');
    }
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        $color = '#da7756';
    }

    $statement = db()->prepare('INSERT INTO sources (name, type, category, url, color) VALUES (:name, :type, :category, :url, :color)');
    $statement->execute([
        'name' => mb_substr($name, 0, 120),
        'type' => $type,
        'category' => mb_substr($category, 0, 60),
        'url' => $type === 'rss' ? $url : null,
        'color' => $color,
    ]);

    $sourceId = (int) db()->lastInsertId();

    if ($type === 'rss') {
        $source = ['id' => $sourceId, 'url' => $url];
        $result = fetch_source($source);
        if ($result['error']) {
            flash('error', 'Bron toegevoegd, maar ophalen mislukte: ' . $result['error']);
        } else {
            flash('success', 'Bron toegevoegd en ' . $result['imported'] . ' berichten opgehaald.');
        }
    } else {
        flash('success', 'Bron toegevoegd.');
    }

    redirect('sources');
}

function toggle_source(int $id): void
{
    require_admin();
    verify_csrf();

    db()->prepare('UPDATE sources SET is_active = NOT is_active WHERE id = :id')->execute(['id' => $id]);
    redirect('sources');
}

function delete_source(int $id): void
{
    require_admin();
    verify_csrf();

    db()->prepare('DELETE FROM sources WHERE id = :id')->execute(['id' => $id]);
    flash('success', 'Bron en bijbehorende berichten verwijderd.');
    redirect('sources');
}

function refresh_source(int $id): void
{
    require_admin();
    verify_csrf();

    $statement = db()->prepare('SELECT * FROM sources WHERE id = :id AND type = "rss"');
    $statement->execute(['id' => $id]);
    $source = $statement->fetch();

    if (!$source) {
        abort(404, 'Bron niet gevonden.');
    }

    $result = fetch_source($source);
    if ($result['error']) {
        flash('error', 'Ophalen mislukte: ' . $result['error']);
    } else {
        flash('success', $result['imported'] . ' berichten opgehaald van ' . $source['name'] . '.');
    }

    redirect('sources');
}

function refresh_all_sources(): void
{
    require_admin();
    verify_csrf();

    $results = fetch_all_active_sources();
    $total = array_sum(array_column($results, 'imported'));
    flash('success', 'Vernieuwd: ' . $total . ' nieuwe/bijgewerkte berichten.');
    redirect('sources');
}

function cron_fetch(): void
{
    header('Content-Type: application/json');

    $token = $_GET['token'] ?? '';
    $expected = setting('cron_token');

    if ($expected === '' || !is_string($token) || !hash_equals($expected, (string) $token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'invalid token']);
        exit;
    }

    $results = fetch_all_active_sources();
    $total = array_sum(array_column($results, 'imported'));
    echo json_encode(['ok' => true, 'imported' => $total]);
    exit;
}
