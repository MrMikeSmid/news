<?php

declare(strict_types=1);

function wall_page(): void
{
    require_admin();

    $filter = clean_query('category');
    $categories = db()->query("SELECT DISTINCT category FROM sources ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

    $sql = 'SELECT posts.*, sources.name AS source_name, sources.category AS source_category, sources.color AS source_color, sources.type AS source_type
            FROM posts LEFT JOIN sources ON sources.id = posts.source_id';
    $params = [];
    if ($filter !== '') {
        $sql .= ' WHERE sources.category = :category';
        $params['category'] = $filter;
    }
    $sql .= ' ORDER BY posts.published_at DESC LIMIT 100';

    $statement = db()->prepare($sql);
    $statement->execute($params);
    $posts = $statement->fetchAll();

    $manualSource = ensure_manual_source();

    render('wall', [
        'title' => 'Wall',
        'posts' => $posts,
        'categories' => $categories,
        'activeCategory' => $filter,
        'manualSourceId' => $manualSource,
    ]);
}

function clean_query(string $key): string
{
    return trim((string) ($_GET[$key] ?? ''));
}

function ensure_manual_source(): int
{
    $statement = db()->query("SELECT id FROM sources WHERE type = 'manual' ORDER BY id ASC LIMIT 1");
    $id = $statement->fetchColumn();
    if ($id) {
        return (int) $id;
    }
    db()->prepare("INSERT INTO sources (name, type, category, color) VALUES ('Eigen berichten', 'manual', 'eigen', '#da7756')")->execute();
    return (int) db()->lastInsertId();
}

function create_manual_post(): void
{
    require_admin();
    verify_csrf();

    $text = clean('body');
    $link = clean('link');
    $sourceId = ensure_manual_source();

    $image = handle_media_upload('image');
    if ($image['error']) {
        flash('error', $image['error']);
        redirect('wall');
    }
    $video = handle_media_upload('video');
    if ($video['error']) {
        flash('error', $video['error']);
        redirect('wall');
    }

    if ($text === '' && $image['path'] === null && $video['path'] === null) {
        flash('error', 'Schrijf iets, of voeg een afbeelding/video toe.');
        redirect('wall');
    }
    if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) {
        flash('error', 'De link is niet geldig.');
        redirect('wall');
    }

    $mediaType = 'text';
    if ($video['path'] !== null) {
        $mediaType = 'video';
    } elseif ($image['path'] !== null) {
        $mediaType = 'image';
    } elseif ($link !== '') {
        $mediaType = 'link';
    }

    $statement = db()->prepare('INSERT INTO posts (source_id, guid, title, body, link, image_path, video_path, media_type, published_at)
        VALUES (:source_id, :guid, NULL, :body, :link, :image_path, :video_path, :media_type, :published_at)');
    $statement->execute([
        'source_id' => $sourceId,
        'guid' => bin2hex(random_bytes(12)),
        'body' => mb_substr($text, 0, 4000),
        'link' => $link !== '' ? $link : null,
        'image_path' => $image['path'],
        'video_path' => $video['path'],
        'media_type' => $mediaType,
        'published_at' => gmdate('Y-m-d H:i:s'),
    ]);

    flash('success', 'Geplaatst op de wall.');
    redirect('wall');
}

function delete_post(int $id): void
{
    require_admin();
    verify_csrf();

    db()->prepare('DELETE FROM posts WHERE id = :id')->execute(['id' => $id]);
    redirect('wall');
}
