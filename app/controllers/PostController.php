<?php

declare(strict_types=1);

function active_channels(int $appId): array
{
    $statement = db()->prepare('SELECT * FROM channels WHERE app_id = :id AND is_active = 1 ORDER BY created_at ASC');
    $statement->execute(['id' => $appId]);
    return $statement->fetchAll();
}

function find_post(int $id): array
{
    $statement = db()->prepare('SELECT * FROM posts WHERE id = :id');
    $statement->execute(['id' => $id]);
    $post = $statement->fetch();
    if (!$post) {
        abort(404, 'Post niet gevonden.');
    }
    return $post;
}

function post_new(int $appId): void
{
    require_admin();
    $app = find_app($appId);
    render('posts/edit', [
        'title' => 'Nieuwe post',
        'app' => $app,
        'post' => null,
        'channels' => active_channels($appId),
        'postChannels' => [],
    ]);
}

function post_edit(int $id): void
{
    require_admin();
    $post = find_post($id);
    $app = find_app((int) $post['app_id']);

    $statement = db()->prepare('SELECT * FROM post_channels WHERE post_id = :id');
    $statement->execute(['id' => $id]);
    $postChannels = [];
    foreach ($statement->fetchAll() as $row) {
        $postChannels[$row['channel_id']] = $row;
    }

    render('posts/edit', [
        'title' => 'Post bewerken',
        'app' => $app,
        'post' => $post,
        'channels' => active_channels((int) $app['id']),
        'postChannels' => $postChannels,
    ]);
}

function post_create(int $appId): void
{
    require_admin();
    verify_csrf();
    find_app($appId);
    save_post(null, $appId);
}

function post_update(int $id): void
{
    require_admin();
    verify_csrf();
    $post = find_post($id);
    save_post($id, (int) $post['app_id']);
}

function post_channel_errors(array $channel, array $post, array $override): array
{
    $platform = platform($channel['platform']);
    if (!$platform) {
        return ['Onbekend platform.'];
    }
    return validate_for_platform(
        $platform,
        trim((string) ($override['title'] ?? '')) ?: (string) $post['title'],
        trim((string) ($override['body'] ?? '')) ?: (string) $post['body'],
        (string) $post['link'],
        !empty($post['image_path']),
        (string) ($override['subreddit'] ?? '')
    );
}

function save_post(?int $postId, int $appId): void
{
    $existing = $postId ? find_post($postId) : null;
    $back = $postId ? 'posts/' . $postId . '/edit' : 'apps/' . $appId . '/posts/new';

    $title = mb_substr(clean('title'), 0, 300);
    $body = trim((string) ($_POST['body'] ?? ''));
    $link = clean('link');
    if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) {
        flash('error', 'De link is geen geldige URL.');
        redirect($back);
    }

    $image = $existing['image_path'] ?? null;
    if (!empty($_POST['remove_image'])) {
        $image = null;
    }
    $upload = handle_media_upload('image');
    if ($upload['error']) {
        flash('error', $upload['error']);
        redirect($back);
    }
    if ($upload['path'] !== null) {
        if ($upload['type'] !== 'image') {
            flash('error', 'Alleen afbeeldingen zijn toegestaan.');
            redirect($back);
        }
        $image = $upload['path'];
    }

    if ($title === '' && $body === '') {
        flash('error', 'Vul een titel of tekst in.');
        redirect($back);
    }

    $pdo = db();
    $now = gmdate('Y-m-d H:i:s');
    if ($postId) {
        $pdo->prepare('UPDATE posts SET title = :title, body = :body, link = :link, image_path = :image, updated_at = :now WHERE id = :id')
            ->execute(['title' => $title, 'body' => $body, 'link' => $link, 'image' => $image, 'now' => $now, 'id' => $postId]);
    } else {
        $pdo->prepare('INSERT INTO posts (app_id, title, body, link, image_path) VALUES (:app, :title, :body, :link, :image)')
            ->execute(['app' => $appId, 'title' => $title, 'body' => $body, 'link' => $link, 'image' => $image]);
        $postId = (int) $pdo->lastInsertId();
    }
    $post = find_post($postId);

    $allowed = [];
    foreach (active_channels($appId) as $channel) {
        $allowed[(int) $channel['id']] = $channel;
    }
    $selected = array_values(array_intersect(array_map('intval', (array) ($_POST['channels'] ?? [])), array_keys($allowed)));

    $keep = $pdo->prepare('SELECT channel_id FROM post_channels WHERE post_id = :id');
    $keep->execute(['id' => $postId]);
    foreach ($keep->fetchAll(PDO::FETCH_COLUMN) as $channelId) {
        if (!in_array((int) $channelId, $selected, true)) {
            $pdo->prepare('DELETE FROM post_channels WHERE post_id = :p AND channel_id = :c')->execute(['p' => $postId, 'c' => $channelId]);
        }
    }

    $problems = 0;
    foreach ($selected as $channelId) {
        $override = [
            'title' => trim((string) ($_POST['ch_title'][$channelId] ?? '')),
            'body' => trim((string) ($_POST['ch_body'][$channelId] ?? '')),
            'subreddit' => preg_replace('#^/?r/#i', '', trim((string) ($_POST['ch_sub'][$channelId] ?? ''))),
        ];
        $errors = post_channel_errors($allowed[$channelId], $post, $override);
        $problems += count($errors) > 0 ? 1 : 0;

        $pdo->prepare('INSERT INTO post_channels (post_id, channel_id, title, body, subreddit, status, result)
            VALUES (:p, :c, :title, :body, :sub, :status, :result)
            ON CONFLICT(post_id, channel_id) DO UPDATE SET title = excluded.title, body = excluded.body, subreddit = excluded.subreddit,
                status = CASE WHEN post_channels.status = \'published\' THEN \'published\' ELSE excluded.status END,
                result = CASE WHEN post_channels.status = \'published\' THEN post_channels.result ELSE excluded.result END')
            ->execute([
                'p' => $postId, 'c' => $channelId,
                'title' => $override['title'], 'body' => $override['body'], 'sub' => $override['subreddit'],
                'status' => $errors ? 'draft' : 'ready',
                'result' => $errors ? implode(' ', $errors) : null,
            ]);
    }

    refresh_post_status($postId);

    if ($problems > 0) {
        flash('error', 'Opgeslagen, maar ' . $problems . ' kanaal/kanalen voldoen nog niet aan de eisen.');
    } else {
        flash('success', 'Post opgeslagen.');
    }
    redirect('posts/' . $postId . '/edit');
}

