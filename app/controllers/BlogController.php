<?php

declare(strict_types=1);

function blogs_page(): void
{
    require_admin();
    $blogs = db()->query('SELECT * FROM blogs ORDER BY updated_at DESC')->fetchAll();
    render('blogs/index', ['title' => 'Blogs', 'blogs' => $blogs]);
}

function blog_new(): void
{
    require_admin();
    render('blogs/edit', ['title' => 'Nieuwe blog', 'blog' => null]);
}

function blog_create(): void
{
    require_admin();
    verify_csrf();

    $title = clean('title');
    $content = (string) ($_POST['content'] ?? '');
    $excerpt = clean('excerpt');
    $status = clean('status') === 'published' ? 'published' : 'draft';

    if ($title === '' || trim(strip_tags($content)) === '') {
        flash('error', 'Titel en inhoud zijn verplicht.');
        redirect('blogs/new');
    }

    $slug = unique_blog_slug(slugify($title));
    $cover = handle_media_upload('cover_image');
    if ($cover['error']) {
        flash('error', $cover['error']);
        redirect('blogs/new');
    }

    $statement = db()->prepare('INSERT INTO blogs (title, slug, excerpt, content, cover_image, status)
        VALUES (:title, :slug, :excerpt, :content, :cover_image, :status)');
    $statement->execute([
        'title' => mb_substr($title, 0, 250),
        'slug' => $slug,
        'excerpt' => mb_substr($excerpt, 0, 500),
        'content' => $content,
        'cover_image' => $cover['path'],
        'status' => $status,
    ]);

    flash('success', 'Blog opgeslagen.');
    redirect('blogs/' . db()->lastInsertId() . '/edit');
}

function blog_edit(int $id): void
{
    require_admin();
    $blog = find_blog($id);
    render('blogs/edit', ['title' => 'Blog bewerken', 'blog' => $blog]);
}

function blog_update(int $id): void
{
    require_admin();
    verify_csrf();
    $blog = find_blog($id);

    $title = clean('title');
    $content = (string) ($_POST['content'] ?? '');
    $excerpt = clean('excerpt');
    $status = clean('status') === 'published' ? 'published' : 'draft';

    if ($title === '' || trim(strip_tags($content)) === '') {
        flash('error', 'Titel en inhoud zijn verplicht.');
        redirect('blogs/' . $id . '/edit');
    }

    $cover = handle_media_upload('cover_image');
    if ($cover['error']) {
        flash('error', $cover['error']);
        redirect('blogs/' . $id . '/edit');
    }

    $statement = db()->prepare('UPDATE blogs SET title = :title, excerpt = :excerpt, content = :content,
        cover_image = COALESCE(:cover_image, cover_image), status = :status, updated_at = :updated_at WHERE id = :id');
    $statement->execute([
        'title' => mb_substr($title, 0, 250),
        'excerpt' => mb_substr($excerpt, 0, 500),
        'content' => $content,
        'cover_image' => $cover['path'],
        'status' => $status,
        'updated_at' => gmdate('Y-m-d H:i:s'),
        'id' => $id,
    ]);

    flash('success', 'Blog bijgewerkt.');
    redirect('blogs/' . $id . '/edit');
}

function blog_delete(int $id): void
{
    require_admin();
    verify_csrf();
    db()->prepare('DELETE FROM blogs WHERE id = :id')->execute(['id' => $id]);
    flash('success', 'Blog verwijderd.');
    redirect('blogs');
}

function blog_send(int $id): void
{
    require_admin();
    verify_csrf();
    $blog = find_blog($id);

    $endpoint = setting('api_endpoint_url');
    if ($endpoint === '' || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
        flash('error', 'Stel eerst een geldig API-endpoint in bij Instellingen.');
        redirect('blogs/' . $id . '/edit');
    }

    $payload = json_encode([
        'title' => $blog['title'],
        'slug' => $blog['slug'],
        'excerpt' => $blog['excerpt'],
        'content' => $blog['content'],
        'cover_image' => $blog['cover_image'],
        'published_at' => gmdate('c'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $result = send_blog_payload($endpoint, setting('api_key'), (string) $payload);

    $statement = db()->prepare('UPDATE blogs SET sent_at = :sent_at, send_status = :status WHERE id = :id');
    $statement->execute([
        'sent_at' => gmdate('Y-m-d H:i:s'),
        'status' => $result['ok'] ? 'ok' : 'error: ' . $result['message'],
        'id' => $id,
    ]);

    if ($result['ok']) {
        flash('success', 'Blog verstuurd naar de externe site.');
    } else {
        flash('error', 'Versturen mislukt: ' . $result['message']);
    }

    redirect('blogs/' . $id . '/edit');
}

function send_blog_payload(string $endpoint, string $apiKey, string $payload): array
{
    $headers = ['Content-Type: application/json'];
    if ($apiKey !== '') {
        $headers[] = 'Authorization: Bearer ' . $apiKey;
    }

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
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'message' => $curlError ?: 'onbekende fout'];
        }
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['ok' => true, 'message' => 'HTTP ' . $httpCode];
        }
        return ['ok' => false, 'message' => 'HTTP ' . $httpCode];
    }

    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $payload,
        'timeout' => 20,
        'ignore_errors' => true,
    ]]);
    $response = @file_get_contents($endpoint, false, $context);
    $statusLine = $http_response_header[0] ?? '';
    if ($response === false) {
        return ['ok' => false, 'message' => 'verbinding mislukt'];
    }
    if (preg_match('/\s(\d{3})\s/', $statusLine, $matches) && (int) $matches[1] < 300) {
        return ['ok' => true, 'message' => $statusLine];
    }
    return ['ok' => false, 'message' => $statusLine ?: 'onbekende fout'];
}

function find_blog(int $id): array
{
    $statement = db()->prepare('SELECT * FROM blogs WHERE id = :id');
    $statement->execute(['id' => $id]);
    $blog = $statement->fetch();
    if (!$blog) {
        abort(404, 'Blog niet gevonden.');
    }
    return $blog;
}

function unique_blog_slug(string $base): string
{
    $slug = $base;
    $i = 1;
    while (true) {
        $statement = db()->prepare('SELECT 1 FROM blogs WHERE slug = :slug');
        $statement->execute(['slug' => $slug]);
        if (!$statement->fetchColumn()) {
            return $slug;
        }
        $i++;
        $slug = $base . '-' . $i;
    }
}
