<?php

declare(strict_types=1);

function apps_page(): void
{
    require_admin();

    $apps = db()->query('SELECT apps.*,
        (SELECT COUNT(*) FROM channels WHERE channels.app_id = apps.id) AS channel_count,
        (SELECT COUNT(*) FROM posts WHERE posts.app_id = apps.id) AS post_count
        FROM apps ORDER BY apps.created_at DESC')->fetchAll();

    render('apps/index', ['title' => 'Apps', 'apps' => $apps]);
}

function app_create(): void
{
    require_admin();
    verify_csrf();

    $name = clean('name');
    $color = clean('color');
    if ($name === '') {
        flash('error', 'Geef je app een naam.');
        redirect('apps');
    }
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        $color = '#da7756';
    }

    db()->prepare('INSERT INTO apps (name, description, color) VALUES (:name, :description, :color)')->execute([
        'name' => mb_substr($name, 0, 120),
        'description' => mb_substr(clean('description'), 0, 500),
        'color' => $color,
    ]);

    redirect('apps/' . db()->lastInsertId());
}

function find_app(int $id): array
{
    $statement = db()->prepare('SELECT * FROM apps WHERE id = :id');
    $statement->execute(['id' => $id]);
    $app = $statement->fetch();
    if (!$app) {
        abort(404, 'App niet gevonden.');
    }
    return $app;
}

function app_show(int $id): void
{
    require_admin();
    $app = find_app($id);

    $channels = db()->prepare('SELECT * FROM channels WHERE app_id = :id ORDER BY created_at ASC');
    $channels->execute(['id' => $id]);

    $posts = db()->prepare('SELECT * FROM posts WHERE app_id = :id ORDER BY updated_at DESC');
    $posts->execute(['id' => $id]);
    $posts = $posts->fetchAll();

    $chips = db()->prepare('SELECT post_channels.post_id, post_channels.status, channels.platform FROM post_channels
        JOIN channels ON channels.id = post_channels.channel_id
        JOIN posts ON posts.id = post_channels.post_id WHERE posts.app_id = :id');
    $chips->execute(['id' => $id]);
    $chipsByPost = [];
    foreach ($chips->fetchAll() as $chip) {
        $chipsByPost[$chip['post_id']][] = $chip;
    }

    render('apps/show', [
        'title' => $app['name'],
        'app' => $app,
        'channels' => $channels->fetchAll(),
        'posts' => $posts,
        'chipsByPost' => $chipsByPost,
    ]);
}

function app_delete(int $id): void
{
    require_admin();
    verify_csrf();
    find_app($id);
    db()->prepare('DELETE FROM apps WHERE id = :id')->execute(['id' => $id]);
    flash('success', 'App verwijderd.');
    redirect('apps');
}

function channel_create(int $appId): void
{
    require_admin();
    verify_csrf();
    find_app($appId);

    $key = clean('platform');
    $platform = platform($key);
    $webhook = clean('webhook_url');

    if (!$platform) {
        flash('error', 'Kies een geldig platform.');
        redirect('apps/' . $appId);
    }
    if ($webhook !== '' && !filter_var($webhook, FILTER_VALIDATE_URL)) {
        flash('error', 'De webhook-URL is niet geldig.');
        redirect('apps/' . $appId);
    }

    db()->prepare('INSERT INTO channels (app_id, platform, label, handle, webhook_url) VALUES (:app_id, :platform, :label, :handle, :webhook)')->execute([
        'app_id' => $appId,
        'platform' => $key,
        'label' => mb_substr(clean('label') ?: $platform['name'], 0, 120),
        'handle' => mb_substr(clean('handle'), 0, 120),
        'webhook' => $webhook !== '' ? $webhook : null,
    ]);

    flash('success', $platform['name'] . ' gekoppeld.');
    redirect('apps/' . $appId);
}

function find_channel(int $id): array
{
    $statement = db()->prepare('SELECT * FROM channels WHERE id = :id');
    $statement->execute(['id' => $id]);
    $channel = $statement->fetch();
    if (!$channel) {
        abort(404, 'Kanaal niet gevonden.');
    }
    return $channel;
}

function channel_toggle(int $id): void
{
    require_admin();
    verify_csrf();
    $channel = find_channel($id);
    db()->prepare('UPDATE channels SET is_active = NOT is_active WHERE id = :id')->execute(['id' => $id]);
    redirect('apps/' . $channel['app_id']);
}

function channel_delete(int $id): void
{
    require_admin();
    verify_csrf();
    $channel = find_channel($id);
    db()->prepare('DELETE FROM channels WHERE id = :id')->execute(['id' => $id]);
    flash('success', 'Kanaal ontkoppeld.');
    redirect('apps/' . $channel['app_id']);
}