function refresh_post_status(int $postId): void
{
    $statement = db()->prepare('SELECT status FROM post_channels WHERE post_id = :id');
    $statement->execute(['id' => $postId]);
    $statuses = $statement->fetchAll(PDO::FETCH_COLUMN);

    $status = 'draft';
    if ($statuses) {
        if (!array_diff($statuses, ['published'])) {
            $status = 'published';
        } elseif (!array_diff($statuses, ['ready', 'published'])) {
            $status = 'ready';
        }
    }
    db()->prepare('UPDATE posts SET status = :status WHERE id = :id')->execute(['status' => $status, 'id' => $postId]);
}

function post_delete(int $id): void
{
    require_admin();
    verify_csrf();
    $post = find_post($id);
    db()->prepare('DELETE FROM posts WHERE id = :id')->execute(['id' => $id]);
    flash('success', 'Post verwijderd.');
    redirect('apps/' . $post['app_id']);
}

function absolute_url(string $path): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $path;
}

function post_publish(int $id): void
{
    require_admin();
    verify_csrf();
    $post = find_post($id);
    $app = find_app((int) $post['app_id']);

    $statement = db()->prepare('SELECT post_channels.*, channels.platform, channels.label, channels.handle, channels.webhook_url
        FROM post_channels JOIN channels ON channels.id = post_channels.channel_id
        WHERE post_channels.post_id = :id AND post_channels.status != \'published\'');
    $statement->execute(['id' => $id]);
    $rows = $statement->fetchAll();

    if (!$rows) {
        flash('error', 'Er zijn geen kanalen om te publiceren.');
        redirect('posts/' . $id . '/edit');
    }

    $published = 0;
    $failed = 0;
    foreach ($rows as $row) {
        $platform = platform($row['platform']);
        $errors = post_channel_errors($row, $post, $row);
        if ($errors || !$platform) {
            set_channel_result((int) $row['id'], 'draft', implode(' ', $errors));
            $failed++;
            continue;
        }

        $title = trim((string) $row['title']) ?: (string) $post['title'];
        $text = compose_text($platform, trim((string) $row['body']) ?: (string) $post['body'], (string) $post['link']);

        if (empty($row['webhook_url'])) {
            set_channel_result((int) $row['id'], 'ready', 'Geen webhook gekoppeld: kopieer de tekst en plaats handmatig.');
            continue;
        }

        $payload = json_encode([
            'app' => $app['name'],
            'platform' => $row['platform'],
            'channel' => $row['label'],
            'handle' => $row['handle'],
            'title' => $title,
            'text' => $text,
            'link' => $post['link'],
            'image_url' => $post['image_path'] ? absolute_url($post['image_path']) : null,
            'subreddit' => $row['subreddit'] ?: null,
            'sent_at' => gmdate('c'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $result = send_json($row['webhook_url'], (string) $payload);
        if ($result['ok']) {
            db()->prepare('UPDATE post_channels SET status = \'published\', result = :r, published_at = :now WHERE id = :id')
                ->execute(['r' => $result['message'], 'now' => gmdate('Y-m-d H:i:s'), 'id' => $row['id']]);
            $published++;
        } else {
            set_channel_result((int) $row['id'], 'failed', $result['message']);
            $failed++;
        }
    }

    refresh_post_status($id);
    if ($failed > 0) {
        flash('error', $published . ' verstuurd, ' . $failed . ' mislukt of ongeldig. Zie de kanaalstatus.');
    } elseif ($published > 0) {
        flash('success', $published . ' kanaal/kanalen verstuurd.');
    } else {
        flash('success', 'Klaar. Kanalen zonder webhook staan klaar om handmatig te plaatsen.');
    }
    redirect('posts/' . $id . '/edit');
}

function set_channel_result(int $id, string $status, string $result): void
{
    db()->prepare('UPDATE post_channels SET status = :s, result = :r WHERE id = :id')->execute(['s' => $status, 'r' => $result, 'id' => $id]);
}

function send_json(string $endpoint, string $payload): array
{
    $headers = ['Content-Type: application/json'];

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'message' => $curlError ?: 'verbinding mislukt'];
        }
        return ['ok' => $httpCode >= 200 && $httpCode < 300, 'message' => 'HTTP ' . $httpCode];
    }

    $context = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $payload, 'timeout' => 20, 'ignore_errors' => true]]);
    $response = @file_get_contents($endpoint, false, $context);
    $statusLine = $http_response_header[0] ?? '';
    if ($response === false) {
        return ['ok' => false, 'message' => 'verbinding mislukt'];
    }
    $ok = preg_match('/\s(\d{3})\s/', $statusLine, $m) && (int) $m[1] < 300;
    return ['ok' => (bool) $ok, 'message' => $statusLine ?: 'onbekende fout'];
}
